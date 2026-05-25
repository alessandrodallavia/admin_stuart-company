<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('admin_shipments', 'carrier_payload')) {
            Schema::table('admin_shipments', function (Blueprint $table) {
                $table->dropColumn('carrier_payload');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('admin_shipments', 'carrier_payload')) {
            Schema::table('admin_shipments', function (Blueprint $table) {
                $table->json('carrier_payload')->nullable()->after('carrier_reference');
            });
        }
    }
};
