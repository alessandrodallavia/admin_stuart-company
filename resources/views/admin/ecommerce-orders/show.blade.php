@extends('admin.layouts.app')

@section('title', $order->reference)
@section('active_nav', 'ecommerce-orders')

@section('content')
@php
    $money = fn ($value) => '€ '.number_format((float) $value, 2, ',', '.');
    $customer = $order->customer;
    $paid = $order->status === 'paid';
    $statusLabel = match (true) {
        $order->status === 'cancelled' => 'Annullato',
        $paid => 'Pagato',
        $order->status === 'proforma_pending' => 'Proforma da generare',
        $order->status === 'bank_transfer_pending' => 'In attesa di bonifico',
        in_array($order->stripe_payment_status, ['processing', 'requires_capture'], true) => 'Pagamento in verifica',
        default => 'In attesa di pagamento',
    };
    $canManageOrder = auth('admin')->user()?->hasAdminPermission('leads.manage');
    $canManageDocuments = auth('admin')->user()?->hasAdminPermission('documents.manage');
@endphp
<div class="catalog-workspace w-full">
    <header class="catalog-page-header pb-24!">
        <div>
            <a href="{{ route('admin.ecommerce-orders.index') }}" class="catalog-eyebrow inline-block hover:text-bullstar">← Ordini ecommerce</a>
            <div class="flex flex-wrap items-center gap-12">
                <h1>{{ $order->reference }}</h1><span class="catalog-badge">{{ $order->status === 'cancelled' ? 'Annullato' : (\App\Models\EcommerceOrder::ORDER_STATUSES[$order->order_status] ?? 'Da gestire') }}</span>
                <span class="catalog-badge {{ $paid ? 'catalog-badge-green' : '' }}">{{ $statusLabel }}</span>
            </div>
            <p>Ricevuto il {{ $order->created_at->format('d/m/Y') }} alle {{ $order->created_at->format('H:i') }} · {{ $order->customer_name }}</p>
        </div>
        <span class="catalog-tax-note">{{ count($order->items) }} articoli · {{ collect($order->items)->sum('quantity') }} pezzi</span>
    </header>


    <div class="grid items-start gap-24 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="min-w-0 space-y-24">
            <section class="catalog-surface">
                <div class="catalog-section-heading px-20! py-16! md:px-24!"><div><h2>Articoli ordinati</h2><p>Varianti, personalizzazioni e file del cliente.</p></div></div>
                @foreach($order->items as $key => $item)
                    <article class="border-t border-gray-mid p-20 md:p-24">
                        <header class="flex flex-wrap items-start justify-between gap-16">
                            <div class="min-w-0">
                                <p class="catalog-eyebrow">Codice {{ $item['code'] }}</p>
                                <h3 class="mt-4 text-18 font-semibold">{{ $item['name'] }}</h3>
                                <p class="mt-8 text-12 text-gray">{{ $item['mode'] === 'neutral' ? 'Capo neutro' : 'Personalizzato' }} · {{ $item['quantity'] }} pezzi</p>
                            </div>
                            <div class="text-right"><p class="whitespace-nowrap text-18 font-semibold">{{ $money($item['gross']) }}</p><p class="mt-4 text-11 text-gray">IVA inclusa</p></div>
                        </header>

                        <div class="mt-20 overflow-x-auto rounded-xl border border-gray-mid">
                            <table class="w-full text-left text-13">
                                <thead class="border-b border-gray-mid bg-gray-light text-11 text-gray"><tr><th class="px-16 py-10 font-semibold">Colore</th><th class="px-16 py-10 font-semibold">Taglia</th><th class="px-16 py-10 text-right font-semibold">Quantità</th></tr></thead>
                                <tbody class="divide-y divide-gray-mid">@foreach($item['rows'] as $row)<tr><td class="px-16 py-10">{{ $row['color'] }}</td><td class="px-16 py-10">{{ $row['size'] }}</td><td class="px-16 py-10 text-right tabular-nums">{{ $row['quantity'] }} pezzi</td></tr>@endforeach</tbody>
                            </table>
                        </div>

                        @include('admin.ecommerce-orders.variants')
                        @if($item['print_labels'])<div class="mt-20"><h4 class="text-12 font-semibold uppercase tracking-normal text-gray">Personalizzazioni</h4><ul class="mt-10 space-y-8 text-13">@foreach($item['print_labels'] as $label)<li>{{ $label }}</li>@endforeach</ul></div>@endif

                        @if($item['previews'])
                            <div class="mt-20"><h4 class="text-12 font-semibold uppercase tracking-normal text-gray">Anteprime</h4>
                                <div class="mt-12 grid grid-cols-2 gap-12 sm:grid-cols-3">
                                    @foreach($item['previews'] as $preview)
                                        <figure class="min-w-0 max-w-[200px]">
                                            <div class="relative aspect-[4/5] overflow-hidden rounded-xl bg-gray-light">
                                                <img class="absolute inset-0 h-full w-full object-contain" src="{{ rtrim(config('services.public_site.url'), '/') . $preview['mockup'] }}" alt="{{ $item['name'] }} · {{ $preview['color'] }}" loading="lazy">
                                                @if($preview['overlay'])<img class="absolute object-contain" alt="Grafica posizionata" src="{{ route('admin.ecommerce-orders.file', [$order, $key, $preview['position'], 'preview' => 1]) }}" style="left:{{ $preview['overlay']['left'] }}%;top:{{ $preview['overlay']['top'] }}%;width:{{ $preview['overlay']['width'] }}%;height:{{ $preview['overlay']['height'] }}%" loading="lazy">@endif
                                            </div>
                                            <figcaption class="mt-8 text-12 leading-relaxed text-gray">{{ $preview['color'] }} · {{ \App\Models\EcommerceOrder::POSITIONS[$preview['position']] }}</figcaption>
                                        </figure>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($item['artworks'])
                            <div class="mt-20"><h4 class="text-12 font-semibold uppercase tracking-normal text-gray">File grafici</h4><div class="mt-12 space-y-10">
                                @foreach($item['artworks'] as $side => $file)
                                    <div class="flex flex-wrap items-center justify-between gap-12 rounded-xl border border-gray-mid p-16">
                                        <div class="min-w-0 flex-1"><p class="text-13 font-semibold">{{ \App\Models\EcommerceOrder::POSITIONS[$side] }}</p><p class="mt-4 break-all text-12 text-gray">{{ $file['name'] }}</p>
                                            @if(isset($item['artwork_composition'][$side]))@php($drawing = $item['artwork_composition'][$side])<p class="mt-8 text-11 leading-relaxed text-gray">Dimensioni: {{ $drawing['width_cm'] }} × {{ $drawing['height_cm'] }} cm<br>Posizione nell’area: X {{ $drawing['x_cm'] }} · Y {{ $drawing['y_cm'] }} cm</p>@endif
                                        </div>
                                        <a class="catalog-button catalog-button-secondary" href="{{ route('admin.ecommerce-orders.file', [$order, $key, $side]) }}">Scarica file ↓</a>
                                    </div>
                                @endforeach
                            </div></div>
                        @endif
                        @if($item['notes'])<div class="mt-20 rounded-xl bg-gray-light p-16"><h4 class="text-12 font-semibold text-gray">Note articolo</h4><p class="mt-8 whitespace-pre-line break-words text-13 leading-relaxed">{{ $item['notes'] }}</p></div>@endif
                    </article>
                @endforeach
            </section>

            <div class="grid gap-16 md:grid-cols-2">
                @foreach([
                    'Contatti' => ['Nome e cognome' => $order->customer_name, 'E-mail' => $order->email, 'Telefono' => $customer['phone'] ?? null],
                    'Spedizione' => ['Destinatario' => $customer['shipping_name'] ?? null, 'Indirizzo' => $customer['shipping_address'] ?? null, 'Località' => trim(($customer['shipping_postal_code'] ?? '').' '.($customer['shipping_city'] ?? '').' '.($customer['shipping_province'] ?? '')), 'Paese' => 'Italia'],
                    'Fatturazione' => ['Tipo cliente' => ($customer['billing_type'] ?? 'private') === 'company' ? 'Azienda' : 'Privato', 'Intestatario' => $customer['billing_name'] ?? null, 'Codice fiscale' => $customer['billing_tax_code'] ?? $customer['billing_company_tax_code'] ?? null, 'Partita IVA' => $customer['billing_vat'] ?? null, 'Codice SDI' => $customer['billing_recipient_code'] ?? null, 'PEC' => $customer['billing_pec'] ?? null, 'Indirizzo' => $customer['billing_address'] ?? null, 'Località' => trim(($customer['billing_postal_code'] ?? '').' '.($customer['billing_city'] ?? '').' '.($customer['billing_province'] ?? ''))],
                ] as $title => $fields)
                    <section class="catalog-surface p-20 md:p-24"><h2 class="text-base font-semibold">{{ $title }}</h2><dl class="mt-16 space-y-16">@foreach($fields as $label => $value)@if($value)<div><dt class="text-11 text-gray">{{ $label }}</dt><dd class="mt-4 break-words text-13 leading-relaxed">{{ $value }}</dd></div>@endif @endforeach</dl></section>
                @endforeach
                @if($customer['notes'] ?? null)<section class="catalog-surface p-20 md:p-24"><h2 class="text-base font-semibold">Note ordine</h2><p class="mt-16 whitespace-pre-line break-words text-13 leading-relaxed">{{ $customer['notes'] }}</p></section>@endif
            </div>
            @include('admin.ecommerce-orders.history')
        </div>

        <aside class="min-w-0 space-y-16" aria-label="Gestione e riepilogo ordine">
            @include('admin.ecommerce-orders.management')
            <section class="catalog-surface p-20 md:p-24">
                <h2 class="text-base font-semibold">Riepilogo economico</h2>
                <dl class="mt-20 space-y-16 text-13">
                    <div class="flex justify-between gap-12"><dt class="text-gray">Imponibile</dt><dd class="tabular-nums">{{ $money($order->net) }}</dd></div>
                    <div class="flex justify-between gap-12"><dt class="text-gray">IVA</dt><dd class="tabular-nums">{{ $money($order->vat) }}</dd></div>
                    <div class="flex justify-between gap-12 border-t border-gray-mid pt-16 text-18 font-semibold"><dt>Totale ordine</dt><dd class="whitespace-nowrap tabular-nums">{{ $money($order->gross) }}</dd></div>
                </dl>
                <p class="mt-12 text-11 leading-relaxed text-gray">IVA inclusa · {{ $order->shipping_gross > 0 ? 'Spedizione inclusa: '.$money($order->shipping_gross) : 'Spedizione gratuita' }}</p>
            </section>
            <section class="catalog-surface p-20 md:p-24">
                <h2 class="text-base font-semibold">Pagamento</h2>
                <p class="mt-16 text-13 font-semibold">{{ $order->payment_method === 'bank' ? 'Bonifico bancario' : 'Stripe' }}</p>
                <p class="mt-8 text-12 leading-relaxed text-gray">{{ $statusLabel }}</p>
                @if($order->paid_at)<p class="mt-8 text-12 text-gray">Confermato il {{ $order->paid_at->format('d/m/Y H:i') }}</p>@endif
                @if($order->stripe_last_error && !$paid)<p class="mt-12 rounded-xl bg-red-50 p-12 text-12 leading-relaxed text-red-700">{{ $order->stripe_last_error }}</p>@endif

                @if($order->payment_method === 'bank')
                    <div class="mt-20 space-y-12 border-t border-gray-mid pt-16">
                        @if($order->proforma)<a class="catalog-button catalog-button-secondary w-full" href="{{ route('admin.documents.show', $order->proforma) }}">Apri proforma {{ $order->proforma->display_code }} ↗</a>@endif
                        @if($canManageDocuments && $order->status !== 'cancelled')
                            @if(!$order->proforma && !$paid)<p class="text-12 leading-relaxed text-gray">Genera la proforma per inviare al cliente importo e coordinate del bonifico.</p><form method="POST" action="{{ route('admin.ecommerce-orders.proforma', $order) }}">@csrf<button class="catalog-button catalog-button-primary w-full">Genera proforma</button></form>@endif
                            @if($order->proforma && !$order->proforma_sent_at)<form method="POST" action="{{ route('admin.ecommerce-orders.proforma.send', $order) }}">@csrf<button class="catalog-button catalog-button-secondary w-full">Invia proforma al cliente</button></form>@endif
                            @if($order->proforma && !$paid)<p class="text-12 leading-relaxed text-gray">Registra il pagamento dopo aver verificato l’accredito.</p><form method="POST" action="{{ route('admin.ecommerce-orders.payment', $order) }}" data-confirm-order-update data-order-confirm-message="Confermi di aver verificato l’accredito del bonifico?" data-order-notify-available="false">@csrf @method('PATCH')<button class="catalog-button catalog-button-primary w-full">Registra bonifico ricevuto</button></form>@endif
                        @endif
                    </div>
                @endif
            </section>
            @if($order->status === 'cancelled')
                <section class="catalog-surface p-20 md:p-24"><h2 class="text-base font-semibold">Ordine annullato</h2><p class="mt-12 text-12 text-gray">{{ $order->cancelled_at?->format('d/m/Y H:i') }}</p><p class="mt-12 whitespace-pre-line text-13 leading-relaxed">{{ $order->cancellation_reason }}</p></section>
            @elseif(!$paid && auth('admin')->user()?->hasAdminPermission('leads.manage'))
                <section class="catalog-surface p-20 md:p-24"><details><summary class="cursor-pointer text-13 font-semibold text-red-700">Annulla ordine</summary><p class="mt-16 text-12 leading-relaxed text-gray">Usa questa azione per un ordine non completato o con sessione cliente scaduta. L’eventuale pagamento Stripe verrà chiuso e l’ordine non sarà più pagabile.</p><form method="POST" action="{{ route('admin.ecommerce-orders.cancel', $order) }}" class="mt-16 space-y-12">@csrf @method('PATCH')<label class="catalog-field"><span>Motivo (facoltativo)</span><textarea name="reason" maxlength="1000" rows="3" class="w-full rounded-xl border border-gray-mid p-12 text-13" placeholder="Sessione cliente scaduta">{{ old('reason') }}</textarea></label><button class="catalog-button catalog-button-secondary w-full border-red-200! text-red-700!">Conferma annullamento</button></form></details></section>
            @endif
            <section class="catalog-surface p-20 md:p-24">
                <h2 class="text-base font-semibold">Comunicazioni</h2>
                <dl class="mt-16 space-y-16 text-12"><div><dt class="text-gray">Conferma ordine</dt><dd class="mt-4">{{ $order->confirmation_sent_at ? 'Inviata il '.$order->confirmation_sent_at->format('d/m/Y H:i') : 'In attesa di invio' }}</dd></div>
                @if($order->payment_method === 'bank')<div><dt class="text-gray">Proforma</dt><dd class="mt-4">{{ $order->proforma_sent_at ? 'Inviata il '.$order->proforma_sent_at->format('d/m/Y H:i') : 'Non ancora inviata' }}</dd></div>@endif</dl>
            </section>
        </aside>
    </div>
</div>
@endsection
