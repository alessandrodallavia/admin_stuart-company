const normalize = (value) => value.trim().toLocaleLowerCase('it');
const isHex = (value) => /^#[0-9a-f]{6}$/i.test(value);

// Build controls only for the product being opened, rather than creating
// every product's variant controls and listeners at page load.
export function initializeVariantEditors(scope) {
    scope.querySelectorAll('[data-catalog-variants]').forEach((editor) => {
        if (editor.dataset.initialized) return;
        editor.dataset.initialized = 'true';
        const initial = JSON.parse(editor.querySelector('[data-variant-data]').textContent);
        const colors = editor.querySelector('[data-color-list]');
        const colorTemplate = editor.querySelector('[data-color-template]');
        const sizes = editor.querySelector('[data-size-list]');
        let nextColor = 0;

        const colorStatus = () => {
            const count = colors.children.length;
            editor.querySelector('[data-color-empty]').hidden = count !== 0;
            editor.querySelector('[data-color-count]').textContent = `${count} ${count === 1 ? 'colore' : 'colori'}`;
            editor.querySelector('[data-color-add]').disabled = count >= 200;
        };
        const paintColor = (row) => {
            const value = row.querySelector('[data-color-hex]').value.trim();
            const valid = isHex(value);
            const preview = row.querySelector('[data-color-preview]');
            preview.style.backgroundColor = valid ? value : '';
            preview.classList.toggle('catalog-color-unset', !valid);
            row.querySelector('[data-color-hex]').setCustomValidity(value && !valid ? 'Inserisci un codice nel formato #RRGGBB, oppure lascia vuoto.' : '');
            if (valid) row.querySelector('[data-color-picker]').value = value;
        };
        const sizeNames = () => [...sizes.querySelectorAll('input')].map((input) => input.value);
        const refreshColorSizes = (row, renderOptions = true) => {
            const names = sizeNames();
            const restricted = row.querySelector('[data-color-size-mode]').value === '1';
            const chosen = new Set([...row.allowedSizes].map(normalize));
            row.allowedSizes = names.filter((name) => chosen.has(normalize(name)));
            row.querySelector('[data-color-size-summary]').textContent = restricted
                ? (row.allowedSizes.length ? row.allowedSizes.join(' · ') : 'Nessuna taglia abilitata per questo colore.')
                : (names.length ? `Tutte: ${names.join(' · ')}` : 'Aggiungi le taglie generali del prodotto.');
            const details = row.querySelector('[data-color-size-details]');
            details.hidden = !restricted;
            const values = row.querySelector('[data-color-size-values]');
            values.replaceChildren();
            if (restricted) row.allowedSizes.forEach((name) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `colors[${row.dataset.colorIndex}][sizes][]`;
                input.value = name;
                values.append(input);
            });
            if (!renderOptions) return;
            const options = row.querySelector('[data-color-size-options]');
            // Create checkboxes only when this color's selector is expanded.
            options.replaceChildren();
            if (!details.open || !restricted) return;
            if (!names.length) {
                options.textContent = 'Aggiungi prima le taglie generali del prodotto.';
                return;
            }
            names.forEach((name) => {
                const label = document.createElement('label');
                label.className = 'catalog-check';
                const input = document.createElement('input');
                input.type = 'checkbox';
                input.dataset.colorSizeOption = name;
                input.checked = row.allowedSizes.includes(name);
                label.append(input, document.createTextNode(name));
                options.append(label);
            });
        };
        const addColor = (value = {}, focus = false) => {
            if (colors.children.length >= 200) return;
            if (!value || typeof value !== 'object') value = {};
            const row = colorTemplate.content.firstElementChild.cloneNode(true);
            const index = nextColor++;
            for (const field of ['code', 'name', 'hex']) {
                const input = row.querySelector(`[data-color-${field}]`);
                input.name = `colors[${index}][${field}]`;
                input.value = value[field] ?? '';
            }
            row.dataset.colorIndex = index;
            row.allowedSizes = Array.isArray(value.sizes) ? [...value.sizes] : [];
            const mode = row.querySelector('[data-color-size-mode]');
            mode.name = `colors[${index}][sizes_restricted]`;
            mode.value = value.sizes_restricted === true || String(value.sizes_restricted) === '1' ? '1' : '0';
            row.querySelector('[data-color-size-details]').addEventListener('toggle', () => refreshColorSizes(row));
            row.setAttribute('aria-label', `Colore ${index + 1}`);
            colors.append(row);
            paintColor(row);
            refreshColorSizes(row);
            colorStatus();
            if (focus) row.querySelector('[data-color-name]').focus();
        };
        const sizeStatus = () => {
            colors.querySelectorAll('[data-color-row]').forEach((row) => refreshColorSizes(row));
            const selected = [...sizes.querySelectorAll('input')].map((input) => normalize(input.value));
            editor.querySelector('[data-size-empty]').hidden = selected.length !== 0;
            editor.querySelector('[data-size-count]').textContent = `${selected.length} ${selected.length === 1 ? 'taglia' : 'taglie'}`;
            editor.querySelectorAll('[data-size-preset]').forEach((button) => {
                button.setAttribute('aria-pressed', String(selected.includes(normalize(button.dataset.sizePreset))));
            });
        };
        const announceSize = (message) => { editor.querySelector('[data-size-message]').textContent = message; };
        const addSize = (value) => {
            const name = String(value).trim();
            if (!name) return false;
            if (name.length > 50 || sizes.children.length >= 100) {
                announceSize('Massimo 100 taglie, con nomi fino a 50 caratteri.');
                return false;
            }
            if ([...sizes.querySelectorAll('input')].some((input) => normalize(input.value) === normalize(name))) {
                announceSize(`${name} è già selezionata.`);
                return false;
            }
            const chip = document.createElement('span');
            chip.className = 'catalog-selected-size';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'sizes[]';
            input.value = name;
            const label = document.createElement('span');
            label.textContent = name;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.dataset.sizeRemove = '';
            remove.setAttribute('aria-label', `Rimuovi taglia ${name}`);
            remove.textContent = '×';
            chip.append(input, label, remove);
            sizes.append(chip);
            sizeStatus();
            announceSize('');
            return true;
        };
        const addCustomSize = () => {
            const input = editor.querySelector('[data-size-custom]');
            if (addSize(input.value)) input.value = '';
            input.focus();
        };
        editor.addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) return;
            if (button.hasAttribute('data-color-add')) addColor({}, true);
            if (button.hasAttribute('data-color-remove')) {
                const row = button.closest('[data-color-row]');
                const next = row.nextElementSibling ?? row.previousElementSibling;
                row.remove();
                colorStatus();
                (next?.querySelector('[data-color-name]') ?? editor.querySelector('[data-color-add]')).focus();
            }
            if (button.hasAttribute('data-size-preset')) {
                const existing = [...sizes.querySelectorAll('input')].find((input) => normalize(input.value) === normalize(button.dataset.sizePreset));
                if (existing) {
                    existing.parentElement.remove();
                    sizeStatus();
                    announceSize('');
                } else addSize(button.dataset.sizePreset);
            }
            if (button.hasAttribute('data-size-group')) {
                button.dataset.sizeGroup.split(',').forEach(addSize);
                announceSize('');
            }
            if (button.hasAttribute('data-size-add')) addCustomSize();
            if (button.hasAttribute('data-size-remove')) {
                button.parentElement.remove();
                sizeStatus();
                editor.querySelector('[data-size-custom]').focus();
                announceSize('');
            }
        });
        editor.addEventListener('change', (event) => {
            if (event.target.hasAttribute('data-color-size-mode')) {
                const row = event.target.closest('[data-color-row]');
                if (event.target.value === '1') row.allowedSizes = sizeNames();
                row.querySelector('[data-color-size-details]').open = event.target.value === '1';
                refreshColorSizes(row);
            }
            if (event.target.hasAttribute('data-color-size-option')) {
                const row = event.target.closest('[data-color-row]');
                const name = event.target.dataset.colorSizeOption;
                row.allowedSizes = event.target.checked ? [...row.allowedSizes, name] : row.allowedSizes.filter((size) => size !== name);
                refreshColorSizes(row, false);
            }
        });
        editor.addEventListener('input', (event) => {
            if (event.target.hasAttribute('data-color-picker')) {
                const row = event.target.closest('[data-color-row]');
                row.querySelector('[data-color-hex]').value = event.target.value.toUpperCase();
                paintColor(row);
            } else if (event.target.hasAttribute('data-color-hex')) paintColor(event.target.closest('[data-color-row]'));
        });
        editor.querySelector('[data-size-custom]').addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                addCustomSize();
            }
        });
        (initial.sizes ?? []).forEach(addSize);
        (initial.colors ?? []).forEach((color) => addColor(color));
        colorStatus();
        sizeStatus();
    });
}
