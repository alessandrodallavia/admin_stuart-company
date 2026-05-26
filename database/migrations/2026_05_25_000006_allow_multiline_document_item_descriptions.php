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

        if (Schema::hasColumn('admin_document_items', 'description')) {
            DB::statement('ALTER TABLE admin_document_items MODIFY description TEXT NOT NULL');
        }

        if (Schema::hasColumn('admin_document_items', 'quantity')) {
            DB::statement('ALTER TABLE admin_document_items MODIFY quantity DECIMAL(10, 2) NOT NULL DEFAULT 0');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasColumn('admin_document_items', 'description')) {
            DB::statement('ALTER TABLE admin_document_items MODIFY description VARCHAR(255) NOT NULL');
        }

        if (Schema::hasColumn('admin_document_items', 'quantity')) {
            DB::statement('ALTER TABLE admin_document_items MODIFY quantity DECIMAL(10, 2) NOT NULL DEFAULT 1');
        }
    }
};
