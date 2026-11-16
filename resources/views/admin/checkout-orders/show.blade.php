@extends('admin.layouts.app')

@section('title', 'Ordine '.$checkout->order_number.' - Stuart Admin')
@section('page_title', 'Ordine '.$checkout->order_number)
@section('active_nav', 'checkout-orders')

@section('content')
@php
    $money = fn ($value) => '€ '.number_format((float) $value, 2, ',', '.');
    $customerName = $checkout->billing_company_name ?: trim($checkout->contact_first_name.' '.$checkout->contact_last_name);
    $billingName = $checkout->billing_company_name ?: trim($checkout->billing_first_name.' '.$checkout->billing_last_name);
    $depositCheckout = $payments->firstWhere('payment_type', 'deposit');
    $balanceCheckout = $payments->firstWhere('payment_type', 'balance');
    $proformaPaymentLabel = match($checkout->payment_type) {
        'deposit' => 'acconto',
        'balance' => 'saldo',
        default => 'pagamento completo',
    };
@endphp

<div class="catalog-workspace w-full">
    @if(session('checkout_url'))
        <div class="mb-16 rounded-10 border border-bullstar/20 bg-bullstar/5 p-14">
            <p class="text-12 font-extrabold uppercase text-bullstar">Link checkout saldo</p>
            <div class="mt-8 flex flex-col gap-8 md:flex-row">
                <input readonly value="{{ session('checkout_url') }}" class="min-w-0 flex-1 rounded-10 border-gray-mid bg-white px-12 py-10 text-12 font-semibold">
                <a href="{{ session('checkout_url') }}" target="_blank" class="catalog-button catalog-button-primary flex-none">Apri link</a>
            </div>
        </div>
    @endif
    <header class="catalog-page-header">
        <div>
            <a href="{{ route('admin.checkout-orders.index') }}" class="catalog-eyebrow inline-block transition hover:text-bullstar">← Ordini checkout</a>
            <div class="flex flex-wrap items-center gap-10">
                <h1>{{ $checkout->order_number }}</h1>
                <span class="catalog-badge {{ $checkout->order_status === 'paid' ? 'catalog-badge-green' : '' }}">{{ $statuses[$checkout->order_status] ?? $checkout->order_status }}</span>
            </div>
            <p>Ricevuto il {{ $checkout->details_completed_at?->format('d/m/Y \\a\\l\\l\\e H:i') }}</p>
        </div>
        <div class="catalog-header-actions">
            <span class="catalog-tax-note">{{ $checkout->payment_label }} · {{ $money($checkout->total_amount) }}</span>
            <a href="{{ route('admin.leads.index', ['lead' => $checkout->lead_id]) }}" class="catalog-button catalog-button-secondary">Apri lead ↗</a>
        </div>
    </header>

    <div class="grid gap-16 xl:grid-cols-[minmax(0,1.55fr)_minmax(340px,.75fr)]">
        <div class="space-y-16">
            <section class="catalog-surface">
                <div class="border-b border-gray-mid bg-gray-light px-16 py-12">
                    <h2 class="text-14 font-black uppercase tracking-normal">Prodotti ordinati</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left">
                        <thead class="border-b border-gray-mid text-11 font-extrabold uppercase text-gray">
                            <tr><th class="px-16 py-8">Prodotto</th><th class="px-12 py-8 text-right">Quantità</th><th class="px-12 py-8 text-right">Prezzo unitario</th><th class="px-16 py-8 text-right">Totale</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-mid">
                            @foreach($checkout->items as $item)
                                @php
                                    $displayDetails = \App\Support\OrderItemVariantFormatter::details($item);
                                    $salesItem = $checkout->salesSheet?->items?->firstWhere('id', (int) ($item['sales_item_id'] ?? 0));
                                    if (! $salesItem && $checkout->salesSheet) {
                                        $salesItem = $checkout->salesSheet->items->first(function ($candidate) use ($item) {
                                            return $candidate->product_code === ($item['code'] ?? null)
                                                && ($candidate->configuration_name ?: $candidate->product_name) === ($item['name'] ?? null);
                                        });
                                    }
                                    $hasCatalogVariants = $salesItem && $salesItem->crmProduct
                                        && $salesItem->crmProduct->colors->isNotEmpty()
                                        && $salesItem->crmProduct->sizes->isNotEmpty();
                                @endphp
                                <tr>
                                    <td class="px-16 py-12"><p class="text-14 font-black">{{ $item['name'] }}</p><p class="mt-3 text-11 font-semibold text-gray">{{ $item['code'] ?? '' }}@if($displayDetails) · {{ $displayDetails }}@endif</p></td>
                                    <td class="px-12 py-12 text-right text-13 font-semibold">{{ rtrim(rtrim(number_format((float) $item['quantity'], 2, ',', '.'), '0'), ',') }}</td>
                                    <td class="px-12 py-12 text-right text-13 font-semibold">{{ $money($item['unit_price']) }}</td>
                                    <td class="px-16 py-12 text-right text-14 font-black">{{ $money($item['line_total']) }}</td>
                                </tr>
                                @if($hasCatalogVariants)
                                    <tr>
                                        <td colspan="4" class="bg-gray-light/50 px-16 py-10">
                                            <details>
                                                <summary class="cursor-pointer text-11 font-extrabold uppercase text-bullstar">Modifica taglie e colori</summary>
                                                <form method="POST" action="{{ route('admin.checkout-orders.items.variants.update', [$checkout, $salesItem]) }}" class="mt-10" data-variants-form>
                                                    @csrf
                                                    @method('PATCH')
                                                    @php
                                                        $selectedVariantColorId = (string) ($salesItem->variants->first()?->crm_product_color_id
                                                            ?: $salesItem->crmProduct->colors->first()->id);
                                                        $filledVariantColors = $salesItem->crmProduct->colors->mapWithKeys(fn ($color) => [
                                                            (string) $color->id => $salesItem->variants
                                                                ->where('crm_product_color_id', $color->id)
                                                                ->contains(fn ($variant) => (float) $variant->quantity > 0),
                                                        ])->all();
                                                    @endphp
                                                    <div x-data="{ color: @js($selectedVariantColorId), filled: @js($filledVariantColors) }">
                                                        <label class="catalog-field"><span>Colore</span><select x-model="color">@foreach($salesItem->crmProduct->colors as $color)<option value="{{ $color->id }}" x-text="filled[@js((string) $color->id)] ? @js($color->name.' · quantità inserite') : @js($color->name)"></option>@endforeach</select></label>
                                                        <p class="mt-4 text-10 font-semibold leading-relaxed text-gray">Puoi compilare più colori: cambia colore dal menu, le quantità già inserite rimangono memorizzate.</p>
                                                    <div class="mt-6 overflow-x-auto rounded-10 border border-gray-mid bg-white">
                                                        <table class="w-full border-collapse text-10">
                                                            <thead class="sticky top-0 bg-gray-light"><tr>@foreach($salesItem->crmProduct->sizes as $size)<th class="min-w-44 px-2 py-4 text-center">{{ $size->name }}</th>@endforeach</tr></thead>
                                                            <tbody>
                                                                @foreach($salesItem->crmProduct->colors as $color)
                                                                    @php
                                                                        $allowedSizeIds = $color->sizes_restricted
                                                                            ? $color->sizes->pluck('id')
                                                                            : $salesItem->crmProduct->sizes->pluck('id');
                                                                    @endphp
                                                                    <tr x-show="color === @js((string) $color->id)" x-cloak>
                                                                        @foreach($salesItem->crmProduct->sizes as $size)
                                                                            <td class="p-3 text-center">
                                                                                @if($allowedSizeIds->contains($size->id))
                                                                                    @php
                                                                                        $selectedVariant = $salesItem->variants->first(function ($variant) use ($color, $size) {
                                                                                            return $variant->crm_product_color_id === $color->id
                                                                                                && $variant->crm_product_size_id === $size->id;
                                                                                        });
                                                                                        $variantQuantity = $selectedVariant?->quantity;
                                                                                    @endphp
                                                                                    <input name="variants[{{ $color->id }}][{{ $size->id }}]" value="{{ $variantQuantity ? (float) $variantQuantity : '' }}" @input="filled[@js((string) $color->id)] = Array.from($el.closest('tr').querySelectorAll('input')).some(element => Number(element.value) > 0)" type="number" min="0" step="1" inputmode="numeric" class="catalog-variant-quantity h-26 w-40 rounded-6 border-gray-mid px-2 py-1 text-center text-10 font-bold focus:border-bullstar focus:ring-bullstar">
                                                                                @else
                                                                                    <span class="text-gray-mid">—</span>
                                                                                @endif
                                                                            </td>
                                                                        @endforeach
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    </div>
                                                    <div class="mt-8 flex flex-wrap items-center justify-between gap-8">
                                                        <p class="text-10 font-semibold text-gray">La somma deve restare {{ number_format((float) $salesItem->quantity, 0, ',', '.') }} pezzi. Il cliente non dovrà ricompilare il checkout.</p>
                                                        <button class="catalog-button catalog-button-secondary">Salva taglie e colori</button>
                                                    </div>
                                                    @error('variants.'.$salesItem->id)<p class="mt-6 text-11 font-bold text-red-600">{{ $message }}</p>@enderror
                                                </form>
                                            </details>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="grid gap-16 md:grid-cols-2">
                <section class="catalog-surface p-16">
                    <h2 class="text-12 font-extrabold uppercase text-gray">Contatti e spedizione</h2>
                    <p class="mt-12 text-14 font-black">{{ $customerName }}</p>
                    <p class="mt-4 text-13 font-semibold">{{ $checkout->contact_email }}<br>{{ $checkout->contact_phone }}</p>
                    <div class="mt-14 border-t border-gray-mid pt-12 text-13 font-semibold leading-[21px]">
                        @if($checkout->shipping_company)<p>{{ $checkout->shipping_company }}</p>@endif
                        <p>{{ trim($checkout->shipping_first_name.' '.$checkout->shipping_last_name) }}</p>
                        <p>{{ $checkout->shipping_address }}</p>
                        <p>{{ $checkout->shipping_postal_code }} {{ $checkout->shipping_city }} ({{ $checkout->shipping_province }})</p>
                    </div>
                </section>

                <section class="catalog-surface p-16">
                    <h2 class="text-12 font-extrabold uppercase text-gray">Fatturazione</h2>
                    <p class="mt-12 text-14 font-black">{{ $billingName }}</p>
                    <div class="mt-4 text-13 font-semibold leading-[21px]">
                        <p>{{ $checkout->billing_address }}</p>
                        <p>{{ $checkout->billing_postal_code }} {{ $checkout->billing_city }} ({{ $checkout->billing_province }})</p>
                        <p class="mt-8">Codice Fiscale: {{ $checkout->billing_tax_code ?: '—' }}</p>
                        @if($checkout->billing_vat_number)<p>P. IVA: {{ $checkout->billing_vat_number }}</p>@endif
                        @if($checkout->billing_recipient_code)<p>Codice Destinatario: {{ $checkout->billing_recipient_code }}</p>@endif
                        @if($checkout->billing_pec)<p>PEC: {{ $checkout->billing_pec }}</p>@endif
                    </div>
                </section>
            </div>

            <section class="catalog-surface p-16">
                <h2 class="text-12 font-extrabold uppercase text-gray">Note del cliente</h2>
                <p class="mt-10 whitespace-pre-line text-13 font-semibold leading-[21px]">{{ $checkout->notes ?: 'Nessuna nota inserita.' }}</p>
            </section>
        </div>

        <aside class="space-y-16">
            @if(auth('admin')->user()?->hasAdminPermission('leads.manage'))
                <section class="catalog-surface p-16">
                    <h2 class="text-14 font-black uppercase">Gestione ordine</h2>
                    <form method="POST" action="{{ route('admin.checkout-orders.status.update', $checkout) }}" class="mt-12" data-order-status-form data-current-status="{{ $checkout->order_status }}">
                        @csrf
                        @method('PATCH')
                        <label class="block">
                            <span class="text-11 font-extrabold uppercase text-gray">Stato ordine</span>
                            <select name="order_status" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                                @foreach($statuses as $value => $label)<option value="{{ $value }}" @selected($checkout->order_status === $value)>{{ $label }}</option>@endforeach
                            </select>
                        </label>
                        <button class="catalog-button catalog-button-secondary mt-10">Aggiorna stato</button>
                    </form>

                    <div class="mt-8 flex flex-wrap items-center gap-8 border-t border-gray-mid pt-8">
                        @if(auth('admin')->user()?->hasAdminPermission('documents.manage') && $checkout->status !== 'paid')
                            @if($checkout->proforma)
                                <a href="{{ route('admin.documents.show', $checkout->proforma) }}" class="catalog-button catalog-button-primary">Apri proforma {{ $proformaPaymentLabel }}</a>
                            @else
                                <form method="POST" action="{{ route('admin.checkout-orders.proforma.store', $checkout) }}" class="inline-flex">
                                    @csrf
                                    <button class="catalog-button catalog-button-primary">Genera proforma {{ $proformaPaymentLabel }}</button>
                                </form>
                            @endif
                        @endif

                        @if($depositCheckout && $checkout->order_status === 'balance_pending')
                            @if(! $balanceCheckout)
                                <form method="POST" action="{{ route('admin.checkout-orders.balance.store', $depositCheckout) }}" class="inline-flex">
                                    @csrf
                                    <button class="catalog-button catalog-button-secondary">Genera link saldo</button>
                                </form>
                                @if(auth('admin')->user()?->hasAdminPermission('documents.manage'))
                                    <form method="POST" action="{{ route('admin.checkout-orders.balance.proforma.store', $depositCheckout) }}" class="inline-flex">
                                        @csrf
                                        <button class="catalog-button catalog-button-secondary">Genera proforma saldo</button>
                                    </form>
                                @endif
                            @else
                                @if(in_array($balanceCheckout->status, ['bank_transfer_pending', 'paid'], true))
                                    <span class="catalog-badge catalog-badge-green">Checkout saldo completato</span>
                                @else
                                    <a href="{{ $checkoutUrls[$balanceCheckout->id] }}" target="_blank" class="catalog-button catalog-button-secondary">Apri link saldo ↗</a>
                                @endif
                                @if(auth('admin')->user()?->hasAdminPermission('documents.manage') && $checkout->id !== $balanceCheckout->id)
                                    @if($balanceCheckout->proforma)
                                        <a href="{{ route('admin.documents.show', $balanceCheckout->proforma) }}" class="catalog-button catalog-button-secondary">Apri proforma saldo</a>
                                    @else
                                        <form method="POST" action="{{ route('admin.checkout-orders.balance.proforma.store', $depositCheckout) }}" class="inline-flex">
                                            @csrf
                                            <button class="catalog-button catalog-button-secondary">Genera proforma saldo</button>
                                        </form>
                                    @endif
                                @endif
                            @endif
                        @endif

                    </div>
                </section>
            @endif

            @if(auth('admin')->user()?->hasAdminPermission('leads.manage'))
                @php
                    $selectedCarrier = array_key_exists((string) $checkout->fulfillment_carrier, $fulfillmentCarriers)
                        ? $checkout->fulfillment_carrier
                        : ($checkout->fulfillment_carrier ? 'other' : '');
                @endphp
                <section class="catalog-surface p-16" x-data="{ method: @js($checkout->fulfillment_method ?: 'shipping'), carrier: @js($selectedCarrier) }">
                    <h2 class="text-14 font-black uppercase">Consegna</h2>
                    <form method="POST" action="{{ route('admin.checkout-orders.fulfillment.update', $checkout) }}" class="mt-12 space-y-10" data-fulfillment-form data-current-fulfillment-status="{{ $checkout->fulfillment_status ?: 'pending' }}">
                        @csrf
                        @method('PATCH')
                        <label class="catalog-field"><span>Modalità</span><select name="fulfillment_method" x-model="method">@foreach($fulfillmentMethods as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                        <label class="catalog-field"><span>Stato consegna</span><select name="fulfillment_status">@foreach($fulfillmentStatuses as $value => $label)<option value="{{ $value }}" @selected(($checkout->fulfillment_status ?: 'pending') === $value)>{{ $label }}</option>@endforeach</select></label>
                        <div x-show="method === 'shipping'" x-cloak class="grid gap-10">
                            <label class="catalog-field">
                                <span>Corriere</span>
                                <select x-model="carrier">
                                    <option value="">Seleziona il corriere</option>
                                    @foreach($fulfillmentCarriers as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                    <option value="other">Altro</option>
                                </select>
                            </label>
                            <input type="hidden" name="fulfillment_carrier" :value="carrier" :disabled="carrier === 'other'">
                            <label class="catalog-field" x-show="carrier === 'other'" x-cloak>
                                <span>Nome corriere</span>
                                <input name="fulfillment_carrier" value="{{ $selectedCarrier === 'other' ? $checkout->fulfillment_carrier : '' }}" placeholder="Nome del corriere" :disabled="carrier !== 'other'">
                            </label>
                            <label class="catalog-field"><span>Tracking</span><input name="fulfillment_tracking" value="{{ $checkout->fulfillment_tracking }}" placeholder="Codice tracking"></label>
                        </div>
                        <label class="catalog-field"><span>Note consegna</span><textarea name="fulfillment_notes" rows="3" placeholder="Indicazioni interne">{{ $checkout->fulfillment_notes }}</textarea></label>
                        @if($checkout->fulfilled_at)<p class="catalog-help">Completata il {{ $checkout->fulfilled_at->format('d/m/Y H:i') }}</p>@endif
                        <button class="catalog-button catalog-button-primary">Aggiorna consegna</button>
                    </form>
                </section>
            @endif

            <section class="catalog-surface p-16">
                <h2 class="text-14 font-black uppercase">Riepilogo economico</h2>
                <dl class="mt-14 space-y-10 text-13 font-semibold">
                    <div class="flex justify-between gap-12"><dt>Prodotti</dt><dd>{{ $money($checkout->product_subtotal) }}</dd></div>
                    @if((float) $checkout->discount_amount > 0)<div class="flex justify-between gap-12"><dt>Sconto</dt><dd>− {{ $money($checkout->discount_amount) }}</dd></div>@endif
                    @if((float) $checkout->rounding_adjustment !== 0.0)<div class="flex justify-between gap-12"><dt>Arrotondamento</dt><dd>{{ $money($checkout->rounding_adjustment) }}</dd></div>@endif
                    <div class="flex justify-between gap-12"><dt>Spedizione</dt><dd>{{ $money($checkout->shipping_amount) }}</dd></div>
                    <div class="flex justify-between gap-12 border-t border-gray-mid pt-10"><dt>Imponibile ordine</dt><dd>{{ $money($checkout->order_taxable_amount) }}</dd></div>
                    <div class="flex justify-between gap-12 text-gray"><dt>IVA {{ number_format((float) $checkout->vat_rate, 0) }}%</dt><dd>{{ $money($checkout->order_vat_amount) }}</dd></div>
                    <div class="flex justify-between gap-12 border-t border-gray-mid pt-12 text-16 font-black"><dt>Totale ordine</dt><dd>{{ $money($checkout->order_total_amount) }}</dd></div>
                    @if($checkout->payment_type !== 'full')
                        <div class="rounded-10 bg-gray-light px-12 py-10">
                            <p class="text-11 font-extrabold uppercase text-gray">Piano di pagamento</p>
                            <div class="mt-6 divide-y divide-gray-mid">
                                @foreach($payments as $payment)
                                    <div class="flex items-center justify-between gap-12 py-7">
                                        <dt>
                                            <span class="block text-14 font-black">{{ $payment->payment_label }}</span>
                                            <span class="mt-2 block text-10 font-semibold text-gray">
                                                @if($payment->status === 'paid')
                                                    Pagato
                                                @elseif($payment->proforma)
                                                    Proforma generata
                                                @elseif($payment->details_completed_at)
                                                    Proforma da generare
                                                @else
                                                    Checkout da completare
                                                @endif
                                            </span>
                                        </dt>
                                        <dd class="flex flex-col items-end gap-5 text-14 font-black">
                                            <span>{{ $money($payment->total_amount) }}</span>
                                            @if($payment->proforma && $payment->status !== 'paid' && auth('admin')->user()?->hasAdminPermission('documents.manage'))
                                                <form method="POST" action="{{ route('admin.checkout-orders.payment.paid', $payment) }}" data-payment-confirm data-payment-type="{{ $payment->payment_type }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button class="text-10 font-extrabold uppercase text-bullstar hover:underline">Segna come pagato</button>
                                                </form>
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </dl>
            </section>

            <section class="catalog-surface p-16">
                <h2 class="text-14 font-black uppercase">Pagamento</h2>
                <p class="mt-12 text-13 font-semibold">{{ $checkout->payment_method === 'bank' ? 'Bonifico bancario' : 'Pagamento online' }}</p>
                <p class="mt-4 text-11 font-semibold text-gray">Stato checkout: {{ match($checkout->status) { 'bank_transfer_pending' => 'Proforma richiesta', 'details_completed' => 'Dati completati', 'paid' => 'Pagato', 'invalidated' => 'Ordine modificato', 'expired' => 'Scaduto', 'revoked' => 'Revocato', default => 'Bozza' } }}</p>
                @if($checkout->payment_type === 'full' && $checkout->proforma && $checkout->status !== 'paid' && auth('admin')->user()?->hasAdminPermission('documents.manage'))
                    <form method="POST" action="{{ route('admin.checkout-orders.payment.paid', $checkout) }}" class="mt-12 inline-flex" data-payment-confirm data-payment-type="{{ $checkout->payment_type }}">
                        @csrf
                        @method('PATCH')
                        <button class="catalog-button catalog-button-secondary text-green-700">Segna come pagato</button>
                    </form>
                @endif
            </section>

            @if($payments->count() > 1)
                <section class="catalog-surface p-16">
                    <h2 class="text-14 font-black uppercase">Acconto e saldo</h2>
                    <div class="mt-12 divide-y divide-gray-mid">
                        @foreach($payments as $payment)
                            <div class="flex items-center justify-between gap-12 py-10 text-13">
                                <span>
                                    <strong>{{ $payment->payment_label }}</strong>
                                    <span class="mt-2 block text-11 font-semibold text-gray">
                                        @if($payment->proforma)
                                            Proforma generata
                                        @elseif($payment->details_completed_at)
                                            Proforma da generare
                                        @else
                                            Checkout da completare
                                        @endif
                                    </span>
                                </span>
                                <strong>{{ $money($payment->total_amount) }}</strong>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </aside>
    </div>
</div>
@push('scripts')
<script>
    const paymentConfirmation = (paymentType) => paymentType === 'balance'
        ? 'Confermi di segnare il saldo come pagato? L’ordine passerà allo stato Pronto. Non verrà inviata alcuna notifica al cliente.'
        : 'Confermi di segnare la proforma come pagata? L’ordine passerà automaticamente In produzione e il cliente riceverà una notifica tramite WhatsApp oppure, se non disponibile, tramite e-mail.';

    document.querySelectorAll('[data-payment-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (! window.confirm(paymentConfirmation(form.dataset.paymentType))) event.preventDefault();
        });
    });

    document.querySelector('[data-order-status-form]')?.addEventListener('submit', (event) => {
        const form = event.currentTarget;
        const nextStatus = form.elements.order_status.value;
        if (nextStatus === form.dataset.currentStatus) return;

        const messages = {
            in_production: 'Confermi il passaggio a In produzione? Il cliente riceverà automaticamente una notifica tramite WhatsApp oppure, se non disponibile, tramite e-mail.',
            balance_pending: 'Confermi il passaggio a In attesa saldo? Non verrà creato né inviato nulla automaticamente: dovrai scegliere manualmente se generare il link saldo oppure la proforma saldo.',
            ready: 'Confermi il passaggio a Pronto? Non verrà inviata alcuna notifica al cliente.',
            shipped: 'Confermi il passaggio a Spedito? Il cliente riceverà automaticamente una notifica tramite WhatsApp oppure, se non disponibile, tramite e-mail.',
            cancelled: 'Confermi l’annullamento dell’ordine? Non verrà inviata alcuna notifica al cliente.',
        };
        const message = messages[nextStatus] || 'Confermi il cambio di stato dell’ordine?';
        if (! window.confirm(message)) event.preventDefault();
    });

    document.querySelector('[data-fulfillment-form]')?.addEventListener('submit', (event) => {
        const form = event.currentTarget;
        const nextStatus = form.elements.fulfillment_status.value;
        if (nextStatus === form.dataset.currentFulfillmentStatus) return;
        const messages = {
            shipped: 'Confermi la spedizione? Il cliente riceverà automaticamente una notifica tramite WhatsApp oppure, se non disponibile, tramite e-mail.',
            delivered: 'Confermi che l’ordine è stato consegnato? Non verrà inviata alcuna notifica.',
            collected: 'Confermi che l’ordine è stato ritirato? Non verrà inviata alcuna notifica.',
        };
        if (! window.confirm(messages[nextStatus] || 'Confermi l’aggiornamento della consegna?')) event.preventDefault();
    });
</script>
@endpush
@endsection
