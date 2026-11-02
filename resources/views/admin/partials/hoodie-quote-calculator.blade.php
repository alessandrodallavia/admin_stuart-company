<details data-hoodie-quote data-url="{{ route('admin.conversations.hoodie-quote.catalog', $selectedConversation) }}" class="rounded-10 border border-gray-mid bg-gray-light p-12">
    <summary class="cursor-pointer text-14 font-bold text-black-nike">Prepara preventivo felpe</summary>
    <p class="mt-8 text-12 text-gray">Confronta le qualità e prepara un messaggio modificabile prima dell'invio.</p>
    <div data-quote-fields hidden class="mt-12 grid gap-12">
        <div class="grid grid-cols-2 gap-12">
            <label class="text-12 font-bold">Tipo di felpa
                <select data-quote-model class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14"></select>
            </label>
            <label class="text-12 font-bold">Quantità
                <input data-quote-quantity type="number" step="1" inputmode="numeric" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14">
            </label>
        </div>
        <label class="text-12 font-bold">Personalizzazione
            <select data-quote-print class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14"></select>
        </label>
        <fieldset>
            <legend class="text-12 font-bold">Qualità da confrontare</legend>
            <div data-quote-qualities class="mt-8 flex flex-wrap gap-12"></div>
        </fieldset>
        <button type="button" data-quote-generate class="rounded-10 bg-black-nike px-12 py-10 text-12 font-bold text-white">Genera messaggio</button>
        <label data-quote-preview-wrap hidden class="text-12 font-bold">Anteprima messaggio
            <textarea data-quote-preview readonly rows="14" class="mt-6 w-full rounded-10 border-gray-mid bg-white px-12 py-10 text-14"></textarea>
        </label>
        <button type="button" data-quote-insert hidden disabled class="rounded-10 bg-bullstar px-12 py-10 text-12 font-bold text-white">Inserisci nella chat</button>
    </div>
    <p data-quote-status role="status" aria-live="polite" class="mt-8 text-12 font-semibold text-gray"></p>
</details>

@push('scripts')
<script>
(() => {
    const panel = document.querySelector('[data-hoodie-quote]');
    if (!panel) return;
    const find = (name) => panel.querySelector(`[data-quote-${name}]`);
    const status = find('status');
    const model = find('model');
    const quantity = find('quantity');
    const print = find('print');
    const generate = find('generate');
    const insert = find('insert');
    const preview = find('preview');
    let catalog = null;
    let loading = false;
    let revision = 0;
    const invalidate = () => {
        revision++;
        insert.disabled = true;
        insert.hidden = true;
        find('preview-wrap').hidden = true;
        status.textContent = '';
    };
    const syncPrints = () => {
        for (const option of print.options) {
            const allowed = catalog.personalization_models[option.value];
            option.disabled = Boolean(allowed && !allowed.includes(model.value));
            option.hidden = option.disabled;
        }
        if (print.selectedOptions[0]?.disabled) {
            print.value = Array.from(print.options).find(o => !o.disabled)?.value || '';
        }
    };
    const errorText = (data) => Object.values(data.errors || {}).flat().join(' ') || data.message || 'Impossibile preparare il preventivo. Riprova.';
    panel.addEventListener('toggle', async () => {
        if (!panel.open || catalog || loading) return;
        loading = true;
        status.textContent = 'Caricamento listino…';
        try {
            const response = await fetch(panel.dataset.url, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok) throw new Error(errorText(data));
            catalog = data;
            for (const [value, label] of Object.entries(data.models)) model.add(new Option(label, value));
            for (const [value, label] of Object.entries(data.personalizations)) print.add(new Option(label, value));
            for (const [value, text] of Object.entries(data.qualities)) {
                const label = document.createElement('label');
                label.className = 'flex items-center gap-8 text-12 font-semibold';
                const input = document.createElement('input');
                input.type = 'checkbox';
                input.value = value;
                input.checked = data.defaults.qualities.includes(value);
                input.dataset.quoteQuality = '';
                label.append(input, document.createTextNode(text));
                find('qualities').append(label);
            }
            model.value = data.defaults.model;
            quantity.min = data.minimum_quantity;
            quantity.max = data.maximum_quantity;
            quantity.value = data.defaults.quantity;
            print.value = data.defaults.personalization;
            syncPrints();
            find('fields').hidden = false;
            status.textContent = '';
        } catch (error) {
            status.textContent = error.message;
        } finally { loading = false; }
    });
    // This panel lives inside the chat form: Enter must never submit a message.
    panel.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.tagName === 'INPUT') event.preventDefault();
    });
    panel.addEventListener('input', invalidate);
    model.addEventListener('change', () => { syncPrints(); invalidate(); });
    panel.addEventListener('change', (event) => { if (event.target !== model) invalidate(); });
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
                    model: model.value, quantity: quantity.value, personalization: print.value,
                    qualities: Array.from(panel.querySelectorAll('[data-quote-quality]:checked')).map(input => input.value),
                }),
            });
            const data = await response.json();
            if (revision !== currentRevision) return;
            if (!response.ok) throw new Error(errorText(data));
            preview.value = data.message;
            find('preview-wrap').hidden = false;
            insert.disabled = false;
            insert.hidden = false;
            status.textContent = 'Prezzi, IVA e spedizione calcolati. Controlla il testo prima di inserirlo nella chat.';
        } catch (error) {
            if (revision === currentRevision) status.textContent = error.message;
        } finally { generate.disabled = false; }
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
        composer.focus();
        insert.disabled = true;
        status.textContent = 'Preventivo inserito nel campo messaggio. Puoi modificarlo e inviarlo quando sei pronto.';
    });
})();
</script>
@endpush
