<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_sales_sheets', function (Blueprint $table) {
            $table->decimal('product_revenue_total', 12, 2)->default(0)->after('lead_id');
            $table->decimal('shipping_fee', 12, 2)->default(9.90)->after('product_revenue_total');
            $table->decimal('free_shipping_threshold', 12, 2)->default(250)->after('shipping_fee');
            $table->decimal('shipping_charge', 12, 2)->default(0)->after('free_shipping_threshold');
            $table->decimal('shipping_cost', 12, 2)->default(0)->after('shipping_charge');
        });

        DB::table('lead_sales_sheets')->orderBy('id')->eachById(function ($sheet) {
            $productRevenue = (float) DB::table('lead_sales_items')->where('lead_sales_sheet_id', $sheet->id)->sum('revenue_total');
            $productCost = (float) DB::table('lead_sales_items')->where('lead_sales_sheet_id', $sheet->id)->sum('cost_total');
            $hasProducts = $productRevenue > 0 || DB::table('lead_sales_items')->where('lead_sales_sheet_id', $sheet->id)->exists();
            $shippingCharge = $hasProducts && $productRevenue <= 250 ? 9.90 : 0;
            $shippingCost = $hasProducts ? 9.90 : 0;
            $revenue = $productRevenue + $shippingCharge;
            $cost = $productCost + $shippingCost;
            $margin = $revenue - $cost;

            DB::table('lead_sales_sheets')->where('id', $sheet->id)->update([
                'product_revenue_total' => $productRevenue,
                'shipping_charge' => $shippingCharge,
                'shipping_cost' => $shippingCost,
                'revenue_total' => $revenue,
                'cost_total' => $cost,
                'margin_total' => $margin,
                'margin_percentage' => $revenue > 0 ? ($margin / $revenue) * 100 : 0,
            ]);
            DB::table('leads')->where('id', $sheet->lead_id)->update(['margin_amount' => $margin]);
        });
    }

    public function down(): void
    {
        DB::table('lead_sales_sheets')->orderBy('id')->eachById(function ($sheet) {
            $revenue = (float) DB::table('lead_sales_items')->where('lead_sales_sheet_id', $sheet->id)->sum('revenue_total');
            $cost = (float) DB::table('lead_sales_items')->where('lead_sales_sheet_id', $sheet->id)->sum('cost_total');
            $margin = $revenue - $cost;

            DB::table('lead_sales_sheets')->where('id', $sheet->id)->update([
                'revenue_total' => $revenue,
                'cost_total' => $cost,
                'margin_total' => $margin,
                'margin_percentage' => $revenue > 0 ? ($margin / $revenue) * 100 : 0,
            ]);
            DB::table('leads')->where('id', $sheet->lead_id)->update(['margin_amount' => $margin]);
        });

        Schema::table('lead_sales_sheets', function (Blueprint $table) {
            $table->dropColumn([
                'product_revenue_total',
                'shipping_fee',
                'free_shipping_threshold',
                'shipping_charge',
                'shipping_cost',
            ]);
        });
    }
};
