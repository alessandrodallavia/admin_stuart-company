<div data-crm-sales class="overflow-hidden rounded-10 border border-gray-mid bg-white">
    {{-- Header e indicatori economici --}}
    <header class="border-b border-gray-mid bg-black-nike px-12 py-12 text-white sm:px-16 sm:py-12">
        <div class="flex flex-col gap-10 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-10 font-extrabold uppercase tracking-wider text-white/60">{{ $salesSheet?->order_number }} · {{ $salesSheet?->name }}</p>
                <h2 class="mt-4 text-18 font-black">Configura prodotti e materiali dell'ordine</h2>
                <p class="mt-4 max-w-2xl text-11 font-semibold text-white/60">Prezzi, lavorazioni, colori e grafiche in un unico flusso.</p>
            </div>

            <div class="grid grid-cols-2 gap-6 sm:grid-cols-4 lg:min-w-[520px]">
                <div class="rounded-10 bg-white/10 px-8 py-8">
                    <p class="text-10 font-extrabold uppercase text-white/50">Totale cliente</p>
                    <p class="mt-3 text-16 font-black">€ {{ number_format((float)($salesSheet?->revenue_total ?? 0), 2, ',', '.') }}</p>
                </div>
                <div class="rounded-10 bg-white/10 px-8 py-8">
                    <p class="text-10 font-extrabold uppercase text-white/50">Costi diretti</p>
                    <p class="mt-3 text-16 font-black">€ {{ number_format((float)($salesSheet?->cost_total ?? 0), 2, ',', '.') }}</p>
                </div>
                <div class="rounded-10 bg-bullstar px-8 py-8">
                    <p class="text-10 font-extrabold uppercase text-white/70">Margine</p>
                    <p class="mt-3 text-16 font-black">€ {{ number_format((float)($salesSheet?->margin_total ?? 0), 2, ',', '.') }}</p>
                </div>
                <div class="rounded-10 bg-white/10 px-8 py-8">
                    <p class="text-10 font-extrabold uppercase text-white/50">Margine %</p>
                    <p class="mt-3 text-16 font-black">{{ number_format((float)($salesSheet?->margin_percentage ?? 0), 2, ',', '.') }}%</p>
                </div>
            </div>
        </div>
    </header>

    <div class="p-10 sm:p-12">
        @if($statusMessage)
            <div class="mb-10 flex items-center gap-6 rounded-10 border border-bullstar/20 bg-bullstar/5 px-10 py-8 text-11 font-bold text-bullstar" wire:key="sales-status">
                <span class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-bullstar text-10 text-white">✓</span>
                {{ $statusMessage }}
            </div>
        @endif

        @php
            $hasProducts = ($salesSheet?->items?->count() ?? 0) > 0;
            $freeShipping = $hasProducts && (float)($salesSheet?->product_revenue_total ?? 0) > (float)($salesSheet?->free_shipping_threshold ?? 250);
        @endphp
        <section class="mb-12 grid gap-10 lg:grid-cols-2">
            <form wire:submit="saveShipping" class="rounded-10 border border-gray-mid bg-gray-light p-10 sm:p-12">
                <div class="flex flex-wrap items-start justify-between gap-6">
                    <div>
                        <p class="text-12 font-black uppercase">Spedizione</p>
                        <p class="mt-3 text-10 font-semibold text-gray">Tariffa addebitata al cliente fino alla soglia; oltre la soglia resta a carico Stuart.</p>
                    </div>
                    <span class="rounded-full px-8 py-5 text-10 font-extrabold uppercase {{ $freeShipping ? 'bg-bullstar/10 text-bullstar' : 'bg-white text-gray' }}">
                        {{ ! $hasProducts ? 'In attesa prodotti' : ($freeShipping ? 'A carico Stuart' : 'Addebitata al cliente') }}
                    </span>
                </div>

                <div class="mt-10 grid gap-8 sm:grid-cols-2">
                    <label>
                        <span class="text-10 font-extrabold uppercase text-gray">Tariffa spedizione</span>
                        <div class="mt-4 flex overflow-hidden rounded-10 border border-gray-mid bg-white focus-within:border-bullstar focus-within:ring-1 focus-within:ring-bullstar">
                            <span class="flex shrink-0 items-center border-r border-gray-mid bg-white px-10 text-12 font-bold text-gray">€</span>
                            <input wire:model="shippingFee" required type="number" min="0" step="0.01" class="min-w-0 flex-1 border-0 bg-white px-10 py-8 text-12 font-semibold focus:border-0 focus:ring-0">
                        </div>
                        @error('shippingFee')<span class="mt-4 block text-10 font-bold text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <label>
                        <span class="text-10 font-extrabold uppercase text-gray">Gratuita oltre</span>
                        <div class="mt-4 flex overflow-hidden rounded-10 border border-gray-mid bg-white focus-within:border-bullstar focus-within:ring-1 focus-within:ring-bullstar">
                            <span class="flex shrink-0 items-center border-r border-gray-mid bg-white px-10 text-12 font-bold text-gray">€</span>
                            <input wire:model="freeShippingThreshold" required type="number" min="0" step="0.01" class="min-w-0 flex-1 border-0 bg-white px-10 py-8 text-12 font-semibold focus:border-0 focus:ring-0">
                        </div>
                        @error('freeShippingThreshold')<span class="mt-4 block text-10 font-bold text-red-600">{{ $message }}</span>@enderror
                    </label>
                </div>

                <div class="mt-10 grid grid-cols-3 gap-4 rounded-10 border border-gray-mid bg-white p-8 text-center">
                    <div><p class="text-10 font-extrabold uppercase text-gray">Prodotti</p><p class="mt-3 text-12 font-black">€ {{ number_format((float)($salesSheet?->product_revenue_total ?? 0), 2, ',', '.') }}</p></div>
                    <div><p class="text-10 font-extrabold uppercase text-gray">Spedizione cliente</p><p class="mt-3 text-12 font-black">€ {{ number_format((float)($salesSheet?->shipping_charge ?? 0), 2, ',', '.') }}</p></div>
                    <div><p class="text-10 font-extrabold uppercase text-gray">Costo spedizione</p><p class="mt-3 text-12 font-black">€ {{ number_format((float)($salesSheet?->shipping_cost ?? 0), 2, ',', '.') }}</p></div>
                </div>

                <button type="submit" wire:loading.attr="disabled" wire:target="saveShipping" class="mt-8 w-full rounded-10 bg-black-nike px-10 py-9 text-10 font-extrabold uppercase text-white transition hover:bg-bullstar disabled:opacity-50">Aggiorna spedizione</button>
            </form>

            <div class="rounded-10 border border-gray-mid bg-black-nike p-10 text-white sm:p-12">
                <div class="flex items-start justify-between gap-8">
                    <div>
                        <p class="text-12 font-black uppercase">Diagnosi economica</p>
                        <p class="mt-3 text-10 font-semibold text-white/60">{{ $isReorder ? 'Il riordino non sostiene nuovamente il costo di acquisizione del cliente.' : 'Il CAC medio è calcolato sugli ultimi 30 giorni.' }}</p>
                    </div>
                    <span class="rounded-full px-8 py-5 text-10 font-extrabold uppercase {{ $economicStatus['class'] }}">{{ $economicStatus['label'] }}</span>
                </div>
                <div class="mt-10 grid grid-cols-2 gap-6">
                    <div class="rounded-10 bg-white/10 p-8"><p class="text-10 font-extrabold uppercase text-white/50">Totale cliente</p><p class="mt-3 text-14 font-black">{{ $salesSheet && $hasProducts ? '€ '.number_format((float)$salesSheet->revenue_total, 2, ',', '.') : 'N.D.' }}</p></div>
                    <div class="rounded-10 bg-white/10 p-8"><p class="text-10 font-extrabold uppercase text-white/50">Costi diretti</p><p class="mt-3 text-14 font-black">{{ $salesSheet && $hasProducts ? '€ '.number_format((float)$salesSheet->cost_total, 2, ',', '.') : 'N.D.' }}</p></div>
                    <div class="rounded-10 bg-white/10 p-8"><p class="text-10 font-extrabold uppercase text-white/50">Margine lordo</p><p class="mt-3 text-14 font-black">{{ $salesSheet && $hasProducts ? '€ '.number_format((float)$salesSheet->margin_total, 2, ',', '.') : 'N.D.' }}</p></div>
                    <div class="rounded-10 bg-white/10 p-8">
                        <div class="inline-flex items-center justify-center gap-4">
                            <p class="text-10 font-extrabold uppercase leading-none text-white/50">{{ $isReorder ? 'CAC riordino' : 'CAC medio' }}</p>
                            <span class="group relative inline-flex normal-case">
                                <button type="button" aria-label="Informazioni sul CAC {{ $isReorder ? 'del riordino' : 'del primo ordine' }}" class="inline-flex h-16 w-16 -translate-y-px cursor-help items-center justify-center rounded-full border border-white/40 text-10 font-black leading-none text-white/70 focus:border-white focus:text-white focus:outline-none">?</button>
                                <span role="tooltip" class="pointer-events-none invisible absolute bottom-full left-1/2 z-50 mb-6 w-[230px] -translate-x-1/2 rounded-10 bg-white px-9 py-7 text-left text-10 font-semibold normal-case leading-[15px] text-black-nike opacity-0 shadow-lg transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">{{ $isReorder ? 'Il costo di acquisizione viene attribuito soltanto al primo ordine del cliente. Per questo riordino il CAC è pari a zero.' : 'Questo è il primo ordine del cliente: il CAC medio degli ultimi 30 giorni viene sottratto qui una sola volta.' }}</span>
                            </span>
                        </div>
                        <p class="mt-3 text-14 font-black">{{ $currentCac !== null ? '€ '.number_format($currentCac, 2, ',', '.') : 'N.D.' }}</p>
                    </div>
                    <div class="rounded-10 bg-white/10 p-8"><p class="text-10 font-extrabold uppercase text-white/50">Profitto dopo Ads</p><p class="mt-3 text-14 font-black">{{ $profitAfterAds !== null ? '€ '.number_format($profitAfterAds, 2, ',', '.') : 'N.D.' }}</p></div>
                    <div class="rounded-10 bg-white/10 p-8"><p class="text-10 font-extrabold uppercase text-white/50">Margine finale</p><p class="mt-3 text-14 font-black">{{ $profitPercentage !== null ? number_format($profitPercentage, 1, ',', '.').'%' : 'N.D.' }}</p></div>
                    <div class="col-span-2 rounded-10 bg-white/10 p-8"><div class="flex items-center justify-between gap-6"><div><p class="text-10 font-extrabold uppercase text-white/50">CAC massimo sostenibile</p><p class="mt-3 text-14 font-black">{{ $maximumSustainableCac !== null ? '€ '.number_format($maximumSustainableCac, 2, ',', '.') : 'N.D.' }}</p></div><p class="max-w-[210px] text-right text-10 font-semibold leading-[14px] text-white/50">Margine lordo meno il 30% del totale cliente.</p></div></div>
                </div>
                <p class="mt-8 rounded-10 bg-white/10 px-8 py-7 text-10 font-semibold leading-[16px] text-white/70">
                    @if($profitPercentage !== null)
                        {{ $isReorder ? 'Senza un nuovo CAC restano' : 'Dopo il CAC restano' }} {{ number_format($profitPercentage, 1, ',', '.') }}% del totale vendita.
                    @else
                        La diagnosi sarà disponibile quando esistono prodotti e un CAC calcolabile.
                    @endif
                </p>
            </div>
        </section>

        <form wire:submit="saveAdjustments" class="mb-12 rounded-10 border border-gray-mid bg-white p-10 sm:p-12">
            <div class="flex flex-wrap items-start justify-between gap-6">
                <div>
                    <p class="text-12 font-black uppercase">Sconto e arrotondamento</p>
                    <p class="mt-3 text-10 font-semibold text-gray">Applicati al totale prodotti prima del calcolo della spedizione.</p>
                </div>
                <span class="rounded-full bg-gray-light px-8 py-5 text-10 font-extrabold uppercase text-gray">Sconto € {{ number_format((float)($salesSheet?->discount_amount ?? 0), 2, ',', '.') }}</span>
            </div>
            <div class="mt-8 grid gap-8 md:grid-cols-4 md:items-end">
                <label><span class="text-10 font-extrabold uppercase text-gray">Tipo sconto</span><select wire:model.live="discountType" class="mt-4 w-full rounded-10 border-gray-mid bg-white px-10 py-8 text-12 font-semibold focus:border-bullstar focus:ring-bullstar"><option value="">Nessuno</option><option value="percentage">Percentuale</option><option value="fixed">Importo fisso</option></select></label>
                <label><span class="text-10 font-extrabold uppercase text-gray">Valore sconto</span><input wire:model="discountValue" type="number" min="0" step="0.01" class="mt-4 w-full rounded-10 border-gray-mid bg-white px-10 py-8 text-12 font-semibold focus:border-bullstar focus:ring-bullstar">@error('discountValue')<span class="mt-4 block text-10 font-bold text-red-600">{{ $message }}</span>@enderror</label>
                <label><span class="flex items-center gap-4 text-10 font-extrabold uppercase text-gray">Arrotondamento totale <span class="group relative inline-flex normal-case"><span tabindex="0" aria-label="Informazioni sull'arrotondamento" class="flex h-16 w-16 cursor-help items-center justify-center rounded-full border border-gray text-10 font-black leading-none text-gray focus:border-bullstar focus:text-bullstar">i</span><span role="tooltip" class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-5 hidden w-[190px] -translate-x-1/2 rounded-10 bg-black-nike px-8 py-6 text-center text-10 font-semibold normal-case leading-[14px] text-white shadow-lg group-hover:block group-focus-within:block">Accetta anche valori negativi.</span></span></span><div class="mt-4 flex overflow-hidden rounded-10 border border-gray-mid bg-white"><span class="flex items-center border-r border-gray-mid px-10 text-12 font-bold text-gray">€</span><input wire:model="roundingAdjustment" type="number" step="0.01" class="min-w-0 flex-1 border-0 px-10 py-8 text-12 font-semibold focus:ring-0"></div></label>
                <button class="rounded-10 bg-black-nike px-10 py-9 text-10 font-extrabold uppercase text-white transition hover:bg-bullstar">Applica correzioni</button>
            </div>
        </form>

        {{-- Inserimento prodotto --}}
        <section class="rounded-10 border border-gray-mid bg-gray-light p-10 sm:p-12">
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div>
                    <p class="text-12 font-black uppercase">Aggiungi un prodotto</p>
                    <p class="mt-3 text-11 font-semibold text-gray">Il prezzo viene proposto dal listino in base alla quantità.</p>
                </div>
                <span class="rounded-full bg-white px-8 py-4 text-10 font-extrabold text-gray">{{ $salesSheet?->items?->count() ?? 0 }} prodotti</span>
            </div>

            <form wire:submit="addProduct" class="mt-10 grid gap-8 md:grid-cols-2 lg:grid-cols-12 lg:items-end">
                <label class="lg:col-span-3">
                    <span class="text-10 font-extrabold uppercase text-gray">Prodotto</span>
                    <select wire:model.live="productId" required class="mt-4 w-full rounded-10 border-gray-mid bg-white px-10 py-8 text-12 font-semibold focus:border-bullstar focus:ring-bullstar">
                        <option value="">Seleziona dal catalogo</option>
                        @foreach($products as $product)<option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>@endforeach
                    </select>
                    @error('productId')<span class="mt-4 block text-10 font-bold text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="lg:col-span-2">
                    <span class="text-10 font-extrabold uppercase text-gray">Nome configurazione <span class="normal-case font-semibold">(facoltativo)</span></span>
                    <input wire:model="configurationName" type="text" maxlength="255" placeholder="Es. Maglia staff evento" class="mt-4 w-full rounded-10 border-gray-mid bg-white px-10 py-8 text-12 font-semibold focus:border-bullstar focus:ring-bullstar">
                    @error('configurationName')<span class="mt-4 block text-10 font-bold text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="lg:col-span-3">
                    <span class="flex items-center gap-4 text-10 font-extrabold uppercase text-gray">Gruppo quantità <span class="normal-case font-semibold">(facoltativo)</span> <span class="group relative inline-flex normal-case"><span tabindex="0" aria-label="Informazioni sul gruppo quantità" class="flex h-16 w-16 cursor-help items-center justify-center rounded-full border border-gray text-10 font-black leading-none text-gray focus:border-bullstar focus:text-bullstar">i</span><span role="tooltip" class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-5 hidden w-[230px] -translate-x-1/2 rounded-10 bg-black-nike px-8 py-6 text-center text-10 font-semibold normal-case leading-[14px] text-white shadow-lg group-hover:block group-focus-within:block">Somma le varianti per scegliere la fascia costo/prezzo corretta.</span></span></span>
                    <select wire:model.live="pricingGroupItemId" class="mt-4 w-full rounded-10 border-gray-mid bg-white px-10 py-8 text-12 font-semibold focus:border-bullstar focus:ring-bullstar">
                        <option value="">Nuovo gruppo autonomo</option>
                        @foreach(($salesSheet?->items ?? collect())->where('crm_product_id', (int) $productId)->unique('pricing_group_uuid') as $groupItem)
                            <option value="{{ $groupItem->id }}">Unisci a {{ $groupItem->pricing_group_name ?: $groupItem->configuration_name ?: $groupItem->product_name }} ({{ number_format((float)($salesSheet?->items?->where('pricing_group_uuid', $groupItem->pricing_group_uuid)->sum('quantity') ?? 0), 0, ',', '.') }} pz)</option>
                        @endforeach
                    </select>
                </label>
                <label class="lg:col-span-2">
                    <span class="text-10 font-extrabold uppercase text-gray">Quantità</span>
                    <input wire:model.live.debounce.300ms="quantity" required type="number" min="0.01" step="0.01" placeholder="0" class="mt-4 w-full rounded-10 border-gray-mid bg-white px-10 py-8 text-12 font-semibold focus:border-bullstar focus:ring-bullstar">
                    @error('quantity')<span class="mt-4 block text-10 font-bold text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="lg:col-span-2">
                    <span class="text-10 font-extrabold uppercase text-gray">Prezzo finale / pz</span>
                    <div class="mt-4 flex overflow-hidden rounded-10 border border-gray-mid bg-white focus-within:border-bullstar focus-within:ring-1 focus-within:ring-bullstar">
                        <span class="flex shrink-0 items-center border-r border-gray-mid bg-gray-light px-10 text-12 font-bold text-gray">€</span>
                        <input wire:model="finalUnitPrice" required type="number" min="0" step="0.01" placeholder="0,00" class="min-w-0 flex-1 border-0 bg-white px-10 py-8 text-12 font-semibold focus:border-0 focus:ring-0">
                    </div>
                    @error('finalUnitPrice')<span class="mt-4 block text-10 font-bold text-red-600">{{ $message }}</span>@enderror
                </label>
                <button type="submit" wire:loading.attr="disabled" wire:target="addProduct" class="min-h-[40px] rounded-10 bg-black-nike px-10 text-10 font-extrabold uppercase text-white transition hover:bg-bullstar disabled:opacity-50 lg:col-span-1">
                    <span wire:loading.remove wire:target="addProduct">Aggiungi</span><span wire:loading wire:target="addProduct">…</span>
                </button>
            </form>
        </section>

        <div class="mt-12 grid items-start gap-12 {{ $salesSheet?->items?->isNotEmpty() ? 'lg:grid-cols-[minmax(0,1fr)_320px]' : 'grid-cols-1' }}">
            {{-- Elenco prodotti --}}
            <main class="min-w-0 space-y-10">
                @forelse($salesSheet?->items ?? [] as $item)
                    @php($groupQuantity = ($salesSheet?->items ?? collect())->where('pricing_group_uuid', $item->pricing_group_uuid)->sum('quantity'))
                    <details wire:key="sales-item-{{ $item->id }}" open class="group overflow-hidden rounded-10 border border-gray-mid bg-white shadow-sm">
                        <summary class="cursor-pointer list-none border-b border-gray-mid bg-gray-light px-10 py-8 sm:px-12">
                            <div class="flex flex-col gap-8 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex min-w-0 items-center gap-8">
                                    <span class="flex h-28 w-28 shrink-0 items-center justify-center rounded-full bg-black-nike text-10 font-black text-white">{{ $loop->iteration }}</span>
                                    <div class="min-w-0">
                                        <h3 class="truncate text-14 font-black">{{ $item->configuration_name ?: $item->product_name }}</h3>
                                        <p class="mt-3 text-10 font-bold text-gray">{{ $item->product_code }} · {{ $item->product_name }} · {{ number_format((float)$item->quantity, 2, ',', '.') }} pz @if($groupQuantity != $item->quantity) · fascia calcolata su {{ number_format((float)$groupQuantity, 0, ',', '.') }} pz @endif</p>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between gap-10 sm:justify-end">
                                    <div class="text-left sm:text-right">
                                        <p class="text-10 font-extrabold uppercase text-gray">Totale prodotto</p>
                                        <p class="mt-2 text-16 font-black">€ {{ number_format((float)$item->revenue_total, 2, ',', '.') }}</p>
                                    </div>
                                    <span class="text-16 font-black text-gray transition group-open:rotate-180">⌄</span>
                                    <button type="button" wire:click.stop="removeProduct({{ $item->id }})" wire:confirm="Rimuovere il prodotto?" wire:loading.attr="disabled" title="Rimuovi prodotto" class="flex h-28 w-28 items-center justify-center rounded-full border border-red-200 bg-white text-14 font-black text-red-600 transition hover:bg-red-50 disabled:opacity-50">×</button>
                                </div>
                            </div>
                        </summary>

                        <div class="grid lg:grid-cols-[minmax(0,1.2fr)_minmax(280px,.8fr)]">
                            {{-- Produzione --}}
                            <form wire:submit="saveItemDetails({{ $item->id }})" class="min-w-0 p-10 sm:p-12 lg:border-r lg:border-gray-mid">
                                <div class="flex items-center justify-between gap-6">
                                    <div>
                                        <p class="text-11 font-black uppercase">Dettagli produzione</p>
                                        <p class="mt-3 text-10 font-semibold text-gray">Informazioni e materiali per lavorare il prodotto.</p>
                                    </div>
                                    @if($item->attachments->isNotEmpty())
                                        <span class="shrink-0 rounded-full bg-bullstar/10 px-8 py-4 text-10 font-extrabold text-bullstar">{{ $item->attachments->count() }} file</span>
                                    @endif
                                </div>

                                <div class="mt-8 grid gap-8 sm:grid-cols-2">
                                    <label>
                                        <span class="text-10 font-extrabold uppercase text-gray">Colori e quantità</span>
                                        <textarea wire:model="itemColors.{{ $item->id }}" rows="4" maxlength="2000" placeholder="Blu navy — 50 pz&#10;Bianco — 30 pz" class="mt-4 w-full rounded-10 border-gray-mid bg-white px-10 py-8 text-11 font-semibold focus:border-bullstar focus:ring-bullstar"></textarea>
                                        @error('itemColors.'.$item->id)<span class="mt-4 block text-10 font-bold text-red-600">{{ $message }}</span>@enderror
                                    </label>
                                    <label>
                                        <span class="text-10 font-extrabold uppercase text-gray">Note operative</span>
                                        <textarea wire:model="itemNotes.{{ $item->id }}" rows="4" maxlength="10000" placeholder="Posizione stampa, riferimenti, urgenze…" class="mt-4 w-full rounded-10 border-gray-mid bg-white px-10 py-8 text-11 font-semibold focus:border-bullstar focus:ring-bullstar"></textarea>
                                        @error('itemNotes.'.$item->id)<span class="mt-4 block text-10 font-bold text-red-600">{{ $message }}</span>@enderror
                                    </label>
                                </div>

                                <label class="mt-8 block cursor-pointer rounded-10 border border-dashed border-gray-mid bg-gray-light px-10 py-8 transition hover:border-bullstar hover:bg-bullstar/5">
                                    <span class="flex items-center gap-8">
                                        <span class="flex h-28 w-28 shrink-0 items-center justify-center rounded-full bg-white text-16 font-black text-bullstar">+</span>
                                        <span>
                                            <span class="block text-10 font-extrabold uppercase">Aggiungi file grafici</span>
                                            <span class="mt-2 block text-10 font-semibold text-gray">PDF, AI, EPS, SVG, PSD, PNG, JPG o ZIP · max 10 MB</span>
                                        </span>
                                    </span>
                                    <input wire:model="itemUploads.{{ $item->id }}" type="file" multiple accept=".pdf,.ai,.eps,.svg,.psd,.png,.jpg,.jpeg,.zip" class="sr-only">
                                    @error('itemUploads.'.$item->id)<span class="mt-4 block text-10 font-bold text-red-600">{{ $message }}</span>@enderror
                                    @error('itemUploads.'.$item->id.'.*')<span class="mt-4 block text-10 font-bold text-red-600">{{ $message }}</span>@enderror
                                </label>

                                <label class="mt-8 block cursor-pointer rounded-10 border border-dashed border-gray-mid bg-gray-light px-10 py-8 transition hover:border-bullstar hover:bg-bullstar/5">
                                    <span class="flex items-center gap-8">
                                        <span class="flex h-28 w-28 shrink-0 items-center justify-center rounded-full bg-white text-16 font-black text-bullstar">↕</span>
                                        <span>
                                            <span class="block text-10 font-extrabold uppercase">Tabella taglie del prodotto</span>
                                            <span class="mt-2 block text-10 font-semibold text-gray">JPG o PNG · max 20 MB @if($item->size_chart_path) · già caricata @endif</span>
                                        </span>
                                    </span>
                                    <input wire:model="itemSizeCharts.{{ $item->id }}" type="file" accept=".png,.jpg,.jpeg,image/png,image/jpeg" class="sr-only">
                                    @error('itemSizeCharts.'.$item->id)<span class="mt-4 block text-10 font-bold text-red-600">{{ $message }}</span>@enderror
                                </label>

                                @if($item->attachments->isNotEmpty())
                                    <div class="mt-8 grid gap-4 sm:grid-cols-2">
                                        @foreach($item->attachments as $attachment)
                                            <div class="flex min-w-0 items-center justify-between gap-5 rounded-10 border border-gray-mid px-8 py-6">
                                                <span class="truncate text-10 font-bold" title="{{ $attachment->filename }}">{{ $attachment->filename }}</span>
                                                <button type="button" wire:click="removeAttachment({{ $item->id }}, {{ $attachment->id }})" wire:confirm="Rimuovere questo file?" class="shrink-0 text-13 font-black text-red-600" title="Rimuovi file">×</button>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="mt-8 flex flex-wrap items-center justify-between gap-6">
                                    <p class="text-10 font-semibold text-gray">Questi dati vengono salvati anche automaticamente all'invio.</p>
                                    <button type="submit" wire:loading.attr="disabled" wire:target="saveItemDetails({{ $item->id }})" class="rounded-10 border border-black-nike bg-white px-12 py-8 text-10 font-extrabold uppercase transition hover:bg-black-nike hover:text-white disabled:opacity-50">
                                        <span wire:loading.remove wire:target="saveItemDetails({{ $item->id }})">Salva dettagli</span>
                                        <span wire:loading wire:target="saveItemDetails({{ $item->id }})">Salvataggio…</span>
                                    </button>
                                </div>
                            </form>

                            {{-- Prezzo e lavorazioni --}}
                            <div class="min-w-0 bg-gray-light/50 p-10 sm:p-12">
                                <p class="text-11 font-black uppercase">Prezzo e lavorazioni</p>
                                <div class="mt-8 grid grid-cols-3 gap-4">
                                    <div class="rounded-10 bg-white px-6 py-6 text-center">
                                        <p class="text-10 font-extrabold uppercase text-gray">Base</p>
                                        <p class="mt-2 text-11 font-black">€ {{ number_format((float)$item->product_unit_price, 2, ',', '.') }}</p>
                                    </div>
                                    <div class="rounded-10 bg-white px-6 py-6 text-center">
                                        <p class="text-10 font-extrabold uppercase text-gray">Costo</p>
                                        <p class="mt-2 text-11 font-black">€ {{ number_format((float)$item->cost_total, 2, ',', '.') }}</p>
                                    </div>
                                    <div class="rounded-10 bg-bullstar/10 px-6 py-6 text-center">
                                        <p class="text-10 font-extrabold uppercase text-bullstar">Margine</p>
                                        <p class="mt-2 text-11 font-black text-bullstar">€ {{ number_format((float)$item->margin_total, 2, ',', '.') }}</p>
                                    </div>
                                </div>

                                <form wire:submit="updateFinalPrice({{ $item->id }})" class="mt-8">
                                    <label>
                                        <span class="flex items-center justify-between gap-4 text-10 font-extrabold uppercase text-gray">
                                            Prezzo finale / pz
                                            @if($item->final_price_overridden)<span class="rounded-full bg-amber-100 px-6 py-4 text-10 text-amber-700">Manuale</span>@else<span class="rounded-full bg-bullstar/10 px-6 py-4 text-10 text-bullstar">Automatico</span>@endif
                                        </span>
                                        <div class="mt-4 flex overflow-hidden rounded-10 border border-gray-mid bg-white focus-within:border-bullstar focus-within:ring-1 focus-within:ring-bullstar">
                                            <span class="flex shrink-0 items-center border-r border-gray-mid bg-gray-light px-10 text-13 font-black">€</span>
                                            <input wire:model="itemFinalPrices.{{ $item->id }}" required type="number" min="0" step="0.01" class="min-w-0 flex-1 border-0 bg-white px-10 py-8 text-14 font-black focus:border-0 focus:ring-0">
                                        </div>
                                        @error('itemFinalPrices.'.$item->id)<span class="mt-4 block text-10 font-bold text-red-600">{{ $message }}</span>@enderror
                                    </label>
                                    <div class="mt-5 flex gap-5">
                                        <button type="submit" wire:loading.attr="disabled" wire:target="updateFinalPrice({{ $item->id }})" class="flex-1 rounded-10 bg-black-nike px-8 py-8 text-10 font-extrabold uppercase text-white disabled:opacity-50">Aggiorna prezzo</button>
                                        @if($item->final_price_overridden)
                                            <button type="button" wire:click="resetFinalPrice({{ $item->id }})" wire:loading.attr="disabled" title="Ripristina prezzo automatico" class="rounded-10 border border-gray-mid bg-white px-8 py-8 text-10 font-extrabold uppercase">Ripristina</button>
                                        @endif
                                    </div>
                                </form>

                                <div class="mt-10 border-t border-gray-mid pt-9">
                                    <p class="text-10 font-extrabold uppercase text-gray">Lavorazioni</p>
                                    @if($item->prints->isNotEmpty())
                                        <div class="mt-5 space-y-4">
                                            @foreach($item->prints as $print)
                                                <div wire:key="sales-print-{{ $print->id }}" class="flex items-center justify-between gap-5 rounded-10 border border-gray-mid bg-white px-8 py-6">
                                                    <span class="min-w-0 truncate text-10 font-bold">{{ $print->print_name }}</span>
                                                    <span class="flex shrink-0 items-center gap-5 text-10 font-black">€ {{ number_format((float)$print->unit_price, 2, ',', '.') }} <button type="button" wire:click="removePrint({{ $item->id }}, {{ $print->id }})" wire:loading.attr="disabled" title="Rimuovi lavorazione" class="text-13 text-red-600">×</button></span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="mt-5 rounded-10 border border-dashed border-gray-mid px-8 py-8 text-center text-10 font-semibold text-gray">Nessuna lavorazione aggiunta</p>
                                    @endif

                                    <form wire:submit="addPrint({{ $item->id }})" class="mt-6 flex gap-5">
                                        <select wire:model="printTypeIds.{{ $item->id }}" required aria-label="Lavorazione per {{ $item->configuration_name ?: $item->product_name }}" class="min-w-0 flex-1 rounded-10 border-gray-mid bg-white px-8 py-8 text-10 font-semibold focus:border-bullstar focus:ring-bullstar">
                                            <option value="">Aggiungi lavorazione…</option>
                                            @foreach($printTypes as $type)<option value="{{ $type->id }}">{{ $type->code }} · {{ $type->name }}</option>@endforeach
                                        </select>
                                        <button type="submit" wire:loading.attr="disabled" wire:target="addPrint({{ $item->id }})" class="rounded-10 bg-white px-8 py-8 text-14 font-black shadow-sm disabled:opacity-50" title="Aggiungi lavorazione">+</button>
                                    </form>
                                    @error('printTypeIds.'.$item->id)<span class="mt-4 block text-10 font-bold text-red-600">{{ $message }}</span>@enderror
                                </div>
                            </div>
                        </div>
                    </details>
                @empty
                    <div class="rounded-10 border border-dashed border-gray-mid bg-gray-light px-12 py-20 text-center">
                        <span class="mx-auto flex h-36 w-36 items-center justify-center rounded-full bg-white text-20 font-black text-gray">+</span>
                        <p class="mt-8 text-13 font-black">Nessun prodotto nell'ordine</p>
                        <p class="mt-3 text-10 font-semibold text-gray">Usa il modulo in alto per aggiungere il primo prodotto.</p>
                    </div>
                @endforelse
            </main>

            {{-- Invio ordine --}}
            @if($salesSheet?->items?->isNotEmpty())
                <aside class="space-y-8 lg:sticky lg:top-16">
                    <form wire:submit="sendOrder" class="rounded-10 border border-gray-mid bg-gray-light p-10">
                        <div class="flex h-32 w-32 items-center justify-center rounded-full bg-bullstar text-14 font-black text-white">→</div>
                        <p class="mt-8 text-13 font-black">Invia l'ordine</p>
                        <p class="mt-3 text-10 font-semibold leading-relaxed text-gray">Crea lo ZIP con riepilogo, colori e grafiche e lo invia ad Alessandro.</p>

                        <label class="mt-8 block">
                            <span class="text-10 font-extrabold uppercase text-gray">Nome ordine</span>
                            <input wire:model="orderName" required type="text" maxlength="100" placeholder="Es. Staff Festival Roma" class="mt-4 w-full rounded-10 border-gray-mid bg-white px-10 py-8 text-11 font-semibold focus:border-bullstar focus:ring-bullstar">
                            @error('orderName')<span class="mt-4 block text-10 font-bold text-red-600">{{ $message }}</span>@enderror
                        </label>

                        <div class="mt-8 rounded-10 bg-white px-8 py-8 text-10 font-semibold leading-relaxed text-gray">
                            <span class="font-extrabold text-black-nike">Destinatario</span><br>
                            Alessandro · alessandro@stuart-company.com<br>
                            <span class="font-extrabold text-black-nike">CC</span> Daniele · daniele.dallavia@gmail.com
                        </div>

                        <button type="submit" wire:confirm="Inviare l'ordine completo ad Alessandro?" wire:loading.attr="disabled" wire:target="sendOrder,itemUploads" class="mt-8 w-full rounded-10 bg-bullstar px-10 py-10 text-10 font-extrabold uppercase text-white transition hover:bg-bullstar-hover disabled:opacity-50">
                            <span wire:loading.remove wire:target="sendOrder">Crea ZIP e invia</span>
                            <span wire:loading wire:target="sendOrder">Preparazione…</span>
                        </button>
                        <p class="mt-5 text-center text-10 font-semibold text-gray">I dati non ancora salvati vengono inclusi automaticamente.</p>
                    </form>

                    @if($salesSheet->dispatches->isNotEmpty())
                        <div class="rounded-10 border border-gray-mid bg-white p-8" wire:poll.5s>
                            <p class="text-10 font-extrabold uppercase text-gray">Ultimi invii</p>
                            <div class="mt-6 space-y-6">
                                @foreach($salesSheet->dispatches->sortByDesc('id')->take(4) as $dispatch)
                                    <div class="border-b border-gray-mid pb-6 last:border-0 last:pb-0">
                                        <div class="flex items-start justify-between gap-5">
                                            <span class="min-w-0 truncate text-10 font-bold" title="{{ $dispatch->filename }}">{{ $dispatch->filename }}</span>
                                            <span class="shrink-0 rounded-full px-5 py-4 text-10 font-extrabold {{ $dispatch->status === 'sent' ? 'bg-green-100 text-green-700' : ($dispatch->status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">{{ $dispatch->status === 'sent' ? 'Inviato' : ($dispatch->status === 'failed' ? 'Fallito' : 'In corso') }}</span>
                                        </div>
                                        <p class="mt-3 text-10 font-semibold text-gray">{{ ($dispatch->sent_at ?: $dispatch->created_at)->format('d/m/Y H:i') }}</p>
                                        @if($dispatch->status === 'failed' && $dispatch->error_message)<p class="mt-3 text-10 font-semibold text-red-600">{{ $dispatch->error_message }}</p>@endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </aside>
            @endif
        </div>
    </div>
</div>
