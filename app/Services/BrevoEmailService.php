<?php

namespace App\Services;

use Brevo\Client\Configuration;
use Brevo\Client\Api\TransactionalEmailsApi;
use Brevo\Client\Model\SendSmtpEmail;
use Brevo\Client\Api\ContactsApi;
use Brevo\Client\Model\CreateContact;
use Brevo\Client\Api\DealsApi;
use Brevo\Client\Model\CrmDealsPost;
use GuzzleHttp\Client;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Models\Lead;

class BrevoEmailService
{
    protected $apiInstance;

    public function __construct() {
        $apiKey = config('services.brevo.api_key');
        $config = Configuration::getDefaultConfiguration()
            ->setApiKey('api-key', $apiKey)
            ->setApiKey('partner-key', $apiKey);

        $this->apiInstance = new TransactionalEmailsApi(
            new Client(),
            $config
        );

        $this->contactsApiInstance = new ContactsApi(
            new Client(),
            $config
        );

        $this->dealsApiInstance = new DealsApi(
            new Client(),
            $config
        );
    }

    public function sendEmail($to, $tag, $params) {
        $sendSmtpEmail = new SendSmtpEmail([
            'templateId' => 1,
            'sender' => ['name' => 'Bullstar', 'email' => 'info@bullstar.it'],
            'replyTo' => ['name' => 'Bullstar', 'email' => 'info@bullstar.it'],
            'to' => [['name' => $to['name'], 'email' => $to['email']]],
            'params' => $params,
            'tags' => $tag
        ]);

        try {
            $result = $this->apiInstance->sendTransacEmail($sendSmtpEmail);
            return $result;
        } catch (Exception $e) {
            Log::error('Exception when calling TransactionalEmailsApi->sendTransacEmail: ' . $e->getMessage());
            throw new Exception('Exception when calling TransactionalEmailsApi->sendTransacEmail: ' . $e->getMessage());
        }
    }

    public function createContact($data)
    {
        try {

            $contact = new CreateContact([
                'email' => $data['email'],
                'attributes' => $data['attributes'] ?? [],
                'listIds' => $data['listIds'] ?? [],
                'updateEnabled' => true
            ]);

            $this->contactsApiInstance->createContact($contact);

            // recupera il contatto appena creato
            return $this->contactsApiInstance->getContactInfo($data['email']);

        } catch (Exception $e) {

            Log::error('Brevo createContact error', [
                'message' => $e->getMessage()
            ]);

            return null;
        }
    }

    public function createDeal($data)
    {
        try {

            $response = Http::withHeaders([
                'api-key' => config('services.brevo.api_key'),
                'accept' => 'application/json'
            ])->get('https://api.brevo.com/v3/crm/pipeline/details/' . config('services.brevo.pipeline_landing'));

            Log::info('response',[$response->json()]);

            $payload = [
                'name' => $data['name'],
                'pipelineId' => $data['pipelineId'],
                'dealStageId' => $data['dealStageId'],
                'attributes' => $data['dealAttributes'] ?? []
            ];

            if (! empty($data['linkedContactsIds'])) {
                $payload['linkedContactsIds'] = $data['linkedContactsIds'];
            }

            $response = $this->postDeal($payload);

            while ($response->failed() && $invalidAttribute = $this->invalidDealAttribute($response->json('message'))) {
                unset($payload['attributes'][$invalidAttribute]);

                Log::warning('Brevo createDeal: attributo non valido rimosso', [
                    'attribute' => $invalidAttribute,
                ]);

                $response = $this->postDeal($payload);
            }

            if ($response->failed()) {
                Log::warning('Brevo createDeal fallito', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
            }

            Log::info('dealAttributes', [$payload['attributes']]);

            return $response->json();

        } catch (\Exception $e) {

            Log::error('Brevo createDeal error', [
                'message' => $e->getMessage()
            ]);

            return null;
        }
    }

    public function syncLeadContact(\App\Models\Lead $lead): ?int
    {
        if (! $lead->email) {
            Log::info('Brevo contact non aggiornato: email lead mancante', [
                'lead_id' => $lead->id,
            ]);

            return null;
        }

        $response = $this->createContact([
            'email' => $lead->email,
            'attributes' => array_filter([
                'firstname' => $lead->name,
                'phone' => $lead->phone,
                'club' => $lead->club,
                'city' => $lead->city,
                'lead_status' => $lead->status,
                'quote_amount' => $lead->quote_amount,
                'payment_amount' => $lead->payment_amount,
                'payment_link' => $lead->payment_link,
                'utm_source' => $lead->utm_source,
                'utm_medium' => $lead->utm_medium,
                'utm_campaign' => $lead->utm_campaign,
                'utm_term' => $lead->utm_term,
                'gclid' => $lead->gclid,
                'landing_page' => $lead->landing_page,
            ], fn ($value) => $value !== null && $value !== ''),
            'listIds' => $lead->marketing_consent ? [2] : [],
        ]);

        return $response && method_exists($response, 'getId')
            ? $response->getId()
            : null;
    }

    public function createDealForLead(Lead $lead, ?int $linkedContactId = null): ?array
    {
        return $this->createDeal([
            'name' => $this->dealNameForLead($lead),
            'pipelineId' => config('services.brevo.pipeline_landing'),
            'dealStageId' => config('services.brevo.lead_stages.' . $lead->status, config('services.brevo.first_stage')),
            'linkedContactsIds' => $linkedContactId ? [$linkedContactId] : [],
            'dealAttributes' => $this->dealAttributesForLead($lead),
        ]);
    }

    public function dealNameForLead(Lead $lead): string
    {
        return trim(($lead->club ?: 'WhatsApp') . ' - ' . ($lead->name ?: $lead->phone ?: $lead->uuid), ' -');
    }

    public function dealAttributesForLead(Lead $lead, bool $includeNulls = false): array
    {
        $attributes = [
            'phone' => $lead->phone,
            'city' => $lead->city,
            'club' => $lead->club,
            'message' => $lead->message,
            'amount' => $lead->payment_amount ?: $lead->quote_amount,
            'payment_link' => $lead->payment_link,
            'landing_page' => $lead->landing_page,
            'utm_source' => $lead->utm_source,
            'utm_medium' => $lead->utm_medium,
            'utm_campaign' => $lead->utm_campaign,
            'utm_term' => $lead->utm_term,
            'utm_content' => $lead->utm_content,
            'gclid' => $lead->gclid,
            'fbclid' => $lead->fbclid,
            'device' => $lead->device,
        ];

        if ($includeNulls) {
            return array_filter($attributes, fn ($value) => $value !== '');
        }

        return array_filter($attributes, fn ($value) => $value !== null && $value !== '');
    }

    public function updateDealStage(string $dealId, string $leadStatus, array $attributes = [], ?int $linkedContactId = null, ?string $dealName = null): ?array
    {
        $pipelineId = config('services.brevo.pipeline_landing');
        $stageId = config("services.brevo.lead_stages.{$leadStatus}");

        if (! $pipelineId || ! $stageId) {
            Log::warning('Brevo stage non configurato per stato lead', [
                'deal_id' => $dealId,
                'lead_status' => $leadStatus,
            ]);

            return null;
        }

        try {
            $response = $this->patchDeal($dealId, [
                ...$attributes,
                'pipeline' => $pipelineId,
                'deal_stage' => $stageId,
            ], $linkedContactId, $dealName);

            if ($response->failed()) {
                Log::warning('Brevo updateDealStage fallito', [
                    'deal_id' => $dealId,
                    'lead_status' => $leadStatus,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Brevo updateDealStage error', [
                'deal_id' => $dealId,
                'lead_status' => $leadStatus,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function updateDealAttributes(string $dealId, array $attributes = [], ?int $linkedContactId = null, ?string $dealName = null): ?array
    {
        try {
            $response = $this->patchDeal($dealId, $attributes, $linkedContactId, $dealName);

            if ($response->failed()) {
                Log::warning('Brevo updateDealAttributes fallito', [
                    'deal_id' => $dealId,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Brevo updateDealAttributes error', [
                'deal_id' => $dealId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function patchDeal(string $dealId, array $attributes = [], ?int $linkedContactId = null, ?string $dealName = null)
    {
        $payload = [
            'attributes' => array_filter($attributes, fn ($value) => $value !== ''),
        ];

        if ($dealName) {
            $payload['name'] = $dealName;
        }

        if ($linkedContactId) {
            $payload['linkedContactsIds'] = [$linkedContactId];
        }

        $response = $this->sendPatchDeal($dealId, $payload);

        while ($response->failed() && $invalidAttribute = $this->invalidDealAttribute($response->json('message'))) {
            unset($payload['attributes'][$invalidAttribute]);

            Log::warning('Brevo patchDeal: attributo non valido rimosso', [
                'deal_id' => $dealId,
                'attribute' => $invalidAttribute,
            ]);

            $response = $this->sendPatchDeal($dealId, $payload);
        }

        if ($response->successful() && $linkedContactId) {
            $this->linkDealContact($dealId, $linkedContactId);
        }

        return $response;
    }

    private function sendPatchDeal(string $dealId, array $payload)
    {
        return Http::withHeaders([
            'api-key' => config('services.brevo.api_key'),
            'Content-Type' => 'application/json',
            'accept' => 'application/json',
        ])->patch("https://api.brevo.com/v3/crm/deals/{$dealId}", $payload);
    }

    private function postDeal(array $payload)
    {
        return Http::withHeaders([
            'api-key' => config('services.brevo.api_key'),
            'Content-Type' => 'application/json',
            'accept' => 'application/json',
        ])->post('https://api.brevo.com/v3/crm/deals', $payload);
    }

    private function linkDealContact(string $dealId, int $contactId): void
    {
        $response = Http::withHeaders([
            'api-key' => config('services.brevo.api_key'),
            'Content-Type' => 'application/json',
            'accept' => 'application/json',
        ])->patch("https://api.brevo.com/v3/crm/deals/link-unlink/{$dealId}", [
            'linkContactIds' => [$contactId],
        ]);

        if ($response->failed()) {
            Log::warning('Brevo linkDealContact fallito', [
                'deal_id' => $dealId,
                'contact_id' => $contactId,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
        }
    }

    private function invalidDealAttribute(?string $message): ?string
    {
        if (! $message) {
            return null;
        }

        preg_match('/Invalid attribute:\s*([A-Za-z0-9_]+)/', $message, $matches);

        return $matches[1] ?? null;
    }
}
