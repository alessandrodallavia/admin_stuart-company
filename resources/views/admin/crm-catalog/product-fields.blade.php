@php
    $formKey = (string) ($catalogProduct?->id ?? 'new');
    $restore = (string) old('catalog_product_key') === $formKey;
    $brandValue = $restore ? old('crm_brand_id') : $catalogProduct?->crm_brand_id;
    $supplierValues = $restore ? old('supplier_ids', []) : ($catalogProduct?->suppliers->modelKeys() ?? []);
    $colorValues = $restore ? old('colors', []) : ($catalogProduct?->colors->map(fn ($color) => ['code' => $color->code, 'name' => $color->name, 'hex' => $color->hex, 'sizes_restricted' => $color->sizes_restricted, 'sizes' => $color->sizes->pluck('name')->all()])->all() ?? []);
    $sizeValues = $restore ? old('sizes', []) : ($catalogProduct?->sizes->pluck('name')->all() ?? []);
    $colorValues = is_array($colorValues) ? array_values($colorValues) : [];
    $sizeValues = is_array($sizeValues) ? array_values($sizeValues) : [];
    $brandSuppliers = $brands->mapWithKeys(fn ($brand) => [(string) $brand->id => $brand->suppliers->modelKeys()]);
@endphp
<input type="hidden" name="metadata_present" value="1"><input type="hidden" name="catalog_product_key" value="{{ $formKey }}">
<section class="catalog-form-section" x-data="{ brand: @js((string) $brandValue), allowed: @js($brandSuppliers) }">
    <div class="catalog-form-section-title"><h3>Brand e fornitori</h3><p>Seleziona i distributori da cui acquisti questo prodotto.</p></div>
    <div class="catalog-form-grid">
        <label class="catalog-field"><span>Brand</span><select name="crm_brand_id" x-model="brand"><option value="">Non specificato</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected((string) $brandValue === (string) $brand->id)>{{ $brand->name }}</option>@endforeach</select><small>Gestisci le associazioni nella sezione Brand.</small></label>
        <fieldset class="catalog-field"><legend>Fornitori del prodotto</legend><div class="catalog-checkbox-list">
            @forelse($suppliers as $supplier)<label class="catalog-check" :class="!(allowed[brand] || []).includes({{ $supplier->id }}) && 'catalog-disabled'"><input type="checkbox" name="supplier_ids[]" value="{{ $supplier->id }}" @checked(in_array($supplier->id, $supplierValues)) :disabled="!(allowed[brand] || []).includes({{ $supplier->id }})"><span>{{ $supplier->name }}</span></label>@empty<p class="catalog-help">Nessun fornitore configurato.</p>@endforelse
        </div><small>Puoi selezionare più fornitori tra quelli associati al brand.</small></fieldset>
    </div>
</section>
@include('admin.crm-catalog.supplier-details')
<section class="catalog-form-section">
    <div class="catalog-form-section-title"><h3>Documenti e riferimenti</h3><p>Conserva la scheda tecnica e il collegamento al prodotto del fornitore.</p></div>
    <div class="catalog-form-grid">
        <label class="catalog-field"><span>Pagina prodotto del fornitore</span><input name="supplier_product_url" type="url" maxlength="2048" value="{{ $restore ? old('supplier_product_url') : $catalogProduct?->supplier_product_url }}" placeholder="https://..."></label>
        <div class="catalog-field"><label class="catalog-field"><span>Scheda tecnica PDF</span><input name="datasheet" type="file" accept="application/pdf,.pdf"><small>Massimo 10 MB. Un nuovo PDF sostituisce quello precedente.</small></label>
            @if($catalogProduct?->datasheet_path)<a href="{{ route('admin.crm-catalog.products.datasheet', $catalogProduct) }}" class="catalog-link">Scarica {{ $catalogProduct->datasheet_filename }}</a><label class="catalog-check"><input type="checkbox" name="remove_datasheet" value="1" @checked($restore && old('remove_datasheet'))><span>Rimuovi scheda attuale</span></label>@endif
        </div>
    </div>
</section>
@include('admin.crm-catalog.variants-editor')
<section class="catalog-form-section"><label class="catalog-check catalog-visibility"><input type="hidden" name="is_visible_on_site" value="0"><input type="checkbox" name="is_visible_on_site" value="1" @checked($restore ? old('is_visible_on_site') : $catalogProduct?->is_visible_on_site)><span><strong>Abilita visibilità sul sito</strong><small>Il prodotto resta disponibile negli ordini anche se nascosto. La pubblicazione sarà attiva quando il sito verrà collegato al CRM.</small></span></label></section>
