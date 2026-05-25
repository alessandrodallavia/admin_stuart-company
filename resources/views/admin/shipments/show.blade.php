@extends('admin.layouts.app')

@section('title', 'Spedizione '.$shipment->id.' - Stuart Admin')
@section('page_title', 'Spedizione '.$shipment->id)
@section('active_nav', 'shipments')

@section('content')
    <section class="mb-16 flex flex-col gap-12 md:flex-row md:items-center md:justify-between">
        <div class="flex flex-wrap gap-8">
            <a href="{{ route('admin.shipments.index') }}" class="rounded-10 border border-gray-mid bg-white px-10 py-6 text-11 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Archivio</a>
            @if ($shipment->tracking_url)
                <a href="{{ $shipment->tracking_url }}" target="_blank" class="rounded-10 border border-gray-mid bg-white px-10 py-6 text-11 font-extrabold uppercase tracking-normal transition hover:border-bullstar hover:text-bullstar">Tracking</a>
            @endif
            @if (auth('admin')->user()?->hasAdminPermission('shipments.manage'))
                <a href="{{ route('admin.shipments.create') }}" class="rounded-10 border border-gray-mid bg-white px-10 py-6 text-11 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Nuova spedizione</a>
                @if ($shipment->status === 'failed')
                    <form method="POST" action="{{ route('admin.shipments.retry', $shipment) }}">
                        @csrf
                        <button type="submit" class="rounded-10 bg-bullstar px-10 py-6 text-11 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover">Riprova</button>
                    </form>
                @endif
            @endif
        </div>
    </section>

    <section class="grid gap-16 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-16">
            <section class="rounded-10 border border-gray-mid bg-white p-16">
                <div class="flex flex-col gap-12 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-12 font-extrabold uppercase tracking-normal text-gray">{{ $shipment->carrier_label }}</p>
                        <h2 class="mt-6 text-30 font-black leading-tight">{{ $shipment->tracking_number ?: 'Tracking non disponibile' }}</h2>
                        <p class="mt-6 text-14 font-semibold text-gray">{{ optional($shipment->shipped_at ?: $shipment->created_at)->format('d/m/Y H:i') }}</p>
                    </div>
                    <div class="text-left md:text-right">
                        <span class="rounded-full {{ $shipment->status === 'shipped' ? 'bg-whatsapp/10 text-whatsapp' : ($shipment->status === 'failed' ? 'bg-red-50 text-red-700' : 'bg-gray-light text-gray') }} px-10 py-6 text-11 font-extrabold uppercase tracking-normal">{{ $shipment->status_label }}</span>
                        <p class="mt-10 text-18 font-black">{{ $shipment->parcels_count }} colli</p>
                    </div>
                </div>
                @if ($shipment->error_message)
                    <div class="mt-14 rounded-10 border border-red-200 bg-red-50 p-12 text-14 font-bold text-red-700">{{ $shipment->error_message }}</div>
                @endif
            </section>

            <section class="overflow-hidden rounded-10 border border-gray-mid bg-white">
                <div class="border-b border-gray-mid px-12 py-8">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Colli ed etichette</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left">
                        <thead class="bg-gray-light text-11 font-extrabold uppercase tracking-normal text-gray">
                            <tr>
                                <th class="px-12 py-8">Collo</th>
                                <th class="px-12 py-8">Parcel ID</th>
                                <th class="px-12 py-8">Tracking</th>
                                <th class="px-12 py-8 text-right">Peso</th>
                                <th class="px-12 py-8 text-right">Volume</th>
                                <th class="px-12 py-8">Dropbox</th>
                                <th class="px-12 py-8 text-right">Etichetta</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-mid">
                            @forelse ($shipment->parcels as $parcel)
                                <tr>
                                    <td class="px-12 py-8 text-14 font-black">{{ $parcel->parcel_number }}</td>
                                    <td class="px-12 py-8 text-13 font-semibold text-gray">{{ $parcel->parcel_id ?: '-' }}</td>
                                    <td class="px-12 py-8 text-13 font-semibold text-gray">{{ $parcel->tracking_number ?: '-' }}</td>
                                    <td class="px-12 py-8 text-right text-13 font-semibold">{{ $parcel->weight_kg ?: '-' }}</td>
                                    <td class="px-12 py-8 text-right text-13 font-semibold">{{ $parcel->volume_m3 ?: '-' }}</td>
                                    <td class="px-12 py-8 text-12 font-semibold text-gray">{{ $parcel->dropbox_path ?: '-' }}</td>
                                    <td class="px-12 py-8 text-right">
                                        @if ($parcel->label_stream && auth('admin')->user()?->hasAdminPermission('shipments.manage'))
                                            <form method="POST" action="{{ route('admin.shipments.parcels.label', [$shipment, $parcel]) }}">
                                                @csrf
                                                <button type="submit" class="rounded-10 border border-gray-mid px-10 py-6 text-11 font-extrabold uppercase tracking-normal transition hover:border-bullstar hover:text-bullstar">ZPL</button>
                                            </form>
                                        @else
                                            <span class="text-12 font-semibold text-gray">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-12 py-16 text-14 font-semibold text-gray">Nessun collo salvato.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <aside class="space-y-16">
            <section class="rounded-10 border border-gray-mid bg-white p-16">
                <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Destinatario</p>
                <h2 class="mt-6 text-20 font-black leading-tight">{{ $shipment->recipient_name }}</h2>
                <div class="mt-10 space-y-5 text-14 font-semibold text-gray">
                    <p>{{ $shipment->recipient_email ?: 'Email non indicata' }}</p>
                    <p>{{ $shipment->recipient_phone ?: 'Telefono non indicato' }}</p>
                    <p>{{ $shipment->recipient_address_line }}</p>
                    <p>{{ $shipment->recipient_city_line }}</p>
                    <p>{{ $shipment->recipient_country }}</p>
                </div>
            </section>

            <section class="rounded-10 border border-gray-mid bg-white p-16">
                <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Dati spedizione</p>
                <div class="mt-10 space-y-5 text-14 font-semibold text-gray">
                    <p>Riferimento {{ $shipment->reference ?: '-' }}</p>
                    <p>Peso {{ $shipment->weight_kg }} kg</p>
                    <p>Volume {{ $shipment->volume_m3 }} m3</p>
                    <p>Contrassegno {{ $shipment->cash_on_delivery ? '€ '.number_format((float) $shipment->cash_on_delivery, 2, ',', '.') : '-' }}</p>
                    <p>Ref corriere {{ $shipment->carrier_reference ?: '-' }}</p>
                </div>
            </section>

            <section class="rounded-10 border border-gray-mid bg-white p-16">
                <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Documenti</p>
                @if ($shipment->documents->isNotEmpty())
                    <div class="mt-8 space-y-8">
                        @foreach ($shipment->documents as $linkedDocument)
                            <a href="{{ route('admin.documents.show', $linkedDocument) }}" class="block text-14 font-bold text-bullstar underline-offset-4 hover:underline">{{ $linkedDocument->type_label }} {{ $linkedDocument->display_code }}</a>
                        @endforeach
                    </div>
                @else
                    <p class="mt-8 text-14 font-semibold text-gray">Spedizione libera.</p>
                @endif
            </section>
        </aside>
    </section>
@endsection
