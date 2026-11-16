<?php

namespace App\Services;

use App\Models\LeadOrderCheckout;
use App\Models\LeadSalesSheet;

class CheckoutOrderLeadStatusService
{
    public function __construct(private readonly LeadConversionTrackingService $tracking) {}

    public function proformaSent(LeadOrderCheckout $checkout): void
    {
        $lead = $checkout->lead;
        if (! $lead || ! $this->isFirstOrder($checkout) || in_array($lead->status, ['order_completed', 'lost'], true)) return;

        $lead->forceFill(['status' => 'payment_pending'])->save();
    }

    public function paymentReceived(LeadOrderCheckout $checkout): void
    {
        $lead = $checkout->lead;
        if (! $lead || ! $this->isFirstOrder($checkout) || $lead->status === 'order_completed') return;

        $lead->forceFill(['status' => 'order_completed'])->save();
        $this->tracking->trackForCurrentStatus($lead);
    }

    private function isFirstOrder(LeadOrderCheckout $checkout): bool
    {
        return (int) LeadSalesSheet::query()->where('lead_id', $checkout->lead_id)->min('id') === (int) $checkout->lead_sales_sheet_id;
    }
}
