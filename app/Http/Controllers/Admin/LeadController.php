<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\WhatsappConversation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LeadController extends Controller
{
    private const QUOTE_PDF_DISK = 'local';

    public function board(): View
    {
        $columns = $this->boardColumns();
        $leads = Lead::query()
            ->whereIn('status', collect($columns)->pluck('statuses')->flatten()->all())
            ->latest()
            ->get();

        $boardColumns = collect($columns)
            ->map(function (array $column) use ($leads) {
                $columnLeads = $leads
                    ->whereIn('status', $column['statuses'])
                    ->values();

                return [
                    ...$column,
                    'leads' => $columnLeads,
                    'count' => $columnLeads->count(),
                    'total' => $columnLeads->sum(fn (Lead $lead) => $this->boardLeadAmount($lead)),
                ];
            })
            ->all();

        return view('admin.leads.board', [
            'columns' => $boardColumns,
        ]);
    }

    public function index(Request $request, ?Lead $lead = null): View
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('q')->toString());

        $leadsQuery = Lead::query()
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('club', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->latest();

        $leads = $leadsQuery->paginate(14)->withQueryString();

        $selectedLead = $lead
            ? $lead->fresh()
            : $leads->first();

        $selectedConversation = $selectedLead
            ? WhatsappConversation::query()
                ->where(function ($query) use ($selectedLead) {
                    $query
                        ->where('lead_id', $selectedLead->id)
                        ->orWhere('id', $selectedLead->whatsapp_conversation_id);
                })
                ->latest('last_message_at')
                ->first()
            : null;

        $statuses = $this->statuses();

        return view('admin.leads.index', [
            'leads' => $leads,
            'selectedLead' => $selectedLead,
            'selectedConversation' => $selectedConversation,
            'statuses' => $statuses,
            'stats' => $this->stats($statuses),
            'currentStatus' => $status,
            'search' => $search,
        ]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $statuses = array_keys($this->statuses());

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in($statuses)],
            'quote_number' => ['nullable', 'string', 'max:50', Rule::unique('leads', 'quote_number')->ignore($lead->id)],
            'quote_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'payment_link' => ['nullable', 'url', 'max:2048'],
            'payment_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'quote_pdf' => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:20480'],
        ]);

        if ($data['status'] === 'quote_sent' && empty($data['quote_amount'])) {
            return back()
                ->withErrors(['quote_amount' => 'Inserisci l’importo del preventivo prima di segnare il lead come preventivo inviato.'])
                ->withInput();
        }

        if ($data['status'] === 'link_sent' && (empty($data['payment_link']) || empty($data['payment_amount']))) {
            return back()
                ->withErrors(['payment_link' => 'Inserisci link e importo pagamento prima di segnare il lead come link inviato.'])
                ->withInput();
        }

        $attributes = [
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
            'quote_number' => $data['quote_number'] ?: $lead->quote_number,
            'quote_amount' => $data['quote_amount'] ?? $lead->quote_amount,
            'payment_link' => $data['payment_link'] ?? $lead->payment_link,
            'payment_amount' => $data['payment_amount'] ?? $lead->payment_amount,
        ];

        if ($request->hasFile('quote_pdf')) {
            if ($lead->quote_pdf_path && Storage::disk($lead->quote_pdf_disk ?? self::QUOTE_PDF_DISK)->exists($lead->quote_pdf_path)) {
                Storage::disk($lead->quote_pdf_disk ?? self::QUOTE_PDF_DISK)->delete($lead->quote_pdf_path);
            }

            $file = $request->file('quote_pdf');
            $originalName = $file->getClientOriginalName() ?: 'preventivo.pdf';
            $baseName = Str::limit(Str::slug(pathinfo($originalName, PATHINFO_FILENAME)), 90, '');
            $filename = ($baseName ?: 'preventivo') . '-' . now()->format('YmdHis') . '-' . Str::random(6) . '.pdf';
            $path = $file->storeAs("leads/{$lead->id}/quotes", $filename, self::QUOTE_PDF_DISK);

            $attributes = [
                ...$attributes,
                'quote_pdf_disk' => self::QUOTE_PDF_DISK,
                'quote_pdf_path' => $path,
                'quote_pdf_filename' => $originalName,
                'quote_pdf_mime_type' => $file->getMimeType() ?: 'application/pdf',
                'quote_pdf_size' => $file->getSize(),
                'quote_pdf_uploaded_at' => now(),
            ];
        }

        $lead->fill($attributes)->save();

        return redirect()
            ->route('admin.leads.index', ['lead' => $lead])
            ->with('status', 'Lead aggiornato.');
    }

    public function showQuotePdf(Lead $lead)
    {
        if (! $lead->quote_pdf_path || ! Storage::disk($lead->quote_pdf_disk ?? self::QUOTE_PDF_DISK)->exists($lead->quote_pdf_path)) {
            abort(404);
        }

        return Storage::disk($lead->quote_pdf_disk ?? self::QUOTE_PDF_DISK)->response(
            $lead->quote_pdf_path,
            $lead->quote_pdf_filename ?: 'preventivo.pdf',
            ['Content-Type' => $lead->quote_pdf_mime_type ?: 'application/pdf'],
            'inline'
        );
    }

    public function createStripePaymentLink(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'payment_amount' => ['required', 'numeric', 'min:0.50', 'max:99999999.99'],
        ]);

        $secretKey = config('services.stripe.secret_key');

        if (! $secretKey) {
            throw ValidationException::withMessages([
                'payment_amount' => 'Chiave Stripe mancante. Aggiungi STRIPE_SECRET_KEY nel file .env dell’admin.',
            ]);
        }

        $amount = (float) $data['payment_amount'];
        $amountInCents = (int) round($amount * 100);
        $currency = strtolower((string) config('services.stripe.currency', 'eur'));
        $quoteNumber = $this->ensureQuoteNumber($lead);
        $description = $lead->club ?: $lead->name ?: "Lead #{$lead->id}";
        $payload = [
            'mode' => 'payment',
            'success_url' => config('services.stripe.success_url'),
            'cancel_url' => config('services.stripe.cancel_url'),
            'client_reference_id' => (string) $lead->id,
            'metadata' => [
                'lead_id' => (string) $lead->id,
                'lead_uuid' => (string) $lead->uuid,
                'quote_number' => $quoteNumber,
            ],
            'line_items' => [
                [
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => $currency,
                        'unit_amount' => $amountInCents,
                        'product_data' => [
                            'name' => "Preventivo {$quoteNumber}",
                            'description' => Str::limit($description, 250, ''),
                            'metadata' => [
                                'lead_id' => (string) $lead->id,
                                'quote_number' => $quoteNumber,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ($lead->email) {
            $payload['customer_email'] = $lead->email;
        }

        try {
            $response = Http::asForm()
                ->withToken($secretKey)
                ->post('https://api.stripe.com/v1/checkout/sessions', $payload);
        } catch (ConnectionException $exception) {
            throw ValidationException::withMessages([
                'payment_amount' => 'Stripe non raggiungibile. Controlla la connessione e riprova.',
            ]);
        }

        if ($response->failed() || ! $response->json('url')) {
            throw ValidationException::withMessages([
                'payment_amount' => 'Creazione link Stripe fallita: ' . ($response->json('error.message') ?: 'errore sconosciuto'),
            ]);
        }

        $lead->fill([
            'quote_number' => $quoteNumber,
            'payment_amount' => $amount,
            'payment_link' => $response->json('url'),
            'status' => 'link_sent',
        ])->save();

        return redirect()
            ->route('admin.leads.index', ['lead' => $lead])
            ->with('status', 'Link pagamento Stripe creato e salvato sul lead.');
    }

    private function ensureQuoteNumber(Lead $lead): string
    {
        if ($lead->quote_number) {
            return $lead->quote_number;
        }

        $quoteNumber = sprintf('PREV-%06d', $lead->id);

        $lead->forceFill([
            'quote_number' => $quoteNumber,
        ])->saveQuietly();

        $lead->quote_number = $quoteNumber;

        return $quoteNumber;
    }

    private function stats(array $statuses): array
    {
        $counts = Lead::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => Lead::count(),
            'open' => Lead::whereNotIn('status', ['order_completed'])->count(),
            'ready' => (int) ($counts['completed'] ?? 0),
            'paid' => (int) ($counts['order_completed'] ?? 0),
            'by_status' => collect($statuses)
                ->mapWithKeys(fn ($label, $key) => [$key => (int) ($counts[$key] ?? 0)])
                ->all(),
        ];
    }

    private function statuses(): array
    {
        return [
            'pre' => 'Pre lead',
            'confirmed' => 'Confermato',
            'completed' => 'Lavorare',
            'quote_sent' => 'Prev. inv.',
            'link_sent' => 'Link inv.',
            'order_completed' => 'Completato',
        ];
    }

    private function boardColumns(): array
    {
        return [
            [
                'key' => 'new',
                'label' => 'Nuovo lead',
                'statuses' => ['pre', 'confirmed', 'completed'],
                'accent' => 'text-black-nike',
            ],
            [
                'key' => 'quote_sent',
                'label' => 'Preventivo inviato',
                'statuses' => ['quote_sent'],
                'accent' => 'text-black-nike',
            ],
            [
                'key' => 'link_sent',
                'label' => 'Link inviato',
                'statuses' => ['link_sent'],
                'accent' => 'text-black-nike',
            ],
            [
                'key' => 'completed',
                'label' => 'Completato',
                'statuses' => ['order_completed'],
                'accent' => 'text-whatsapp',
            ],
        ];
    }

    private function boardLeadAmount(Lead $lead): float
    {
        return (float) ($lead->payment_amount ?: $lead->quote_amount ?: 0);
    }
}
