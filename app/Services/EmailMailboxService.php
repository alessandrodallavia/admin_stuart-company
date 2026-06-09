<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\EmailAttachment;
use App\Models\EmailConversation;
use App\Models\EmailMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Webklex\PHPIMAP\ClientManager;

class EmailMailboxService
{
    public function send(
        EmailAccount $account,
        EmailConversation $conversation,
        string $body,
        array $uploadedAttachments = [],
        array $cc = [],
        array $bcc = [],
        ?string $htmlBody = null,
        array $storedAttachments = [],
    ): EmailMessage {
        $account->loadMissing('adminUser');
        $signatureText = $this->signatureText($account);
        $signatureHtml = $this->signatureHtml($account);
        $bodyWithSignature = rtrim($body)."\n\n".$signatureText;
        $htmlWithSignature = $this->withHtmlSignature($htmlBody, $body, $signatureHtml);

        $threadMessageIds = $conversation->messages()
            ->whereNotNull('message_id')
            ->oldest('id')
            ->pluck('message_id')
            ->map(fn ($messageId) => trim($messageId, '<>'))
            ->filter()
            ->values();

        $message = EmailMessage::create([
            'email_conversation_id' => $conversation->id,
            'message_id' => sprintf('<%s@%s>', Str::uuid(), parse_url(config('app.url'), PHP_URL_HOST) ?: 'stuart-company.com'),
            'direction' => 'outbound',
            'status' => 'pending',
            'from_email' => $account->email,
            'from_name' => $account->from_name,
            'to' => [['email' => $conversation->contact_email, 'name' => $conversation->contact_name]],
            'cc' => $this->addressList($cc),
            'bcc' => $this->addressList($bcc),
            'subject' => $conversation->subject,
            'body_text' => $bodyWithSignature,
            'body_html' => $htmlWithSignature,
        ]);

        $attachments = [
            ...$this->storeAttachments($message, $uploadedAttachments),
            ...$this->copyStoredAttachments($message, $storedAttachments),
        ];

        try {
            $email = (new Email)
                ->from(new Address($account->email, $account->from_name ?: $account->email))
                ->to(new Address($conversation->contact_email, $conversation->contact_name ?: ''))
                ->subject($conversation->subject ?: 'Stuart Company')
                ->text($bodyWithSignature)
                ->html($htmlWithSignature);

            $email->getHeaders()->addIdHeader('Message-ID', trim($message->message_id, '<>'));

            if ($threadMessageIds->isNotEmpty()) {
                $email->getHeaders()->addIdHeader('In-Reply-To', $threadMessageIds->last());
                $email->getHeaders()->addIdHeader('References', $threadMessageIds->all());
            }

            foreach ($cc as $address) {
                if ($address) {
                    $email->addCc($address);
                }
            }

            foreach ($bcc as $address) {
                if ($address) {
                    $email->addBcc($address);
                }
            }

            foreach ($attachments as $attachment) {
                $email->attachFromPath(
                    Storage::disk($attachment->disk)->path($attachment->path),
                    $attachment->filename,
                    $attachment->mime_type,
                );
            }

            Transport::fromDsn($this->dsn($account))->send($email);
            $sentAt = now();

            $message->forceFill([
                'status' => 'sent',
                'sent_at' => $sentAt,
            ])->save();

            $conversation->forceFill([
                'is_seen' => true,
                'last_message_at' => $message->sent_at,
            ])->save();

            try {
                $this->appendToSentFolder($account, $email->toString(), $sentAt);
            } catch (\Throwable $e) {
                Log::warning('Copia email inviata non salvata nella cartella IMAP', [
                    'email_account_id' => $account->id,
                    'email_message_id' => $message->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (\Throwable $e) {
            $message->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => $e->getMessage(),
            ])->save();
        }

        return $message->fresh(['attachments']);
    }

    /**
     * @param  array<int, UploadedFile>  $uploadedAttachments
     * @return array<int, EmailAttachment>
     */
    private function storeAttachments(EmailMessage $message, array $uploadedAttachments): array
    {
        return collect($uploadedAttachments)
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->map(function (UploadedFile $file) use ($message) {
                $path = $file->store('email-attachments/'.$message->id, 'local');

                return EmailAttachment::create([
                    'email_message_id' => $message->id,
                    'disk' => 'local',
                    'path' => $path,
                    'filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ]);
            })
            ->values()
            ->all();
    }

    private function copyStoredAttachments(EmailMessage $message, array $storedAttachments): array
    {
        return collect($storedAttachments)
            ->filter(fn ($attachment) => isset($attachment['disk'], $attachment['path']) && Storage::disk($attachment['disk'])->exists($attachment['path']))
            ->map(function (array $attachment) use ($message) {
                $filename = $attachment['filename'] ?? basename($attachment['path']);
                $path = 'email-attachments/'.$message->id.'/'.Str::uuid().'-'.$filename;
                Storage::disk('local')->put($path, Storage::disk($attachment['disk'])->get($attachment['path']));

                return EmailAttachment::create([
                    'email_message_id' => $message->id,
                    'disk' => 'local',
                    'path' => $path,
                    'filename' => $filename,
                    'mime_type' => $attachment['mime_type'] ?? null,
                    'size' => $attachment['size'] ?? Storage::disk('local')->size($path),
                ]);
            })
            ->values()
            ->all();
    }

    private function dsn(EmailAccount $account): string
    {
        $scheme = $account->smtp_encryption === 'ssl' ? 'smtps' : 'smtp';
        $username = rawurlencode($account->username);
        $password = rawurlencode($account->password() ?: '');
        $host = $account->smtp_host;
        $port = $account->smtp_port;

        return "{$scheme}://{$username}:{$password}@{$host}:{$port}";
    }

    private function appendToSentFolder(EmailAccount $account, string $rawMessage, mixed $sentAt): void
    {
        if (! $account->is_active || ! $account->password()) {
            return;
        }

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
        $client = $manager->account('mailbox');

        try {
            $client->connect();
            $folders = $client->getFolders(false, null, true);
            $preferredNames = collect([
                'sent',
                'sent messages',
                'sent items',
                'posta inviata',
                'inbox.sent',
                'inbox/sent',
            ]);
            $sentFolder = $folders->first(function ($folder) use ($preferredNames) {
                $name = Str::lower((string) $folder->name);
                $path = Str::lower((string) $folder->path);

                return $preferredNames->contains($name)
                    || $preferredNames->contains($path)
                    || str_contains($name, 'sent')
                    || str_contains($name, 'inviat');
            });

            if (! $sentFolder) {
                throw new \RuntimeException('Cartella IMAP della posta inviata non trovata.');
            }

            $sentFolder->appendMessage($rawMessage, ['\Seen'], $sentAt);
        } finally {
            try {
                $client->disconnect();
            } catch (\Throwable) {
            }
        }
    }

    private function addressList(array $addresses): array
    {
        return collect($addresses)
            ->filter()
            ->map(fn ($address) => ['email' => $address])
            ->values()
            ->all();
    }

    private function signatureText(EmailAccount $account): string
    {
        return implode("\n", [
            $account->adminUser?->name ?: $account->from_name ?: $account->email,
            config('email_signature.company'),
            config('email_signature.phone'),
            $account->email,
            config('email_signature.website'),
            '',
            config('email_signature.disclaimer'),
        ]);
    }

    private function signatureHtml(EmailAccount $account): string
    {
        return view('emails.partials.signature', [
            'name' => $account->adminUser?->name ?: $account->from_name ?: $account->email,
            'company' => config('email_signature.company'),
            'phone' => config('email_signature.phone'),
            'email' => $account->email,
            'website' => config('email_signature.website'),
            'logoUrl' => config('email_signature.logo_url'),
            'disclaimer' => config('email_signature.disclaimer'),
        ])->render();
    }

    private function withHtmlSignature(?string $htmlBody, string $body, string $signatureHtml): string
    {
        if (! $htmlBody) {
            return '<div style="font-family:Arial,Helvetica,sans-serif;color:#1f1f21;font-size:15px;line-height:1.6;">'
                .nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))
                .$signatureHtml
                .'</div>';
        }

        if (preg_match('/<\/body>/i', $htmlBody)) {
            return preg_replace('/<\/body>/i', $signatureHtml.'</body>', $htmlBody, 1) ?: $htmlBody.$signatureHtml;
        }

        return $htmlBody.$signatureHtml;
    }
}
