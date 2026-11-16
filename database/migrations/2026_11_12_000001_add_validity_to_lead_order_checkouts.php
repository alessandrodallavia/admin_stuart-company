<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_order_checkouts', function (Blueprint $table) {
            $table->string('order_snapshot_hash', 64)->nullable()->after('status');
            $table->timestamp('expires_at')->nullable()->after('order_snapshot_hash');
            $table->timestamp('invalidated_at')->nullable()->after('expires_at');
            $table->timestamp('revoked_at')->nullable()->after('invalidated_at');
        });
    }

    public function down(): void
    {
        Schema::table('lead_order_checkouts', fn (Blueprint $table) => $table->dropColumn([
            'order_snapshot_hash', 'expires_at', 'invalidated_at', 'revoked_at',
        ]));
    }
};
