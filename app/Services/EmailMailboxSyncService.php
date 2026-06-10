<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\EmailAttachment;
use App\Models\EmailConversation;
use App\Models\EmailMessage;
use App\Models\Lead;
use App\Notifications\AdminActionNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

class EmailMailboxSyncService
{
    public function __construct(private EmailContentService $content) {}

    public function sync(EmailAccount $account): int
    {
        $client = $this->client($account);
        $imported = 0;

        try {
            $client->connect();
            $folder = $client->getFolder($account->sync_folder ?: 'INBOX');

            if (! $folder) {
                throw new \RuntimeException('Cartella IMAP non trovata: '.($account->sync_folder ?: 'INBOX'));
            }

            $messages = $folder->messages()
                ->all()
                ->leaveUnread()
                ->setFetchOrderDesc()
                ->limit(200)
                ->get();

            foreach ($messages as $remoteMessage) {
                $imported += $this->import($account, $remoteMessage, $folder->path) ? 1 : 0;
            }

            $account->forceFill([
                'last_synced_at' => now(),
                'last_sync_error' => null,
            ])->save();
        } catch (\Throwable $e) {
            $account->forceFill([
                'last_sync_error' => Str::limit($e->getMessage(), 1000),
            ])->save();

            throw $e;
        } finally {
            try {
                $client->disconnect();
            } catch (\Throwable) {
            }
        }

        return $imported;
    }

    public function markConversationSeen(EmailConversation $conversation): void
    {
        if ($conversation->is_training) {
            $conversation->messages()
                ->where('direction', 'inbound')
                ->whereNull('seen_at')
                ->update(['seen_at' => now()]);
            $conversation->forceFill(['is_seen' => true])->save();

            return;
        }

        $account = $conversation->account;

        if (! $account?->is_active || ! $account->password()) {
            return;
        }

        $messages = $conversation->messages()
            ->where('direction', 'inbound')
            ->whereNull('seen_at')
            ->whereNotNull('provider_uid')
            ->get();

        if ($messages->isEmpty()) {
            $conversation->forceFill(['is_seen' => true])->save();

            return;
        }

        $client = $this->client($account);

        try {
            $client->connect();

            foreach ($messages->groupBy('provider_folder') as $folderPath => $folderMessages) {
                $folder = $client->getFolder($folderPath ?: $account->sync_folder ?: 'INBOX');

                if (! $folder) {
                    continue;
                }

                foreach ($folderMessages as $message) {
                    $folder->messages()->getMessageByUid($message->provider_uid)->setFlag('Seen');
                    $message->forceFill(['seen_at' => now()])->save();
                }
            }

            $conversation->forceFill(['is_seen' => true])->save();
        } finally {
            try {
                $client->disconnect();
            } catch (\Throwable) {
            }
        }
    }

    public function markConversationUnread(EmailConversation $conversation): bool
    {
        $account = $conversation->account;
        $message = $conversation->messages()
            ->where('direction', 'inbound')
            ->latest('received_at')
            ->latest('id')
            ->first();

        if (! $message) {
            return false;
        }

        if ($conversation->is_training) {
            $message->forceFill(['seen_at' => null])->save();
            $conversation->forceFill(['is_seen' => false])->save();

            return true;
        }

        if ($account?->is_active && $account->password() && $message->provider_uid) {
            $client = $this->client($account);

            try {
                $client->connect();
                $folder = $client->getFolder($message->provider_folder ?: $account->sync_folder ?: 'INBOX');
                $folder?->messages()->getMessageByUid($message->provider_uid)->unsetFlag('Seen');
            } finally {
                try {
                    $client->disconnect();
                } catch (\Throwable) {
                }
            }
        }

        $message->forceFill(['seen_at' => null])->save();
        $conversation->forceFill(['is_seen' => false])->save();

        return true;
    }

    private function import(EmailAccount $account, Message $remoteMessage, string $folderPath): bool
    {
        $messageId = $this->normalizeMessageId($this->attribute($remoteMessage->getMessageId()));
        $uid = (int) $remoteMessage->getUid();

        $existing = EmailMessage::query()
            ->whereHas('conversation', fn ($query) => $query->where('email_account_id', $account->id))
            ->where(function ($query) use ($messageId, $folderPath, $uid) {
                if ($messageId) {
                    $query->where('message_id', $messageId);
                } else {
                    $query->where('provider_folder', $folderPath)->where('provider_uid', $uid);
                }
            })
            ->first();

        if ($existing) {
            $this->syncSeenState($existing, $remoteMessage);

            return false;
        }

        $from = $remoteMessage->getFrom()->first();
        $fromEmail = $from instanceof Address ? $from->mail : null;

        if (! $fromEmail || strtolower($fromEmail) === strtolower($account->email)) {
            return false;
        }

        $subject = trim($this->attribute($remoteMessage->getSubject()) ?: 'Senza oggetto');
        $conversation = $this->findConversation($account, $remoteMessage, $fromEmail, $subject);
        $receivedAt = $this->receivedAt($remoteMessage);
        $isSeen = $remoteMessage->hasFlag('Seen');

        $message = EmailMessage::create([
            'email_conversation_id' => $conversation->id,
            'message_id' => $messageId ?: sprintf('<imap-%s-%s@local>', $account->id, $uid),
            'provider_uid' => $uid,
            'provider_folder' => $folderPath,
            'direction' => 'inbound',
            'status' => 'received',
            'from_email' => $fromEmail,
            'from_name' => $from instanceof Address ? $from->personal : null,
            'to' => $this->addresses($remoteMessage->getTo()->all()),
            'cc' => $this->addresses($remoteMessage->getCc()->all()),
            'subject' => $subject,
            'body_text' => $this->content->cleanText(trim($remoteMessage->getTextBody()) ?: trim(strip_tags($remoteMessage->getHTMLBody()))),
            'body_html' => $this->content->cleanHtml($remoteMessage->getHTMLBody() ?: null),
            'received_at' => $receivedAt,
            'seen_at' => $isSeen ? now() : null,
        ]);

        $this->storeAttachments($message, $remoteMessage);

        $conversation->forceFill([
            'contact_name' => $conversation->contact_name ?: ($from instanceof Address ? $from->personal : null),
            'status' => 'open',
            'is_seen' => $isSeen,
            'last_message_at' => $receivedAt,
        ])->save();

        if (! $isSeen && ($account->last_synced_at || $receivedAt->greaterThanOrEqualTo($account->created_at))) {
            $account->adminUser?->notify(new AdminActionNotification(
                kind: 'new-email-message',
                title: 'Nuova email da '.($conversation->contact_name ?: $fromEmail),
                body: $subject,
                url: route('admin.email.conversations.show', $conversation),
                actionLabel: 'Apri email',
                sendEmail: false,
                meta: ['email_conversation_id' => $conversation->id],
            ));
        }

        return true;
    }

    private function findConversation(EmailAccount $account, Message $message, string $fromEmail, string $subject): EmailConversation
    {
        $references = collect([
            $this->attribute($message->getInReplyTo()),
            $this->attribute($message->getReferences()),
        ])->filter()->flatMap(fn ($value) => preg_split('/\s+/', $value) ?: [])->map(fn ($value) => $this->normalizeMessageId($value))->filter();

        if ($references->isNotEmpty()) {
            $conversation = EmailConversation::query()
                ->where('is_training', false)
                ->where('email_account_id', $account->id)
                ->whereHas('messages', fn ($query) => $query->whereIn('message_id', $references->all()))
                ->first();

            if ($conversation) {
                return $conversation;
            }
        }

        $normalizedSubject = preg_replace('/^(re|r|fw|fwd)\s*:\s*/i', '', $subject);
        $conversation = EmailConversation::query()
            ->where('is_training', false)
            ->where('email_account_id', $account->id)
            ->where('contact_email', $fromEmail)
            ->where('subject', 'like', '%'.$normalizedSubject)
            ->latest('last_message_at')
            ->first();

        if ($conversation) {
            return $conversation;
        }

        $lead = Lead::query()
            ->where('is_training', false)
            ->where('email', $fromEmail)
            ->latest('id')
            ->first();

        return EmailConversation::create([
            'email_account_id' => $account->id,
            'lead_id' => $lead?->id,
            'assigned_user_id' => $account->admin_user_id,
            'subject' => $normalizedSubject ?: $subject,
            'contact_email' => $fromEmail,
            'contact_name' => optional($message->getFrom()->first())->personal,
            'status' => 'open',
            'is_seen' => false,
            'last_message_at' => now(),
        ]);
    }

    private function storeAttachments(EmailMessage $message, Message $remoteMessage): void
    {
        foreach ($remoteMessage->getAttachments() as $remoteAttachment) {
            $filename = basename($remoteAttachment->getName() ?: $remoteAttachment->filename ?: 'allegato');
            $path = 'email-attachments/'.$message->id.'/'.Str::uuid().'-'.$filename;
            Storage::disk('local')->put($path, $remoteAttachment->getContent());

            EmailAttachment::create([
                'email_message_id' => $message->id,
                'disk' => 'local',
                'path' => $path,
                'filename' => $filename,
                'mime_type' => $remoteAttachment->getContentType(),
                'size' => $remoteAttachment->getSize(),
                'content_id' => $remoteAttachment->getId(),
            ]);
        }
    }

    private function syncSeenState(EmailMessage $message, Message $remoteMessage): void
    {
        $isSeen = $remoteMessage->hasFlag('Seen');
        $message->forceFill([
            'received_at' => $this->receivedAt($remoteMessage),
            'seen_at' => $isSeen ? ($message->seen_at ?: now()) : null,
        ])->save();

        $hasUnread = $message->conversation->messages()
            ->where('direction', 'inbound')
            ->whereNull('seen_at')
            ->exists();

        $message->conversation->forceFill(['is_seen' => ! $hasUnread])->save();
    }

    private function client(EmailAccount $account): Client
    {
        $manager = new ClientManager([
            'options' => ['debug' => false],
            'accounts' => [
                'mailbox' => [
                    'host' => $account->imap_host,
                    'port' => $account->imap_port,
                    'encryption' => $account->imap_encryption === 'none' ? false : $account->imap_encryption,
                    'validate_cert' => true,
                    'username' => $account->username,
                    'password' => $account->password(),
                    'protocol' => 'imap',
                ],
            ],
        ]);

        return $manager->account('mailbox');
    }

    private function attribute(mixed $attribute): ?string
    {
        $value = is_object($attribute) && method_exists($attribute, 'first') ? $attribute->first() : $attribute;

        return $value === null ? null : trim((string) $value);
    }

    private function receivedAt(Message $message): Carbon
    {
        $rawDate = $this->attribute($message->getDate());

        if ($rawDate) {
            try {
                $date = Carbon::parse($rawDate);
                $displayTimezone = config('app.display_timezone', 'Europe/Rome');
                $offset = $date->copy()->setTimezone($displayTimezone)->offset;

                return $date->addSeconds($offset);
            } catch (\Throwable) {
            }
        }

        return $message->getDate()->toDate() ?: now();
    }

    private function normalizeMessageId(?string $messageId): ?string
    {
        if (! $messageId) {
            return null;
        }

        return '<'.trim($messageId, " \t\n\r\0\x0B<>").'>';
    }

    private function addresses(array $addresses): array
    {
        return collect($addresses)
            ->filter(fn ($address) => $address instanceof Address && $address->mail)
            ->map(fn (Address $address) => ['email' => $address->mail, 'name' => $address->personal])
            ->values()
            ->all();
    }
}
