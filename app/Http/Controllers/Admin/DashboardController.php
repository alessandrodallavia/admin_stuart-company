<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\GoogleAdsReportingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, GoogleAdsReportingService $googleAds): View
    {
        $dateFrom = $this->date($request->string('date_from')->toString(), now()->startOfMonth());
        $dateTo = $this->date($request->string('date_to')->toString(), now());
        $search = trim($request->string('q')->toString());
        $statuses = $this->statuses();
        $requestedStatuses = $request->input('statuses', $request->filled('status') ? [$request->input('status')] : []);
        $selectedStatuses = collect(is_array($requestedStatuses) ? $requestedStatuses : [$requestedStatuses])
            ->filter(fn ($status) => is_string($status) && array_key_exists($status, $statuses))
            ->unique()
            ->values()
            ->all();

        if ($dateFrom->greaterThan($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $baseQuery = Lead::query()
            ->whereBetween('created_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->when($selectedStatuses !== [], fn (Builder $query) => $query->whereIn('status', $selectedStatuses))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('utm_campaign', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('product', 'like', "%{$search}%");
                });
            });

        $recordsCount = (clone $baseQuery)->count();
        $preLeadsCount = (clone $baseQuery)->where('status', 'pre')->count();
        $workedQuery = (clone $baseQuery)->where('status', '!=', 'pre');
        $workedLeadsCount = (clone $workedQuery)->count();
        $chatStartedCount = (clone $baseQuery)
            ->where(function (Builder $query) {
                $query
                    ->whereHas('linkedWhatsappConversation.messages', fn (Builder $query) => $query->where('direction', 'inbound'))
                    ->orWhereHas('whatsappConversation.messages', fn (Builder $query) => $query->where('direction', 'inbound'));
            })
            ->count();
        $quotesQuery = (clone $workedQuery)->whereHas('quotePdfs');
        $quotesCount = (clone $quotesQuery)->count();
        $paymentsQuery = (clone $workedQuery)->where('status', 'order_completed');
        $paymentsCount = (clone $paymentsQuery)->count();
        $quoteValue = (float) (clone $quotesQuery)->sum('quote_amount');
        $paymentValue = (float) (clone $paymentsQuery)->sum('payment_amount');
        $quantityQuery = (clone $workedQuery)->whereNotNull('quantity');
        $quantityCoverage = (clone $quantityQuery)->count();
        $averageQuantity = $quantityCoverage > 0 ? (float) $quantityQuery->avg('quantity') : null;
        $marginQuery = (clone $paymentsQuery)->whereNotNull('margin_amount');
        $marginCoverage = (clone $marginQuery)->count();
        $marginValue = $marginCoverage > 0 ? (float) $marginQuery->sum('margin_amount') : null;
        $marginIsComplete = $paymentsCount > 0 && $marginCoverage === $paymentsCount;

        $openPipelineQuery = (clone $quotesQuery)->whereNotIn('status', ['order_completed', 'lost']);
        $wonPipelineQuery = (clone $quotesQuery)->where('status', 'order_completed');
        $lostPipelineQuery = (clone $quotesQuery)->where('status', 'lost');

        $stats = [
            'records' => $recordsCount,
            'pre_leads' => $preLeadsCount,
            'worked_leads' => $workedLeadsCount,
            'chat_started' => $chatStartedCount,
            'quotes' => $quotesCount,
            'payments' => $paymentsCount,
            'quote_value' => $quoteValue,
            'payment_value' => $paymentValue,
            'average_quote' => $quotesCount > 0 ? $quoteValue / $quotesCount : null,
            'average_payment' => $paymentsCount > 0 ? $paymentValue / $paymentsCount : null,
            'average_quantity' => $averageQuantity,
            'quantity_coverage' => $quantityCoverage,
            'prelead_to_chat' => $recordsCount > 0 ? ($chatStartedCount / $recordsCount) * 100 : null,
            'worked_to_quote' => $workedLeadsCount > 0 ? ($quotesCount / $workedLeadsCount) * 100 : null,
            'quote_to_payment' => $quotesCount > 0 ? ($paymentsCount / $quotesCount) * 100 : null,
            'worked_to_payment' => $workedLeadsCount > 0 ? ($paymentsCount / $workedLeadsCount) * 100 : null,
            'margin' => $marginValue,
            'margin_coverage' => $marginCoverage,
            'margin_is_complete' => $marginIsComplete,
            'pipeline_open_count' => (clone $openPipelineQuery)->count(),
            'pipeline_open_value' => (float) (clone $openPipelineQuery)->sum('quote_amount'),
            'pipeline_won_count' => (clone $wonPipelineQuery)->count(),
            'pipeline_won_value' => (float) (clone $wonPipelineQuery)->sum('quote_amount'),
            'pipeline_lost_count' => (clone $lostPipelineQuery)->count(),
            'pipeline_lost_value' => (float) (clone $lostPipelineQuery)->sum('quote_amount'),
        ];

        try {
            $ads = $googleAds->performance($dateFrom, $dateTo);
        } catch (\Throwable $exception) {
            report($exception);
            $ads = ['available' => false, 'error' => 'Google Ads non raggiungibile. Riprova più tardi.'];
        }

        $spend = (float) ($ads['spend'] ?? 0);
        $ads['cost_per_record'] = $recordsCount > 0 ? $spend / $recordsCount : null;
        $ads['cost_per_chat'] = $chatStartedCount > 0 ? $spend / $chatStartedCount : null;
        $ads['cac'] = $paymentsCount > 0 ? $spend / $paymentsCount : null;
        $ads['roas'] = $spend > 0 ? $paymentValue / $spend : null;
        $ads['romi'] = $spend > 0 && $marginIsComplete ? $marginValue / $spend : null;

        $excludePreLeadsFromTable = $selectedStatuses === [];
        $leads = (clone $baseQuery)
            ->when($excludePreLeadsFromTable, fn (Builder $query) => $query->where('status', '!=', 'pre'))
            ->with('quotePdfs')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.crm-dashboard', [
            'leads' => $leads,
            'stats' => $stats,
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
            'search' => $search,
            'selectedStatuses' => $selectedStatuses,
            'statuses' => $statuses,
            'excludePreLeadsFromTable' => $excludePreLeadsFromTable,
            'ads' => $ads,
        ]);
    }

    private function date(string $value, Carbon $fallback): Carbon
    {
        if ($value === '') {
            return $fallback;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function statuses(): array
    {
        return [
            'pre' => 'Pre lead',
            'confirmed' => 'Confermato',
            'completed' => 'Da lavorare',
            'quote_sent' => 'Proposta inviata',
            'link_sent' => 'Link inviato',
            'proforma_pending' => 'Proforma da inviare',
            'payment_pending' => 'Pagamento in attesa',
            'order_completed' => 'Pagato',
            'lost' => 'Perso',
        ];
    }
}
