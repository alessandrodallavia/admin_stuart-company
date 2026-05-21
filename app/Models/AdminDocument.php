<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminDocument extends Model
{
    public const TYPES = [
        'quote' => 'Preventivo',
        'offline_order' => 'Ordine offline',
        'delivery_note' => 'DDT',
        'invoice' => 'Fattura',
    ];

    public const STATUSES = [
        'draft' => 'Bozza',
        'issued' => 'Emesso',
        'sent' => 'Inviato',
        'accepted' => 'Accettato',
        'completed' => 'Completato',
        'cancelled' => 'Annullato',
    ];

    public const PAYMENT_STATUSES = [
        'unpaid' => 'Da pagare',
        'partial' => 'Parziale',
        'paid' => 'Pagato',
        'overdue' => 'Scaduto',
    ];

    protected $fillable = [
        'type',
        'number',
        'year',
        'code',
        'document_date',
        'status',
        'payment_status',
        'currency',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_tax_code',
        'customer_vat_number',
        'customer_recipient_code',
        'customer_pec',
        'customer_address',
        'customer_street_number',
        'customer_city',
        'customer_province',
        'customer_postal_code',
        'customer_country',
        'source_document_id',
        'notes',
        'subtotal',
        'vat_total',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'subtotal' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(AdminDocumentItem::class)->orderBy('position');
    }

    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(AdminDocumentPaymentSchedule::class)->orderBy('due_date');
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_document_id');
    }

    public function generatedDocuments(): HasMany
    {
        return $this->hasMany(self::class, 'source_document_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return self::PAYMENT_STATUSES[$this->payment_status] ?? ucfirst((string) $this->payment_status);
    }

    public function getDisplayCodeAttribute(): string
    {
        return $this->code ?: sprintf('%s/%s', $this->number ?: '-', $this->year);
    }

    public function refreshTotals(): void
    {
        $subtotal = (float) $this->items()->sum('line_subtotal');
        $vatTotal = (float) $this->items()->sum('line_vat');

        $this->forceFill([
            'subtotal' => $subtotal,
            'vat_total' => $vatTotal,
            'total' => $subtotal + $vatTotal,
        ])->save();

        $this->refreshPaymentStatus();
    }

    public function refreshPaymentStatus(): void
    {
        $paidAmount = (float) $this->paymentSchedules()->sum('paid_amount');
        $hasOverdue = $this->paymentSchedules()
            ->where('status', '!=', 'paid')
            ->whereDate('due_date', '<', now()->toDateString())
            ->exists();

        $status = match (true) {
            $this->total > 0 && $paidAmount >= (float) $this->total => 'paid',
            $paidAmount > 0 => 'partial',
            $hasOverdue => 'overdue',
            default => 'unpaid',
        };

        $this->forceFill(['payment_status' => $status])->save();
    }
}
