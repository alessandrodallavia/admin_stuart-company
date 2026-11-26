<?php
namespace App\Console\Commands;
use App\Models\EcommerceOrder;
use App\Services\AdminDocumentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class SyncEcommerceOrderPayments extends Command {
 protected $signature = 'orders:sync-ecommerce-payments';
 protected $description = 'Allinea gli incassi confermati ecommerce ai documenti già generati';
 public function handle(AdminDocumentService $service): int {
  EcommerceOrder::whereNotNull('paid_at')->where('status','paid')->where(function ($q) {
   $q->whereHas('invoice',fn ($d) => $d->where('payment_status','!=','paid'))->orWhereHas('proforma',fn ($d) => $d->where('payment_status','!=','paid'));
  })->chunkById(100,function ($orders) use ($service) {
   foreach ($orders as $order) DB::transaction(function () use ($order,$service) {
    $order = EcommerceOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
    if ($order->status !== 'paid' || !$order->paid_at) return;
    foreach (array_filter([$order->invoice,$order->proforma]) as $document) {
     if ($document->payment_status === 'paid' || $document->currency !== 'EUR' || round((float) $document->total,2) !== round((float) $order->gross,2) || round((float) $document->paymentSchedules()->sum('amount'),2) !== round((float) $order->gross,2)) continue;
     foreach ($document->paymentSchedules as $schedule) $service->markPayment($document,$schedule->id,(float) $schedule->amount,$order->paid_at->toDateString());
    }
   },3);
  });
  return self::SUCCESS;
 }
}
