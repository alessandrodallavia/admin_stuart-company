const dialog = document.querySelector('#order-update-confirmation');
if (dialog) {
    let pending = null;
    let returnFocus = null;
    const approved = new WeakSet();
    const message = dialog.querySelector('[data-order-confirm-message]');
    const notify = dialog.querySelector('[data-order-confirm-notify]');
    const help = dialog.querySelector('[data-order-confirm-help]');

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!form.matches('[data-confirm-order-update]')) return;
        if (form.dataset.orderSubmitting) { event.preventDefault(); return; }
        if (approved.has(form)) {
            approved.delete(form);
            form.dataset.orderSubmitting = 'true';
            if (event.submitter) event.submitter.disabled = true;
            return;
        }
        event.preventDefault();
        const field = form.elements.namedItem(form.dataset.orderConfirmField || '');
        const label = field?.selectedOptions?.[0]?.textContent?.trim();
        const available = form.dataset.orderNotifyAvailable === 'true' || (form.dataset.orderNotifyValues || '').split(',').includes(field?.value);
        const choice = form.querySelector('[data-notify-customer]');
        message.textContent = form.dataset.orderConfirmMessage || (label ? `Confermi l’aggiornamento a “${label}”?` : 'Confermi l’aggiornamento dell’ordine?');
        notify.disabled = !available;
        notify.checked = available && Boolean(choice?.checked);
        help.textContent = available ? 'La scelta vale solo per questo aggiornamento. Togli la spunta per modificare lo stato senza inviare comunicazioni.' : 'Per questo aggiornamento non è prevista alcuna comunicazione automatica al cliente.';
        pending = { form, submitter: event.submitter, choice, available };
        returnFocus = event.submitter;
        dialog.showModal();
    });
    dialog.querySelector('[data-order-confirm-cancel]').addEventListener('click', () => dialog.close());
    dialog.querySelector('[data-order-confirm-submit]').addEventListener('click', () => {
        if (!pending) return;
        const { form, submitter, choice, available } = pending;
        if (choice) choice.checked = available && notify.checked;
        approved.add(form);
        dialog.close();
        form.requestSubmit(submitter || undefined);
    });
    dialog.addEventListener('close', () => { pending = null; returnFocus?.focus(); returnFocus = null; });
}
