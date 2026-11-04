@extends('admin.layouts.app')
@section('title', 'Catalogo CRM - Stuart Admin')
@section('page_title', 'Catalogo CRM')
@section('active_nav', 'crm-catalog')

@section('content')
@php
    $canManageCatalog = auth('admin')->user()?->hasAdminPermission('crm_catalog.manage');
    $catalogTabs = ['products' => ['Prodotti', $products->count()], 'prints' => ['Stampe', $printTypes->count()], 'brands' => ['Brand', $brands->count()], 'suppliers' => ['Fornitori', $suppliers->count()], 'categories' => ['Categorie lead', $categories->count()]];
    $catalogSection = old('catalog_section', request('section', 'products'));
    if (!is_string($catalogSection) || !array_key_exists($catalogSection, $catalogTabs)) $catalogSection = 'products';
    $restoreDialog = old('catalog_dialog', '');
    if (!is_string($restoreDialog)) $restoreDialog = '';
@endphp
<div data-crm-catalog data-restore-dialog="{{ $restoreDialog }}" class="catalog-workspace">
    <header class="catalog-page-header">
        <div><p class="catalog-eyebrow">Vendite e anagrafiche</p><h1>Catalogo commerciale</h1><p>Gestisci prodotti, listini e fornitori da un unico spazio.</p></div>
        <span class="catalog-tax-note">Costi e prezzi IVA esclusa</span>
    </header>

    <div class="catalog-surface">
        <nav class="catalog-tabs" role="tablist" aria-label="Sezioni del catalogo">
            @foreach($catalogTabs as $key => [$label, $count])
                <button type="button" id="catalog-tab-{{ $key }}" role="tab" data-catalog-tab="catalog-panel-{{ $key }}" aria-controls="catalog-panel-{{ $key }}" aria-selected="{{ $catalogSection === $key ? 'true' : 'false' }}" tabindex="{{ $catalogSection === $key ? '0' : '-1' }}">{{ $label }}<span>{{ $count }}</span></button>
            @endforeach
        </nav>

        <section id="catalog-panel-products" data-catalog-panel role="tabpanel" aria-labelledby="catalog-tab-products" @if($catalogSection !== 'products') hidden @endif>
            <div class="catalog-section-heading"><div><h2>Prodotti</h2><p>Consulta l’anagrafica o apri la scheda per modificare dettagli e prezzi.</p></div>@if($canManageCatalog)<button type="button" data-catalog-open="product-new" class="catalog-button catalog-button-primary"><span aria-hidden="true">＋</span> Nuovo prodotto</button>@endif</div>
            <div class="catalog-toolbar">
                <form method="GET" class="catalog-search" role="search"><input type="hidden" name="section" value="{{ $catalogSection }}"><input name="q" value="{{ $search }}" aria-label="Cerca nel catalogo" placeholder="Cerca codice, prodotto, brand o fornitore"><button class="catalog-button catalog-button-secondary">Cerca</button>@if($search)<a href="{{ route('admin.crm-catalog.index', ['section' => 'products']) }}" class="catalog-text-button">Azzera</a>@endif</form>
                <div class="catalog-filters"><select aria-label="Filtra per brand" data-catalog-brand-filter><option value="">Tutti i brand</option>@foreach($brands as $brand)<option value="{{ $brand->id }}">{{ $brand->name }}</option>@endforeach</select><select aria-label="Filtra per visibilità" data-catalog-visibility-filter><option value="">Ogni visibilità</option><option value="visible">Visibilità abilitata</option><option value="hidden">Nascosti sul sito</option></select></div>
            </div>
            <div class="catalog-list-caption"><span data-catalog-product-count aria-live="polite" aria-atomic="true">{{ $products->count() }} prodotti</span><span>Importi unitari · IVA esclusa</span></div>
            <div class="catalog-table-scroll"><table class="catalog-table catalog-products-table"><thead><tr><th scope="col">Prodotto</th><th scope="col">Brand</th><th scope="col">Fornitori</th><th scope="col" class="catalog-number">Costo unitario</th><th scope="col">Varianti</th><th scope="col">Sito</th><th scope="col"><span class="sr-only">Azioni</span></th></tr></thead><tbody>
                @foreach($products as $product)
                    <tr data-catalog-product-row data-brand="{{ $product->crm_brand_id }}" data-visibility="{{ $product->is_visible_on_site ? 'visible' : 'hidden' }}">
                        <td><strong>{{ $product->name }}</strong><span class="catalog-secondary catalog-code">{{ $product->code }}</span></td>
                        <td data-label="Brand">{{ $product->brand?->name ?: '—' }}</td>
                        <td data-label="Fornitori" class="catalog-supplier-cell">{{ $product->suppliers->pluck('name')->implode(', ') ?: '—' }}</td>
                        <td data-label="Costo unitario" class="catalog-number"><strong>€ {{ number_format((float)$product->unit_cost, 2, ',', '.') }}</strong><span class="catalog-secondary">{{ $product->priceTiers->count() }} {{ $product->priceTiers->count() === 1 ? 'fascia prezzo' : 'fasce prezzo' }}</span></td>
                        <td data-label="Varianti"><span>{{ $product->colors->count() }} {{ $product->colors->count() === 1 ? 'colore' : 'colori' }}</span>@if($product->colors->isNotEmpty())<span class="catalog-mini-swatches" aria-label="Anteprima colori">@foreach($product->colors->take(5) as $color)<i class="{{ $color->hex ? '' : 'catalog-color-unset' }}" @if($color->hex) style="background-color: {{ $color->hex }}" @endif title="{{ $color->name }}"></i>@endforeach</span>@endif<span class="catalog-secondary">{{ $product->sizes->count() }} {{ $product->sizes->count() === 1 ? 'taglia' : 'taglie' }}</span></td>
                        <td data-label="Sito"><span class="catalog-badge {{ $product->is_visible_on_site ? 'catalog-badge-green' : '' }}">{{ $product->is_visible_on_site ? 'Abilitato' : 'Nascosto' }}</span></td>
                        <td class="catalog-row-actions"><button type="button" data-catalog-open="product-{{ $product->id }}" class="catalog-button catalog-button-secondary">Apri scheda <span aria-hidden="true">↗</span></button></td>
                    </tr>
                @endforeach
            </tbody></table></div>
            <div data-catalog-filter-empty @if($products->isNotEmpty()) hidden @endif class="catalog-empty"><strong>Nessun prodotto trovato</strong><p>Modifica la ricerca o i filtri, oppure aggiungi un prodotto al catalogo.</p></div>
        </section>

        <section id="catalog-panel-prints" data-catalog-panel role="tabpanel" aria-labelledby="catalog-tab-prints" @if($catalogSection !== 'prints') hidden @endif>
            <div class="catalog-section-heading"><div><h2>Stampe e lavorazioni</h2><p>Tariffe di costo e vendita per applicazione, suddivise per quantità.</p></div>@if($canManageCatalog)<button type="button" data-catalog-open="print-new" class="catalog-button catalog-button-primary">＋ Nuova stampa</button>@endif</div>
            <form method="GET" class="catalog-search catalog-search-inset" role="search"><input type="hidden" name="section" value="prints"><input name="q" value="{{ $search }}" aria-label="Cerca stampe" placeholder="Cerca codice o nome della stampa"><button class="catalog-button catalog-button-secondary">Cerca</button>@if($search)<a href="{{ route('admin.crm-catalog.index', ['section' => 'prints']) }}" class="catalog-text-button">Azzera</a>@endif</form>
            <div class="catalog-table-scroll"><table class="catalog-table"><thead><tr><th scope="col">Lavorazione</th><th scope="col">Codice</th><th scope="col">Fasce configurate</th><th scope="col"><span class="sr-only">Azioni</span></th></tr></thead><tbody>
                @forelse($printTypes as $printType)<tr><td><strong>{{ $printType->name }}</strong></td><td class="catalog-code">{{ $printType->code }}</td><td>{{ $printType->priceTiers->count() }} fasce</td><td class="catalog-row-actions"><button type="button" data-catalog-open="print-{{ $printType->id }}" class="catalog-button catalog-button-secondary">Apri listino ↗</button></td></tr>@empty<tr><td colspan="4" class="catalog-empty">Nessuna stampa trovata.</td></tr>@endforelse
            </tbody></table></div>
        </section>

        @include('admin.crm-catalog.directories')

        <section id="catalog-panel-categories" data-catalog-panel role="tabpanel" aria-labelledby="catalog-tab-categories" @if($catalogSection !== 'categories') hidden @endif>
            <div class="catalog-section-heading"><div><h2>Categorie lead</h2><p>Classificazione dei clienti nel CRM. Queste categorie non classificano i prodotti.</p></div>@if($canManageCatalog)<button type="button" data-catalog-open="category-new" class="catalog-button catalog-button-primary">＋ Nuova categoria</button>@endif</div>
            <div class="catalog-table-scroll"><table class="catalog-table"><thead><tr><th scope="col">Categoria</th><th scope="col">Ordine di visualizzazione</th><th scope="col">Stato</th><th scope="col"><span class="sr-only">Azioni</span></th></tr></thead><tbody>
                @forelse($categories as $category)<tr><td><strong>{{ $category->name }}</strong></td><td>{{ $category->sort_order }}</td><td><span class="catalog-badge {{ $category->is_active ? 'catalog-badge-green' : '' }}">{{ $category->is_active ? 'Attiva' : 'Disattivata' }}</span></td><td><div class="catalog-actions">@if($canManageCatalog)<form method="POST" action="{{ route('admin.crm-catalog.categories.toggle', $category) }}">@csrf @method('PATCH')<button class="catalog-button catalog-button-secondary">{{ $category->is_active ? 'Disattiva' : 'Riattiva' }}</button></form><form method="POST" action="{{ route('admin.crm-catalog.categories.destroy', $category) }}" data-catalog-delete="Eliminare la categoria {{ $category->name }}? Le categorie già associate ai lead non possono essere eliminate.">@csrf @method('DELETE')<button class="catalog-text-button catalog-text-danger">Elimina</button></form>@endif</div></td></tr>@empty<tr><td colspan="4" class="catalog-empty">Nessuna categoria configurata.</td></tr>@endforelse
            </tbody></table></div>
        </section>
    </div>

    @include('admin.crm-catalog.product-dialogs')
    @include('admin.crm-catalog.print-dialogs')
    @include('admin.crm-catalog.directory-dialogs')
    @if($canManageCatalog)
        <x-catalog-dialog id="category-new" title="Nuova categoria lead" description="Organizza i lead con una classificazione condivisa.">
            <form method="POST" action="{{ route('admin.crm-catalog.categories.store') }}" class="catalog-form">@csrf<input type="hidden" name="catalog_dialog" value="category-new"><input type="hidden" name="catalog_section" value="categories">
                <div class="catalog-modal-body catalog-form-grid"><label class="catalog-field catalog-span-full"><span>Nome categoria</span><input name="name" value="{{ old('catalog_dialog') === 'category-new' ? old('name') : '' }}" required maxlength="255" placeholder="Es. Associazioni sportive"></label><label class="catalog-field"><span>Ordine di visualizzazione</span><input name="sort_order" value="{{ old('catalog_dialog') === 'category-new' ? old('sort_order', 0) : 0 }}" type="number" min="0"><small>Le categorie con il numero più basso appaiono per prime.</small></label></div>
                <footer class="catalog-modal-footer"><button type="button" data-catalog-close class="catalog-button catalog-button-secondary">Annulla</button><button class="catalog-button catalog-button-primary">Aggiungi categoria</button></footer>
            </form>
        </x-catalog-dialog>
        <x-catalog-dialog id="catalog-delete" title="Conferma eliminazione" description="L’operazione è definitiva.">
            <div class="catalog-modal-body"><p data-catalog-delete-message></p></div><footer class="catalog-modal-footer"><button type="button" data-catalog-close class="catalog-button catalog-button-secondary">Annulla</button><button type="button" data-catalog-confirm-delete class="catalog-button catalog-button-danger">Elimina definitivamente</button></footer>
        </x-catalog-dialog>
    @endif
</div>
@endsection
