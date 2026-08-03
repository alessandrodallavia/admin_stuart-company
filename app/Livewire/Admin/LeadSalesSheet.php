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
use App\Services\LeadEconomicMetricsService;
use App\Services\LeadSalesSheetService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class LeadSalesSheet extends Component
{
    use WithFileUploads;

    public int $leadId;

    public ?int $sheetId = null;

    public string $productId = '';

    public string $configurationName = '';

    public string $pricingGroupItemId = '';

    public string $quantity = '';

    public string $finalUnitPrice = '';

    public string $shippingFee = '9.90';

    public string $freeShippingThreshold = '250.00';

    public string $discountType = '';

    public string $discountValue = '0.00';

    public string $roundingAdjustment = '0.00';

    public array $printTypeIds = [];

    public array $itemFinalPrices = [];

    public array $itemColors = [];

    public array $itemNotes = [];

    public array $itemUploads = [];

    public string $orderName = '';

    public ?string $statusMessage = null;

    public function mount(int $leadId, ?int $sheetId = null): void
    {
        $this->leadId = $leadId;
        $this->sheetId = $sheetId;
        $lead = $this->lead()->load('salesSheets.items');
        $sheet = $this->sheet();
        $this->sheetId = $sheet?->id;
        $this->orderName = $lead->name ?: '';
        $this->syncOrderFields($sheet);

        foreach ($sheet?->items ?? [] as $item) {
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

    public function updatedPricingGroupItemId(): void
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
            'pricingGroupItemId' => ['nullable', 'integer'],
        ]);

        $product = CrmProduct::query()->with('priceTiers')->where('is_active', true)->findOrFail($data['productId']);
        $sheet = $this->sheet() ?? $this->createSheet();
        $groupItem = filled($data['pricingGroupItemId'] ?? null)
            ? $sheet->items()->findOrFail((int) $data['pricingGroupItemId'])
            : null;

        if ($groupItem && $groupItem->crm_product_id !== $product->id) {
            $this->addError('pricingGroupItemId', 'Il gruppo selezionato appartiene a un prodotto diverso.');

            return;
        }

        $pricingQuantity = (float) $data['quantity'] + ($groupItem
            ? (float) $sheet->items()->where('pricing_group_uuid', $groupItem->pricing_group_uuid)->sum('quantity')
            : 0);
        $tier = $calculator->tier($product->priceTiers, $pricingQuantity);

        if (! $tier) {
            $this->addError('quantity', 'Nessuna fascia prezzo configurata per la quantità complessiva del gruppo.');

            return;
        }

        $item = $sheet->items()->create([
            'crm_product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'configuration_name' => filled($data['configurationName'] ?? null) ? trim($data['configurationName']) : null,
            'pricing_group_uuid' => $groupItem?->pricing_group_uuid ?: (string) Str::uuid(),
            'pricing_group_name' => $groupItem?->pricing_group_name ?: (filled($data['configurationName'] ?? null) ? trim($data['configurationName']) : $product->name),
            'quantity' => $data['quantity'],
            'product_unit_cost' => $tier->unit_cost ?? $product->unit_cost,
            'product_unit_price' => $tier->unit_price,
            'final_unit_price' => $data['finalUnitPrice'],
            'final_price_overridden' => abs((float) $data['finalUnitPrice'] - (float) $tier->unit_price) > 0.0001,
        ]);

        $calculator->recalculate($sheet);
        foreach ($sheet->fresh()->items as $sheetItem) {
            $this->syncItemFields($sheetItem);
        }
        $this->reset('productId', 'configurationName', 'pricingGroupItemId', 'quantity', 'finalUnitPrice');
        $this->resetValidation();
        $this->statusMessage = 'Prodotto aggiunto alla scheda vendita.';
    }

    public function removeProduct(int $itemId, LeadSalesSheetService $calculator): void
    {
        $this->authorizeManage();
        $lead = $this->lead();
        $sheet = $this->sheet();
        $item = LeadSalesItem::query()->with('attachments')->findOrFail($itemId);
        abort_unless($item->lead_sales_sheet_id === $sheet?->id, 404);

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
        $sheet = $this->sheet();
        $item = LeadSalesItem::query()->findOrFail($itemId);
        abort_unless($item->lead_sales_sheet_id === $sheet?->id, 404);

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

        $calculator->recalculate($sheet);
        foreach ($sheet->fresh()->items as $sheetItem) {
            $this->syncItemFields($sheetItem);
        }
        unset($this->printTypeIds[$itemId]);
        $this->resetValidation($field);
        $this->statusMessage = 'Lavorazione aggiunta.';
    }

    public function removePrint(int $itemId, int $printId, LeadSalesSheetService $calculator): void
    {
        $this->authorizeManage();
        $lead = $this->lead();
        $sheet = $this->sheet();
        $item = LeadSalesItem::query()->findOrFail($itemId);
        $print = LeadSalesItemPrint::query()->findOrFail($printId);
        abort_unless($item->lead_sales_sheet_id === $sheet?->id && $print->lead_sales_item_id === $item->id, 404);

        $print->delete();
        $calculator->recalculate($sheet);
        foreach ($sheet->fresh()->items as $sheetItem) {
            $this->syncItemFields($sheetItem);
        }
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
        $sheet = $this->sheet() ?? $this->createSheet();
        $sheet->update([
            'shipping_fee' => $data['shippingFee'],
            'free_shipping_threshold' => $data['freeShippingThreshold'],
        ]);
        $calculator->recalculate($sheet);
        $this->syncOrderFields($sheet->fresh());
        $this->statusMessage = 'Regole di spedizione aggiornate.';
    }

    public function saveAdjustments(LeadSalesSheetService $calculator): void
    {
        $this->authorizeManage();
        $data = $this->validate([
            'discountType' => ['nullable', 'in:percentage,fixed'],
            'discountValue' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'roundingAdjustment' => ['required', 'numeric', 'min:-99999999.99', 'max:99999999.99'],
        ]);
        $sheet = $this->sheet() ?? $this->createSheet();
        $sheet->update([
            'discount_type' => filled($data['discountType']) ? $data['discountType'] : null,
            'discount_value' => $data['discountValue'],
            'rounding_adjustment' => $data['roundingAdjustment'],
        ]);
        $calculator->recalculate($sheet);
        $this->syncOrderFields($sheet->fresh());
        $this->statusMessage = 'Sconto e arrotondamento aggiornati.';
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

        $lead = $this->lead();
        $sheet = $this->sheet()?->load(['items.prints', 'items.attachments']);

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
        $lead = $this->lead();
        $sheet = $this->sheet()?->load(['items.prints', 'items.attachments', 'dispatches']);
        $isReorder = $sheet !== null && $lead->salesSheets()->whereKeyNot($sheet->id)->where('id', '<', $sheet->id)->exists();
        $cac = $isReorder ? 0.0 : $economicMetrics->currentCac();
        $margin = $sheet?->items?->isNotEmpty() ? (float) $sheet->margin_total : null;
        $profitAfterAds = $margin !== null && $cac !== null ? $margin - $cac : null;
        $profitPercentage = $profitAfterAds !== null && (float) $sheet->revenue_total > 0
            ? ($profitAfterAds / (float) $sheet->revenue_total) * 100
            : null;
        $maximumSustainableCac = $margin !== null && (float) $sheet->revenue_total > 0
            ? $margin - ((float) $sheet->revenue_total * 0.30)
            : null;
        $economicStatus = match (true) {
            $profitPercentage === null => ['label' => 'N.D.', 'class' => 'bg-white/10 text-white'],
            $profitPercentage < 30 => ['label' => 'Non sostenibile', 'class' => 'bg-red-600 text-white'],
            $profitPercentage < 40 => ['label' => 'Al limite', 'class' => 'bg-amber-500 text-black-nike'],
            default => ['label' => 'Redditizio', 'class' => 'bg-whatsapp text-white'],
        };

        return view('livewire.admin.lead-sales-sheet', [
            'salesSheet' => $sheet,
            'currentCac' => $cac,
            'isReorder' => $isReorder,
            'profitAfterAds' => $profitAfterAds,
            'profitPercentage' => $profitPercentage,
            'maximumSustainableCac' => $maximumSustainableCac,
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
        abort_unless($item->lead_sales_sheet_id === $this->sheet()?->id, 404);

        return $item;
    }

    private function syncItemFields(LeadSalesItem $item): void
    {
        $this->itemFinalPrices[$item->id] = number_format((float) $item->final_unit_price, 2, '.', '');
        $this->itemColors[$item->id] = collect($item->colors)->join("\n");
        $this->itemNotes[$item->id] = $item->notes ?? '';
    }

    private function syncOrderFields(?\App\Models\LeadSalesSheet $sheet): void
    {
        $this->shippingFee = number_format((float) ($sheet?->shipping_fee ?? 9.90), 2, '.', '');
        $this->freeShippingThreshold = number_format((float) ($sheet?->free_shipping_threshold ?? 250), 2, '.', '');
        $this->discountType = $sheet?->discount_type ?? '';
        $this->discountValue = number_format((float) ($sheet?->discount_value ?? 0), 2, '.', '');
        $this->roundingAdjustment = number_format((float) ($sheet?->rounding_adjustment ?? 0), 2, '.', '');
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
        $groupQuantity = 0;
        if ($this->pricingGroupItemId && ($sheet = $this->sheet())) {
            $groupItem = $sheet->items()->find($this->pricingGroupItemId);
            if ($groupItem && $groupItem->crm_product_id === $product?->id) {
                $groupQuantity = (float) $sheet->items()->where('pricing_group_uuid', $groupItem->pricing_group_uuid)->sum('quantity');
            }
        }
        $tier = $product ? app(LeadSalesSheetService::class)->tier($product->priceTiers, $groupQuantity + (float) $this->quantity) : null;
        $this->finalUnitPrice = $tier ? number_format((float) $tier->unit_price, 2, '.', '') : '';
    }

    private function sheet(): ?\App\Models\LeadSalesSheet
    {
        $query = $this->lead()->salesSheets();

        return $this->sheetId ? $query->whereKey($this->sheetId)->firstOrFail() : $query->first();
    }

    private function createSheet(): \App\Models\LeadSalesSheet
    {
        $sheet = $this->lead()->salesSheets()->create([
            'name' => 'Nuovo ordine',
            'status' => 'draft',
            'shipping_fee' => 9.90,
            'free_shipping_threshold' => 250,
            'revenue_total' => 0,
            'cost_total' => 0,
            'margin_total' => 0,
            'margin_percentage' => 0,
        ]);
        $sheet->update(['order_number' => 'ORD-'.str_pad((string) $sheet->id, 6, '0', STR_PAD_LEFT)]);
        $this->sheetId = $sheet->id;

        return $sheet;
    }
}
