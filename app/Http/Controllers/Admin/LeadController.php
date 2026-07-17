<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use App\Models\EmailConversation;
use App\Models\EmailMessage;
use App\Models\Lead;
use App\Models\LeadQuotePdf;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\EmailMailboxService;
use App\Services\LeadConversionTrackingService;
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
            ? $lead->fresh()->load('quotePdfs')
            : $leads->first()?->load('quotePdfs');

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

    public function update(Request $request, Lead $lead, LeadConversionTrackingService $tracking): RedirectResponse
    {
        $statuses = array_keys($this->statuses());
        $previousStatus = $lead->status;

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in($statuses)],
            'payment_link' => ['nullable', 'url', 'max:2048'],
            'payment_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'category' => ['nullable', 'string', 'max:255'],
            'product' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'lead_quality' => ['nullable', Rule::in(['Bassa', 'Media', 'Alta'])],
            'loss_reason' => ['nullable', 'string', 'max:255'],
            'crm_notes' => ['nullable', 'string', 'max:5000'],
            'margin_amount' => ['nullable', 'numeric', 'min:-99999999.99', 'max:99999999.99'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'ad_group' => ['nullable', 'string', 'max:255'],
            'utm_term' => ['nullable', 'string', 'max:255'],
            'search_term' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['status'] === 'quote_sent' && ! $lead->quotePdfs()->exists()) {
            return back()
                ->withErrors(['proposal_pdf' => 'Carica una proposta prima di segnare il lead come proposta inviata.'])
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
            'payment_link' => $data['payment_link'] ?? $lead->payment_link,
            'payment_amount' => $data['payment_amount'] ?? $lead->payment_amount,
            'category' => $data['category'] ?? null,
            'product' => $data['product'] ?? null,
            'quantity' => $data['quantity'] ?? null,
            'lead_quality' => $data['lead_quality'] ?? null,
            'loss_reason' => $data['loss_reason'] ?? null,
            'crm_notes' => $data['crm_notes'] ?? null,
            'margin_amount' => $data['margin_amount'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'ad_group' => $data['ad_group'] ?? null,
            'utm_term' => $data['utm_term'] ?? null,
            'search_term' => $data['search_term'] ?? null,
        ];

        $lead->fill($attributes)->save();
        $lead->refresh();

        if ($lead->status !== $previousStatus) {
            $tracking->trackForCurrentStatus($lead);
        }

        return redirect()
            ->route('admin.leads.index', ['lead' => $lead])
            ->with('status', 'Lead aggiornato.');
    }

    public function storeQuotePdfs(Request $request, Lead $lead, LeadConversionTrackingService $tracking): RedirectResponse
    {
        $data = $request->validate([
            'proposal_number' => ['required', 'string', 'max:100', Rule::unique('lead_quote_pdfs', 'proposal_number')],
            'proposal_amount' => ['required', 'numeric', 'min:0.50', 'max:99999999.99'],
            'proposal_pdf' => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:20480'],
            'send_google_event' => ['nullable', 'boolean'],
        ]);

        $file = $data['proposal_pdf'] ?? null;
        $originalName = null;
        $path = null;

        if ($file) {
            $originalName = $file->getClientOriginalName() ?: 'proposta.pdf';
            $baseName = Str::limit(Str::slug(pathinfo($originalName, PATHINFO_FILENAME)), 90, '');
            $filename = ($baseName ?: 'proposta').'-'.now()->format('YmdHis').'-'.Str::random(6).'.pdf';
            $path = $file->storeAs("leads/{$lead->id}/proposals", $filename, self::QUOTE_PDF_DISK);
        }

        $lead->quotePdfs()->create([
            'proposal_number' => $data['proposal_number'],
            'amount' => $data['proposal_amount'],
            'disk' => $file ? self::QUOTE_PDF_DISK : null,
            'path' => $path,
            'filename' => $originalName,
            'mime_type' => $file ? ($file->getMimeType() ?: 'application/pdf') : null,
            'size' => $file?->getSize(),
            'uploaded_at' => now(),
        ]);

        $lead->forceFill([
            'quote_number' => $data['proposal_number'],
            'quote_amount' => $data['proposal_amount'],
        ])->save();

        if ($request->boolean('send_google_event')) {
            if ($this->shouldAdvanceStatus($lead->status, 'quote_sent')) {
                $lead->forceFill(['status' => 'quote_sent'])->save();
            }

            $tracking->trackQuoteSent($lead->fresh());
        }

        return redirect()
            ->route('admin.leads.index', ['lead' => $lead])
            ->with('status', $file ? 'Proposta caricata.' : 'Proposta salvata senza PDF.');
    }

    public function showQuotePdf(Lead $lead, LeadQuotePdf $quotePdf)
    {
        $quotePdf = $this->quotePdfForLead($lead, $quotePdf);

        if (! $quotePdf->disk || ! $quotePdf->path || ! Storage::disk($quotePdf->disk)->exists($quotePdf->path)) {
            abort(404);
        }

        return Storage::disk($quotePdf->disk)->response(
            $quotePdf->path,
            $quotePdf->filename,
            ['Content-Type' => $quotePdf->mime_type ?: 'application/pdf'],
            'inline'
        );
    }

    public function destroyQuotePdf(Lead $lead, LeadQuotePdf $quotePdf): RedirectResponse
    {
        $quotePdf = $this->quotePdfForLead($lead, $quotePdf);

        if ($quotePdf->disk && $quotePdf->path && Storage::disk($quotePdf->disk)->exists($quotePdf->path)) {
            Storage::disk($quotePdf->disk)->delete($quotePdf->path);
        }

        $quotePdf->delete();
        $latestProposal = $lead->quotePdfs()->first();
        $lead->forceFill([
            'quote_number' => $latestProposal?->proposal_number,
            'quote_amount' => $latestProposal?->amount,
        ])->saveQuietly();

        return redirect()
            ->route('admin.leads.index', ['lead' => $lead])
            ->with('status', 'Proposta eliminata.');
    }

    public function createStripePaymentLink(Request $request, Lead $lead): RedirectResponse
    {
        $proposal = $this->latestProposal($lead);
        $quoteNumber = $proposal->proposal_number;
        $secretKey = $lead->is_training
            ? config('services.stripe.test_secret_key')
            : config('services.stripe.secret_key');

        if (! $secretKey) {
            throw ValidationException::withMessages([
                'payment_amount' => $lead->is_training
                    ? 'Chiave Stripe test mancante. Aggiungi STRIPE_TEST_SECRET_KEY nel file .env dell’admin.'
                    : 'Chiave Stripe mancante. Aggiungi STRIPE_SECRET_KEY nel file .env dell’admin.',
            ]);
        }

        $amount = (float) $proposal->amount;
        $amountInCents = (int) round($amount * 100);
        $currency = strtolower((string) config('services.stripe.currency', 'eur'));

        if ($currency !== 'eur') {
            throw ValidationException::withMessages([
                'payment_amount' => 'L’addebito SEPA Stripe richiede valuta EUR.',
            ]);
        }

        $token = $lead->payment_checkout_token ?: Str::random(48);
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
                'is_training' => $lead->is_training ? '1' : '0',
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'lead_id' => (string) $lead->id,
                    'lead_uuid' => (string) $lead->uuid,
                    'quote_number' => $quoteNumber,
                    'is_training' => $lead->is_training ? '1' : '0',
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
                            'name' => $quoteNumber,
                            'metadata' => [
                                'lead_id' => (string) $lead->id,
                                'quote_number' => $quoteNumber,
                                'is_training' => $lead->is_training ? '1' : '0',
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
                'payment_amount' => 'Creazione link Stripe fallita: '.($response->json('error.message') ?: 'errore sconosciuto'),
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

        app(LeadConversionTrackingService::class)->trackPaymentLinkSent($lead);

        return redirect()
            ->route('admin.leads.index', ['lead' => $lead])
            ->with('status', $lead->is_training
                ? 'Link pagamento Stripe sandbox creato e salvato sul lead formativo.'
                : 'Link pagamento Stripe creato e salvato sul lead.');
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
        $body = "Importo proposta: € {$amount}\n\nScegli come preferisci procedere:\n\n- Paga ora: carta di credito/debito, Amazon Pay, Google Pay, Apple Pay, PayPal, Satispay o addebito SEPA.\n- Bonifico bancario: ti invio la proforma con tutti i dati bancari.";

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

        if ($this->shouldAdvanceStatus($lead->status, 'link_sent')) {
            $lead->forceFill(['status' => 'link_sent'])->save();
        }
        app(LeadConversionTrackingService::class)->trackPaymentLinkSent($lead->fresh());

        return redirect()
            ->route('admin.leads.index', ['lead' => $lead])
            ->with('status', 'Link pagamento inviato su WhatsApp con pulsante.');
    }

    public function sendQuotePdfWhatsapp(Request $request, Lead $lead, LeadQuotePdf $quotePdf): RedirectResponse
    {
        $request->validate([
            'send_google_event' => ['nullable', 'boolean'],
        ]);

        $quotePdf = $this->quotePdfForLead($lead, $quotePdf);

        if (! $quotePdf->disk || ! $quotePdf->path || ! Storage::disk($quotePdf->disk)->exists($quotePdf->path)) {
            return back()->withErrors([
                'quote_pdf' => 'Carica prima un PDF proposta valido.',
            ]);
        }

        $conversation = $this->findOrCreateWhatsappConversation($lead);

        if (! $conversation) {
            return back()->withErrors([
                'quote_pdf' => 'Nessuna conversazione WhatsApp o telefono collegato a questo lead.',
            ]);
        }

        $body = null;
        $filePath = Storage::disk($quotePdf->disk)->path($quotePdf->path);
        $filename = $quotePdf->filename;

        $file = fopen($filePath, 'r');

        try {
            $mediaResponse = Http::withToken(config('services.whatsapp.token'))
                ->attach('file', $file, $filename)
                ->post('https://graph.facebook.com/v25.0/'.config('services.whatsapp.phone_number_id').'/media', [
                    'messaging_product' => 'whatsapp',
                    'type' => $quotePdf->mime_type ?: 'application/pdf',
                ]);
        } finally {
            if (is_resource($file)) {
                fclose($file);
            }
        }

        if ($mediaResponse->failed() || ! $mediaResponse->json('id')) {
            Log::warning('Upload proposta WhatsApp fallito', [
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
            'media_disk' => $quotePdf->disk,
            'media_path' => $quotePdf->path,
            'media_mime_type' => $quotePdf->mime_type ?: 'application/pdf',
            'media_filename' => $filename,
            'media_size' => $quotePdf->size,
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
            Log::warning('Invio proposta WhatsApp fallito', [
                'lead_id' => $lead->id,
                'conversation_id' => $conversation->id,
                'response' => $response->json(),
            ]);

            return back()->withErrors([
                'quote_pdf' => 'Invio PDF WhatsApp fallito: '.($message->error_message ?? 'errore sconosciuto'),
            ]);
        }

        if ($this->shouldAdvanceStatus($lead->status, 'quote_sent')) {
            $lead->forceFill(['status' => 'quote_sent'])->save();
        }

        if ($request->boolean('send_google_event')) {
            app(LeadConversionTrackingService::class)->trackQuoteSent($lead->fresh());
        }

        return redirect()
            ->route('admin.leads.index', ['lead' => $lead])
            ->with('status', 'Proposta inviata su WhatsApp.');
    }

    public function sendQuotePdfEmail(Request $request, Lead $lead, LeadQuotePdf $quotePdf, EmailMailboxService $mailbox, LeadConversionTrackingService $tracking): RedirectResponse
    {
        $request->validate([
            'send_google_event' => ['nullable', 'boolean'],
        ]);

        $quotePdf = $this->quotePdfForLead($lead, $quotePdf);

        if (! $lead->email) {
            return back()->withErrors(['email' => 'Inserisci prima l’email del cliente.']);
        }

        if (! $quotePdf->disk || ! $quotePdf->path || ! Storage::disk($quotePdf->disk)->exists($quotePdf->path)) {
            return back()->withErrors(['quote_pdf' => 'Carica prima un PDF proposta valido.']);
        }

        $account = $this->currentEmailAccount();
        $proposalNumber = $quotePdf->proposal_number;
        $conversation = $this->findOrCreateEmailConversation($lead, $account, $proposalNumber);
        $body = "Buongiorno,\n\nin allegato trovi la proposta {$proposalNumber}.\n\nResto a disposizione per qualsiasi domanda.";

        if ($lead->is_training) {
            $this->storeTrainingEmailMessage($conversation, $account->email, $body);
            $lead->forceFill(['status' => 'quote_sent'])->save();

            return redirect()
                ->route('admin.leads.index', ['lead' => $lead])
                ->with('status', 'Proposta simulata inviata via email.');
        }

        $message = $mailbox->send(
            $account,
            $conversation,
            $body,
            storedAttachments: [[
                'disk' => $quotePdf->disk,
                'path' => $quotePdf->path,
                'filename' => $quotePdf->filename,
                'mime_type' => $quotePdf->mime_type ?: 'application/pdf',
                'size' => $quotePdf->size,
            ]],
        );

        if ($message->status !== 'sent') {
            return back()->withErrors(['quote_pdf' => 'Invio email fallito: '.$message->error_message]);
        }

        if ($this->shouldAdvanceStatus($lead->status, 'quote_sent')) {
            $lead->forceFill(['status' => 'quote_sent'])->save();
        }

        if ($request->boolean('send_google_event')) {
            $tracking->trackQuoteSent($lead->fresh());
        }

        return redirect()
            ->route('admin.leads.index', ['lead' => $lead])
            ->with('status', 'Proposta inviata via email con PDF allegato.');
    }

    public function sendStripePaymentLinkEmail(Lead $lead, EmailMailboxService $mailbox, LeadConversionTrackingService $tracking): RedirectResponse
    {
        if (! $lead->email) {
            return back()->withErrors(['email' => 'Inserisci prima l’email del cliente.']);
        }

        if (! $lead->payment_link || ! $lead->payment_amount) {
            return back()->withErrors(['payment_link' => 'Crea prima un link Stripe con relativo importo.']);
        }

        $account = $this->currentEmailAccount();
        $proposalNumber = $this->latestProposal($lead)->proposal_number;
        $conversation = $this->findOrCreateEmailConversation($lead, $account, "Pagamento {$proposalNumber}");
        $amount = number_format((float) $lead->payment_amount, 2, ',', '.');
        $body = "Importo proposta: € {$amount}\n\nClicca sul link seguente per procedere al pagamento della proposta:\n{$lead->payment_link}\n\nSe preferisci pagare tramite bonifico bancario, rispondi a questa e-mail e ti invieremo la proforma con tutti i dettagli per il pagamento.";
        $html = view('emails.lead-payment-link', [
            'lead' => $lead,
            'amount' => $amount,
            'paymentLink' => $lead->payment_link,
            'quoteNumber' => $proposalNumber,
        ])->render();

        if ($lead->is_training) {
            $this->storeTrainingEmailMessage($conversation, $account->email, $body);
            $lead->forceFill(['status' => 'link_sent'])->save();

            return redirect()
                ->route('admin.leads.index', ['lead' => $lead])
                ->with('status', 'Link pagamento simulato inviato via email.');
        }

        $message = $mailbox->send($account, $conversation, $body, htmlBody: $html);

        if ($message->status !== 'sent') {
            return back()->withErrors(['payment_link' => 'Invio email fallito: '.$message->error_message]);
        }

        if ($this->shouldAdvanceStatus($lead->status, 'link_sent')) {
            $lead->forceFill(['status' => 'link_sent'])->save();
        }
        $tracking->trackPaymentLinkSent($lead->fresh());

        return redirect()
            ->route('admin.leads.index', ['lead' => $lead])
            ->with('status', 'Link pagamento inviato via email con pulsante Paga ora.');
    }

    private function currentEmailAccount(): EmailAccount
    {
        $account = EmailAccount::query()
            ->where('admin_user_id', Auth::guard('admin')->id())
            ->where('is_active', true)
            ->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'email' => 'La tua casella email non è configurata o non è attiva.',
            ]);
        }

        return $account;
    }

    private function quotePdfForLead(Lead $lead, LeadQuotePdf $quotePdf): LeadQuotePdf
    {
        abort_unless($quotePdf->lead_id === $lead->id, 404);

        return $quotePdf;
    }

    private function findOrCreateEmailConversation(Lead $lead, EmailAccount $account, string $subject): EmailConversation
    {
        return EmailConversation::query()
            ->where('email_account_id', $account->id)
            ->where('lead_id', $lead->id)
            ->latest('last_message_at')
            ->first()
            ?? EmailConversation::create([
                'email_account_id' => $account->id,
                'lead_id' => $lead->id,
                'assigned_user_id' => Auth::guard('admin')->id(),
                'subject' => $subject,
                'contact_email' => $lead->email,
                'contact_name' => $lead->name,
                'status' => 'open',
                'is_seen' => true,
                'last_message_at' => now(),
                'is_training' => $lead->is_training,
                'training_owner_id' => $lead->training_owner_id,
                'training_scenario' => $lead->training_scenario,
            ]);
    }

    private function shouldAdvanceStatus(?string $currentStatus, string $targetStatus): bool
    {
        $statuses = array_keys($this->statuses());
        $currentIndex = array_search($currentStatus, $statuses, true);
        $targetIndex = array_search($targetStatus, $statuses, true);

        return $targetIndex !== false && ($currentIndex === false || $currentIndex < $targetIndex);
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
                'is_training' => $lead->is_training,
                'training_owner_id' => $lead->training_owner_id,
                'training_scenario' => $lead->training_scenario,
            ]);

            $lead->forceFill([
                'whatsapp_conversation_id' => $conversation->id,
            ])->save();
        }

        return $conversation;
    }

    private function storeTrainingEmailMessage(EmailConversation $conversation, string $fromEmail, string $body): void
    {
        EmailMessage::create([
            'email_conversation_id' => $conversation->id,
            'message_id' => 'training-'.Str::uuid(),
            'direction' => 'outbound',
            'status' => 'sent',
            'from_email' => $fromEmail,
            'to' => [$conversation->contact_email],
            'subject' => $conversation->subject,
            'body_text' => $body,
            'sent_at' => now(),
        ]);

        $conversation->forceFill([
            'is_seen' => true,
            'last_message_at' => now(),
        ])->save();
    }

    private function latestProposal(Lead $lead): LeadQuotePdf
    {
        $proposal = $lead->quotePdfs()->first();

        if (! $proposal?->proposal_number || ! $proposal->amount) {
            throw ValidationException::withMessages([
                'proposal_pdf' => 'Carica prima una proposta indicando numero e importo.',
            ]);
        }

        if ($lead->quote_number !== $proposal->proposal_number || $lead->quote_amount !== $proposal->amount) {
            $lead->forceFill([
                'quote_number' => $proposal->proposal_number,
                'quote_amount' => $proposal->amount,
            ])->saveQuietly();
        }

        return $proposal;
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
                'payment_amount' => 'Creazione cliente Stripe fallita: '.($response->json('error.message') ?: 'errore sconosciuto'),
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
            'open' => Lead::whereNotIn('status', ['order_completed', 'lost'])->count(),
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
            'quote_sent' => 'Proposta inv.',
            'link_sent' => 'Link inv.',
            'proforma_pending' => 'Proforma da inv.',
            'payment_pending' => 'Pag. in attesa',
            'order_completed' => 'Completato',
            'lost' => 'Perso',
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
                'label' => 'Proposta inviata',
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
            [
                'key' => 'lost',
                'label' => 'Perso',
                'statuses' => ['lost'],
                'accent' => 'text-gray',
            ],
        ];
    }

    private function boardLeadAmount(Lead $lead): float
    {
        return (float) ($lead->payment_amount ?: $lead->quote_amount ?: 0);
    }
}
