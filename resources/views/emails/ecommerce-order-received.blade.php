<h1>Abbiamo ricevuto il tuo ordine {{ $order->reference }}</h1>
<p>Ciao {{ $order->customer_name }}, grazie per il tuo ordine.</p>
<ul>@foreach($order->items as $item)<li>{{ $item['name'] }} · {{ $item['quantity'] }} pezzi · € {{ number_format($item['gross'], 2, ',', '.') }} IVA inclusa</li>@endforeach</ul>
<p>Totale ordine: € {{ number_format($order->gross, 2, ',', '.') }} IVA inclusa.</p>
<p>{{ $order->status === 'paid' ? 'Pagamento ricevuto.' : 'Pagamento non ancora confermato.' }} @if(collect($order->items)->contains('mode', 'personalized'))Prima della produzione riceverai la bozza grafica da approvare.@endif</p>
@if($order->payment_method === 'bank' && $order->status !== 'paid')<p>Hai scelto il bonifico bancario: riceverai la proforma con le coordinate per il pagamento.</p>@endif
<p>Il team Stuart</p>
