<section class="catalog-form-section" data-catalog-variants>
    <input type="hidden" name="variants_present" value="1">
    <script type="application/json" data-variant-data>@json(['colors' => $colorValues, 'sizes' => $sizeValues])</script>
    <div class="catalog-variant-heading">
        <div class="catalog-form-section-title"><h3>Colori disponibili <span data-color-count class="catalog-variant-count" aria-live="polite"></span></h3><p>Inserisci nome e codice del fornitore. Usa il pallino per scegliere il campione colore.</p></div>
        <button type="button" data-color-add class="catalog-button catalog-button-secondary">＋ Aggiungi colore</button>
    </div>
    <div data-color-list class="catalog-color-list"></div>
    <p data-color-empty class="catalog-variant-empty">Nessun colore inserito. Aggiungi il primo colore per questo prodotto.</p>
    <template data-color-template>
        <div data-color-row class="catalog-color-row" role="group">
            <label class="catalog-color-swatch" title="Scegli il campione colore">
                <span data-color-preview class="catalog-color-unset" aria-hidden="true"></span>
                <input type="color" data-color-picker value="#000000" aria-label="Scegli campione colore">
            </label>
            <div class="catalog-color-fields">
                <label class="catalog-field"><span>Codice fornitore</span><input data-color-code maxlength="100" placeholder="Es. BK" autocomplete="off"></label>
                <label class="catalog-field catalog-color-name"><span>Nome colore *</span><input data-color-name required maxlength="100" placeholder="Es. Nero" autocomplete="off"></label>
                <label class="catalog-field"><span>Campione HEX</span><input data-color-hex maxlength="7" pattern="#[0-9a-fA-F]{6}" placeholder="#RRGGBB" autocomplete="off" spellcheck="false"></label>
            </div>
            <button type="button" data-color-remove class="catalog-variant-remove" aria-label="Rimuovi colore" title="Rimuovi colore">×</button>
        </div>
    </template>
    <p class="catalog-help catalog-variant-hint">Codice e campione sono facoltativi. Se il codice manca, viene usato il nome. Il pallino tratteggiato indica un campione non definito.</p>

    <div class="catalog-size-editor">
        <div class="catalog-variant-heading"><div class="catalog-form-section-title"><h3>Taglie disponibili <span data-size-count class="catalog-variant-count" aria-live="polite"></span></h3><p>Seleziona le taglie standard oppure aggiungi una misura specifica.</p></div><button type="button" data-size-group="XS,S,M,L,XL,XXL" class="catalog-text-button">Seleziona XS–XXL</button></div>
        <div class="catalog-size-presets" role="group" aria-label="Taglie adulto">
            @foreach(['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL', '5XL', 'Taglia unica'] as $size)<button type="button" data-size-preset="{{ $size }}" aria-pressed="false">{{ $size }}</button>@endforeach
        </div>
        <p class="catalog-size-group-label">Bambino</p>
        <div class="catalog-size-presets" role="group" aria-label="Taglie bambino">
            @foreach(['2/3 anni', '3/4 anni', '5/6 anni', '7/8 anni', '9/11 anni', '12/14 anni'] as $size)<button type="button" data-size-preset="{{ $size }}" aria-pressed="false">{{ $size }}</button>@endforeach
        </div>
        <div class="catalog-custom-size"><label class="catalog-field"><span>Altra taglia</span><input data-size-custom maxlength="50" placeholder="Es. 4XL/5XL oppure 36" autocomplete="off"></label><button type="button" data-size-add class="catalog-button catalog-button-secondary">Aggiungi taglia</button></div>
        <p data-size-message class="catalog-size-message" role="status"></p>
        <div class="catalog-size-selection"><p class="catalog-size-group-label">Taglie selezionate · ordine di visualizzazione</p><div data-size-list class="catalog-selected-sizes" aria-label="Taglie selezionate"></div><p data-size-empty class="catalog-help">Nessuna taglia selezionata.</p></div>
    </div>
</section>
