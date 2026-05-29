<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_documents', function (Blueprint $table) {
            $table->string('shipping_name')->nullable()->after('customer_country');
            $table->string('shipping_phone', 40)->nullable()->after('shipping_name');
            $table->string('shipping_address')->nullable()->after('shipping_phone');
            $table->string('shipping_street_number', 30)->nullable()->after('shipping_address');
            $table->string('shipping_city', 120)->nullable()->after('shipping_street_number');
            $table->string('shipping_province', 10)->nullable()->after('shipping_city');
            $table->string('shipping_postal_code', 20)->nullable()->after('shipping_province');
            $table->string('shipping_country', 2)->nullable()->after('shipping_postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('admin_documents', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_name',
                'shipping_phone',
                'shipping_address',
                'shipping_street_number',
                'shipping_city',
                'shipping_province',
                'shipping_postal_code',
                'shipping_country',
            ]);
        });
    }
};
