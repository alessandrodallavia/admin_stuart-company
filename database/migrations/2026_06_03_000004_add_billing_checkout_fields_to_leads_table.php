<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'payment_checkout_token')) {
                $table->string('payment_checkout_token', 80)->nullable()->unique()->after('payment_link');
            }

            if (! Schema::hasColumn('leads', 'billing_customer_type')) {
                $table->string('billing_customer_type')->nullable()->after('payment_amount');
                $table->string('billing_name')->nullable()->after('billing_customer_type');
                $table->string('billing_email')->nullable()->after('billing_name');
                $table->string('billing_phone')->nullable()->after('billing_email');
                $table->string('billing_tax_code', 40)->nullable()->after('billing_phone');
                $table->string('billing_vat_number', 40)->nullable()->after('billing_tax_code');
                $table->string('billing_recipient_code', 7)->nullable()->after('billing_vat_number');
                $table->string('billing_pec')->nullable()->after('billing_recipient_code');
                $table->string('billing_address_line1')->nullable()->after('billing_pec');
                $table->string('billing_address_line2')->nullable()->after('billing_address_line1');
                $table->string('billing_postal_code', 20)->nullable()->after('billing_address_line2');
                $table->string('billing_city')->nullable()->after('billing_postal_code');
                $table->string('billing_province', 10)->nullable()->after('billing_city');
                $table->string('billing_country', 2)->nullable()->after('billing_province');
                $table->timestamp('billing_completed_at')->nullable()->after('billing_country');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            foreach ([
                'billing_completed_at',
                'billing_country',
                'billing_province',
                'billing_city',
                'billing_postal_code',
                'billing_address_line2',
                'billing_address_line1',
                'billing_pec',
                'billing_recipient_code',
                'billing_vat_number',
                'billing_tax_code',
                'billing_phone',
                'billing_email',
                'billing_name',
                'billing_customer_type',
                'payment_checkout_token',
            ] as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
