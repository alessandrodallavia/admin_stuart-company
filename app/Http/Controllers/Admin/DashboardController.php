<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $dateFrom = $this->date($request->string('date_from')->toString(), now()->startOfMonth());
        $dateTo = $this->date($request->string('date_to')->toString(), now());
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        if ($dateFrom->greaterThan($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $baseQuery = Lead::query()
            ->whereBetween('created_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
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

        $leadsCount = (clone $baseQuery)->count();
        $quotesCount = (clone $baseQuery)->whereHas('quotePdfs')->count();
        $paymentsCount = (clone $baseQuery)->where('status', 'order_completed')->count();
        $quoteValue = (float) (clone $baseQuery)->sum('quote_amount');
        $paymentValue = (float) (clone $baseQuery)->where('status', 'order_completed')->sum('payment_amount');
        $marginValue = (float) (clone $baseQuery)->where('status', 'order_completed')->sum('margin_amount');

        $stats = [
            'leads' => $leadsCount,
            'quotes' => $quotesCount,
            'payments' => $paymentsCount,
            'quote_value' => $quoteValue,
            'payment_value' => $paymentValue,
            'average_quote' => $quotesCount > 0 ? $quoteValue / $quotesCount : 0,
            'average_payment' => $paymentsCount > 0 ? $paymentValue / $paymentsCount : 0,
            'average_quantity' => $leadsCount > 0 ? (float) (clone $baseQuery)->sum('quantity') / $leadsCount : 0,
            'lead_to_quote' => $leadsCount > 0 ? ($quotesCount / $leadsCount) * 100 : 0,
            'quote_to_payment' => $quotesCount > 0 ? ($paymentsCount / $quotesCount) * 100 : 0,
            'lead_to_payment' => $leadsCount > 0 ? ($paymentsCount / $leadsCount) * 100 : 0,
            'margin' => $marginValue,
        ];

        $leads = (clone $baseQuery)
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
            'currentStatus' => $status,
            'statuses' => $this->statuses(),
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
