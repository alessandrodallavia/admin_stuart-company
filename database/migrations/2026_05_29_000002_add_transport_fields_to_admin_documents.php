<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_documents', function (Blueprint $table) {
            $table->string('transport_reason')->nullable()->after('shipping_country');
            $table->string('transport_care')->nullable()->after('transport_reason');
            $table->date('transport_start_date')->nullable()->after('transport_care');
            $table->string('goods_appearance')->nullable()->after('transport_start_date');
            $table->unsignedInteger('parcels_count')->nullable()->after('goods_appearance');
            $table->decimal('gross_weight_kg', 8, 2)->nullable()->after('parcels_count');
            $table->decimal('net_weight_kg', 8, 2)->nullable()->after('gross_weight_kg');
            $table->string('carrier_name')->nullable()->after('net_weight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('admin_documents', function (Blueprint $table) {
            $table->dropColumn([
                'transport_reason',
                'transport_care',
                'transport_start_date',
                'goods_appearance',
                'parcels_count',
                'gross_weight_kg',
                'net_weight_kg',
                'carrier_name',
            ]);
        });
    }
};
