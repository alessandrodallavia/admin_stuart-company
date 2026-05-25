<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminShipment extends Model
{
    public const CARRIERS = [
        'brt' => 'BRT',
        'sda' => 'SDA',
    ];

    public const STATUSES = [
        'draft' => 'Bozza',
        'shipped' => 'Spedita',
        'failed' => 'Errore',
        'cancelled' => 'Annullata',
    ];

    protected $fillable = [
        'admin_document_id',
        'carrier',
        'status',
        'reference',
        'recipient_name',
        'recipient_email',
        'recipient_phone',
        'recipient_address',
        'recipient_street_number',
        'recipient_city',
        'recipient_province',
        'recipient_postal_code',
        'recipient_country',
        'parcels_count',
        'weight_kg',
        'volume_m3',
        'cash_on_delivery',
        'tracking_number',
        'carrier_reference',
        'carrier_response',
        'error_message',
        'shipped_at',
    ];

    protected function casts(): array
    {
        return [
            'carrier_response' => 'array',
            'cash_on_delivery' => 'decimal:2',
            'parcels_count' => 'integer',
            'shipped_at' => 'datetime',
            'volume_m3' => 'decimal:3',
            'weight_kg' => 'decimal:2',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(AdminDocument::class, 'admin_document_id');
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(AdminDocument::class, 'admin_document_admin_shipment')
            ->withTimestamps();
    }

    public function parcels(): HasMany
    {
        return $this->hasMany(AdminShipmentParcel::class)->orderBy('parcel_number');
    }

    public function getCarrierLabelAttribute(): string
    {
        return self::CARRIERS[$this->carrier] ?? strtoupper((string) $this->carrier);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getRecipientAddressLineAttribute(): string
    {
        return trim(collect([$this->recipient_address, $this->recipient_street_number])->filter()->implode(' '));
    }

    public function getRecipientCityLineAttribute(): string
    {
        return trim(($this->recipient_postal_code ? $this->recipient_postal_code.' ' : '').($this->recipient_city ?: '').($this->recipient_province ? ' ('.$this->recipient_province.')' : ''));
    }

    public function getTrackingUrlAttribute(): ?string
    {
        $tracking = $this->tracking_number ?: $this->parcels->first()?->tracking_number;

        if (! $tracking) {
            return null;
        }

        return match ($this->carrier) {
            'brt' => 'https://vas.brt.it/vas/sped_det_show.hsm?referer=sped_numspe_par.htm&Nspediz='.$tracking,
            'sda' => 'https://www.poste.it/cerca/index.html#/risultati-spedizioni/'.$tracking,
            default => null,
        };
    }
}
