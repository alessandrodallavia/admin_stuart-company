@php
    $paymentMethods = $paymentMethods ?? collect();
    $oldItems = old('items');
    $items = $oldItems ?: ($document->exists ? $document->items->map(fn ($item) => [
        'item_code' => $item->item_code,
        'description' => $item->description,
        'quantity' => $item->quantity,
        'unit_price' => $item->unit_price,
        'vat_rate' => $item->vat_rate,
    ])->all() : [['item_code' => '', 'description' => '', 'quantity' => 0, 'unit_price' => 0, 'vat_rate' => 22]]);

    $oldPayments = old('payments');
    $payments = $oldPayments ?: ($document->exists ? $document->paymentSchedules->map(fn ($payment) => [
        'due_date' => optional($payment->due_date)->format('Y-m-d'),
        'method' => $payment->method,
        'payment_method_code' => $payment->payment_method_code ?: 'MP05',
        'amount' => $payment->amount,
        'paid_amount' => $payment->paid_amount,
        'paid_at' => optional($payment->paid_at)->format('Y-m-d'),
        'notes' => $payment->notes,
    ])->all() : [['due_date' => now()->format('Y-m-d'), 'method' => 'Bonifico bancario', 'payment_method_code' => 'MP05', 'amount' => 0, 'paid_amount' => 0, 'paid_at' => '', 'notes' => '']]);
@endphp

<form method="POST" action="{{ $document->exists ? route('admin.documents.update', $document) : route('admin.documents.store') }}" class="space-y-16" data-document-form>
    @csrf
    @if ($document->exists)
        @method('PATCH')
    @endif

    <section class="rounded-10 border border-gray-mid bg-white p-16">
        <div class="grid gap-12 lg:grid-cols-4">
            <label class="block">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Tipo</span>
                <select name="type" data-document-type class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $document->type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block" data-fiscal-type-field>
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Tipo documento SDI</span>
                <select name="fiscal_type" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    @foreach (config('documents.invoice_fiscal_types') as $value => $label)
                        <option value="{{ $value }}" @selected(old('fiscal_type', $document->fiscal_type ?: 'TD01') === $value)>{{ $value }} - {{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Data</span>
                <input name="document_date" value="{{ old('document_date', optional($document->document_date)->format('Y-m-d') ?: now()->format('Y-m-d')) }}" type="date" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
            </label>
            <label class="block">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Stato</span>
                <select name="status" data-document-status class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $document->status ?: 'draft') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Valuta</span>
                <input name="currency" value="{{ old('currency', $document->currency ?: 'EUR') }}" maxlength="3" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold uppercase focus:border-bullstar focus:ring-bullstar">
            </label>
            <label class="block">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Condizioni pagamento</span>
                <select name="payment_conditions" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    <option value="TP00" @selected(old('payment_conditions', $document->payment_conditions ?: 'TP02') === 'TP00')>TP00 - Seleziona metodo...</option>
                    <option value="TP02" @selected(old('payment_conditions', $document->payment_conditions ?: 'TP02') === 'TP02')>TP02 - Pagamento completo</option>
                    <option value="TP01" @selected(old('payment_conditions', $document->payment_conditions ?: 'TP02') === 'TP01')>TP01 - Pagamento a rate</option>
                </select>
            </label>
        </div>
    </section>

    <section class="rounded-10 border border-gray-mid bg-white p-16">
        <div class="mb-12">
            <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Cliente</p>
            <h2 class="mt-4 text-20 font-black leading-tight">Dati intestazione</h2>
        </div>
        <div class="grid gap-12 lg:grid-cols-3">
            <label class="block lg:col-span-2">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Nome cliente</span>
                <input name="customer_name" value="{{ old('customer_name', $document->customer_name) }}" required maxlength="255" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
            </label>
            <label class="block">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Email</span>
                <input name="customer_email" value="{{ old('customer_email', $document->customer_email) }}" type="email" maxlength="255" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
            </label>
            <label class="block">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Telefono</span>
                <input name="customer_phone" value="{{ old('customer_phone', $document->customer_phone) }}" maxlength="40" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
            </label>
            <label class="block">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Codice fiscale</span>
                <input name="customer_tax_code" value="{{ old('customer_tax_code', $document->customer_tax_code) }}" maxlength="40" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold uppercase focus:border-bullstar focus:ring-bullstar">
            </label>
            <label class="block">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Partita IVA</span>
                <input name="customer_vat_number" value="{{ old('customer_vat_number', $document->customer_vat_number) }}" maxlength="40" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold uppercase focus:border-bullstar focus:ring-bullstar">
            </label>
            <label class="block">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Codice destinatario</span>
                <input name="customer_recipient_code" value="{{ old('customer_recipient_code', $document->customer_recipient_code) }}" maxlength="7" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold uppercase focus:border-bullstar focus:ring-bullstar">
            </label>
            <label class="block lg:col-span-2">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">PEC</span>
                <input name="customer_pec" value="{{ old('customer_pec', $document->customer_pec) }}" type="email" maxlength="255" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
            </label>
            <label class="block lg:col-span-2">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Indirizzo</span>
                <input name="customer_address" value="{{ old('customer_address', $document->customer_address) }}" maxlength="255" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
            </label>
            <label class="block">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Numero civico</span>
                <input name="customer_street_number" value="{{ old('customer_street_number', $document->customer_street_number) }}" maxlength="30" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
            </label>
            <label class="block">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Città</span>
                <input name="customer_city" value="{{ old('customer_city', $document->customer_city) }}" maxlength="120" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
            </label>
            <label class="block">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Provincia</span>
                <input name="customer_province" value="{{ old('customer_province', $document->customer_province) }}" maxlength="10" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold uppercase focus:border-bullstar focus:ring-bullstar">
            </label>
            <label class="block">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">CAP</span>
                <input name="customer_postal_code" value="{{ old('customer_postal_code', $document->customer_postal_code) }}" maxlength="20" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
            </label>
            <label class="block">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Paese</span>
                <input name="customer_country" value="{{ old('customer_country', $document->customer_country ?: 'IT') }}" maxlength="2" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold uppercase focus:border-bullstar focus:ring-bullstar">
            </label>
        </div>
    </section>

    <section class="overflow-hidden rounded-10 border border-gray-mid bg-white">
        <div class="flex items-center justify-between gap-12 border-b border-gray-mid px-16 py-12">
            <div>
                <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Righe</p>
                <h2 class="mt-4 text-20 font-black leading-tight">Articoli e servizi</h2>
            </div>
            <button type="button" data-add-row="items" class="rounded-10 border border-gray-mid px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Aggiungi riga</button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1120px] text-left">
                <thead class="bg-gray-light text-11 font-extrabold uppercase tracking-normal text-gray">
                    <tr>
                        <th class="w-50 px-8 py-10"></th>
                        <th class="w-120 px-12 py-10">Codice</th>
                        <th class="px-12 py-10">Descrizione</th>
                        <th class="w-100 px-12 py-10">Q.tà</th>
                        <th class="w-120 px-12 py-10">Prezzo</th>
                        <th class="w-90 px-12 py-10">IVA %</th>
                        <th class="w-120 px-12 py-10 text-right">Imponibile</th>
                        <th class="w-60 px-12 py-10 text-right"></th>
                    </tr>
                </thead>
                <tbody id="items-rows" class="divide-y divide-gray-mid">
                    @foreach ($items as $index => $item)
                        <tr>
                            <td class="px-8 py-8 align-top">
                                <button type="button" draggable="true" data-drag-item-row class="flex h-50 w-32 cursor-move flex-col items-center justify-center gap-4 rounded-10 border border-gray-mid hover:border-black-nike" title="Trascina riga" aria-label="Trascina riga">
                                    <span class="block h-px w-16 bg-black-nike"></span>
                                    <span class="block h-px w-16 bg-black-nike"></span>
                                    <span class="block h-px w-16 bg-black-nike"></span>
                                </button>
                            </td>
                            <td class="px-8 py-8 align-top"><input name="items[{{ $index }}][item_code]" value="{{ $item['item_code'] ?? '' }}" placeholder="Codice" maxlength="80" class="h-50 w-full rounded-10 border-gray-mid px-8 py-6 text-12 font-semibold uppercase focus:border-bullstar focus:ring-bullstar"></td>
                            <td class="px-8 py-8 align-top"><textarea name="items[{{ $index }}][description]" placeholder="Descrizione riga" class="h-50 w-full resize-y rounded-10 border-gray-mid px-8 py-6 text-12 font-semibold focus:border-bullstar focus:ring-bullstar">{{ $item['description'] ?? '' }}</textarea></td>
                            <td class="px-8 py-8 align-top"><input data-line-quantity name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 0 }}" type="number" min="0" step="0.01" class="h-50 w-full rounded-10 border-gray-mid px-8 py-6 text-12 font-semibold focus:border-bullstar focus:ring-bullstar"></td>
                            <td class="px-8 py-8 align-top"><input data-line-unit-price name="items[{{ $index }}][unit_price]" value="{{ $item['unit_price'] ?? 0 }}" type="number" min="0" step="0.01" class="h-50 w-full rounded-10 border-gray-mid px-8 py-6 text-12 font-semibold focus:border-bullstar focus:ring-bullstar"></td>
                            <td class="px-8 py-8 align-top"><input data-line-vat-rate name="items[{{ $index }}][vat_rate]" value="{{ $item['vat_rate'] ?? 22 }}" type="number" min="0" step="0.01" class="h-50 w-full rounded-10 border-gray-mid px-8 py-6 text-12 font-semibold focus:border-bullstar focus:ring-bullstar"></td>
                            <td class="px-12 py-10 text-right align-top text-14 font-black"><span class="flex h-50 items-center justify-end" data-line-total>€ 0,00</span></td>
                            <td class="px-8 py-8 align-top">
                                <button type="button" data-remove-item-row class="flex h-50 w-32 items-center justify-center rounded-10 border border-gray-mid hover:border-black-nike" title="Elimina riga" aria-label="Elimina riga">
                                    <svg class="h-16 w-16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M3 6h18"></path>
                                        <path d="M8 6V4h8v2"></path>
                                        <path d="M19 6l-1 14H6L5 6"></path>
                                        <path d="M10 11v5"></path>
                                        <path d="M14 11v5"></path>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t border-gray-mid bg-gray-light text-14 font-black">
                    <tr>
                        <td colspan="7" class="px-12 py-10 text-right">Imponibile</td>
                        <td class="px-12 py-10 text-right" data-summary-subtotal>€ 0,00</td>
                    </tr>
                    <tr>
                        <td colspan="7" class="px-12 py-10 text-right">IVA</td>
                        <td class="px-12 py-10 text-right" data-summary-vat>€ 0,00</td>
                    </tr>
                    <tr>
                        <td colspan="7" class="px-12 py-10 text-right">Totale</td>
                        <td class="px-12 py-10 text-right" data-summary-total>€ 0,00</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    <section class="overflow-hidden rounded-10 border border-gray-mid bg-white">
        <div class="flex items-center justify-between gap-12 border-b border-gray-mid px-16 py-12">
            <div>
                <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Pagamenti</p>
                <h2 class="mt-4 text-20 font-black leading-tight">Scadenze</h2>
            </div>
            <button type="button" data-add-row="payments" class="rounded-10 border border-gray-mid px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Aggiungi scadenza</button>
        </div>
        <div class="border-b border-gray-mid bg-gray-light px-16 py-10 text-12 font-extrabold uppercase tracking-normal text-gray">
            Totale documento <span data-payment-document-total>€ 0,00</span> · Scadenze <span data-payment-total>€ 0,00</span> · Differenza <span data-payment-difference>€ 0,00</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] text-left">
                <thead class="bg-gray-light text-11 font-extrabold uppercase tracking-normal text-gray">
                    <tr>
                        <th class="w-150 px-12 py-10">Scadenza</th>
                        <th class="px-12 py-10">Metodo</th>
                        <th class="w-150 px-12 py-10">Importo</th>
                        <th class="w-150 px-12 py-10">Pagato</th>
                        <th class="w-150 px-12 py-10">Data pagamento</th>
                        <th class="px-12 py-10">Note</th>
                    </tr>
                </thead>
                <tbody id="payments-rows" class="divide-y divide-gray-mid">
                    @foreach ($payments as $index => $payment)
                        <tr>
                            <td class="px-12 py-10"><input name="payments[{{ $index }}][due_date]" value="{{ $payment['due_date'] ?? '' }}" type="date" class="w-full rounded-10 border-gray-mid px-10 py-8 text-14 font-semibold focus:border-bullstar focus:ring-bullstar"></td>
                            <td class="px-12 py-10">
                                <select name="payments[{{ $index }}][payment_method_code]" class="w-full rounded-10 border-gray-mid px-10 py-8 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                                    @foreach ($paymentMethods as $method)
                                        <option value="{{ $method->code }}" @selected(($payment['payment_method_code'] ?? 'MP05') === $method->code)>{{ $method->code }} - {{ $method->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-12 py-10"><input data-payment-amount name="payments[{{ $index }}][amount]" value="{{ $payment['amount'] ?? 0 }}" type="number" min="0" step="0.01" class="w-full rounded-10 border-gray-mid px-10 py-8 text-14 font-semibold focus:border-bullstar focus:ring-bullstar"></td>
                            <td class="px-12 py-10"><input name="payments[{{ $index }}][paid_amount]" value="{{ $payment['paid_amount'] ?? 0 }}" type="number" min="0" step="0.01" class="w-full rounded-10 border-gray-mid px-10 py-8 text-14 font-semibold focus:border-bullstar focus:ring-bullstar"></td>
                            <td class="px-12 py-10"><input name="payments[{{ $index }}][paid_at]" value="{{ $payment['paid_at'] ?? '' }}" type="date" class="w-full rounded-10 border-gray-mid px-10 py-8 text-14 font-semibold focus:border-bullstar focus:ring-bullstar"></td>
                            <td class="px-12 py-10"><input name="payments[{{ $index }}][notes]" value="{{ $payment['notes'] ?? '' }}" class="w-full rounded-10 border-gray-mid px-10 py-8 text-14 font-semibold focus:border-bullstar focus:ring-bullstar"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-10 border border-gray-mid bg-white p-16">
        <label class="block">
            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Note</span>
            <textarea name="notes" rows="4" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">{{ old('notes', $document->notes) }}</textarea>
        </label>
        <div class="mt-16 flex flex-wrap items-center gap-8">
            <button type="submit" class="rounded-10 bg-bullstar px-16 py-10 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover">
                Salva documento
            </button>
            <a href="{{ $document->exists ? route('admin.documents.show', $document) : route('admin.documents.index') }}" class="rounded-10 border border-gray-mid px-16 py-10 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">
                Annulla
            </a>
        </div>
    </section>
</form>

@push('scripts')
    <script>
        const paymentMethodOptions = @json($paymentMethods->map(fn ($method) => ['code' => $method->code, 'name' => $method->name])->values());
        const paymentMethodSelect = (index, selected = 'MP05') => `<select name="payments[${index}][payment_method_code]" class="w-full rounded-10 border-gray-mid px-10 py-8 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">${
            paymentMethodOptions.map((method) => `<option value="${method.code}" ${method.code === selected ? 'selected' : ''}>${method.code} - ${method.name}</option>`).join('')
        }</select>`;

        const templates = {
            items: (index) => `<tr>
                <td class="px-8 py-8 align-top">
                    <button type="button" draggable="true" data-drag-item-row class="flex h-50 w-32 cursor-move flex-col items-center justify-center gap-4 rounded-10 border border-gray-mid hover:border-black-nike" title="Trascina riga" aria-label="Trascina riga">
                        <span class="block h-px w-16 bg-black-nike"></span>
                        <span class="block h-px w-16 bg-black-nike"></span>
                        <span class="block h-px w-16 bg-black-nike"></span>
                    </button>
                </td>
                <td class="px-8 py-8 align-top"><input name="items[${index}][item_code]" placeholder="Codice" maxlength="80" class="h-50 w-full rounded-10 border-gray-mid px-8 py-6 text-12 font-semibold uppercase focus:border-bullstar focus:ring-bullstar"></td>
                <td class="px-8 py-8 align-top"><textarea name="items[${index}][description]" placeholder="Descrizione riga" class="h-50 w-full resize-y rounded-10 border-gray-mid px-8 py-6 text-12 font-semibold focus:border-bullstar focus:ring-bullstar"></textarea></td>
                <td class="px-8 py-8 align-top"><input data-line-quantity name="items[${index}][quantity]" type="number" min="0" step="0.01" class="h-50 w-full rounded-10 border-gray-mid px-8 py-6 text-12 font-semibold focus:border-bullstar focus:ring-bullstar"></td>
                <td class="px-8 py-8 align-top"><input data-line-unit-price name="items[${index}][unit_price]" value="0" type="number" min="0" step="0.01" class="h-50 w-full rounded-10 border-gray-mid px-8 py-6 text-12 font-semibold focus:border-bullstar focus:ring-bullstar"></td>
                <td class="px-8 py-8 align-top"><input data-line-vat-rate name="items[${index}][vat_rate]" value="22" type="number" min="0" step="0.01" class="h-50 w-full rounded-10 border-gray-mid px-8 py-6 text-12 font-semibold focus:border-bullstar focus:ring-bullstar"></td>
                <td class="px-12 py-10 text-right align-top text-14 font-black"><span class="flex h-50 items-center justify-end" data-line-total>€ 0,00</span></td>
                <td class="px-8 py-8 align-top">
                    <button type="button" data-remove-item-row class="flex h-50 w-32 items-center justify-center rounded-10 border border-gray-mid hover:border-black-nike" title="Elimina riga" aria-label="Elimina riga">
                        <svg class="h-16 w-16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 6h18"></path>
                            <path d="M8 6V4h8v2"></path>
                            <path d="M19 6l-1 14H6L5 6"></path>
                            <path d="M10 11v5"></path>
                            <path d="M14 11v5"></path>
                        </svg>
                    </button>
                </td>
            </tr>`,
            payments: (index) => `<tr>
                <td class="px-12 py-10"><input name="payments[${index}][due_date]" type="date" class="w-full rounded-10 border-gray-mid px-10 py-8 text-14 font-semibold focus:border-bullstar focus:ring-bullstar"></td>
                <td class="px-12 py-10">${paymentMethodSelect(index)}</td>
                <td class="px-12 py-10"><input data-payment-amount name="payments[${index}][amount]" value="0" type="number" min="0" step="0.01" class="w-full rounded-10 border-gray-mid px-10 py-8 text-14 font-semibold focus:border-bullstar focus:ring-bullstar"></td>
                <td class="px-12 py-10"><input name="payments[${index}][paid_amount]" value="0" type="number" min="0" step="0.01" class="w-full rounded-10 border-gray-mid px-10 py-8 text-14 font-semibold focus:border-bullstar focus:ring-bullstar"></td>
                <td class="px-12 py-10"><input name="payments[${index}][paid_at]" type="date" class="w-full rounded-10 border-gray-mid px-10 py-8 text-14 font-semibold focus:border-bullstar focus:ring-bullstar"></td>
                <td class="px-12 py-10"><input name="payments[${index}][notes]" class="w-full rounded-10 border-gray-mid px-10 py-8 text-14 font-semibold focus:border-bullstar focus:ring-bullstar"></td>
            </tr>`
        };

        document.querySelectorAll('[data-add-row]').forEach((button) => {
            button.addEventListener('click', () => {
                const key = button.dataset.addRow;
                const target = document.getElementById(`${key}-rows`);
                target.insertAdjacentHTML('beforeend', templates[key](target.children.length));
                reindexRows(target);
                recalculateDocument();
            });
        });

        const form = document.querySelector('[data-document-form]');
        const statusesByType = @json(\App\Models\AdminDocument::STATUSES);
        const money = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' });
        let lastSyncedSinglePayment = null;

        const numberValue = (input) => Number.parseFloat(String(input?.value || '0').replace(',', '.')) || 0;
        const reindexRows = (tbody) => {
            tbody.querySelectorAll('tr').forEach((row, index) => {
                row.querySelectorAll('[name]').forEach((field) => {
                    field.name = field.name.replace(/\[\d+]/, `[${index}]`);
                });
            });
        };
        const writeMoney = (selector, value) => {
            const element = document.querySelector(selector);
            if (element) {
                element.textContent = money.format(value);
            }
        };

        function recalculateDocument(event = null) {
            let subtotal = 0;
            let vat = 0;

            document.querySelectorAll('#items-rows tr').forEach((row) => {
                const quantity = numberValue(row.querySelector('[data-line-quantity]'));
                const unitPrice = numberValue(row.querySelector('[data-line-unit-price]'));
                const vatRate = numberValue(row.querySelector('[data-line-vat-rate]'));
                const lineSubtotal = quantity * unitPrice;
                const lineVat = lineSubtotal * vatRate / 100;

                subtotal += lineSubtotal;
                vat += lineVat;

                const lineTotalElement = row.querySelector('[data-line-total]');
                if (lineTotalElement) {
                    lineTotalElement.textContent = money.format(lineSubtotal);
                }
            });

            const total = subtotal + vat;
            const paymentInputs = [...document.querySelectorAll('[data-payment-amount]')];

            if (paymentInputs.length === 1 && event?.target?.matches?.('[data-line-quantity], [data-line-unit-price], [data-line-vat-rate]')) {
                const paymentInput = paymentInputs[0];
                const paymentValue = numberValue(paymentInput);
                if (lastSyncedSinglePayment === null || Math.abs(paymentValue - lastSyncedSinglePayment) < 0.01 || paymentValue === 0) {
                    paymentInput.value = total.toFixed(2);
                    lastSyncedSinglePayment = total;
                }
            }

            const paymentTotal = paymentInputs.reduce((sum, input) => sum + numberValue(input), 0);

            writeMoney('[data-summary-subtotal]', subtotal);
            writeMoney('[data-summary-vat]', vat);
            writeMoney('[data-summary-total]', total);
            writeMoney('[data-payment-document-total]', total);
            writeMoney('[data-payment-total]', paymentTotal);
            writeMoney('[data-payment-difference]', paymentTotal - total);
        }

        const itemRows = document.getElementById('items-rows');
        let draggedItemRow = null;

        itemRows?.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-remove-item-row]');
            const row = event.target.closest('tr');
            const target = itemRows;

            if (!removeButton || !row || !target) {
                return;
            }

            row.remove();
            reindexRows(target);
            recalculateDocument();
        });

        itemRows?.addEventListener('dragstart', (event) => {
            const handle = event.target.closest('[data-drag-item-row]');

            if (!handle) {
                return;
            }

            draggedItemRow = handle.closest('tr');
            draggedItemRow?.classList.add('bg-gray-light');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', '');
        });

        itemRows?.addEventListener('dragover', (event) => {
            if (!draggedItemRow) {
                return;
            }

            const targetRow = event.target.closest('tr');

            if (!targetRow || targetRow === draggedItemRow) {
                return;
            }

            event.preventDefault();

            const bounds = targetRow.getBoundingClientRect();
            const insertAfter = event.clientY > bounds.top + bounds.height / 2;
            itemRows.insertBefore(draggedItemRow, insertAfter ? targetRow.nextSibling : targetRow);
        });

        itemRows?.addEventListener('drop', (event) => {
            event.preventDefault();
            reindexRows(itemRows);
            recalculateDocument();
        });

        itemRows?.addEventListener('dragend', () => {
            draggedItemRow?.classList.remove('bg-gray-light');
            draggedItemRow = null;
            reindexRows(itemRows);
        });

        form?.addEventListener('input', recalculateDocument);
        form?.addEventListener('submit', () => {
            document.querySelectorAll('tbody[id$="-rows"]').forEach(reindexRows);
        });
        form?.querySelector('[data-document-type]')?.addEventListener('change', (event) => {
            const statusSelect = form.querySelector('[data-document-status]');
            const fiscalTypeField = form.querySelector('[data-fiscal-type-field]');
            const currentStatus = statusSelect.value;
            const statuses = statusesByType[event.target.value] || statusesByType.quote;
            statusSelect.innerHTML = Object.entries(statuses)
                .map(([value, label]) => `<option value="${value}" ${value === currentStatus ? 'selected' : ''}>${label}</option>`)
                .join('');

            if (!statuses[currentStatus]) {
                statusSelect.value = 'draft';
            }

            if (fiscalTypeField) {
                fiscalTypeField.classList.toggle('hidden', event.target.value !== 'invoice');
            }
        });
        form?.querySelector('[data-fiscal-type-field]')?.classList.toggle('hidden', form?.querySelector('[data-document-type]')?.value !== 'invoice');
        recalculateDocument();
    </script>
@endpush
