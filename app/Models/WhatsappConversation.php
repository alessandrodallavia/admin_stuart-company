<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WhatsappConversation extends Model
{
    protected $fillable = [
        'lead_id',
        'assigned_user_id',
        'contact_phone',
        'business_phone',
        'mode',
        'status',
        'needs_human',
        'last_message_at',
        'human_requested_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'needs_human' => 'boolean',
            'last_message_at' => 'datetime',
            'human_requested_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function linkedLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(WhatsappMessage::class)->latestOfMany();
    }

    public function unreadIncomingMessages(): HasMany
    {
        return $this->messages()
            ->where('direction', 'inbound')
            ->whereNull('admin_read_at');
    }
}
