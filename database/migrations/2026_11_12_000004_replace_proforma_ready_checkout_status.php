<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('lead_order_checkouts')
            ->where('order_status', 'proforma_ready')
            ->update(['order_status' => 'proforma_pending']);
    }

    public function down(): void {}
};
