<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('leads')
            ->where('quote_number', 'like', 'PREV-%')
            ->orderBy('id')
            ->chunkById(100, function ($leads): void {
                foreach ($leads as $lead) {
                    $number = (int) substr((string) $lead->quote_number, 5);

                    DB::table('leads')
                        ->where('id', $lead->id)
                        ->update(['quote_number' => sprintf('Preventivo nr. %04d', $number)]);
                }
            });
    }

    public function down(): void
    {
        DB::table('leads')
            ->where('quote_number', 'like', 'Preventivo nr. %')
            ->orderBy('id')
            ->chunkById(100, function ($leads): void {
                foreach ($leads as $lead) {
                    $number = (int) substr((string) $lead->quote_number, 15);

                    DB::table('leads')
                        ->where('id', $lead->id)
                        ->update(['quote_number' => sprintf('PREV-%06d', $number)]);
                }
            });
    }
};
