<?php

namespace App\Livewire\Admin\Documents;

use App\Models\AdminDocument;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public function render()
    {
        $type = request()->string('type')->toString();
        $status = request()->string('status')->toString();
        $paymentStatus = request()->string('payment_status')->toString();
        $search = trim(request()->string('q')->toString());

        return view('livewire.admin.documents.index', [
            'documents' => AdminDocument::query()
                ->withCount('items')
                ->when($type !== '', fn ($query) => $query->where('type', $type))
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->when($paymentStatus !== '', fn ($query) => $query->where('payment_status', $paymentStatus))
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere('customer_name', 'like', "%{$search}%")
                            ->orWhere('customer_email', 'like', "%{$search}%")
                            ->orWhere('customer_phone', 'like', "%{$search}%");
                    });
                })
                ->latest('document_date')
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'types' => AdminDocument::TYPES,
            'statuses' => AdminDocument::STATUSES,
            'paymentStatuses' => AdminDocument::PAYMENT_STATUSES,
            'currentType' => $type,
            'currentStatus' => $status,
            'currentPaymentStatus' => $paymentStatus,
            'search' => $search,
            'stats' => $this->stats(),
        ]);
    }

    private function stats(): array
    {
        return [
            'total' => AdminDocument::count(),
            'open_total' => AdminDocument::whereIn('payment_status', ['unpaid', 'partial', 'overdue'])->sum('total'),
            'overdue' => AdminDocument::where('payment_status', 'overdue')->count(),
            'by_type' => AdminDocument::query()
                ->selectRaw('type, count(*) as aggregate')
                ->groupBy('type')
                ->pluck('aggregate', 'type')
                ->all(),
        ];
    }
}
