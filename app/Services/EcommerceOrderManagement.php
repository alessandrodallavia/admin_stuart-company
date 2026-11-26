<?php
namespace App\Services;
use App\Models\{EcommerceOrder, EcommerceOrderEvent};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class EcommerceOrderManagement {
 public function update(EcommerceOrder $order, array $data, int $actor, bool $notifyCustomer = true): void {
  DB::transaction(function () use ($order, $data, $actor, $notifyCustomer) {
   $order = EcommerceOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
   if ($order->status === 'cancelled') throw ValidationException::withMessages(['order' => 'Un ordine annullato non può essere modificato.']);
   $before = $order->only(array_keys($data));
   if ($before == $data) return;
   $notification = null;
   if (isset($data['order_status']) && $order->order_status !== $data['order_status']) {
    $notification = match($data['order_status']) { 'in_production' => 'in_production', 'shipped' => 'shipped', default => null };
    if ($data['order_status'] === 'ready') $data['fulfillment_status'] = 'ready';
    if ($data['order_status'] === 'in_production') $data['fulfillment_status'] = 'pending';
    if ($data['order_status'] === 'new') $data['fulfillment_status'] = 'pending';
    if ($data['order_status'] === 'shipped') {
     if ($order->fulfillment_method === 'pickup') { $data['fulfillment_status'] = 'collected'; $data['fulfilled_at'] = now(); $notification = 'collected'; }
     elseif ($order->fulfillment_method === 'direct') { $data['fulfillment_status'] = 'delivered'; $data['fulfilled_at'] = now(); $notification = 'delivered'; }
     else $data['fulfillment_status'] = 'shipped';
    }
   }
   if (isset($data['fulfillment_status'])) {
    if ($data['fulfillment_status'] === 'pending' && !isset($data['order_status']) && in_array($order->order_status,['ready','shipped'],true)) $data['order_status'] = 'new';
    if (in_array($data['fulfillment_status'], ['delivered','collected'], true)) $data['fulfilled_at'] = $order->fulfilled_at ?: now();
    elseif (!isset($data['fulfilled_at'])) $data['fulfilled_at'] = null;
    if ($data['fulfillment_status'] === 'ready') $data['order_status'] = 'ready';
    if (in_array($data['fulfillment_status'], ['shipped','delivered','collected'], true)) $data['order_status'] = 'shipped';
    if ($order->fulfillment_status !== $data['fulfillment_status'] && in_array($data['fulfillment_status'], ['shipped','delivered','collected'], true)) $notification = $data['fulfillment_status'];
    elseif ($order->fulfillment_status === 'shipped' && $data['fulfillment_status'] === 'shipped' && (($data['fulfillment_tracking'] ?? null) !== $order->fulfillment_tracking || ($data['fulfillment_carrier'] ?? null) !== $order->fulfillment_carrier)) $notification = 'tracking_updated';
   }
   $order->fill($data)->save();
   EcommerceOrderEvent::create(['ecommerce_order_id' => $order->id, 'admin_user_id' => $actor, 'kind' => $notification ?: 'updated',
    'details' => ['before' => $before, 'after' => $order->only(array_keys($data)), 'carrier' => $order->fulfillment_carrier, 'tracking' => $order->fulfillment_tracking, 'notify_customer' => $notifyCustomer],
    'notification_status' => $notification ? ($notifyCustomer ? 'pending' : 'suppressed') : null]);
  }, 3);
 }
}
