<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Confirmed by the owner: EUR 35 excluding VAT for each selected embroidery.
        DB::table('crm_print_types')->where('code', 'IMPIANTO_RICAMO')->where('technique', 'setup')->whereNull('fixed_price')->update(['fixed_price' => 35]);
    }

    public function down(): void
    {
        // Preserve the commercial price and any subsequent operator changes.
    }
};
