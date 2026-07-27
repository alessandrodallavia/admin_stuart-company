<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('lead_order_dispatches', 'cc_email')) {
            Schema::table('lead_order_dispatches', function (Blueprint $table) {
                $table->dropColumn('cc_email');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('lead_order_dispatches', 'cc_email')) {
            Schema::table('lead_order_dispatches', function (Blueprint $table) {
                $table->string('cc_email')->nullable()->after('to_email');
            });
        }
    }
};
