<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_messages', 'media_id')) {
                $table->string('media_id')->nullable()->after('body');
            }

            if (! Schema::hasColumn('whatsapp_messages', 'media_disk')) {
                $table->string('media_disk')->nullable()->after('media_id');
            }

            if (! Schema::hasColumn('whatsapp_messages', 'media_path')) {
                $table->string('media_path')->nullable()->after('media_disk');
            }

            if (! Schema::hasColumn('whatsapp_messages', 'media_mime_type')) {
                $table->string('media_mime_type')->nullable()->after('media_path');
            }

            if (! Schema::hasColumn('whatsapp_messages', 'media_filename')) {
                $table->string('media_filename')->nullable()->after('media_mime_type');
            }

            if (! Schema::hasColumn('whatsapp_messages', 'media_size')) {
                $table->unsignedBigInteger('media_size')->nullable()->after('media_filename');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $columns = [
                'media_size',
                'media_filename',
                'media_mime_type',
                'media_path',
                'media_disk',
                'media_id',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('whatsapp_messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
