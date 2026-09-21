@extends('admin.layouts.app')

@section('title', 'Leads - Stuart Admin')
@section('page_title', 'Leads')
@section('active_nav', 'leads')

@section('content')
            @if (! $selectedLead)
            <div class="mb-12 flex flex-col gap-10 rounded-10 bg-black-nike px-16 py-14 text-white md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-10 font-extrabold uppercase tracking-wider text-white/60">CRM commerciale</p>
                    <h1 class="mt-4 text-24 font-black">Lista lead</h1>
                    <p class="mt-4 text-11 font-semibold text-white/60">Cerca, filtra e apri rapidamente le opportunità da lavorare.</p>
                </div>
                <div class="flex flex-wrap gap-8">
                <a href="{{ route('admin.leads.index') }}" class="rounded-10 border border-bullstar bg-bullstar px-12 py-10 text-12 font-extrabold uppercase tracking-normal text-white">
                    Tabella
                </a>
                <a href="{{ route('admin.leads.board') }}" class="rounded-10 border border-white/20 bg-white/10 px-12 py-10 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-white/20">
                    Riepilogo
                </a>
                </div>
            </div>

            @if (Auth::guard('admin')->user()?->hasAdminPermission('leads.manage'))
                <details class="group mb-12 overflow-hidden rounded-10 border border-gray-mid bg-white" @if($errors->hasAny(['name', 'phone', 'email', 'acquisition_channel', 'attribution_confidence', 'duplicate'])) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-12 bg-gray-light px-16 py-12 transition hover:bg-white">
                        <div>
                            <p class="text-12 font-extrabold uppercase tracking-normal text-black-nike">Nuovo lead manuale</p>
                            <p class="mt-4 text-11 font-semibold text-gray">Registra chiamate, contatti diretti, referral o clienti esistenti.</p>
                        </div>
                        <span class="rounded-10 bg-black-nike px-12 py-8 text-11 font-extrabold uppercase tracking-normal text-white group-open:hidden">Aggiungi</span>
                        <span class="hidden rounded-10 border border-gray-mid bg-white px-12 py-8 text-11 font-extrabold uppercase tracking-normal group-open:inline-flex">Chiudi</span>
                    </summary>

                    <form method="POST" action="{{ route('admin.leads.store') }}" class="border-t border-gray-mid p-16">
                        @csrf

                        @error('duplicate')
                            <div class="mb-12 rounded-10 border border-red-200 bg-red-50 px-12 py-10 text-12 font-bold text-red-700">{{ $message }}</div>
                        @enderror

                        <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-4">
                            <label class="block lg:col-span-2">
                                <span class="text-11 font-extrabold uppercase tracking-normal text-gray">Nome e cognome *</span>
                                <input name="name" value="{{ old('name') }}" required class="mt-5 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                                @error('name')<span class="mt-4 block text-11 font-bold text-red-700">{{ $message }}</span>@enderror
                            </label>
                            <label class="block lg:col-span-2">
                                <span class="text-11 font-extrabold uppercase tracking-normal text-gray">Azienda</span>
                                <input name="club" value="{{ old('club') }}" class="mt-5 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                            </label>
                            <label class="block">
                                <span class="text-11 font-extrabold uppercase tracking-normal text-gray">Telefono</span>
                                <input name="phone" value="{{ old('phone') }}" inputmode="tel" class="mt-5 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                                @error('phone')<span class="mt-4 block text-11 font-bold text-red-700">{{ $message }}</span>@enderror
                            </label>
                            <label class="block">
                                <span class="text-11 font-extrabold uppercase tracking-normal text-gray">E-mail</span>
                                <input name="email" value="{{ old('email') }}" type="email" class="mt-5 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                                @error('email')<span class="mt-4 block text-11 font-bold text-red-700">{{ $message }}</span>@enderror
                            </label>
                            <label class="block">
                                <span class="text-11 font-extrabold uppercase tracking-normal text-gray">Città</span>
                                <input name="city" value="{{ old('city') }}" class="mt-5 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                            </label>
                            <label class="block">
                                <span class="text-11 font-extrabold uppercase tracking-normal text-gray">Categoria</span>
                                <select name="lead_category_id" class="mt-5 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                                    <option value="">Non indicata</option>
                                    @foreach ($leadCategories as $category)
                                        <option value="{{ $category->id }}" @selected((string) old('lead_category_id') === (string) $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-11 font-extrabold uppercase tracking-normal text-gray">Canale *</span>
                                <select name="acquisition_channel" required class="mt-5 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                                    @foreach ($manualChannels as $value => $label)
                                        <option value="{{ $value }}" @selected(old('acquisition_channel', 'telefono') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-11 font-extrabold uppercase tracking-normal text-gray">Affidabilità origine *</span>
                                <select name="attribution_confidence" required class="mt-5 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                                    @foreach ($attributionConfidences as $value => $label)
                                        <option value="{{ $value }}" @selected(old('attribution_confidence', 'unknown') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block lg:col-span-2">
                                <span class="text-11 font-extrabold uppercase tracking-normal text-gray">Prodotto richiesto</span>
                                <input name="product" value="{{ old('product') }}" list="manual-lead-products" class="mt-5 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                                <datalist id="manual-lead-products">
                                    @foreach ($crmProducts as $product)<option value="{{ $product->name }}"></option>@endforeach
                                </datalist>
                            </label>
                            <label class="block">
                                <span class="text-11 font-extrabold uppercase tracking-normal text-gray">Quantità</span>
                                <input name="quantity" value="{{ old('quantity') }}" type="number" min="0.01" step="0.01" class="mt-5 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                            </label>
                            <label class="block">
                                <span class="text-11 font-extrabold uppercase tracking-normal text-gray">Nota attribuzione</span>
                                <input name="attribution_note" value="{{ old('attribution_note') }}" placeholder="Es. dichiara di averci trovato su Google" class="mt-5 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                            </label>
                            <label class="block md:col-span-2 lg:col-span-4">
                                <span class="text-11 font-extrabold uppercase tracking-normal text-gray">Note commerciali</span>
                                <textarea name="crm_notes" rows="3" class="mt-5 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">{{ old('crm_notes') }}</textarea>
                            </label>
                        </div>

                        <div class="mt-12 flex flex-col gap-10 border-t border-gray-mid pt-12 md:flex-row md:items-center md:justify-between">
                            <label class="flex items-start gap-8 text-11 font-semibold text-gray">
                                <input type="checkbox" name="confirm_duplicate" value="1" @checked(old('confirm_duplicate')) class="mt-2 h-16 w-16 rounded border-gray-mid text-bullstar focus:ring-bullstar">
                                <span>Crea comunque se telefono o e-mail risultano già presenti.</span>
                            </label>
                            <button type="submit" class="rounded-10 bg-bullstar px-16 py-10 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover">Crea lead e apri scheda</button>
                        </div>
                    </form>
                </details>
            @endif

            <section class="mb-12 grid grid-cols-2 gap-8 lg:grid-cols-4">
                <article class="rounded-10 border border-gray-mid bg-white p-12">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Lead totali</p>
                    <p class="mt-8 text-38 font-black leading-none tracking-normal">{{ $stats['total'] }}</p>
                </article>
                <article class="rounded-10 border border-gray-mid bg-white p-12">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Aperti</p>
                    <p class="mt-8 text-38 font-black leading-none tracking-normal">{{ $stats['open'] }}</p>
                </article>
                <article class="rounded-10 border border-gray-mid bg-white p-12">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Da lavorare</p>
                    <p class="mt-8 text-38 font-black leading-none tracking-normal">{{ $stats['ready'] }}</p>
                </article>
                <article class="rounded-10 border border-gray-mid bg-white p-12">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Pagati</p>
                    <p class="mt-8 text-38 font-black leading-none tracking-normal">{{ $stats['paid'] }}</p>
                </article>
            </section>

            <section class="mb-12 overflow-hidden rounded-10 border border-gray-mid bg-gray-light">
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
            @endif

            <section class="min-h-[660px] flex-1">
                <section class="{{ $selectedLead ? 'hidden' : '' }} overflow-hidden rounded-10 border border-gray-mid bg-white">
                    <div class="flex flex-col gap-12 border-b border-gray-mid bg-gray-light px-16 py-12 md:flex-row md:items-center md:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-6">
                                <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Pipeline</p>
                                @if($excludePreLeads)<span class="rounded-full bg-white px-8 py-4 text-10 font-extrabold text-gray">Pre-lead esclusi</span>@endif
                            </div>
                            <p class="mt-4 text-14 font-bold text-black-nike">{{ $leads->total() }} lead trovati</p>
                        </div>
                        <div class="flex flex-wrap gap-5">
                            <a href="{{ route('admin.leads.export', ['q' => $search ?: null, 'status' => $currentStatus ?: null]) }}" class="rounded-10 border border-gray-mid bg-white px-10 py-7 text-10 font-extrabold uppercase text-black-nike transition hover:border-bullstar hover:text-bullstar">Esporta risultati CSV</a>
                            <a href="{{ route('admin.leads.export', ['scope' => 'all']) }}" class="rounded-10 bg-black-nike px-10 py-7 text-10 font-extrabold uppercase text-white transition hover:bg-bullstar">Esporta tutti</a>
                        </div>
                        <div class="flex flex-wrap gap-6">
                            @foreach ($statuses as $value => $label)
                                <a href="{{ route('admin.leads.index', ['status' => $value]) }}" class="rounded-full border px-10 py-6 text-11 font-extrabold uppercase tracking-normal transition {{ $currentStatus === $value ? 'border-bullstar bg-bullstar text-white' : 'border-gray-mid text-gray hover:border-black-nike hover:text-black-nike' }}">
                                    {{ $label }} {{ $stats['by_status'][$value] ?? 0 }}
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid gap-8 bg-gray-light p-8 lg:hidden">
                        @forelse($leads as $lead)
                            @php
                                $mobileStatusLabel = $statuses[$lead->status] ?? ucfirst((string) $lead->status ?: 'Senza stato');
                            @endphp
                            <a href="{{ route('admin.leads.index', ['lead' => $lead, 'status' => $currentStatus ?: null, 'q' => $search ?: null]) }}" class="rounded-10 border border-gray-mid bg-white p-10 transition hover:border-bullstar">
                                <div class="flex items-start justify-between gap-8">
                                    <div class="min-w-0">
                                        <p class="truncate text-14 font-black">{{ $lead->name ?: 'Lead senza nome' }}</p>
                                        <p class="mt-3 truncate text-10 font-semibold text-gray">{{ $lead->club ?: $lead->city ?: 'Nessuna organizzazione' }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-gray-light px-8 py-4 text-10 font-extrabold uppercase text-gray">{{ $mobileStatusLabel }}</span>
                                </div>
                                <div class="mt-8 grid grid-cols-2 gap-6 border-t border-gray-mid pt-6 text-10">
                                    <div><p class="font-extrabold uppercase text-gray">Contatto</p><p class="mt-3 truncate font-bold">{{ $lead->email ?: $lead->phone ?: 'Incompleto' }}</p></div>
                                    <div class="text-right"><p class="font-extrabold uppercase text-gray">Ingresso</p><p class="mt-3 font-bold">{{ optional($lead->created_at)?->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</p></div>
                                </div>
                            </a>
                        @empty
                            <div class="rounded-10 border border-dashed border-gray-mid bg-white p-20 text-center text-12 font-semibold text-gray">Nessun lead con questi filtri.</div>
                        @endforelse
                    </div>

                    <div class="hidden overflow-x-auto lg:block">
                        <table class="min-w-[920px] w-full text-left">
                            <thead class="border-b border-gray-mid bg-black-nike text-11 font-extrabold uppercase tracking-normal text-white/70">
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
                                        $statusBadgeClass = match ($lead->status) {
                                            'pre' => 'bg-gray-light text-gray',
                                            'confirmed' => 'bg-blue-50 text-blue-700',
                                            'completed' => 'bg-amber-50 text-amber-700',
                                            'quote_sent' => 'bg-bullstar/10 text-bullstar',
                                            'link_sent' => 'bg-indigo-50 text-indigo-700',
                                            'proforma_pending' => 'bg-orange-50 text-orange-700',
                                            'payment_pending' => 'bg-yellow-50 text-yellow-700',
                                            'order_completed' => 'bg-whatsapp/10 text-whatsapp',
                                            'lost' => 'bg-red-50 text-red-700',
                                            default => 'bg-black-nike text-white',
                                        };
                                    @endphp
                                    <tr class="transition hover:bg-gray-light {{ $isSelected ? 'bg-bullstar/5' : 'bg-white' }}">
                                        <td class="px-12 py-12">
                                            <p class="max-w-[240px] truncate text-14 font-black leading-tight">{{ $lead->name ?: 'Lead senza nome' }}</p>
                                            <p class="mt-4 max-w-[240px] truncate text-12 font-semibold text-gray">{{ $lead->club ?: $lead->city ?: 'Nessuna organizzazione' }}</p>
                                            <p class="mt-4 max-w-[240px] truncate text-11 font-bold text-gray">{{ $lead->email ?: $lead->phone ?: 'Contatto incompleto' }}</p>
                                        </td>
                                        <td class="px-12 py-12">
                                            <span class="inline-flex rounded-full px-10 py-6 text-11 font-extrabold uppercase tracking-normal {{ $statusBadgeClass }}">{{ $statusLabel }}</span>
                                        </td>
                                        <td class="px-12 py-12">
                                            <p class="text-12 font-bold text-black-nike">
                                                {{ match ($lead->status) {
                                                    'pre' => 'Attesa messaggio WhatsApp',
                                                    'confirmed' => 'Risposta automatica',
                                                    'completed' => 'Invio proposta',
                                                    'quote_sent' => 'Invio link pagamento',
                                                    'link_sent' => 'Pagamento cliente',
                                                    'proforma_pending' => 'Invio proforma bonifico',
                                                    'payment_pending' => 'In attesa conferma fondi',
                                                    'order_completed' => 'Concluso',
                                                    'lost' => 'Non concluso',
                                                    default => 'Verifica lead',
                                                } }}
                                            </p>
                                            <p class="mt-4 text-11 font-semibold text-gray">
                                                @if ($lead->quote_amount || $lead->payment_amount)
                                                    Proposta {{ $lead->quote_amount ? '€ ' . number_format((float) $lead->quote_amount, 2, ',', '.') : '-' }} / Link {{ $lead->payment_amount ? '€ ' . number_format((float) $lead->payment_amount, 2, ',', '.') : '-' }}
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
                                            <p class="text-12 font-bold text-black-nike">{{ optional($lead->created_at)?->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</p>
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

                <main
                    class="{{ $selectedLead ? '' : 'hidden' }} overflow-hidden rounded-10 border border-gray-mid bg-white"
                    x-data="{ tab: ['proposal', 'product', 'origin'].includes(window.location.hash.slice(1)) ? window.location.hash.slice(1) : 'main', selectTab(value) { this.tab = value; window.history.replaceState(null, '', '#'+value) } }"
                >
                    @if ($selectedLead)
                        @php
                            $statusLabel = $statuses[$selectedLead->status] ?? ucfirst((string) $selectedLead->status ?: 'Senza stato');
                            $statusBadgeClass = match ($selectedLead->status) {
                                'pre' => 'bg-gray-light text-gray',
                                'confirmed' => 'bg-blue-50 text-blue-700',
                                'completed' => 'bg-amber-50 text-amber-700',
                                'quote_sent' => 'bg-bullstar/10 text-bullstar',
                                'link_sent' => 'bg-indigo-50 text-indigo-700',
                                'proforma_pending' => 'bg-orange-50 text-orange-700',
                                'payment_pending' => 'bg-yellow-50 text-yellow-700',
                                'order_completed' => 'bg-whatsapp/10 text-whatsapp',
                                'lost' => 'bg-red-50 text-red-700',
                                default => 'bg-black-nike text-white',
                            };
                        @endphp
                        <div class="flex flex-col gap-10 border-b border-gray-mid px-16 py-14 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Scheda lead</p>
                                <h2 class="mt-5 text-24 font-black leading-tight tracking-normal">{{ $selectedLead->name ?: 'Lead senza nome' }}</h2>
                                <p class="mt-5 text-14 font-semibold text-gray">{{ $selectedLead->club ?: $selectedLead->city ?: 'Organizzazione non indicata' }}</p>
                            </div>
                            <a href="{{ route('admin.leads.index', ['status' => $currentStatus ?: null, 'q' => $search ?: null]) }}" class="inline-flex h-32 w-fit items-center rounded-10 border border-gray-mid px-12 text-11 font-extrabold uppercase tracking-normal transition hover:border-bullstar hover:text-bullstar">← Torna ai lead</a>
                        </div>

                        <nav class="flex gap-4 overflow-x-auto border-b border-gray-mid bg-gray-light px-16 pt-10" aria-label="Sezioni del lead">
                            <button type="button" @click="selectTab('main')" :class="tab === 'main' ? 'border-black-nike bg-white text-black-nike' : 'border-transparent text-gray hover:text-black-nike'" class="h-36 shrink-0 rounded-t-10 border border-b-0 px-12 text-11 font-extrabold uppercase tracking-normal transition">Principale</button>
                            <button type="button" @click="selectTab('proposal')" :class="tab === 'proposal' ? 'border-black-nike bg-white text-black-nike' : 'border-transparent text-gray hover:text-black-nike'" class="h-36 shrink-0 rounded-t-10 border border-b-0 px-12 text-11 font-extrabold uppercase tracking-normal transition">Proposta</button>
                            <button type="button" @click="selectTab('product')" :class="tab === 'product' ? 'border-black-nike bg-white text-black-nike' : 'border-transparent text-gray hover:text-black-nike'" class="h-36 shrink-0 rounded-t-10 border border-b-0 px-12 text-11 font-extrabold uppercase tracking-normal transition">Ordini e prodotti</button>
                            <button type="button" @click="selectTab('origin')" :class="tab === 'origin' ? 'border-black-nike bg-white text-black-nike' : 'border-transparent text-gray hover:text-black-nike'" class="h-36 shrink-0 rounded-t-10 border border-b-0 px-12 text-11 font-extrabold uppercase tracking-normal transition">Origine lead</button>
                        </nav>

                        <div class="grid gap-16 p-16 lg:grid-cols-2">
                            <section x-show="tab === 'main'" x-cloak class="order-1 overflow-hidden rounded-10 border border-gray-mid bg-white shadow-sm lg:col-start-1 lg:row-span-2 lg:row-start-1">
                                <div class="flex items-start justify-between gap-12 bg-black-nike px-12 py-10 text-white">
                                    <div>
                                        <p class="text-10 font-extrabold uppercase tracking-wider text-white/60">Scheda principale</p>
                                        <p class="mt-4 text-18 font-black leading-tight">Dati e avanzamento lead</p>
                                    </div>
                                    <span class="rounded-full px-10 py-6 text-11 font-extrabold uppercase tracking-normal {{ $statusBadgeClass }}">
                                        {{ $statusLabel }}
                                    </span>
                                </div>
                                @php
                                    $storedLossReason = $selectedLead->loss_reason;
                                    $selectedLossReason = array_key_exists((string) $storedLossReason, $lossReasons) ? $storedLossReason : ($storedLossReason ? 'other' : '');
                                    $otherLossReason = $selectedLossReason === 'other' ? $storedLossReason : '';
                                @endphp
                                <form method="POST" action="{{ route('admin.leads.update', $selectedLead) }}" enctype="multipart/form-data" class="space-y-10 p-12" x-data="{ leadStatus: @js(old('status', $selectedLead->status)), lossReason: @js(old('loss_reason', $selectedLossReason)) }">
                                    @csrf
                                    @method('PATCH')

                                    <div>
                                        <p class="text-11 font-black uppercase">Anagrafica e contatti</p>
                                        <p class="mt-3 text-10 font-semibold text-gray">Informazioni principali del referente.</p>
                                    </div>

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

                                    <div class="grid gap-10 md:grid-cols-2">
                                        <label class="block">
                                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Email</span>
                                            <input name="email" value="{{ old('email', $selectedLead->email) }}" type="email" maxlength="255" placeholder="cliente@email.it" class="mt-6 w-full rounded-10 border-gray-mid bg-white px-12 py-10 text-14 font-semibold text-black-nike focus:border-bullstar focus:ring-bullstar">
                                        </label>

                                        <label class="block">
                                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Nuovo stato</span>
                                            <select name="status" x-model="leadStatus" class="mt-6 w-full rounded-10 border-gray-mid bg-white px-12 py-10 text-14 font-semibold text-black-nike focus:border-bullstar focus:ring-bullstar">
                                                @foreach ($statuses as $value => $label)
                                                    <option value="{{ $value }}" @selected(old('status', $selectedLead->status) === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                    </div>

                                    <div class="grid gap-10 rounded-10 border border-gray-mid bg-gray-light p-10 md:grid-cols-2">
                                        <label class="block">
                                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Importo pagamento</span>
                                            <input name="payment_amount" value="{{ old('payment_amount', $selectedLead->payment_amount) }}" type="number" min="0" step="0.01" placeholder="0,00" class="mt-6 w-full rounded-10 border-gray-mid bg-white px-12 py-10 text-14 font-semibold text-black-nike focus:border-bullstar focus:ring-bullstar">
                                        </label>

                                        <label class="block">
                                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Link pagamento</span>
                                            <input name="payment_link" value="{{ old('payment_link', $selectedLead->payment_link) }}" type="url" placeholder="https://..." class="mt-6 w-full rounded-10 border-gray-mid bg-white px-12 py-10 text-14 font-semibold text-black-nike focus:border-bullstar focus:ring-bullstar">
                                        </label>
                                    </div>

                                    <div class="border-t border-gray-mid pt-10">
                                        <p class="text-11 font-black uppercase">Qualificazione CRM</p>
                                        <p class="mt-3 text-10 font-semibold text-gray">Classificazione commerciale e note interne.</p>
                                    </div>

                                    <div class="grid gap-10 md:grid-cols-2">
                                        <label class="block">
                                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Categoria</span>
                                            <select name="lead_category_id" class="mt-6 w-full rounded-10 border-gray-mid bg-white px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                                                <option value="">Non indicata</option>
                                                @foreach ($leadCategories as $category)
                                                    <option value="{{ $category->id }}" @selected((string) old('lead_category_id', $selectedLead->lead_category_id) === (string) $category->id)>{{ $category->name }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="block">
                                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Qualità lead</span>
                                            <select name="lead_quality" class="mt-6 w-full rounded-10 border-gray-mid bg-white px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                                                <option value="">Non indicata</option>
                                                @foreach (['Bassa', 'Media', 'Alta'] as $quality)
                                                    <option value="{{ $quality }}" @selected(old('lead_quality', $selectedLead->lead_quality) === $quality)>{{ $quality }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label x-show="leadStatus === 'lost'" x-cloak class="block">
                                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Motivo perdita</span>
                                            <select name="loss_reason" x-model="lossReason" :required="leadStatus === 'lost'" class="mt-6 w-full rounded-10 border-gray-mid bg-white px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                                                <option value="">Seleziona il motivo</option>
                                                @foreach($lossReasons as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            @error('loss_reason')<span class="mt-4 block text-11 font-bold text-red-700">{{ $message }}</span>@enderror
                                        </label>
                                        <label x-show="leadStatus === 'lost' && lossReason === 'other'" x-cloak class="block">
                                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Specifica motivo *</span>
                                            <input name="loss_reason_other" value="{{ old('loss_reason_other', $otherLossReason) }}" :required="leadStatus === 'lost' && lossReason === 'other'" type="text" maxlength="255" class="mt-6 w-full rounded-10 border-gray-mid bg-white px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                                            @error('loss_reason_other')<span class="mt-4 block text-11 font-bold text-red-700">{{ $message }}</span>@enderror
                                        </label>
                                    </div>

                                    <label class="block">
                                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Note CRM</span>
                                        <textarea name="crm_notes" rows="3" maxlength="5000" class="mt-6 w-full rounded-10 border-gray-mid bg-white px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">{{ old('crm_notes', $selectedLead->crm_notes) }}</textarea>
                                    </label>

                                    <button type="submit" class="ml-auto block w-full rounded-10 bg-bullstar px-16 py-12 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover md:w-auto">
                                        Aggiorna lead
                                    </button>
                                </form>
                            </section>

                            @php
                                    $personalizationLabels = [
                                        'heart_logo' => 'Logo lato cuore',
                                        'front_print' => 'Stampa frontale',
                                        'big_front_print' => 'Stampa grande frontale',
                                        'heart_logo_back_print' => 'Logo lato cuore + stampa retro',
                                        'front_print_back_print' => 'Stampa frontale + stampa retro',
                                        'big_front_print_big_back_print' => 'Stampa grande frontale + stampa grande retro',
                                    ];
                                    $mockupPersonalization = $personalizationLabels[$selectedLead->calculator_personalization] ?? $selectedLead->calculator_personalization;
                                    $hasLiveMockupGraphics = filled($selectedLead->live_mockup_front_file) || filled($selectedLead->live_mockup_back_file);
                                    $mockupModelKey = Illuminate\Support\Str::slug((string) $selectedLead->calculator_model);
                                    $mockupColorKey = Illuminate\Support\Str::slug((string) $selectedLead->live_mockup_color);
                                    $hasMockupBase = filled($mockupModelKey) && filled($mockupColorKey);
                                    $isHeartLogo = str_starts_with((string) $selectedLead->calculator_personalization, 'heart_logo');
                                    $isLargePrint = in_array($selectedLead->calculator_personalization, ['big_front_print', 'big_front_print_big_back_print'], true);
                                    $artworkPositions = [
                                        'front' => $isHeartLogo
                                            ? ['top' => '22%', 'left' => '62%', 'width' => '16%', 'height' => '16%']
                                            : ($isLargePrint
                                                ? ['top' => '20%', 'left' => '50%', 'width' => '40%', 'height' => '44%']
                                                : ['top' => '25%', 'left' => '50%', 'width' => '40%', 'height' => '24%']),
                                        'back' => $isLargePrint
                                            ? ['top' => '18%', 'left' => '50%', 'width' => '40%', 'height' => '44%']
                                            : ['top' => '22%', 'left' => '50%', 'width' => '40%', 'height' => '24%'],
                                    ];
                            @endphp
                                <section x-show="tab === 'main'" class="order-5 overflow-hidden rounded-10 border border-gray-mid bg-white shadow-sm lg:col-start-2 lg:row-start-3">
                                    <div class="flex items-center justify-between gap-10 bg-black-nike px-12 py-10 text-white">
                                        <div>
                                            <p class="text-10 font-extrabold uppercase tracking-wider text-white/60">Anteprima live</p>
                                            <h3 class="mt-4 text-18 font-black">Grafiche mockup</h3>
                                        </div>
                                        <span class="rounded-full bg-white/10 px-10 py-6 text-10 font-extrabold uppercase">{{ $hasLiveMockupGraphics ? 'Caricate dal cliente' : 'Nessuna grafica' }}</span>
                                    </div>

                                    <div class="space-y-12 p-12">
                                        <dl class="grid grid-cols-2 gap-x-10 gap-y-8 rounded-10 border border-gray-mid bg-gray-light p-10 text-11 sm:grid-cols-3">
                                            <div>
                                                <dt class="font-extrabold uppercase text-gray">Modello</dt>
                                                <dd class="mt-3 font-black">{{ $selectedLead->calculator_model ?: '—' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="font-extrabold uppercase text-gray">Colore</dt>
                                                <dd class="mt-3 font-black">{{ $selectedLead->live_mockup_color ?: '—' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="font-extrabold uppercase text-gray">Quantità</dt>
                                                <dd class="mt-3 font-black">{{ $selectedLead->calculator_quantity ? number_format($selectedLead->calculator_quantity, 0, ',', '.') : '—' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="font-extrabold uppercase text-gray">Origine CTA</dt>
                                                <dd class="mt-3 font-black">{{ $selectedLead->cta_origin === 'live_mockup' ? 'Anteprima live' : ($selectedLead->cta_origin ?: '—') }}</dd>
                                            </div>
                                            <div class="col-span-2 sm:col-span-3">
                                                <dt class="font-extrabold uppercase text-gray">Personalizzazione</dt>
                                                <dd class="mt-3 font-black">{{ $mockupPersonalization ?: '—' }}</dd>
                                            </div>
                                            @if ($selectedLead->live_mockup_configured_at)
                                                <div class="col-span-2 sm:col-span-3">
                                                    <dt class="font-extrabold uppercase text-gray">Configurato il</dt>
                                                    <dd class="mt-3 font-black">{{ $selectedLead->live_mockup_configured_at->format('d/m/Y H:i') }}</dd>
                                                </div>
                                            @endif
                                        </dl>

                                        <div class="grid gap-10 sm:grid-cols-2">
                                            @foreach (['front' => 'Fronte', 'back' => 'Retro'] as $side => $label)
                                                <article class="overflow-hidden rounded-10 border border-gray-mid bg-gray-light">
                                                    <div class="flex items-center justify-between border-b border-gray-mid bg-white px-10 py-8">
                                                        <p class="text-11 font-black uppercase">{{ $label }}</p>
                                                        @if ($liveMockupFiles[$side]['exists'])
                                                            <span class="rounded-full bg-whatsapp/10 px-5 py-2 text-[8px] font-extrabold uppercase leading-none text-whatsapp">Disponibile</span>
                                                        @endif
                                                    </div>

                                                    @if ($hasMockupBase)
                                                        <div class="bg-white p-8">
                                                            <div class="relative mx-auto aspect-[4/5] max-w-[250px] overflow-hidden">
                                                                <img src="{{ route('admin.leads.mockup-base.show', [$selectedLead, $side]) }}" alt="Mockup {{ strtolower($label) }} {{ $selectedLead->calculator_model }} {{ $selectedLead->live_mockup_color }}" class="h-full w-full object-contain">
                                                                @if ($liveMockupFiles[$side]['exists'])
                                                                    <img src="{{ route('admin.leads.mockup-files.show', [$selectedLead, $side]) }}" alt="Grafica {{ strtolower($label) }} caricata dal cliente" class="absolute -translate-x-1/2 object-contain" style="top: {{ $artworkPositions[$side]['top'] }}; left: {{ $artworkPositions[$side]['left'] }}; width: {{ $artworkPositions[$side]['width'] }}; height: {{ $artworkPositions[$side]['height'] }};">
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @elseif ($liveMockupFiles[$side]['exists'])
                                                        <a href="{{ route('admin.leads.mockup-files.show', [$selectedLead, $side]) }}" target="_blank" rel="noopener" class="block bg-white p-8">
                                                            <img src="{{ route('admin.leads.mockup-files.show', [$selectedLead, $side]) }}" alt="Grafica {{ strtolower($label) }} caricata dal cliente" class="mx-auto h-44 w-full object-contain">
                                                        </a>
                                                    @endif

                                                    @if ($liveMockupFiles[$side]['exists'])
                                                        <div class="grid grid-cols-2 gap-6 p-8">
                                                            <a href="{{ route('admin.leads.mockup-files.show', [$selectedLead, $side]) }}" target="_blank" rel="noopener" class="inline-flex h-32 items-center justify-center rounded-10 border border-gray-mid bg-white px-8 text-10 font-extrabold uppercase transition hover:border-bullstar hover:text-bullstar">Apri</a>
                                                            <a href="{{ route('admin.leads.mockup-files.download', [$selectedLead, $side]) }}" class="inline-flex h-32 items-center justify-center rounded-10 bg-bullstar px-8 text-10 font-extrabold uppercase text-white transition hover:bg-bullstar-hover">Scarica</a>
                                                        </div>
                                                    @elseif (filled($liveMockupFiles[$side]['path']))
                                                        <div class="p-12 text-center text-11 font-bold text-red-700">File non trovato nello spazio di archiviazione.</div>
                                                    @else
                                                        <div class="p-12 text-center text-11 font-semibold text-gray">Grafica non caricata.</div>
                                                    @endif
                                                </article>
                                            @endforeach
                                        </div>
                                    </div>
                                </section>

                            <section x-show="tab === 'product'" x-cloak class="order-5 lg:col-span-2 lg:row-start-1">
                                <div class="mb-10 rounded-10 border border-gray-mid bg-white p-10 sm:p-12">
                                    <div class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                                        <div>
                                            <p class="text-10 font-extrabold uppercase text-gray">Storico cliente</p>
                                            <h3 class="mt-4 text-18 font-black">{{ $selectedLead->salesSheets->count() }} {{ $selectedLead->salesSheets->count() === 1 ? 'ordine' : 'ordini' }}</h3>
                                            <p class="mt-3 text-10 font-semibold text-gray">Ogni nuovo acquisto resta collegato allo stesso lead e ha conti, prodotti e materiali separati.</p>
                                        </div>
                                        <form method="POST" action="{{ route('admin.leads.orders.store', $selectedLead) }}" class="flex w-full gap-5 lg:w-auto">@csrf
                                            <input name="name" required maxlength="255" placeholder="Nome nuovo ordine" class="min-w-0 flex-1 rounded-10 border-gray-mid px-10 py-8 text-11 font-semibold lg:w-[240px]">
                                            <button class="shrink-0 rounded-10 bg-bullstar px-12 text-10 font-extrabold uppercase text-white">+ Nuovo ordine</button>
                                        </form>
                                    </div>
                                    <div class="mt-8 flex gap-5 overflow-x-auto pb-2">
                                        @foreach($selectedLead->salesSheets as $order)
                                            <div class="flex shrink-0 overflow-hidden rounded-10 border {{ $selectedSalesSheet?->id === $order->id ? 'border-black-nike bg-black-nike text-white' : 'border-gray-mid bg-gray-light' }}">
                                                <a href="{{ route('admin.leads.index', ['lead' => $selectedLead, 'sales_sheet' => $order->id]).'#product' }}" class="px-10 py-7 text-10 font-extrabold uppercase transition {{ $selectedSalesSheet?->id === $order->id ? 'text-white' : 'hover:text-bullstar' }}">{{ $order->order_number }} · {{ $order->name }} · € {{ number_format((float)$order->revenue_total, 2, ',', '.') }}</a>
                                                <form method="POST" action="{{ route('admin.leads.orders.destroy', [$selectedLead, $order]) }}" onsubmit="return confirm('Eliminare definitivamente questo ordine, i prodotti e tutti i file grafici collegati?')" class="flex border-l {{ $selectedSalesSheet?->id === $order->id ? 'border-white/20' : 'border-gray-mid' }}">@csrf @method('DELETE')
                                                    <button type="submit" title="Elimina ordine" aria-label="Elimina ordine {{ $order->order_number }}" class="px-8 text-14 font-black transition {{ $selectedSalesSheet?->id === $order->id ? 'text-white/70 hover:bg-red-600 hover:text-white' : 'text-red-600 hover:bg-red-50' }}">×</button>
                                                </form>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <livewire:admin.lead-sales-sheet :lead-id="$selectedLead->id" :sheet-id="$selectedSalesSheet?->id" :key="'lead-sales-sheet-'.$selectedLead->id.'-'.($selectedSalesSheet?->id ?? 'new')" />
                            </section>

                            <section x-show="tab === 'proposal'" x-cloak class="order-2 overflow-hidden rounded-10 border border-gray-mid bg-white shadow-sm lg:col-span-2 lg:col-start-1 lg:row-start-1">
                                <div class="flex flex-wrap items-center justify-between gap-8 bg-black-nike px-12 py-12 text-white">
                                    <div>
                                        <p class="text-10 font-extrabold uppercase tracking-wider text-white/60">Scheda proposta</p>
                                        <h3 class="mt-4 text-18 font-black">Preventivi e invii al cliente</h3>
                                        <p class="mt-3 text-10 font-semibold text-white/60">Crea, archivia e condividi le proposte commerciali.</p>
                                    </div>
                                    <span class="rounded-full bg-white/10 px-10 py-6 text-10 font-extrabold">{{ $selectedLead->quotePdfs->count() }} {{ $selectedLead->quotePdfs->count() === 1 ? 'proposta' : 'proposte' }}</span>
                                </div>

                                <form method="POST" action="{{ route('admin.leads.quote-pdfs.store', $selectedLead) }}" enctype="multipart/form-data" class="m-12 grid min-w-0 gap-10 rounded-10 border border-gray-mid bg-gray-light p-10 md:grid-cols-2">
                                    @csrf
                                    <label class="block min-w-0">
                                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Numero proposta</span>
                                        <input name="proposal_number" value="{{ old('proposal_number') }}" type="text" maxlength="100" required placeholder="Inserisci numero proposta" class="mt-6 w-full rounded-10 border-gray-mid bg-white px-12 py-10 text-14 font-semibold text-black-nike focus:border-bullstar focus:ring-bullstar">
                                    </label>
                                    <label class="block min-w-0">
                                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Importo proposta</span>
                                        <input name="proposal_amount" value="{{ old('proposal_amount') }}" type="number" min="0.50" step="0.01" required placeholder="0,00" class="mt-6 w-full rounded-10 border-gray-mid bg-white px-12 py-10 text-14 font-semibold text-black-nike focus:border-bullstar focus:ring-bullstar">
                                    </label>
                                    <label class="block min-w-0 md:col-span-2">
                                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">PDF proposta</span>
                                        <input name="proposal_pdf" type="file" accept="application/pdf,.pdf" class="mt-6 block w-full min-w-0 overflow-hidden rounded-10 border border-dashed border-gray-mid bg-white px-8 py-8 text-12 font-semibold text-black-nike file:mr-8 file:rounded-10 file:border-0 file:bg-black-nike file:px-10 file:py-8 file:text-11 file:font-extrabold file:uppercase file:tracking-normal file:text-white focus:border-bullstar focus:ring-bullstar">
                                        <span class="mt-4 block text-10 font-semibold text-gray">Facoltativo · PDF fino a 20 MB.</span>
                                    </label>
                                    <label class="flex items-start gap-8 rounded-10 border border-gray-mid bg-white px-10 py-8">
                                        <input name="send_google_event" value="1" type="checkbox" class="mt-1 rounded border-gray-mid text-bullstar focus:ring-bullstar">
                                        <span class="min-w-0 text-12 font-bold leading-[18px] text-black-nike">
                                            Invia evento proposta a Google
                                        </span>
                                    </label>
                                    <button type="submit" class="w-full rounded-10 bg-bullstar px-16 py-12 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover">
                                        Salva proposta
                                    </button>
                                </form>

                                <div class="border-t border-gray-mid bg-gray-light/50 px-12 py-12">
                                    <div class="mb-8 flex items-center justify-between gap-6">
                                        <div>
                                            <p class="text-11 font-black uppercase">Proposte salvate</p>
                                            <p class="mt-3 text-10 font-semibold text-gray">Apri il PDF o scegli il canale di invio.</p>
                                        </div>
                                    </div>
                                    <div class="grid gap-8 lg:grid-cols-2">
                                    @forelse ($selectedLead->quotePdfs as $quotePdf)
                                        @php
                                            $hasProposalPdf = $quotePdf->disk && $quotePdf->path;
                                        @endphp
                                        <article class="flex flex-col rounded-10 border border-gray-mid bg-white p-10">
                                            <div class="flex flex-wrap items-center justify-between gap-6">
                                                <div class="min-w-0">
                                                    <p class="text-10 font-extrabold uppercase text-gray">{{ $quotePdf->proposal_number }}</p>
                                                    <p class="mt-3 text-18 font-black text-black-nike">€ {{ number_format((float) $quotePdf->amount, 2, ',', '.') }}</p>
                                                    @if ($hasProposalPdf)
                                                        <a href="{{ route('admin.leads.quote-pdfs.show', [$selectedLead, $quotePdf]) }}" target="_blank" class="mt-2 block truncate text-12 font-bold text-bullstar underline-offset-4 hover:underline">{{ $quotePdf->filename }}</a>
                                                    @else
                                                        <p class="mt-2 text-12 font-bold text-gray">Nessun PDF allegato</p>
                                                    @endif
                                                </div>
                                                <p class="text-11 font-semibold text-gray">
                                                    {{ $quotePdf->uploaded_at?->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}
                                                </p>
                                            </div>
                                            <div class="mt-auto grid gap-6 pt-10 sm:grid-cols-3">
                                                <form method="POST" action="{{ route('admin.leads.quote-pdfs.whatsapp', [$selectedLead, $quotePdf]) }}" class="flex h-full flex-col rounded-10 border border-gray-mid bg-gray-light p-8">
                                                    @csrf
                                                    <p class="text-10 font-extrabold uppercase text-black-nike">WhatsApp</p>
                                                    <label class="mt-6 flex flex-1 items-start gap-6 text-10 font-semibold leading-[16px] text-gray">
                                                        <input name="send_google_event" value="1" type="checkbox" class="rounded border-gray-mid text-bullstar focus:ring-bullstar">
                                                        <span>Registra anche l'evento proposta su Google</span>
                                                    </label>
                                                    <button type="submit" @disabled(! $hasProposalPdf) class="mt-8 w-full rounded-10 border border-whatsapp bg-whatsapp px-10 py-8 text-11 font-extrabold uppercase leading-none tracking-normal text-white transition hover:bg-whatsapp/90 disabled:cursor-not-allowed disabled:border-gray-mid disabled:bg-gray">
                                                        Invia
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.leads.quote-pdfs.email', [$selectedLead, $quotePdf]) }}" class="flex h-full flex-col rounded-10 border border-gray-mid bg-gray-light p-8">
                                                    @csrf
                                                    <p class="text-10 font-extrabold uppercase text-black-nike">Email</p>
                                                    <label class="mt-6 flex flex-1 items-start gap-6 text-10 font-semibold leading-[16px] text-gray">
                                                        <input name="send_google_event" value="1" type="checkbox" class="rounded border-gray-mid text-bullstar focus:ring-bullstar">
                                                        <span>Registra anche l'evento proposta su Google</span>
                                                    </label>
                                                    <button type="submit" @disabled(! $selectedLead->email || ! $hasProposalPdf) class="mt-8 w-full rounded-10 border border-bullstar bg-bullstar px-10 py-8 text-11 font-extrabold uppercase leading-none tracking-normal text-white transition hover:bg-bullstar-hover disabled:cursor-not-allowed disabled:border-gray-mid disabled:bg-gray">
                                                        Invia
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.leads.quote-pdfs.destroy', [$selectedLead, $quotePdf]) }}" onsubmit="return confirm('Eliminare questa proposta?')" class="flex h-full flex-col rounded-10 border border-gray-mid bg-gray-light p-8">
                                                    @csrf
                                                    @method('DELETE')
                                                    <p class="text-10 font-extrabold uppercase text-black-nike">Gestione</p>
                                                    <p class="mt-6 flex-1 text-10 font-semibold leading-[16px] text-gray">Rimuovi definitivamente questa proposta e il relativo PDF.</p>
                                                    <button type="submit" class="mt-8 w-full rounded-10 border border-red-600 bg-white px-10 py-8 text-11 font-extrabold uppercase leading-none tracking-normal text-red-600 transition hover:bg-red-50">
                                                        Elimina proposta
                                                    </button>
                                                </form>
                                            </div>
                                        </article>
                                    @empty
                                        <div class="rounded-10 border border-dashed border-gray-mid bg-white px-12 py-20 text-center lg:col-span-2">
                                            <p class="text-13 font-black">Nessuna proposta salvata</p>
                                            <p class="mt-3 text-10 font-semibold text-gray">Compila il modulo per creare la prima proposta.</p>
                                        </div>
                                    @endforelse
                                    </div>
                                </div>
                            </section>

                            <section x-show="tab === 'main'" x-cloak class="order-3 grid gap-10 lg:col-start-2 lg:row-start-1">
                                <div class="overflow-hidden rounded-10 border border-gray-mid bg-white shadow-sm">
                                    <div class="flex items-center justify-between gap-6 border-b border-gray-mid bg-gray-light px-12 py-8">
                                        <div>
                                            <p class="text-11 font-black uppercase">Pagamento</p>
                                            <p class="mt-3 text-10 font-semibold text-gray">Link e azioni di incasso.</p>
                                        </div>
                                        <p class="text-18 font-black">{{ $selectedLead->payment_amount ? '€ ' . number_format((float) $selectedLead->payment_amount, 2, ',', '.') : '-' }}</p>
                                    </div>
                                    <div class="p-12">
                                    @if ($selectedLead->payment_link)
                                        <a href="{{ $selectedLead->payment_link }}" target="_blank" class="block truncate rounded-10 border border-gray-mid bg-gray-light px-8 py-6 text-10 font-bold text-bullstar underline-offset-4 hover:underline">Apri link di pagamento ↗</a>
                                        <div class="mt-8 grid gap-6 sm:grid-cols-2">
                                        <form method="POST" action="{{ route('admin.leads.stripe-payment-link.whatsapp', $selectedLead) }}">
                                            @csrf
                                            <button type="submit" class="w-full rounded-10 border border-whatsapp bg-whatsapp px-10 py-8 text-11 font-extrabold uppercase leading-none tracking-normal text-white transition hover:bg-whatsapp/90">
                                                Invia su WhatsApp
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.leads.stripe-payment-link.email', $selectedLead) }}">
                                            @csrf
                                            <button type="submit" @disabled(! $selectedLead->email) class="w-full rounded-10 border border-bullstar bg-bullstar px-10 py-8 text-11 font-extrabold uppercase leading-none tracking-normal text-white transition hover:bg-bullstar-hover disabled:cursor-not-allowed disabled:border-gray-mid disabled:bg-gray">
                                                Invia via email
                                            </button>
                                        </form>
                                        </div>
                                    @endif
                                    <form method="POST" action="{{ route('admin.leads.stripe-payment-link', $selectedLead) }}" class="mt-10">
                                        @csrf
                                        <button type="submit" class="w-full rounded-10 bg-bullstar px-16 py-12 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover">
                                            Crea link Stripe per ultima proposta
                                        </button>
                                    </form>
                                    </div>
                                </div>
                            </section>

                            <section x-show="tab === 'main'" x-cloak class="order-4 grid gap-10 lg:col-start-2 lg:row-start-2">
                                <div class="rounded-10 border border-gray-mid bg-white p-12 shadow-sm">
                                    <p class="text-11 font-black uppercase">Contatti</p>
                                    <div class="mt-8 grid gap-5">
                                        <div class="rounded-10 border border-gray-mid bg-gray-light px-8 py-6"><p class="text-10 font-extrabold uppercase text-gray">Email</p><p class="mt-3 break-all text-12 font-bold">{{ $selectedLead->email ?: 'Email mancante' }}</p></div>
                                        <div class="rounded-10 border border-gray-mid bg-gray-light px-8 py-6"><p class="text-10 font-extrabold uppercase text-gray">Telefono</p><p class="mt-3 text-12 font-bold">{{ $selectedLead->phone ?: 'Telefono mancante' }}</p></div>
                                    </div>
                                </div>
                                <div class="rounded-10 border border-gray-mid bg-white p-12 shadow-sm">
                                    <p class="text-11 font-black uppercase">Richiesta iniziale</p>
                                    <p class="mt-8 whitespace-pre-line rounded-10 border border-gray-mid bg-gray-light p-10 text-13 font-semibold leading-[20px] text-black-nike">{{ $selectedLead->message ?: 'Nessun messaggio salvato.' }}</p>
                                </div>
                            </section>

                            <section x-show="tab === 'origin'" x-cloak class="order-6 overflow-hidden rounded-10 border border-gray-mid bg-gray-light shadow-sm lg:col-span-2 lg:row-start-1">
                                @php
                                    $landingUrl = $selectedLead->landing_page ?: $selectedLead->entry_page;
                                    $isLandingUrl = $landingUrl && \Illuminate\Support\Str::startsWith($landingUrl, ['http://', 'https://']);
                                    $isReferrerUrl = $selectedLead->referrer && \Illuminate\Support\Str::startsWith($selectedLead->referrer, ['http://', 'https://']);
                                @endphp
                                <div class="bg-black-nike px-12 py-12 text-white">
                                    <p class="text-10 font-extrabold uppercase tracking-wider text-white/60">Origine lead</p>
                                    <h3 class="mt-4 text-18 font-black">Acquisizione e tracciamento</h3>
                                    <p class="mt-3 text-10 font-semibold text-white/60">Percorso di ingresso, attribuzione pubblicitaria e dati tecnici.</p>
                                </div>

                                <div class="p-12">
                                <dl class="grid gap-8 text-12 md:grid-cols-2">
                                    <div class="rounded-10 border border-gray-mid bg-white p-10">
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">Pagina di arrivo</dt>
                                        <dd class="mt-4 break-words font-bold">
                                            @if ($landingUrl && $isLandingUrl)
                                                <a href="{{ $landingUrl }}" target="_blank" rel="noopener" class="text-bullstar underline-offset-4 hover:underline">{{ $landingUrl }}</a>
                                            @else
                                                {{ $landingUrl ?: '-' }}
                                            @endif
                                        </dd>
                                    </div>
                                    <div class="rounded-10 border border-gray-mid bg-white p-10">
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">Referrer</dt>
                                        <dd class="mt-4 break-words font-bold">
                                            @if ($selectedLead->referrer && $isReferrerUrl)
                                                <a href="{{ $selectedLead->referrer }}" target="_blank" rel="noopener" class="text-bullstar underline-offset-4 hover:underline">{{ $selectedLead->referrer }}</a>
                                            @else
                                                {{ $selectedLead->referrer ?: '-' }}
                                            @endif
                                        </dd>
                                    </div>
                                </dl>

                                <div class="mt-10">
                                    <p class="text-11 font-black uppercase">Dettagli acquisizione</p>
                                    <p class="mt-3 text-10 font-semibold text-gray">Dati registrati al momento dell'ingresso.</p>
                                </div>
                                <dl class="mt-8 grid gap-6 text-12 sm:grid-cols-2 lg:grid-cols-4">
                                    <div class="rounded-10 border border-gray-mid bg-white p-8">
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">Canale acquisizione</dt>
                                        <dd class="mt-4 break-words font-bold">{{ $manualChannels[$selectedLead->acquisition_channel] ?? ($selectedLead->acquisition_channel ?: '-') }}</dd>
                                    </div>
                                    <div class="rounded-10 border border-gray-mid bg-white p-8">
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">Affidabilità origine</dt>
                                        <dd class="mt-4 break-words font-bold">{{ $attributionConfidences[$selectedLead->attribution_confidence] ?? ($selectedLead->attribution_confidence ?: '-') }}</dd>
                                    </div>
                                    <div class="rounded-10 border border-gray-mid bg-white p-8">
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">Inserito da</dt>
                                        <dd class="mt-4 break-words font-bold">{{ $selectedLead->createdByAdmin?->name ?: 'Automazione' }}</dd>
                                    </div>
                                    <div class="rounded-10 border border-gray-mid bg-white p-8">
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">Nota attribuzione</dt>
                                        <dd class="mt-4 break-words font-bold">{{ $selectedLead->attribution_note ?: '-' }}</dd>
                                    </div>
                                    <div class="rounded-10 border border-gray-mid bg-white p-8">
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">Ingresso</dt>
                                        <dd class="mt-4 font-bold">{{ optional($selectedLead->created_at)?->timezone(config('app.display_timezone'))->format('d/m/Y H:i') ?: '-' }}</dd>
                                    </div>
                                    <div class="rounded-10 border border-gray-mid bg-white p-8">
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">IP</dt>
                                        <dd class="mt-4 break-all font-bold">{{ $selectedLead->ip ?: '-' }}</dd>
                                    </div>
                                    <div class="rounded-10 border border-gray-mid bg-white p-8">
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">Device</dt>
                                        <dd class="mt-4 break-words font-bold">{{ $selectedLead->device ?: '-' }}</dd>
                                    </div>
                                    <div class="rounded-10 border border-gray-mid bg-white p-8">
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">UTM source</dt>
                                        <dd class="mt-4 break-words font-bold">{{ $selectedLead->utm_source ?: '-' }}</dd>
                                    </div>
                                    <div class="rounded-10 border border-gray-mid bg-white p-8">
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">UTM medium</dt>
                                        <dd class="mt-4 break-words font-bold">{{ $selectedLead->utm_medium ?: '-' }}</dd>
                                    </div>
                                    <div class="rounded-10 border border-gray-mid bg-white p-8">
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">Campagna</dt>
                                        <dd class="mt-4 break-words font-bold">{{ $selectedLead->utm_campaign ?: '-' }}</dd>
                                    </div>
                                    <div class="rounded-10 border border-gray-mid bg-white p-8">
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">Ad Group</dt>
                                        <dd class="mt-4 break-words font-bold">{{ $selectedLead->ad_group ?: '-' }}</dd>
                                    </div>
                                    <div class="rounded-10 border border-gray-mid bg-white p-8">
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">Keyword</dt>
                                        <dd class="mt-4 break-words font-bold">{{ $selectedLead->utm_term ?: '-' }}</dd>
                                    </div>
                                    <div class="rounded-10 border border-gray-mid bg-white p-8">
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">Search Term</dt>
                                        <dd class="mt-4 break-words font-bold">{{ $selectedLead->search_term ?: '-' }}</dd>
                                    </div>
                                    <div class="rounded-10 border border-gray-mid bg-white p-8">
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">Paese acquisizione</dt>
                                        <dd class="mt-4 break-words font-bold">{{ $selectedLead->acquisition_country ?: '-' }}</dd>
                                    </div>
                                    <div class="rounded-10 border border-gray-mid bg-white p-8">
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">Regione acquisizione</dt>
                                        <dd class="mt-4 break-words font-bold">{{ $selectedLead->acquisition_region ?: '-' }}</dd>
                                    </div>
                                    <div class="rounded-10 border border-gray-mid bg-white p-8">
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">Content</dt>
                                        <dd class="mt-4 break-words font-bold">{{ $selectedLead->utm_content ?: '-' }}</dd>
                                    </div>
                                    <div class="rounded-10 border border-gray-mid bg-white p-8">
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">GCLID</dt>
                                        <dd class="mt-4 break-all font-bold">{{ $selectedLead->gclid ?: '-' }}</dd>
                                    </div>
                                    <div class="rounded-10 border border-gray-mid bg-white p-8">
                                        <dt class="font-extrabold uppercase tracking-normal text-gray">FBCLID</dt>
                                        <dd class="mt-4 break-all font-bold">{{ $selectedLead->fbclid ?: '-' }}</dd>
                                    </div>
                                </dl>

                                <div class="mt-10 rounded-10 border border-gray-mid bg-white p-10">
                                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">User agent</p>
                                    <p class="mt-4 break-words text-12 font-semibold leading-[18px] text-black-nike">{{ $selectedLead->user_agent ?: '-' }}</p>
                                </div>
                                </div>
                            </section>

                            @if ($selectedConversation)
                                <a x-show="tab === 'main'" x-cloak href="{{ route('admin.conversations.show', $selectedConversation) }}" target="_blank" class="order-7 block rounded-10 border border-whatsapp bg-whatsapp px-12 py-12 text-center text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-whatsapp/90 lg:col-span-2">
                                    Apri chat WhatsApp
                                </a>
                            @else
                                <div x-show="tab === 'main'" x-cloak class="order-7 rounded-10 border border-dashed border-gray-mid bg-gray-light px-12 py-12 text-14 font-semibold text-gray lg:col-span-2">
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
                </main>
            </section>
@endsection
