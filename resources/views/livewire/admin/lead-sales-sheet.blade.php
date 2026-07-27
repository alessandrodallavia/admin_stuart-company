<div data-crm-sales class="overflow-hidden rounded-10 border border-gray-mid bg-white">
    {{-- Header e indicatori economici --}}
    <header class="border-b border-gray-mid bg-black-nike px-12 py-12 text-white sm:px-16 sm:py-12">
        <div class="flex flex-col gap-10 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-10 font-extrabold uppercase tracking-wider text-white/60">Scheda prodotto</p>
                <h2 class="mt-4 text-18 font-black">Configura prodotti e materiali dell'ordine</h2>
                <p class="mt-4 max-w-2xl text-11 font-semibold text-white/60">Prezzi, lavorazioni, colori e grafiche in un unico flusso.</p>
            </div>

            <div class="grid grid-cols-2 gap-6 sm:grid-cols-4 lg:min-w-[520px]">
                <div class="rounded-10 bg-white/10 px-8 py-8">
                    <p class="text-10 font-extrabold uppercase text-white/50">Vendita</p>
                    <p class="mt-3 text-16 font-black">€ {{ number_format((float)($salesSheet?->revenue_total ?? 0), 2, ',', '.') }}</p>
                </div>
                <div class="rounded-10 bg-white/10 px-8 py-8">
                    <p class="text-10 font-extrabold uppercase text-white/50">Costo</p>
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
                <label class="lg:col-span-4">
                    <span class="text-10 font-extrabold uppercase text-gray">Prodotto</span>
                    <select wire:model.live="productId" required class="mt-4 w-full rounded-10 border-gray-mid bg-white px-10 py-8 text-12 font-semibold focus:border-bullstar focus:ring-bullstar">
                        <option value="">Seleziona dal catalogo</option>
                        @foreach($products as $product)<option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>@endforeach
                    </select>
                    @error('productId')<span class="mt-4 block text-10 font-bold text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="lg:col-span-3">
                    <span class="text-10 font-extrabold uppercase text-gray">Nome configurazione <span class="normal-case font-semibold">(facoltativo)</span></span>
                    <input wire:model="configurationName" type="text" maxlength="255" placeholder="Es. Maglia staff evento" class="mt-4 w-full rounded-10 border-gray-mid bg-white px-10 py-8 text-12 font-semibold focus:border-bullstar focus:ring-bullstar">
                    @error('configurationName')<span class="mt-4 block text-10 font-bold text-red-600">{{ $message }}</span>@enderror
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

        <div class="mt-12 grid items-start gap-12 lg:grid-cols-[minmax(0,1fr)_320px]">
            {{-- Elenco prodotti --}}
            <main class="min-w-0 space-y-10">
                @forelse($salesSheet?->items ?? [] as $item)
                    <article wire:key="sales-item-{{ $item->id }}" class="overflow-hidden rounded-10 border border-gray-mid bg-white shadow-sm">
                        <div class="border-b border-gray-mid bg-gray-light px-10 py-8 sm:px-12">
                            <div class="flex flex-col gap-8 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex min-w-0 items-center gap-8">
                                    <span class="flex h-28 w-28 shrink-0 items-center justify-center rounded-full bg-black-nike text-10 font-black text-white">{{ $loop->iteration }}</span>
                                    <div class="min-w-0">
                                        <h3 class="truncate text-14 font-black">{{ $item->configuration_name ?: $item->product_name }}</h3>
                                        <p class="mt-3 text-10 font-bold text-gray">{{ $item->product_code }} · {{ $item->product_name }} · {{ number_format((float)$item->quantity, 2, ',', '.') }} pz</p>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between gap-10 sm:justify-end">
                                    <div class="text-left sm:text-right">
                                        <p class="text-10 font-extrabold uppercase text-gray">Totale prodotto</p>
                                        <p class="mt-2 text-16 font-black">€ {{ number_format((float)$item->revenue_total, 2, ',', '.') }}</p>
                                    </div>
                                    <button type="button" wire:click="removeProduct({{ $item->id }})" wire:confirm="Rimuovere il prodotto?" wire:loading.attr="disabled" title="Rimuovi prodotto" class="flex h-28 w-28 items-center justify-center rounded-full border border-red-200 bg-white text-14 font-black text-red-600 transition hover:bg-red-50 disabled:opacity-50">×</button>
                                </div>
                            </div>
                        </div>

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
                    </article>
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
