@if($canManageOrder && $order->status !== 'cancelled')
    @php($variantProduct = ($variantProducts ?? collect())->get($item['product_id'] ?? 0))
    <details class="mt-20 rounded-xl border border-gray-mid p-16">
        <summary class="cursor-pointer text-13 font-semibold">Modifica colori e taglie</summary>
        @if($order->proforma || $order->invoice || $order->deliveryDocument)
            <p class="mt-12 text-12 leading-relaxed text-gray">Per modificare le varianti occorre rimuovere i documenti già generati.</p>
        @elseif(!$variantProduct)
            <p class="mt-12 text-12 text-gray">Il prodotto non è collegato al catalogo CRM.</p>
        @else
            <form method="POST" action="{{ route('admin.ecommerce-orders.variants', [$order, $key]) }}" class="mt-16 space-y-16">@csrf @method('PATCH')
                <p class="text-12 leading-relaxed text-gray">Distribuisci {{ $item['quantity'] }} pezzi tra colori e taglie. Il totale e i prezzi non cambiano.</p>
                @foreach($variantProduct->colors as $variantColor)
                    @php($selectedRows = collect($item['rows'])->where('color_id', $variantColor->id))
                    <details class="rounded-xl bg-gray-light p-12" @if($selectedRows->isNotEmpty()) open @endif>
                        <summary class="cursor-pointer text-13 font-semibold">{{ $variantColor->name }}</summary>
                        <div class="mt-12 grid grid-cols-3 gap-12 sm:grid-cols-4">
                            @foreach($variantColor->sizes_restricted ? $variantColor->sizes : $variantProduct->sizes as $variantSize)
                                <label class="catalog-field text-center"><span>{{ $variantSize->name }}</span><input type="number" inputmode="numeric" min="0" max="10000" step="1" class="w-full text-center" name="variants[{{ $variantColor->id }}][{{ $variantSize->id }}]" value="{{ old('variants.'.$variantColor->id.'.'.$variantSize->id, $selectedRows->where('size', $variantSize->name)->sum('quantity')) }}" aria-label="Quantità {{ $variantColor->name }} {{ $variantSize->name }}"></label>
                            @endforeach
                        </div>
                    </details>
                @endforeach
                <button class="catalog-button catalog-button-primary">Salva varianti</button>
            </form>
        @endif
    </details>
@endif
