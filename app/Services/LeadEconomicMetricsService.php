<?php

namespace App\Services;

use App\Models\Lead;

class LeadEconomicMetricsService
{
    public function __construct(private readonly GoogleAdsReportingService $googleAds) {}

    public function currentCac(): ?float
    {
        $dateFrom = now()->subDays(29)->startOfDay();
        $dateTo = now()->endOfDay();
        $paidLeads = Lead::query()
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('status', 'order_completed')
            ->count();

        if ($paidLeads === 0) {
            return null;
        }

        try {
            $ads = $this->googleAds->performance($dateFrom, $dateTo);
        } catch (\Throwable) {
            return null;
        }

        if (! ($ads['available'] ?? false) || ! array_key_exists('spend', $ads)) {
            return null;
        }

        return (float) $ads['spend'] / $paidLeads;
    }
}
