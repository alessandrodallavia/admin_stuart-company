import { initializeVariantEditors } from './crm-catalog-variants';

function initializeCatalog() {
    const root = document.querySelector('[data-crm-catalog]');
    if (!root || root.dataset.initialized) return;
    root.dataset.initialized = 'true';

    const history = [];
    const triggers = new WeakMap();
    let deleteForm = null;

    const updateScrollLock = () => {
        document.body.classList.toggle('catalog-modal-open', !!root.querySelector('dialog[open]'));
    };
    const getDialog = (id) => {
        const existing = document.getElementById(id);
        if (existing instanceof HTMLDialogElement && root.contains(existing)) return existing;
        const template = document.getElementById(`${id}-template`);
        if (!(template instanceof HTMLTemplateElement) || !root.contains(template)) return null;
        const dialog = template.content.querySelector('dialog')?.cloneNode(true);
        if (!(dialog instanceof HTMLDialogElement)) return null;
        prepareDialog(dialog);
        root.append(dialog);
        return dialog;
    };
    const openDialog = (id, trigger = null) => {
        const dialog = getDialog(id);
        if (!dialog) return;
        const current = root.querySelector('dialog[open]');
        if (current === dialog) return;
        if (current) {
            history.push({ dialog: current, trigger });
            current.dataset.switching = 'true';
            current.close();
        }
        if (trigger) triggers.set(dialog, trigger);
        initializeVariantEditors(dialog);
        dialog.showModal();
        updateScrollLock();
    };
    const prepareDialog = (dialog) => {
        dialog.addEventListener('close', () => {
            if (dialog.dataset.switching) {
                delete dialog.dataset.switching;
                return;
            }
            const previous = history.pop();
            const returnFocus = triggers.get(dialog);
            // Closed forms must leave the document, otherwise Chrome and
            // autofill extensions still discover every hidden catalog field.
            dialog.remove();
            triggers.delete(dialog);
            if (previous) {
                previous.dialog.showModal();
                previous.trigger?.focus();
            } else {
                returnFocus?.focus();
            }
            updateScrollLock();
        });
        dialog.addEventListener('click', (event) => {
            if (event.target !== dialog) return;
            const bounds = dialog.getBoundingClientRect();
            if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
        });
    };

    const selectTab = (button, local = false) => {
        const group = local ? button.closest('[data-catalog-local-tabs]') : root;
        const attribute = local ? 'data-catalog-local-tab' : 'data-catalog-tab';
        const panelAttribute = local ? 'data-catalog-local-panel' : 'data-catalog-panel';
        const target = button.getAttribute(attribute);
        group.querySelectorAll(`[${attribute}]`).forEach((tab) => {
            const selected = tab === button;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
        });
        group.querySelectorAll(`[${panelAttribute}]`).forEach((panel) => { panel.hidden = panel.id !== target; });
        if (!local) {
            root.querySelectorAll('[name="section"]').forEach((field) => { field.value = target.replace('catalog-panel-', ''); });
            const url = new URL(window.location.href);
            url.searchParams.set('section', target.replace('catalog-panel-', ''));
            window.history.replaceState(null, '', url);
        }
    };
    root.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        if (button.hasAttribute('data-catalog-open')) openDialog(button.dataset.catalogOpen, button);
        if (button.hasAttribute('data-catalog-close')) button.closest('dialog')?.close();
        if (button.matches('[data-catalog-tab], [data-catalog-local-tab]')) selectTab(button, button.hasAttribute('data-catalog-local-tab'));
    });
    root.addEventListener('keydown', (event) => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        const current = event.target.closest('[role="tab"]');
        if (!current) return;
        const tabs = [...current.closest('[role="tablist"]').querySelectorAll('[role="tab"]')];
        let index = tabs.indexOf(current);
        index = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
        event.preventDefault();
        tabs[index].click();
        tabs[index].focus();
    });

    root.addEventListener('submit', (event) => {
        const form = event.target;
        if (!form.hasAttribute('data-catalog-delete') || form.dataset.confirmed) return;
        event.preventDefault();
        deleteForm = form;
        const confirmation = getDialog('catalog-delete');
        if (!confirmation) return;
        confirmation.querySelector('[data-catalog-delete-message]').textContent = form.dataset.catalogDelete;
        openDialog('catalog-delete', form.querySelector('button'));
    });
    root.addEventListener('click', (event) => {
        if (!event.target.closest('[data-catalog-confirm-delete]') || !deleteForm) return;
        deleteForm.dataset.confirmed = 'true';
        deleteForm.requestSubmit();
    });

    const filterProducts = () => {
        const brand = root.querySelector('[data-catalog-brand-filter]')?.value || '';
        const visibility = root.querySelector('[data-catalog-visibility-filter]')?.value || '';
        let count = 0;
        root.querySelectorAll('[data-catalog-product-row]').forEach((row) => {
            const visible = (!brand || row.dataset.brand === brand) && (!visibility || row.dataset.visibility === visibility);
            row.hidden = !visible;
            if (visible) count++;
        });
        const counter = root.querySelector('[data-catalog-product-count]');
        if (counter) counter.textContent = `${count} ${count === 1 ? 'prodotto' : 'prodotti'}`;
        const empty = root.querySelector('[data-catalog-filter-empty]');
        if (empty) empty.hidden = count > 0;
    };
    root.querySelectorAll('[data-catalog-brand-filter], [data-catalog-visibility-filter]').forEach((select) => select.addEventListener('change', filterProducts));
    filterProducts();
    const restoredDialog = root.dataset.restoreDialog;
    if (restoredDialog) {
        const parent = getDialog(restoredDialog)?.dataset.catalogParent;
        if (parent) {
            openDialog(parent);
            const pricingTab = document.getElementById(parent)?.querySelector('[data-catalog-local-tab$="-pricing"]');
            if (pricingTab) selectTab(pricingTab, true);
        }
        openDialog(restoredDialog);
    }
}

document.addEventListener('DOMContentLoaded', initializeCatalog);
document.addEventListener('livewire:navigated', initializeCatalog);
