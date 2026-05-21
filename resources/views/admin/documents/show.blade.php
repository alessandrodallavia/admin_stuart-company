@extends('admin.layouts.app')

@section('title', $document->type_label . ' ' . $document->display_code . ' - Stuart Admin')
@section('page_title', $document->type_label . ' ' . $document->display_code)
@section('active_nav', 'documents')

@section('content')
    <section class="mb-16 flex flex-col gap-12 md:flex-row md:items-center md:justify-between">
        <div class="flex flex-wrap gap-8">
            <a href="{{ route('admin.documents.index') }}" class="rounded-10 border border-gray-mid bg-white px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Archivio</a>
            <a href="{{ route('admin.documents.preview', $document) }}" target="_blank" class="rounded-10 border border-gray-mid bg-white px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">PDF</a>
            <a href="{{ route('admin.documents.edit', $document) }}" class="rounded-10 bg-bullstar px-12 py-8 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover">Modifica</a>
            @foreach ($types as $type => $label)
                @if ($type !== $document->type)
                    <form method="POST" action="{{ route('admin.documents.duplicate', $document) }}">
                        @csrf
                        <input type="hidden" name="type" value="{{ $type }}">
                        <button type="submit" class="rounded-10 border border-gray-mid bg-white px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Genera {{ $label }}</button>
                    </form>
                @endif
            @endforeach
        </div>
        <form method="POST" action="{{ route('admin.documents.destroy', $document) }}" onsubmit="return confirm('Eliminare questo documento?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-10 border border-red-200 bg-red-50 px-12 py-8 text-12 font-extrabold uppercase tracking-normal text-red-700 transition hover:border-red-500">Elimina</button>
        </form>
    </section>

    <section class="grid gap-16 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-16">
            <section class="rounded-10 border border-gray-mid bg-white p-16">
                <div class="flex flex-col gap-12 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-12 font-extrabold uppercase tracking-normal text-gray">{{ $document->type_label }}</p>
                        <h2 class="mt-6 text-30 font-black leading-tight">{{ $document->display_code }}</h2>
                        <p class="mt-6 text-14 font-semibold text-gray">Data {{ optional($document->document_date)->format('d/m/Y') }}</p>
                    </div>
                    <div class="text-left md:text-right">
                        <span class="rounded-full bg-black-nike px-10 py-6 text-11 font-extrabold uppercase tracking-normal text-white">{{ $document->status_label }}</span>
                        <p class="mt-10 text-30 font-black">€ {{ number_format((float) $document->total, 2, ',', '.') }}</p>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-10 border border-gray-mid bg-white">
                <div class="border-b border-gray-mid px-16 py-12">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Righe documento</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-left">
                        <thead class="bg-gray-light text-11 font-extrabold uppercase tracking-normal text-gray">
                            <tr>
                                <th class="px-12 py-10">Descrizione</th>
                                <th class="px-12 py-10 text-right">Q.tà</th>
                                <th class="px-12 py-10 text-right">Prezzo</th>
                                <th class="px-12 py-10 text-right">IVA</th>
                                <th class="px-12 py-10 text-right">Totale</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-mid">
                            @foreach ($document->items as $item)
                                <tr>
                                    <td class="px-12 py-12 text-14 font-bold">{{ $item->description }}</td>
                                    <td class="px-12 py-12 text-right text-14 font-semibold">{{ number_format((float) $item->quantity, 2, ',', '.') }}</td>
                                    <td class="px-12 py-12 text-right text-14 font-semibold">€ {{ number_format((float) $item->unit_price, 2, ',', '.') }}</td>
                                    <td class="px-12 py-12 text-right text-14 font-semibold">{{ number_format((float) $item->vat_rate, 2, ',', '.') }}%</td>
                                    <td class="px-12 py-12 text-right text-14 font-black">€ {{ number_format((float) $item->line_total, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-gray-mid bg-gray-light text-14 font-black">
                            <tr><td colspan="4" class="px-12 py-10 text-right">Imponibile</td><td class="px-12 py-10 text-right">€ {{ number_format((float) $document->subtotal, 2, ',', '.') }}</td></tr>
                            <tr><td colspan="4" class="px-12 py-10 text-right">IVA</td><td class="px-12 py-10 text-right">€ {{ number_format((float) $document->vat_total, 2, ',', '.') }}</td></tr>
                            <tr><td colspan="4" class="px-12 py-10 text-right">Totale</td><td class="px-12 py-10 text-right">€ {{ number_format((float) $document->total, 2, ',', '.') }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            @if ($document->notes)
                <section class="rounded-10 border border-gray-mid bg-white p-16">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Note</p>
                    <p class="mt-8 whitespace-pre-line text-14 font-semibold leading-relaxed">{{ $document->notes }}</p>
                </section>
            @endif
        </div>

        <aside class="space-y-16">
            <section class="rounded-10 border border-gray-mid bg-white p-16">
                <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Cliente</p>
                <h2 class="mt-6 text-20 font-black leading-tight">{{ $document->customer_name }}</h2>
                <div class="mt-10 space-y-5 text-14 font-semibold text-gray">
                    <p>{{ $document->customer_email ?: 'Email non indicata' }}</p>
                    <p>{{ $document->customer_pec ? 'PEC '.$document->customer_pec : 'PEC non indicata' }}</p>
                    <p>{{ $document->customer_phone ?: 'Telefono non indicato' }}</p>
                    <p>{{ trim(collect([$document->customer_address, $document->customer_street_number])->filter()->implode(' ')) ?: 'Indirizzo non indicato' }}</p>
                    <p>{{ trim(($document->customer_postal_code ? $document->customer_postal_code . ' ' : '') . ($document->customer_city ?: '') . ($document->customer_province ? ' (' . $document->customer_province . ')' : '')) ?: 'Città non indicata' }}</p>
                    <p>CF {{ $document->customer_tax_code ?: '-' }} · P.IVA {{ $document->customer_vat_number ?: '-' }}</p>
                    <p>Cod.Dest. {{ $document->customer_recipient_code ?: '-' }}</p>
                </div>
            </section>

            <section class="rounded-10 border border-gray-mid bg-white p-16">
                <div class="flex items-start justify-between gap-12">
                    <div>
                        <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Pagamenti</p>
                        <h2 class="mt-6 text-20 font-black leading-tight">{{ $document->payment_status_label }}</h2>
                    </div>
                    <span class="rounded-full {{ $document->payment_status === 'paid' ? 'bg-whatsapp/10 text-whatsapp' : ($document->payment_status === 'overdue' ? 'bg-brand/10 text-brand' : 'bg-gray-light text-gray') }} px-10 py-6 text-11 font-extrabold uppercase tracking-normal">{{ $document->payment_status_label }}</span>
                </div>
                <div class="mt-12 space-y-10">
                    @forelse ($document->paymentSchedules as $payment)
                        <form method="POST" action="{{ route('admin.documents.payments.update', $document) }}" class="rounded-10 border border-gray-mid bg-gray-light p-12">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="schedule_id" value="{{ $payment->id }}">
                            <div class="flex items-start justify-between gap-10">
                                <div>
                                    <p class="text-14 font-black">€ {{ number_format((float) $payment->amount, 2, ',', '.') }}</p>
                                    <p class="mt-4 text-11 font-bold uppercase tracking-normal text-gray">{{ optional($payment->due_date)->format('d/m/Y') }} · {{ $payment->method ?: 'Metodo non indicato' }}</p>
                                </div>
                                <span class="rounded-full bg-white px-8 py-5 text-11 font-extrabold uppercase tracking-normal text-gray">{{ ucfirst($payment->status) }}</span>
                            </div>
                            <div class="mt-10 grid gap-8 md:grid-cols-2 xl:grid-cols-1">
                                <input name="paid_amount" value="{{ $payment->paid_amount }}" type="number" min="0" step="0.01" class="rounded-10 border-gray-mid px-10 py-8 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                                <input name="paid_at" value="{{ optional($payment->paid_at)->format('Y-m-d') }}" type="date" class="rounded-10 border-gray-mid px-10 py-8 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                            </div>
                            <button type="submit" class="mt-8 w-full rounded-10 bg-black-nike px-12 py-8 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-black">Aggiorna pagamento</button>
                        </form>
                    @empty
                        <p class="mt-10 text-14 font-semibold text-gray">Nessuna scadenza inserita.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-10 border border-gray-mid bg-white p-16">
                <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Collegamenti</p>
                @if ($document->sourceDocument)
                    <a href="{{ route('admin.documents.show', $document->sourceDocument) }}" class="mt-8 block text-14 font-bold text-bullstar underline-offset-4 hover:underline">Origine: {{ $document->sourceDocument->type_label }} {{ $document->sourceDocument->display_code }}</a>
                @endif
                @forelse ($document->generatedDocuments as $generated)
                    <a href="{{ route('admin.documents.show', $generated) }}" class="mt-8 block text-14 font-bold text-bullstar underline-offset-4 hover:underline">{{ $generated->type_label }} {{ $generated->display_code }}</a>
                @empty
                    @unless ($document->sourceDocument)
                        <p class="mt-8 text-14 font-semibold text-gray">Nessun documento collegato.</p>
                    @endunless
                @endforelse
            </section>
        </aside>
    </section>
@endsection
