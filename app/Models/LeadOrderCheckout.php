<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadOrderCheckout extends Model
{
    protected $guarded = [];

    protected $casts = [
        'items' => 'array',
        'vat_rate' => 'decimal:2',
        'product_subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'rounding_adjustment' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'order_taxable_amount' => 'decimal:2',
        'order_vat_amount' => 'decimal:2',
        'order_total_amount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'details_completed_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(LeadQuotePdf::class, 'lead_quote_pdf_id');
    }

    public function salesSheet(): BelongsTo
    {
        return $this->belongsTo(LeadSalesSheet::class, 'lead_sales_sheet_id');
    }
}
