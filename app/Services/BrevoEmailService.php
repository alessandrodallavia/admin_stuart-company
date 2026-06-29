<?php

namespace App\Services;

use Brevo\Brevo;
use Brevo\Exceptions\BrevoApiException;
use Brevo\TransactionalEmails\Requests\SendTransacEmailRequest;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestReplyTo;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestSender;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestToItem;
use Exception;
use Illuminate\Support\Facades\Log;

class BrevoEmailService
{
    public function sendEmail($to, $tag, $params)
    {
        $payload = [
            'templateId' => 1,
            'sender' => ['name' => 'Bullstar', 'email' => 'info@bullstar.it'],
            'replyTo' => ['name' => 'Bullstar', 'email' => 'info@bullstar.it'],
            'to' => [['name' => $to['name'], 'email' => $to['email']]],
            'params' => $params,
            'tags' => $tag,
        ];

        try {
            return $this->postTransactionalEmail($payload);
        } catch (Exception $e) {
            Log::error('Brevo transactional email error: '.$e->getMessage());
            throw new Exception('Brevo transactional email error: '.$e->getMessage());
        }
    }

    public function sendAdminHtmlNotification(array $to, string $subject, string $html, array $tags = ['admin-notification'])
    {
        $from = config('admin_notifications.from');

        $payload = [
            'sender' => [
                'name' => $from['name'],
                'email' => $from['email'],
            ],
            'replyTo' => [
                'name' => $from['name'],
                'email' => $from['email'],
            ],
            'to' => [[
                'name' => $to['name'] ?? $to['email'],
                'email' => $to['email'],
            ]],
            'subject' => $subject,
            'htmlContent' => $html,
            'tags' => $tags,
        ];

        try {
            return $this->postTransactionalEmail($payload);
        } catch (Exception $e) {
            Log::error('Brevo admin notification email error', [
                'message' => $e->getMessage(),
                'status_code' => $e->getCode(),
                'response_body' => $e instanceof BrevoApiException ? $e->getBody() : null,
                'to' => $to['email'] ?? null,
                'subject' => $subject,
            ]);

            throw $e;
        }
    }

    private function postTransactionalEmail(array $payload): array
    {
        $sender = $payload['sender'];
        $replyTo = $payload['replyTo'] ?? $sender;

        $request = new SendTransacEmailRequest([
            'templateId' => $payload['templateId'] ?? null,
            'subject' => $payload['subject'] ?? null,
            'htmlContent' => $payload['htmlContent'] ?? null,
            'params' => $payload['params'] ?? null,
            'tags' => $payload['tags'] ?? null,
            'sender' => new SendTransacEmailRequestSender([
                'name' => $sender['name'] ?? null,
                'email' => $sender['email'],
            ]),
            'replyTo' => new SendTransacEmailRequestReplyTo([
                'name' => $replyTo['name'] ?? null,
                'email' => $replyTo['email'],
            ]),
            'to' => array_map(
                fn (array $recipient) => new SendTransacEmailRequestToItem([
                    'name' => $recipient['name'] ?? null,
                    'email' => $recipient['email'],
                ]),
                $payload['to'],
            ),
        ]);

        return (array) $this->brevo()->transactionalEmails->sendTransacEmail($request);
    }

    private function brevo(): Brevo
    {
        return new Brevo(
            apiKey: config('services.brevo.api_key'),
        );
    }
}
