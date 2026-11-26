<?php
namespace App\Services;
use App\Models\{EcommerceOrder, EcommerceOrderEvent, CrmProduct};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class EcommerceOrderVariants {
 public function save(EcommerceOrder $order, string $key, array $input, int $actor): void {
  DB::transaction(function () use ($order,$key,$input,$actor) {
   $order = EcommerceOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
   $fail = fn ($text) => throw ValidationException::withMessages(['variants' => $text]);
   if ($order->status === 'cancelled') $fail('Ordine annullato.');
   if ($order->proforma || $order->invoice || $order->deliveryDocument) $fail('Rimuovi prima i documenti generati per modificare le varianti.');
   $items = $order->items;
   if (!isset($items[$key])) abort(404);
   $item = $items[$key];
   $product = CrmProduct::with(['colors.sizes','sizes'])->find($item['product_id'] ?? 0);
   if (!$product) $fail('Il prodotto non è collegato al catalogo CRM.');
   $rows = app(LeadSalesItemVariantService::class)->resolveRows($product,$input,(float) $item['quantity']);
   $newRows = array_map(fn ($row) => ['color_id' => (int) $row['crm_product_color_id'], 'color' => $row['color_name'], 'size' => $row['size_name'], 'quantity' => (int) $row['quantity']], $rows);
   if ($item['rows'] == $newRows) return;
   $items[$key]['rows'] = $newRows;
   $previews = [];
   foreach ($product->colors->whereIn('id',array_column($newRows,'color_id')) as $color) {
    foreach ($item['mode'] === 'neutral' ? ['front'] : array_keys($item['prints']) as $side) {
     $path = $color->{'mockup_'.$side.'_path'};
     if (!$path || str_starts_with($path,'/') || str_contains($path,'\\') || in_array('..',explode('/',$path),true) || !in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),['png','jpg','jpeg','webp'],true)) continue;
     $original = collect($item['previews'])->firstWhere('position',$side);
     $previews[] = ['color' => $color->name, 'position' => $side, 'mockup' => '/assets/images/landing/live-mockup/'.implode('/',array_map('rawurlencode',explode('/',$path))), 'overlay' => $original['overlay'] ?? null];
    }
   }
   $items[$key]['previews'] = $previews;
   $order->update(['items' => $items]);
   EcommerceOrderEvent::create(['ecommerce_order_id' => $order->id, 'admin_user_id' => $actor, 'kind' => 'variants', 'details' => ['item' => $item['name'], 'before' => $item['rows'], 'after' => $newRows]]);
  });
 }
}
