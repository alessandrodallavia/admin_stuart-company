<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappFollowUp extends Model
{
    protected $fillable = [
        'whatsapp_conversation_id',
        'created_by_admin_user_id',
        'sent_message_id',
        'trigger_message_id',
        'auto_generated',
        'due_at',
        'body',
        'status',
        'sent_at',
        'cancelled_at',
        'cancel_reason',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'auto_generated' => 'boolean',
            'due_at' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class, 'whatsapp_conversation_id');
    }

    public function sentMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'sent_message_id');
    }

    public function triggerMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'trigger_message_id');
    }
}
