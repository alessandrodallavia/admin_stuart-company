<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_document_items', function (Blueprint $table) {
            $table->boolean('informational')->default(false)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('admin_document_items', function (Blueprint $table) {
            $table->dropColumn('informational');
        });
    }
};
