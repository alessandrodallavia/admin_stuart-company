<?php

namespace App\Observers;

use App\Models\Lead;
use App\Services\BrevoEmailService;
use Illuminate\Support\Facades\Log;

class LeadObserver
{
    public function updated(Lead $lead): void
    {
        $watchedFields = [
            'status',
            'name',
            'email',
            'phone',
            'quote_amount',
            'payment_amount',
            'payment_link',
            'whatsapp_conversation_id',
        ];

        if (! $lead->wasChanged($watchedFields)) {
            return;
        }

        $brevo = app(BrevoEmailService::class);
        $linkedContactId = null;

        if ($lead->wasChanged(['name', 'email', 'phone'])) {
            $linkedContactId = $brevo->syncLeadContact($lead);
        }

        if (! $lead->pipeline_lead_id) {
            $responseDeal = $brevo->createDealForLead($lead, $linkedContactId);

            if (! empty($responseDeal['id'])) {
                $lead->forceFill([
                    'pipeline_lead_id' => $responseDeal['id'],
                ])->saveQuietly();

                return;
            }

            Log::warning('Lead non inviato a Brevo: creazione deal fallita', [
                'lead_id' => $lead->id,
                'status' => $lead->status,
                'response' => $responseDeal,
            ]);

            return;
        }

        if ($lead->wasChanged('status')) {
            $brevo->updateDealStage(
                $lead->pipeline_lead_id,
                $lead->status,
                $brevo->dealAttributesForLead($lead, includeNulls: true),
                $linkedContactId,
                $brevo->dealNameForLead($lead)
            );

            return;
        }

        $brevo->updateDealAttributes(
            $lead->pipeline_lead_id,
            $brevo->dealAttributesForLead($lead, includeNulls: true),
            $linkedContactId,
            $brevo->dealNameForLead($lead)
        );
    }
}
