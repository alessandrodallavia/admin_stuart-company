<button type="button" data-quote-open class="catalog-button catalog-button-secondary wa-quote-trigger">Prepara preventivo prodotti <span aria-hidden="true">↗</span></button>
<dialog data-hoodie-quote data-url="{{ route('admin.conversations.hoodie-quote.catalog', $selectedConversation) }}" class="catalog-dialog catalog-dialog-wide wa-quote-dialog" aria-labelledby="wa-quote-title">
    <div class="catalog-dialog-shell">
        <header class="catalog-dialog-header"><div><h2 id="wa-quote-title">Prepara preventivo prodotti</h2><p>Confronta i prodotti CRM e controlla il messaggio prima di inserirlo nella chat.</p></div><button type="button" data-quote-close class="catalog-close" aria-label="Chiudi preventivo">×</button></header>
        <div class="catalog-dialog-content catalog-modal-body">
            <label class="catalog-field wa-quote-family">Prodotto<select data-quote-family><option value="hoodie">Felpe</option><option value="tshirt">T-shirt</option></select></label>
            <p data-quote-status role="status" aria-live="polite" class="wa-quote-status"></p>
            <div data-quote-fields hidden class="wa-quote-grid">
                <section class="wa-quote-controls" aria-label="Configurazione del preventivo">
                    <div class="catalog-form-grid">
                        <label data-quote-model-wrap class="catalog-field"><span>Tipo di felpa</span><select data-quote-model></select></label>
                        <label class="catalog-field"><span>Quantità</span><input data-quote-quantity type="number" step="1" inputmode="numeric"></label>
                        <input type="hidden" data-quote-print value="positions:">
                        @foreach(['front'=>'Fronte','back'=>'Retro','sleeve'=>'Manica'] as $position=>$label)<label class="catalog-field catalog-span-full" hidden><span>{{ $label }}</span><select data-quote-position="{{ $position }}"><option value="">Nessuna</option></select></label>@endforeach
                    </div>
                    <fieldset class="wa-quote-quality-field"><legend>Qualità da confrontare</legend><div data-quote-qualities></div></fieldset>
                    <button type="button" data-quote-generate class="catalog-button catalog-button-primary">Genera messaggio</button>
                    <p class="catalog-help">Prezzi dal catalogo CRM, IVA e spedizione calcolate per ogni soluzione.</p>
                </section>
                <section class="wa-quote-output" aria-label="Anteprima del messaggio">
                    <div data-quote-empty class="wa-quote-empty"><strong>Il preventivo comparirà qui</strong><p>Scegli le opzioni e premi “Genera messaggio”.</p></div>
                    <label data-quote-preview-wrap hidden class="catalog-field"><span>Anteprima messaggio</span><textarea data-quote-preview readonly rows="16"></textarea></label>
                    <button type="button" data-quote-insert hidden disabled class="catalog-button catalog-button-primary">Inserisci nella chat</button>
                    <p class="catalog-help">Il testo verrà inserito nel campo messaggio. Potrai modificarlo prima di inviarlo.</p>
                </section>
            </div>
        </div>
    </div>
</dialog>

@push('scripts')
<script>
(() => {
    const panel = document.querySelector('[data-hoodie-quote]');
    if (!panel) return;
    const find = (name) => panel.querySelector(`[data-quote-${name}]`);
    const status = find('status');
    const trigger = document.querySelector('[data-quote-open]');
    let inserted = false;
    const family = find('family');
    const model = find('model');
    const quantity = find('quantity');
    const print = find('print');
    const generate = find('generate');
    const insert = find('insert');
    const preview = find('preview');
    const positionSelects = [...panel.querySelectorAll('[data-quote-position]')];
    const selectedPositions = () => Object.fromEntries(positionSelects.filter(s => s.value).map(s => [s.dataset.quotePosition, Number(s.value)]));
    const encodePositions = selected => 'positions:' + ['front', 'back', 'sleeve'].filter(side => selected[side]).map(side => side + '=' + selected[side]).join(';');
    let catalog = null;
    let loading = false;
    let revision = 0;
    const invalidate = () => {
        revision++;
        insert.disabled = true;
        insert.hidden = true;
        find('preview-wrap').hidden = true;
        find('empty').hidden = false;
        status.textContent = '';
    };
    const syncPrints = (restore = selectedPositions()) => {
        if (!catalog) return;
        for (const select of positionSelects) {
            const side = select.dataset.quotePosition;
            const choices = {};
            for (const [quality, models] of Object.entries(catalog.position_catalog || {})) {
                const positions = models[catalog.family === 'tshirt' ? quality : model.value] || {};
                Object.assign(choices, positions[side] || {});
            }
            select.replaceChildren(new Option('Nessuna', ''));
            for (const [id, type] of Object.entries(choices)) select.add(new Option(type.label, id));
            select.closest('label').hidden = !Object.keys(choices).length;
            if (choices[restore?.[side]]) select.value = String(restore[side]);
        }
        print.value = encodePositions(selectedPositions());
    };
    const errorText = (data) => Object.values(data.errors || {}).flat().join(' ') || data.message || 'Impossibile preparare il preventivo. Riprova.';
    const loadCatalog = async (selectedFamily = null) => {
        if (loading) return;
        invalidate();
        catalog = null;
        loading = true;
        family.disabled = true;
        find('fields').hidden = true;
        status.textContent = 'Caricamento listino CRM…';
        try {
            const url = new URL(panel.dataset.url, window.location.origin);
            if (selectedFamily) url.searchParams.set('family', selectedFamily);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok) throw new Error(errorText(data));
            catalog = data;
            family.value = data.family;
            model.replaceChildren();
            print.replaceChildren();
            find('qualities').replaceChildren();
            for (const [value, label] of Object.entries(data.models)) model.add(new Option(label, value));
            // Positions are explicitly selected; there is no combined personalization menu.
            for (const [value, text] of Object.entries(data.qualities)) {
                const label = document.createElement('label');
                label.className = 'flex items-center gap-8 text-12 font-semibold';
                const input = document.createElement('input');
                input.type = 'checkbox';
                input.value = value;
                input.checked = data.defaults.qualities.includes(value);
                input.dataset.quoteQuality = '';
                const caption = document.createElement('span');
                caption.dataset.quoteQualityLabel = '';
                caption.dataset.label = text;
                label.append(input, caption);
                find('qualities').append(label);
            }
            model.value = data.defaults.model;
            find('model-wrap').hidden = data.family === 'tshirt';
            quantity.closest('label').classList.toggle('col-span-2', data.family === 'tshirt');
            quantity.min = data.minimum_quantity;
            quantity.max = data.maximum_quantity;
            quantity.value = data.defaults.quantity;
            syncPrints(data.defaults.positions || {});
            const available = syncQualities();
            find('fields').hidden = false;
            status.textContent = available ? '' : 'Nessun prodotto attivo associato. Configura le associazioni nel catalogo CRM.';
        } catch (error) {
            status.textContent = error.message;
        } finally { loading = false; family.disabled = false; }
    };
    const syncQualities = () => {
        if (!catalog) return;
        for (const input of panel.querySelectorAll('[data-quote-quality]')) {
            const productModel = catalog.family === 'tshirt' ? input.value : model.value;
            const product = catalog.products?.[productModel]?.[input.value];
            const allowed = catalog.position_catalog?.[input.value]?.[productModel] || {};
            const supported = Object.entries(selectedPositions()).every(([side, id]) => allowed[side]?.[id]);
            input.disabled = !product || !supported;
            if (!product) input.checked = false;
            const caption = input.parentElement.querySelector('[data-quote-quality-label]');
            caption.textContent = `${caption.dataset.label}${product ? ' · ' + product.code + (supported ? '' : ' · lavorazione non abilitata') : ' · prodotto non associato o inattivo'}`;
        }
        const available = Boolean(panel.querySelector('[data-quote-quality]:not(:disabled)'));
        generate.disabled = !available || !Object.keys(selectedPositions()).length;
        if (!panel.querySelector('[data-quote-quality]:checked')) {
            const first = panel.querySelector('[data-quote-quality]:not(:disabled)');
            if (first) first.checked = true;
        }
        return available;
    };
    trigger.addEventListener('click', () => {
        inserted = false;
        panel.showModal();
        if (!catalog && !loading) loadCatalog();
    });
    find('close').addEventListener('click', () => panel.close());
    panel.addEventListener('click', (event) => { if (event.target === panel) panel.close(); });
    panel.addEventListener('close', () => {
        if (inserted) document.getElementById('message-composer')?.focus();
        else trigger.focus();
    });
    family.addEventListener('change', () => loadCatalog(family.value));
    // This panel lives inside the chat form: Enter must never submit a message.
    panel.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.tagName === 'INPUT') event.preventDefault();
    });
    panel.addEventListener('input', invalidate);
    model.addEventListener('change', () => { syncPrints(); const available = syncQualities(); invalidate(); if (!available) status.textContent = 'Nessun prodotto attivo associato per questo modello.'; });
    panel.addEventListener('change', (event) => { if (event.target !== model && event.target !== family) { if (event.target.hasAttribute('data-quote-position')) { print.value = encodePositions(selectedPositions()); syncQualities(); } invalidate(); } });
    generate.addEventListener('click', async () => {
        invalidate();
        const currentRevision = revision;
        generate.disabled = true;
        status.textContent = 'Calcolo in corso…';
        try {
            const response = await fetch(panel.dataset.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                body: JSON.stringify({
                    positions: selectedPositions(), family: family.value, model: model.value, quantity: quantity.value, personalization: print.value,
                    qualities: Array.from(panel.querySelectorAll('[data-quote-quality]:checked')).map(input => input.value),
                }),
            });
            const data = await response.json();
            if (revision !== currentRevision) return;
            if (!response.ok) throw new Error(errorText(data));
            preview.value = data.message;
            find('preview-wrap').hidden = false;
            find('empty').hidden = true;
            insert.disabled = false;
            insert.hidden = false;
            status.textContent = 'Prezzi, IVA e spedizione calcolati. Controlla il testo prima di inserirlo nella chat.';
        } catch (error) {
            if (revision === currentRevision) status.textContent = error.message;
        } finally { generate.disabled = loading || !Object.keys(selectedPositions()).length || !panel.querySelector('[data-quote-quality]:not(:disabled)'); }
    });
    insert.addEventListener('click', () => {
        if (insert.disabled) return;
        const composer = document.getElementById('message-composer');
        if (!composer || composer.disabled) {
            status.textContent = 'Seleziona “Messaggio libero” per inserire il preventivo.';
            return;
        }
        const text = composer.value.trim() ? `${composer.value.trim()}\n\n${preview.value}` : preview.value;
        if (text.length > composer.maxLength) {
            status.textContent = 'Il testo supera il limite della chat. Riduci il messaggio già presente o le qualità confrontate.';
            return;
        }
        composer.value = text;
        composer.dispatchEvent(new Event('input', { bubbles: true }));
        inserted = true;
        panel.close();
        composer.focus();
        insert.disabled = true;
        status.textContent = 'Preventivo inserito nel campo messaggio. Puoi modificarlo e inviarlo quando sei pronto.';
    });
})();
</script>
@endpush
