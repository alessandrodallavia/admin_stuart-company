@php
    $activeArea = collect($documentAreas)->firstWhere('type', $currentType);
    $isDeliveryNoteArea = $currentType === 'delivery_note';
    $documentGroups = $currentType === ''
        ? collect($documentAreas)
            ->map(fn ($area) => array_merge($area, [
                'documents' => $documents->getCollection()->where('type', $area['type']),
            ]))
            ->filter(fn ($area) => $area['documents']->isNotEmpty())
        : collect([[
            'type' => $currentType,
            'label' => $activeArea['label'] ?? 'Archivio documenti',
            'description' => $activeArea['description'] ?? 'Documenti filtrati',
            'documents' => $documents->getCollection(),
        ]]);
@endphp

<div>
    <section class="mb-16 grid gap-12 md:grid-cols-3">
        <article class="rounded-10 border border-gray-mid bg-white p-16">
            <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Documenti</p>
            <p class="mt-8 text-38 font-black leading-none tracking-normal">{{ $stats['total'] }}</p>
        </article>
        <article class="rounded-10 border border-gray-mid bg-white p-16">
            <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Scaduti</p>
            <p class="mt-8 text-38 font-black leading-none tracking-normal">{{ $stats['overdue'] }}</p>
        </article>
        <article class="rounded-10 border border-gray-mid bg-white p-16">
            <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Fatture</p>
            <p class="mt-8 text-38 font-black leading-none tracking-normal">{{ $stats['by_type']['invoice'] ?? 0 }}</p>
        </article>
    </section>

    <section class="mb-16 overflow-hidden rounded-10 border border-gray-mid bg-white">
        <div class="border-b border-gray-mid px-16 py-12">
            <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Aree documenti</p>
            <p class="mt-4 text-14 font-bold text-black-nike">Archivio separato per flusso amministrativo</p>
        </div>
        <div class="grid gap-px bg-gray-mid md:grid-cols-2 xl:grid-cols-6">
            <a
                href="{{ route('admin.documents.index', request()->except(['type', 'page'])) }}"
                class="bg-white p-10 transition hover:bg-gray-light {{ $currentType === '' ? 'ring-2 ring-inset ring-bullstar' : '' }}"
            >
                <p class="text-12 font-extrabold uppercase tracking-normal {{ $currentType === '' ? 'text-bullstar' : 'text-gray' }}">Tutti</p>
                <p class="mt-6 text-20 font-black leading-none tracking-normal">{{ $stats['total'] }}</p>
                <p class="mt-4 text-12 font-semibold text-gray">Vista raggruppata</p>
            </a>
            @foreach ($documentAreas as $area)
                @php
                    $areaCount = $stats['by_type'][$area['type']] ?? 0;
                    $areaTotal = $stats['totals_by_type'][$area['type']] ?? 0;
                @endphp
                <a
                    href="{{ route('admin.documents.index', array_merge(request()->except(['type', 'page']), ['type' => $area['type']])) }}"
                    class="bg-white p-10 transition hover:bg-gray-light {{ $currentType === $area['type'] ? 'ring-2 ring-inset ring-bullstar' : '' }}"
                >
                    <p class="text-12 font-extrabold uppercase tracking-normal {{ $currentType === $area['type'] ? 'text-bullstar' : 'text-gray' }}">{{ $area['label'] }}</p>
                    <p class="mt-6 text-20 font-black leading-none tracking-normal">{{ $areaCount }}</p>
                    <p class="mt-4 text-12 font-semibold text-gray">€ {{ number_format((float) $areaTotal, 2, ',', '.') }}</p>
                </a>
            @endforeach
        </div>
    </section>

    <section class="mb-16 overflow-hidden rounded-10 border border-gray-mid bg-white">
        <form method="GET" action="{{ route('admin.documents.index') }}" data-auto-filter-form class="grid gap-12 p-16 {{ $isDeliveryNoteArea ? 'xl:grid-cols-[160px_minmax(220px,1fr)_180px_auto]' : 'xl:grid-cols-[160px_minmax(220px,1fr)_180px_180px_auto]' }} xl:items-end">
            @if ($currentType !== '')
                <input type="hidden" name="type" value="{{ $currentType }}">
            @endif
            <label class="block">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Numero documento</span>
                <input name="search_number" value="{{ $searchNumber }}" data-auto-filter-input type="search" placeholder="OFF-1 o 1" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
            </label>
            <label class="block">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Cerca</span>
                <input name="search" value="{{ $search }}" data-auto-filter-input type="search" placeholder="Cliente, email, telefono, CF/P.IVA, città" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
            </label>
            @if ($currentType !== '')
                <label class="block">
                    <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Stato</span>
                    <select name="status" data-auto-filter-select class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                        <option value="">Tutti</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($currentStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @unless ($isDeliveryNoteArea)
                <label class="block">
                    <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Pagamento</span>
                    <select name="payment_status" data-auto-filter-select class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                        <option value="">Tutti</option>
                        @foreach ($paymentStatuses as $value => $label)
                            <option value="{{ $value }}" @selected($currentPaymentStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            @endunless
            <div class="flex gap-8">
                <a href="{{ $currentType === '' ? route('admin.documents.index') : route('admin.documents.index', ['type' => $currentType]) }}" class="rounded-10 border border-gray-mid px-16 py-10 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Reset</a>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-10 border border-gray-mid bg-white">
        <div class="flex flex-col gap-12 border-b border-gray-mid px-16 py-12 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-12 font-extrabold uppercase tracking-normal text-gray">{{ $currentType === '' ? 'Archivio per area' : $activeArea['label'] ?? 'Archivio' }}</p>
                <p class="mt-4 text-14 font-bold text-black-nike">{{ $documents->total() }} documenti trovati{{ $currentType === '' ? ', suddivisi per tipologia' : '' }}</p>
            </div>
            <div class="flex flex-wrap gap-8">
                @if ($currentType === 'invoice')
                    <a href="{{ route('admin.documents.payments') }}" class="rounded-10 border border-gray-mid px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Pagamenti</a>
                    <a href="{{ route('admin.documents.import-xml') }}" class="rounded-10 border border-gray-mid px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Importa XML</a>
                    <form id="sdi-export-form" method="GET" action="{{ route('admin.documents.export-sdi') }}">
                        <button type="submit" class="rounded-10 border border-gray-mid px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Export SDI Aruba</button>
                    </form>
                @endif
                @if ($currentType !== '')
                    <a href="{{ route('admin.documents.create', ['type' => $currentType]) }}" class="rounded-10 bg-bullstar px-12 py-8 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover">+ {{ $activeArea['label'] ?? 'Documento' }}</a>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full {{ $isDeliveryNoteArea ? 'min-w-[840px]' : 'min-w-[1000px]' }} text-left">
                <thead class="border-b border-gray-mid bg-gray-light text-11 font-extrabold uppercase tracking-normal text-gray">
                    <tr>
                        <th class="w-48 px-12 py-12">
                            <input type="checkbox" data-toggle-sdi-selection class="rounded border-gray-mid text-bullstar focus:ring-bullstar" aria-label="Seleziona tutte le fatture esportabili">
                        </th>
                        <th class="px-12 py-12">Documento</th>
                        <th class="px-12 py-12">Cliente</th>
                        <th class="px-12 py-12">Stato</th>
                        @unless ($isDeliveryNoteArea)
                            <th class="px-12 py-12">Pagamento</th>
                            <th class="px-12 py-12 text-right">Totale</th>
                        @endunless
                        <th class="px-12 py-12"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-mid">
                    @if ($documents->count() === 0)
                        <tr>
                            <td colspan="7" class="px-16 py-28">
                                <div class="rounded-10 border border-dashed border-gray-mid bg-gray-light p-20 text-14 font-semibold text-gray">Nessun documento con questi filtri.</div>
                            </td>
                        </tr>
                    @else
                        @foreach ($documentGroups as $group)
                            @if ($currentType === '')
                                <tr class="bg-gray-light">
                                    <td colspan="7" class="px-12 py-10">
                                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                            <div>
                                                <p class="text-12 font-extrabold uppercase tracking-normal text-bullstar">{{ $group['label'] }}</p>
                                                <p class="mt-2 text-12 font-semibold text-gray">{{ $group['description'] }}</p>
                                            </div>
                                            <div class="flex flex-wrap gap-10">
                                                <a href="{{ route('admin.documents.create', ['type' => $group['type']]) }}" class="text-12 font-extrabold uppercase tracking-normal text-bullstar underline-offset-4 hover:underline">+ Nuovo {{ $group['label'] }}</a>
                                                <a href="{{ route('admin.documents.index', array_merge(request()->except(['type', 'page']), ['type' => $group['type']])) }}" class="text-12 font-extrabold uppercase tracking-normal text-black-nike underline-offset-4 hover:underline">Apri area</a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif

                            @foreach ($group['documents'] as $document)
                                <tr>
                                    <td class="px-12 py-12 align-top">
                                        @if ($document->type === 'invoice' && ! in_array($document->status, ['draft', 'cancelled'], true))
                                            <input form="sdi-export-form" name="document_ids[]" value="{{ $document->id }}" type="checkbox" data-sdi-selection class="rounded border-gray-mid text-bullstar focus:ring-bullstar" aria-label="Seleziona {{ $document->type_label }} {{ $document->display_code }} per export SDI">
                                        @else
                                            <span class="block h-16 w-16 rounded border border-gray-mid bg-gray-light"></span>
                                        @endif
                                    </td>
                                    <td class="px-12 py-12">
                                        <p class="text-14 font-black">{{ $document->type_label }} {{ $document->display_code }}</p>
                                        <p class="mt-4 text-11 font-semibold text-gray">{{ optional($document->document_date)->format('d/m/Y') }} · {{ $document->items_count }} righe</p>
                                    </td>
                                    <td class="px-12 py-12">
                                        <p class="max-w-[260px] truncate text-14 font-bold">{{ $document->customer_name }}</p>
                                        <p class="mt-4 max-w-[260px] truncate text-11 font-semibold text-gray">{{ $document->customer_email ?: $document->customer_phone ?: 'Contatto non indicato' }}</p>
                                    </td>
                                    <td class="px-12 py-12"><span class="rounded-full {{ $document->status_badge_class }} px-10 py-6 text-11 font-extrabold uppercase tracking-normal">{{ $document->status_label }}</span></td>
                                    @unless ($isDeliveryNoteArea)
                                        <td class="px-12 py-12">
                                            @unless ($document->type === 'delivery_note')
                                                <span class="rounded-full {{ $document->payment_status === 'paid' ? 'bg-whatsapp/10 text-whatsapp' : 'bg-gray-light text-gray' }} px-10 py-6 text-11 font-extrabold uppercase tracking-normal">{{ $document->payment_status_label }}</span>
                                            @endunless
                                        </td>
                                        <td class="px-12 py-12 text-right text-14 font-black">
                                            @unless ($document->type === 'delivery_note')
                                                € {{ number_format((float) $document->total, 2, ',', '.') }}
                                            @endunless
                                        </td>
                                    @endunless
                                    <td class="px-12 py-12 text-right">
                                        <div class="flex justify-end gap-6">
                                            <a href="{{ route('admin.documents.preview', $document) }}" target="_blank" class="rounded-10 border border-gray-mid px-10 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">PDF</a>
                                            @if ($document->type === 'invoice')
                                                <a href="{{ route('admin.documents.xml', $document) }}" class="rounded-10 border border-gray-mid px-10 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">XML</a>
                                            @endif
                                            <form method="POST" action="{{ route('admin.documents.duplicate', $document) }}">
                                                @csrf
                                                <input type="hidden" name="type" value="{{ $document->type }}">
                                                <button type="submit" class="rounded-10 border border-gray-mid px-10 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Copia</button>
                                            </form>
                                            <a href="{{ route('admin.documents.show', $document) }}" class="rounded-10 border border-gray-mid px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-bullstar hover:text-bullstar">Apri</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-mid px-16 py-12">
            {{ $documents->links() }}
        </div>
    </section>
</div>

@push('scripts')
    <script>
        document.querySelectorAll('[data-auto-filter-form]').forEach((form) => {
            let submitTimeout;

            const submit = () => {
                form.requestSubmit();
            };

            form.querySelectorAll('[data-auto-filter-select]').forEach((field) => {
                field.addEventListener('change', submit);
            });

            form.querySelectorAll('[data-auto-filter-input]').forEach((field) => {
                field.addEventListener('input', () => {
                    clearTimeout(submitTimeout);
                    submitTimeout = setTimeout(submit, 500);
                });
            });
        });

        document.querySelector('[data-toggle-sdi-selection]')?.addEventListener('change', (event) => {
            document.querySelectorAll('[data-sdi-selection]').forEach((checkbox) => {
                checkbox.checked = event.target.checked;
            });
        });
    </script>
@endpush
