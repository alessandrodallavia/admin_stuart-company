<?php

namespace App\Livewire\Admin;

use App\Jobs\SendLeadOrder;
use App\Models\CrmPrintType;
use App\Models\CrmProduct;
use App\Models\EmailAccount;
use App\Models\Lead;
use App\Models\LeadSalesItem;
use App\Models\LeadSalesItemAttachment;
use App\Models\LeadSalesItemPrint;
use App\Services\LeadSalesSheetService;
use App\Services\LeadEconomicMetricsService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class LeadSalesSheet extends Component
{
    use WithFileUploads;

    public int $leadId;

    public string $productId = '';

    public string $configurationName = '';

    public string $quantity = '';

    public string $finalUnitPrice = '';

    public string $shippingFee = '9.90';

    public string $freeShippingThreshold = '250.00';

    public array $printTypeIds = [];

    public array $itemFinalPrices = [];

    public array $itemColors = [];

    public array $itemNotes = [];

    public array $itemUploads = [];

    public string $orderName = '';

    public ?string $statusMessage = null;

    public function mount(int $leadId): void
    {
        $this->leadId = $leadId;
        $lead = $this->lead()->load('salesSheet.items');
        $this->orderName = $lead->name ?: '';
        $this->syncShippingFields($lead->salesSheet);

        foreach ($lead->salesSheet?->items ?? [] as $item) {
            $this->syncItemFields($item);
        }
    }

    public function updatedProductId(): void
    {
        $this->suggestFinalPrice();
    }

    public function updatedQuantity(): void
    {
        $this->suggestFinalPrice();
    }

    public function addProduct(LeadSalesSheetService $calculator): void
    {
        $this->authorizeManage();
        $data = $this->validate([
            'productId' => ['required', 'exists:crm_products,id'],
            'configurationName' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'finalUnitPrice' => ['required', 'numeric', 'min:0'],
        ]);

        $product = CrmProduct::query()->with('priceTiers')->where('is_active', true)->findOrFail($data['productId']);
        $tier = $calculator->tier($product->priceTiers, (float) $data['quantity']);

        if (! $tier) {
            $this->addError('quantity', 'Nessuna fascia prezzo configurata per questa quantità.');

            return;
        }

        $sheet = $this->lead()->salesSheet()->firstOrCreate([], [
            'shipping_fee' => 9.90,
            'free_shipping_threshold' => 250,
            'revenue_total' => 0,
            'cost_total' => 0,
            'margin_total' => 0,
            'margin_percentage' => 0,
        ]);

        $item = $sheet->items()->create([
            'crm_product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'configuration_name' => filled($data['configurationName'] ?? null) ? trim($data['configurationName']) : null,
            'quantity' => $data['quantity'],
            'product_unit_cost' => $product->unit_cost,
            'product_unit_price' => $tier->unit_price,
            'final_unit_price' => $data['finalUnitPrice'],
            'final_price_overridden' => abs((float) $data['finalUnitPrice'] - (float) $tier->unit_price) > 0.0001,
        ]);

        $calculator->recalculate($sheet);
        $this->syncItemFields($item);
        $this->reset('productId', 'configurationName', 'quantity', 'finalUnitPrice');
        $this->resetValidation();
        $this->statusMessage = 'Prodotto aggiunto alla scheda vendita.';
    }

    public function removeProduct(int $itemId, LeadSalesSheetService $calculator): void
    {
        $this->authorizeManage();
        $lead = $this->lead();
        $item = LeadSalesItem::query()->with('attachments')->findOrFail($itemId);
        abort_unless($item->lead_sales_sheet_id === $lead->salesSheet?->id, 404);

        $sheet = $lead->salesSheet;
        foreach ($item->attachments as $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }
        $item->delete();
        $calculator->recalculate($sheet);
        unset($this->printTypeIds[$itemId], $this->itemFinalPrices[$itemId], $this->itemColors[$itemId], $this->itemNotes[$itemId], $this->itemUploads[$itemId]);
        $this->statusMessage = 'Prodotto rimosso.';
    }

    public function addPrint(int $itemId, LeadSalesSheetService $calculator): void
    {
        $this->authorizeManage();
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
        $this->syncItemFields($item->fresh());
        unset($this->printTypeIds[$itemId]);
        $this->resetValidation($field);
        $this->statusMessage = 'Lavorazione aggiunta.';
    }

    public function removePrint(int $itemId, int $printId, LeadSalesSheetService $calculator): void
    {
        $this->authorizeManage();
        $lead = $this->lead();
        $item = LeadSalesItem::query()->findOrFail($itemId);
        $print = LeadSalesItemPrint::query()->findOrFail($printId);
        abort_unless($item->lead_sales_sheet_id === $lead->salesSheet?->id && $print->lead_sales_item_id === $item->id, 404);

        $print->delete();
        $calculator->recalculate($lead->salesSheet);
        $this->syncItemFields($item->fresh());
        $this->statusMessage = 'Lavorazione rimossa.';
    }

    public function updateFinalPrice(int $itemId, LeadSalesSheetService $calculator): void
    {
        $this->authorizeManage();
        $item = $this->itemForLead($itemId);
        $field = "itemFinalPrices.$itemId";
        $this->validate([$field => ['required', 'numeric', 'min:0']]);
        $item->forceFill([
            'final_unit_price' => $this->itemFinalPrices[$itemId],
            'final_price_overridden' => true,
        ])->save();
        $calculator->recalculate($item->leadSalesSheet);
        $this->syncItemFields($item->fresh());
        $this->statusMessage = 'Prezzo finale aggiornato.';
    }

    public function resetFinalPrice(int $itemId, LeadSalesSheetService $calculator): void
    {
        $this->authorizeManage();
        $item = $this->itemForLead($itemId);
        $item->forceFill(['final_price_overridden' => false])->save();
        $calculator->recalculate($item->leadSalesSheet);
        $this->syncItemFields($item->fresh());
        $this->statusMessage = 'Prezzo finale ripristinato al calcolo automatico.';
    }

    public function saveShipping(LeadSalesSheetService $calculator): void
    {
        $this->authorizeManage();
        $data = $this->validate([
            'shippingFee' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'freeShippingThreshold' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);
        $sheet = $this->lead()->salesSheet()->firstOrCreate([], [
            'revenue_total' => 0,
            'cost_total' => 0,
            'margin_total' => 0,
            'margin_percentage' => 0,
        ]);
        $sheet->update([
            'shipping_fee' => $data['shippingFee'],
            'free_shipping_threshold' => $data['freeShippingThreshold'],
        ]);
        $calculator->recalculate($sheet);
        $this->syncShippingFields($sheet->fresh());
        $this->statusMessage = 'Regole di spedizione aggiornate.';
    }

    public function saveItemDetails(int $itemId): void
    {
        $this->authorizeManage();
        $item = $this->itemForLead($itemId);
        $this->validate([
            "itemColors.$itemId" => ['nullable', 'string', 'max:2000'],
            "itemNotes.$itemId" => ['nullable', 'string', 'max:10000'],
            "itemUploads.$itemId" => ['nullable', 'array', 'max:10'],
            "itemUploads.$itemId.*" => ['file', 'max:10240'],
        ]);

        $this->persistItemDetails($item);
        $this->statusMessage = 'Colori, note e grafiche salvati.';
    }

    public function removeAttachment(int $itemId, int $attachmentId): void
    {
        $this->authorizeManage();
        $item = $this->itemForLead($itemId);
        $attachment = LeadSalesItemAttachment::query()->findOrFail($attachmentId);
        abort_unless($attachment->lead_sales_item_id === $item->id, 404);
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
        $this->statusMessage = 'File grafico rimosso.';
    }

    public function sendOrder(): void
    {
        $this->authorizeManage();
        $admin = auth('admin')->user();
        abort_if($admin->training_mode_active, 403, 'Invio reale non disponibile in modalità formazione.');
        $this->validate(['orderName' => ['required', 'string', 'max:100']]);

        $lead = $this->lead()->load(['salesSheet.items.prints', 'salesSheet.items.attachments']);
        $sheet = $lead->salesSheet;

        if (! $sheet || $sheet->items->isEmpty()) {
            $this->addError('orderName', 'Aggiungi almeno un prodotto prima dell’invio.');

            return;
        }

        $this->validate([
            'itemColors.*' => ['nullable', 'string', 'max:2000'],
            'itemNotes.*' => ['nullable', 'string', 'max:10000'],
            'itemUploads.*' => ['nullable', 'array', 'max:10'],
            'itemUploads.*.*' => ['file', 'max:10240'],
        ]);

        foreach ($sheet->items as $item) {
            $this->persistItemDetails($item);
        }

        $sheet->load(['items.prints', 'items.attachments']);

        $account = EmailAccount::query()
            ->where('admin_user_id', $admin->id)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (! $account) {
            $this->addError('orderName', 'Configura una casella email attiva per l’operatore prima dell’invio.');

            return;
        }

        $orderName = trim($this->orderName);
        $version = (int) $sheet->dispatches()->where('order_name', $orderName)->max('version') + 1;
        $slug = Str::slug($orderName) ?: 'ordine';
        $filename = 'Ordine-Lead-'.$slug.($version > 1 ? '-v'.$version : '').'.zip';
        $dispatchData = [
            'admin_user_id' => $admin->id,
            'order_name' => $orderName,
            'version' => $version,
            'filename' => $filename,
            'to_email' => config('lead_orders.to.email'),
            'status' => 'pending',
        ];

        // Compatibilità temporanea con database che hanno già eseguito
        // la prima versione della migrazione, dove cc_email era obbligatorio.
        if (Schema::hasColumn('lead_order_dispatches', 'cc_email')) {
            $dispatchData['cc_email'] = '';
        }

        $dispatch = $sheet->dispatches()->create($dispatchData);

        SendLeadOrder::dispatch($dispatch->id, $account->id);
        $this->statusMessage = "Ordine in preparazione per Alessandro ({$filename}).";
    }

    public function render(LeadEconomicMetricsService $economicMetrics)
    {
        $lead = $this->lead()->load(['salesSheet.items.prints', 'salesSheet.items.attachments', 'salesSheet.dispatches']);
        $sheet = $lead->salesSheet;
        $cac = $economicMetrics->currentCac();
        $margin = $sheet?->items?->isNotEmpty() ? (float) $sheet->margin_total : null;
        $profitAfterAds = $margin !== null && $cac !== null ? $margin - $cac : null;
        $profitPercentage = $profitAfterAds !== null && (float) $sheet->revenue_total > 0
            ? ($profitAfterAds / (float) $sheet->revenue_total) * 100
            : null;
        $economicStatus = match (true) {
            $profitPercentage === null => ['label' => 'N.D.', 'class' => 'bg-white/10 text-white'],
            $profitPercentage < 0 => ['label' => 'Non sostenibile', 'class' => 'bg-red-600 text-white'],
            $profitPercentage < 10 => ['label' => 'Al limite', 'class' => 'bg-amber-500 text-black-nike'],
            default => ['label' => 'Redditizio', 'class' => 'bg-whatsapp text-white'],
        };

        return view('livewire.admin.lead-sales-sheet', [
            'salesSheet' => $sheet,
            'currentCac' => $cac,
            'profitAfterAds' => $profitAfterAds,
            'profitPercentage' => $profitPercentage,
            'economicStatus' => $economicStatus,
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

    private function authorizeManage(): void
    {
        abort_unless(auth('admin')->user()?->hasAdminPermission('leads.manage'), 403);
    }

    private function itemForLead(int $itemId): LeadSalesItem
    {
        $lead = $this->lead();
        $item = LeadSalesItem::query()->findOrFail($itemId);
        abort_unless($item->lead_sales_sheet_id === $lead->salesSheet?->id, 404);

        return $item;
    }

    private function syncItemFields(LeadSalesItem $item): void
    {
        $this->itemFinalPrices[$item->id] = number_format((float) $item->final_unit_price, 2, '.', '');
        $this->itemColors[$item->id] = collect($item->colors)->join("\n");
        $this->itemNotes[$item->id] = $item->notes ?? '';
    }

    private function syncShippingFields(?\App\Models\LeadSalesSheet $sheet): void
    {
        $this->shippingFee = number_format((float) ($sheet?->shipping_fee ?? 9.90), 2, '.', '');
        $this->freeShippingThreshold = number_format((float) ($sheet?->free_shipping_threshold ?? 250), 2, '.', '');
    }

    private function persistItemDetails(LeadSalesItem $item): void
    {
        $colors = collect(preg_split('/[\r\n,;]+/', $this->itemColors[$item->id] ?? ''))
            ->map(fn ($color) => trim($color))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $item->update([
            'colors' => $colors ?: null,
            'notes' => filled($this->itemNotes[$item->id] ?? null) ? trim($this->itemNotes[$item->id]) : null,
        ]);

        foreach ($this->itemUploads[$item->id] ?? [] as $file) {
            $path = $file->store('lead-orders/'.$this->leadId.'/items/'.$item->id, 'local');
            $item->attachments()->create([
                'disk' => 'local',
                'path' => $path,
                'filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        $this->itemUploads[$item->id] = [];
    }

    private function suggestFinalPrice(): void
    {
        if (! $this->productId || ! is_numeric($this->quantity) || (float) $this->quantity <= 0) {
            $this->finalUnitPrice = '';

            return;
        }

        $product = CrmProduct::query()->with('priceTiers')->find($this->productId);
        $tier = $product ? app(LeadSalesSheetService::class)->tier($product->priceTiers, (float) $this->quantity) : null;
        $this->finalUnitPrice = $tier ? number_format((float) $tier->unit_price, 2, '.', '') : '';
    }
}
