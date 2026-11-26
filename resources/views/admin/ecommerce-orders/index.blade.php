@extends('admin.layouts.app')

@section('title', 'Ordini ecommerce')
@section('active_nav', 'ecommerce-orders')

@section('content')
<div class="catalog-workspace w-full">
    <header class="catalog-page-header">
        <div>
            <p class="catalog-eyebrow">Operazioni</p>
            <h1>Ordini ecommerce</h1>
            <p>Ordini dal sito, dettagli cliente e stato dei pagamenti Stripe.</p>
        </div>
        <span class="catalog-tax-note">{{ $orders->total() }} {{ $orders->total() === 1 ? 'ordine' : 'ordini' }}</span>
    </header>

    <section class="catalog-surface mb-16">
        <form class="catalog-toolbar checkout-orders-toolbar" method="GET" role="search">
            <label class="catalog-field flex-1">
                <span>Cerca un ordine</span>
                <input type="search" name="q" value="{{ $search }}" maxlength="200" placeholder="Numero ordine, nome cliente o e-mail">
            </label>
            <label class="catalog-field w-full md:w-[240px]"><span>Lavorazione</span><select name="order_status"><option value="">Tutti gli stati</option>@foreach(\App\Models\EcommerceOrder::ORDER_STATUSES as $value => $label)<option value="{{ $value }}" @selected($workStatus === $value)>{{ $label }}</option>@endforeach</select></label>
            <div class="flex self-end gap-8">
                <button class="catalog-button catalog-button-primary">Cerca</button>
                <a href="{{ route('admin.ecommerce-orders.index') }}" class="catalog-button catalog-button-secondary">Azzera</a>
            </div>
        </form>
    </section>

    <section class="catalog-surface" aria-label="Lista ordini ecommerce">
        <div class="catalog-table-scroll">
            <table class="catalog-table min-w-[940px]">
                <thead>
                    <tr>
                        <th scope="col">Ordine</th>
                        <th scope="col">Cliente</th>
                        <th scope="col" class="text-right">Totale ordine</th>
                        <th scope="col">Pagamento</th>
                        <th scope="col">Lavorazione</th><th scope="col">Ricevuto</th>
                        <th scope="col" class="text-right">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        @php
                            $paid = $order->status === 'paid';
                            $processing = $order->payment_method === 'online' && ! $paid && !in_array($order->status, ['cancelled', 'proforma_pending', 'bank_transfer_pending'], true) && in_array($order->stripe_payment_status, ['processing', 'requires_capture'], true);
                            $failed = $order->payment_method === 'online' && ! $paid && !in_array($order->status, ['cancelled', 'proforma_pending', 'bank_transfer_pending'], true) && ($order->stripe_last_error || $order->stripe_payment_status === 'canceled');
                            $label = match (true) { $order->status === 'cancelled' => 'Annullato', $order->status === 'proforma_pending' => 'Proforma da generare', $order->status === 'bank_transfer_pending' => 'In attesa di bonifico', $paid => 'Pagato', $processing => 'In verifica', $failed => 'Da riprovare', default => 'In attesa di pagamento' };
                            $pieces = collect($order->items)->sum('quantity');
                        @endphp
                        <tr>
                            <td class="max-w-[260px]">
                                <a href="{{ route('admin.ecommerce-orders.show', $order) }}" class="text-14 font-bold text-bullstar hover:underline">{{ $order->reference }}</a>
                                <p class="mt-4 text-11 text-gray">{{ count($order->items) }} {{ count($order->items) === 1 ? 'articolo' : 'articoli' }} · {{ $pieces }} pezzi</p>
                                <p class="mt-4 line-clamp-2 text-11 text-gray">{{ collect($order->items)->pluck('name')->join(', ') }}</p>
                            </td>
                            <td class="max-w-[260px]">
                                <p class="text-14 font-semibold">{{ ($order->customer['billing_type'] ?? 'private') === 'company' ? ($order->customer['billing_name'] ?? $order->customer_name) : $order->customer_name }}</p>
                                <p class="mt-4 break-all text-11 text-gray">{{ $order->email }}</p>
                                @if($order->customer['phone'] ?? null)<p class="mt-4 text-11 text-gray">{{ $order->customer['phone'] }}</p>@endif
                            </td>
                            <td class="catalog-number">
                                <strong>€ {{ number_format($order->gross, 2, ',', '.') }}</strong>
                                <p class="mt-4 text-11 text-gray">IVA e spedizione incluse</p>
                            </td>
                            <td>
                                <span class="catalog-badge {{ $order->status === 'cancelled' ? 'border-red-200! bg-red-50! text-red-700!' : ($paid ? 'catalog-badge-green' : ($failed ? 'border-red-200! bg-red-50! text-red-700!' : ($processing ? 'border-blue-200! bg-blue-50! text-blue-700!' : 'border-amber-200! bg-amber-50! text-amber-800!'))) }}">{{ $label }}</span>
                                @if($order->paid_at)<p class="mt-4 text-11 text-gray">{{ $order->paid_at->format('d/m/Y H:i') }}</p>@endif
                            </td>
                            <td><span class="catalog-badge {{ $order->status === 'cancelled' ? 'border-red-200! bg-red-50! text-red-700!' : '' }}">{{ $order->status === 'cancelled' ? 'Annullato' : (\App\Models\EcommerceOrder::ORDER_STATUSES[$order->order_status] ?? 'Da gestire') }}</span></td>
                            <td class="whitespace-nowrap">
                                <p>{{ $order->created_at->format('d/m/Y') }}</p>
                                <p class="mt-4 text-11 text-gray">{{ $order->created_at->format('H:i') }}</p>
                            </td>
                            <td class="catalog-row-actions"><a href="{{ route('admin.ecommerce-orders.show', $order) }}" class="catalog-button catalog-button-secondary" aria-label="Apri ordine {{ $order->reference }}">Apri scheda ↗</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="catalog-empty"><strong>{{ $search !== '' ? 'Nessun ordine trovato.' : 'Nessun ordine ecommerce ricevuto.' }}</strong><p>{{ $search !== '' ? 'Prova un altro numero ordine, nome o indirizzo e-mail.' : 'Gli ordini inviati dal sito compariranno qui.' }}</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())<div class="border-t border-gray-mid p-16">{{ $orders->links() }}</div>@endif
    </section>
</div>
@endsection
