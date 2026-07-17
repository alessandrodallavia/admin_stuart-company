<?php

namespace App\Livewire\Admin;

use App\Models\CrmPrintType;
use App\Models\CrmProduct;
use App\Models\Lead;
use App\Models\LeadSalesItem;
use App\Models\LeadSalesItemPrint;
use App\Services\LeadSalesSheetService;
use Livewire\Component;

class LeadSalesSheet extends Component
{
    public int $leadId;
    public string $productId = '';
    public string $configurationName = '';
    public string $quantity = '';
    public array $printTypeIds = [];
    public ?string $statusMessage = null;

    public function mount(int $leadId): void
    {
        $this->leadId = $leadId;
        $this->lead();
    }

    public function addProduct(LeadSalesSheetService $calculator): void
    {
        $data = $this->validate([
            'productId' => ['required', 'exists:crm_products,id'],
            'configurationName' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        $product = CrmProduct::query()->with('priceTiers')->where('is_active', true)->findOrFail($data['productId']);
        $tier = $calculator->tier($product->priceTiers, (float) $data['quantity']);

        if (! $tier) {
            $this->addError('quantity', 'Nessuna fascia prezzo configurata per questa quantità.');
            return;
        }

        $sheet = $this->lead()->salesSheet()->firstOrCreate([], [
            'revenue_total' => 0,
            'cost_total' => 0,
            'margin_total' => 0,
            'margin_percentage' => 0,
        ]);

        $sheet->items()->create([
            'crm_product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'configuration_name' => filled($data['configurationName'] ?? null) ? trim($data['configurationName']) : null,
            'quantity' => $data['quantity'],
            'product_unit_cost' => $product->unit_cost,
            'product_unit_price' => $tier->unit_price,
        ]);

        $calculator->recalculate($sheet);
        $this->reset('productId', 'configurationName', 'quantity');
        $this->resetValidation();
        $this->statusMessage = 'Prodotto aggiunto alla scheda vendita.';
    }

    public function removeProduct(int $itemId, LeadSalesSheetService $calculator): void
    {
        $lead = $this->lead();
        $item = LeadSalesItem::query()->findOrFail($itemId);
        abort_unless($item->lead_sales_sheet_id === $lead->salesSheet?->id, 404);

        $sheet = $lead->salesSheet;
        $item->delete();
        $calculator->recalculate($sheet);
        unset($this->printTypeIds[$itemId]);
        $this->statusMessage = 'Prodotto rimosso.';
    }

    public function addPrint(int $itemId, LeadSalesSheetService $calculator): void
    {
        $lead = $this->lead();
        $item = LeadSalesItem::query()->findOrFail($itemId);
        abort_unless($item->lead_sales_sheet_id === $lead->salesSheet?->id, 404);

        $field = "printTypeIds.$itemId";
        $this->validate([$field => ['required', 'exists:crm_print_types,id']]);
        $type = CrmPrintType::query()->with('priceTiers')->where('is_active', true)->findOrFail($this->printTypeIds[$itemId]);
        $tier = $calculator->tier($type->priceTiers, (float) $item->quantity);

        if (! $tier) {
            $this->addError($field, 'Nessuna fascia prezzo disponibile per questa quantità.');
            return;
        }

        $item->prints()->create([
            'crm_print_type_id' => $type->id,
            'print_code' => $type->code,
            'print_name' => $type->name,
            'unit_cost' => $tier->unit_cost,
            'unit_price' => $tier->unit_price,
        ]);

        $calculator->recalculate($lead->salesSheet);
        unset($this->printTypeIds[$itemId]);
        $this->resetValidation($field);
        $this->statusMessage = 'Lavorazione aggiunta.';
    }

    public function removePrint(int $itemId, int $printId, LeadSalesSheetService $calculator): void
    {
        $lead = $this->lead();
        $item = LeadSalesItem::query()->findOrFail($itemId);
        $print = LeadSalesItemPrint::query()->findOrFail($printId);
        abort_unless($item->lead_sales_sheet_id === $lead->salesSheet?->id && $print->lead_sales_item_id === $item->id, 404);

        $print->delete();
        $calculator->recalculate($lead->salesSheet);
        $this->statusMessage = 'Lavorazione rimossa.';
    }

    public function render()
    {
        $lead = $this->lead()->load('salesSheet.items.prints');

        return view('livewire.admin.lead-sales-sheet', [
            'salesSheet' => $lead->salesSheet,
            'products' => CrmProduct::query()->where('is_active', true)->orderBy('code')->get(),
            'printTypes' => CrmPrintType::query()->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    private function lead(): Lead
    {
        $admin = auth('admin')->user();
        abort_unless($admin?->hasAdminPermission('leads.view'), 403);

        return Lead::query()->findOrFail($this->leadId);
    }
}
