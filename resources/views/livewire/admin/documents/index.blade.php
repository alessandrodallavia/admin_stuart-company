<div>
    <section class="mb-16 grid gap-12 md:grid-cols-4">
        <article class="rounded-10 border border-gray-mid bg-white p-16">
            <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Documenti</p>
            <p class="mt-8 text-38 font-black leading-none tracking-normal">{{ $stats['total'] }}</p>
        </article>
        <article class="rounded-10 border border-gray-mid bg-white p-16">
            <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Residuo da incassare</p>
            <p class="mt-8 text-30 font-black leading-none tracking-normal">€ {{ number_format((float) $stats['open_total'], 2, ',', '.') }}</p>
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
        <form method="GET" action="{{ route('admin.documents.index') }}" class="grid gap-12 p-16 xl:grid-cols-[160px_minmax(220px,1fr)_180px_180px_180px_auto] xl:items-end">
            <label class="block">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Numero documento</span>
                <input name="search_number" value="{{ $searchNumber }}" type="search" placeholder="OFF-1 o 1" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
            </label>
            <label class="block">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Cerca</span>
                <input name="search" value="{{ $search }}" type="search" placeholder="Cliente, email, telefono, CF/P.IVA, città" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
            </label>
            <label class="block">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Tipo</span>
                <select name="type" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    <option value="">Tutti</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected($currentType === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Stato</span>
                <select name="status" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    <option value="">Tutti</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($currentStatus === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Pagamento</span>
                <select name="payment_status" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    <option value="">Tutti</option>
                    @foreach ($paymentStatuses as $value => $label)
                        <option value="{{ $value }}" @selected($currentPaymentStatus === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex gap-8">
                <button type="submit" class="rounded-10 bg-black-nike px-16 py-10 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-black">Filtra</button>
                <a href="{{ route('admin.documents.index') }}" class="rounded-10 border border-gray-mid px-16 py-10 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Reset</a>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-10 border border-gray-mid bg-white">
        <div class="flex flex-col gap-12 border-b border-gray-mid px-16 py-12 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Archivio</p>
                <p class="mt-4 text-14 font-bold text-black-nike">{{ $documents->total() }} documenti trovati</p>
            </div>
            <div class="flex flex-wrap gap-8">
                <a href="{{ route('admin.documents.payments') }}" class="rounded-10 border border-gray-mid px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Pagamenti</a>
                <a href="{{ route('admin.documents.import-xml') }}" class="rounded-10 border border-gray-mid px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Importa XML</a>
                @foreach ($types as $value => $label)
                    <a href="{{ route('admin.documents.create', ['type' => $value]) }}" class="rounded-10 bg-bullstar px-12 py-8 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover">+ {{ $label }}</a>
                @endforeach
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1000px] text-left">
                <thead class="border-b border-gray-mid bg-gray-light text-11 font-extrabold uppercase tracking-normal text-gray">
                    <tr>
                        <th class="px-12 py-12">Documento</th>
                        <th class="px-12 py-12">Cliente</th>
                        <th class="px-12 py-12">Stato</th>
                        <th class="px-12 py-12">Pagamento</th>
                        <th class="px-12 py-12 text-right">Totale</th>
                        <th class="px-12 py-12"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-mid">
                    @forelse ($documents as $document)
                        <tr>
                            <td class="px-12 py-12">
                                <p class="text-14 font-black">{{ $document->type_label }} {{ $document->display_code }}</p>
                                <p class="mt-4 text-11 font-semibold text-gray">{{ optional($document->document_date)->format('d/m/Y') }} · {{ $document->items_count }} righe</p>
                            </td>
                            <td class="px-12 py-12">
                                <p class="max-w-[260px] truncate text-14 font-bold">{{ $document->customer_name }}</p>
                                <p class="mt-4 max-w-[260px] truncate text-11 font-semibold text-gray">{{ $document->customer_email ?: $document->customer_phone ?: 'Contatto non indicato' }}</p>
                            </td>
                            <td class="px-12 py-12"><span class="rounded-full bg-black-nike px-10 py-6 text-11 font-extrabold uppercase tracking-normal text-white">{{ $document->status_label }}</span></td>
                            <td class="px-12 py-12"><span class="rounded-full {{ $document->payment_status === 'paid' ? 'bg-whatsapp/10 text-whatsapp' : 'bg-gray-light text-gray' }} px-10 py-6 text-11 font-extrabold uppercase tracking-normal">{{ $document->payment_status_label }}</span></td>
                            <td class="px-12 py-12 text-right text-14 font-black">€ {{ number_format((float) $document->total, 2, ',', '.') }}</td>
                            <td class="px-12 py-12 text-right">
                                <div class="flex justify-end gap-6">
                                    <a href="{{ route('admin.documents.preview', $document) }}" target="_blank" class="rounded-10 border border-gray-mid px-10 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">PDF</a>
                                    <form method="POST" action="{{ route('admin.documents.duplicate', $document) }}">
                                        @csrf
                                        <input type="hidden" name="type" value="{{ $document->type }}">
                                        <button type="submit" class="rounded-10 border border-gray-mid px-10 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Copia</button>
                                    </form>
                                    <a href="{{ route('admin.documents.show', $document) }}" class="rounded-10 border border-gray-mid px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-bullstar hover:text-bullstar">Apri</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-16 py-28">
                                <div class="rounded-10 border border-dashed border-gray-mid bg-gray-light p-20 text-14 font-semibold text-gray">Nessun documento con questi filtri.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-mid px-16 py-12">
            {{ $documents->links() }}
        </div>
    </section>
</div>
