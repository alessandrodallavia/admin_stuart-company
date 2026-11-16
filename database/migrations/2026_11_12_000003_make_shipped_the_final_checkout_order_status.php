<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('lead_order_checkouts')
            ->where('order_status', 'completed')
            ->update(['order_status' => 'shipped']);
    }

    public function down(): void
    {
        // Lo stato precedente non è ricostruibile senza confondere ordini spediti e completati.
    }
};
