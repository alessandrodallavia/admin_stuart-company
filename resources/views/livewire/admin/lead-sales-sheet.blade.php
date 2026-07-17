<div data-crm-sales class="rounded-10 border border-gray-mid p-12">
    <div class="flex flex-wrap items-start justify-between gap-10">
        <div>
            <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Scheda vendita</p>
            <p class="mt-5 text-14 font-bold">Prodotti, lavorazioni e marginalità</p>
        </div>
        <div class="grid grid-cols-2 gap-x-12 gap-y-8 text-right sm:grid-cols-4">
            <div><p class="text-10 font-extrabold uppercase text-gray">Vendita</p><p class="mt-4 text-16 font-black">€ {{ number_format((float)($salesSheet?->revenue_total ?? 0), 2, ',', '.') }}</p></div>
            <div><p class="text-10 font-extrabold uppercase text-gray">Costo</p><p class="mt-4 text-16 font-black">€ {{ number_format((float)($salesSheet?->cost_total ?? 0), 2, ',', '.') }}</p></div>
            <div><p class="text-10 font-extrabold uppercase text-gray">Margine</p><p class="mt-4 text-16 font-black text-bullstar">€ {{ number_format((float)($salesSheet?->margin_total ?? 0), 2, ',', '.') }}</p></div>
            <div><p class="text-10 font-extrabold uppercase text-gray">Margine %</p><p class="mt-4 text-16 font-black">{{ number_format((float)($salesSheet?->margin_percentage ?? 0), 2, ',', '.') }}%</p></div>
        </div>
    </div>

    @if($statusMessage)
        <div class="mt-10 rounded-10 border border-bullstar/20 bg-bullstar/5 px-10 py-8 text-11 font-bold text-bullstar" wire:key="sales-status">{{ $statusMessage }}</div>
    @endif

    <form wire:submit="addProduct" class="mt-12 grid gap-8 rounded-10 border border-gray-mid bg-gray-light p-10 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_120px_auto] md:items-end">
        <label>
            <span class="text-10 font-extrabold uppercase text-gray">Prodotto</span>
            <select wire:model="productId" required class="mt-4 w-full border-gray-mid bg-white">
                <option value="">Seleziona prodotto</option>
                @foreach($products as $product)<option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>@endforeach
            </select>
            @error('productId')<span class="mt-4 block text-10 font-bold text-red-600">{{ $message }}</span>@enderror
        </label>
        <label>
            <span class="text-10 font-extrabold uppercase text-gray">Nome configurazione <span class="normal-case text-gray">(facoltativo)</span></span>
            <input wire:model="configurationName" type="text" maxlength="255" placeholder="Es. Maglia staff evento" class="mt-4 w-full border-gray-mid bg-white">
            @error('configurationName')<span class="mt-4 block text-10 font-bold text-red-600">{{ $message }}</span>@enderror
        </label>
        <label>
            <span class="text-10 font-extrabold uppercase text-gray">Quantità</span>
            <input wire:model="quantity" required type="number" min="0.01" step="0.01" placeholder="0,00" class="mt-4 w-full border-gray-mid bg-white">
            @error('quantity')<span class="mt-4 block text-10 font-bold text-red-600">{{ $message }}</span>@enderror
        </label>
        <button type="submit" wire:loading.attr="disabled" wire:target="addProduct" class="rounded-10 bg-black-nike text-10 font-extrabold uppercase text-white disabled:opacity-50">
            <span wire:loading.remove wire:target="addProduct">Aggiungi</span><span wire:loading wire:target="addProduct">Attendi…</span>
        </button>
    </form>

    <div class="mt-12 grid gap-10">
        @forelse($salesSheet?->items ?? [] as $item)
            <article wire:key="sales-item-{{ $item->id }}" class="rounded-10 border border-gray-mid p-10">
                <div class="flex flex-wrap justify-between gap-10">
                    <div>
                        <p class="text-14 font-black">{{ $item->configuration_name ?: $item->product_name }}</p>
                        <p class="mt-4 text-12 text-gray">{{ $item->product_code }} · {{ $item->product_name }} · {{ number_format((float)$item->quantity, 2, ',', '.') }} pz · €{{ number_format((float)$item->product_unit_price, 2, ',', '.') }}/pz</p>
                    </div>
                    <div class="text-right"><p class="text-13 font-black">Vendita €{{ number_format((float)$item->revenue_total, 2, ',', '.') }}</p><p class="mt-3 text-12 font-bold text-bullstar">Margine €{{ number_format((float)$item->margin_total, 2, ',', '.') }}</p></div>
                </div>

                @if($item->prints->isNotEmpty())
                    <div class="mt-8 flex flex-wrap gap-5">
                        @foreach($item->prints as $print)
                            <button type="button" wire:key="sales-print-{{ $print->id }}" wire:click="removePrint({{ $item->id }}, {{ $print->id }})" wire:loading.attr="disabled" title="Rimuovi lavorazione" class="rounded-full bg-gray-light text-11 font-bold disabled:opacity-50">{{ $print->print_name }} · €{{ number_format((float)$print->unit_price, 2, ',', '.') }} ×</button>
                        @endforeach
                    </div>
                @endif

                <div class="mt-8 grid items-end gap-6 sm:grid-cols-[minmax(0,1fr)_auto]">
                    <form wire:submit="addPrint({{ $item->id }})" class="grid items-end gap-6 sm:grid-cols-[minmax(0,1fr)_auto]">
                        <label>
                            <span class="text-10 font-extrabold uppercase text-gray">Aggiungi lavorazione</span>
                            <select wire:model="printTypeIds.{{ $item->id }}" required class="mt-4 w-full border-gray-mid bg-white">
                                <option value="">Seleziona lavorazione</option>
                                @foreach($printTypes as $type)<option value="{{ $type->id }}">{{ $type->code }} · {{ $type->name }}</option>@endforeach
                            </select>
                            @error('printTypeIds.'.$item->id)<span class="mt-4 block text-10 font-bold text-red-600">{{ $message }}</span>@enderror
                        </label>
                        <button type="submit" wire:loading.attr="disabled" wire:target="addPrint({{ $item->id }})" class="rounded-10 border border-black-nike text-10 font-extrabold uppercase disabled:opacity-50">Aggiungi</button>
                    </form>
                    <button type="button" wire:click="removeProduct({{ $item->id }})" wire:confirm="Rimuovere il prodotto?" wire:loading.attr="disabled" class="w-full rounded-10 border border-red-600 text-10 font-extrabold uppercase text-red-600 disabled:opacity-50">Rimuovi prodotto</button>
                </div>
            </article>
        @empty
            <p class="text-12 font-semibold text-gray">Nessun prodotto nella scheda vendita.</p>
        @endforelse
    </div>
</div>
