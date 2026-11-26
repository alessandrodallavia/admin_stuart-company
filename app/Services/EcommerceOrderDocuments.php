<?php
namespace App\Services;
use App\Models\{EcommerceOrder, EcommerceOrderEvent, AdminDocument};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class EcommerceOrderDocuments {
 public function create(EcommerceOrder $order, string $type, int $actor): AdminDocument {
  return DB::transaction(function () use ($order,$type,$actor) {
   $order = EcommerceOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
   if ($order->status === 'cancelled' || !in_array($type,['invoice','delivery_note','offline_order'],true)) throw ValidationException::withMessages(['documents' => 'Documento non disponibile per questo ordine.']);
   $relation = match ($type) { 'invoice' => 'invoice', 'offline_order' => 'offlineDocument', default => 'deliveryDocument' };
   if ($order->$relation) return $order->$relation;
   $data = app(EcommerceOrderProforma::class)->data($order);
   $data['type'] = $type;
   $data['payments'][0]['payment_method_code'] = $order->payment_method === 'bank' ? 'MP05' : 'MP08';
   if ($order->paid_at) { $data['payments'][0]['paid_amount'] = $order->gross; $data['payments'][0]['paid_at'] = $order->paid_at->toDateString(); }
   if ($type === 'delivery_note') {
    $data['payments'] = []; $data['rounding_adjustment'] = 0;
    $data['items'] = collect($order->items)->map(fn ($item) => ['item_code' => $item['code'], 'description' => $item['name']."\n".collect($item['rows'])->map(fn ($row) => $row['color'].' · '.$row['size'].' · '.$row['quantity'].' pezzi')->join("\n"), 'quantity' => $item['quantity'], 'unit_price' => 0, 'vat_rate' => 0])->values()->all();
   }
   $document = app(AdminDocumentService::class)->create($data);
   $column = match ($type) { 'invoice' => 'invoice_document_id', 'offline_order' => 'offline_document_id', default => 'delivery_document_id' };
   $order->update([$column => $document->id]);
   self::linkExisting($order, $document);
   EcommerceOrderEvent::create(['ecommerce_order_id' => $order->id, 'admin_user_id' => $actor, 'kind' => 'document', 'details' => ['type' => $type, 'code' => $document->display_code]]);
   return $document;
  });
 }
 public static function linkExisting(EcommerceOrder $order, AdminDocument $document): void {
  $ids = array_filter([$order->admin_document_id, $order->invoice_document_id, $order->delivery_document_id, $order->offline_document_id]);
  foreach (AdminDocument::whereIn('id',$ids)->where('id','!=',$document->id)->get() as $other) {
   DocumentRelationService::link($document->currentDocumentType(), $document->id, $other->currentDocumentType(), $other->id, 'ecommerce-order');
  }
 }

}
