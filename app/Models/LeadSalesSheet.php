<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadSalesSheet extends Model
{
    public const VAT_RATE = 22;

    protected $fillable = ['lead_id', 'order_number', 'name', 'status', 'product_revenue_total', 'shipping_fee', 'free_shipping_threshold', 'shipping_charge', 'shipping_cost', 'discount_type', 'discount_value', 'discount_amount', 'rounding_adjustment', 'revenue_total', 'cost_total', 'margin_total', 'margin_percentage', 'notes'];

    protected $casts = ['product_revenue_total' => 'decimal:2', 'shipping_fee' => 'decimal:2', 'free_shipping_threshold' => 'decimal:2', 'shipping_charge' => 'decimal:2', 'shipping_cost' => 'decimal:2', 'discount_value' => 'decimal:2', 'discount_amount' => 'decimal:2', 'rounding_adjustment' => 'decimal:2', 'revenue_total' => 'decimal:2', 'cost_total' => 'decimal:2', 'margin_total' => 'decimal:2', 'margin_percentage' => 'decimal:2'];

    /** @return array{net: float, vat: float, gross: float, rate: int} */
    public function vatTotals(): array
    {
        $netCents = (int) round((float) $this->revenue_total * 100);
        $vatCents = (int) round($netCents * self::VAT_RATE / 100, 0, PHP_ROUND_HALF_UP);

        return [
            'net' => $netCents / 100,
            'vat' => $vatCents / 100,
            'gross' => ($netCents + $vatCents) / 100,
            'rate' => self::VAT_RATE,
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(LeadSalesItem::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(LeadOrderDispatch::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(LeadQuotePdf::class);
    }
}
