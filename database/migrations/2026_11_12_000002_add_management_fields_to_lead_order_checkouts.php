<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_order_checkouts', function (Blueprint $table) {
            $table->string('order_status', 40)->default('new')->after('status')->index();
            $table->unsignedBigInteger('admin_document_id')->nullable()->after('lead_sales_sheet_id')->index();
        });

        DB::table('lead_order_checkouts')
            ->whereNotNull('details_completed_at')
            ->update(['order_status' => 'proforma_pending']);
    }

    public function down(): void
    {
        Schema::table('lead_order_checkouts', fn (Blueprint $table) => $table->dropColumn([
            'order_status',
            'admin_document_id',
        ]));
    }
};
