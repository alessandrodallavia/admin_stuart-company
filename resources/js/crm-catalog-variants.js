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
            row.setAttribute('aria-label', `Colore ${index + 1}`);
            colors.append(row);
            paintColor(row);
            colorStatus();
            if (focus) row.querySelector('[data-color-name]').focus();
        };
        const sizeStatus = () => {
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
        (initial.colors ?? []).forEach((color) => addColor(color));
        (initial.sizes ?? []).forEach(addSize);
        colorStatus();
        sizeStatus();
    });
}
