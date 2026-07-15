<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminDocumentItem extends Model
{
    protected $fillable = [
        'admin_document_id',
        'position',
        'item_code',
        'description',
        'quantity',
        'unit_price',
        'vat_rate',
        'line_subtotal',
        'line_vat',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'line_vat' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(AdminDocument::class, 'admin_document_id');
    }
}
