<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_documents', function (Blueprint $table) {
            $table->string('customer_street_number', 30)->nullable()->after('customer_address');
            $table->string('customer_province', 10)->nullable()->after('customer_city');
            $table->string('customer_recipient_code', 7)->nullable()->after('customer_vat_number');
            $table->string('customer_pec')->nullable()->after('customer_recipient_code');
        });
    }

    public function down(): void
    {
        Schema::table('admin_documents', function (Blueprint $table) {
            $table->dropColumn([
                'customer_street_number',
                'customer_province',
                'customer_recipient_code',
                'customer_pec',
            ]);
        });
    }
};
