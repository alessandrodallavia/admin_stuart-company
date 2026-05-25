@extends('admin.layouts.app')

@section('title', 'Leads - Stuart Admin')
@section('page_title', 'Leads')
@section('active_nav', 'leads')

@section('content')
            <div class="mb-16 flex flex-wrap items-center justify-end gap-8">
                <a href="{{ route('admin.leads.index') }}" class="rounded-10 border border-bullstar bg-bullstar px-12 py-10 text-12 font-extrabold uppercase tracking-normal text-white">
                    Tabella
                </a>
                <a href="{{ route('admin.leads.board') }}" class="rounded-10 border border-gray-mid bg-white px-12 py-10 text-12 font-extrabold uppercase tracking-normal text-black-nike transition hover:border-black-nike">
                    Riepilogo
                </a>
            </div>

            <section class="mb-16 grid gap-12 md:grid-cols-4">
                <article class="rounded-10 border border-gray-mid bg-white p-16">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Lead totali</p>
                    <p class="mt-8 text-38 font-black leading-none tracking-normal">{{ $stats['total'] }}</p>
                </article>
                <article class="rounded-10 border border-gray-mid bg-white p-16">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Aperti</p>
                    <p class="mt-8 text-38 font-black leading-none tracking-normal">{{ $stats['open'] }}</p>
                </article>
                <article class="rounded-10 border border-gray-mid bg-white p-16">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Da lavorare</p>
                    <p class="mt-8 text-38 font-black leading-none tracking-normal">{{ $stats['ready'] }}</p>
                </article>
                <article class="rounded-10 border border-gray-mid bg-white p-16">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Pagati</p>
                    <p class="mt-8 text-38 font-black leading-none tracking-normal">{{ $stats['paid'] }}</p>
                </article>
            </section>

            <section class="mb-16 overflow-hidden rounded-10 border border-gray-mid bg-white">
                <form method="GET" action="{{ route('admin.leads.index') }}" class="grid gap-12 p-16 lg:grid-cols-[minmax(220px,1fr)_240px_auto] lg:items-end">
                    <label class="block">
                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Cerca</span>
                        <input name="q" value="{{ $search }}" type="search" placeholder="Nome, email, telefono, club o città" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    </label>
                    <label class="block">
                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Stato</span>
                        <select name="status" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                            <option value="">Tutti gli stati</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected($currentStatus === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="flex gap-8">
                        <button type="submit" class="rounded-10 bg-black-nike px-16 py-10 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-black">
                            Filtra
                        </button>
                        <a href="{{ route('admin.leads.index') }}" class="rounded-10 border border-gray-mid px-16 py-10 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">
                            Reset
                        </a>
                    </div>
                </form>
            </section>

            <section class="grid min-h-[660px] flex-1 gap-16 xl:grid-cols-[minmax(0,1fr)_420px]">
                <section class="overflow-hidden rounded-10 border border-gray-mid bg-white">
                    <div class="flex flex-col gap-12 border-b border-gray-mid px-16 py-12 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Pipeline</p>
                            <p class="mt-4 text-14 font-bold text-black-nike">{{ $leads->total() }} lead trovati</p>
                        </div>
                        <div class="flex flex-wrap gap-6">
                            @foreach ($statuses as $value => $label)
                                <a href="{{ route('admin.leads.index', ['status' => $value]) }}" class="rounded-full border px-10 py-6 text-11 font-extrabold uppercase tracking-normal transition {{ $currentStatus === $value ? 'border-bullstar bg-bullstar text-white' : 'border-gray-mid text-gray hover:border-black-nike hover:text-black-nike' }}">
                                    {{ $label }} {{ $stats['by_status'][$value] ?? 0 }}
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-[920px] w-full text-left">
                            <thead class="border-b border-gray-mid bg-gray-light text-11 font-extrabold uppercase tracking-normal text-gray">
                                <tr>
                                    <th class="px-12 py-12">Lead</th>
                                    <th class="px-12 py-12">Stato</th>
                                    <th class="px-12 py-12">Valore prossimo</th>
                                    <th class="px-12 py-12">Tracking</th>
                                    <th class="px-12 py-12">Ingresso</th>
                                    <th class="px-12 py-12"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-mid">
                                @forelse ($leads as $lead)
                                    @php
                                        $isSelected = ($selectedLead?->id ?? null) === $lead->id;
                                        $statusLabel = $statuses[$lead->status] ?? ucfirst((string) $lead->status ?: 'Senza stato');
                                    @endphp
                                    <tr class="{{ $isSelected ? 'bg-bullstar/5' : 'bg-white' }}">
                                        <td class="px-12 py-12">
                                            <p class="max-w-[240px] truncate text-14 font-black leading-tight">{{ $lead->name ?: 'Lead senza nome' }}</p>
                                            <p class="mt-4 max-w-[240px] truncate text-12 font-semibold text-gray">{{ $lead->club ?: $lead->city ?: 'Nessuna organizzazione' }}</p>
                                            <p class="mt-4 max-w-[240px] truncate text-11 font-bold text-gray">{{ $lead->email ?: $lead->phone ?: 'Contatto incompleto' }}</p>
                                        </td>
                                        <td class="px-12 py-12">
                                            <span class="inline-flex rounded-full bg-black-nike px-10 py-6 text-11 font-extrabold uppercase tracking-normal text-white">{{ $statusLabel }}</span>
                                        </td>
                                        <td class="px-12 py-12">
                                            <p class="text-12 font-bold text-black-nike">
                                                {{ match ($lead->status) {
                                                    'pre' => 'Attesa messaggio WhatsApp',
                                                    'confirmed' => 'Risposta automatica',
                                                    'completed' => 'Invio preventivo',
                                                    'quote_sent' => 'Invio link pagamento',
                                                    'link_sent' => 'Pagamento cliente',
                                                    'order_completed' => 'Concluso',
                                                    default => 'Verifica lead',
                                                } }}
                                            </p>
                                            <p class="mt-4 text-11 font-semibold text-gray">
                                                @if ($lead->quote_amount || $lead->payment_amount)
                                                    Preventivo {{ $lead->quote_amount ? '€ ' . number_format((float) $lead->quote_amount, 2, ',', '.') : '-' }} / Link {{ $lead->payment_amount ? '€ ' . number_format((float) $lead->payment_amount, 2, ',', '.') : '-' }}
                                                @else
                                                    Automazione pronta da collegare
                                                @endif
                                            </p>
                                        </td>
                                        <td class="px-12 py-12">
                                            <div class="flex flex-wrap gap-5">
                                                @if ($lead->gclid)
                                                    <span class="rounded-full bg-bullstar/10 px-8 py-5 text-11 font-extrabold uppercase tracking-normal text-bullstar">GCLID</span>
                                                @endif
                                                @if ($lead->fbclid)
                                                    <span class="rounded-full bg-black-nike/10 px-8 py-5 text-11 font-extrabold uppercase tracking-normal text-black-nike">FBCLID</span>
                                                @endif
                                                @if ($lead->utm_source)
                                                    <span class="rounded-full bg-gray-light px-8 py-5 text-11 font-extrabold uppercase tracking-normal text-gray">{{ $lead->utm_source }}</span>
                                                @endif
                                                @unless ($lead->gclid || $lead->fbclid || $lead->utm_source)
                                                    <span class="text-12 font-semibold text-gray">Organico o non tracciato</span>
                                                @endunless
                                            </div>
                                        </td>
                                        <td class="px-12 py-12">
                                            <p class="text-12 font-bold text-black-nike">{{ optional($lead->created_at)->format('d/m/Y H:i') }}</p>
                                            <p class="mt-4 max-w-[170px] truncate text-11 font-semibold text-gray">{{ $lead->landing_page ?: $lead->entry_page ?: 'Pagina non salvata' }}</p>
                                        </td>
                                        <td class="px-12 py-12 text-right">
                                            <a href="{{ route('admin.leads.index', ['lead' => $lead, 'status' => $currentStatus ?: null, 'q' => $search ?: null]) }}" class="rounded-10 border border-gray-mid px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-bullstar hover:text-bullstar">
                                                Apri
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-16 py-28">
                                            <div class="rounded-10 border border-dashed border-gray-mid bg-gray-light p-20 text-14 font-semibold text-gray">
                                                Nessun lead con questi filtri.
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-gray-mid px-16 py-12">
                        {{ $leads->links() }}
                    </div>
                </section>

                <aside class="overflow-hidden rounded-10 border border-gray-mid bg-white">
                    @if ($selectedLead)
                        @php
                            $statusLabel = $statuses[$selectedLead->status] ?? ucfirst((string) $selectedLead->status ?: 'Senza stato');
                        @endphp
                        <div class="border-b border-gray-mid px-16 py-16">
                            <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Scheda lead</p>
                            <h2 class="mt-6 text-24 font-black leading-tight tracking-normal">{{ $selectedLead->name ?: 'Lead senza nome' }}</h2>
                            <p class="mt-6 text-14 font-semibold text-gray">{{ $selectedLead->club ?: $selectedLead->city ?: 'Organizzazione non indicata' }}</p>
                        </div>

                        <div class="space-y-16 p-16">
                            <section class="rounded-10 border border-gray-mid bg-gray-light p-12">
                                <div class="flex items-start justify-between gap-12">
                                    <div>
                                        <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Stato attuale</p>
                                        <p class="mt-6 text-18 font-black leading-tight">{{ $statusLabel }}</p>
                                    </div>
                                    <span class="rounded-full bg-black-nike/10 px-10 py-6 text-11 font-extrabold uppercase tracking-normal text-black-nike">
                                        Locale
                                    </span>
                                </div>
                                <form method="POST" action="{{ route('admin.leads.update', $selectedLead) }}" enctype="multipart/form-data" class="mt-12 space-y-10">
                                    @csrf
                                    @method('PATCH')

                                    <div class="grid gap-10 md:grid-cols-2">
                                        <label class="block">
                                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Nome</span>
                                            <input name="name" value="{{ old('name', $selectedLead->name) }}" type="text" maxlength="100" placeholder="Nome cliente" class="mt-6 w-full rounded-10 border-gray-mid bg-white px-12 py-10 text-14 font-semibold text-black-nike focus:border-bullstar focus:ring-bullstar">
                                        </label>
                                        <label class="block">
                                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Telefono</span>
                                            <input name="phone" value="{{ old('phone', $selectedLead->phone) }}" type="tel" maxlength="30" placeholder="+39..." class="mt-6 w-full rounded-10 border-gray-mid bg-white px-12 py-10 text-14 font-semibold text-black-nike focus:border-bullstar focus:ring-bullstar">
                                        </label>
                                    </div>

                                    <label class="block">
                                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Email</span>
                                        <input name="email" value="{{ old('email', $selectedLead->email) }}" type="email" maxlength="255" placeholder="cliente@email.it" class="mt-6 w-full rounded-10 border-gray-mid bg-white px-12 py-10 text-14 font-semibold text-black-nike focus:border-bullstar focus:ring-bullstar">
                                    </label>

                                    <label class="block">
                                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Nuovo stato</span>
                                        <select name="status" class="mt-6 w-full rounded-10 border-gray-mid bg-white px-12 py-10 text-14 font-semibold text-black-nike focus:border-bullstar focus:ring-bullstar">
                                            @foreach ($statuses as $value => $label)
                                                <option value="{{ $value }}" @selected(old('status', $selectedLead->status) === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>

                                    <label class="block">
                                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Numero preventivo</span>
                                        <input name="quote_number" value="{{ old('quote_number', $selectedLead->quote_number) }}" type="text" maxlength="50" placeholder="Generato alla creazione link Stripe" class="mt-6 w-full rounded-10 border-gray-mid bg-white px-12 py-10 text-14 font-semibold text-black-nike focus:border-bullstar focus:ring-bullstar">
                                    </label>

                                    <div class="grid gap-10 md:grid-cols-2">
                                        <label class="block">
                                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Importo preventivo</span>
                                            <input name="quote_amount" value="{{ old('quote_amount', $selectedLead->quote_amount) }}" type="number" min="0" step="0.01" placeholder="0,00" class="mt-6 w-full rounded-10 border-gray-mid bg-white px-12 py-10 text-14 font-semibold text-black-nike focus:border-bullstar focus:ring-bullstar">
                                        </label>
                                        <label class="block">
                                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Importo pagamento</span>
                                            <input name="payment_amount" value="{{ old('payment_amount', $selectedLead->payment_amount) }}" type="number" min="0" step="0.01" placeholder="0,00" class="mt-6 w-full rounded-10 border-gray-mid bg-white px-12 py-10 text-14 font-semibold text-black-nike focus:border-bullstar focus:ring-bullstar">
                                        </label>
                                    </div>

                                    <label class="block">
                                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Link pagamento</span>
                                        <input name="payment_link" value="{{ old('payment_link', $selectedLead->payment_link) }}" type="url" placeholder="https://..." class="mt-6 w-full rounded-10 border-gray-mid bg-white px-12 py-10 text-14 font-semibold text-black-nike focus:border-bullstar focus:ring-bullstar">
                                    </label>

                                    <label class="block">
                                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">PDF preventivo</span>
                                        <input name="quote_pdf" type="file" accept="application/pdf,.pdf" class="mt-6 w-full rounded-10 border border-gray-mid bg-white px-12 py-10 text-14 font-semibold text-black-nike file:mr-12 file:rounded-10 file:border-0 file:bg-black-nike file:px-12 file:py-8 file:text-12 file:font-extrabold file:uppercase file:tracking-normal file:text-white focus:border-bullstar focus:ring-bullstar">
                                        <span class="mt-6 block text-11 font-semibold text-gray">Carica un PDF fino a 20 MB. Se ne carichi uno nuovo, sostituisce quello precedente.</span>
                                    </label>

                                    <button type="submit" class="w-full rounded-10 bg-bullstar px-12 py-10 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover">
                                        Aggiorna lead
                                    </button>
                                </form>
                            </section>

                            <section class="grid gap-10 md:grid-cols-2">
                                <div class="rounded-10 border border-gray-mid p-12">
                                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Preventivo</p>
                                    <p class="mt-8 text-14 font-black">{{ $selectedLead->quote_number ?: 'Numero non assegnato' }}</p>
                                    <p class="mt-8 text-18 font-black">{{ $selectedLead->quote_amount ? '€ ' . number_format((float) $selectedLead->quote_amount, 2, ',', '.') : '-' }}</p>
                                    @if ($selectedLead->quote_pdf_path)
                                        <a href="{{ route('admin.leads.quote-pdf', $selectedLead) }}" target="_blank" class="mt-8 block truncate text-12 font-bold text-bullstar underline-offset-4 hover:underline">
                                            {{ $selectedLead->quote_pdf_filename ?: 'Apri PDF preventivo' }}
                                        </a>
                                        @if ($selectedLead->quote_pdf_uploaded_at)
                                            <p class="mt-4 text-11 font-semibold text-gray">Caricato il {{ $selectedLead->quote_pdf_uploaded_at->format('d/m/Y H:i') }}</p>
                                        @endif
                                    @else
                                        <p class="mt-8 text-12 font-semibold text-gray">PDF non caricato</p>
                                    @endif
                                </div>
                                <div class="rounded-10 border border-gray-mid p-12">
                                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Pagamento</p>
                                    <p class="mt-8 text-18 font-black">{{ $selectedLead->payment_amount ? '€ ' . number_format((float) $selectedLead->payment_amount, 2, ',', '.') : '-' }}</p>
                                    @if ($selectedLead->payment_link)
                                        <a href="{{ $selectedLead->payment_link }}" target="_blank" class="mt-8 block truncate text-12 font-bold text-bullstar underline-offset-4 hover:underline">Apri link</a>
                                    @endif
                                    <form method="POST" action="{{ route('admin.leads.stripe-payment-link', $selectedLead) }}" class="mt-10 space-y-8">
                                        @csrf
                                        <label class="block">
                                            <span class="text-11 font-extrabold uppercase tracking-normal text-gray">Importo Stripe</span>
                                            <input name="payment_amount" value="{{ old('payment_amount', $selectedLead->payment_amount ?: $selectedLead->quote_amount) }}" type="number" min="0.50" step="0.01" placeholder="0,00" class="mt-6 w-full rounded-10 border-gray-mid bg-white px-12 py-10 text-14 font-semibold text-black-nike focus:border-bullstar focus:ring-bullstar">
                                        </label>
                                        <button type="submit" class="w-full rounded-10 border border-black-nike bg-black-nike px-10 py-6 text-11 font-extrabold uppercase leading-none tracking-normal text-white transition hover:bg-black">
                                            Crea link Stripe
                                        </button>
                                    </form>
                                </div>
                            </section>

                            <section class="grid gap-10">
                                <div class="rounded-10 border border-gray-mid p-12">
                                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Contatti</p>
                                    <p class="mt-8 text-14 font-bold">{{ $selectedLead->email ?: 'Email mancante' }}</p>
                                    <p class="mt-4 text-14 font-bold">{{ $selectedLead->phone ?: 'Telefono mancante' }}</p>
                                </div>
                                <div class="rounded-10 border border-gray-mid p-12">
                                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Richiesta</p>
                                    <p class="mt-8 whitespace-pre-line text-14 font-semibold leading-[20px] text-black-nike">{{ $selectedLead->message ?: 'Nessun messaggio salvato.' }}</p>
                                </div>
                            </section>

                            <section class="rounded-10 border border-gray-mid p-12">
                                <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Automazioni previste</p>
                                <div class="mt-12 space-y-8">
                                    <div class="flex items-center justify-between gap-12 rounded-10 bg-gray-light px-12 py-10">
                                        <div>
                                            <p class="text-14 font-black">Invia preventivo</p>
                                            <p class="mt-4 text-11 font-semibold text-gray">Salva importo + evento Google generate_quote</p>
                                        </div>
                                        <button disabled class="rounded-10 border border-gray-mid px-10 py-6 text-11 font-extrabold uppercase tracking-normal text-gray">Presto</button>
                                    </div>
                                    <div class="flex items-center justify-between gap-12 rounded-10 bg-gray-light px-12 py-10">
                                        <div>
                                            <p class="text-14 font-black">Link pagamento</p>
                                            <p class="mt-4 text-11 font-semibold text-gray">Salva link e importo sul lead</p>
                                        </div>
                                        <button disabled class="rounded-10 border border-gray-mid px-10 py-6 text-11 font-extrabold uppercase tracking-normal text-gray">Presto</button>
                                    </div>
                                    <div class="flex items-center justify-between gap-12 rounded-10 bg-gray-light px-12 py-10">
                                        <div>
                                            <p class="text-14 font-black">Ordine completato</p>
                                            <p class="mt-4 text-11 font-semibold text-gray">Pagamento ricevuto + evento Google purchase</p>
                                        </div>
                                        <button disabled class="rounded-10 border border-gray-mid px-10 py-6 text-11 font-extrabold uppercase tracking-normal text-gray">Presto</button>
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-10 border border-gray-mid p-12">
                                <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Tracking</p>
                                <dl class="mt-10 grid grid-cols-2 gap-10 text-12">
                                    <div>
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">UTM source</dt>
                                        <dd class="mt-4 truncate font-bold">{{ $selectedLead->utm_source ?: '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">Campaign</dt>
                                        <dd class="mt-4 truncate font-bold">{{ $selectedLead->utm_campaign ?: '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">GCLID</dt>
                                        <dd class="mt-4 truncate font-bold">{{ $selectedLead->gclid ?: '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">FBCLID</dt>
                                        <dd class="mt-4 truncate font-bold">{{ $selectedLead->fbclid ?: '-' }}</dd>
                                    </div>
                                </dl>
                            </section>

                            @if ($selectedConversation)
                                <a href="{{ route('admin.conversations.show', $selectedConversation) }}" target="_blank" class="block rounded-10 border border-whatsapp bg-whatsapp px-12 py-12 text-center text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-whatsapp/90">
                                    Apri chat WhatsApp
                                </a>
                            @else
                                <div class="rounded-10 border border-dashed border-gray-mid bg-gray-light px-12 py-12 text-14 font-semibold text-gray">
                                    Nessuna chat WhatsApp collegata a questo lead.
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="flex min-h-[660px] items-center justify-center p-24 text-center">
                            <div>
                                <p class="text-24 font-black leading-tight">Nessun lead selezionato</p>
                                <p class="mt-8 text-14 font-semibold text-gray">Quando arrivano richieste, qui vedrai stato, tracking e prossime azioni.</p>
                            </div>
                        </div>
                    @endif
                </aside>
            </section>
@endsection
