<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('admin_document_items', 'item_code')) {
            Schema::table('admin_document_items', function (Blueprint $table) {
                $table->string('item_code', 80)->nullable()->after('position');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('admin_document_items', 'item_code')) {
            Schema::table('admin_document_items', function (Blueprint $table) {
                $table->dropColumn('item_code');
            });
        }
    }
};
