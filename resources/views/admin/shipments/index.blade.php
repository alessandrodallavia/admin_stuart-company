@extends('admin.layouts.app')

@section('title', 'Spedizioni - Stuart Admin')
@section('page_title', 'Spedizioni')
@section('active_nav', 'shipments')

@section('content')
    <section class="mb-16 flex flex-col gap-12 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Archivio spedizioni</p>
            <h2 class="mt-4 text-24 font-black leading-tight">{{ $shipments->total() }} spedizioni</h2>
        </div>
        @if (auth('admin')->user()?->hasAdminPermission('shipments.manage'))
            <a href="{{ route('admin.shipments.create') }}" class="rounded-10 bg-bullstar px-12 py-8 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover">Nuova spedizione</a>
        @endif
    </section>

    <section class="mb-16 overflow-hidden rounded-10 border border-gray-mid bg-white">
        <form method="GET" action="{{ route('admin.shipments.index') }}" class="grid gap-12 p-16 xl:grid-cols-[minmax(220px,1fr)_150px_150px_170px_150px_150px_auto] xl:items-end">
            <label>
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Cerca</span>
                <input name="q" value="{{ $filters['q'] ?? '' }}" type="search" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar" placeholder="Destinatario, tracking, riferimento">
            </label>
            <label>
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Corriere</span>
                <select name="carrier" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    <option value="">Tutti</option>
                    @foreach ($carriers as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['carrier'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Stato</span>
                <select name="status" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    <option value="">Tutti</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Collegamento</span>
                <select name="link" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    <option value="">Tutte</option>
                    <option value="with_document" @selected(($filters['link'] ?? '') === 'with_document')>Con documento</option>
                    <option value="free" @selected(($filters['link'] ?? '') === 'free')>Libere</option>
                </select>
            </label>
            <label>
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Da</span>
                <input name="date_from" value="{{ $filters['date_from'] ?? '' }}" type="date" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
            </label>
            <label>
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">A</span>
                <input name="date_to" value="{{ $filters['date_to'] ?? '' }}" type="date" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
            </label>
            <div class="flex gap-8">
                <button type="submit" class="rounded-10 bg-black-nike px-12 py-8 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-black">Filtra</button>
                <a href="{{ route('admin.shipments.index') }}" class="rounded-10 border border-gray-mid px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Reset</a>
            </div>
        </form>
    </section>

    <form method="POST" action="{{ route('admin.shipments.bordero') }}" target="_blank" class="overflow-hidden rounded-10 border border-gray-mid bg-white">
        @csrf
        @if (auth('admin')->user()?->hasAdminPermission('shipments.manage'))
            <div class="flex flex-col gap-8 border-b border-gray-mid bg-white px-12 py-8 md:flex-row md:items-center md:justify-between">
                <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Seleziona le spedizioni e stampa il borderò del corriere</p>
                <div class="flex flex-wrap gap-8">
                    <select name="carrier" class="min-w-[120px] rounded-10 border-gray-mid py-6 pl-10 pr-32 text-12 font-semibold focus:border-bullstar focus:ring-bullstar">
                        @foreach ($carriers as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-10 bg-black-nike px-10 py-6 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-black">Borderò</button>
                </div>
            </div>
        @endif
        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] text-left">
                <thead class="border-b border-gray-mid bg-gray-light text-11 font-extrabold uppercase tracking-normal text-gray">
                    <tr>
                        <th class="px-12 py-8"></th>
                        <th class="px-12 py-8">Data</th>
                        <th class="px-12 py-8">Corriere</th>
                        <th class="px-12 py-8">Stato</th>
                        <th class="px-12 py-8">Destinatario</th>
                        <th class="px-12 py-8">Riferimento</th>
                        <th class="px-12 py-8">Documento</th>
                        <th class="px-12 py-8">Tracking</th>
                        <th class="px-12 py-8 text-right">Colli</th>
                        <th class="px-12 py-8 text-right">Azioni</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-mid">
                    @forelse ($shipments as $shipment)
                        <tr>
                            <td class="px-12 py-8">
                                @if (auth('admin')->user()?->hasAdminPermission('shipments.manage'))
                                    <input type="checkbox" name="shipment_ids[]" value="{{ $shipment->id }}" class="rounded border-gray-mid text-bullstar focus:ring-bullstar">
                                @endif
                            </td>
                            <td class="px-12 py-8 text-12 font-bold text-gray">{{ optional($shipment->created_at)->format('d/m/Y H:i') }}</td>
                            <td class="px-12 py-8 text-14 font-black">{{ $shipment->carrier_label }}</td>
                            <td class="px-12 py-8">
                                <span class="rounded-full {{ $shipment->status === 'shipped' ? 'bg-whatsapp/10 text-whatsapp' : ($shipment->status === 'failed' ? 'bg-red-50 text-red-700' : 'bg-gray-light text-gray') }} px-10 py-6 text-11 font-extrabold uppercase tracking-normal">{{ $shipment->status_label }}</span>
                            </td>
                            <td class="px-12 py-8">
                                <p class="max-w-[220px] truncate text-14 font-black">{{ $shipment->recipient_name }}</p>
                                <p class="mt-3 max-w-[220px] truncate text-11 font-semibold text-gray">{{ $shipment->recipient_city_line }}</p>
                            </td>
                            <td class="px-12 py-8 text-13 font-semibold text-gray">{{ $shipment->reference ?: '-' }}</td>
                            <td class="px-12 py-8">
                                @if ($shipment->documents->isNotEmpty())
                                    <div class="space-y-3">
                                        @foreach ($shipment->documents as $linkedDocument)
                                            <a href="{{ route('admin.documents.show', $linkedDocument) }}" class="block text-13 font-bold text-bullstar underline-offset-4 hover:underline">{{ $linkedDocument->display_code }}</a>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-13 font-semibold text-gray">Libera</span>
                                @endif
                            </td>
                            <td class="px-12 py-8 text-13 font-semibold text-gray">
                                @if ($shipment->tracking_url)
                                    <a href="{{ $shipment->tracking_url }}" target="_blank" class="text-bullstar underline-offset-4 hover:underline">{{ $shipment->tracking_number ?: 'Tracking' }}</a>
                                @else
                                    {{ $shipment->tracking_number ?: '-' }}
                                @endif
                            </td>
                            <td class="px-12 py-8 text-right text-14 font-black">{{ $shipment->parcels_count }}</td>
                            <td class="px-12 py-8 text-right">
                                <a href="{{ route('admin.shipments.show', $shipment) }}" class="rounded-10 border border-gray-mid px-10 py-6 text-11 font-extrabold uppercase tracking-normal transition hover:border-bullstar hover:text-bullstar">Apri</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-12 py-14">
                                <div class="rounded-10 border border-dashed border-gray-mid bg-gray-light p-20 text-14 font-semibold text-gray">Nessuna spedizione con questi filtri.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>

    <div class="mt-16">
        {{ $shipments->links() }}
    </div>
@endsection
