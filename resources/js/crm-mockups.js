const init = () => {
    const root = document.querySelector('[data-crm-mockups]');
    if (!root || root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    const dialog = root.querySelector('[data-mockup-library]');
    if (!dialog) return;
    const library = JSON.parse(root.querySelector('[data-mockup-library-data]').textContent);
    const search = root.querySelector('[data-mockup-search]');
    const gallery = root.querySelector('[data-mockup-gallery]');
    const more = root.querySelector('[data-mockup-more]');
    const status = root.querySelector('[data-mockup-status]');
    const sides = [...root.querySelectorAll('[data-mockup-side]')];
    let selectedSide = null;
    let returnFocus = null;
    let limit = 24;
    const blobs = new Map();
    const preview = (section, url, path) => {
        const image = section.querySelector('[data-mockup-preview]');
        image.hidden = !url;
        if (url) image.src = url;
        else image.removeAttribute('src');
        section.querySelector('[data-mockup-empty]').hidden = Boolean(url);
        section.querySelector('[data-mockup-filename]').textContent = path || 'Anteprima non disponibile';
        status.textContent = 'Modifiche non salvate. Premi “Salva mockup del colore” per applicarle.';
    };
    const clearUpload = (section) => {
        section.querySelector('[data-mockup-upload]').value = '';
        if (blobs.has(section)) URL.revokeObjectURL(blobs.get(section));
        blobs.delete(section);
    };
    const choose = (asset) => {
        if (!selectedSide) return;
        clearUpload(selectedSide);
        selectedSide.querySelector('[data-mockup-action]').value = 'library';
        selectedSide.querySelector('[data-mockup-asset]').value = asset.path;
        preview(selectedSide, asset.url, asset.path);
        dialog.close();
    };
    const render = () => {
        const query = search.value.trim().toLocaleLowerCase();
        const filtered = library.filter((asset) => asset.path.toLocaleLowerCase().includes(query));
        gallery.replaceChildren();
        for (const asset of filtered.slice(0, limit)) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'catalog-mockup-library-item';
            button.title = asset.path;
            const img = document.createElement('img');
            img.src = asset.url;
            img.loading = 'lazy';
            img.alt = '';
            const name = document.createElement('span');
            name.textContent = asset.path;
            button.append(img, name);
            button.addEventListener('click', () => choose(asset));
            gallery.append(button);
        }
        root.querySelector('[data-mockup-library-count]').textContent = `${Math.min(filtered.length, limit)} di ${filtered.length} immagini`;
        more.hidden = filtered.length <= limit;
    };
    root.querySelectorAll('[data-mockup-browse]').forEach((button) => button.addEventListener('click', () => {
        selectedSide = sides.find((section) => section.dataset.mockupSide === button.dataset.mockupBrowse);
        returnFocus = button;
        search.value = '';
        limit = 24;
        render();
        dialog.showModal();
        search.focus();
    }));
    root.querySelector('[data-mockup-library-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => returnFocus?.focus());
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    search.addEventListener('input', () => { limit = 24; render(); });
    more.addEventListener('click', () => { limit += 24; render(); });
    for (const section of sides) {
        section.querySelector('[data-mockup-upload]').addEventListener('change', (event) => {
            const file = event.target.files[0];
            if (!file) return;
            if (blobs.has(section)) URL.revokeObjectURL(blobs.get(section));
            const blob = URL.createObjectURL(file);
            blobs.set(section, blob);
            section.querySelector('[data-mockup-action]').value = 'upload';
            preview(section, blob, file.name);
        });
        section.querySelector('[data-mockup-clear]').addEventListener('click', () => {
            clearUpload(section);
            section.querySelector('[data-mockup-action]').value = 'clear';
            section.querySelector('[data-mockup-asset]').value = '';
            preview(section, '', '');
        });
        section.querySelector('[data-mockup-reset]').addEventListener('click', () => {
            clearUpload(section);
            section.querySelector('[data-mockup-action]').value = 'keep';
            section.querySelector('[data-mockup-asset]').value = '';
            preview(section, section.dataset.originalUrl, section.dataset.originalPath);
        });
    }
    window.addEventListener('beforeunload', () => { for (const blob of blobs.values()) URL.revokeObjectURL(blob); });
};
document.addEventListener('DOMContentLoaded', init);
document.addEventListener('livewire:navigated', init);
