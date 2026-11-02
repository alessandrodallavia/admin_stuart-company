<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfMissing('payment_type', fn (Blueprint $table) => $table->string('payment_type', 20)->default('full')->after('status'));
        $this->addColumnIfMissing('payment_label', fn (Blueprint $table) => $table->string('payment_label', 100)->default('Pagamento completo')->after('payment_type'));
        $this->addColumnIfMissing('order_taxable_amount', fn (Blueprint $table) => $table->decimal('order_taxable_amount', 12, 2)->default(0)->after('shipping_amount'));
        $this->addColumnIfMissing('order_vat_amount', fn (Blueprint $table) => $table->decimal('order_vat_amount', 12, 2)->default(0)->after('order_taxable_amount'));
        $this->addColumnIfMissing('order_total_amount', fn (Blueprint $table) => $table->decimal('order_total_amount', 12, 2)->default(0)->after('order_vat_amount'));

        DB::table('lead_order_checkouts')
            ->where('order_total_amount', 0)
            ->update([
                'order_taxable_amount' => DB::raw('taxable_amount'),
                'order_vat_amount' => DB::raw('vat_amount'),
                'order_total_amount' => DB::raw('total_amount'),
            ]);

        if (DB::getDriverName() === 'mysql') {
            $uniqueIndex = 'lead_order_checkouts_lead_sales_sheet_id_unique';
            $normalIndex = 'lead_order_checkouts_lead_sales_sheet_id_index';

            if ($this->mysqlIndexExists($uniqueIndex) && ! $this->mysqlIndexExists($normalIndex)) {
                Schema::table('lead_order_checkouts', fn (Blueprint $table) => $table->index('lead_sales_sheet_id'));
            }

            if ($this->mysqlIndexExists($uniqueIndex)) {
                Schema::table('lead_order_checkouts', fn (Blueprint $table) => $table->dropUnique($uniqueIndex));
            }
        }
    }

    public function down(): void
    {
        // Non ripristiniamo il vincolo univoco: potrebbe ormai esistere più
        // di un checkout (acconto e saldo) per lo stesso ordine.
        foreach (['order_total_amount', 'order_vat_amount', 'order_taxable_amount', 'payment_label', 'payment_type'] as $column) {
            if (Schema::hasColumn('lead_order_checkouts', $column)) {
                Schema::table('lead_order_checkouts', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }

    private function addColumnIfMissing(string $column, callable $definition): void
    {
        if (! Schema::hasColumn('lead_order_checkouts', $column)) {
            Schema::table('lead_order_checkouts', $definition);
        }
    }

    private function mysqlIndexExists(string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'lead_order_checkouts')
            ->where('index_name', $index)
            ->exists();
    }
};
