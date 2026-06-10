<?php

namespace App\Models;

use App\Models\Concerns\TrainingScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WhatsappConversation extends Model
{
    use TrainingScoped;

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
        'manual_started_at',
        'follow_up_excluded_until',
        'follow_up_excluded_permanently',
        'follow_up_exclusion_reason',
        'metadata',
        'is_training',
        'training_owner_id',
        'training_scenario',
    ];

    protected function casts(): array
    {
        return [
            'needs_human' => 'boolean',
            'last_message_at' => 'datetime',
            'human_requested_at' => 'datetime',
            'manual_started_at' => 'datetime',
            'follow_up_excluded_until' => 'datetime',
            'follow_up_excluded_permanently' => 'boolean',
            'metadata' => 'array',
            'is_training' => 'boolean',
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

    public function latestIncomingMessage(): HasOne
    {
        return $this->hasOne(WhatsappMessage::class)
            ->where('direction', 'inbound')
            ->latestOfMany();
    }

    public function unreadIncomingMessages(): HasMany
    {
        return $this->messages()
            ->where('direction', 'inbound')
            ->whereNull('admin_read_at');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(WhatsappFollowUp::class);
    }

    public function pendingFollowUps(): HasMany
    {
        return $this->followUps()->where('status', 'pending');
    }

    public function dueFollowUps(): HasMany
    {
        return $this->pendingFollowUps()->where('due_at', '<=', now());
    }

    public function isExcludedFromFollowUps(): bool
    {
        return $this->follow_up_excluded_permanently
            || ($this->follow_up_excluded_until && $this->follow_up_excluded_until->isFuture());
    }

    public function isWhatsappWindowExpired(): bool
    {
        $latestIncomingAt = $this->latestIncomingMessage?->received_at
            ?? $this->latestIncomingMessage?->created_at
            ?? $this->last_message_at
            ?? $this->created_at;

        return $latestIncomingAt
            ? $latestIncomingAt->lte(now()->subHours(24))
            : false;
    }
}
