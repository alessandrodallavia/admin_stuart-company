<?php

namespace App\Livewire\Admin\Documents;

use App\Models\AdminDocumentPaymentSchedule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class PaymentsIndex extends Component
{
    use WithPagination;

    public function paginationView(): string
    {
        return 'vendor.pagination.livewire-tailwind';
    }

    #[Url(as: 'status', except: '')]
    public string $status = '';

    public function filter(string $status = ''): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.admin.documents.payments-index', [
            'payments' => AdminDocumentPaymentSchedule::query()
                ->with('document')
                ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
                ->orderBy('due_date')
                ->paginate(20),
        ]);
    }
}
