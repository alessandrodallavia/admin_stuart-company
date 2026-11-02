<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConvertCatalogPricesToNet extends Command
{
    protected $signature = 'catalog:prices-to-net {--vat=22 : Aliquota IVA da scorporare} {--apply : Applica realmente la conversione}';

    protected $description = 'Scorpora l’IVA da prezzi e costi del catalogo; senza --apply mostra solo l’anteprima.';

    public function handle(): int
    {
        $vatRate = (float) $this->option('vat');

        if ($vatRate <= 0 || $vatRate >= 100) {
            $this->error('L’aliquota IVA deve essere maggiore di 0 e minore di 100.');

            return self::FAILURE;
        }

        if (DB::table('catalog_price_conversions')->exists()) {
            $this->error('Conversione già applicata: operazione interrotta per evitare un secondo scorporo IVA.');

            return self::FAILURE;
        }

        $productPrices = DB::table('crm_product_price_tiers')->where('unit_price', '>', 0)->get(['id', 'unit_price']);
        $printPrices = DB::table('crm_print_price_tiers')->where('unit_price', '>', 0)->get(['id', 'unit_price']);
        $productBaseCosts = DB::table('crm_products')->where('unit_cost', '>', 0)->get(['id', 'unit_cost']);
        $productTierCosts = DB::table('crm_product_price_tiers')->where('unit_cost', '>', 0)->get(['id', 'unit_cost']);
        $printCosts = DB::table('crm_print_price_tiers')->where('unit_cost', '>', 0)->get(['id', 'unit_cost']);
        $productCostCount = $productBaseCosts->count() + $productTierCosts->count();

        $this->info(sprintf(
            'Anteprima IVA %.2f%%: %d prezzi prodotto, %d prezzi lavorazione, %d costi prodotto e %d costi lavorazione.',
            $vatRate,
            $productPrices->count(),
            $printPrices->count(),
            $productCostCount,
            $printCosts->count(),
        ));

        $examples = $productPrices->take(5)->map(fn ($row) => [
            'Tipo' => 'Prodotto',
            'ID' => $row->id,
            'Lordo' => number_format((float) $row->unit_price, 4, ',', '.'),
            'Netto' => number_format($this->netPrice((float) $row->unit_price, $vatRate), 2, ',', '.'),
        ])->merge($printPrices->take(5)->map(fn ($row) => [
            'Tipo' => 'Lavorazione',
            'ID' => $row->id,
            'Lordo' => number_format((float) $row->unit_price, 4, ',', '.'),
            'Netto' => number_format($this->netPrice((float) $row->unit_price, $vatRate), 2, ',', '.'),
        ]));

        if ($examples->isNotEmpty()) {
            $this->table(['Tipo', 'ID', 'Lordo', 'Netto'], $examples->all());
        }

        if (! $this->option('apply')) {
            $this->warn('Nessun dato modificato. Ripeti con --apply per applicare la conversione.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($productPrices, $printPrices, $productBaseCosts, $productTierCosts, $printCosts, $productCostCount, $vatRate) {
            foreach ($productPrices as $row) {
                DB::table('crm_product_price_tiers')->where('id', $row->id)->update([
                    'unit_price' => $this->netPrice((float) $row->unit_price, $vatRate),
                    'updated_at' => now(),
                ]);
            }

            foreach ($printPrices as $row) {
                DB::table('crm_print_price_tiers')->where('id', $row->id)->update([
                    'unit_price' => $this->netPrice((float) $row->unit_price, $vatRate),
                    'updated_at' => now(),
                ]);
            }

            foreach ($productBaseCosts as $row) {
                DB::table('crm_products')->where('id', $row->id)->update([
                    'unit_cost' => $this->netPrice((float) $row->unit_cost, $vatRate),
                    'updated_at' => now(),
                ]);
            }

            foreach ($productTierCosts as $row) {
                DB::table('crm_product_price_tiers')->where('id', $row->id)->update([
                    'unit_cost' => $this->netPrice((float) $row->unit_cost, $vatRate),
                    'updated_at' => now(),
                ]);
            }

            foreach ($printCosts as $row) {
                DB::table('crm_print_price_tiers')->where('id', $row->id)->update([
                    'unit_cost' => $this->netPrice((float) $row->unit_cost, $vatRate),
                    'updated_at' => now(),
                ]);
            }

            DB::table('catalog_price_conversions')->insert([
                'vat_rate' => $vatRate,
                'product_prices_converted' => $productPrices->count(),
                'print_prices_converted' => $printPrices->count(),
                'product_costs_converted' => $productCostCount,
                'print_costs_converted' => $printCosts->count(),
                'applied_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->info('Conversione completata. Prezzi e costi del catalogo sono ora IVA esclusa.');

        return self::SUCCESS;
    }

    private function netPrice(float $gross, float $vatRate): float
    {
        return round(round($gross / (1 + $vatRate / 100), 3, PHP_ROUND_HALF_UP), 2, PHP_ROUND_HALF_UP);
    }
}
