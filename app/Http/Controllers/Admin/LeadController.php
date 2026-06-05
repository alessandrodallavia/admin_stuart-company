<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\Ga4MeasurementService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    public function update(Request $request, Lead $lead, Ga4MeasurementService $ga4): RedirectResponse
    {
        $statuses = array_keys($this->statuses());
        $previousStatus = $lead->status;

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
        $lead->refresh();

        $this->sendQuoteSentEvent($lead, $previousStatus, $ga4);

        return redirect()
            ->route('admin.leads.index', ['lead' => $lead])
            ->with('status', 'Lead aggiornato.');
    }

    private function sendQuoteSentEvent(Lead $lead, ?string $previousStatus, Ga4MeasurementService $ga4): void
    {
        if ($lead->status !== 'quote_sent' || $previousStatus === 'quote_sent' || $lead->ga4_quote_sent_at) {
            return;
        }

        try {
            $ga4->sendQuoteSent($lead);

            $lead->forceFill([
                'ga4_quote_sent_at' => now(),
                'ga4_quote_sent_status' => 'sent',
                'ga4_quote_sent_error' => null,
            ])->save();
        } catch (\Throwable $exception) {
            Log::warning('Invio quote_sent a GA4 fallito', [
                'lead_id' => $lead->id,
                'lead_uuid' => $lead->uuid,
                'error' => $exception->getMessage(),
            ]);

            $lead->forceFill([
                'ga4_quote_sent_status' => 'failed',
                'ga4_quote_sent_error' => $exception->getMessage(),
            ])->save();
        }
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

        if ($currency !== 'eur') {
            throw ValidationException::withMessages([
                'payment_amount' => 'L’addebito SEPA Stripe richiede valuta EUR.',
            ]);
        }

        $quoteNumber = $this->ensureQuoteNumber($lead);
        $token = $lead->payment_checkout_token ?: Str::random(48);
        $description = $lead->club ?: $lead->name ?: "Lead #{$lead->id}";
        $stripeCustomerId = $this->ensureStripeCustomer($lead, $secretKey);
        $payload = [
            'mode' => 'payment',
            'success_url' => config('services.stripe.success_url'),
            'cancel_url' => config('services.stripe.cancel_url'),
            'customer' => $stripeCustomerId,
            'client_reference_id' => (string) $lead->id,
            'metadata' => [
                'lead_id' => (string) $lead->id,
                'lead_uuid' => (string) $lead->uuid,
                'quote_number' => $quoteNumber,
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'lead_id' => (string) $lead->id,
                    'lead_uuid' => (string) $lead->uuid,
                    'quote_number' => $quoteNumber,
                ],
            ],
            'line_items' => [
                [
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => $currency,
                        'unit_amount' => $amountInCents,
                        'tax_behavior' => 'inclusive',
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
            'payment_checkout_token' => $token,
            'payment_link' => $response->json('url'),
            'status' => 'link_sent',
        ])->save();
        $lead->refresh();

        $this->sendPaymentLinkSentEvent($lead, $ga4 = app(Ga4MeasurementService::class));

        return redirect()
            ->route('admin.leads.index', ['lead' => $lead])
            ->with('status', 'Link pagamento Stripe creato e salvato sul lead.');
    }

    public function sendStripePaymentLinkWhatsapp(Lead $lead): RedirectResponse
    {
        if (! $lead->payment_link || ! $lead->payment_amount) {
            return back()->withErrors([
                'payment_link' => 'Crea prima un link Stripe con relativo importo.',
            ]);
        }

        $conversation = $this->findOrCreateWhatsappConversation($lead);

        if (! $conversation) {
            return back()->withErrors([
                'payment_link' => 'Nessuna conversazione WhatsApp o telefono collegato a questo lead.',
            ]);
        }

        $amount = number_format((float) $lead->payment_amount, 2, ',', '.');
        $body = "Importo preventivo: € {$amount}\n\nScegli come preferisci procedere:\n\n- Paga ora: carta di credito/debito, Amazon Pay, Google Pay, Apple Pay, PayPal, Satispay o addebito SEPA.\n- Bonifico bancario: ti invio la proforma con tutti i dati bancari.";
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $conversation->contact_phone,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => [
                    'text' => $body,
                ],
                'footer' => [
                    'text' => 'Stuart Company',
                ],
                'action' => [
                    'buttons' => [
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'pay_now_link_request',
                                'title' => 'Paga ora',
                            ],
                        ],
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'bank_transfer_proforma_request',
                                'title' => 'Bonifico bancario',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withToken(config('services.whatsapp.token'))
            ->post('https://graph.facebook.com/v25.0/'.config('services.whatsapp.phone_number_id').'/messages', $payload);

        $message = WhatsappMessage::create([
            'whatsapp_conversation_id' => $conversation->id,
            'provider_message_id' => $response->json('messages.0.id'),
            'direction' => 'outbound',
            'source' => 'user',
            'type' => 'interactive',
            'status' => $response->successful() ? 'sent' : 'failed',
            'from_phone' => config('services.whatsapp.phone_number_id'),
            'to_phone' => $conversation->contact_phone,
            'body' => $body,
            'payload' => [
                'request' => $payload,
                'response' => $response->json(),
            ],
            'error_code' => $response->json('error.code'),
            'error_message' => $response->json('error.message'),
            'sent_at' => $response->successful() ? now() : null,
            'failed_at' => $response->failed() ? now() : null,
        ]);

        $conversation->forceFill([
            'assigned_user_id' => Auth::guard('admin')->id(),
            'needs_human' => false,
            'last_message_at' => $message->created_at,
        ])->save();

        if ($response->failed()) {
            Log::warning('Invio link pagamento WhatsApp fallito', [
                'lead_id' => $lead->id,
                'conversation_id' => $conversation->id,
                'response' => $response->json(),
            ]);

            return back()->withErrors([
                'payment_link' => 'Invio WhatsApp fallito: '.($message->error_message ?? 'errore sconosciuto'),
            ]);
        }

        return redirect()
            ->route('admin.leads.index', ['lead' => $lead])
            ->with('status', 'Link pagamento inviato su WhatsApp con pulsante.');
    }

    public function sendQuotePdfWhatsapp(Lead $lead): RedirectResponse
    {
        if (! $lead->quote_pdf_path || ! Storage::disk($lead->quote_pdf_disk ?? self::QUOTE_PDF_DISK)->exists($lead->quote_pdf_path)) {
            return back()->withErrors([
                'quote_pdf' => 'Carica prima un PDF preventivo valido.',
            ]);
        }

        $conversation = $this->findOrCreateWhatsappConversation($lead);

        if (! $conversation) {
            return back()->withErrors([
                'quote_pdf' => 'Nessuna conversazione WhatsApp o telefono collegato a questo lead.',
            ]);
        }

        $body = 'In allegato il preventivo';
        $filePath = Storage::disk($lead->quote_pdf_disk ?? self::QUOTE_PDF_DISK)->path($lead->quote_pdf_path);
        $filename = $lead->quote_pdf_filename ?: 'preventivo.pdf';

        $file = fopen($filePath, 'r');

        try {
            $mediaResponse = Http::withToken(config('services.whatsapp.token'))
                ->attach('file', $file, $filename)
                ->post('https://graph.facebook.com/v25.0/'.config('services.whatsapp.phone_number_id').'/media', [
                    'messaging_product' => 'whatsapp',
                    'type' => $lead->quote_pdf_mime_type ?: 'application/pdf',
                ]);
        } finally {
            if (is_resource($file)) {
                fclose($file);
            }
        }

        if ($mediaResponse->failed() || ! $mediaResponse->json('id')) {
            Log::warning('Upload preventivo WhatsApp fallito', [
                'lead_id' => $lead->id,
                'response' => $mediaResponse->json(),
            ]);

            return back()->withErrors([
                'quote_pdf' => 'Upload PDF su WhatsApp fallito: '.($mediaResponse->json('error.message') ?: 'errore sconosciuto'),
            ]);
        }

        $mediaId = $mediaResponse->json('id');
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $conversation->contact_phone,
            'type' => 'document',
            'document' => [
                'id' => $mediaId,
                'caption' => $body,
                'filename' => $filename,
            ],
        ];

        $response = Http::withToken(config('services.whatsapp.token'))
            ->post('https://graph.facebook.com/v25.0/'.config('services.whatsapp.phone_number_id').'/messages', $payload);

        $message = WhatsappMessage::create([
            'whatsapp_conversation_id' => $conversation->id,
            'provider_message_id' => $response->json('messages.0.id'),
            'direction' => 'outbound',
            'source' => 'user',
            'type' => 'document',
            'status' => $response->successful() ? 'sent' : 'failed',
            'from_phone' => config('services.whatsapp.phone_number_id'),
            'to_phone' => $conversation->contact_phone,
            'body' => $body,
            'media_id' => $mediaId,
            'media_disk' => $lead->quote_pdf_disk ?? self::QUOTE_PDF_DISK,
            'media_path' => $lead->quote_pdf_path,
            'media_mime_type' => $lead->quote_pdf_mime_type ?: 'application/pdf',
            'media_filename' => $filename,
            'media_size' => $lead->quote_pdf_size,
            'payload' => [
                'media_response' => $mediaResponse->json(),
                'request' => $payload,
                'response' => $response->json(),
            ],
            'error_code' => $response->json('error.code'),
            'error_message' => $response->json('error.message'),
            'sent_at' => $response->successful() ? now() : null,
            'failed_at' => $response->failed() ? now() : null,
        ]);

        $conversation->forceFill([
            'assigned_user_id' => Auth::guard('admin')->id(),
            'needs_human' => false,
            'last_message_at' => $message->created_at,
        ])->save();

        if ($response->failed()) {
            Log::warning('Invio preventivo WhatsApp fallito', [
                'lead_id' => $lead->id,
                'conversation_id' => $conversation->id,
                'response' => $response->json(),
            ]);

            return back()->withErrors([
                'quote_pdf' => 'Invio PDF WhatsApp fallito: '.($message->error_message ?? 'errore sconosciuto'),
            ]);
        }

        return redirect()
            ->route('admin.leads.index', ['lead' => $lead])
            ->with('status', 'PDF preventivo inviato su WhatsApp.');
    }

    private function findOrCreateWhatsappConversation(Lead $lead): ?WhatsappConversation
    {
        $conversation = $lead->whatsappConversation
            ?? $lead->linkedWhatsappConversation
            ?? WhatsappConversation::query()
                ->where('lead_id', $lead->id)
                ->latest('last_message_at')
                ->latest()
                ->first();

        if (! $conversation && $lead->phone) {
            $conversation = WhatsappConversation::create([
                'lead_id' => $lead->id,
                'contact_phone' => $lead->phone,
                'business_phone' => config('services.whatsapp.phone_number_id'),
                'mode' => 'manual',
                'status' => 'open',
                'needs_human' => false,
                'last_message_at' => now(),
            ]);

            $lead->forceFill([
                'whatsapp_conversation_id' => $conversation->id,
            ])->save();
        }

        return $conversation;
    }

    private function sendPaymentLinkSentEvent(Lead $lead, Ga4MeasurementService $ga4): void
    {
        if ($lead->status !== 'link_sent' || $lead->ga4_payment_link_sent_at) {
            return;
        }

        try {
            $ga4->sendPaymentLinkSent($lead);

            $lead->forceFill([
                'ga4_payment_link_sent_at' => now(),
                'ga4_payment_link_sent_status' => 'sent',
                'ga4_payment_link_sent_error' => null,
            ])->save();
        } catch (\Throwable $exception) {
            Log::warning('Invio payment_link_sent a GA4 fallito', [
                'lead_id' => $lead->id,
                'lead_uuid' => $lead->uuid,
                'error' => $exception->getMessage(),
            ]);

            $lead->forceFill([
                'ga4_payment_link_sent_status' => 'failed',
                'ga4_payment_link_sent_error' => $exception->getMessage(),
            ])->save();
        }
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

    private function ensureStripeCustomer(Lead $lead, string $secretKey): string
    {
        if ($lead->stripe_customer_id) {
            return $lead->stripe_customer_id;
        }

        try {
            $customerPayload = array_filter([
                'email' => $lead->email ?: null,
                'name' => $lead->name ?: null,
                'phone' => $lead->phone ?: null,
                'metadata' => [
                    'lead_id' => (string) $lead->id,
                    'lead_uuid' => (string) $lead->uuid,
                ],
            ], fn ($value) => $value !== null);

            $response = Http::asForm()
                ->withToken($secretKey)
                ->post('https://api.stripe.com/v1/customers', $customerPayload);
        } catch (ConnectionException $exception) {
            throw ValidationException::withMessages([
                'payment_amount' => 'Stripe non raggiungibile durante la creazione del cliente. Controlla la connessione e riprova.',
            ]);
        }

        if ($response->failed() || ! $response->json('id')) {
            throw ValidationException::withMessages([
                'payment_amount' => 'Creazione cliente Stripe fallita: ' . ($response->json('error.message') ?: 'errore sconosciuto'),
            ]);
        }

        $lead->forceFill([
            'stripe_customer_id' => $response->json('id'),
        ])->save();

        return $response->json('id');
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
            'proforma_pending' => 'Proforma da inv.',
            'payment_pending' => 'Pag. in attesa',
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
                'label' => 'Pagamento',
                'statuses' => ['link_sent', 'proforma_pending', 'payment_pending'],
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
