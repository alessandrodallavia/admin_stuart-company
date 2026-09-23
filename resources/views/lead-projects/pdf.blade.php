@php
    $blue = '#206AE9';
    $black = '#111111';
    $muted = '#687080';
    $line = '#DDE3EC';
    $surface = '#F4F7FB';
    $money = fn ($value) => 'EUR '.number_format((float) $value, 2, ',', '.');
@endphp

<table cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td width="12%" valign="middle"><img src="{{ $logoPath }}" width="44"></td>
        <td width="53%" valign="middle"><span style="font-size: 12px; font-weight: bold; color: {{ $black }};">STUART COMPANY</span><br><span style="font-size: 7.5px; color: {{ $muted }};">Abbigliamento personalizzato</span></td>
        <td width="35%" align="right" valign="middle"><span style="font-size: 7px; color: {{ $muted }};">PROPOSTA</span><br><span style="font-size: 9px; font-weight: bold; color: {{ $blue }};">{{ e($proposal->proposal_number) }}</span></td>
    </tr>
</table>

<div style="height: 8px;"></div>
<table cellpadding="10" cellspacing="0" border="0" width="100%">
    <tr>
        <td width="72%" style="background-color: {{ $black }}; color: #ffffff;"><span style="font-size: 7px; color: #AEB4BE;">IL TUO PROGETTO</span><br><span style="font-size: 17px; font-weight: bold;">Pronto per la conferma</span><br><span style="font-size: 8px; color: #D6DAE0;">Mockup e dettagli verificati dal team Stuart</span></td>
        <td width="28%" align="center" valign="middle" style="background-color: {{ $blue }}; color: #ffffff;"><span style="font-size: 7px;">TOTALE IVA INCLUSA</span><br><span style="font-size: 15px; font-weight: bold;">{{ $money($amount) }}</span></td>
    </tr>
</table>

<div style="height: 10px;"></div>
@if (count($mockups))
    <table cellpadding="6" cellspacing="5" border="0" width="100%">
        <tr>
            @foreach ($mockups as $label => $path)
                <td width="{{ 100 / count($mockups) }}%" align="center" valign="middle" style="border: 1px solid {{ $line }}; background-color: {{ $surface }};"><span style="color: {{ $blue }}; font-size: 7px; font-weight: bold;">VISTA {{ strtoupper($label) }}</span><br><span style="color: {{ $muted }}; font-size: 6.5px;">Mockup definitivo</span><br><div style="height: 3px;"></div><img src="{{ $path }}" style="height: 43mm; width: auto;"></td>
            @endforeach
        </tr>
    </table>
@else
    <table cellpadding="12" cellspacing="0" border="0" width="100%"><tr><td align="center" style="border: 1px solid {{ $line }}; background-color: {{ $surface }}; color: {{ $muted }}; font-size: 8px;">Lo spazio per i mockup fronte e retro sarà compilato con le immagini caricate dall'admin.</td></tr></table>
@endif

<div style="height: 10px;"></div>
<table cellpadding="0" cellspacing="0" border="0" width="100%"><tr><td width="70%" style="font-size: 11px; font-weight: bold; color: {{ $black }};">Dettagli del progetto</td><td width="30%" align="right" style="font-size: 7px; color: {{ $muted }};">Prezzi espressi in euro</td></tr></table>
<div style="height: 4px;"></div>
<table cellpadding="5" cellspacing="0" border="1" width="100%" style="border-color: {{ $line }}; font-size: 7.5px; color: {{ $black }};">
    <tr style="background-color: {{ $surface }}; color: {{ $muted }}; font-weight: bold;"><td width="42%">PRODOTTO E PERSONALIZZAZIONE</td><td width="12%" align="center">Q.TÀ</td><td width="21%" align="right">PREZZO UNITARIO</td><td width="25%" align="right">TOTALE</td></tr>
    @forelse ($sheet?->items ?? [] as $item)
        <tr>
            <td width="42%"><span style="font-weight: bold;">{{ e($item->configuration_name ?: $item->product_name) }}</span>@if (collect($item->colors)->filter()->isNotEmpty())<br><span style="color: {{ $muted }};">Colore: {{ e(collect($item->colors)->filter()->join(', ')) }}</span>@endif @if ($item->prints->isNotEmpty())<br><span style="color: {{ $muted }};">Stampe: {{ e($item->prints->pluck('print_name')->filter()->join(', ')) }}</span>@endif @if ($item->notes)<br><span style="color: {{ $muted }};">{{ e($item->notes) }}</span>@endif</td>
            <td width="12%" align="center">{{ number_format((float) $item->quantity, 0, ',', '.') }}</td>
            <td width="21%" align="right">{{ $money($item->final_unit_price) }}</td>
            <td width="25%" align="right"><span style="font-weight: bold;">{{ $money($item->revenue_total) }}</span></td>
        </tr>
    @empty
        <tr><td width="42%"><span style="font-weight: bold;">{{ e($lead->product ?: $lead->calculator_model ?: 'Progetto personalizzato') }}</span>@if ($lead->live_mockup_color)<br><span style="color: {{ $muted }};">Colore: {{ e($lead->live_mockup_color) }}</span>@endif</td><td width="12%" align="center">{{ number_format((float) ($lead->quantity ?: $lead->calculator_quantity ?: 1), 0, ',', '.') }}</td><td width="21%" align="right">-</td><td width="25%" align="right"><span style="font-weight: bold;">{{ $money($subtotal) }}</span></td></tr>
    @endforelse
</table>

<div style="height: 7px;"></div>
<table cellpadding="4" cellspacing="0" border="0" width="100%" style="font-size: 8px; color: {{ $black }};">
    <tr><td width="62%"></td><td width="23%" style="color: {{ $muted }};">Imponibile</td><td width="15%" align="right">{{ $money($subtotal) }}</td></tr>
    <tr><td width="62%"></td><td width="23%" style="color: {{ $muted }};">IVA {{ $vatRate }}%</td><td width="15%" align="right">{{ $money($vat) }}</td></tr>
    <tr style="font-size: 10px; font-weight: bold; color: {{ $blue }};"><td width="62%"></td><td width="23%">Totale finale</td><td width="15%" align="right">{{ $money($amount) }}</td></tr>
</table>

<div style="height: 8px;"></div>
<table cellpadding="7" cellspacing="3" border="0" width="100%" style="font-size: 7.5px;"><tr><td width="50%" style="border-left: 2px solid {{ $blue }}; background-color: {{ $surface }};"><span style="font-weight: bold; color: {{ $black }};">TEMPI DI CONSEGNA</span><br><span style="color: {{ $muted }};">Spedizione entro 6 giorni lavorativi dalla conferma, salvo accordi specifici.</span></td><td width="50%" style="border-left: 2px solid {{ $blue }}; background-color: {{ $surface }};"><span style="font-weight: bold; color: {{ $black }};">ASSISTENZA DIRETTA</span><br><span style="color: {{ $muted }};">Il team Stuart resta a disposizione prima della conferma.</span></td></tr></table>

<div style="height: 9px;"></div>
@if ($lead->payment_link)
    <table cellpadding="9" cellspacing="0" border="0" width="100%"><tr><td align="center" style="background-color: {{ $blue }}; color: #ffffff; font-size: 10px; font-weight: bold;"><a href="{{ $lead->payment_link }}" style="color: #ffffff; text-decoration: none;">CONFERMA L'ORDINE E PROCEDI AL PAGAMENTO</a></td></tr></table>
    <div style="height: 3px;"></div><div style="font-size: 6.5px; color: {{ $muted }}; text-align: center;">Pagamento sicuro tramite lo stesso link inviato su WhatsApp.</div>
@else
    <table cellpadding="8" cellspacing="0" border="1" width="100%" style="border-color: {{ $blue }};"><tr><td align="center" style="color: {{ $black }}; font-size: 8px;"><span style="font-weight: bold;">Pagamento in preparazione</span><br><span style="color: {{ $muted }};">Il link sicuro sarà inviato direttamente su WhatsApp.</span></td></tr></table>
@endif
