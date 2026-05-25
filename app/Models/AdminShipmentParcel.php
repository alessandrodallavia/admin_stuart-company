<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminShipmentParcel extends Model
{
    protected $fillable = [
        'admin_shipment_id',
        'parcel_number',
        'parcel_id',
        'tracking_number',
        'label_stream',
        'dropbox_path',
        'weight_kg',
        'volume_m3',
    ];

    protected function casts(): array
    {
        return [
            'parcel_number' => 'integer',
            'volume_m3' => 'decimal:3',
            'weight_kg' => 'decimal:2',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(AdminShipment::class, 'admin_shipment_id');
    }

    public function decodedLabel(): ?string
    {
        return $this->label_stream ? base64_decode($this->label_stream, true) ?: null : null;
    }
}
