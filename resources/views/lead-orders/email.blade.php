<p>Ciao Alessandro e Daniele,</p>
<p>è disponibile il materiale completo per l’ordine <strong>{{ $dispatch->order_name }}</strong>, relativo al lead <strong>{{ $lead->name ?: '#'.$lead->id }}</strong>.</p>

<ul>
    @foreach($sheet->items as $item)
        <li>
            <strong>{{ $item->configuration_name ?: $item->product_name }}</strong>:
            {{ number_format((float)$item->quantity, 2, ',', '.') }} pz,
            colori {{ collect($item->colors)->join(', ') ?: 'non indicati' }},
            prezzo finale € {{ number_format((float)$item->final_unit_price, 2, ',', '.') }}/pz.
        </li>
    @endforeach
</ul>

<p>
    Totale vendita: <strong>€ {{ number_format((float)$sheet->revenue_total, 2, ',', '.') }}</strong><br>
    Costo: <strong>€ {{ number_format((float)$sheet->cost_total, 2, ',', '.') }}</strong><br>
    Margine: <strong>€ {{ number_format((float)$sheet->margin_total, 2, ',', '.') }}</strong>
</p>
<p>Lo ZIP allegato contiene il riepilogo PDF e, per ogni prodotto, colori, note e file grafici.</p>
