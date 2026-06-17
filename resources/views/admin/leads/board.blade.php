@extends('admin.layouts.app')

@section('title', 'Riepilogo lead - Stuart Admin')
@section('page_title', 'Riepilogo lead')
@section('active_nav', 'leads')

@section('content')
            <div class="mb-16 flex flex-wrap items-center justify-end gap-8">
                <a href="{{ route('admin.leads.index') }}" class="rounded-10 border border-gray-mid bg-white px-12 py-10 text-12 font-extrabold uppercase tracking-normal text-black-nike transition hover:border-black-nike">
                    Tabella
                </a>
                <a href="{{ route('admin.leads.board') }}" class="rounded-10 border border-bullstar bg-bullstar px-12 py-10 text-12 font-extrabold uppercase tracking-normal text-white">
                    Riepilogo
                </a>
            </div>

            <section class="min-h-[720px] overflow-hidden rounded-10 border border-gray-mid bg-gray-light">
                <div class="overflow-x-auto">
                    <div class="grid min-w-[1280px] grid-cols-5">
                        @foreach ($columns as $column)
                            <section class="min-h-[720px] border-r border-gray-mid last:border-r-0">
                                <header class="border-b border-gray-mid bg-white px-20 py-20">
                                    <div class="flex items-start justify-between gap-12">
                                        <h2 class="{{ $column['accent'] }} text-18 font-black leading-none tracking-normal">{{ $column['label'] }}</h2>
                                        <p class="text-18 font-semibold leading-none text-gray">{{ $column['count'] }}</p>
                                    </div>
                                    <div class="mt-12 flex items-center justify-between gap-12 text-14 leading-none">
                                        <p class="font-semibold text-gray">Importo totale</p>
                                        <p class="font-bold text-gray">{{ number_format((float) $column['total'], 2, ',', '.') }} €</p>
                                    </div>
                                </header>

                                <div class="min-h-[590px] bg-gray-light px-16 py-16">
                                    <div class="space-y-10">
                                        @forelse ($column['leads'] as $lead)
                                            <article class="overflow-hidden rounded-10 border border-gray-mid bg-white">
                                                <div class="p-12">
                                                    <h3 class="truncate text-14 font-semibold leading-tight tracking-normal">
                                                        WhatsApp - {{ $lead->name ?: $lead->phone ?: 'Lead senza nome' }}
                                                    </h3>

                                                    @if ($lead->email)
                                                        <div class="mt-10">
                                                            <span class="inline-flex max-w-full items-center rounded-full bg-whatsapp/10 px-8 py-5 text-11 font-bold leading-none text-black-nike">
                                                                <span class="mr-6 inline-flex h-16 w-16 shrink-0 items-center justify-center rounded-full border border-black-nike text-11 leading-none">@</span>
                                                                <span class="truncate">{{ $lead->email }}</span>
                                                            </span>
                                                        </div>
                                                    @endif

                                                    <div class="mt-8 space-y-5 text-12 font-semibold leading-[18px] text-gray">
                                                        @if ($lead->club)
                                                            <p class="truncate">{{ $lead->club }}</p>
                                                        @endif
                                                        <p class="truncate">{{ $lead->landing_page ?: $lead->entry_page ?: 'Pagina non salvata' }}</p>
                                                        <p class="admin-line-clamp-2">{{ $lead->message ?: 'Nessun messaggio salvato.' }}</p>
                                                        <p class="truncate">{{ $lead->phone ?: 'Telefono mancante' }}</p>
                                                    </div>
                                                </div>

                                                @if (in_array($column['key'], ['completed', 'lost'], true))
                                                    <div class="border-t border-gray-mid px-12 py-10">
                                                        <p class="text-14 font-semibold leading-none text-black-nike">
                                                            {{ $column['key'] === 'lost' ? 'Data perdita' : 'Data di chiusura' }}: {{ optional($lead->updated_at)->format('d/m/Y') ?: '-' }}
                                                        </p>
                                                    </div>
                                                @else
                                                    <a href="{{ route('admin.leads.index', ['lead' => $lead]) }}" class="block border-t border-gray-mid px-12 py-10 text-center text-14 font-black leading-none tracking-normal text-bullstar transition hover:bg-gray-light">
                                                        Scegli un'azione
                                                    </a>
                                                @endif
                                            </article>
                                        @empty
                                            <div class="rounded-10 border border-dashed border-gray-mid bg-white px-12 py-12 text-12 font-semibold text-gray">
                                                Nessun lead in questa colonna.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            </section>
                        @endforeach
                    </div>
                </div>
            </section>
@endsection
