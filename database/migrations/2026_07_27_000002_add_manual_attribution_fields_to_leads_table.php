<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('acquisition_channel', 30)->nullable()->after('acquisition_region');
            $table->string('attribution_confidence', 20)->nullable()->after('acquisition_channel');
            $table->string('attribution_note')->nullable()->after('attribution_confidence');
            $table->foreignId('created_by_admin_user_id')->nullable()->after('attribution_note')->constrained('admin_users')->nullOnDelete();

            $table->index('acquisition_channel');
            $table->index('attribution_confidence');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['created_by_admin_user_id']);
            $table->dropIndex(['acquisition_channel']);
            $table->dropIndex(['attribution_confidence']);
            $table->dropColumn([
                'acquisition_channel',
                'attribution_confidence',
                'attribution_note',
                'created_by_admin_user_id',
            ]);
        });
    }
};
