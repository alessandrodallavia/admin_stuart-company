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
            $table->string('proposal_number')->nullable()->after('lead_id');
        });

        DB::table('lead_quote_pdfs')
            ->join('leads', 'leads.id', '=', 'lead_quote_pdfs.lead_id')
            ->select('lead_quote_pdfs.id', 'lead_quote_pdfs.lead_id', 'leads.quote_number')
            ->orderBy('lead_quote_pdfs.id')
            ->get()
            ->each(function ($proposal) {
                $legacyNumber = $proposal->quote_number
                    ? preg_replace('/^Preventivo/i', 'Proposta', $proposal->quote_number)
                    : 'Proposta-'.$proposal->lead_id;

                DB::table('lead_quote_pdfs')
                    ->where('id', $proposal->id)
                    ->update([
                        'proposal_number' => $legacyNumber.'-'.$proposal->id,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('lead_quote_pdfs', function (Blueprint $table) {
            $table->dropColumn('proposal_number');
        });
    }
};
