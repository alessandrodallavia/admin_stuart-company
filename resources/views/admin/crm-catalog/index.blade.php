@extends('admin.layouts.app')
@section('title', 'Catalogo CRM - Stuart Admin')
@section('page_title', 'Catalogo CRM')
@section('active_nav', 'crm-catalog')

@section('content')
<div data-crm-catalog class="space-y-12">
    <section class="rounded-10 border border-gray-mid bg-white">
        <div class="border-b border-gray-mid px-12 py-10">
            <p class="text-10 font-extrabold uppercase tracking-normal text-gray">Classificazione lead</p>
            <h2 class="mt-4 text-18 font-black">Categorie</h2>
        </div>
        <div class="grid gap-12 p-12 lg:grid-cols-[420px_minmax(0,1fr)] lg:items-start">
            <form method="POST" action="{{ route('admin.crm-catalog.categories.store') }}" class="grid gap-8 rounded-10 border border-gray-mid bg-gray-light p-10">@csrf
                <label><span class="text-10 font-extrabold uppercase text-gray">Nome categoria</span><input name="name" required placeholder="Es. Squadre sportive" class="mt-4 w-full bg-white"></label>
                <label>
                    <span class="text-10 font-extrabold uppercase text-gray">Ordine di visualizzazione</span>
                    <input name="sort_order" type="number" min="0" value="0" class="mt-4 w-full bg-white">
                    <span class="mt-4 block text-10 font-semibold leading-[14px] text-gray">Le categorie con il numero più basso vengono mostrate per prime nel menu del lead.</span>
                </label>
                <button class="rounded-10 bg-black-nike px-12 text-10 font-extrabold uppercase text-white">Aggiungi categoria</button>
            </form>
            <div class="grid gap-6">
                @error('category')<div class="rounded-10 border border-red-200 bg-red-50 px-10 py-8 text-11 font-bold text-red-700">{{ $message }}</div>@enderror
                @forelse($categories as $category)
                    <div class="flex flex-wrap items-center justify-between gap-8 rounded-10 border border-gray-mid px-10 py-8 {{ $category->is_active ? 'bg-white' : 'bg-gray-light opacity-70' }}">
                        <div class="min-w-0">
                            <p class="truncate text-12 font-bold">{{ $category->name }}</p>
                            <p class="mt-3 text-10 font-semibold text-gray">Ordine: {{ $category->sort_order }} · {{ $category->is_active ? 'Attiva' : 'Disattivata' }}</p>
                        </div>
                        <div class="flex items-center gap-5">
                            <form method="POST" action="{{ route('admin.crm-catalog.categories.toggle', $category) }}">@csrf @method('PATCH')
                                <button class="rounded-10 border border-black-nike px-8 text-10 font-extrabold uppercase">{{ $category->is_active ? 'Disattiva' : 'Riattiva' }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.crm-catalog.categories.destroy', $category) }}" onsubmit="return confirm('Eliminare definitivamente questa categoria?')">@csrf @method('DELETE')
                                <button class="rounded-10 border border-red-600 px-8 text-10 font-extrabold uppercase text-red-600">Elimina</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-12 font-semibold text-gray">Nessuna categoria configurata.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="rounded-10 border border-gray-mid bg-white">
        <div class="border-b border-gray-mid px-12 py-10">
            <p class="text-10 font-extrabold uppercase tracking-normal text-gray">Listino articoli</p>
            <h2 class="mt-4 text-18 font-black">Prodotti</h2>
        </div>
        <div class="p-12">
            <form method="POST" action="{{ route('admin.crm-catalog.products.store') }}" class="grid gap-6 rounded-10 border border-gray-mid bg-gray-light p-10 md:grid-cols-[150px_minmax(220px,1fr)_150px_auto] md:items-end">@csrf
                <label><span class="text-10 font-extrabold uppercase text-gray">Codice</span><input name="code" required placeholder="TS01" class="mt-4 w-full bg-white"></label>
                <label><span class="text-10 font-extrabold uppercase text-gray">Nome prodotto</span><input name="name" required placeholder="T-shirt Premium" class="mt-4 w-full bg-white"></label>
                <label><span class="text-10 font-extrabold uppercase text-gray">Costo unitario</span><input name="unit_cost" required type="number" min="0" step="0.01" placeholder="0,00" class="mt-4 w-full bg-white"></label>
                <button class="rounded-10 bg-black-nike px-12 text-10 font-extrabold uppercase text-white">Salva prodotto</button>
            </form>

            <div class="mt-12 grid gap-10 lg:grid-cols-2">
                @forelse($products as $product)
                    <article class="rounded-10 border border-gray-mid p-10">
                        <div class="flex flex-wrap items-start justify-between gap-8">
                            <div><p class="text-14 font-black">{{ $product->code }} · {{ $product->name }}</p><p class="mt-4 text-11 font-bold text-gray">Costo unitario € {{ number_format((float)$product->unit_cost, 2, ',', '.') }}</p></div>
                            <span class="rounded-full bg-gray-light px-8 py-5 text-10 font-extrabold uppercase text-gray">{{ $product->priceTiers->count() }} fasce</span>
                        </div>
                        <div class="mt-8 overflow-hidden rounded-10 border border-gray-mid">
                            @forelse($product->priceTiers as $tier)
                                <div class="flex items-center justify-between border-b border-gray-mid px-8 py-6 text-11 font-bold last:border-b-0"><span>{{ number_format((float)$tier->min_quantity, 2, ',', '.') }} – {{ $tier->max_quantity !== null ? number_format((float)$tier->max_quantity, 2, ',', '.') : '∞' }}</span><span>€ {{ number_format((float)$tier->unit_price, 2, ',', '.') }}</span></div>
                            @empty <p class="px-8 py-6 text-11 font-semibold text-gray">Nessuna fascia prezzo.</p>@endforelse
                        </div>
                        <form method="POST" action="{{ route('admin.crm-catalog.products.tiers.store', $product) }}" class="mt-8 grid grid-cols-3 gap-5">@csrf
                            <input name="min_quantity" required type="number" min="0.01" step="0.01" placeholder="Quantità da"><input name="max_quantity" type="number" step="0.01" placeholder="Quantità a"><input name="unit_price" required type="number" min="0" step="0.01" placeholder="Prezzo €"><button class="col-span-3 rounded-10 border border-black-nike px-10 text-10 font-extrabold uppercase">Aggiungi fascia prezzo</button>
                        </form>
                    </article>
                @empty
                    <p class="text-12 font-semibold text-gray">Nessun prodotto configurato.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="rounded-10 border border-gray-mid bg-white">
        <div class="border-b border-gray-mid px-12 py-10">
            <p class="text-10 font-extrabold uppercase tracking-normal text-gray">Listino lavorazioni</p>
            <h2 class="mt-4 text-18 font-black">Stampe</h2>
        </div>
        <div class="p-12">
            <form method="POST" action="{{ route('admin.crm-catalog.prints.store') }}" class="grid gap-6 rounded-10 border border-gray-mid bg-gray-light p-10 md:grid-cols-[150px_minmax(220px,1fr)_auto] md:items-end">@csrf
                <label><span class="text-10 font-extrabold uppercase text-gray">Codice</span><input name="code" required placeholder="CUORE1" class="mt-4 w-full bg-white"></label>
                <label><span class="text-10 font-extrabold uppercase text-gray">Descrizione stampa</span><input name="name" required placeholder="Lato cuore 1 colore" class="mt-4 w-full bg-white"></label>
                <button class="rounded-10 bg-black-nike px-12 text-10 font-extrabold uppercase text-white">Salva stampa</button>
            </form>

            <div class="mt-12 space-y-10">
                @forelse($printTypes as $printType)
                    <article class="overflow-hidden rounded-10 border border-gray-mid">
                        <div class="flex flex-wrap items-center justify-between gap-8 border-b border-gray-mid bg-gray-light px-10 py-8">
                            <div class="flex min-w-0 items-center gap-8">
                                <span class="shrink-0 rounded-10 bg-black-nike px-8 py-5 text-10 font-extrabold uppercase text-white">{{ $printType->code }}</span>
                                <p class="truncate text-14 font-black">{{ $printType->name }}</p>
                            </div>
                            <span class="text-10 font-extrabold uppercase text-gray">{{ $printType->priceTiers->count() }} {{ $printType->priceTiers->count() === 1 ? 'fascia' : 'fasce' }}</span>
                        </div>

                        <div class="grid lg:grid-cols-[minmax(0,3fr)_minmax(340px,2fr)]">
                            <div class="p-10 lg:order-2 lg:border-l lg:border-gray-mid">
                                <p class="mb-6 text-10 font-extrabold uppercase text-gray">Fasce configurate</p>
                                <div class="overflow-hidden rounded-10 border border-gray-mid">
                                    <div class="grid grid-cols-3 bg-gray-light px-8 py-6 text-10 font-extrabold uppercase text-gray">
                                        <span>Quantità</span><span class="text-center">Costo unitario</span><span class="text-right">Prezzo vendita</span>
                                    </div>
                                    @forelse($printType->priceTiers as $tier)
                                        <div class="grid grid-cols-3 items-center border-t border-gray-mid px-8 py-6 text-11 font-bold">
                                            <span>{{ number_format((float)$tier->min_quantity, 2, ',', '.') }} – {{ $tier->max_quantity !== null ? number_format((float)$tier->max_quantity, 2, ',', '.') : '∞' }}</span>
                                            <span class="text-center">€ {{ number_format((float)$tier->unit_cost, 2, ',', '.') }}</span>
                                            <span class="text-right">€ {{ number_format((float)$tier->unit_price, 2, ',', '.') }}</span>
                                        </div>
                                    @empty
                                        <p class="border-t border-gray-mid px-8 py-8 text-11 font-semibold text-gray">Nessuna fascia configurata.</p>
                                    @endforelse
                                </div>
                            </div>

                            <form method="POST" action="{{ route('admin.crm-catalog.prints.tiers.store', $printType) }}" class="border-t border-gray-mid bg-gray-light p-10 lg:order-1 lg:border-t-0">@csrf
                                <p class="mb-6 text-10 font-extrabold uppercase text-gray">Nuova fascia</p>
                                <div class="grid grid-cols-2 gap-6">
                                    <label><span class="text-10 font-bold text-gray">Quantità da</span><input name="min_quantity" required type="number" min="0.01" step="0.01" placeholder="0,00" class="mt-4 w-full bg-white"></label>
                                    <label><span class="text-10 font-bold text-gray">Quantità a</span><input name="max_quantity" type="number" step="0.01" placeholder="Senza limite" class="mt-4 w-full bg-white"></label>
                                    <label><span class="text-10 font-bold text-gray">Costo unitario €</span><input name="unit_cost" required type="number" min="0" step="0.01" placeholder="0,00" class="mt-4 w-full bg-white"></label>
                                    <label><span class="text-10 font-bold text-gray">Prezzo vendita €</span><input name="unit_price" required type="number" min="0" step="0.01" placeholder="0,00" class="mt-4 w-full bg-white"></label>
                                </div>
                                <button class="mt-8 w-full rounded-10 bg-black-nike px-10 text-10 font-extrabold uppercase text-white">Aggiungi fascia</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <p class="text-12 font-semibold text-gray">Nessuna stampa configurata.</p>
                @endforelse
            </div>
        </div>
    </section>
</div>
@endsection
