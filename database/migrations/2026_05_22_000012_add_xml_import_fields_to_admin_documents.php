<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_documents', function (Blueprint $table) {
            $table->string('xml_filename')->nullable()->after('source_document_id');
            $table->string('xml_hash', 64)->nullable()->unique()->after('xml_filename');
            $table->boolean('xml_imported')->default(false)->after('xml_hash');
        });
    }

    public function down(): void
    {
        Schema::table('admin_documents', function (Blueprint $table) {
            $table->dropUnique(['xml_hash']);
            $table->dropColumn(['xml_filename', 'xml_hash', 'xml_imported']);
        });
    }
};
