@extends('admin.layouts.app')

@section('title', $document->type_label . ' ' . $document->display_code . ' - Stuart Admin')
@section('page_title', $document->type_label . ' ' . $document->display_code)
@section('active_nav', 'documents')

@section('content')
    <section class="mb-16 flex flex-col gap-12 md:flex-row md:items-center md:justify-between">
        <div class="flex flex-wrap gap-8">
            @php
                $availableGenerationTypes = collect($document->availableActions())
                    ->map(fn ($type) => $type === 'order' ? 'offline_order' : $type)
                    ->all();
            @endphp
            <a href="{{ route('admin.documents.index') }}" class="rounded-10 border border-gray-mid bg-white px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Archivio</a>
            <a href="{{ route('admin.documents.preview', $document) }}" target="_blank" class="rounded-10 border border-gray-mid bg-white px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">PDF</a>
            @if ($document->type === 'invoice')
                <a href="{{ route('admin.documents.xml', $document) }}" class="rounded-10 border border-gray-mid bg-white px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">XML</a>
            @endif
            <a href="{{ route('admin.documents.edit', $document) }}" class="rounded-10 bg-bullstar px-12 py-8 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover">Modifica</a>
            <form method="POST" action="{{ route('admin.documents.duplicate', $document) }}">
                @csrf
                <input type="hidden" name="type" value="{{ $document->type }}">
                <button type="submit" class="rounded-10 border border-gray-mid bg-white px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Copia</button>
            </form>
            @foreach ($types as $type => $label)
                @if ($type !== $document->type && in_array($type, $availableGenerationTypes, true))
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
                        @if ($document->type === 'invoice')
                            <p class="mt-2 text-12 font-bold uppercase tracking-normal text-gray">{{ $document->fiscal_type ?: 'TD01' }} - {{ config('documents.invoice_fiscal_types')[$document->fiscal_type ?: 'TD01'] ?? 'Fattura' }}</p>
                        @endif
                    </div>
                    <div class="text-left md:text-right">
                        <span class="rounded-full {{ $document->status_badge_class }} px-10 py-6 text-11 font-extrabold uppercase tracking-normal">{{ $document->status_label }}</span>
                        @unless ($document->type === 'delivery_note')
                            <p class="mt-10 text-30 font-black">€ {{ number_format((float) $document->total, 2, ',', '.') }}</p>
                        @endunless
                    </div>
                </div>
            </section>

            @if ($document->related_documents->isNotEmpty())
                <section class="rounded-10 border border-gray-mid bg-white p-16">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Filiera documenti</p>
                    <div class="mt-10 flex flex-wrap items-center gap-8">
                        <span class="rounded-full bg-black-nike px-10 py-6 text-11 font-extrabold uppercase tracking-normal text-white">{{ $document->type_label }} {{ $document->display_code }}</span>
                        @foreach ($document->related_documents as $relatedDocument)
                            <a href="{{ route('admin.documents.show', ['document' => $relatedDocument['id']]) }}" class="rounded-full {{ $relatedDocument['class'] }} px-10 py-6 text-11 font-extrabold uppercase tracking-normal underline-offset-4 hover:underline">{{ $relatedDocument['label'] }}</a>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="overflow-hidden rounded-10 border border-gray-mid bg-white">
                <div class="border-b border-gray-mid px-16 py-12">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Righe documento</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full {{ $document->type === 'delivery_note' ? 'min-w-[680px]' : 'min-w-[920px]' }} text-left">
                        <thead class="bg-gray-light text-11 font-extrabold uppercase tracking-normal text-gray">
                            <tr>
                                <th class="px-12 py-10">Codice</th>
                                <th class="px-12 py-10">Descrizione</th>
                                <th class="px-12 py-10 text-right">Q.tà</th>
                                @unless ($document->type === 'delivery_note')
                                    <th class="px-12 py-10 text-right">Prezzo</th>
                                    <th class="px-12 py-10 text-right">IVA</th>
                                    <th class="px-12 py-10 text-right">Imponibile</th>
                                @endunless
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-mid">
                            @foreach ($document->items as $item)
                                <tr>
                                    <td class="px-12 py-12 text-14 font-semibold">{{ $item->item_code ?: '-' }}</td>
                                    <td class="whitespace-pre-line px-12 py-12 text-14 font-bold">{{ $item->description }}</td>
                                    <td class="px-12 py-12 text-right text-14 font-semibold">{{ number_format((float) $item->quantity, 2, ',', '.') }}</td>
                                    @unless ($document->type === 'delivery_note')
                                        <td class="px-12 py-12 text-right text-14 font-semibold">€ {{ rtrim(preg_replace('/0{1,2}$/', '', number_format((float) $item->unit_price, 4, ',', '.')), ',') }}</td>
                                        <td class="px-12 py-12 text-right text-14 font-semibold">{{ number_format((float) $item->vat_rate, 2, ',', '.') }}%</td>
                                        <td class="px-12 py-12 text-right text-14 font-black">€ {{ number_format((float) $item->line_subtotal, 2, ',', '.') }}</td>
                                    @endunless
                                </tr>
                            @endforeach
                        </tbody>
                        @unless ($document->type === 'delivery_note')
                            <tfoot class="border-t border-gray-mid bg-gray-light text-14 font-black">
                                <tr><td colspan="5" class="px-12 py-10 text-right">Imponibile</td><td class="px-12 py-10 text-right">€ {{ number_format((float) $document->subtotal, 2, ',', '.') }}</td></tr>
                                <tr><td colspan="5" class="px-12 py-10 text-right">IVA</td><td class="px-12 py-10 text-right">€ {{ number_format((float) $document->vat_total, 2, ',', '.') }}</td></tr>
                                <tr><td colspan="5" class="px-12 py-10 text-right">Totale</td><td class="px-12 py-10 text-right">€ {{ number_format((float) $document->total, 2, ',', '.') }}</td></tr>
                            </tfoot>
                        @endunless
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

            @if ($document->shipping_name || $document->shipping_address || $document->shipping_city || $document->shipping_postal_code)
                <section class="rounded-10 border border-gray-mid bg-white p-16">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Destinazione</p>
                    <h2 class="mt-6 text-20 font-black leading-tight">{{ $document->shipping_name ?: $document->customer_name }}</h2>
                    <div class="mt-10 space-y-5 text-14 font-semibold text-gray">
                        <p>{{ $document->shipping_phone ?: $document->customer_phone ?: 'Telefono non indicato' }}</p>
                        <p>{{ trim(collect([$document->shipping_address, $document->shipping_street_number])->filter()->implode(' ')) ?: 'Indirizzo non indicato' }}</p>
                        <p>{{ trim(($document->shipping_postal_code ? $document->shipping_postal_code . ' ' : '') . ($document->shipping_city ?: '') . ($document->shipping_province ? ' (' . $document->shipping_province . ')' : '')) ?: 'Città non indicata' }}</p>
                        <p>{{ $document->shipping_country ?: $document->customer_country ?: 'IT' }}</p>
                    </div>
                </section>
            @endif

            @if ($document->type === 'delivery_note')
                <section class="rounded-10 border border-gray-mid bg-white p-16">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Trasporto</p>
                    <div class="mt-10 space-y-5 text-14 font-semibold text-gray">
                        <p>Causale {{ $document->transport_reason ?: 'Vendita' }}</p>
                        <p>Trasporto a cura {{ $document->transport_care ?: '-' }}</p>
                        <p>Data inizio {{ optional($document->transport_start_date ?: $document->document_date)->format('d/m/Y') }}</p>
                        <p>Aspetto {{ $document->goods_appearance ?: '-' }}</p>
                        <p>Colli {{ $document->parcels_count ?: '-' }}</p>
                        <p>Peso lordo {{ $document->gross_weight_kg ? number_format((float) $document->gross_weight_kg, 2, ',', '.') . ' kg' : '-' }}</p>
                        <p>Peso netto {{ $document->net_weight_kg ? number_format((float) $document->net_weight_kg, 2, ',', '.') . ' kg' : '-' }}</p>
                        <p>Vettore {{ $document->carrier_name ?: '-' }}</p>
                    </div>
                </section>
            @endif

            @unless ($document->type === 'delivery_note')
                <section class="rounded-10 border border-gray-mid bg-white p-16">
                    <div class="flex items-start justify-between gap-12">
                        <div>
                            <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Pagamenti</p>
                            <h2 class="mt-6 text-20 font-black leading-tight">{{ $document->payment_status_label }}</h2>
                        </div>
                        <span class="rounded-full {{ $document->payment_status === 'paid' ? 'bg-whatsapp/10 text-whatsapp' : 'bg-gray-light text-gray' }} px-10 py-6 text-11 font-extrabold uppercase tracking-normal">{{ $document->payment_status_label }}</span>
                    </div>
                    <div class="mt-10 space-y-4 text-13 font-semibold text-gray">
                        <p>Condizioni {{ $document->payment_conditions ?: 'TP02' }}</p>
                        @if ($document->payment_method || $document->bank_name || $document->bank_iban)
                            <p>{{ $document->payment_method }}{{ $document->paymentMethod?->name ? ' - '.$document->paymentMethod->name : '' }}</p>
                            @if ($document->bank_name || $document->bank_iban)
                                <p>{{ collect([$document->bank_name, $document->bank_iban, $document->bank_bic])->filter()->implode(' · ') }}</p>
                            @endif
                        @endif
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
                                        <p class="mt-4 text-11 font-bold uppercase tracking-normal text-gray">{{ optional($payment->due_date)->format('d/m/Y') }} · {{ $payment->payment_method_code ?: '--' }} {{ $payment->paymentMethod?->name ?: $payment->method ?: 'Metodo non indicato' }}</p>
                                    </div>
                                    <span class="rounded-full bg-white px-8 py-5 text-11 font-extrabold uppercase tracking-normal text-gray">{{ $payment->status_label }}</span>
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
            @endunless

            <section class="rounded-10 border border-gray-mid bg-white p-16">
                <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Collegamenti</p>
                @php
                    $shownRelatedKeys = $directRelations
                        ->map(fn ($directRelation) => $directRelation['key'])
                        ->all();
                    $hasLinks = $directRelations->isNotEmpty() || $document->sourceDocument || $document->generatedDocuments->isNotEmpty();
                @endphp
                @foreach ($directRelations as $directRelation)
                    <div class="mt-8 flex items-center justify-between gap-8">
                        <a href="{{ route('admin.documents.show', $directRelation['document']) }}" class="min-w-0 text-14 font-bold text-bullstar underline-offset-4 hover:underline">{{ $directRelation['document']->type_label }} {{ $directRelation['document']->display_code }}</a>
                        <form method="POST" action="{{ route('admin.documents.relations.destroy', [$document, $directRelation['relation']]) }}" onsubmit="return confirm('Eliminare questo collegamento?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-10 border border-gray-mid px-8 py-5 text-11 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Scollega</button>
                        </form>
                    </div>
                @endforeach
                @if ($document->sourceDocument && ! in_array($document->sourceDocument->currentDocumentType()->value.'-'.$document->sourceDocument->id, $shownRelatedKeys, true))
                    <div class="mt-8 flex items-center justify-between gap-8">
                        <a href="{{ route('admin.documents.show', $document->sourceDocument) }}" class="min-w-0 text-14 font-bold text-bullstar underline-offset-4 hover:underline">Origine: {{ $document->sourceDocument->type_label }} {{ $document->sourceDocument->display_code }}</a>
                        <form method="POST" action="{{ route('admin.documents.source-links.destroy', [$document, $document->sourceDocument]) }}" onsubmit="return confirm('Eliminare questo collegamento?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-10 border border-gray-mid px-8 py-5 text-11 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Scollega</button>
                        </form>
                    </div>
                @endif
                @forelse ($document->generatedDocuments as $generated)
                    @unless (in_array($generated->currentDocumentType()->value.'-'.$generated->id, $shownRelatedKeys, true))
                        <div class="mt-8 flex items-center justify-between gap-8">
                            <a href="{{ route('admin.documents.show', $generated) }}" class="min-w-0 text-14 font-bold text-bullstar underline-offset-4 hover:underline">{{ $generated->type_label }} {{ $generated->display_code }}</a>
                            <form method="POST" action="{{ route('admin.documents.source-links.destroy', [$document, $generated]) }}" onsubmit="return confirm('Eliminare questo collegamento?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-10 border border-gray-mid px-8 py-5 text-11 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Scollega</button>
                            </form>
                        </div>
                    @endunless
                @empty
                @endforelse
                @unless ($hasLinks)
                    <p class="mt-8 text-14 font-semibold text-gray">Nessun documento collegato.</p>
                @endunless
            </section>

            @if (auth('admin')->user()?->hasAdminPermission('shipments.view'))
                <section class="rounded-10 border border-gray-mid bg-white p-16">
                    <div class="flex items-start justify-between gap-12">
                        <div>
                            <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Spedizioni</p>
                            <h2 class="mt-6 text-20 font-black leading-tight">{{ $document->shipments->count() }} collegate</h2>
                        </div>
                        @if (auth('admin')->user()?->hasAdminPermission('shipments.manage'))
                            <a href="{{ route('admin.shipments.create', ['document_id' => $document->id]) }}" class="rounded-10 bg-bullstar px-10 py-8 text-11 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover">Crea</a>
                        @endif
                    </div>
                    <div class="mt-12 space-y-8">
                        @forelse ($document->shipments as $shipment)
                            <a href="{{ route('admin.shipments.show', $shipment) }}" class="block rounded-10 border border-gray-mid bg-gray-light p-12 transition hover:border-bullstar">
                                <div class="flex items-start justify-between gap-10">
                                    <div>
                                        <p class="text-14 font-black">{{ $shipment->carrier_label }} {{ $shipment->tracking_number ?: '#' . $shipment->id }}</p>
                                        <p class="mt-4 text-11 font-bold uppercase tracking-normal text-gray">{{ optional($shipment->shipped_at ?: $shipment->created_at)->format('d/m/Y H:i') }} · {{ $shipment->parcels_count }} colli</p>
                                    </div>
                                    <span class="rounded-full {{ $shipment->status === 'shipped' ? 'bg-whatsapp/10 text-whatsapp' : ($shipment->status === 'failed' ? 'bg-red-50 text-red-700' : 'bg-white text-gray') }} px-8 py-5 text-11 font-extrabold uppercase tracking-normal">{{ $shipment->status_label }}</span>
                                </div>
                            </a>
                        @empty
                            <p class="text-14 font-semibold text-gray">Nessuna spedizione collegata.</p>
                        @endforelse
                    </div>
                </section>
            @endif
        </aside>
    </section>
@endsection
