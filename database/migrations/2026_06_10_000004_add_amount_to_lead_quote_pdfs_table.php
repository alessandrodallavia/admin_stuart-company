<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_quote_pdfs', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->nullable()->after('proposal_number');
        });

        DB::table('lead_quote_pdfs')
            ->join('leads', 'leads.id', '=', 'lead_quote_pdfs.lead_id')
            ->select('lead_quote_pdfs.id', 'leads.quote_amount')
            ->whereNotNull('leads.quote_amount')
            ->orderBy('lead_quote_pdfs.id')
            ->get()
            ->each(function ($proposal) {
                DB::table('lead_quote_pdfs')
                    ->where('id', $proposal->id)
                    ->update(['amount' => $proposal->quote_amount]);
            });
    }

    public function down(): void
    {
        Schema::table('lead_quote_pdfs', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }
};
