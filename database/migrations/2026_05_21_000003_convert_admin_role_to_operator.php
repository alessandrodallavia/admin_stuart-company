<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('admin_users')
            ->where('role', 'admin')
            ->update(['role' => 'operator']);
    }

    public function down(): void
    {
        //
    }
};
