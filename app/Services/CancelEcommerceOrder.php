<?php
namespace App\Services;
use App\Models\EcommerceOrder;
use Illuminate\Support\Facades\{DB, Http};
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
class CancelEcommerceOrder {
 public function handle(EcommerceOrder $order, int $adminId, ?string $reason): void {
  DB::transaction(function () use ($order, $adminId, $reason) {
   $order = EcommerceOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
   if ($order->status === 'cancelled') return;
   $fail = fn ($message) => throw ValidationException::withMessages(['cancellation' => $message]);
   if ($order->paid_at || !in_array($order->status, ['received','payment_pending','proforma_pending','bank_transfer_pending'], true)) $fail('Puoi annullare solo un ordine non pagato.');
   if ($order->proforma && ($order->proforma->payment_status === 'paid' || (float) $order->proforma->paymentSchedules()->sum('paid_amount') > 0)) $fail('Risulta già registrato un pagamento sulla proforma.');
   if ($order->stripe_payment_intent_id) {
    $secret = config('services.stripe.ecommerce_secret_key') ?: config('services.stripe.secret_key');
    if (!$secret) $fail('Stripe non è configurato: impossibile verificare e chiudere il pagamento.');
    try {
     $api = fn () => Http::withBasicAuth($secret, '')->acceptJson()->timeout(15);
     $intent = $api()->get('https://api.stripe.com/v1/payment_intents/'.$order->stripe_payment_intent_id)->throw()->json();
     if (($intent['id'] ?? '') !== $order->stripe_payment_intent_id || (string) data_get($intent, 'metadata.ecommerce_order_id') !== (string) $order->id || data_get($intent, 'metadata.ecommerce_order_uuid') !== $order->uuid) $fail('Il pagamento Stripe non corrisponde all’ordine.');
     if (in_array($intent['status'] ?? '', ['succeeded','processing','requires_capture'], true)) $fail('Il pagamento è già ricevuto, in elaborazione o autorizzato. Non è possibile annullare questo ordine.');
     if (($intent['status'] ?? '') !== 'canceled') $intent = $api()->withHeader('Idempotency-Key','cancel-ecommerce-'.$order->uuid)->asForm()->post('https://api.stripe.com/v1/payment_intents/'.$order->stripe_payment_intent_id.'/cancel', ['cancellation_reason' => 'abandoned'])->throw()->json();
     if (($intent['id'] ?? '') !== $order->stripe_payment_intent_id || ($intent['status'] ?? '') !== 'canceled') $fail('Stripe non ha confermato l’annullamento. Riprova.');
    } catch (ValidationException $exception) { throw $exception; }
    catch (RequestException $exception) {
     $code = $exception->response->json('error.code');
     Log::warning('Annullamento ecommerce: richiesta Stripe rifiutata', ['order_id' => $order->id, 'stripe_code' => $code, 'http_status' => $exception->response->status(), 'stripe_request_id' => $exception->response->header('Request-Id')]);
     if ($exception->response->status() === 404 && $code === 'resource_missing') $fail('Stripe non trova il pagamento con le credenziali dell’admin. Verifica che admin e sito usino lo stesso account Stripe e la stessa modalità (test o live). L’ordine resta aperto.');
     if (in_array($exception->response->status(), [401,403], true)) $fail('Le credenziali Stripe dell’admin non sono valide o non consentono questa operazione. L’ordine resta aperto.');
     $fail('Stripe non ha consentito l’annullamento del pagamento. L’ordine resta aperto: riprova.');
    }
    catch (\Throwable $exception) { report($exception); $fail('Non è stato possibile chiudere il pagamento Stripe. L’ordine resta aperto: riprova.'); }
    $order->stripe_payment_status = 'canceled';
   }
   if ($order->proforma) $order->proforma->update(['status' => 'cancelled']);
   $order->fill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $adminId, 'cancellation_reason' => $reason ?: 'Ordine non completato / sessione cliente scaduta.'])->save();
  });
 }
}
