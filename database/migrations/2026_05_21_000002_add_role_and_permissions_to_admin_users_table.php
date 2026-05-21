<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            if (! Schema::hasColumn('admin_users', 'role')) {
                $table->string('role')->default('operator')->after('password');
            }

            if (! Schema::hasColumn('admin_users', 'permissions')) {
                $table->json('permissions')->nullable()->after('role');
            }
        });

        DB::table('admin_users')
            ->whereNull('role')
            ->orWhere('role', '')
            ->update(['role' => 'operator']);
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            if (Schema::hasColumn('admin_users', 'permissions')) {
                $table->dropColumn('permissions');
            }

            if (Schema::hasColumn('admin_users', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
