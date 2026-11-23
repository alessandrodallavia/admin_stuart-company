<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OrderCheckoutFingerprint
{
    public function forSheet(int $sheetId): string
    {
        $sheet = DB::table('lead_sales_sheets')->where('id', $sheetId)->first([
            'id', 'prices_include_vat', 'product_revenue_total', 'shipping_charge',
            'discount_type', 'discount_value', 'discount_amount', 'rounding_adjustment',
            'minimum_order_amount', 'minimum_order_adjustment', 'revenue_total',
        ]);
        $items = DB::table('lead_sales_items')->where('lead_sales_sheet_id', $sheetId)->orderBy('id')->get([
            'id', 'product_code', 'product_name', 'configuration_name', 'quantity',
            'final_unit_price', 'revenue_total', 'colors',
        ]);
        $prints = DB::table('lead_sales_item_prints')
            ->whereIn('lead_sales_item_id', $items->pluck('id'))
            ->orderBy('id')
            ->get(['lead_sales_item_id', 'print_code', 'print_name', 'unit_price']);

        return hash('sha256', json_encode([$sheet, $items, $prints], JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE));
    }
}
