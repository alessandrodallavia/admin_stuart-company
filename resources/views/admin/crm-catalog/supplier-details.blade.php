@if($catalogProduct)
    @foreach($catalogProduct->supplierDetails as $supplierDetail)
        <section class="catalog-form-section catalog-supplier-details">
            <div class="catalog-variant-heading">
                <div class="catalog-form-section-title"><h3>Scheda prodotto · {{ $supplierDetail->supplier?->name ?? 'Fornitore' }}</h3><p>Dati del fornitore · verificati il {{ $supplierDetail->source_checked_at?->format('d/m/Y') }}</p></div>
                @if(filter_var($supplierDetail->datasheet_url, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($supplierDetail->datasheet_url, PHP_URL_SCHEME) ?? ''), ['http', 'https']))
                    <a href="{{ $supplierDetail->datasheet_url }}" target="_blank" rel="noopener noreferrer" class="catalog-button catalog-button-secondary">Apri scheda tecnica PDF ↗</a>
                @endif
            </div>
            <dl class="catalog-details-grid"><div><dt>Composizione</dt><dd>{{ $supplierDetail->composition }}</dd></div><div><dt>Grammatura</dt><dd>{{ $supplierDetail->weight_gsm }} g/m²</dd></div></dl>
            <p class="catalog-supplier-description">{{ $supplierDetail->details }}</p>
            <p class="catalog-help">Il PDF si apre sul sito del fornitore. Puoi conservare una copia privata caricandola in Documenti e riferimenti.</p>
        </section>
    @endforeach
@endif
