<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropUnique(['admin_user_id', 'email']);
            $table->unique('admin_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropUnique(['admin_user_id']);
            $table->unique(['admin_user_id', 'email']);
        });
    }
};
