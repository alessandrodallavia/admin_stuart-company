<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadSalesItemVariant extends Model
{
    protected $fillable = [
        'lead_sales_item_id', 'crm_product_color_id', 'crm_product_size_id',
        'color_name', 'size_name', 'quantity',
    ];

    protected $casts = ['quantity' => 'decimal:2'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(LeadSalesItem::class, 'lead_sales_item_id');
    }
}
