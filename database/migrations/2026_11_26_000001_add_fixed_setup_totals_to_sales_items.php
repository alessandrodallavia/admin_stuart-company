<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('lead_sales_items', function (Blueprint $table) { $table->decimal('setup_revenue_total',12,2)->default(0); $table->decimal('setup_cost_total',12,2)->default(0); $table->json('setup_items')->nullable(); }); }
 public function down(): void { Schema::table('lead_sales_items',fn(Blueprint $table) => $table->dropColumn(['setup_revenue_total','setup_cost_total','setup_items'])); }
};
