<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lead extends Model
{
    protected $fillable = [

        'uuid',
        'status',
        'name',
        'email',
        'phone',
        'club',
        'city',
        'message',

        'privacy_consent',
        'marketing_consent',

        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',

        'gclid',
        'fbclid',

        'landing_page',
        'entry_page',
        'referrer',

        'ip',
        'user_agent',
        'device',

        'pipeline_lead_id',
        'payment_link',
        'quote_amount',
        'payment_amount',
        'whatsapp_conversation_id',
    ];

    protected $casts = [
        'privacy_consent' => 'boolean',
        'marketing_consent' => 'boolean',
        'quote_amount' => 'decimal:2',
        'payment_amount' => 'decimal:2',
    ];

    public function whatsappConversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class);
    }

    public function linkedWhatsappConversation(): HasOne
    {
        return $this->hasOne(WhatsappConversation::class);
    }
}
