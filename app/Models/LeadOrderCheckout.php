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
        'minimum_order_amount' => 'decimal:2',
        'minimum_order_adjustment' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'order_taxable_amount' => 'decimal:2',
        'order_vat_amount' => 'decimal:2',
        'order_total_amount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'details_completed_at' => 'datetime',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
        'invalidated_at' => 'datetime',
        'revoked_at' => 'datetime',
        'fulfilled_at' => 'datetime',
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

    public function proforma(): BelongsTo
    {
        return $this->belongsTo(AdminDocument::class, 'admin_document_id');
    }

    public static function synchronizeOrderStatusForSheet(int $salesSheetId): void
    {
        $checkouts = self::query()
            ->where('lead_sales_sheet_id', $salesSheetId)
            ->whereNotIn('status', ['invalidated', 'expired', 'revoked'])
            ->with('proforma')
            ->get();
        $payments = $checkouts->whereNotNull('details_completed_at');

        if ($payments->isEmpty()) {
            return;
        }

        $currentStatus = $checkouts->pluck('order_status')->first();
        if (in_array($currentStatus, ['in_production', 'balance_pending', 'ready', 'shipped', 'cancelled'], true)) {
            return;
        }

        $status = match (true) {
            $payments->contains(fn (self $payment) => ! $payment->proforma || $payment->proforma->status !== 'sent') => 'proforma_pending',
            $payments->contains(fn (self $payment) => $payment->status === 'paid') => 'paid',
            default => 'payment_pending',
        };

        self::query()
            ->where('lead_sales_sheet_id', $salesSheetId)
            ->update(['order_status' => $status]);
    }
}
