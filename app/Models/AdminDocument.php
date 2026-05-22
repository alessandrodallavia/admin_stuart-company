<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Services\DocumentActionService;
use App\Services\DocumentRelationService;
use App\Traits\HasDocumentRelations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminDocument extends Model
{
    use HasDocumentRelations;

    public const TYPES = [
        'quote' => 'Preventivo',
        'proforma' => 'Proforma',
        'offline_order' => 'Ordine offline',
        'delivery_note' => 'DDT',
        'invoice' => 'Fattura',
    ];

    public const STATUSES = [
        'quote' => [
            'draft' => 'Bozza',
            'sent' => 'Inviato',
            'accepted' => 'Accettato',
            'rejected' => 'Rifiutato',
            'cancelled' => 'Annullato',
        ],
        'proforma' => [
            'draft' => 'Bozza',
            'sent' => 'Inviata',
            'accepted' => 'Accettata',
            'cancelled' => 'Annullata',
        ],
        'offline_order' => [
            'draft' => 'Bozza',
            'confirmed' => 'Confermato',
            'purchased' => 'Acquistato',
            'completed' => 'Completato',
            'completed_partially' => 'Completato parzialmente',
            'cancelled' => 'Annullata',
        ],
        'delivery_note' => [
            'draft' => 'Bozza',
            'sent' => 'Inviato',
        ],
        'invoice' => [
            'draft' => 'Bozza',
            'issued' => 'Emessa',
            'sent' => 'Inviata SDI',
            'cancelled' => 'Annullata',
        ],
    ];

    public const PAYMENT_STATUSES = [
        'unpaid' => 'Non pagato',
        'paid' => 'Pagato',
        'not_managed' => 'Non gestito',
    ];

    protected $fillable = [
        'type',
        'fiscal_type',
        'number',
        'year',
        'code',
        'document_date',
        'status',
        'payment_status',
        'payment_conditions',
        'payment_method',
        'bank_name',
        'bank_iban',
        'bank_bic',
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
        'xml_filename',
        'xml_hash',
        'xml_imported',
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
            'xml_imported' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (AdminDocument $document) {
            $type = $document->currentDocumentType();

            DocumentRelation::where(function ($query) use ($document, $type) {
                $query->where('from_type', $type)
                    ->where('from_id', $document->id);
            })->orWhere(function ($query) use ($document, $type) {
                $query->where('to_type', $type)
                    ->where('to_id', $document->id);
            })->delete();
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(AdminDocumentItem::class)->orderBy('position');
    }

    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(AdminDocumentPaymentSchedule::class)->orderBy('due_date');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(DocumentsPaymentMethod::class, 'payment_method', 'code');
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
        return self::statusLabelFor($this->type, $this->status);
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->type) {
            'invoice' => match ($this->payment_status) {
                'unpaid' => 'Non pagata',
                'paid' => 'Pagata',
                'not_managed' => 'Non gestita',
                default => ucfirst((string) $this->payment_status),
            },
            default => self::PAYMENT_STATUSES[$this->payment_status] ?? ucfirst((string) $this->payment_status),
        };
    }

    public static function statusesFor(?string $type): array
    {
        return self::STATUSES[$type ?: 'quote'] ?? self::STATUSES['quote'];
    }

    public static function allStatuses(): array
    {
        return collect(self::STATUSES)
            ->flatMap(fn ($statuses) => $statuses)
            ->all();
    }

    public static function statusLabelFor(?string $type, ?string $status): string
    {
        return self::statusesFor($type)[$status] ?? ucfirst((string) $status);
    }

    public function getDisplayCodeAttribute(): string
    {
        if ($this->status === 'draft' || ! $this->number) {
            return 'BOZZA';
        }

        return $this->code ?: $this->documentPrefix().'-'.$this->number;
    }

    public function documentPrefix(): string
    {
        return match ($this->type) {
            'quote' => 'PREV',
            'proforma' => 'PRO',
            'offline_order' => 'OFF',
            'delivery_note' => 'DDT',
            'invoice' => 'FPR',
            default => 'DOC',
        };
    }

    public static function documentType(): DocumentType
    {
        return match (request()->route('document')?->type ?? 'quote') {
            'offline_order' => DocumentType::ORDER,
            'delivery_note' => DocumentType::DELIVERY_NOTE,
            'invoice' => DocumentType::INVOICE,
            'proforma' => DocumentType::PROFORMA,
            default => DocumentType::QUOTE,
        };
    }

    public function currentDocumentType(): DocumentType
    {
        return match ($this->type) {
            'offline_order' => DocumentType::ORDER,
            'delivery_note' => DocumentType::DELIVERY_NOTE,
            'invoice' => DocumentType::INVOICE,
            'proforma' => DocumentType::PROFORMA,
            default => DocumentType::QUOTE,
        };
    }

    public function relations()
    {
        $type = $this->currentDocumentType();

        return DocumentRelation::where(function ($q) use ($type) {
            $q->where('from_type', $type)
                ->where('from_id', $this->id);
        })->orWhere(function ($q) use ($type) {
            $q->where('to_type', $type)
                ->where('to_id', $this->id);
        });
    }

    public function availableActions(): array
    {
        return DocumentActionService::availableActions(
            $this->currentDocumentType(),
            $this->id
        );
    }

    public function getRelatedDocumentsAttribute()
    {
        $startType = $this->currentDocumentType();
        $startId = $this->id;
        $visited = collect();
        $queue = collect([
            ['type' => $startType->value, 'id' => $startId],
        ]);

        while ($queue->isNotEmpty()) {
            $current = $queue->shift();
            $key = $current['type'].'-'.$current['id'];

            if ($visited->contains($key)) {
                continue;
            }

            $visited->push($key);

            $relations = DocumentRelation::where(function ($q) use ($current) {
                $q->where('from_type', $current['type'])
                    ->where('from_id', $current['id']);
            })->orWhere(function ($q) use ($current) {
                $q->where('to_type', $current['type'])
                    ->where('to_id', $current['id']);
            })->get();

            foreach ($relations as $rel) {
                foreach ([['type' => $rel->from_type->value, 'id' => $rel->from_id], ['type' => $rel->to_type->value, 'id' => $rel->to_id]] as $node) {
                    $nodeKey = $node['type'].'-'.$node['id'];
                    if (! $visited->contains($nodeKey)) {
                        $queue->push($node);
                    }
                }
            }
        }

        return $visited
            ->reject(fn ($key) => $key === $startType->value.'-'.$startId)
            ->map(function ($key) {
                [$type, $id] = explode('-', $key);
                $documentType = DocumentType::from($type);
                $model = DocumentRelationService::resolveModel($documentType)::query()
                    ->where('type', DocumentRelationService::adminType($documentType))
                    ->find((int) $id);

                if (! $model) {
                    return null;
                }

                return [
                    'label' => $model->type_label.' nr. '.$model->display_code,
                    'class' => match ($type) {
                        'order' => 'bg-blue-100 text-blue-700',
                        'delivery_note' => 'bg-purple-100 text-purple-700',
                        'invoice' => 'bg-green-100 text-green-700',
                        'quote' => 'bg-yellow-100 text-yellow-700',
                        default => 'bg-gray-100 text-gray-700',
                    },
                    'type' => $type,
                    'id' => (int) $id,
                ];
            })
            ->filter()
            ->values();
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
        $status = match (true) {
            $this->total > 0 && $paidAmount >= (float) $this->total => 'paid',
            default => 'unpaid',
        };

        $this->forceFill(['payment_status' => $status])->save();
    }
}
