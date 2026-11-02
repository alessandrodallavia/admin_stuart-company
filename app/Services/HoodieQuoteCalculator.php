<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class HoodieQuoteCalculator
{
    private ?array $catalog = null;

    public function catalog(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }
        $path = rtrim(config('landing_quote.path'), '/');
        if (! is_file($path.'/config/hoodie_calculator.php') || ! is_file($path.'/app/Support/PrintConfiguration.php')) {
            throw new RuntimeException('Listino felpe non disponibile. Verificare il collegamento alla landing.');
        }
        require_once $path.'/app/Support/PrintConfiguration.php';

        return $this->catalog = require $path.'/config/hoodie_calculator.php';
    }

    public function defaults(?Lead $lead): array
    {
        $catalog = $this->catalog();
        $defaults = ['model' => 'hood', 'quantity' => 20, 'personalization' => 'heart_logo_back_print', 'qualities' => ['economic', 'basic']];
        if (! $lead?->isHoodieRequest()) {
            return $defaults;
        }
        $savedModel = mb_strtolower((string) $lead->calculator_model);
        foreach ($catalog['models'] as $key => $label) {
            if (str_contains($savedModel, mb_strtolower($label)) || ($key === 'hood' && str_contains($savedModel, 'cappuccio'))) {
                $defaults['model'] = $key;
                break;
            }
        }
        foreach ($catalog['qualities'] as $key => $label) {
            if (str_contains($savedModel, mb_strtolower($label))) {
                $defaults['qualities'] = [$key];
                break;
            }
        }
        if ($lead->calculator_quantity >= $catalog['minimum_quantity']) {
            $defaults['quantity'] = (int) $lead->calculator_quantity;
        }
        if (isset($catalog['personalizations'][$lead->calculator_personalization])) {
            $defaults['personalization'] = $lead->calculator_personalization;
        }

        return $defaults;
    }

    public function generate(array $selection): array
    {
        $c = $this->catalog();
        $model = $selection['model'];
        $print = $selection['personalization'];
        $quantity = (int) $selection['quantity'];
        if ($quantity < $c['minimum_quantity'] || $quantity > $c['custom_quote_above_quantity']) {
            throw ValidationException::withMessages(['quantity' => 'Inserire una quantità da 10 a 3.000 pezzi. Oltre questa soglia serve una quotazione dedicata.']);
        }
        $allowed = $c['personalization_models'][$print] ?? array_keys($c['models']);
        if (! in_array($model, $allowed, true)) {
            throw ValidationException::withMessages(['personalization' => 'Questa stampa non è disponibile per il modello selezionato.']);
        }
        $bandKey = null;
        foreach ($c['quantity_bands'] as $key => $band) {
            if ($quantity >= $band['min'] && ($band['max'] === null || $quantity <= $band['max'])) {
                $bandKey = $key;
                break;
            }
        }
        $rows = [];
        foreach ($selection['qualities'] as $quality) {
            $unit = $c['quality_prices'][$quality][$model][$print][$bandKey] ?? null;
            if (! is_numeric($unit) || $unit <= 0) {
                throw ValidationException::withMessages(['qualities' => 'Prezzo non disponibile per una delle qualità selezionate.']);
            }
            // Match landing: multiply the rounded unit price, then calculate VAT.
            $unitCents = (int) round($unit * 100);
            $subtotal = $unitCents * $quantity;
            $vat = (int) round($subtotal * $c['vat_rate'] / 100);
            $shipping = $subtotal + $vat < (int) round($c['free_shipping_threshold_inc_vat'] * 100)
                ? (int) round($c['shipping_cost'] * 100) : 0;
            $rows[] = ['quality' => $quality, 'label' => $c['qualities'][$quality], 'unit' => $unitCents, 'subtotal' => $subtotal, 'vat' => $vat, 'shipping' => $shipping, 'total' => $subtotal + $vat + $shipping];
        }
        $money = static fn (int $cents): string => 'EUR '.number_format($cents / 100, 2, ',', '.');
        $lines = ['Perfetto, grazie!', '', 'Per il suo progetto le propongo queste soluzioni:', '',
            'Modello: '.$c['models'][$model], 'Quantità: '.$quantity.' pezzi',
            'Personalizzazione inclusa: '.$c['personalizations'][$print], ''];
        foreach ($rows as $row) {
            $lines[] = $row['label'].' - '.$money($row['unit']).' / pz + IVA';
            $lines[] = 'Totale '.$quantity.' pz: '.$money($row['subtotal']).' + IVA';
            $lines[] = 'IVA '.$c['vat_rate'].'%: '.$money($row['vat']);
            $lines[] = 'Spedizione: '.($row['shipping'] === 0 ? 'gratuita' : $money($row['shipping']).' IVA inclusa');
            $lines[] = 'Totale ordine, IVA e spedizione incluse: '.$money($row['total']);
            $lines[] = '';
        }
        $lines[] = 'La spedizione è gratuita da '.$money((int) round($c['free_shipping_threshold_inc_vat'] * 100)).' IVA inclusa di prodotti; sotto questa soglia costa '.$money((int) round($c['shipping_cost'] * 100)).' IVA inclusa.';
        $lines[] = '';
        $lines[] = "Tempi: spedizione entro {$c['shipping_days']} giorni lavorativi dalla conferma dell'ordine e della grafica, con consegna normalmente in {$c['courier_delivery']} lavorative dalla spedizione.";
        $lines[] = '';
        $lines[] = 'Per confrontare altre quantità o configurazioni:';
        $lines[] = 'Calcola il tuo prezzo -> https://stuart-company.com/felpe-personalizzate#calcolatore-prezzo';
        $lines[] = '';
        $lines[] = 'Se il prezzo è in linea con le sue aspettative, preparerò gratuitamente il mockup della felpa scelta e le invierò la scheda prodotto, così potrà valutarne caratteristiche, colori e taglie senza alcun impegno.';

        return ['message' => implode("\n", $lines), 'quotes' => $rows, 'band' => $bandKey];
    }
}
