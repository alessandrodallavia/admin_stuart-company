<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadQuotePdf extends Model
{
    protected $fillable = [
        'lead_id',
        'proposal_number',
        'amount',
        'disk',
        'path',
        'filename',
        'mime_type',
        'size',
        'uploaded_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'uploaded_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
