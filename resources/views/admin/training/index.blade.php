@extends('admin.layouts.app')

@section('title', 'Formazione - Stuart Admin')
@section('page_title', 'Formazione')
@section('active_nav', 'training')

@section('content')
    <section class="border-b border-gray-mid pb-20">
        <div class="flex flex-col gap-12 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="text-12 font-extrabold uppercase tracking-normal text-bullstar">Ambiente isolato</p>
                <h2 class="mt-4 text-24 font-black leading-tight">Scenari operatore</h2>
                <p class="mt-6 max-w-[720px] text-14 font-semibold leading-[22px] text-gray">Genera contatti realistici e gestiscili nelle normali sezioni Leads, WhatsApp ed Email. Nessuna comunicazione viene inviata all’esterno.</p>
            </div>
            <form method="POST" action="{{ route('admin.training.reset') }}" onsubmit="return confirm('Eliminare tutti i dati formativi?')">
                @csrf
                @method('DELETE')
                <button class="rounded-10 border border-red-200 bg-white px-14 py-10 text-12 font-extrabold uppercase tracking-normal text-red-700 transition hover:bg-red-50">Azzera formazione</button>
            </form>
        </div>
    </section>

    @unless ($isTrainingActive)
        <div class="mt-20 border-l-4 border-bullstar bg-white px-16 py-14">
            <p class="text-14 font-black">Attiva la modalità formazione per avviare gli scenari.</p>
            <form method="POST" action="{{ route('admin.training.toggle') }}" class="mt-10">
                @csrf
                <button class="rounded-10 bg-bullstar px-14 py-10 text-12 font-extrabold uppercase tracking-normal text-white">Avvia formazione</button>
            </form>
        </div>
    @endunless

    <div class="grid gap-12 py-20 md:grid-cols-3 {{ $isTrainingActive ? '' : 'opacity-50' }}">
        @foreach ([
            ['complete', 'Percorso completo', 'Crea lead, conversazione WhatsApp ed email ricevuta.'],
            ['whatsapp', 'Nuovo lead WhatsApp', 'Crea un contatto con primo messaggio WhatsApp da gestire.'],
            ['email', 'Nuovo lead email', 'Crea un contatto con una richiesta ricevuta via email.'],
        ] as [$key, $title, $description])
            <form method="POST" action="{{ route('admin.training.scenarios.store') }}" class="flex min-h-[190px] flex-col justify-between rounded-10 border border-gray-mid bg-white p-16">
                @csrf
                <input type="hidden" name="scenario" value="{{ $key }}">
                <div>
                    <h3 class="text-18 font-black leading-tight">{{ $title }}</h3>
                    <p class="mt-8 text-14 font-semibold leading-[22px] text-gray">{{ $description }}</p>
                </div>
                <button @disabled(! $isTrainingActive) class="mt-16 rounded-10 bg-bullstar px-14 py-10 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover disabled:cursor-not-allowed disabled:bg-gray">Avvia scenario</button>
            </form>
        @endforeach
    </div>

    <p class="text-13 font-bold text-gray">Lead formativi presenti: {{ $trainingLeadsCount }}</p>

    @if ($isTrainingActive && $trainingLeads->isNotEmpty())
        <section class="mt-20 border-t border-gray-mid pt-20">
            <h2 class="text-20 font-black leading-tight">Simula risposta cliente</h2>
            <div class="mt-12 divide-y divide-gray-mid border-y border-gray-mid">
                @foreach ($trainingLeads as $lead)
                    <div class="grid gap-12 bg-white px-14 py-14 lg:grid-cols-[1fr_auto_auto] lg:items-center">
                        <div>
                            <p class="text-14 font-black">{{ $lead->name }}</p>
                            <p class="mt-3 text-12 font-semibold text-gray">{{ $lead->email }} · {{ $lead->phone }}</p>
                            <form method="POST" action="{{ route('admin.training.leads.complete-payment', $lead) }}" class="mt-8">
                                @csrf
                                <button class="text-11 font-extrabold uppercase tracking-normal text-bullstar hover:underline">Simula pagamento completato</button>
                            </form>
                        </div>
                        @if ($lead->whatsappConversation)
                            <form method="POST" action="{{ route('admin.training.leads.reply', $lead) }}" class="flex gap-6">
                                @csrf
                                <input type="hidden" name="channel" value="whatsapp">
                                <select name="reply" class="rounded-10 border-gray-mid px-10 py-8 text-12 font-bold focus:border-bullstar focus:ring-bullstar">
                                    <option value="interested">Cliente interessato</option>
                                    <option value="quote_change">Modifica preventivo</option>
                                    <option value="bank_transfer">Richiede bonifico</option>
                                    <option value="thanks">Ringraziamento</option>
                                </select>
                                <button class="rounded-10 border border-whatsapp px-10 py-8 text-12 font-extrabold uppercase tracking-normal text-whatsapp">WhatsApp</button>
                            </form>
                        @endif
                        @if ($lead->emailConversations->isNotEmpty())
                            <form method="POST" action="{{ route('admin.training.leads.reply', $lead) }}" class="flex gap-6">
                                @csrf
                                <input type="hidden" name="channel" value="email">
                                <select name="reply" class="rounded-10 border-gray-mid px-10 py-8 text-12 font-bold focus:border-bullstar focus:ring-bullstar">
                                    <option value="interested">Cliente interessato</option>
                                    <option value="quote_change">Modifica preventivo</option>
                                    <option value="bank_transfer">Richiede bonifico</option>
                                    <option value="thanks">Ringraziamento</option>
                                </select>
                                <button class="rounded-10 border border-bullstar px-10 py-8 text-12 font-extrabold uppercase tracking-normal text-bullstar">Email</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@endsection
