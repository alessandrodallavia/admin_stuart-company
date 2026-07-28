<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadSalesSheet extends Model
{
    protected $fillable = ['lead_id', 'order_number', 'name', 'status', 'product_revenue_total', 'shipping_fee', 'free_shipping_threshold', 'shipping_charge', 'shipping_cost', 'discount_type', 'discount_value', 'discount_amount', 'rounding_adjustment', 'revenue_total', 'cost_total', 'margin_total', 'margin_percentage', 'notes'];

    protected $casts = ['product_revenue_total' => 'decimal:2', 'shipping_fee' => 'decimal:2', 'free_shipping_threshold' => 'decimal:2', 'shipping_charge' => 'decimal:2', 'shipping_cost' => 'decimal:2', 'discount_value' => 'decimal:2', 'discount_amount' => 'decimal:2', 'rounding_adjustment' => 'decimal:2', 'revenue_total' => 'decimal:2', 'cost_total' => 'decimal:2', 'margin_total' => 'decimal:2', 'margin_percentage' => 'decimal:2'];

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
}
