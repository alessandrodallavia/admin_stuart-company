<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('email_welcome_sent_at')->nullable()->after('ga4_purchase_sent_error');
            $table->string('email_welcome_status')->nullable()->after('email_welcome_sent_at');
            $table->text('email_welcome_error')->nullable()->after('email_welcome_status');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'email_welcome_sent_at',
                'email_welcome_status',
                'email_welcome_error',
            ]);
        });
    }
};
