@extends('admin.layouts.app')

@section('title', 'Nuova spedizione - Stuart Admin')
@section('page_title', 'Nuova spedizione')
@section('active_nav', 'shipments')

@section('content')
    <section class="mb-16 flex flex-col gap-12 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Creazione immediata</p>
            <h2 class="mt-4 text-24 font-black leading-tight">{{ $document ? 'Da documento '.$document->display_code : 'Spedizione libera' }}</h2>
        </div>
        <a href="{{ route('admin.shipments.index') }}" class="rounded-10 border border-gray-mid bg-white px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Archivio</a>
    </section>

    <form method="POST" action="{{ route('admin.shipments.store') }}" class="grid gap-16 xl:grid-cols-[minmax(0,1fr)_360px]">
        @csrf
        @php($selectedDocumentsPayload = $selectedDocuments->map(fn ($selectedDocument) => [
            'id' => $selectedDocument->id,
            'label' => $selectedDocument->display_code.' - '.$selectedDocument->customer_name,
            'meta' => trim($selectedDocument->type_label.' · '.optional($selectedDocument->document_date)->format('d/m/Y')),
        ])->values())

        <div class="space-y-16">
            <section class="rounded-10 border border-gray-mid bg-white p-16">
                <div class="grid gap-12 md:grid-cols-2">
                    <label>
                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Corriere</span>
                        <select name="carrier" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                            @foreach ($carriers as $value => $label)
                                <option value="{{ $value }}" @selected(old('carrier', $shipment->carrier) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Riferimento interno</span>
                        <input name="reference" value="{{ old('reference', $shipment->reference) }}" type="text" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    </label>
                </div>
            </section>

            <section class="rounded-10 border border-gray-mid bg-white p-16">
                <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Destinatario</p>
                <div class="mt-12 grid gap-12 md:grid-cols-2">
                    <label>
                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Nome</span>
                        <input name="recipient_name" value="{{ old('recipient_name', $shipment->recipient_name) }}" required type="text" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    </label>
                    <label>
                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Email</span>
                        <input name="recipient_email" value="{{ old('recipient_email', $shipment->recipient_email) }}" type="email" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    </label>
                    <label>
                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Telefono</span>
                        <input name="recipient_phone" value="{{ old('recipient_phone', $shipment->recipient_phone) }}" type="text" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    </label>
                    <label>
                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Nazione</span>
                        <input name="recipient_country" value="{{ old('recipient_country', $shipment->recipient_country ?: 'IT') }}" required maxlength="2" type="text" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold uppercase focus:border-bullstar focus:ring-bullstar">
                    </label>
                    <label class="md:col-span-2">
                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Indirizzo</span>
                        <input name="recipient_address" value="{{ old('recipient_address', $shipment->recipient_address) }}" required type="text" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    </label>
                    <label>
                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Civico</span>
                        <input name="recipient_street_number" value="{{ old('recipient_street_number', $shipment->recipient_street_number) }}" type="text" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    </label>
                    <label>
                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">CAP</span>
                        <input name="recipient_postal_code" value="{{ old('recipient_postal_code', $shipment->recipient_postal_code) }}" required type="text" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    </label>
                    <label>
                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Città</span>
                        <input name="recipient_city" value="{{ old('recipient_city', $shipment->recipient_city) }}" required type="text" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    </label>
                    <label>
                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Provincia</span>
                        <input name="recipient_province" value="{{ old('recipient_province', $shipment->recipient_province) }}" maxlength="10" type="text" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold uppercase focus:border-bullstar focus:ring-bullstar">
                    </label>
                </div>
            </section>
        </div>

        <aside class="space-y-16">
            <section class="rounded-10 border border-gray-mid bg-white p-16">
                <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Colli</p>
                <div class="mt-12 space-y-12">
                    <label class="block">
                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Numero colli</span>
                        <input name="parcels_count" value="{{ old('parcels_count', $shipment->parcels_count ?: 1) }}" min="1" max="99" required type="number" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    </label>
                    <label class="block">
                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Peso totale kg</span>
                        <input name="weight_kg" value="{{ old('weight_kg', $shipment->weight_kg ?: 1.0) }}" min="0.1" step="0.01" required type="number" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    </label>
                    <label class="block">
                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Volume totale m3</span>
                        <input name="volume_m3" value="{{ old('volume_m3', $shipment->volume_m3 ?: 0.000) }}" min="0.000" step="0.001" required type="number" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    </label>
                    <label class="block">
                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Contrassegno</span>
                        <input name="cash_on_delivery" value="{{ old('cash_on_delivery', $shipment->cash_on_delivery) }}" min="0" step="0.01" type="number" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    </label>
                </div>
                <button type="submit" class="mt-12 w-full rounded-10 bg-bullstar px-12 py-10 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover">Crea spedizione</button>
            </section>

            @if ($document)
                <section class="rounded-10 border border-gray-mid bg-white p-16">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Documento di partenza</p>
                    <a href="{{ route('admin.documents.show', $document) }}" class="mt-8 block text-16 font-black text-bullstar underline-offset-4 hover:underline">{{ $document->type_label }} {{ $document->display_code }}</a>
                    <p class="mt-6 text-13 font-semibold text-gray">{{ $document->customer_name }}</p>
                </section>
            @endif

            <section class="rounded-10 border border-gray-mid bg-white p-16">
                <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Documenti collegati</p>
                <div
                    data-document-picker
                    data-search-url="{{ route('admin.shipments.documents.search') }}"
                    data-selected='@json($selectedDocumentsPayload, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG | JSON_HEX_QUOT)'
                    class="mt-10"
                >
                    <div data-selected-list class="space-y-6"></div>
                    <label class="mt-10 block">
                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Cerca</span>
                        <input data-document-search type="search" autocomplete="off" placeholder="Numero, cliente, email o P.IVA" class="mt-6 w-full rounded-10 border-gray-mid px-10 py-8 text-13 font-semibold focus:border-bullstar focus:ring-bullstar">
                    </label>
                    <div data-document-results class="mt-6 hidden max-h-220 overflow-y-auto rounded-10 border border-gray-mid bg-white"></div>
                </div>
            </section>
        </aside>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const picker = document.querySelector('[data-document-picker]');

            if (!picker) {
                return;
            }

            const searchUrl = picker.dataset.searchUrl;
            const selectedList = picker.querySelector('[data-selected-list]');
            const searchInput = picker.querySelector('[data-document-search]');
            const results = picker.querySelector('[data-document-results]');
            const selected = new Map(JSON.parse(picker.dataset.selected || '[]').map((document) => [Number(document.id), document]));
            let debounce = null;
            let controller = null;

            const escapeHtml = (value) => String(value || '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const renderSelected = () => {
                if (!selected.size) {
                    selectedList.innerHTML = '';

                    return;
                }

                selectedList.innerHTML = Array.from(selected.values()).map((document) => `
                    <div class="flex items-start justify-between gap-8 rounded-8 border border-gray-mid bg-gray-light px-10 py-8">
                        <input type="hidden" name="document_ids[]" value="${document.id}">
                        <div class="min-w-0">
                            <p class="truncate text-13 font-black">${escapeHtml(document.label)}</p>
                            <p class="mt-2 truncate text-11 font-semibold text-gray">${escapeHtml(document.meta)}</p>
                        </div>
                        <button type="button" data-remove-document="${document.id}" class="shrink-0 rounded-8 border border-gray-mid bg-white px-8 py-4 text-11 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Rimuovi</button>
                    </div>
                `).join('');
            };

            const renderResults = (documents) => {
                const filtered = documents.filter((document) => !selected.has(Number(document.id)));

                if (!filtered.length) {
                    results.classList.add('hidden');
                    results.innerHTML = '';

                    return;
                }

                results.innerHTML = filtered.map((document) => `
                    <button type="button" data-add-document='${escapeHtml(JSON.stringify(document))}' class="block w-full border-b border-gray-mid px-10 py-8 text-left last:border-b-0 hover:bg-gray-light">
                        <span class="block truncate text-13 font-black">${escapeHtml(document.label)}</span>
                        <span class="mt-2 block truncate text-11 font-semibold text-gray">${escapeHtml(document.meta)}</span>
                    </button>
                `).join('');
                results.classList.remove('hidden');
            };

            const search = async () => {
                const query = searchInput.value.trim();

                if (controller) {
                    controller.abort();
                }

                controller = new AbortController();

                try {
                    const response = await fetch(`${searchUrl}?q=${encodeURIComponent(query)}`, {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    });
                    const payload = await response.json();
                    renderResults(payload.data || []);
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        results.classList.add('hidden');
                    }
                }
            };

            selectedList.addEventListener('click', (event) => {
                const button = event.target.closest('[data-remove-document]');

                if (!button) {
                    return;
                }

                selected.delete(Number(button.dataset.removeDocument));
                renderSelected();
                search();
            });

            results.addEventListener('click', (event) => {
                const button = event.target.closest('[data-add-document]');

                if (!button) {
                    return;
                }

                const document = JSON.parse(button.dataset.addDocument);
                selected.set(Number(document.id), document);
                searchInput.value = '';
                results.classList.add('hidden');
                results.innerHTML = '';
                renderSelected();
                searchInput.focus();
            });

            searchInput.addEventListener('input', () => {
                clearTimeout(debounce);
                debounce = setTimeout(search, 250);
            });

            searchInput.addEventListener('focus', search);
            renderSelected();
        })();
    </script>
@endpush
