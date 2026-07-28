<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('crm_product_price_tiers', 'unit_cost')) {
            Schema::table('crm_product_price_tiers', function (Blueprint $table) {
                $table->decimal('unit_cost', 12, 4)->nullable()->after('max_quantity');
            });
        }

        DB::table('crm_product_price_tiers')->orderBy('id')->eachById(function ($tier) {
            DB::table('crm_product_price_tiers')->where('id', $tier->id)->update([
                'unit_cost' => DB::table('crm_products')->where('id', $tier->crm_product_id)->value('unit_cost'),
            ]);
        });

        // MySQL usa l'indice univoco esistente anche per la foreign key lead_id:
        // creiamo prima quello normale, altrimenti il DROP INDEX viene rifiutato.
        if (! $this->hasIndex('lead_sales_sheets', 'lead_sales_sheets_lead_id_index')) {
            Schema::table('lead_sales_sheets', function (Blueprint $table) {
                $table->index('lead_id');
            });
        }

        if ($this->hasIndex('lead_sales_sheets', 'lead_sales_sheets_lead_id_unique')) {
            Schema::table('lead_sales_sheets', function (Blueprint $table) {
                $table->dropUnique(['lead_id']);
            });
        }

        Schema::table('lead_sales_sheets', function (Blueprint $table) {
            $table->string('order_number')->nullable()->after('lead_id');
            $table->string('name')->nullable()->after('order_number');
            $table->string('status', 30)->default('draft')->after('name');
            $table->string('discount_type', 20)->nullable()->after('shipping_cost');
            $table->decimal('discount_value', 12, 2)->default(0)->after('discount_type');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('discount_value');
            $table->decimal('rounding_adjustment', 12, 2)->default(0)->after('discount_amount');
            $table->unique('order_number');
        });

        DB::table('lead_sales_sheets')->orderBy('id')->eachById(function ($sheet) {
            DB::table('lead_sales_sheets')->where('id', $sheet->id)->update([
                'order_number' => 'ORD-'.str_pad((string) $sheet->id, 6, '0', STR_PAD_LEFT),
                'name' => 'Ordine iniziale',
            ]);
        });

        Schema::table('lead_sales_items', function (Blueprint $table) {
            $table->uuid('pricing_group_uuid')->nullable()->after('configuration_name');
            $table->string('pricing_group_name')->nullable()->after('pricing_group_uuid');
            $table->index('pricing_group_uuid');
        });

        DB::table('lead_sales_items')->orderBy('id')->eachById(function ($item) {
            DB::table('lead_sales_items')->where('id', $item->id)->update([
                'pricing_group_uuid' => (string) Str::uuid(),
                'pricing_group_name' => $item->configuration_name ?: $item->product_name,
            ]);
        });
    }

    public function down(): void
    {
        $hasMultipleOrders = DB::table('lead_sales_sheets')
            ->select('lead_id')
            ->groupBy('lead_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasMultipleOrders) {
            throw new RuntimeException('Rollback non sicuro: esistono lead con più ordini.');
        }

        Schema::table('lead_sales_items', function (Blueprint $table) {
            $table->dropIndex(['pricing_group_uuid']);
            $table->dropColumn(['pricing_group_uuid', 'pricing_group_name']);
        });

        Schema::table('lead_sales_sheets', function (Blueprint $table) {
            $table->dropUnique(['order_number']);
            $table->dropIndex(['lead_id']);
            $table->dropColumn([
                'order_number', 'name', 'status', 'discount_type', 'discount_value',
                'discount_amount', 'rounding_adjustment',
            ]);
            $table->unique('lead_id');
        });

        Schema::table('crm_product_price_tiers', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))->contains(
            fn (array $index) => ($index['name'] ?? null) === $name
        );
    }
};
