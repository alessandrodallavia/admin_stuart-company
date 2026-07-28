<h1 style="font-size:20px;">Ordine Lead — {{ $dispatch->order_name }}</h1>
<p>
    <strong>Cliente:</strong> {{ $lead->name ?: 'Non indicato' }}<br>
    <strong>Email:</strong> {{ $lead->email ?: 'Non indicata' }}<br>
    <strong>Telefono:</strong> {{ $lead->phone ?: 'Non indicato' }}<br>
    <strong>Ordine CRM:</strong> {{ $sheet->order_number }} — {{ $sheet->name }}<br>
    <strong>Versione:</strong> {{ $dispatch->version }}<br>
    <strong>Preparato il:</strong> {{ now()->format('d/m/Y H:i') }}
</p>

@foreach($sheet->items as $item)
    <h2 style="font-size:14px; margin-top:14px;">{{ $item->configuration_name ?: $item->product_name }}</h2>
    <table cellpadding="4" border="1">
        <tr><td width="32%"><strong>Codice / prodotto</strong></td><td width="68%">{{ $item->product_code }} — {{ $item->product_name }}</td></tr>
        <tr><td><strong>Quantità</strong></td><td>{{ number_format((float)$item->quantity, 2, ',', '.') }}</td></tr>
        <tr><td><strong>Colori</strong></td><td>{{ collect($item->colors)->join(', ') ?: 'Non indicati' }}</td></tr>
        <tr><td><strong>Lavorazioni</strong></td><td>{{ $item->prints->pluck('print_name')->join(', ') ?: 'Nessuna' }}</td></tr>
        <tr><td><strong>Prezzo finale unitario</strong></td><td>€ {{ number_format((float)$item->final_unit_price, 2, ',', '.') }}</td></tr>
        <tr><td><strong>Totale vendita</strong></td><td>€ {{ number_format((float)$item->revenue_total, 2, ',', '.') }}</td></tr>
        <tr><td><strong>Costo totale</strong></td><td>€ {{ number_format((float)$item->cost_total, 2, ',', '.') }}</td></tr>
        <tr><td><strong>Margine</strong></td><td>€ {{ number_format((float)$item->margin_total, 2, ',', '.') }}</td></tr>
        <tr><td><strong>Note</strong></td><td>{!! nl2br(e($item->notes ?: 'Nessuna nota')) !!}</td></tr>
        <tr><td><strong>Grafiche</strong></td><td>{{ $item->attachments->pluck('filename')->join(', ') ?: 'Nessun file' }}</td></tr>
    </table>
@endforeach

<h2 style="font-size:14px; margin-top:16px;">Totali ordine</h2>
<p>
    Prodotti: <strong>€ {{ number_format((float)$sheet->product_revenue_total, 2, ',', '.') }}</strong><br>
    Sconto: <strong>- € {{ number_format((float)$sheet->discount_amount, 2, ',', '.') }}</strong><br>
    Arrotondamento: <strong>€ {{ number_format((float)$sheet->rounding_adjustment, 2, ',', '.') }}</strong><br>
    Spedizione cliente: <strong>€ {{ number_format((float)$sheet->shipping_charge, 2, ',', '.') }}</strong><br>
    Costo spedizione: <strong>€ {{ number_format((float)$sheet->shipping_cost, 2, ',', '.') }}</strong><br>
    Totale cliente: <strong>€ {{ number_format((float)$sheet->revenue_total, 2, ',', '.') }}</strong><br>
    Costo: <strong>€ {{ number_format((float)$sheet->cost_total, 2, ',', '.') }}</strong><br>
    Margine: <strong>€ {{ number_format((float)$sheet->margin_total, 2, ',', '.') }} ({{ number_format((float)$sheet->margin_percentage, 2, ',', '.') }}%)</strong>
</p>
