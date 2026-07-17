<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('ad_group')->nullable()->after('utm_content');
            $table->string('search_term')->nullable()->after('ad_group');
            $table->string('category')->nullable()->after('search_term');
            $table->string('product')->nullable()->after('category');
            $table->decimal('quantity', 10, 2)->nullable()->after('product');
            $table->string('lead_quality', 20)->nullable()->after('quantity');
            $table->string('loss_reason')->nullable()->after('lead_quality');
            $table->text('crm_notes')->nullable()->after('loss_reason');
            $table->decimal('margin_amount', 12, 2)->nullable()->after('crm_notes');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'ad_group',
                'search_term',
                'category',
                'product',
                'quantity',
                'lead_quality',
                'loss_reason',
                'crm_notes',
                'margin_amount',
            ]);
        });
    }
};
