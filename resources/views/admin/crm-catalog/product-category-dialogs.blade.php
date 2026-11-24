@if($canManageCatalog)
    @foreach(collect([null])->concat($productCategories) as $productCategory)
        @php
            $categoryDialogId = 'product-category-'.($productCategory?->id ?? 'new');
            $restoreCategory = old('catalog_dialog') === $categoryDialogId;
            $parentValue = $restoreCategory ? old('parent_id') : $productCategory?->parent_id;
        @endphp
        <x-catalog-dialog :id="$categoryDialogId" :title="$productCategory ? 'Modifica categoria prodotto' : 'Nuova categoria prodotto'" description="Definisci nome, categoria superiore e ordine di visualizzazione.">
            <form method="POST" action="{{ $productCategory ? route('admin.crm-catalog.product-categories.update', $productCategory) : route('admin.crm-catalog.product-categories.store') }}" class="catalog-form">
                @csrf @if($productCategory) @method('PATCH') @endif
                <input type="hidden" name="catalog_dialog" value="{{ $categoryDialogId }}"><input type="hidden" name="catalog_section" value="product-categories">
                <div class="catalog-modal-body catalog-form-grid">
                    <label class="catalog-field catalog-span-full"><span>Nome categoria *</span><input name="name" value="{{ $restoreCategory ? old('name') : $productCategory?->name }}" required maxlength="255"></label>
                    <label class="catalog-field catalog-span-full"><span>Slug</span><input name="slug" value="{{ $restoreCategory ? old('slug') : $productCategory?->slug }}" maxlength="255" pattern="[a-z0-9]+(-[a-z0-9]+)*"><small>Identificativo per l’URL: lettere minuscole, numeri e trattini. Se vuoto, viene generato dal nome alla creazione.</small></label>
                    <label class="catalog-field catalog-span-full"><span>Categoria superiore</span><select name="parent_id"><option value="">Nessuna — categoria principale</option>@foreach($productCategories as $parentOption) @if($parentOption->id !== $productCategory?->id)<option value="{{ $parentOption->id }}" @selected((string) $parentValue === (string) $parentOption->id)>{{ $parentOption->name }} · {{ $parentOption->slug }}{{ $parentOption->is_active ? '' : ' (disattivata)' }}</option>@endif @endforeach</select><small>Selezionala per creare una sottocategoria.</small></label>
                    <label class="catalog-field catalog-span-full"><span>Descrizione</span><textarea name="description" rows="3" maxlength="5000">{{ $restoreCategory ? old('description') : $productCategory?->description }}</textarea></label>
                    <label class="catalog-field"><span>Ordine di visualizzazione</span><input type="number" name="sort_order" min="0" max="4294967295" value="{{ $restoreCategory ? old('sort_order', 0) : ($productCategory?->sort_order ?? 0) }}"><small>I numeri più bassi appaiono per primi.</small></label>
                    <label class="catalog-field"><span>Stato</span><select name="is_active"><option value="1" @selected((string) ($restoreCategory ? old('is_active', 1) : ($productCategory ? (int) $productCategory->is_active : 1)) === '1')>Attiva</option><option value="0" @selected((string) ($restoreCategory ? old('is_active', 1) : ($productCategory ? (int) $productCategory->is_active : 1)) === '0')>Disattivata</option></select></label>
                </div>
                <footer class="catalog-modal-footer"><button type="button" data-catalog-close class="catalog-button catalog-button-secondary">Annulla</button><button class="catalog-button catalog-button-primary">{{ $productCategory ? 'Salva modifiche' : 'Crea categoria' }}</button></footer>
            </form>
            @if($productCategory)
                <div class="catalog-delete-zone"><span>Puoi eliminare solo categorie senza prodotti e sottocategorie.</span><form method="POST" action="{{ route('admin.crm-catalog.product-categories.destroy', $productCategory) }}" data-catalog-delete="Eliminare la categoria {{ $productCategory->name }}?">
                    @csrf @method('DELETE')<input type="hidden" name="catalog_dialog" value="{{ $categoryDialogId }}"><input type="hidden" name="catalog_section" value="product-categories"><button class="catalog-text-button catalog-text-danger">Elimina categoria</button>
                </form></div>
            @endif
        </x-catalog-dialog>
    @endforeach
@endif
