<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDocumentService;
use App\Services\CheckoutOrderCustomerNotificationService;
use App\Services\CheckoutOrderLeadStatusService;
use App\Services\LeadOrderCheckoutService;
use App\Models\LeadOrderCheckout;
use App\Models\LeadSalesItem;
use App\Services\LeadSalesItemVariantService;
use App\Support\OrderItemVariantFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutOrderController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();
        $statuses = $this->statuses();

        $orders = LeadOrderCheckout::query()
            ->with(['lead', 'salesSheet'])
            ->whereNotNull('details_completed_at')
            ->whereIn('id', LeadOrderCheckout::query()
                ->selectRaw('MIN(id)')
                ->whereNotNull('details_completed_at')
                ->whereNotIn('status', ['invalidated', 'expired', 'revoked'])
                ->groupBy('lead_sales_sheet_id'))
            ->when(isset($statuses[$status]), fn (Builder $query) => $query->where('order_status', $status))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('order_number', 'like', "%{$search}%")
                        ->orWhere('contact_email', 'like', "%{$search}%")
                        ->orWhere('contact_phone', 'like', "%{$search}%")
                        ->orWhere('billing_company_name', 'like', "%{$search}%")
                        ->orWhereHas('lead', fn (Builder $lead) => $lead
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->latest('details_completed_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.checkout-orders.index', compact('orders', 'statuses', 'status', 'search'));
    }

    public function show(LeadOrderCheckout $checkout, LeadOrderCheckoutService $checkoutService): View|RedirectResponse
    {
        $primaryCheckout = LeadOrderCheckout::query()
            ->where('lead_sales_sheet_id', $checkout->lead_sales_sheet_id)
            ->whereNotIn('status', ['invalidated', 'expired', 'revoked'])
            ->orderByRaw("CASE payment_type WHEN 'deposit' THEN 0 WHEN 'full' THEN 1 ELSE 2 END")
            ->orderBy('id')
            ->firstOrFail();

        if (! $checkout->is($primaryCheckout)) {
            return redirect()->route('admin.checkout-orders.show', $primaryCheckout);
        }

        $checkout->load(['lead', 'salesSheet.items.variants', 'salesSheet.items.crmProduct.sizes', 'salesSheet.items.crmProduct.colors.sizes', 'proforma']);
        $payments = LeadOrderCheckout::query()
            ->with('proforma')
            ->where('lead_sales_sheet_id', $checkout->lead_sales_sheet_id)
            ->orderBy('created_at')
            ->get();
        $statuses = $this->statuses();
        $fulfillmentMethods = $this->fulfillmentMethods();
        $fulfillmentStatuses = $this->fulfillmentStatuses();
        $fulfillmentCarriers = $this->fulfillmentCarriers();
        $checkoutUrls = $payments->mapWithKeys(fn (LeadOrderCheckout $payment) => [
            $payment->id => $checkoutService->publicUrl($payment),
        ]);

        return view('admin.checkout-orders.show', compact('checkout', 'payments', 'statuses', 'checkoutUrls', 'fulfillmentMethods', 'fulfillmentStatuses', 'fulfillmentCarriers'));
    }

    public function storeBalance(LeadOrderCheckout $checkout, LeadOrderCheckoutService $service): RedirectResponse
    {
        abort_unless($checkout->payment_type === 'deposit', 422);
        $checkout->loadMissing('salesSheet');
        abort_unless($checkout->salesSheet, 404);

        $balance = $service->createForOrder($checkout->salesSheet, 'balance');

        return redirect()
            ->route('admin.checkout-orders.show', $checkout)
            ->with('status', 'Checkout del saldo generato. Il link non è stato inviato automaticamente.')
            ->with('checkout_url', $service->publicUrl($balance));
    }

    public function storeBalanceProforma(
        LeadOrderCheckout $checkout,
        LeadOrderCheckoutService $checkoutService,
        AdminDocumentService $documentService,
    ): RedirectResponse {
        abort_unless($checkout->payment_type === 'deposit', 422);
        $checkout->loadMissing('salesSheet');
        abort_unless($checkout->salesSheet, 404);

        $balance = LeadOrderCheckout::query()
            ->where('lead_sales_sheet_id', $checkout->lead_sales_sheet_id)
            ->where('payment_type', 'balance')
            ->whereNotIn('status', ['invalidated', 'expired', 'revoked'])
            ->latest('id')
            ->first() ?: $checkoutService->createForOrder($checkout->salesSheet, 'balance');

        $balance->forceFill([
            ...$checkout->only($this->customerDataFields()),
            'status' => 'bank_transfer_pending',
            'payment_method' => 'bank',
            'details_completed_at' => $balance->details_completed_at ?: now(),
            'order_status' => 'balance_pending',
        ])->save();

        return $this->storeProforma($balance, $documentService);
    }

    public function storeProforma(LeadOrderCheckout $checkout, AdminDocumentService $service): RedirectResponse
    {
        if ($checkout->proforma) {
            return redirect()->route('admin.documents.show', $checkout->proforma);
        }

        if ($checkout->admin_document_id) {
            $checkout->forceFill(['admin_document_id' => null])->save();
        }

        $checkout->loadMissing('lead');
        $document = $service->create($this->proformaData($checkout));
        $checkout->forceFill(['admin_document_id' => $document->id])->save();
        LeadOrderCheckout::synchronizeOrderStatusForSheet($checkout->lead_sales_sheet_id);

        return redirect()
            ->route('admin.documents.show', $document)
            ->with('status', 'Proforma generata dall’ordine '.$checkout->order_number.'.');
    }

    public function updateStatus(
        Request $request,
        LeadOrderCheckout $checkout,
        CheckoutOrderCustomerNotificationService $notifications,
    ): RedirectResponse
    {
        $data = $request->validate([
            'order_status' => ['required', Rule::in(array_keys($this->statuses()))],
        ], [], ['order_status' => 'stato ordine']);

        $previousStatus = $checkout->order_status;
        if ($previousStatus === $data['order_status']) {
            return back()->with('status', 'Lo stato dell’ordine è già aggiornato.');
        }

        if ($data['order_status'] === 'balance_pending') {
            $deposit = LeadOrderCheckout::query()
                ->where('lead_sales_sheet_id', $checkout->lead_sales_sheet_id)
                ->where('payment_type', 'deposit')
                ->first();

            if (! $deposit) {
                throw ValidationException::withMessages([
                    'order_status' => 'Lo stato “In attesa saldo” è disponibile solo per gli ordini con acconto e saldo.',
                ]);
            }
        }

        LeadOrderCheckout::query()
            ->where('lead_sales_sheet_id', $checkout->lead_sales_sheet_id)
            ->update(['order_status' => $data['order_status']]);

        $checkout->forceFill(['order_status' => $data['order_status']]);
        $notification = match ($data['order_status']) {
            'in_production' => $notifications->orderProcessing($checkout),
            'shipped' => $notifications->shipped($checkout),
            default => null,
        };

        $response = back()->with('status', 'Stato dell’ordine aggiornato.');
        if ($notification && ! $notification['sent']) {
            $response->with('notification_warning', 'Stato aggiornato, ma la notifica al cliente non è stata inviata: '.$notification['error']);
        }

        return $response;
    }

    public function updateFulfillment(
        Request $request,
        LeadOrderCheckout $checkout,
        CheckoutOrderCustomerNotificationService $notifications,
    ): RedirectResponse {
        $data = $request->validate([
            'fulfillment_method' => ['required', Rule::in(array_keys($this->fulfillmentMethods()))],
            'fulfillment_status' => ['required', Rule::in(array_keys($this->fulfillmentStatuses()))],
            'fulfillment_carrier' => ['nullable', 'string', 'max:255'],
            'fulfillment_tracking' => ['nullable', 'string', 'max:255'],
            'fulfillment_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $previousStatus = $checkout->fulfillment_status;
        $completed = in_array($data['fulfillment_status'], ['delivered', 'collected'], true);
        $orderStatus = in_array($data['fulfillment_status'], ['shipped', 'delivered', 'collected'], true)
            ? 'shipped'
            : $checkout->order_status;

        LeadOrderCheckout::query()
            ->where('lead_sales_sheet_id', $checkout->lead_sales_sheet_id)
            ->update([
                ...$data,
                'order_status' => $orderStatus,
                'fulfilled_at' => $completed ? now() : null,
            ]);

        $notification = null;
        if ($data['fulfillment_status'] === 'shipped' && $previousStatus !== 'shipped') {
            $checkout->forceFill([...$data, 'order_status' => 'shipped']);
            $notification = $notifications->shipped($checkout);
        }

        $response = back()->with('status', 'Consegna dell’ordine aggiornata.');
        if ($notification && ! $notification['sent']) {
            $response->with('notification_warning', 'Consegna aggiornata, ma la notifica al cliente non è stata inviata: '.$notification['error']);
        }

        return $response;
    }

    public function updateItemVariants(
        Request $request,
        LeadOrderCheckout $checkout,
        LeadSalesItem $item,
        LeadSalesItemVariantService $service,
    ): RedirectResponse {
        abort_unless($item->lead_sales_sheet_id === $checkout->lead_sales_sheet_id, 404);
        $hasProforma = LeadOrderCheckout::query()
            ->where('lead_sales_sheet_id', $checkout->lead_sales_sheet_id)
            ->whereNotNull('admin_document_id')
            ->exists();
        if ($hasProforma) {
            throw ValidationException::withMessages([
                'variants.'.$item->id => 'Elimina prima le proforme generate per modificare taglie e colori dell’ordine.',
            ]);
        }

        $data = $request->validate([
            'variants' => ['nullable', 'array'],
            'variants.*.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $service->save($item, $data['variants'] ?? [], 'variants.'.$item->id);

        return back()->with('status', 'Taglie e colori aggiornati senza richiedere nuovamente i dati al cliente.');
    }

    private function customerDataFields(): array
    {
        return [
            'contact_email', 'contact_first_name', 'contact_last_name', 'contact_phone',
            'shipping_company', 'shipping_first_name', 'shipping_last_name', 'shipping_address',
            'shipping_city', 'shipping_postal_code', 'shipping_province', 'billing_customer_type',
            'billing_first_name', 'billing_last_name', 'billing_company_name', 'billing_tax_code',
            'billing_vat_number', 'billing_recipient_code', 'billing_pec', 'billing_address',
            'billing_city', 'billing_postal_code', 'billing_province', 'notes',
        ];
    }

    public function markPaid(
        LeadOrderCheckout $checkout,
        AdminDocumentService $service,
        CheckoutOrderCustomerNotificationService $notifications,
        CheckoutOrderLeadStatusService $leadStatuses,
    ): RedirectResponse
    {
        if ($checkout->status === 'paid') {
            return back()->with('status', $checkout->payment_label.' risulta già pagato. Nessuna nuova notifica inviata.');
        }

        $checkout->load('proforma.paymentSchedules');

        if (! $checkout->proforma) {
            throw ValidationException::withMessages([
                'payment' => 'Genera prima la proforma relativa a questo pagamento.',
            ]);
        }

        if ($checkout->proforma->paymentSchedules->isEmpty()) {
            throw ValidationException::withMessages([
                'payment' => 'La proforma non contiene una scadenza di pagamento da saldare.',
            ]);
        }

        DB::transaction(function () use ($checkout, $service) {
            foreach ($checkout->proforma->paymentSchedules as $schedule) {
                $service->markPayment(
                    $checkout->proforma,
                    $schedule->id,
                    (float) $schedule->amount,
                    now()->toDateString(),
                );
            }

            $checkout->forceFill(['status' => 'paid'])->save();

            LeadOrderCheckout::query()
                ->where('lead_sales_sheet_id', $checkout->lead_sales_sheet_id)
                ->update(['order_status' => $checkout->payment_type === 'balance' ? 'ready' : 'in_production']);
        });

        $notification = $checkout->payment_type === 'balance'
            ? null
            : $notifications->orderProcessing($checkout->forceFill(['order_status' => 'in_production']));
        $leadStatuses->paymentReceived($checkout);

        $response = back()->with('status', $checkout->payment_label.' segnato come pagato. Stato dell’ordine aggiornato.');
        if ($notification && ! $notification['sent']) {
            $response->with('notification_warning', 'Pagamento registrato, ma la notifica al cliente non è stata inviata: '.$notification['error']);
        }

        return $response;
    }

    private function statuses(): array
    {
        return [
            'new' => 'Nuovo',
            'proforma_pending' => 'Proforma da generare',
            'payment_pending' => 'Pagamento in attesa',
            'paid' => 'Pagato',
            'in_production' => 'In produzione',
            'balance_pending' => 'In attesa saldo',
            'ready' => 'Pronto',
            'shipped' => 'Spedito',
            'cancelled' => 'Annullato',
        ];
    }

    private function fulfillmentMethods(): array
    {
        return ['shipping' => 'Spedizione', 'pickup' => 'Ritiro', 'direct' => 'Consegna diretta'];
    }

    private function fulfillmentCarriers(): array
    {
        return [
            'BRT' => 'BRT',
            'GLS' => 'GLS',
            'DHL' => 'DHL',
            'UPS' => 'UPS',
            'FedEx' => 'FedEx',
            'Poste Italiane / SDA' => 'Poste Italiane / SDA',
        ];
    }

    private function fulfillmentStatuses(): array
    {
        return ['pending' => 'Da gestire', 'ready' => 'Pronto', 'shipped' => 'Spedito', 'delivered' => 'Consegnato', 'collected' => 'Ritirato'];
    }

    private function itemDescription(array $item): string
    {
        $details = OrderItemVariantFormatter::proformaDetails($item);
        $name = mb_strtoupper($item['name']);
        if (! empty($item['variants'])) {
            return $name.($details !== '' ? "\n".$details : '');
        }

        return mb_strtoupper($item['name'].($details !== '' ? ' - '.$details : ''));
    }

    private function proformaData(LeadOrderCheckout $checkout): array
    {
        $minimumOrderApplied = (float) $checkout->minimum_order_adjustment > 0;

        if ($checkout->payment_type === 'deposit') {
            $items = [[
                'item_code' => null,
                'description' => mb_strtoupper($checkout->payment_label.' ordine '.$checkout->order_number),
                'quantity' => 1,
                'unit_price' => $checkout->taxable_amount,
                'vat_rate' => $checkout->vat_rate,
                'informational' => false,
            ]];

            $items[] = [
                'item_code' => null,
                'description' => "\u{00A0}",
                'quantity' => 0,
                'unit_price' => 0,
                'vat_rate' => 0,
                'informational' => true,
            ];

            $items[] = [
                'item_code' => null,
                'description' => 'RIEPILOGO ORDINE',
                'quantity' => 0,
                'unit_price' => 0,
                'vat_rate' => 0,
                'informational' => true,
            ];

            foreach ($checkout->items as $item) {
                $items[] = [
                    'item_code' => $item['code'] ?? null,
                    'description' => $this->itemDescription($item),
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'vat_rate' => $checkout->vat_rate,
                    'informational' => true,
                ];
            }

            if ($minimumOrderApplied) {
                $items[] = [
                    'item_code' => null,
                    'description' => 'MINIMO D’ORDINE APPLICATO',
                    'quantity' => 1,
                    'unit_price' => $checkout->minimum_order_amount,
                    'vat_rate' => $checkout->vat_rate,
                    'informational' => true,
                ];
            }

            if ((float) $checkout->shipping_amount > 0) {
                $items[] = [
                    'item_code' => null,
                    'description' => 'SPESE DI TRASPORTO',
                    'quantity' => 1,
                    'unit_price' => $checkout->shipping_amount,
                    'vat_rate' => $checkout->vat_rate,
                    'informational' => true,
                ];
            }
        } else {
            $orderItems = collect($checkout->items)->map(fn (array $item) => [
                'item_code' => $item['code'] ?? null,
                'description' => $this->itemDescription($item),
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'vat_rate' => $checkout->vat_rate,
            ])->all();

            if ($minimumOrderApplied) {
                $items = [[
                    'item_code' => null,
                    'description' => 'IMPORTO MINIMO ORDINE',
                    'quantity' => 1,
                    'unit_price' => $checkout->minimum_order_amount,
                    'vat_rate' => $checkout->vat_rate,
                ], [
                    'item_code' => null,
                    'description' => "\u{00A0}",
                    'quantity' => 0,
                    'unit_price' => 0,
                    'vat_rate' => 0,
                    'informational' => true,
                ], [
                    'item_code' => null,
                    'description' => 'RIEPILOGO ORDINE',
                    'quantity' => 0,
                    'unit_price' => 0,
                    'vat_rate' => 0,
                    'informational' => true,
                ]];
                foreach ($orderItems as $item) {
                    $items[] = $item + ['informational' => true];
                }
            } else {
                $items = $orderItems;
            }

            if (! $minimumOrderApplied && (float) $checkout->discount_amount > 0) {
                $items[] = ['item_code' => 'SCONTO', 'description' => 'SCONTO ORDINE', 'quantity' => 1, 'unit_price' => -(float) $checkout->discount_amount, 'vat_rate' => $checkout->vat_rate];
            }
            if (! $minimumOrderApplied && (float) $checkout->rounding_adjustment !== 0.0) {
                $items[] = ['item_code' => 'ARROT', 'description' => 'ARROTONDAMENTO', 'quantity' => 1, 'unit_price' => $checkout->rounding_adjustment, 'vat_rate' => $checkout->vat_rate];
            }
            if ((float) $checkout->shipping_amount > 0) {
                $items[] = ['item_code' => null, 'description' => 'SPESE DI TRASPORTO', 'quantity' => 1, 'unit_price' => $checkout->shipping_amount, 'vat_rate' => $checkout->vat_rate];
            }

            if ($checkout->payment_type === 'balance') {
                $deposits = LeadOrderCheckout::query()
                    ->with('proforma')
                    ->where('lead_sales_sheet_id', $checkout->lead_sales_sheet_id)
                    ->where('payment_type', 'deposit')
                    ->whereNotIn('status', ['invalidated', 'expired', 'revoked'])
                    ->get();
                $references = $deposits
                    ->map(fn (LeadOrderCheckout $deposit) => $deposit->proforma?->code ?: '---')
                    ->unique()
                    ->join(', ');
                $items[] = [
                    'item_code' => null,
                    'description' => 'ACCONTO RIF. NS. PROFORMA NR. '.($references ?: '---'),
                    'quantity' => 1,
                    'unit_price' => -(float) $deposits->sum('taxable_amount'),
                    'vat_rate' => $checkout->vat_rate,
                ];
            }
        }

        $documentSubtotal = round((float) collect($items)->reject(
            fn (array $item) => (bool) ($item['informational'] ?? false)
        )->sum(
            fn (array $item) => round((float) $item['quantity'] * (float) $item['unit_price'], 2, PHP_ROUND_HALF_UP)
        ), 2, PHP_ROUND_HALF_UP);
        $documentVat = round($documentSubtotal * (float) $checkout->vat_rate / 100, 2, PHP_ROUND_HALF_UP);
        $roundingAdjustment = round((float) $checkout->total_amount - $documentSubtotal - $documentVat, 2, PHP_ROUND_HALF_UP);
        $shippingContact = trim($checkout->shipping_first_name.' '.$checkout->shipping_last_name);
        $shippingName = $shippingContact
            ? trim($shippingContact.($checkout->shipping_company ? ' c/o '.$checkout->shipping_company : ''))
            : (string) $checkout->shipping_company;

        return [
            'type' => 'proforma',
            'document_date' => now()->toDateString(),
            'status' => 'draft',
            'currency' => $checkout->currency,
            'customer_name' => $checkout->billing_company_name ?: trim($checkout->billing_first_name.' '.$checkout->billing_last_name),
            'customer_email' => $checkout->contact_email,
            'customer_phone' => $checkout->contact_phone,
            'customer_tax_code' => $checkout->billing_tax_code,
            'customer_vat_number' => $checkout->billing_vat_number,
            'customer_recipient_code' => $checkout->billing_recipient_code,
            'customer_pec' => $checkout->billing_pec,
            'customer_address' => $checkout->billing_address,
            'customer_city' => $checkout->billing_city,
            'customer_province' => $checkout->billing_province,
            'customer_postal_code' => $checkout->billing_postal_code,
            'customer_country' => 'IT',
            'shipping_name' => $shippingName,
            'shipping_phone' => $checkout->contact_phone,
            'shipping_address' => $checkout->shipping_address,
            'shipping_city' => $checkout->shipping_city,
            'shipping_province' => $checkout->shipping_province,
            'shipping_postal_code' => $checkout->shipping_postal_code,
            'shipping_country' => 'IT',
            'notes' => collect(['Ordine '.$checkout->order_number, $checkout->notes])->filter()->join("\n"),
            'rounding_adjustment' => $roundingAdjustment,
            'payment_conditions' => 'TP02',
            'prices_include_vat' => false,
            'items' => $items,
            'payments' => [[
                'due_date' => now()->toDateString(),
                'payment_method_code' => 'MP05',
                'amount' => $checkout->total_amount,
            ]],
        ];
    }
}
