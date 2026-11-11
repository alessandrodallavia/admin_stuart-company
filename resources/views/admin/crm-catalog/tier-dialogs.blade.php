@foreach(collect([null])->concat($priceOwner->priceTiers) as $tier)
    @php
        $tierDialogId = $priceKind.'-tier-'.($tier?->id ?? 'new-'.$priceOwner->id);
        $restoreTier = old('catalog_dialog') === $tierDialogId;
        $tierSection = $priceKind === 'product' ? 'products' : 'prints';
    @endphp
    <x-catalog-dialog :id="$tierDialogId" :parent="$priceKind.'-'.$priceOwner->id" :title="$tier ? 'Modifica fascia prezzo' : 'Nuova fascia prezzo'" :description="$priceOwner->code.' · '.$priceOwner->name">
        <form method="POST" action="{{ $tier ? route('admin.crm-catalog.'.$tierSection.'.tiers.update', $tier) : route('admin.crm-catalog.'.$tierSection.'.tiers.store', $priceOwner) }}" class="catalog-form">@csrf @if($tier) @method('PATCH') @endif<input type="hidden" name="catalog_dialog" value="{{ $tierDialogId }}"><input type="hidden" name="catalog_section" value="{{ $tierSection }}">
            <div class="catalog-modal-body">
                <p class="catalog-info">Costi e prezzi sono unitari e IVA esclusa.</p>
                <div class="catalog-form-grid">
                    <label class="catalog-field"><span>Quantità da *</span><input name="min_quantity" required type="number" min="0.01" step="0.01" value="{{ $restoreTier ? old('min_quantity') : $tier?->min_quantity }}" placeholder="Es. 10"></label>
                    <label class="catalog-field"><span>Quantità a</span><input name="max_quantity" type="number" min="0.01" step="0.01" value="{{ $restoreTier ? old('max_quantity') : $tier?->max_quantity }}" placeholder="Senza limite"><small>Lascia vuoto per una fascia senza massimo.</small></label>
                    <label class="catalog-field"><span>Costo unitario (€) *</span><input name="unit_cost" required type="number" min="0" step="0.0001" value="{{ $restoreTier ? old('unit_cost') : ($tier?->unit_cost ?? $priceOwner->unit_cost) }}" placeholder="0,00"></label>
                    <label class="catalog-field"><span>Prezzo vendita (€) *</span><input name="unit_price" required type="number" min="0" step="0.0001" value="{{ $restoreTier ? old('unit_price') : $tier?->unit_price }}" placeholder="0,00"></label>
                </div>
            </div>
            <footer class="catalog-modal-footer"><button type="button" data-catalog-close class="catalog-button catalog-button-secondary">Annulla</button><button class="catalog-button catalog-button-primary">{{ $tier ? 'Salva fascia' : 'Aggiungi fascia' }}</button></footer>
        </form>
    </x-catalog-dialog>
@endforeach
