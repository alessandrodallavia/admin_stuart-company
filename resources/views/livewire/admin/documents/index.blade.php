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

<div class="font-montserrat">
    <section class="mb-24 flex flex-col gap-16 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-24 font-semibold text-gray-800">{{ $activeArea['label'] ?? 'Documenti' }}</h2>
            <p class="mt-4 text-14 text-gray">{{ $activeArea['description'] ?? 'Archivio documenti' }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-8">
            @if ($currentType === 'invoice')
                <a href="{{ route('admin.documents.import-xml') }}" class="rounded-md border border-gray-mid bg-gray-light px-16 py-10 text-14 font-medium text-gray-800 transition hover:bg-gray-200">Importa XML</a>
                <form id="sdi-export-form" method="GET" action="{{ route('admin.documents.export-sdi') }}">
                    <button type="submit" class="rounded-md border border-gray-mid bg-gray-light px-16 py-10 text-14 font-medium text-gray-800 transition hover:bg-gray-200">Invia allo SDI</button>
                </form>
            @endif
            <a href="{{ route('admin.documents.create', ['type' => $currentType]) }}" class="flex items-center gap-8 rounded-md bg-bullstar px-16 py-10 text-14 font-medium text-white shadow transition hover:bg-bullstar-hover">
                <span class="text-18 leading-none">+</span>
                <span>Crea nuovo {{ strtolower($activeArea['label'] ?? 'documento') }}</span>
            </a>
        </div>
    </section>

    <section class="mb-24">
        <form method="GET" action="{{ route('admin.documents.index') }}" data-auto-filter-form class="grid gap-12 {{ $isDeliveryNoteArea ? 'lg:grid-cols-[180px_minmax(260px,1fr)_180px_auto]' : 'lg:grid-cols-[180px_minmax(260px,1fr)_180px_180px_auto]' }} lg:items-center">
            @if ($currentType !== '')
                <input type="hidden" name="type" value="{{ $currentType }}">
            @endif
            <label class="block">
                <span class="sr-only">Numero documento</span>
                <input name="search_number" value="{{ $searchNumber }}" data-auto-filter-input type="search" placeholder="Cerca numero..." class="w-full rounded-md border-gray-400 px-16 py-10 text-14 shadow-sm focus:border-bullstar focus:ring-bullstar">
            </label>
            <label class="block">
                <span class="sr-only">Cerca</span>
                <input name="search" value="{{ $search }}" data-auto-filter-input type="search" placeholder="Cerca per cliente..." class="w-full rounded-md border-gray-400 px-16 py-10 text-14 shadow-sm focus:border-bullstar focus:ring-bullstar">
            </label>
            @if ($currentType !== '')
                <label class="block">
                    <span class="sr-only">Stato</span>
                    <select name="status" data-auto-filter-select class="w-full rounded-md border-gray-400 px-12 py-10 text-14 shadow-sm focus:border-bullstar focus:ring-bullstar">
                        <option value="">Tutti gli stati</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($currentStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @unless ($isDeliveryNoteArea)
                <label class="block">
                    <span class="sr-only">Pagamento</span>
                    <select name="payment_status" data-auto-filter-select class="w-full rounded-md border-gray-400 px-12 py-10 text-14 shadow-sm focus:border-bullstar focus:ring-bullstar">
                        <option value="">Tutti i pagamenti</option>
                        @foreach ($paymentStatuses as $value => $label)
                            <option value="{{ $value }}" @selected($currentPaymentStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            @endunless
            <div class="flex gap-8">
                <a href="{{ route('admin.documents.index', ['type' => $currentType]) }}" class="rounded-md border border-gray-mid bg-white px-16 py-10 text-14 font-medium text-gray transition hover:bg-gray-light">Reset</a>
            </div>
        </form>
    </section>

    <section class="document-surface overflow-hidden">
        <div class="border-b border-gray-mid px-16 py-12 text-14 text-gray">{{ $documents->total() }} documenti trovati</div>

        <div class="overflow-x-auto">
            <table class="w-full {{ $isDeliveryNoteArea ? 'min-w-[840px]' : 'min-w-[1000px]' }} text-left">
                <thead class="border-b border-gray-mid bg-gray-100 text-12 font-semibold text-gray">
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
                                <tr class="text-14 text-gray-700 transition hover:bg-gray-50">
                                    <td class="px-12 py-12 align-top">
                                        @if ($document->type === 'invoice' && ! in_array($document->status, ['draft', 'cancelled'], true))
                                            <input form="sdi-export-form" name="document_ids[]" value="{{ $document->id }}" type="checkbox" data-sdi-selection class="rounded border-gray-mid text-bullstar focus:ring-bullstar" aria-label="Seleziona {{ $document->type_label }} {{ $document->display_code }} per export SDI">
                                        @else
                                            <span class="block h-16 w-16 rounded border border-gray-mid bg-gray-light"></span>
                                        @endif
                                    </td>
                                    <td class="px-12 py-12">
                                        <p class="text-14 font-medium text-gray-800">{{ $document->type_label }} {{ $document->display_code }}</p>
                                        <p class="mt-4 text-12 font-normal text-gray">{{ optional($document->document_date)->format('d/m/Y') }} · {{ $document->items_count }} righe</p>
                                    </td>
                                    <td class="px-12 py-12">
                                        <p class="max-w-[260px] truncate text-14 font-normal text-gray-700">{{ $document->customer_name }}</p>
                                        <p class="mt-4 max-w-[260px] truncate text-12 font-normal text-gray">{{ $document->customer_email ?: $document->customer_phone ?: 'Contatto non indicato' }}</p>
                                    </td>
                                    <td class="px-12 py-12"><span class="rounded-full {{ $document->status_badge_class }} px-10 py-6 text-12 font-medium">{{ $document->status_label }}</span></td>
                                    @unless ($isDeliveryNoteArea)
                                        <td class="px-12 py-12">
                                            @unless ($document->type === 'delivery_note')
                                                <span class="rounded-full {{ $document->payment_status === 'paid' ? 'bg-whatsapp/10 text-whatsapp' : 'bg-gray-light text-gray' }} px-10 py-6 text-12 font-medium">{{ $document->payment_status_label }}</span>
                                            @endunless
                                        </td>
                                        <td class="px-12 py-12 text-right text-14 font-normal">
                                            @unless ($document->type === 'delivery_note')
                                                € {{ number_format((float) $document->total, 2, ',', '.') }}
                                            @endunless
                                        </td>
                                    @endunless
                                    <td class="px-12 py-12 text-right">
                                        <div class="flex justify-end gap-6">
                                            <a href="{{ route('admin.documents.preview', $document) }}" target="_blank" class="rounded-md border border-gray-mid px-10 py-8 text-12 font-medium transition hover:bg-gray-light">PDF</a>
                                            @if ($document->type === 'invoice')
                                                <a href="{{ route('admin.documents.xml', $document) }}" class="rounded-md border border-gray-mid px-10 py-8 text-12 font-medium transition hover:bg-gray-light">XML</a>
                                            @endif
                                            <form method="POST" action="{{ route('admin.documents.duplicate', $document) }}">
                                                @csrf
                                                <input type="hidden" name="type" value="{{ $document->type }}">
                                                <button type="submit" class="rounded-md border border-gray-mid px-10 py-8 text-12 font-medium transition hover:bg-gray-light">Copia</button>
                                            </form>
                                            <a href="{{ route('admin.documents.show', $document) }}" class="rounded-md px-12 py-8 text-12 font-medium text-bullstar transition hover:bg-bullstar/10">Vedi</a>
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
