<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasColumn('admin_document_items', 'unit_price')) {
            DB::statement('ALTER TABLE admin_document_items MODIFY unit_price DECIMAL(12, 4) NOT NULL DEFAULT 0');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasColumn('admin_document_items', 'unit_price')) {
            DB::statement('ALTER TABLE admin_document_items MODIFY unit_price DECIMAL(12, 2) NOT NULL DEFAULT 0');
        }
    }
};
