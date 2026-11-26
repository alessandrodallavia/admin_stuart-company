const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { runInNewContext } = require('node:vm');
function fixture({ value = 'in_production', checked = true } = {}) {
  const documentHandlers = {}, dialogHandlers = {}, buttonHandlers = {};
  const notify = {}, message = {}, help = {}, choice = { checked };
  const button = { disabled: false, focus() {} };
  let requests = 0;
  const field = { value, selectedOptions: [{ textContent: value === 'ready' ? 'Pronto' : 'In lavorazione' }] };
  const dialog = { open: false, querySelector: selector => ({ '[data-order-confirm-notify]': notify, '[data-order-confirm-message]': message, '[data-order-confirm-help]': help, '[data-order-confirm-cancel]': { addEventListener: (_, fn) => { buttonHandlers.cancel = fn; } }, '[data-order-confirm-submit]': { addEventListener: (_, fn) => { buttonHandlers.confirm = fn; } } })[selector], addEventListener: (name, fn) => { dialogHandlers[name] = fn; }, showModal() { this.open = true; }, close() { this.open = false; dialogHandlers.close(); } };
  const form = { dataset: { orderConfirmField: 'order_status', orderNotifyValues: 'in_production,shipped' }, matches: () => true, elements: { namedItem: () => field }, querySelector: () => choice, requestSubmit(submitter) { const event = { target: this, submitter, prevented: false, preventDefault() { this.prevented = true; } }; documentHandlers.submit(event); if (!event.prevented) requests++; } };
  const document = { querySelector: () => dialog, addEventListener: (name, fn) => { documentHandlers[name] = fn; } };
  runInNewContext(readFileSync('resources/js/order-status-confirmation.js','utf8'), { document });
  return { dialog, notify, choice, button, help, message, form, submit: () => form.requestSubmit(button), cancel: () => buttonHandlers.cancel(), confirm: () => buttonHandlers.confirm(), requests: () => requests };
}
test('cancelling the confirmation never submits the state change', () => {
  const state = fixture(); state.submit();
  assert.equal(state.dialog.open,true); assert.equal(state.requests(),0); assert.equal(state.notify.checked,true);
  state.cancel(); assert.equal(state.dialog.open,false); assert.equal(state.requests(),0);
});
test('deselecting notifications is forwarded to the confirmed form and a second submit is blocked', () => {
  const state = fixture(); state.submit(); state.notify.checked = false; state.confirm();
  assert.equal(state.choice.checked,false); assert.equal(state.requests(),1); assert.equal(state.button.disabled,true);
  state.submit(); assert.equal(state.requests(),1);
});
test('the preselected notification preference is preserved and unsupported states are explained', () => {
  const silent = fixture({ checked: false }); silent.submit(); assert.equal(silent.notify.checked,false); silent.confirm(); assert.equal(silent.choice.checked,false);
  const ready = fixture({ value: 'ready' }); ready.submit(); assert.equal(ready.notify.disabled,true); assert.match(ready.help.textContent,/non è prevista/); ready.confirm(); assert.equal(ready.choice.checked,false);
});
