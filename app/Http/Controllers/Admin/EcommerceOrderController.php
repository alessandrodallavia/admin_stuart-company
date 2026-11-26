<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\EcommerceOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
class EcommerceOrderController extends Controller {
    public function index(Request $request) {
        $input = $request->validate(['q' => ['nullable', 'string', 'max:200'], 'order_status' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(EcommerceOrder::ORDER_STATUSES))]]);
        $search = trim($input['q'] ?? '');
        $workStatus = $input['order_status'] ?? '';
        $orders = EcommerceOrder::query()->when($workStatus !== '', fn ($q) => $q->where('order_status', $workStatus)->where('status','!=','cancelled'))->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('customer_name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')->orWhere('id', ltrim(str_replace('EC-', '', $search), '0'))))->latest('id')->paginate(25)->withQueryString();
        return view('admin.ecommerce-orders.index', compact('orders', 'search', 'workStatus'));
    }
    public function show(EcommerceOrder $order) {
        $order->load(['proforma', 'invoice', 'deliveryDocument', 'offlineDocument', 'events' => fn ($query) => $query->with('actor')->latest('id')->limit(100)]);
        $variantProducts = \App\Models\CrmProduct::with(['colors.sizes','sizes'])->whereIn('id', array_filter(array_column($order->items, 'product_id')))->get()->keyBy('id');
        return view('admin.ecommerce-orders.show', compact('order', 'variantProducts'));
    }
    public function status(Request $request, EcommerceOrder $order, \App\Services\EcommerceOrderManagement $service) {
        $data = $request->validate(['notify_customer' => ['sometimes','boolean'], 'order_status' => ['required', \Illuminate\Validation\Rule::in(array_keys(EcommerceOrder::ORDER_STATUSES))]]);
        $notifyCustomer = $request->boolean('notify_customer', true);
        unset($data['notify_customer']);
        $event = $service->update($order, $data, (int) $request->user('admin')->id, $notifyCustomer);
        return $this->updateNotificationResponse($event, 'Stato operativo aggiornato.');
    }
    public function fulfillment(Request $request, EcommerceOrder $order, \App\Services\EcommerceOrderManagement $service) {
        $data = $request->validate([
            'notify_customer' => ['sometimes','boolean'],
            'fulfillment_method' => ['required', \Illuminate\Validation\Rule::in(array_keys(EcommerceOrder::FULFILLMENT_METHODS))],
            'fulfillment_status' => ['required', \Illuminate\Validation\Rule::in(array_keys(EcommerceOrder::FULFILLMENT_STATUSES))],
            'fulfillment_carrier' => ['nullable','string','max:255'], 'fulfillment_tracking' => ['nullable','string','max:255'], 'fulfillment_notes' => ['nullable','string','max:2000'],
        ]);
        if (($data['fulfillment_method'] === 'pickup' && in_array($data['fulfillment_status'],['shipped','delivered'],true)) || ($data['fulfillment_method'] !== 'pickup' && $data['fulfillment_status'] === 'collected')) throw \Illuminate\Validation\ValidationException::withMessages(['fulfillment_status' => 'Lo stato non è compatibile con il metodo di consegna.']);
        if ($data['fulfillment_method'] !== 'shipping') { $data['fulfillment_carrier'] = null; $data['fulfillment_tracking'] = null; }
        $notifyCustomer = $request->boolean('notify_customer', true);
        unset($data['notify_customer']);
        $event = $service->update($order, $data, (int) $request->user('admin')->id, $notifyCustomer);
        return $this->updateNotificationResponse($event, 'Consegna aggiornata.');
    }
    private function updateNotificationResponse(?\App\Models\EcommerceOrderEvent $event, string $message)
    {
        if ($event && $event->notification_status === 'pending') {
            app(\App\Services\EcommerceOrderUpdateNotification::class)->send($event);
            $event->refresh();
        }
        if ($event?->notification_status === 'sent') $message .= ' Comunicazione inviata al cliente.';
        if ($event?->notification_status === 'suppressed') $message .= ' Nessuna comunicazione inviata per scelta dell’operatore.';
        $response = back()->with('status', $message);
        if (in_array($event?->notification_status, ['pending', 'failed'], true)) {
            $response->with('notification_warning', 'Aggiornamento salvato, ma la comunicazione non è stata inviata. Il sistema ritenterà automaticamente.');
        }
        return $response;
    }

    public function notes(Request $request, EcommerceOrder $order, \App\Services\EcommerceOrderManagement $service) {
        $data = $request->validate(['internal_notes' => ['nullable','string','max:5000']]);
        $service->update($order, $data, (int) $request->user('admin')->id);
        return back()->with('status', 'Note interne aggiornate.');
    }
    public function variants(Request $request, EcommerceOrder $order, string $item, \App\Services\EcommerceOrderVariants $service) {
        $data = $request->validate(['variants' => ['required','array'], 'variants.*' => ['required','array'], 'variants.*.*' => ['nullable','integer','min:0','max:10000']]);
        $service->save($order, $item, $data['variants'], (int) $request->user('admin')->id);
        return back()->with('status', 'Colori e taglie aggiornati. Quantità totale e prezzi restano invariati.');
    }
    public function document(Request $request, EcommerceOrder $order, \App\Services\EcommerceOrderDocuments $service) {
        $data = $request->validate(['type' => ['required','in:invoice,delivery_note,offline_order']]);
        $document = $service->create($order,$data['type'],(int) $request->user('admin')->id);
        return to_route('admin.documents.show',$document)->with('status', 'Documento generato dall’ordine '.$order->reference.'.');
    }
    public function cancel(Request $request, EcommerceOrder $order, \App\Services\CancelEcommerceOrder $service) {
        $input = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $service->handle($order, (int) $request->user('admin')->id, $input['reason'] ?? null);
        return back()->with('status', 'Ordine annullato.');
    }
    public function proforma(EcommerceOrder $order, \App\Services\EcommerceOrderProforma $service) {
        $document = $service->create($order);
        return to_route('admin.documents.show', $document)->with('status', 'Proforma generata per l’ordine '.$order->reference.'.');
    }
    public function sendProforma(EcommerceOrder $order, \App\Services\EcommerceOrderProforma $service, \App\Services\AdminDocumentPdfService $pdf) {
        $document = $order->proforma;
        abort_unless($document && $order->payment_method === 'bank', 422);
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($order, $document, $service, $pdf) {
                $document = app(\App\Services\AdminDocumentService::class)->markAsSent($document);
                $service->send($order, $document, $pdf->output($document), $pdf->filename($document));
            });
        } catch (\Throwable $exception) { report($exception); return back()->withErrors(['proforma' => 'Invio della proforma non riuscito. Riprova.']); }
        return back()->with('status', 'Proforma inviata al cliente.');
    }
    public function markPaid(Request $request, EcommerceOrder $order, \App\Services\AdminDocumentService $service) {
        $request->validate(['notify_customer' => ['sometimes', 'boolean']]);
        $notifyCustomer = $request->boolean('notify_customer', true);
        abort_unless($order->payment_method === 'bank' && $order->status !== 'cancelled', 422);
        if ($order->status === 'paid') return back()->with('status', 'Il pagamento risulta già registrato.');
        $document = $order->proforma;
        if ($document && ($document->currency !== 'EUR' || round((float) $document->total, 2) !== round((float) $order->gross, 2))) throw \Illuminate\Validation\ValidationException::withMessages(['payment' => 'Il totale della proforma non corrisponde all’ordine.']);
        if (! $document || $document->paymentSchedules->isEmpty()) throw \Illuminate\Validation\ValidationException::withMessages(['payment' => 'Genera prima la proforma con la scadenza di pagamento.']);
        $event = \Illuminate\Support\Facades\DB::transaction(function () use ($order, $document, $service, $notifyCustomer) {
            $order = EcommerceOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($order->status !== 'cancelled', 422, 'Ordine annullato.');
            if ($order->status === 'paid') return;
            foreach ($document->paymentSchedules as $schedule) $service->markPayment($document, $schedule->id, (float) $schedule->amount, now()->toDateString());
            $order->update(['status' => 'paid', 'paid_at' => now()]);
            return \App\Models\EcommerceOrderEvent::create(['ecommerce_order_id' => $order->id, 'admin_user_id' => auth('admin')->id(), 'kind' => 'payment_received', 'details' => ['notify_customer' => $notifyCustomer], 'notification_status' => $notifyCustomer ? 'pending' : 'suppressed']);
        });
        if ($event && $notifyCustomer) app(\App\Services\EcommerceOrderUpdateNotification::class)->send($event);
        $response = back()->with('status', 'Accredito del bonifico registrato.'.($event && $event->fresh()->notification_status === 'sent' ? ' Comunicazione inviata al cliente.' : ''));
        if ($event && $notifyCustomer && $event->fresh()->notification_status !== 'sent') {
            $response->with('notification_warning', 'Pagamento registrato, ma la comunicazione non è stata inviata. Il sistema ritenterà automaticamente.');
        }
        return $response;
    }
    public function file(Request $request, EcommerceOrder $order, string $item, string $position) {
        $file = $order->items[$item]['artworks'][$position] ?? null;
        $disk = Storage::disk('ecommerce_orders');
        abort_unless($file && $disk->exists($file['path']), 404);
        if ($request->boolean('preview')) {
            $mime = $disk->mimeType($file['path']);
            abort_unless(in_array($mime, ['image/png', 'image/jpeg'], true), 404);
            return $disk->response($file['path'], null, ['Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
        }
        return $disk->download($file['path'], $file['name'], ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
