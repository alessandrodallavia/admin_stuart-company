<?php

namespace App\Services;

use App\Models\Lead;
use App\Support\CrmHoodieCatalog;
use App\Support\CrmPositionPricing;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class HoodieQuoteCalculator
{
    private array $catalogs = [];

    public function catalog(string $family = 'hoodie'): array
    {
        if (! in_array($family, ['hoodie', 'tshirt'], true)) {
            throw new RuntimeException('Tipo di prodotto non valido.');
        }
        if (isset($this->catalogs[$family])) {
            return $this->catalogs[$family];
        }
        $path = rtrim(config('landing_quote.path'), '/');
        if (! is_file($path.'/config/'.$family.'_calculator.php') || ! is_file($path.'/app/Support/PrintConfiguration.php') || ! is_file($path.'/app/Support/CrmHoodieCatalog.php')) {
            throw new RuntimeException('Listino '.($family === 'hoodie' ? 'felpe' : 'T-shirt').' non disponibile. Verificare il collegamento alla landing.');
        }
        require_once $path.'/app/Support/PrintConfiguration.php';

        require_once $path.'/app/Support/CrmHoodieCatalog.php';
        try {
            return $this->catalogs[$family] = CrmHoodieCatalog::build(require $path.'/config/'.$family.'_calculator.php', false);
        } catch (\Throwable $e) {
            throw new RuntimeException('Catalogo CRM '.($family === 'hoodie' ? 'felpe' : 'T-shirt').' non disponibile. Verificare migrazioni e associazioni.', 0, $e);
        }
    }

    public function defaults(?Lead $lead, string $family = 'hoodie'): array
    {
        $catalog = $this->catalog($family);
        $defaults = ['model' => 'hood', 'quantity' => 20, 'personalization' => 'heart_logo_back_print', 'qualities' => ['economic', 'basic'], 'positions' => []];
        if ($family === 'tshirt') {
            $defaults['model'] = 'tshirt';
        }
        if (! isset($catalog['personalizations'][$defaults['personalization']])) {
            $defaults['personalization'] = array_key_first($catalog['personalizations']) ?? '';
        }
        if (! $lead || ($family === 'hoodie' && ! $lead->isHoodieRequest()) || ($family === 'tshirt' && ($lead->isHoodieRequest() || ! $lead->calculator_used))) {
            return $defaults;
        }
        $savedModel = mb_strtolower((string) $lead->calculator_model);
        foreach ($family === 'hoodie' ? $catalog['models'] : [] as $key => $label) {
            if (str_contains($savedModel, mb_strtolower($label)) || ($key === 'hood' && str_contains($savedModel, 'cappuccio'))) {
                $defaults['model'] = $key;
                break;
            }
        }
        foreach ($catalog['qualities'] ?? $catalog['models'] as $key => $label) {
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

        $saved = $lead->calculator_crm_snapshot['positions'] ?? null;
        try {
            $defaults['positions'] = $saved ?? CrmPositionPricing::decode($catalog, (string) $lead->calculator_personalization);
        } catch (\InvalidArgumentException) {
            $defaults['positions'] = [];
        }

        return $defaults;
    }

    public function generate(array $selection): array
    {
        $family = $selection['family'] ?? 'hoodie';
        $c = $this->catalog($family);
        $qualities = $c['qualities'] ?? $c['models'];
        $model = $selection['model'];
        $print = isset($selection['positions']) ? CrmPositionPricing::encode(array_filter($selection['positions'])) : ($selection['personalization'] ?? 'positions:');
        $quantity = (int) $selection['quantity'];
        if ($quantity < $c['minimum_quantity'] || $quantity > $c['custom_quote_above_quantity']) {
            throw ValidationException::withMessages(['quantity' => 'Inserire una quantità da '.$c['minimum_quantity'].' a '.number_format($c['custom_quote_above_quantity'], 0, ',', '.').' pezzi. Oltre questa soglia serve una quotazione dedicata.']);
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
            try {
                $quote = CrmHoodieCatalog::quote($c, $family === 'tshirt' ? $quality : $model, $quality, $print, $quantity);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages([str_contains($e->getMessage(), 'Prodotto') ? 'qualities' : 'personalization' => $e->getMessage()]);
            } catch (RuntimeException $e) {
                throw ValidationException::withMessages(['qualities' => $e->getMessage()]);
            }
            $unitCents = $quote['unit'];
            $subtotal = $quote['subtotal'];
            $vat = $quote['vat'];
            $shipping = $quote['shipping'];
            $rows[] = ['setup_total' => $quote['setup_total'] ?? 0, 'setup_count' => $quote['setup_count'] ?? 0, 'items_subtotal' => $quote['items_subtotal'] ?? $subtotal, 'quality' => $quality, 'label' => $qualities[$quality], 'unit' => $unitCents, 'subtotal' => $subtotal, 'vat' => $vat, 'shipping' => $shipping, 'total' => $subtotal + $vat + $shipping];
        }
        $shippingDays = $quote['shipping_days'];
        $money = static fn (int $cents): string => 'EUR '.number_format($cents / 100, 2, ',', '.');
        $lines = ['Perfetto, grazie!', '', 'Per il suo progetto le propongo queste soluzioni:', '',
            'Modello: '.($family === 'tshirt' ? 'T-shirt' : $c['models'][$model]), 'Quantità: '.$quantity.' pezzi',
            'Personalizzazioni: '.$quote['selection_label'], ''];
        foreach ($rows as $row) {
            $product = $c['crm_products'][$family === 'tshirt' ? $row['quality'] : $model][$row['quality']];
            $lines[] = $row['label'].' · '.$product['code'].' · '.$product['name'];
            $lines[] = $row['label'].' - '.$money($row['unit']).' / pz + IVA';
            $lines[] = 'Totale '.$quantity.' pz: '.$money($row['items_subtotal']).' + IVA';
            if ($row['setup_count']) {
                $lines[] = 'Impianti ricamo: '.$row['setup_count'].' · costo unico totale '.$money($row['setup_total']).' + IVA';
                $lines[] = 'Imponibile ordine: '.$money($row['subtotal']);
            }
            $lines[] = 'IVA '.$c['vat_rate'].'%: '.$money($row['vat']);
            $lines[] = 'Spedizione: '.($row['shipping'] === 0 ? 'gratuita' : $money($row['shipping']).' IVA inclusa');
            $lines[] = 'Totale ordine, IVA e spedizione incluse: '.$money($row['total']);
            $lines[] = '';
        }
        $lines[] = 'La spedizione è gratuita da '.$money((int) round($c['free_shipping_threshold_inc_vat'] * 100)).' IVA inclusa di prodotti; sotto questa soglia costa '.$money((int) round($c['shipping_cost'] * 100)).' IVA inclusa.';
        $lines[] = '';
        $lines[] = "Tempi: spedizione entro {$shippingDays} giorni lavorativi dalla conferma dell'ordine e della grafica, con consegna normalmente in {$c['courier_delivery']} lavorative dalla spedizione.";
        $lines[] = '';
        $lines[] = 'Per confrontare altre quantità o configurazioni:';
        $lines[] = 'Calcola il tuo prezzo -> https://stuart-company.com/'.($family === 'hoodie' ? 'felpe-personalizzate' : '').'#calcolatore-prezzo';
        $lines[] = '';
        $lines[] = 'Se il prezzo è in linea con le sue aspettative, preparerò gratuitamente il mockup '.($family === 'hoodie' ? 'della felpa scelta' : 'della T-shirt scelta').' e le invierò la scheda prodotto, così potrà valutarne caratteristiche, colori e taglie senza alcun impegno.';

        return ['family' => $family, 'message' => implode("\n", $lines), 'quotes' => $rows, 'band' => $bandKey];
    }
}
