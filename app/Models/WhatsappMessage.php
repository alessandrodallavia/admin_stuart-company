<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMessage extends Model
{
    protected $fillable = [
        'whatsapp_conversation_id',
        'provider_message_id',
        'direction',
        'source',
        'type',
        'status',
        'from_phone',
        'to_phone',
        'body',
        'media_id',
        'media_disk',
        'media_path',
        'media_mime_type',
        'media_filename',
        'media_size',
        'payload',
        'error_code',
        'error_message',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'received_at',
        'admin_read_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
            'received_at' => 'datetime',
            'admin_read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class, 'whatsapp_conversation_id');
    }
}
