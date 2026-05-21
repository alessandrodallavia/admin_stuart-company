<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminDocumentPaymentSchedule extends Model
{
    public const STATUSES = [
        'unpaid' => 'Non pagato',
        'partial' => 'Parziale',
        'paid' => 'Pagato',
    ];

    protected $fillable = [
        'admin_document_id',
        'due_date',
        'method',
        'payment_method_code',
        'amount',
        'paid_amount',
        'paid_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_at' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(AdminDocument::class, 'admin_document_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(DocumentsPaymentMethod::class, 'payment_method_code', 'code');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
