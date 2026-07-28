<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadSalesItem extends Model
{
    protected $fillable = [
        'lead_sales_sheet_id', 'crm_product_id', 'product_code', 'product_name', 'configuration_name', 'pricing_group_uuid', 'pricing_group_name',
        'quantity', 'product_unit_cost', 'product_unit_price', 'final_unit_price',
        'final_price_overridden', 'colors', 'notes', 'revenue_total', 'cost_total', 'margin_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'product_unit_cost' => 'decimal:4',
        'product_unit_price' => 'decimal:4',
        'final_unit_price' => 'decimal:4',
        'final_price_overridden' => 'boolean',
        'colors' => 'array',
        'revenue_total' => 'decimal:2',
        'cost_total' => 'decimal:2',
        'margin_total' => 'decimal:2',
    ];

    public function prints(): HasMany
    {
        return $this->hasMany(LeadSalesItemPrint::class);
    }

    public function leadSalesSheet(): BelongsTo
    {
        return $this->belongsTo(LeadSalesSheet::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(LeadSalesItemAttachment::class);
    }

    public function crmProduct(): BelongsTo
    {
        return $this->belongsTo(CrmProduct::class);
    }
}
