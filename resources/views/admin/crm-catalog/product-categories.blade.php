<section id="catalog-panel-product-categories" data-catalog-panel role="tabpanel" aria-labelledby="catalog-tab-product-categories" @if($catalogSection !== 'product-categories') hidden @endif>
    <div class="catalog-section-heading"><div><h2>Categorie prodotti</h2><p>Crea e organizza le categorie e le sottocategorie del catalogo ecommerce.</p></div>@if($canManageCatalog)<button type="button" data-catalog-open="product-category-new" class="catalog-button catalog-button-primary">＋ Nuova categoria prodotto</button>@endif</div>
    <div class="catalog-table-scroll"><table class="catalog-table"><thead><tr><th scope="col">Categoria</th><th scope="col">Categoria superiore</th><th scope="col">Prodotti</th><th scope="col">Ordine</th><th scope="col">Stato</th><th scope="col"><span class="sr-only">Azioni</span></th></tr></thead><tbody>
        @forelse($productCategories as $productCategory)
            <tr><td><strong>{{ $productCategory->name }}</strong><span class="catalog-secondary catalog-code">{{ $productCategory->slug }}</span></td><td>{{ $productCategory->parent?->name ?: 'Categoria principale' }}</td><td>{{ $productCategory->products_count }}<span class="catalog-secondary">{{ $productCategory->children_count }} sottocategorie</span></td><td>{{ $productCategory->sort_order }}</td><td><span class="catalog-badge {{ $productCategory->is_active ? 'catalog-badge-green' : '' }}">{{ $productCategory->is_active ? 'Attiva' : 'Disattivata' }}</span></td><td>@if($canManageCatalog)<button type="button" data-catalog-open="product-category-{{ $productCategory->id }}" class="catalog-button catalog-button-secondary">Modifica</button>@endif</td></tr>
        @empty
            <tr><td colspan="6" class="catalog-empty">Nessuna categoria prodotto. Crea la prima categoria per organizzare il catalogo.</td></tr>
        @endforelse
    </tbody></table></div>
</section>
