@extends('admin.layouts.app')

@section('title', 'Ordini checkout')
@section('active_nav', 'checkout-orders')

@section('content')
<div class="catalog-workspace w-full">
    <header class="catalog-page-header">
        <div>
            <p class="catalog-eyebrow">Operazioni</p>
            <h1>Ordini checkout</h1>
            <p>Checkout completati dai clienti, indipendentemente dal metodo di pagamento.</p>
        </div>
        <span class="catalog-tax-note">{{ $orders->total() }} ordini</span>
    </header>

    <section class="catalog-surface mb-16">
        <form method="GET" class="catalog-toolbar checkout-orders-toolbar">
            <label class="catalog-field flex-1">
                <span>Cerca</span>
                <input name="q" value="{{ $search }}" placeholder="Ordine, cliente, e-mail o telefono">
            </label>
            <label class="catalog-field w-full md:w-[240px]">
                <span>Stato</span>
                <select name="status">
                    <option value="">Tutti gli stati</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex self-end gap-8">
                <button class="catalog-button catalog-button-primary">Filtra</button>
                <a href="{{ route('admin.checkout-orders.index') }}" class="catalog-button catalog-button-secondary">Azzera</a>
            </div>
        </form>
    </section>

    <div class="catalog-surface">
        <div class="catalog-table-scroll">
            <table class="catalog-table checkout-orders-table">
                <thead>
                    <tr>
                        <th class="px-12 py-8">Ordine</th>
                        <th class="px-12 py-8">Cliente</th>
                        <th class="px-12 py-8">Pagamento</th>
                        <th class="px-12 py-8 text-right">Importo</th>
                        <th class="px-12 py-8">Stato</th>
                        <th class="px-12 py-8">Ricevuto</th>
                        <th class="px-12 py-8 text-right">Azioni</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-mid">
                    @forelse($orders as $order)
                        @php
                            $customer = $order->billing_company_name ?: trim($order->contact_first_name.' '.$order->contact_last_name) ?: ($order->lead?->name ?: 'Cliente');
                            $statusLabel = $statuses[$order->order_status] ?? ucfirst($order->order_status);
                        @endphp
                        <tr>
                            <td>
                                <p class="text-14 font-black">{{ $order->order_number }}</p>
                                <p class="mt-3 text-11 font-semibold text-gray">{{ collect($order->items)->pluck('name')->filter()->join(', ') }}</p>
                            </td>
                            <td>
                                <p class="text-14 font-black">{{ $customer }}</p>
                                <p class="mt-3 text-11 font-semibold text-gray"><span class="block break-all">{{ $order->contact_email }}</span><span class="block">{{ $order->contact_phone }}</span></p>
                            </td>
                            <td>
                                {{ $order->payment_label }}<br><span class="text-11 text-gray">{{ $order->payment_method === 'bank' ? 'Bonifico bancario' : 'Pagamento online' }}</span>
                            </td>
                            <td class="catalog-number"><strong>€ {{ number_format((float) $order->total_amount, 2, ',', '.') }}</strong></td>
                            <td><span class="catalog-badge {{ $order->order_status === 'paid' ? 'catalog-badge-green' : '' }}">{{ $statusLabel }}</span></td>
                            <td class="whitespace-nowrap">{{ $order->details_completed_at?->format('d/m/Y H:i') }}</td>
                            <td class="catalog-row-actions">
                                <a href="{{ route('admin.checkout-orders.show', $order) }}" class="catalog-button catalog-button-secondary">Apri scheda ↗</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="catalog-empty">Nessun checkout completato trovato.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())<div class="border-t border-gray-mid p-10">{{ $orders->links() }}</div>@endif
    </div>
</div>
@endsection
