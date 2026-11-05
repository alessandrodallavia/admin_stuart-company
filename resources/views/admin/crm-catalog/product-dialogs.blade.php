@foreach(collect([null])->concat($products) as $catalogProduct)
    @if(!$catalogProduct && !$canManageCatalog) @continue @endif
    @php
        $productId = $catalogProduct?->id;
        $dialogId = 'product-'.($productId ?? 'new');
        $restoreProduct = (string) old('catalog_product_key') === (string) ($productId ?? 'new');
    @endphp
    <x-catalog-dialog :id="$dialogId" :title="$catalogProduct ? $catalogProduct->name : 'Nuovo prodotto'" :description="$catalogProduct ? $catalogProduct->code.' · Anagrafica e listino commerciale' : 'Aggiungi un articolo al catalogo commerciale.'" :wide="true">
        <div data-catalog-local-tabs>
            @if($catalogProduct)
                <div class="catalog-local-tabs" role="tablist" aria-label="Scheda prodotto">
                    <button type="button" role="tab" id="{{ $dialogId }}-details-tab" data-catalog-local-tab="{{ $dialogId }}-details" aria-controls="{{ $dialogId }}-details" aria-selected="true">Anagrafica prodotto</button>
                    <a href="{{ route('admin.crm-catalog.products.mockups.index', $catalogProduct) }}" class="catalog-button catalog-button-secondary" style="align-self:center">Mockup per colore ↗</a>
                    <button type="button" role="tab" id="{{ $dialogId }}-pricing-tab" data-catalog-local-tab="{{ $dialogId }}-pricing" aria-controls="{{ $dialogId }}-pricing" aria-selected="false" tabindex="-1">Fasce prezzo <span>{{ $catalogProduct->priceTiers->count() }}</span></button>
                </div>
            @endif
            <div id="{{ $dialogId }}-details" data-catalog-local-panel @if($catalogProduct) role="tabpanel" aria-labelledby="{{ $dialogId }}-details-tab" @endif>
                @if($canManageCatalog)
                    <form method="POST" enctype="multipart/form-data" action="{{ $catalogProduct ? route('admin.crm-catalog.products.update', $catalogProduct) : route('admin.crm-catalog.products.store') }}" class="catalog-form">@csrf @if($catalogProduct) @method('PATCH') @endif
                        <input type="hidden" name="catalog_dialog" value="{{ $dialogId }}"><input type="hidden" name="catalog_section" value="products">
                        <div class="catalog-modal-body">
                            <section class="catalog-form-section"><div class="catalog-form-section-title"><h3>Informazioni principali</h3><p>Codice, nome e costo base del prodotto. Gli importi sono IVA esclusa.</p></div>
                                <div class="catalog-form-grid catalog-form-grid-product">
                                    <label class="catalog-field"><span>Codice prodotto <span aria-hidden="true">*</span></span><input name="code" value="{{ $restoreProduct ? old('code') : $catalogProduct?->code }}" required maxlength="100" placeholder="Es. BS332"></label>
                                    <label class="catalog-field"><span>Nome prodotto <span aria-hidden="true">*</span></span><input name="name" value="{{ $restoreProduct ? old('name') : $catalogProduct?->name }}" required maxlength="255" placeholder="Es. OUTSIDER"></label>
                                    <label class="catalog-field"><span>Costo unitario (€) <span aria-hidden="true">*</span></span><input name="unit_cost" value="{{ $restoreProduct ? old('unit_cost') : $catalogProduct?->unit_cost }}" required type="number" min="0" step="0.0001" placeholder="0,00"></label>
                                </div>
                            </section>
                            @include('admin.crm-catalog.product-fields')
                        </div>
                        <footer class="catalog-modal-footer"><span class="catalog-help catalog-footer-note">* Campi obbligatori</span><button type="button" data-catalog-close class="catalog-button catalog-button-secondary">Annulla</button><button class="catalog-button catalog-button-primary">{{ $catalogProduct ? 'Salva modifiche' : 'Salva prodotto' }}</button></footer>
                    </form>
                    @if($catalogProduct)<div class="catalog-delete-zone"><span>Eliminando il prodotto verranno rimosse anche le sue fasce prezzo.</span><form method="POST" action="{{ route('admin.crm-catalog.products.destroy', $catalogProduct) }}" data-catalog-delete="Eliminare {{ $catalogProduct->name }} e tutte le sue fasce prezzo? Lo storico delle schede vendita conserverà nomi e importi.">@csrf @method('DELETE')<button class="catalog-text-button catalog-text-danger">Elimina prodotto</button></form></div>@endif
                @else
                    <div class="catalog-modal-body">
                        <dl class="catalog-details-grid"><div><dt>Brand</dt><dd>{{ $catalogProduct->brand?->name ?: 'Non specificato' }}</dd></div><div><dt>Fornitori</dt><dd>{{ $catalogProduct->suppliers->pluck('name')->implode(', ') ?: 'Non specificati' }}</dd></div><div><dt>Costo unitario IVA esclusa</dt><dd>€ {{ number_format((float)$catalogProduct->unit_cost, 2, ',', '.') }}</dd></div><div><dt>Visibilità sito</dt><dd>{{ $catalogProduct->is_visible_on_site ? 'Abilitata' : 'Nascosto' }}</dd></div></dl>
                        <section class="catalog-form-section"><h3>Colori disponibili</h3><div class="catalog-chips">@forelse($catalogProduct->colors as $color)<span class="catalog-chip">@if($color->hex)<i style="background-color: {{ $color->hex }}" aria-hidden="true"></i>@endif{{ $color->code }} · {{ $color->name }} — {{ $color->sizes_restricted ? ($color->sizes->pluck('name')->implode(', ') ?: 'Nessuna taglia abilitata') : 'Tutte le taglie' }}</span>@empty<span class="catalog-help">Non specificati</span>@endforelse</div></section>
                        <section class="catalog-form-section"><h3>Taglie disponibili</h3><div class="catalog-chips">@forelse($catalogProduct->sizes as $size)<span class="catalog-chip">{{ $size->name }}</span>@empty<span class="catalog-help">Non specificate</span>@endforelse</div></section>
                        @include('admin.crm-catalog.supplier-details')
                        <div class="catalog-document-links">@if($catalogProduct->datasheet_path)<a href="{{ route('admin.crm-catalog.products.datasheet', $catalogProduct) }}" class="catalog-button catalog-button-secondary">Scarica scheda tecnica PDF</a>@endif @if($catalogProduct->supplier_product_url)<a href="{{ $catalogProduct->supplier_product_url }}" target="_blank" rel="noopener noreferrer" class="catalog-link">Pagina del fornitore ↗</a>@endif</div>
                    </div>
                    <footer class="catalog-modal-footer"><button type="button" data-catalog-close class="catalog-button catalog-button-secondary">Chiudi</button></footer>
                @endif
            </div>
            @if($catalogProduct)
                <div id="{{ $dialogId }}-pricing" data-catalog-local-panel role="tabpanel" aria-labelledby="{{ $dialogId }}-pricing-tab" hidden>
                    @include('admin.crm-catalog.price-list', ['priceOwner' => $catalogProduct, 'priceKind' => 'product'])
                </div>
            @endif
        </div>
    </x-catalog-dialog>
    @if($catalogProduct && $canManageCatalog)
        @include('admin.crm-catalog.tier-dialogs', ['priceOwner' => $catalogProduct, 'priceKind' => 'product'])
    @endif
@endforeach
