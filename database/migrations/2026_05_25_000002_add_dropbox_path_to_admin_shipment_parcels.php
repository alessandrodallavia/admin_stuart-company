<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('admin_shipment_parcels', 'dropbox_path')) {
            Schema::table('admin_shipment_parcels', function (Blueprint $table) {
                $table->string('dropbox_path')->nullable()->after('label_stream');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('admin_shipment_parcels', 'dropbox_path')) {
            Schema::table('admin_shipment_parcels', function (Blueprint $table) {
                $table->dropColumn('dropbox_path');
            });
        }
    }
};
