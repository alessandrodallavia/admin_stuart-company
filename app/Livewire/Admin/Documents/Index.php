<?php

namespace App\Livewire\Admin\Documents;

use App\Enums\DocumentType;
use App\Models\AdminDocument;
use App\Models\DocumentRelation;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public function render()
    {
        $type = request()->string('type')->toString() ?: 'offline_order';
        $status = $type !== '' ? request()->string('status')->toString() : '';
        $paymentStatus = request()->string('payment_status')->toString();
        $search = trim(request()->string('search')->toString() ?: request()->string('q')->toString());
        $searchNumber = trim(request()->string('search_number')->toString());

        return view('livewire.admin.documents.index', [
            'documents' => AdminDocument::query()
                ->withCount('items')
                ->when($type !== '', fn ($query) => $query->where('type', $type))
                ->when($type !== '' && $status !== '', fn ($query) => $query->where('status', $status))
                ->when($paymentStatus !== '', fn ($query) => $query->where('payment_status', $paymentStatus))
                ->when($searchNumber !== '', fn ($query) => $this->applyNumberSearch($query, $searchNumber))
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('customer_name', 'like', "%{$search}%")
                            ->orWhere('customer_email', 'like', "%{$search}%")
                            ->orWhere('customer_phone', 'like', "%{$search}%")
                            ->orWhere('customer_tax_code', 'like', "%{$search}%")
                            ->orWhere('customer_vat_number', 'like', "%{$search}%")
                            ->orWhere('customer_city', 'like', "%{$search}%")
                            ->orWhere('customer_recipient_code', 'like', "%{$search}%")
                            ->orWhere('customer_pec', 'like', "%{$search}%");
                    });
                })
                ->latest('document_date')
                ->latest('number')
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'types' => AdminDocument::TYPES,
            'statuses' => $type !== '' ? AdminDocument::statusesFor($type) : [],
            'paymentStatuses' => AdminDocument::PAYMENT_STATUSES,
            'currentType' => $type,
            'currentStatus' => $status,
            'currentPaymentStatus' => $paymentStatus,
            'search' => $search,
            'searchNumber' => $searchNumber,
            'stats' => $this->stats(),
            'documentAreas' => $this->documentAreas(),
        ]);
    }

    private function applyNumberSearch($query, string $searchNumber): void
    {
        $normalized = str($searchNumber)->upper()->replace(' ', '')->toString();
        $numeric = preg_replace('/\D+/', '', $normalized);

        $query->where(function ($query) use ($normalized, $numeric) {
            $query->where('code', 'like', "%{$normalized}%");

            if ($numeric !== '') {
                $query->orWhere('number', (int) $numeric);
            }

            if ($normalized === 'BOZZA') {
                $query->orWhere('status', 'draft')->orWhereNull('number');
            }
        });
    }

    private function stats(): array
    {
        $byType = AdminDocument::query()
            ->selectRaw('type, count(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type')
            ->all();

        return [
            'total' => AdminDocument::count(),
            'open_total' => $this->openTotal(),
            'overdue' => AdminDocument::where('payment_status', 'overdue')->count(),
            'by_type' => $byType,
            'totals_by_type' => AdminDocument::query()
                ->selectRaw('type, sum(total) as aggregate')
                ->groupBy('type')
                ->pluck('aggregate', 'type')
                ->all(),
        ];
    }

    private function documentAreas(): array
    {
        return collect(AdminDocument::TYPES)
            ->map(fn (string $label, string $type) => [
                'type' => $type,
                'label' => $label,
                'description' => match ($type) {
                    'quote' => 'Richieste, offerte e trattative aperte',
                    'proforma' => 'Proforme e acconti prima della fattura',
                    'offline_order' => 'Ordini interni e avanzamento acquisto',
                    'delivery_note' => 'Documenti di trasporto e consegne',
                    'invoice' => 'Fatture, XML, SDI e incassi',
                    default => 'Documenti amministrativi',
                },
            ])
            ->values()
            ->all();
    }

    private function openTotal(): float
    {
        $documents = AdminDocument::query()
            ->with('paymentSchedules')
            ->where('type', 'invoice')
            ->get()
            ->keyBy(fn (AdminDocument $document) => $this->nodeKey($document->currentDocumentType(), $document->id));

        $relations = DocumentRelation::query()->get();
        $adjacency = [];

        foreach ($relations as $relation) {
            $from = $relation->from_type->value.'-'.$relation->from_id;
            $to = $relation->to_type->value.'-'.$relation->to_id;

            if (! $documents->has($from) || ! $documents->has($to)) {
                continue;
            }

            $adjacency[$from][] = $to;
            $adjacency[$to][] = $from;
        }

        $visited = [];
        $total = 0.0;

        foreach ($documents as $key => $document) {
            if (isset($visited[$key])) {
                continue;
            }

            $component = $this->documentComponent($key, $adjacency, $visited);
            $total += $this->componentOpenTotal($component, $documents);
        }

        return round($total, 2);
    }

    private function documentComponent(string $startKey, array $adjacency, array &$visited): array
    {
        $queue = [$startKey];
        $component = [];

        while ($queue) {
            $key = array_shift($queue);

            if (isset($visited[$key])) {
                continue;
            }

            $visited[$key] = true;
            $component[] = $key;

            foreach ($adjacency[$key] ?? [] as $nextKey) {
                if (! isset($visited[$nextKey])) {
                    $queue[] = $nextKey;
                }
            }
        }

        return $component;
    }

    private function componentOpenTotal(array $component, $documents): float
    {
        $selected = collect($component)
            ->map(fn ($key) => $documents->get($key))
            ->filter()
            ->sortBy(fn (AdminDocument $document) => $this->receivablePriority($document))
            ->first();

        if (! $selected || $selected->payment_status === 'paid' || $selected->payment_status === 'not_managed') {
            return 0.0;
        }

        $paid = (float) $selected->paymentSchedules->sum('paid_amount');

        return max(0, (float) $selected->total - $paid);
    }

    private function receivablePriority(AdminDocument $document): int
    {
        return match ($document->type) {
            'invoice' => 10,
            default => 100,
        };
    }

    private function nodeKey(DocumentType $type, int $id): string
    {
        return $type->value.'-'.$id;
    }
}
