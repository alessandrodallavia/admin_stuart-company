<p>Gentile Cliente,</p>
<p>abbiamo ricevuto il tuo ordine nr. {{ $order->reference }}. Grazie per il tuo acquisto.</p>
<p>Riepilogo ordine:<br>
@foreach($order->items as $item)
{{ $item['name'] }} · {{ $item['quantity'] }} pezzi · € {{ number_format($item['gross'], 2, ',', '.') }} IVA inclusa<br>
@endforeach
Spedizione: {{ $order->shipping_gross > 0 ? '€ '.number_format($order->shipping_gross, 2, ',', '.').' IVA inclusa' : 'gratuita' }}<br>
Totale ordine: € {{ number_format($order->gross, 2, ',', '.') }} IVA inclusa.</p>
@if($order->status === 'paid')
<p>Il pagamento è stato ricevuto.</p>
@elseif($order->payment_method === 'bank')
<p>Hai scelto il bonifico bancario. Riceverai la proforma con l’importo e le coordinate per effettuare il pagamento.</p>
@endif
@if(collect($order->items)->contains('mode', 'personalized'))
<p>Prima della produzione riceverai la bozza grafica da approvare.</p>
@endif
<p>Cordiali saluti</p>
