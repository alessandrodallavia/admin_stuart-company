@extends('admin.layouts.app')

@section('title', 'Dashboard CRM - Stuart Admin')
@section('page_title', 'Dashboard CRM')
@section('active_nav', 'crm-dashboard')

@section('content')
    @php
        $money = fn ($value) => '€ ' . number_format((float) $value, 2, ',', '.');
        $number = fn ($value, $decimals = 0) => number_format((float) $value, $decimals, ',', '.');
    @endphp

    <div class="space-y-16">
        <section class="rounded-10 border border-gray-mid bg-white p-10 md:p-12">
            <div class="flex flex-col gap-10 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-10 font-extrabold uppercase tracking-normal text-gray">Andamento commerciale</p>
                    <h2 class="mt-3 text-20 font-black leading-tight">CRM Dashboard</h2>
                </div>

                <form method="GET" action="{{ route('admin.dashboard') }}" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-[125px_125px_minmax(170px,210px)_155px_auto]">
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
                    <label class="block">
                        <span class="text-10 font-extrabold uppercase tracking-normal text-gray">Stato</span>
                        <select name="status" class="mt-3 h-32 w-full rounded-10 border-gray-mid px-8 py-0 text-12 font-semibold focus:border-bullstar focus:ring-bullstar">
                            <option value="">Tutti gli stati</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected($currentStatus === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="inline-flex h-32 w-fit items-center justify-center self-end justify-self-start rounded-10 bg-black-nike px-10 text-10 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar">Applica</button>
                </form>
            </div>
        </section>

        <section class="grid gap-8 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ([
                ['label' => 'Lead', 'value' => $number($stats['leads']), 'help' => 'Numero di lead acquisiti nel periodo e con i filtri selezionati.'],
                ['label' => 'Preventivi', 'value' => $number($stats['quotes']), 'detail' => $money($stats['quote_value']), 'help' => 'Lead con almeno un preventivo. In blu è indicata la somma del valore preventivi.'],
                ['label' => 'Pagamenti', 'value' => $number($stats['payments']), 'detail' => $money($stats['payment_value']), 'help' => 'Lead con stato Pagato. In blu è indicata la somma dei pagamenti.'],
                ['label' => 'Margine', 'value' => $money($stats['margin']), 'help' => 'Somma del margine inserito sui lead pagati nel periodo.'],
                ['label' => 'Valore medio preventivo', 'value' => $money($stats['average_quote']), 'help' => 'Valore totale dei preventivi diviso per il numero di lead con preventivo.'],
                ['label' => 'Valore medio pagamento', 'value' => $money($stats['average_payment']), 'help' => 'Valore totale dei pagamenti diviso per il numero di lead pagati.'],
                ['label' => 'Quantità media / lead', 'value' => $number($stats['average_quantity'], 1), 'help' => 'Somma delle quantità inserite divisa per il numero totale di lead.'],
                ['label' => 'Lead → Preventivo', 'value' => $number($stats['lead_to_quote'], 1) . '%', 'help' => 'Percentuale di lead che hanno ricevuto almeno un preventivo.'],
                ['label' => 'Preventivo → Pagamento', 'value' => $number($stats['quote_to_payment'], 1) . '%', 'help' => 'Percentuale dei lead con preventivo che risultano pagati.'],
                ['label' => 'Lead → Pagamento', 'value' => $number($stats['lead_to_payment'], 1) . '%', 'help' => 'Percentuale di tutti i lead che risultano pagati.'],
            ] as $card)
                <article class="min-w-0 rounded-10 border border-gray-mid bg-white p-10 md:p-12">
                    <div class="flex items-center gap-5">
                        <p class="min-w-0 truncate text-11 font-extrabold uppercase tracking-normal text-gray">{{ $card['label'] }}</p>
                        <span class="group relative shrink-0">
                            <button type="button" aria-label="Informazioni su {{ $card['label'] }}" class="inline-flex h-16 w-16 items-center justify-center rounded-full border border-gray-mid bg-gray-light text-10 font-black leading-none text-gray focus:border-bullstar focus:outline-none">?</button>
                            <span role="tooltip" class="pointer-events-none invisible absolute left-1/2 top-full z-50 mt-6 w-[220px] -translate-x-1/2 rounded-10 bg-black-nike px-10 py-8 text-left text-11 font-semibold normal-case leading-[16px] text-white opacity-0 shadow-lg transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">{{ $card['help'] }}</span>
                        </span>
                    </div>
                    <p class="mt-6 truncate text-20 font-black leading-none" title="{{ $card['value'] }}">{{ $card['value'] }}</p>
                    @isset($card['detail'])
                        <p class="mt-5 truncate text-12 font-bold text-bullstar" title="{{ $card['detail'] }}">{{ $card['detail'] }}</p>
                    @endisset
                </article>
            @endforeach
        </section>

        <section class="rounded-10 border border-gray-mid bg-white">
            <div class="flex flex-col gap-6 border-b border-gray-mid px-10 py-10 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Database lead</p>
                    <p class="mt-4 text-14 font-bold">{{ $leads->total() }} righe con i filtri correnti</p>
                </div>
                <p class="text-12 font-semibold text-gray">I campi commerciali si modificano dalla scheda Lead.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="crm-leads-table min-w-[1900px] w-full text-left">
                    <thead class="border-b border-gray-mid bg-gray-light text-11 font-extrabold uppercase tracking-normal text-gray">
                        <tr>
                            <th class="px-8 py-7">Data lead</th>
                            <th class="px-8 py-7">Lead</th>
                            <th class="px-8 py-7">Campagna</th>
                            <th class="px-8 py-7">Ad Group</th>
                            <th class="px-8 py-7">Keyword</th>
                            <th class="px-8 py-7">Search Term</th>
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
                                <td class="whitespace-nowrap px-10 py-11">{{ $lead->created_at?->timezone(config('app.display_timezone'))->format('d/m/Y') }}</td>
                                <td class="px-10 py-11">
                                    <p class="max-w-[180px] truncate font-black">{{ $lead->name ?: 'Senza nome' }}</p>
                                    <p class="mt-3 max-w-[180px] truncate text-11 text-gray">{{ $lead->email ?: $lead->phone }}</p>
                                </td>
                                <td class="max-w-[160px] truncate px-10 py-11">{{ $lead->utm_campaign ?: '-' }}</td>
                                <td class="max-w-[150px] truncate px-10 py-11">{{ $lead->ad_group ?: '-' }}</td>
                                <td class="max-w-[150px] truncate px-10 py-11">{{ $lead->utm_term ?: '-' }}</td>
                                <td class="max-w-[180px] truncate px-10 py-11">{{ $lead->search_term ?: '-' }}</td>
                                <td class="px-10 py-11">{{ $lead->category ?: '-' }}</td>
                                <td class="px-10 py-11">{{ $lead->product ?: '-' }}</td>
                                <td class="px-10 py-11">{{ $quantity !== null ? $number($quantity, $quantity == floor($quantity) ? 0 : 2) : '-' }}</td>
                                <td class="px-10 py-11">{{ $quantityBand }}</td>
                                <td class="px-10 py-11">{{ $latestQuote ? 'Sì' : 'No' }}</td>
                                <td class="whitespace-nowrap px-10 py-11">{{ $latestQuote ? $money($latestQuote->amount) : '-' }}</td>
                                <td class="px-10 py-11">{{ $isPaid ? 'Sì' : 'No' }}</td>
                                <td class="whitespace-nowrap px-10 py-11">{{ $isPaid && $lead->payment_amount ? $money($lead->payment_amount) : '-' }}</td>
                                <td class="whitespace-nowrap px-10 py-11">{{ $lead->margin_amount !== null ? $money($lead->margin_amount) : '-' }}</td>
                                <td class="px-10 py-11"><span class="inline-flex whitespace-nowrap rounded-full bg-gray-light px-8 py-5 text-10 font-extrabold uppercase">{{ $statuses[$lead->status] ?? $lead->status }}</span></td>
                                <td class="px-10 py-11">{{ $lead->lead_quality ?: '-' }}</td>
                                <td class="max-w-[180px] truncate px-10 py-11">{{ $lead->loss_reason ?: '-' }}</td>
                                <td class="max-w-[220px] truncate px-10 py-11" title="{{ $lead->crm_notes }}">{{ $lead->crm_notes ?: '-' }}</td>
                                <td class="px-10 py-11"><a href="{{ route('admin.leads.index', $lead) }}" class="text-11 font-extrabold uppercase text-bullstar hover:underline">Apri</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="20" class="px-16 py-24 text-center text-14 font-semibold text-gray">Nessun lead nel periodo selezionato.</td></tr>
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
                        ['label' => 'CPL', 'value' => $money($ads['cpl']), 'help' => 'Costo per lead: spesa Ads divisa per il numero di lead.'],
                        ['label' => 'CPA', 'value' => $money($ads['cpa']), 'help' => 'Costo per acquisizione: spesa Ads divisa per il numero di pagamenti.'],
                        ['label' => 'ROAS', 'value' => $number($ads['roas'], 2) . 'x', 'help' => 'Ritorno sulla spesa pubblicitaria: valore pagamenti diviso per spesa Ads.'],
                        ['label' => 'ROMI', 'value' => $number($ads['romi'], 2) . 'x', 'help' => 'Ritorno sul marketing: margine diviso per spesa Ads.'],
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
                                    <button type="button" aria-label="Informazioni su {{ $metric['label'] }}" class="inline-flex h-16 w-16 items-center justify-center rounded-full border border-gray-mid bg-gray-light text-10 font-black leading-none text-gray focus:border-bullstar focus:outline-none">?</button>
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
