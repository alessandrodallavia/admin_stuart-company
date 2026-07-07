<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappConversation;
use App\Models\WhatsappFollowUp;
use App\Models\WhatsappMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WhatsappConversationController extends Controller
{
    private const MEDIA_DISK = 'local';

    public function index(?WhatsappConversation $conversation = null): View
    {
        if ($conversation) {
            $this->markIncomingMessagesAsRead($conversation);
        }

        $conversations = WhatsappConversation::query()
            ->with(['lead', 'latestMessage', 'latestIncomingMessage'])
            ->withCount(['unreadIncomingMessages', 'pendingFollowUps', 'dueFollowUps'])
            ->orderByDesc('due_follow_ups_count')
            ->orderByDesc('needs_human')
            ->orderByDesc('unread_incoming_messages_count')
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->get();

        $selectedConversation = $conversation?->load([
            'lead',
            'latestIncomingMessage',
            'messages' => fn ($query) => $query->oldest(),
            'followUps' => fn ($query) => $query
                ->orderByRaw("status = 'pending' desc")
                ->orderBy('due_at')
                ->limit(12),
        ])?->loadCount(['pendingFollowUps', 'dueFollowUps']);

        $stats = [
            'total' => WhatsappConversation::count(),
            'auto' => WhatsappConversation::where('mode', 'auto')->count(),
            'needs_human' => WhatsappConversation::where('needs_human', true)->count(),
            'follow_ups_due' => WhatsappFollowUp::query()
                ->where('status', 'pending')
                ->where('due_at', '<=', now())
                ->whereHas('conversation', fn ($query) => $query
                    ->where('follow_up_excluded_permanently', false)
                    ->where(fn ($query) => $query
                        ->whereNull('follow_up_excluded_until')
                        ->orWhere('follow_up_excluded_until', '<=', now())
                    )
                )
                ->count(),
            'unread' => WhatsappMessage::query()
                ->where('direction', 'inbound')
                ->whereNull('admin_read_at')
                ->whereHas('conversation', fn ($query) => $query->where('mode', 'manual'))
                ->count(),
        ];

        return view('admin.dashboard', [
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
            'stats' => $stats,
        ]);
    }

    public function updateMode(Request $request, WhatsappConversation $conversation): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:auto,manual'],
        ]);

        if ($data['mode'] === 'auto' && $conversation->manual_started_at) {
            return back()->withErrors([
                'message' => 'Questa chat è già stata passata in manuale e non può tornare in automatico.',
            ]);
        }

        $conversation->forceFill([
            'mode' => $data['mode'],
            'assigned_user_id' => $data['mode'] === 'manual' ? Auth::guard('admin')->id() : $conversation->assigned_user_id,
            'needs_human' => false,
            'manual_started_at' => $data['mode'] === 'manual'
                ? ($conversation->manual_started_at ?? now())
                : $conversation->manual_started_at,
        ])->save();

        return redirect()
            ->route('admin.conversations.show', $conversation)
            ->with('status', $data['mode'] === 'manual' ? 'Chat passata in manuale.' : 'Chat passata in automatico.');
    }

    public function markAsUnread(WhatsappConversation $conversation): RedirectResponse
    {
        $message = $conversation->messages()
            ->where('direction', 'inbound')
            ->latest('received_at')
            ->latest()
            ->first();

        if (! $message) {
            return back()->withErrors([
                'message' => 'Non ci sono messaggi cliente da segnare come non letti.',
            ]);
        }

        $message->forceFill([
            'admin_read_at' => null,
        ])->save();

        return redirect()
            ->route('admin.dashboard')
            ->with('status', 'Conversazione segnata da leggere.');
    }

    public function storeFollowUp(Request $request, WhatsappConversation $conversation): RedirectResponse
    {
        if (! $this->canCreateFollowUpForConversation($conversation)) {
            return back()->withErrors([
                'message' => 'Non puoi programmare follow-up per lead completati o persi.',
            ]);
        }

        $data = $request->validate([
            'due_at' => ['required', 'date'],
            'body' => ['required', 'string', 'max:4096'],
        ]);
        $dueAt = $this->parseAdminDateTime($data['due_at']);

        if ($dueAt->lessThanOrEqualTo(now())) {
            return back()
                ->withErrors(['message' => 'Imposta una data follow-up futura.'])
                ->withInput();
        }

        $conversation->followUps()->create([
            'created_by_admin_user_id' => Auth::guard('admin')->id(),
            'due_at' => $dueAt,
            'body' => trim($data['body']),
            'status' => 'pending',
        ]);

        return redirect()
            ->route('admin.conversations.show', $conversation)
            ->with('status', 'Follow-up programmato.');
    }

    public function cancelFollowUp(Request $request, WhatsappConversation $conversation, WhatsappFollowUp $followUp): RedirectResponse
    {
        abort_unless($followUp->whatsapp_conversation_id === $conversation->id, 404);

        if ($followUp->status !== 'pending') {
            return back()->withErrors(['message' => 'Puoi annullare solo follow-up ancora in attesa.']);
        }

        $data = $request->validate([
            'cancel_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $followUp->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => $data['cancel_reason'] ?? null,
        ])->save();

        return redirect()
            ->route('admin.conversations.show', $conversation)
            ->with('status', 'Follow-up annullato.');
    }

    public function updateFollowUpExclusion(Request $request, WhatsappConversation $conversation): RedirectResponse
    {
        $data = $request->validate([
            'exclusion_type' => ['required', 'in:none,until,permanent'],
            'excluded_until' => ['nullable', 'date', 'required_if:exclusion_type,until'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $updates = [
            'follow_up_excluded_until' => null,
            'follow_up_excluded_permanently' => false,
            'follow_up_exclusion_reason' => null,
        ];

        if ($data['exclusion_type'] === 'until') {
            $excludedUntil = $this->parseAdminDateTime($data['excluded_until']);

            if ($excludedUntil->lessThanOrEqualTo(now())) {
                return back()
                    ->withErrors(['message' => 'Imposta una data di esclusione futura.'])
                    ->withInput();
            }

            $updates['follow_up_excluded_until'] = $excludedUntil;
            $updates['follow_up_exclusion_reason'] = $data['reason'] ?? null;
        }

        if ($data['exclusion_type'] === 'permanent') {
            $updates['follow_up_excluded_permanently'] = true;
            $updates['follow_up_exclusion_reason'] = $data['reason'] ?? null;
        }

        $conversation->forceFill($updates)->save();

        return redirect()
            ->route('admin.conversations.show', $conversation)
            ->with('status', $data['exclusion_type'] === 'none' ? 'Esclusione follow-up rimossa.' : 'Esclusione follow-up aggiornata.');
    }

    public function sendMessage(Request $request, WhatsappConversation $conversation): RedirectResponse
    {
        if ($conversation->mode !== 'manual') {
            return back()->withErrors([
                'message' => 'Puoi scrivere solo nelle chat impostate in manuale.',
            ]);
        }

        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:4096'],
            'attachment' => ['nullable', 'file', 'max:20480'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:20480'],
            'whatsapp_template' => ['nullable', 'string'],
        ]);

        $selectedTemplate = $this->approvedWhatsappTemplate($data['whatsapp_template'] ?? null);
        $body = trim($data['message'] ?? '');
        $attachments = collect($request->file('attachments', []))
            ->filter()
            ->values();

        if ($request->file('attachment')) {
            $attachments->prepend($request->file('attachment'));
        }

        if (($data['whatsapp_template'] ?? null) && ! $selectedTemplate) {
            return back()
                ->withErrors(['message' => 'Seleziona un modello WhatsApp approvato valido.'])
                ->withInput();
        }

        if ($selectedTemplate && $attachments->isNotEmpty()) {
            return back()
                ->withErrors(['message' => 'I modelli WhatsApp approvati non possono essere inviati insieme a un allegato.'])
                ->withInput();
        }

        if (! $selectedTemplate && $body === '' && $attachments->isEmpty()) {
            return back()
                ->withErrors(['message' => 'Scrivi un messaggio o allega un file prima di inviare.'])
                ->withInput();
        }

        $messages = collect();

        if ($selectedTemplate) {
            $payload = $this->buildTemplatePayload($conversation, $selectedTemplate);
            $messages->push($this->sendOutgoingWhatsappMessage(
                $conversation,
                'template',
                $this->templateMessageBody($selectedTemplate),
                $payload
            ));
        } elseif ($attachments->isEmpty()) {
            $payload = $this->buildOutgoingPayload($conversation, 'text', $body, []);
            $messages->push($this->sendOutgoingWhatsappMessage($conversation, 'text', $body, $payload));
        } else {
            foreach ($attachments as $index => $attachment) {
                $mediaAttributes = $this->uploadAttachmentForWhatsapp($conversation, $attachment);

                if (empty($mediaAttributes['media_id'])) {
                    return back()
                        ->withErrors(['message' => $mediaAttributes['error_message'] ?? 'Upload allegato su WhatsApp fallito.'])
                        ->withInput();
                }

                $messageType = $this->whatsappTypeFromMimeType($mediaAttributes['media_mime_type']);
                $messageBody = $index === 0 ? $body : '';
                $payload = $this->buildOutgoingPayload($conversation, $messageType, $messageBody, $mediaAttributes);

                $messages->push($this->sendOutgoingWhatsappMessage(
                    $conversation,
                    $messageType,
                    $messageBody !== '' ? $messageBody : null,
                    $payload,
                    $mediaAttributes
                ));
            }
        }

        $message = $messages->last();

        $conversation->forceFill([
            'assigned_user_id' => Auth::guard('admin')->id(),
            'needs_human' => false,
            'last_message_at' => $message->created_at,
        ])->save();

        $failedMessage = $messages->firstWhere('status', 'failed');

        if ($failedMessage) {
            return redirect()
                ->route('admin.conversations.show', $conversation)
                ->withErrors(['message' => 'Messaggio salvato, ma invio WhatsApp fallito: '.($failedMessage->error_message ?? 'errore sconosciuto')]);
        }

        if (! $selectedTemplate) {
            $this->scheduleAutomaticFollowUp($conversation, $message);
        }

        return redirect()
            ->route('admin.conversations.show', $conversation)
            ->with('status', 'Messaggio inviato.');
    }

    private function sendOutgoingWhatsappMessage(
        WhatsappConversation $conversation,
        string $messageType,
        ?string $body,
        array $payload,
        array $mediaAttributes = []
    ): WhatsappMessage {
        $response = Http::withToken(config('services.whatsapp.token'))
            ->post('https://graph.facebook.com/v25.0/'.config('services.whatsapp.phone_number_id').'/messages', $payload);
        $providerMessageId = $response->json('messages.0.id');

        if ($providerMessageId && WhatsappMessage::where('provider_message_id', $providerMessageId)->exists()) {
            $providerMessageId = null;
        }

        return WhatsappMessage::create([
            'whatsapp_conversation_id' => $conversation->id,
            'provider_message_id' => $providerMessageId,
            'direction' => 'outbound',
            'source' => 'user',
            'type' => $messageType,
            'status' => $response->successful() ? 'sent' : 'failed',
            'from_phone' => config('services.whatsapp.phone_number_id'),
            'to_phone' => $conversation->contact_phone,
            'body' => $body,
            ...$mediaAttributes,
            'payload' => [
                'request' => $payload,
                'response' => $response->json(),
            ],
            'error_code' => $response->json('error.code'),
            'error_message' => $response->json('error.message'),
            'sent_at' => $response->successful() ? now() : null,
            'failed_at' => $response->failed() ? now() : null,
        ]);
    }

    private function approvedWhatsappTemplate(?string $key): ?array
    {
        if (! $key) {
            return null;
        }

        $template = config("whatsapp_templates.templates.{$key}");

        if (! is_array($template) || empty($template['name'])) {
            return null;
        }

        return $template;
    }

    private function buildTemplatePayload(WhatsappConversation $conversation, array $template): array
    {
        $language = $template['language'] ?? config('whatsapp_templates.default_language', 'it');
        $components = $this->templateComponents($template['parameters'] ?? []);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $conversation->contact_phone,
            'type' => 'template',
            'template' => [
                'name' => $template['name'],
                'language' => ['code' => $language],
            ],
        ];

        if ($components !== []) {
            $payload['template']['components'] = $components;
        }

        return $payload;
    }

    private function templateComponents(array $parameters): array
    {
        if ($parameters === []) {
            return [];
        }

        return [
            [
                'type' => 'body',
                'parameters' => collect($parameters)
                    ->map(fn ($parameter) => [
                        'type' => 'text',
                        'text' => (string) $parameter,
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    private function templateMessageBody(array $template): string
    {
        return $template['body']
            ?? 'Template WhatsApp: '.($template['name'] ?? 'modello approvato');
    }

    private function scheduleAutomaticFollowUp(WhatsappConversation $conversation, WhatsappMessage $message): void
    {
        if (! config('whatsapp_follow_up.auto_enabled', true)
            || $conversation->isExcludedFromFollowUps()
            || ! $this->canCreateFollowUpForConversation($conversation)
        ) {
            return;
        }

        $body = trim((string) config('whatsapp_follow_up.auto_body'));

        if ($body === '') {
            return;
        }

        $conversation->followUps()
            ->where('auto_generated', true)
            ->where('status', 'pending')
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => 'Sostituito da un nuovo messaggio manuale.',
            ]);

        $conversation->followUps()->create([
            'created_by_admin_user_id' => Auth::guard('admin')->id(),
            'trigger_message_id' => $message->id,
            'auto_generated' => true,
            'due_at' => ($message->sent_at ?? $message->created_at)->copy()->addHours((int) config('whatsapp_follow_up.auto_delay_hours', 24)),
            'body' => $body,
            'status' => 'pending',
        ]);
    }

    private function canCreateFollowUpForConversation(WhatsappConversation $conversation): bool
    {
        return ! in_array($conversation->lead?->status, ['completed', 'order_completed', 'lost'], true);
    }

    private function uploadAttachmentForWhatsapp(WhatsappConversation $conversation, $attachment): array
    {
        $preparedFile = $this->prepareAttachmentForWhatsapp($attachment);
        $mimeType = $preparedFile['mime_type'];
        $originalName = $preparedFile['filename'];
        $safeName = Str::limit(Str::slug(pathinfo($originalName, PATHINFO_FILENAME)), 80, '');
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $filename = ($safeName ?: 'allegato').'-'.now()->format('YmdHis').'-'.Str::random(6).($extension ? ".{$extension}" : '');
        $localPath = "whatsapp/{$conversation->id}/outbound/{$filename}";

        Storage::disk(self::MEDIA_DISK)->put($localPath, file_get_contents($preparedFile['path']));

        $file = fopen($preparedFile['path'], 'r');

        try {
            $response = Http::withToken(config('services.whatsapp.token'))
                ->attach('file', $file, $originalName)
                ->post('https://graph.facebook.com/v25.0/'.config('services.whatsapp.phone_number_id').'/media', [
                    'messaging_product' => 'whatsapp',
                    'type' => $mimeType,
                ]);
        } finally {
            if (is_resource($file)) {
                fclose($file);
            }

            if ($preparedFile['temporary'] && file_exists($preparedFile['path'])) {
                @unlink($preparedFile['path']);
            }
        }

        if ($response->failed()) {
            Log::warning('Upload media outbound WhatsApp fallito', [
                'conversation_id' => $conversation->id,
                'response' => $response->json(),
            ]);

            return [
                'media_disk' => self::MEDIA_DISK,
                'media_path' => $localPath,
                'media_mime_type' => $mimeType,
                'media_filename' => $originalName,
                'media_size' => filesize(Storage::disk(self::MEDIA_DISK)->path($localPath)),
                'error_message' => 'Upload allegato su WhatsApp fallito: '.($response->json('error.message') ?: 'formato non accettato o errore sconosciuto'),
            ];
        }

        return [
            'media_id' => $response->json('id'),
            'media_disk' => self::MEDIA_DISK,
            'media_path' => $localPath,
            'media_mime_type' => $mimeType,
            'media_filename' => $originalName,
            'media_size' => filesize(Storage::disk(self::MEDIA_DISK)->path($localPath)),
        ];
    }

    private function prepareAttachmentForWhatsapp($attachment): array
    {
        $mimeType = $attachment->getMimeType() ?: 'application/octet-stream';
        $filename = $attachment->getClientOriginalName() ?: 'allegato';
        $path = $attachment->getRealPath();

        if ($mimeType === 'audio/webm' || strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'webm') {
            $convertedPath = storage_path('app/private/whatsapp-upload-'.Str::uuid().'.ogg');
            $conversionOutput = [];
            $conversionExitCode = 1;

            if (function_exists('exec') && ! in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
                exec(implode(' ', [
                    'ffmpeg',
                    '-y',
                    '-i',
                    escapeshellarg($path),
                    '-vn',
                    '-c:a',
                    'libopus',
                    '-b:a',
                    '32k',
                    escapeshellarg($convertedPath),
                    '2>&1',
                ]), $conversionOutput, $conversionExitCode);
            }

            if ($conversionExitCode === 0 && file_exists($convertedPath)) {
                return [
                    'path' => $convertedPath,
                    'mime_type' => 'audio/ogg',
                    'filename' => pathinfo($filename, PATHINFO_FILENAME).'.ogg',
                    'temporary' => true,
                ];
            }

            Log::warning('Conversione nota vocale WhatsApp fallita', [
                'mime_type' => $mimeType,
                'filename' => $filename,
                'error' => implode("\n", $conversionOutput) ?: 'Funzione exec non disponibile.',
            ]);
        }

        if (! str_starts_with($mimeType, 'image/') || in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            return [
                'path' => $path,
                'mime_type' => $mimeType,
                'filename' => $filename,
                'temporary' => false,
            ];
        }

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return [
                'path' => $path,
                'mime_type' => $mimeType,
                'filename' => $filename,
                'temporary' => false,
            ];
        }

        $image = @imagecreatefromstring(file_get_contents($path));

        if (! $image) {
            return [
                'path' => $path,
                'mime_type' => $mimeType,
                'filename' => $filename,
                'temporary' => false,
            ];
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);

        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);

        $temporaryPath = storage_path('app/private/whatsapp-upload-'.Str::uuid().'.jpg');
        imagejpeg($canvas, $temporaryPath, 90);
        imagedestroy($image);
        imagedestroy($canvas);

        return [
            'path' => $temporaryPath,
            'mime_type' => 'image/jpeg',
            'filename' => pathinfo($filename, PATHINFO_FILENAME).'.jpg',
            'temporary' => true,
        ];
    }

    private function buildOutgoingPayload(WhatsappConversation $conversation, string $messageType, string $body, array $mediaAttributes): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $conversation->contact_phone,
            'type' => $messageType,
        ];

        if ($messageType === 'text') {
            $payload['text'] = ['body' => $body];

            return $payload;
        }

        $media = ['id' => $mediaAttributes['media_id']];

        if (in_array($messageType, ['image', 'document', 'video'], true) && $body !== '') {
            $media['caption'] = $body;
        }

        if ($messageType === 'document') {
            $media['filename'] = $mediaAttributes['media_filename'] ?? 'documento';
        }

        $payload[$messageType] = $media;

        return $payload;
    }

    private function whatsappTypeFromMimeType(?string $mimeType): string
    {
        return match (true) {
            in_array($mimeType, ['image/jpeg', 'image/png'], true) => 'image',
            str_starts_with((string) $mimeType, 'audio/') => 'audio',
            str_starts_with((string) $mimeType, 'video/') => 'video',
            default => 'document',
        };
    }

    public function poll(WhatsappConversation $conversation): JsonResponse
    {
        $this->markIncomingMessagesAsRead($conversation);

        $conversation->load([
            'lead',
            'latestIncomingMessage',
            'messages' => fn ($query) => $query->oldest(),
            'followUps' => fn ($query) => $query
                ->orderByRaw("status = 'pending' desc")
                ->orderBy('due_at')
                ->limit(12),
        ])->loadCount(['pendingFollowUps', 'dueFollowUps']);

        $conversations = WhatsappConversation::query()
            ->with(['lead', 'latestMessage', 'latestIncomingMessage'])
            ->withCount(['unreadIncomingMessages', 'pendingFollowUps', 'dueFollowUps'])
            ->orderByDesc('due_follow_ups_count')
            ->orderByDesc('needs_human')
            ->orderByDesc('unread_incoming_messages_count')
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'stats' => [
                'total' => WhatsappConversation::count(),
                'auto' => WhatsappConversation::where('mode', 'auto')->count(),
                'needs_human' => WhatsappConversation::where('needs_human', true)->count(),
                'follow_ups_due' => WhatsappFollowUp::query()
                    ->where('status', 'pending')
                    ->where('due_at', '<=', now())
                    ->whereHas('conversation', fn ($query) => $query
                        ->where('follow_up_excluded_permanently', false)
                        ->where(fn ($query) => $query
                            ->whereNull('follow_up_excluded_until')
                            ->orWhere('follow_up_excluded_until', '<=', now())
                        )
                    )
                    ->count(),
                'unread' => WhatsappMessage::query()
                    ->where('direction', 'inbound')
                    ->whereNull('admin_read_at')
                    ->whereHas('conversation', fn ($query) => $query->where('mode', 'manual'))
                    ->count(),
            ],
            'selected_conversation' => $this->serializeConversation($conversation),
            'conversations' => $conversations
                ->map(fn (WhatsappConversation $item) => $this->serializeConversation($item))
                ->values(),
            'messages' => $conversation->messages
                ->map(fn (WhatsappMessage $message) => $this->serializeMessage($message))
                ->values(),
            'follow_ups' => $conversation->followUps
                ->map(fn (WhatsappFollowUp $followUp) => $this->serializeFollowUp($followUp, $conversation))
                ->values(),
        ]);
    }

    public function pollIndex(): JsonResponse
    {
        $conversations = WhatsappConversation::query()
            ->with(['lead', 'latestMessage', 'latestIncomingMessage'])
            ->withCount(['unreadIncomingMessages', 'pendingFollowUps', 'dueFollowUps'])
            ->orderByDesc('due_follow_ups_count')
            ->orderByDesc('needs_human')
            ->orderByDesc('unread_incoming_messages_count')
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'stats' => [
                'total' => WhatsappConversation::count(),
                'auto' => WhatsappConversation::where('mode', 'auto')->count(),
                'needs_human' => WhatsappConversation::where('needs_human', true)->count(),
                'follow_ups_due' => WhatsappFollowUp::query()
                    ->where('status', 'pending')
                    ->where('due_at', '<=', now())
                    ->whereHas('conversation', fn ($query) => $query
                        ->where('follow_up_excluded_permanently', false)
                        ->where(fn ($query) => $query
                            ->whereNull('follow_up_excluded_until')
                            ->orWhere('follow_up_excluded_until', '<=', now())
                        )
                    )
                    ->count(),
                'unread' => WhatsappMessage::query()
                    ->where('direction', 'inbound')
                    ->whereNull('admin_read_at')
                    ->whereHas('conversation', fn ($query) => $query->where('mode', 'manual'))
                    ->count(),
            ],
            'conversations' => $conversations
                ->map(fn (WhatsappConversation $item) => $this->serializeConversation($item))
                ->values(),
        ]);
    }

    public function showMedia(WhatsappMessage $message)
    {
        $conversation = $message->conversation;
        $user = Auth::guard('admin')->user();

        abort_unless(
            $conversation
            && $conversation->is_training === (bool) $user?->training_mode_active
            && (! $conversation->is_training || $conversation->training_owner_id === $user?->id),
            404
        );

        if (! $message->media_path) {
            $this->downloadMessageMedia($message);
            $message->refresh();
        }

        if (! $message->media_path || ! Storage::disk($message->media_disk ?? 'public')->exists($message->media_path)) {
            abort(404);
        }

        return Storage::disk($message->media_disk ?? 'public')->response(
            $message->media_path,
            $message->media_filename,
            ['Content-Type' => $message->media_mime_type ?: 'application/octet-stream'],
            'inline'
        );
    }

    private function markIncomingMessagesAsRead(WhatsappConversation $conversation): void
    {
        $conversation->messages()
            ->where('direction', 'inbound')
            ->whereNull('admin_read_at')
            ->update(['admin_read_at' => now()]);
    }

    private function serializeConversation(WhatsappConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'url' => route('admin.conversations.show', $conversation),
            'name' => $conversation->lead?->name ?: $conversation->contact_phone,
            'subtitle' => $conversation->lead?->club ?: $conversation->contact_phone,
            'phone' => $conversation->contact_phone,
            'email' => $conversation->lead?->email,
            'mode' => $conversation->mode,
            'manual_started_at' => $this->formatAdminDateTime($conversation->manual_started_at),
            'whatsapp_window_expired' => $conversation->isWhatsappWindowExpired(),
            'whatsapp_window_reference_at' => $this->formatAdminDateTime($conversation->latestIncomingMessage?->received_at ?? $conversation->latestIncomingMessage?->created_at ?? $conversation->last_message_at ?? $conversation->created_at),
            'needs_human' => $conversation->needs_human,
            'follow_up_excluded' => $conversation->isExcludedFromFollowUps(),
            'follow_up_exclusion_label' => $this->followUpExclusionLabel($conversation),
            'pending_follow_ups_count' => $conversation->pending_follow_ups_count ?? null,
            'due_follow_ups_count' => $conversation->isExcludedFromFollowUps() ? 0 : ($conversation->due_follow_ups_count ?? null),
            'handoff_reason' => data_get($conversation->metadata, 'handoff_reason', 'La chat richiede il tuo intervento.'),
            'unread_count' => $conversation->unread_incoming_messages_count ?? 0,
            'latest_body' => $this->messagePreview($conversation->latestMessage),
            'last_message_at' => $this->formatAdminDateTime($conversation->last_message_at ?? $conversation->created_at),
        ];
    }

    private function followUpExclusionLabel(WhatsappConversation $conversation): ?string
    {
        if ($conversation->follow_up_excluded_permanently) {
            return 'No follow-up';
        }

        if ($conversation->follow_up_excluded_until && $conversation->follow_up_excluded_until->isFuture()) {
            return 'Pausa fino al '.$this->formatAdminDateTime($conversation->follow_up_excluded_until);
        }

        return null;
    }

    private function parseAdminDateTime(string $value): Carbon
    {
        return Carbon::parse($value, config('app.display_timezone'))->utc();
    }

    private function formatAdminDateTime(?Carbon $date): ?string
    {
        return $date?->copy()->timezone(config('app.display_timezone'))->format('d/m/Y H:i');
    }

    private function serializeMessage(WhatsappMessage $message): array
    {
        $isOutbound = $message->direction === 'outbound';
        $statusLabel = $message->status ?: 'n/d';

        if ($isOutbound && $message->read_at) {
            $statusLabel = 'letto';
        } elseif ($isOutbound && $message->delivered_at) {
            $statusLabel = 'consegnato';
        } elseif ($isOutbound && $message->sent_at) {
            $statusLabel = 'inviato';
        } elseif (! $isOutbound && $message->admin_read_at) {
            $statusLabel = 'aperto';
        }

        return [
            'id' => $message->id,
            'direction' => $message->direction,
            'source' => $message->source,
            'type' => $message->type,
            'body' => $message->body,
            'status_label' => $statusLabel,
            'message_at' => $this->formatAdminDateTime($message->received_at ?? $message->sent_at ?? $message->created_at),
            'status_at' => $this->formatAdminDateTime($message->read_at ?? $message->delivered_at ?? $message->sent_at ?? $message->created_at),
            'sent_at' => $this->formatAdminDateTime($message->sent_at),
            'delivered_at' => $this->formatAdminDateTime($message->delivered_at),
            'read_at' => $this->formatAdminDateTime($message->read_at),
            'error_message' => $message->error_message,
            'media' => $this->serializeMedia($message),
        ];
    }

    private function serializeFollowUp(WhatsappFollowUp $followUp, WhatsappConversation $conversation): array
    {
        $isDue = $followUp->status === 'pending' && $followUp->due_at->isPast();

        return [
            'id' => $followUp->id,
            'body' => $followUp->body,
            'status' => $followUp->status,
            'status_label' => match ($followUp->status) {
                'sent' => 'Inviato',
                'failed' => 'Errore',
                'cancelled' => 'Annullato',
                default => $isDue ? 'Da fare' : 'Programmato',
            },
            'status_class' => match ($followUp->status) {
                'sent' => 'bg-whatsapp/10 text-whatsapp',
                'failed' => 'bg-red-50 text-red-700',
                'cancelled' => 'bg-gray-mid text-black-nike',
                default => $isDue ? 'bg-brand text-white' : 'bg-bullstar/10 text-bullstar',
            },
            'auto_generated' => $followUp->auto_generated,
            'is_pending' => $followUp->status === 'pending',
            'due_at' => $this->formatAdminDateTime($followUp->due_at),
            'error_message' => $followUp->error_message,
            'cancel_url' => route('admin.conversations.follow-ups.cancel', [$conversation, $followUp]),
        ];
    }

    private function serializeMedia(WhatsappMessage $message): ?array
    {
        $mediaPayload = $this->mediaPayload($message);

        if (! $message->media_path && empty($mediaPayload['id']) && empty($mediaPayload['url'])) {
            return null;
        }

        return [
            'url' => route('admin.messages.media', $message),
            'mime_type' => $message->media_mime_type ?: ($mediaPayload['mime_type'] ?? null),
            'filename' => $message->media_filename ?: ($mediaPayload['filename'] ?? ucfirst($message->type)),
            'size' => $message->media_size,
            'kind' => $this->mediaKind($message),
        ];
    }

    private function mediaKind(WhatsappMessage $message): string
    {
        $mediaPayload = $this->mediaPayload($message);
        $mimeType = $message->media_mime_type ?: ($mediaPayload['mime_type'] ?? null);

        if (str_starts_with((string) $mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with((string) $mimeType, 'audio/')) {
            return 'audio';
        }

        if (str_starts_with((string) $mimeType, 'video/')) {
            return 'video';
        }

        return 'document';
    }

    private function mediaPayload(WhatsappMessage $message): array
    {
        $payload = $message->payload ?? [];

        return is_array($payload) && isset($payload[$message->type]) && is_array($payload[$message->type])
            ? $payload[$message->type]
            : [];
    }

    private function downloadMessageMedia(WhatsappMessage $message): void
    {
        $mediaPayload = $this->mediaPayload($message);
        $mediaId = $message->media_id ?: ($mediaPayload['id'] ?? null);

        if (! $mediaId && empty($mediaPayload['url'])) {
            return;
        }

        try {
            $mediaUrl = $mediaPayload['url'] ?? null;
            $mimeType = $message->media_mime_type ?: ($mediaPayload['mime_type'] ?? null);
            $fileSize = $message->media_size;

            if ($mediaId) {
                $mediaResponse = Http::withToken(config('services.whatsapp.token'))
                    ->get("https://graph.facebook.com/v25.0/{$mediaId}");

                if ($mediaResponse->successful()) {
                    $mediaUrl = $mediaResponse->json('url') ?: $mediaUrl;
                    $mimeType = $mediaResponse->json('mime_type') ?: $mimeType;
                    $fileSize = $mediaResponse->json('file_size') ?: $fileSize;
                }
            }

            if (! $mediaUrl) {
                return;
            }

            $fileResponse = Http::withToken(config('services.whatsapp.token'))->get($mediaUrl);

            if ($fileResponse->failed()) {
                return;
            }

            $filename = $message->media_filename ?: ($mediaPayload['filename'] ?? $this->fallbackMediaFilename($message, $mediaId, $mimeType));
            $path = 'whatsapp/'.$message->whatsapp_conversation_id.'/'.($mediaId ?: $message->id).'-'.str()->slug(pathinfo($filename, PATHINFO_FILENAME));
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $path .= $extension ? ".{$extension}" : '';

            Storage::disk(self::MEDIA_DISK)->put($path, $fileResponse->body());

            $message->forceFill([
                'media_id' => $mediaId,
                'media_disk' => self::MEDIA_DISK,
                'media_path' => $path,
                'media_mime_type' => $mimeType,
                'media_filename' => $filename,
                'media_size' => $fileSize ?: strlen($fileResponse->body()),
            ])->save();
        } catch (\Throwable $exception) {
            Log::warning('Errore apertura media WhatsApp in admin', [
                'message_id' => $message->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function fallbackMediaFilename(WhatsappMessage $message, ?string $mediaId, ?string $mimeType): string
    {
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'audio/mpeg' => 'mp3',
            'audio/ogg' => 'ogg',
            'audio/aac' => 'aac',
            'audio/mp4' => 'm4a',
            'application/pdf' => 'pdf',
            'video/mp4' => 'mp4',
            default => null,
        };

        $name = $message->type.'-'.($mediaId ?: $message->id);

        return $extension ? "{$name}.{$extension}" : $name;
    }

    private function messagePreview(?WhatsappMessage $message): string
    {
        if (! $message) {
            return 'Nessun messaggio';
        }

        if ($message->body) {
            return $message->body;
        }

        return match ($message->type) {
            'image' => 'Immagine',
            'document' => 'Documento',
            'audio' => 'Audio',
            'video' => 'Video',
            default => 'Nessun messaggio testuale',
        };
    }
}
