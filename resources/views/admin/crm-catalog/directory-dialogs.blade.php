@if($canManageCatalog)
    @foreach(collect([null])->concat($brands) as $brand)
        @php
            $brandDialogId = 'brand-'.($brand?->id ?? 'new');
            $restoreBrand = old('catalog_dialog') === $brandDialogId;
            $selectedSuppliers = $restoreBrand ? old('supplier_ids', []) : ($brand?->suppliers->modelKeys() ?? []);
        @endphp
        <x-catalog-dialog :id="$brandDialogId" :title="$brand ? 'Modifica brand' : 'Nuovo brand'" description="Un brand può essere distribuito da più fornitori.">
            <form method="POST" action="{{ $brand ? route('admin.crm-catalog.brands.update', $brand) : route('admin.crm-catalog.brands.store') }}" class="catalog-form">@csrf @if($brand) @method('PATCH') @endif<input type="hidden" name="catalog_dialog" value="{{ $brandDialogId }}"><input type="hidden" name="catalog_section" value="brands">
                <div class="catalog-modal-body">
                    <label class="catalog-field"><span>Nome brand *</span><input name="name" value="{{ $restoreBrand ? old('name') : $brand?->name }}" required maxlength="255" placeholder="Es. B&C"></label>
                    <fieldset class="catalog-field catalog-form-section"><legend>Fornitori del brand</legend><div class="catalog-checkbox-list">
                        @forelse($suppliers as $supplier)<label class="catalog-check"><input type="checkbox" name="supplier_ids[]" value="{{ $supplier->id }}" @checked(in_array($supplier->id, $selectedSuppliers))><span>{{ $supplier->name }}</span></label>@empty<p class="catalog-help">Nessun fornitore configurato. Crea prima l’anagrafica nella sezione Fornitori.</p>@endforelse
                    </div><small>Non puoi rimuovere un’associazione ancora utilizzata da un prodotto.</small></fieldset>
                </div>
                <footer class="catalog-modal-footer"><button type="button" data-catalog-close class="catalog-button catalog-button-secondary">Annulla</button><button class="catalog-button catalog-button-primary">{{ $brand ? 'Salva brand' : 'Aggiungi brand' }}</button></footer>
            </form>
            @if($brand)<div class="catalog-delete-zone"><span>I brand associati a prodotti non possono essere eliminati.</span><form method="POST" action="{{ route('admin.crm-catalog.brands.destroy', $brand) }}" data-catalog-delete="Eliminare il brand {{ $brand->name }}? L’operazione è consentita solo se non è associato a prodotti.">@csrf @method('DELETE')<input type="hidden" name="catalog_dialog" value="{{ $brandDialogId }}"><input type="hidden" name="catalog_section" value="brands"><button class="catalog-text-button catalog-text-danger">Elimina brand</button></form></div>@endif
        </x-catalog-dialog>
    @endforeach
    @foreach(collect([null])->concat($suppliers) as $supplier)
        @php
            $supplierDialogId = 'supplier-'.($supplier?->id ?? 'new');
            $restoreSupplier = old('catalog_dialog') === $supplierDialogId;
        @endphp
        <x-catalog-dialog :id="$supplierDialogId" :title="$supplier ? 'Modifica fornitore' : 'Nuovo fornitore'" description="L’anagrafica del distributore da associare a brand e prodotti.">
            <form method="POST" action="{{ $supplier ? route('admin.crm-catalog.suppliers.update', $supplier) : route('admin.crm-catalog.suppliers.store') }}" class="catalog-form">@csrf @if($supplier) @method('PATCH') @endif<input type="hidden" name="catalog_dialog" value="{{ $supplierDialogId }}"><input type="hidden" name="catalog_section" value="suppliers">
                <div class="catalog-modal-body catalog-form-grid"><label class="catalog-field catalog-span-full"><span>Nome fornitore *</span><input name="name" value="{{ $restoreSupplier ? old('name') : $supplier?->name }}" required maxlength="255" placeholder="Ragione sociale o nome commerciale"></label><label class="catalog-field catalog-span-full"><span>Sito web</span><input name="website" value="{{ $restoreSupplier ? old('website') : $supplier?->website }}" type="url" maxlength="2048" placeholder="https://..."><small>Facoltativo. Usa il collegamento al sito del distributore.</small></label></div>
                <footer class="catalog-modal-footer"><button type="button" data-catalog-close class="catalog-button catalog-button-secondary">Annulla</button><button class="catalog-button catalog-button-primary">{{ $supplier ? 'Salva fornitore' : 'Aggiungi fornitore' }}</button></footer>
            </form>
            @if($supplier)<div class="catalog-delete-zone"><span>I fornitori associati a prodotti non possono essere eliminati.</span><form method="POST" action="{{ route('admin.crm-catalog.suppliers.destroy', $supplier) }}" data-catalog-delete="Eliminare il fornitore {{ $supplier->name }}? L’operazione è consentita solo se non è associato a prodotti.">@csrf @method('DELETE')<input type="hidden" name="catalog_dialog" value="{{ $supplierDialogId }}"><input type="hidden" name="catalog_section" value="suppliers"><button class="catalog-text-button catalog-text-danger">Elimina fornitore</button></form></div>@endif
        </x-catalog-dialog>
    @endforeach
@endif
