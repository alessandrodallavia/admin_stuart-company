<section class="catalog-surface p-20 md:p-24">
    <h2 class="text-base font-semibold">Gestione ordine</h2>
    @if($order->status === 'cancelled')<p class="mt-16 text-13 text-gray">Ordine annullato</p>
    @elseif($canManageOrder)
        <form method="POST" action="{{ route('admin.ecommerce-orders.status', $order) }}" data-confirm-order-update data-order-confirm-field="order_status" data-order-notify-values="in_production,shipped" class="mt-16 space-y-16">@csrf @method('PATCH')
            <label class="catalog-field"><span>Stato operativo</span><select name="order_status">@foreach(\App\Models\EcommerceOrder::ORDER_STATUSES as $value => $label)<option value="{{ $value }}" @selected(old('order_status', $order->order_status) === $value)>{{ $label }}</option>@endforeach</select></label>
            <p class="text-12 leading-relaxed text-gray">Lo stato operativo non modifica il pagamento. Puoi scegliere se inviare la comunicazione prevista al cliente.</p>
            @include('admin.orders.notification-choice')
            <button class="catalog-button catalog-button-primary w-full">Aggiorna stato</button>
        </form>
    @else<p class="mt-16 text-13">{{ \App\Models\EcommerceOrder::ORDER_STATUSES[$order->order_status] ?? 'Da gestire' }}</p>@endif
</section>
<section class="catalog-surface p-20 md:p-24">
    <h2 class="text-base font-semibold">Spedizione e ritiro</h2>
    @if($canManageOrder && $order->status !== 'cancelled')
        <form method="POST" action="{{ route('admin.ecommerce-orders.fulfillment', $order) }}" data-confirm-order-update data-order-confirm-field="fulfillment_status" data-order-notify-values="shipped,delivered,collected" class="mt-16 space-y-16">@csrf @method('PATCH')
            <label class="catalog-field"><span>Metodo di consegna</span><select name="fulfillment_method">@foreach(\App\Models\EcommerceOrder::FULFILLMENT_METHODS as $value => $label)<option value="{{ $value }}" @selected(old('fulfillment_method', $order->fulfillment_method) === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="catalog-field"><span>Stato consegna</span><select name="fulfillment_status">@foreach(\App\Models\EcommerceOrder::FULFILLMENT_STATUSES as $value => $label)<option value="{{ $value }}" @selected(old('fulfillment_status', $order->fulfillment_status) === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="catalog-field"><span>Corriere (per spedizioni)</span><input name="fulfillment_carrier" maxlength="255" list="ecommerce-carriers" value="{{ old('fulfillment_carrier', $order->fulfillment_carrier) }}"><datalist id="ecommerce-carriers">@foreach(['BRT','GLS','DHL','UPS','FedEx','Poste Italiane / SDA'] as $carrier)<option value="{{ $carrier }}">@endforeach</datalist></label>
            <label class="catalog-field"><span>Codice tracking</span><input name="fulfillment_tracking" maxlength="255" value="{{ old('fulfillment_tracking', $order->fulfillment_tracking) }}"></label>
            <label class="catalog-field"><span>Note di consegna interne</span><textarea name="fulfillment_notes" rows="3" maxlength="2000" class="w-full rounded-xl border border-gray-mid p-12 text-13">{{ old('fulfillment_notes', $order->fulfillment_notes) }}</textarea></label>
            @include('admin.orders.notification-choice')
            <button class="catalog-button catalog-button-primary w-full">Salva consegna</button>
        </form>
    @else
        <dl class="mt-16 space-y-12 text-13"><div><dt class="text-11 text-gray">Metodo</dt><dd class="mt-4">{{ \App\Models\EcommerceOrder::FULFILLMENT_METHODS[$order->fulfillment_method] ?? 'Spedizione' }}</dd></div><div><dt class="text-11 text-gray">Stato</dt><dd class="mt-4">{{ \App\Models\EcommerceOrder::FULFILLMENT_STATUSES[$order->fulfillment_status] ?? 'Da gestire' }}</dd></div>@if($order->fulfillment_tracking)<div><dt class="text-11 text-gray">Tracking</dt><dd class="mt-4 break-all">{{ $order->fulfillment_carrier }} · {{ $order->fulfillment_tracking }}</dd></div>@endif</dl>
    @endif
    @if($order->fulfilled_at)<p class="mt-16 text-12 text-gray">Completata il {{ $order->fulfilled_at->format('d/m/Y H:i') }}</p>@endif
</section>
<section class="catalog-surface p-20 md:p-24">
    <h2 class="text-base font-semibold">Documenti</h2>
    <div class="mt-16 space-y-12">
        @foreach(['invoice' => ['Fattura', 'invoice'], 'delivery_note' => ['DDT', 'deliveryDocument']] as $type => [$label, $relation])
            @if($order->$relation)<a class="catalog-button catalog-button-secondary w-full" href="{{ route('admin.documents.show', $order->$relation) }}">Apri {{ $label }} {{ $order->$relation->display_code }} ↗</a>
            @elseif($canManageDocuments && $order->status !== 'cancelled')<form method="POST" action="{{ route('admin.ecommerce-orders.documents', $order) }}">@csrf<input type="hidden" name="type" value="{{ $type }}"><button class="catalog-button catalog-button-secondary w-full">Genera {{ $label }}</button></form>@endif
        @endforeach
    </div>
</section>
