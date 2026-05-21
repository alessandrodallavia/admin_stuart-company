<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('admin_documents')
            ->where('status', 'draft')
            ->update([
                'number' => null,
                'code' => null,
            ]);

        DB::table('admin_documents')
            ->where('type', 'offline_order')
            ->whereNotNull('number')
            ->where('status', '!=', 'draft')
            ->orderBy('id')
            ->get(['id', 'number'])
            ->each(function ($document) {
                DB::table('admin_documents')
                    ->where('id', $document->id)
                    ->update(['code' => 'OFF-'.$document->number]);
            });
    }

    public function down(): void
    {
        //
    }
};
