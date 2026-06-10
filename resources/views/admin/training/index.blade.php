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
                <p class="mt-6 max-w-[720px] text-14 font-semibold leading-[22px] text-gray">Genera contatti realistici e gestiscili nelle normali sezioni Leads, WhatsApp ed Email.</p>
            </div>
            <form method="POST" action="{{ route('admin.training.reset') }}" onsubmit="return confirm('Eliminare tutti i dati formativi?')">
                @csrf
                @method('DELETE')
                <button class="rounded-10 border border-red-200 bg-white px-14 py-10 text-12 font-extrabold uppercase tracking-normal text-red-700 transition hover:bg-red-50">Azzera formazione</button>
            </form>
        </div>
    </section>

    @if ($isTrainingActive)
        <div class="mt-20 rounded-10 border border-red-300 border-l-4 border-l-red-600 bg-red-50 px-16 py-14">
            <p class="text-12 font-extrabold uppercase tracking-normal text-red-700">Attenzione: comportamento WhatsApp durante il test</p>
            <p class="mt-5 text-14 font-bold leading-[22px] text-black-nike">Tutti i messaggi in uscita, compresa la prima risposta automatica, le proposte e i link pagamento, vengono inviati realmente.</p>
            <p class="mt-5 text-14 font-bold leading-[22px] text-red-700">Dopo il primo messaggio con ID richiesta, le risposte inviate dal telefono usato per il test non funzionano: vengono temporaneamente ignorate e non salvate fino a quando esci dalla formazione.</p>
        </div>
    @endif

    @unless ($isTrainingActive)
        <div class="mt-20 border-l-4 border-bullstar bg-white px-16 py-14">
            <p class="text-14 font-black">Attiva la modalità formazione per avviare gli scenari.</p>
            <form method="POST" action="{{ route('admin.training.toggle') }}" class="mt-10">
                @csrf
                <button class="rounded-10 bg-bullstar px-14 py-10 text-12 font-extrabold uppercase tracking-normal text-white">Avvia formazione</button>
            </form>
        </div>
    @endunless

    <div class="grid gap-12 py-20 md:grid-cols-2 {{ $isTrainingActive ? '' : 'opacity-50' }}">
        @foreach ([
            ['whatsapp', 'Nuovo lead WhatsApp', 'Crea un lead senza dati personali. Dopo la creazione, invia un vero messaggio WhatsApp usando il riferimento generato.', 'whatsapp'],
            ['email', 'Nuovo lead email', 'Crea un contatto senza nome e telefono, con una richiesta ricevuta via email.', 'email'],
        ] as [$key, $title, $description, $contactField])
            <form method="POST" action="{{ route('admin.training.scenarios.store') }}" class="flex min-h-[190px] flex-col justify-between rounded-10 border border-gray-mid bg-white p-16">
                @csrf
                <input type="hidden" name="scenario" value="{{ $key }}">
                <div>
                    <h3 class="text-18 font-black leading-tight">{{ $title }}</h3>
                    <p class="mt-8 text-14 font-semibold leading-[22px] text-gray">{{ $description }}</p>
                    @if ($contactField === 'whatsapp')
                        <div class="mt-12 rounded-10 bg-gray-light p-10">
                            <p class="text-11 font-extrabold uppercase tracking-normal text-gray">Numero a cui scrivere</p>
                            <p class="mt-5 text-16 font-black">{{ $whatsappPhone }}</p>
                            <p class="mt-8 text-11 font-semibold leading-[16px] text-gray">Il messaggio dovrà contenere <strong>ID richiesta: &lt;ID&gt;</strong>. Il resto del testo può essere scritto liberamente.</p>
                        </div>
                    @elseif ($contactField === 'email')
                        <label class="mt-12 block">
                            <span class="text-11 font-extrabold uppercase tracking-normal text-gray">Email reale</span>
                            <input name="contact_email" value="{{ old('scenario') === $key ? old('contact_email') : '' }}" type="email" maxlength="255" required placeholder="nome@email.it" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                            <span class="mt-5 block text-11 font-semibold leading-[16px] text-gray">Usata solo come mittente nel dato simulato. Nessuna email verrà inviata realmente.</span>
                        </label>
                    @endif
                </div>
                <button @disabled(! $isTrainingActive) class="mt-16 rounded-10 bg-bullstar px-14 py-10 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover disabled:cursor-not-allowed disabled:bg-gray">Avvia scenario</button>
            </form>
        @endforeach
    </div>

    <p class="text-13 font-bold text-gray">Lead formativi presenti: {{ $trainingLeadsCount }}</p>

    @if ($isTrainingActive && $trainingLeads->isNotEmpty())
        <section class="mt-20 border-t border-gray-mid pt-20">
            <h2 class="text-20 font-black leading-tight">Simula risposta cliente email</h2>
            <div class="mt-12 divide-y divide-gray-mid border-y border-gray-mid">
                @foreach ($trainingLeads as $lead)
                    <div class="grid gap-12 bg-white px-14 py-14 lg:grid-cols-[1fr_auto_auto] lg:items-center">
                        <div>
                            <p class="text-14 font-black">{{ $lead->name ?: 'Contatto senza nome' }}</p>
                            <p class="mt-3 text-12 font-semibold text-gray">{{ collect([$lead->email, $lead->phone])->filter()->join(' · ') ?: 'Contatto non disponibile' }}</p>
                            @if ($lead->training_scenario === 'whatsapp' && ! $lead->whatsappConversation)
                                <div class="mt-8 rounded-10 border border-whatsapp/30 bg-whatsapp/10 p-10">
                                    <p class="text-11 font-extrabold uppercase tracking-normal text-whatsapp">In attesa del messaggio reale</p>
                                    <p class="mt-6 text-12 font-semibold text-gray">Invia al numero <strong class="text-black-nike">{{ $whatsappPhone }}</strong> un messaggio che contenga:</p>
                                    <p class="mt-6 rounded-10 bg-white px-10 py-8 text-14 font-black text-black-nike">ID richiesta: {{ $lead->uuid }}</p>
                                    <p class="mt-6 text-11 font-semibold leading-[16px] text-gray">Non serve copiare un testo completo: questa riga può essere inserita in qualsiasi messaggio.</p>
                                </div>
                            @endif
                            <form method="POST" action="{{ route('admin.training.leads.complete-payment', $lead) }}" class="mt-8">
                                @csrf
                                <button class="text-11 font-extrabold uppercase tracking-normal text-bullstar hover:underline">Simula pagamento completato</button>
                            </form>
                        </div>
                        @if ($lead->emailConversations->isNotEmpty())
                            <form method="POST" action="{{ route('admin.training.leads.reply', $lead) }}" class="flex gap-6">
                                @csrf
                                <input type="hidden" name="channel" value="email">
                                <select name="reply" class="rounded-10 border-gray-mid px-10 py-8 text-12 font-bold focus:border-bullstar focus:ring-bullstar">
                                    <option value="interested">Cliente interessato</option>
                                    <option value="quote_change">Modifica proposta</option>
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
