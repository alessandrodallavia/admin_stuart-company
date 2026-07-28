@extends('admin.layouts.app')

@section('title', 'Dashboard CRM - Stuart Admin')
@section('page_title', 'Dashboard CRM')
@section('active_nav', 'crm-dashboard')

@section('content')
    @php
        $money = fn ($value) => '€ ' . number_format((float) $value, 2, ',', '.');
        $number = fn ($value, $decimals = 0) => number_format((float) $value, $decimals, ',', '.');
        $moneyOrNd = fn ($value) => $value === null ? 'N.D.' : $money($value);
        $numberOrNd = fn ($value, $decimals = 0) => $value === null ? 'N.D.' : $number($value, $decimals);
        $percentOrNd = fn ($value) => $value === null ? 'N.D.' : $number($value, 1) . '%';
        $currentFilters = array_filter(['q' => $search, 'statuses' => $selectedStatuses]);
    @endphp

    <div class="space-y-16">
        <section class="rounded-10 border border-gray-mid bg-white p-10 md:p-12">
            <div class="flex flex-col gap-10 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-10 font-extrabold uppercase tracking-normal text-gray">Andamento commerciale</p>
                    <h2 class="mt-3 text-20 font-black leading-tight">CRM Dashboard</h2>
                    <p class="mt-5 text-12 font-semibold text-gray">Il periodo è applicato alla data di acquisizione del lead.</p>
                    <div class="mt-6 flex flex-wrap gap-5">
                        <a href="{{ route('admin.dashboard', $currentFilters + ['date_from' => now()->startOfMonth()->toDateString(), 'date_to' => now()->toDateString()]) }}" class="rounded-full border border-gray-mid bg-white px-8 py-5 text-10 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Questo mese</a>
                        <a href="{{ route('admin.dashboard', $currentFilters + ['date_from' => '2026-07-06', 'date_to' => now()->toDateString()]) }}" class="rounded-full border border-gray-mid bg-white px-8 py-5 text-10 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Dal 6 luglio</a>
                    </div>
                </div>

                <form method="GET" action="{{ route('admin.dashboard') }}" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-[125px_125px_minmax(170px,210px)_minmax(180px,230px)_auto]">
                    <label class="block">
                        <span class="text-10 font-extrabold uppercase tracking-normal text-gray">Dal</span>
                        <input name="date_from" value="{{ $dateFrom }}" type="date" class="mt-3 h-32 w-full rounded-10 border-gray-mid px-8 py-0 text-12 font-semibold focus:border-bullstar focus:ring-bullstar">
                    </label>
                    <label class="block">
                        <span class="text-10 font-extrabold uppercase tracking-normal text-gray">Al</span>
                        <input name="date_to" value="{{ $dateTo }}" type="date" class="mt-3 h-32 w-full rounded-10 border-gray-mid px-8 py-0 text-12 font-semibold focus:border-bullstar focus:ring-bullstar">
                    </label>
                    <label class="block">
                        <span class="text-10 font-extrabold uppercase tracking-normal text-gray">Cerca</span>
                        <input name="q" value="{{ $search }}" type="search" placeholder="Lead, campagna, prodotto..." class="mt-3 h-32 w-full rounded-10 border-gray-mid px-8 py-0 text-12 font-semibold focus:border-bullstar focus:ring-bullstar">
                    </label>
                    <div class="block">
                        <span class="text-10 font-extrabold uppercase tracking-normal text-gray">Stato</span>
                        <details class="group relative mt-3">
                            <summary class="flex h-32 cursor-pointer list-none items-center justify-between rounded-10 border border-gray-mid bg-white px-8 text-12 font-semibold focus:outline-none focus:ring-2 focus:ring-bullstar">
                                <span>{{ $selectedStatuses === [] ? 'Tutti gli stati' : count($selectedStatuses) . ' selezionati' }}</span>
                                <span class="text-10 transition group-open:rotate-180">▼</span>
                            </summary>
                            <div class="absolute right-0 top-full z-50 mt-4 max-h-300 w-full min-w-[220px] overflow-y-auto rounded-10 border border-gray-mid bg-white p-6 shadow-lg">
                                @foreach ($statuses as $value => $label)
                                    <label class="flex cursor-pointer items-center gap-8 rounded-10 px-6 py-6 text-12 font-semibold transition hover:bg-gray-light">
                                        <input type="checkbox" name="statuses[]" value="{{ $value }}" @checked(in_array($value, $selectedStatuses, true)) class="h-16 w-16 rounded border-gray-mid text-bullstar focus:ring-bullstar">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </details>
                    </div>
                    <button type="submit" class="inline-flex h-32 w-fit items-center justify-center self-end justify-self-start rounded-10 bg-black-nike px-10 text-10 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar">Applica</button>
                </form>
            </div>
        </section>

        <section class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['label' => 'Record acquisiti', 'value' => $number($stats['records']), 'help' => 'Tutti i record creati nel periodo, inclusi i pre-lead che non hanno ancora proseguito.'],
                ['label' => 'Pre-lead fermi', 'value' => $number($stats['pre_leads']), 'help' => 'Record che risultano ancora nello stato Pre lead. Sono esclusi dai KPI commerciali.'],
                ['label' => 'Chat avviate', 'value' => $number($stats['chat_started']), 'detail' => $percentOrNd($stats['prelead_to_chat']) . ' dei record', 'help' => 'Lead collegati a una conversazione con almeno un messaggio reale ricevuto dal cliente. I messaggi automatici in uscita non contano.'],
                ['label' => 'Lead lavorati', 'value' => $number($stats['worked_leads']), 'help' => 'Record con stato diverso da Pre lead. Sono il denominatore dei KPI commerciali.'],
                ['label' => 'Proposte', 'value' => $number($stats['quotes']), 'detail' => $money($stats['quote_value']), 'help' => 'Lead lavorati con almeno una proposta salvata. In evidenza è indicata la somma dei valori.'],
                ['label' => 'Lead pagati', 'value' => $number($stats['payments']), 'detail' => $money($stats['payment_value']), 'help' => 'Lead lavorati con stato Pagato. Non rappresenta ancora il numero delle singole transazioni.'],
                ['label' => 'Margine conosciuto', 'value' => $moneyOrNd($stats['margin']), 'detail' => $stats['payments'] > 0 ? $number($stats['margin_coverage']) . ' di ' . $number($stats['payments']) . ' pagati' : null, 'help' => 'Somma dei soli margini valorizzati sui lead pagati. La copertura indica per quanti lead il dato è disponibile.'],
                ['label' => 'Valore medio proposta', 'value' => $moneyOrNd($stats['average_quote']), 'help' => 'Valore delle proposte dei lead lavorati diviso per il numero di lead con proposta.'],
                ['label' => 'Valore medio pagato', 'value' => $moneyOrNd($stats['average_payment']), 'help' => 'Valore pagato registrato diviso per il numero di lead pagati.'],
                ['label' => 'Quantità media', 'value' => $numberOrNd($stats['average_quantity'], 1), 'detail' => $number($stats['quantity_coverage']) . ' di ' . $number($stats['worked_leads']) . ' lead', 'help' => 'Media calcolata esclusivamente sui lead lavorati con quantità valorizzata. La copertura mostra quanti record concorrono al calcolo.'],
                ['label' => 'Lavorati → Proposta', 'value' => $percentOrNd($stats['worked_to_quote']), 'help' => 'Lead lavorati con almeno una proposta diviso per tutti i lead con stato diverso da Pre lead.'],
                ['label' => 'Proposta → Pagamento', 'value' => $percentOrNd($stats['quote_to_payment']), 'help' => 'Lead pagati diviso per lead lavorati con almeno una proposta.'],
                ['label' => 'Lavorati → Pagamento', 'value' => $percentOrNd($stats['worked_to_payment']), 'help' => 'Lead pagati diviso per tutti i lead con stato diverso da Pre lead.'],
            ] as $card)
                <article class="min-w-0 rounded-10 border border-gray-mid bg-white p-10 md:p-12">
                    <div class="flex items-center gap-5">
                        <p class="min-w-0 truncate text-11 font-extrabold uppercase tracking-normal text-gray">{{ $card['label'] }}</p>
                        <span class="group relative shrink-0">
                            <button type="button" aria-label="Informazioni su {{ $card['label'] }}" class="inline-flex h-16 w-16 -translate-y-px items-center justify-center rounded-full bg-black-nike text-10 font-black leading-none text-white focus:outline-none focus:ring-2 focus:ring-bullstar">?</button>
                            <span role="tooltip" class="pointer-events-none invisible absolute left-1/2 top-full z-50 mt-6 w-[220px] -translate-x-1/2 rounded-10 bg-black-nike px-10 py-8 text-left text-11 font-semibold normal-case leading-[16px] text-white opacity-0 shadow-lg transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">{{ $card['help'] }}</span>
                        </span>
                    </div>
                    <p class="mt-6 truncate text-20 font-black leading-none" title="{{ $card['value'] }}">{{ $card['value'] }}</p>
                    @if (($card['detail'] ?? null) !== null)
                        <p class="mt-5 truncate text-12 font-bold text-bullstar" title="{{ $card['detail'] }}">{{ $card['detail'] }}</p>
                    @endif
                </article>
            @endforeach
        </section>

        <section class="rounded-10 border border-gray-mid bg-white">
            <div class="border-b border-gray-mid px-10 py-10">
                <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Pipeline proposte</p>
                <p class="mt-4 text-12 font-semibold text-gray">Lettura sintetica delle proposte dei soli lead lavorati.</p>
            </div>
            <div class="grid gap-px bg-gray-mid sm:grid-cols-3">
                @foreach ([
                    ['label' => 'Aperta', 'count' => $stats['pipeline_open_count'], 'value' => $stats['pipeline_open_value'], 'help' => 'Proposte collegate a lead non pagati e non persi.'],
                    ['label' => 'Vinta', 'count' => $stats['pipeline_won_count'], 'value' => $stats['pipeline_won_value'], 'help' => 'Proposte collegate a lead con stato Pagato.'],
                    ['label' => 'Persa', 'count' => $stats['pipeline_lost_count'], 'value' => $stats['pipeline_lost_value'], 'help' => 'Proposte collegate a lead con stato Perso.'],
                ] as $pipeline)
                    <article class="bg-white p-10 md:p-12" title="{{ $pipeline['help'] }}">
                        <p class="text-10 font-extrabold uppercase tracking-normal text-gray">{{ $pipeline['label'] }}</p>
                        <div class="mt-6 flex items-end justify-between gap-8">
                            <p class="text-20 font-black leading-none">{{ $number($pipeline['count']) }}</p>
                            <p class="text-12 font-bold text-bullstar">{{ $money($pipeline['value']) }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="rounded-10 border border-gray-mid bg-white">
            <div class="flex flex-col gap-6 border-b border-gray-mid px-10 py-10 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Database lead</p>
                    <p class="mt-4 text-14 font-bold">{{ $leads->total() }} righe con i filtri correnti</p>
                    @if ($excludePreLeadsFromTable)
                        <span class="mt-5 inline-flex rounded-full bg-gray-light px-8 py-5 text-10 font-extrabold uppercase tracking-normal text-gray">Pre-lead esclusi</span>
                    @endif
                </div>
                <p class="text-12 font-semibold text-gray">I campi commerciali si modificano dalla scheda Lead.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="crm-leads-table min-w-[2350px] w-full text-left">
                    <thead class="border-b border-gray-mid bg-gray-light text-11 font-extrabold uppercase tracking-normal text-gray">
                        <tr>
                            <th class="px-8 py-7">ID</th>
                            <th class="px-8 py-7">Data lead</th>
                            <th class="px-8 py-7">Lead</th>
                            <th class="px-8 py-7">GCLID</th>
                            <th class="px-8 py-7">Campagna</th>
                            <th class="px-8 py-7">Ad Group</th>
                            <th class="px-8 py-7">Keyword</th>
                            <th class="px-8 py-7">Search Term</th>
                            <th class="px-8 py-7">Landing Page</th>
                            <th class="px-8 py-7">Dispositivo</th>
                            <th class="px-8 py-7">Paese</th>
                            <th class="px-8 py-7">Regione</th>
                            <th class="px-8 py-7">Categoria</th>
                            <th class="px-8 py-7">Prodotto</th>
                            <th class="px-8 py-7">Q.tà</th>
                            <th class="px-8 py-7">Fascia</th>
                            <th class="px-8 py-7">Preventivo</th>
                            <th class="px-8 py-7">Valore</th>
                            <th class="px-8 py-7">Pagato</th>
                            <th class="px-8 py-7">Valore pagato</th>
                            <th class="px-8 py-7">Margine</th>
                            <th class="px-8 py-7">Stato</th>
                            <th class="px-8 py-7">Qualità</th>
                            <th class="px-8 py-7">Motivo perdita</th>
                            <th class="px-8 py-7">Note</th>
                            <th class="px-8 py-7"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-mid text-12 font-semibold">
                        @forelse ($leads as $lead)
                            @php
                                $latestQuote = $lead->quotePdfs->first();
                                $isPaid = $lead->status === 'order_completed';
                                $quantity = $lead->quantity !== null ? (float) $lead->quantity : null;
                                $quantityBand = match (true) {
                                    $quantity === null => '-',
                                    $quantity < 10 => '1-9',
                                    $quantity < 20 => '10-19',
                                    $quantity < 50 => '20-49',
                                    $quantity < 100 => '50-99',
                                    default => '100+',
                                };
                            @endphp
                            <tr class="align-middle transition hover:bg-gray-light/60">
                                <td class="whitespace-nowrap px-10 py-11">{{ $lead->id }}</td>
                                <td class="whitespace-nowrap px-10 py-11">{{ $lead->created_at?->timezone(config('app.display_timezone'))->format('d/m/Y') }}</td>
                                <td class="px-10 py-11">
                                    <p class="max-w-[180px] truncate font-black">{{ $lead->name ?: 'Senza nome' }}</p>
                                    <p class="mt-3 max-w-[180px] truncate text-11 text-gray">{{ $lead->email ?: $lead->phone }}</p>
                                </td>
                                <td class="max-w-[150px] truncate px-10 py-11 font-mono text-11" title="{{ $lead->gclid }}">{{ $lead->gclid ?: '-' }}</td>
                                <td class="max-w-[160px] truncate px-10 py-11">{{ $lead->utm_campaign ?: '-' }}</td>
                                <td class="max-w-[150px] truncate px-10 py-11">{{ $lead->ad_group ?: '-' }}</td>
                                <td class="max-w-[150px] truncate px-10 py-11">{{ $lead->utm_term ?: '-' }}</td>
                                <td class="max-w-[180px] truncate px-10 py-11">{{ $lead->search_term ?: '-' }}</td>
                                <td class="max-w-[180px] truncate px-10 py-11" title="{{ $lead->landing_page }}">{{ $lead->landing_page ?: '-' }}</td>
                                <td class="px-10 py-11">{{ $lead->device ?: '-' }}</td>
                                <td class="px-10 py-11">{{ $lead->acquisition_country ?: '-' }}</td>
                                <td class="px-10 py-11">{{ $lead->acquisition_region ?: '-' }}</td>
                                <td class="px-10 py-11">{{ $lead->category ?: '-' }}</td>
                                <td class="px-10 py-11">{{ $lead->product ?: '-' }}</td>
                                <td class="px-10 py-11">{{ $quantity !== null ? $number($quantity, $quantity == floor($quantity) ? 0 : 2) : 'N.D.' }}</td>
                                <td class="px-10 py-11">{{ $quantity !== null ? $quantityBand : 'N.D.' }}</td>
                                <td class="px-10 py-11">{{ $latestQuote ? 'Sì' : 'No' }}</td>
                                <td class="whitespace-nowrap px-10 py-11">{{ $latestQuote ? $money($latestQuote->amount) : '-' }}</td>
                                <td class="px-10 py-11">{{ $isPaid ? 'Sì' : 'No' }}</td>
                                <td class="whitespace-nowrap px-10 py-11">{{ $isPaid && $lead->payment_amount ? $money($lead->payment_amount) : '-' }}</td>
                                <td class="whitespace-nowrap px-10 py-11">{{ $lead->margin_amount !== null ? $money($lead->margin_amount) : 'N.D.' }}</td>
                                <td class="px-10 py-11"><span class="inline-flex whitespace-nowrap rounded-full bg-gray-light px-8 py-5 text-10 font-extrabold uppercase">{{ $statuses[$lead->status] ?? $lead->status }}</span></td>
                                <td class="px-10 py-11">{{ $lead->lead_quality ?: '-' }}</td>
                                <td class="max-w-[180px] truncate px-10 py-11">{{ $lead->loss_reason_label ?: '-' }}</td>
                                <td class="max-w-[220px] truncate px-10 py-11" title="{{ $lead->crm_notes }}">{{ $lead->crm_notes ?: '-' }}</td>
                                <td class="px-10 py-11"><a href="{{ route('admin.leads.index', $lead) }}" class="text-11 font-extrabold uppercase text-bullstar hover:underline">Apri</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="26" class="px-16 py-24 text-center text-14 font-semibold text-gray">Nessun lead nel periodo selezionato.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($leads->hasPages())
                <div class="flex flex-col gap-8 border-t border-gray-mid px-10 py-8 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-11 font-semibold text-gray">
                        Righe {{ $leads->firstItem() }}-{{ $leads->lastItem() }} di {{ $leads->total() }}
                    </p>
                    <nav aria-label="Paginazione lead" class="flex items-center gap-4">
                        @if ($leads->onFirstPage())
                            <span class="inline-flex h-28 items-center rounded-10 border border-gray-mid px-8 text-11 font-bold text-gray opacity-50">‹</span>
                        @else
                            <a href="{{ $leads->previousPageUrl() }}" rel="prev" class="inline-flex h-28 items-center rounded-10 border border-gray-mid px-8 text-11 font-bold transition hover:border-bullstar hover:text-bullstar">‹</a>
                        @endif

                        @php
                            $firstVisiblePage = max(1, $leads->currentPage() - 1);
                            $lastVisiblePage = min($leads->lastPage(), $leads->currentPage() + 1);
                        @endphp
                        @if ($firstVisiblePage > 1)
                            <a href="{{ $leads->url(1) }}" class="inline-flex h-28 min-w-28 items-center justify-center rounded-10 border border-gray-mid px-6 text-11 font-bold transition hover:border-bullstar hover:text-bullstar">1</a>
                            @if ($firstVisiblePage > 2)<span class="px-3 text-11 font-bold text-gray">…</span>@endif
                        @endif
                        @foreach (range($firstVisiblePage, $lastVisiblePage) as $page)
                            @if ($page === $leads->currentPage())
                                <span aria-current="page" class="inline-flex h-28 min-w-28 items-center justify-center rounded-10 border border-black-nike bg-black-nike px-6 text-11 font-bold text-white">{{ $page }}</span>
                            @else
                                <a href="{{ $leads->url($page) }}" class="inline-flex h-28 min-w-28 items-center justify-center rounded-10 border border-gray-mid px-6 text-11 font-bold transition hover:border-bullstar hover:text-bullstar">{{ $page }}</a>
                            @endif
                        @endforeach
                        @if ($lastVisiblePage < $leads->lastPage())
                            @if ($lastVisiblePage < $leads->lastPage() - 1)<span class="px-3 text-11 font-bold text-gray">…</span>@endif
                            <a href="{{ $leads->url($leads->lastPage()) }}" class="inline-flex h-28 min-w-28 items-center justify-center rounded-10 border border-gray-mid px-6 text-11 font-bold transition hover:border-bullstar hover:text-bullstar">{{ $leads->lastPage() }}</a>
                        @endif

                        @if ($leads->hasMorePages())
                            <a href="{{ $leads->nextPageUrl() }}" rel="next" class="inline-flex h-28 items-center rounded-10 border border-gray-mid px-8 text-11 font-bold transition hover:border-bullstar hover:text-bullstar">›</a>
                        @else
                            <span class="inline-flex h-28 items-center rounded-10 border border-gray-mid px-8 text-11 font-bold text-gray opacity-50">›</span>
                        @endif
                    </nav>
                </div>
            @endif
        </section>

        <section class="rounded-10 border border-gray-mid bg-white">
            <div class="border-b border-gray-mid px-10 py-10">
                <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Google Ads</p>
                @if (! $ads['available'])
                    <p class="mt-4 text-12 font-semibold text-gray">{{ $ads['error'] }}</p>
                @else
                    <p class="mt-4 text-12 font-semibold text-gray">Dati aggiornati ogni 30 minuti per il periodo selezionato.</p>
                @endif
            </div>

            @if ($ads['available'])
                <div class="grid gap-px bg-gray-mid sm:grid-cols-2 lg:grid-cols-5">
                    @foreach ([
                        ['label' => 'Spesa Ads', 'value' => $money($ads['spend']), 'help' => 'Costo totale Google Ads nel periodo selezionato.'],
                        ['label' => 'Costo per record', 'value' => $moneyOrNd($ads['cost_per_record']), 'help' => 'Spesa Ads divisa per tutti i record acquisiti nel periodo, inclusi quelli ancora Pre lead.'],
                        ['label' => 'Costo per chat', 'value' => $moneyOrNd($ads['cost_per_chat']), 'help' => 'Spesa Ads divisa per le conversazioni con almeno un messaggio reale ricevuto.'],
                        ['label' => 'CAC lead pagato', 'value' => $moneyOrNd($ads['cac']), 'help' => 'Spesa Ads divisa per il numero di lead con stato Pagato.'],
                        ['label' => 'ROAS', 'value' => $ads['roas'] === null ? 'N.D.' : $number($ads['roas'], 2) . 'x', 'help' => 'Valore pagato registrato diviso per spesa Ads. Il periodo è riferito alla data di acquisizione del lead.'],
                        ['label' => 'ROMI', 'value' => $ads['romi'] === null ? 'N.D.' : $number($ads['romi'], 2) . 'x', 'help' => 'Margine diviso per spesa Ads. È mostrato solo quando tutti i lead pagati hanno un margine valorizzato.'],
                        ['label' => 'CTR', 'value' => $number($ads['ctr'], 2) . '%', 'help' => 'Percentuale di impression che hanno generato un clic.'],
                        ['label' => 'CPC medio', 'value' => $money($ads['average_cpc']), 'help' => 'Costo medio sostenuto per ogni clic.'],
                        ['label' => 'Quota impression', 'value' => $ads['impression_share'] !== null ? $number($ads['impression_share'], 1) . '%' : '-', 'help' => 'Percentuale delle impression ottenute rispetto a quelle per cui gli annunci erano idonei.'],
                        ['label' => 'QI persa ranking', 'value' => $ads['lost_rank_share'] !== null ? $number($ads['lost_rank_share'], 1) . '%' : '-', 'help' => 'Quota impression persa a causa del ranking degli annunci.'],
                        ['label' => 'QI persa budget', 'value' => $ads['lost_budget_share'] !== null ? $number($ads['lost_budget_share'], 1) . '%' : '-', 'help' => 'Quota impression persa perché il budget era insufficiente.'],
                    ] as $metric)
                        <article class="bg-white p-10">
                            <div class="flex items-center gap-5">
                                <p class="min-w-0 truncate text-10 font-extrabold uppercase tracking-normal text-gray">{{ $metric['label'] }}</p>
                                <span class="group relative shrink-0">
                                    <button type="button" aria-label="Informazioni su {{ $metric['label'] }}" class="inline-flex h-16 w-16 -translate-y-px items-center justify-center rounded-full bg-black-nike text-10 font-black leading-none text-white focus:outline-none focus:ring-2 focus:ring-bullstar">?</button>
                                    <span role="tooltip" class="pointer-events-none invisible absolute left-1/2 top-full z-50 mt-6 w-[220px] -translate-x-1/2 rounded-10 bg-black-nike px-10 py-8 text-left text-11 font-semibold normal-case leading-[16px] text-white opacity-0 shadow-lg transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">{{ $metric['help'] }}</span>
                                </span>
                            </div>
                            <p class="mt-5 text-18 font-black leading-none">{{ $metric['value'] }}</p>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
