<?php

namespace App\Models;

use App\Models\Concerns\TrainingScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmailConversation extends Model
{
    use TrainingScoped;

    protected $fillable = [
        'email_account_id',
        'lead_id',
        'assigned_user_id',
        'subject',
        'contact_email',
        'contact_name',
        'provider_thread_id',
        'status',
        'is_seen',
        'last_message_at',
        'is_training',
        'training_owner_id',
        'training_scenario',
    ];

    protected $casts = [
        'is_seen' => 'boolean',
        'last_message_at' => 'datetime',
        'is_training' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class, 'email_account_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(EmailMessage::class)
            ->orderByRaw('COALESCE(sent_at, received_at, created_at) asc')
            ->orderBy('id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(EmailMessage::class)->latestOfMany();
    }
}
