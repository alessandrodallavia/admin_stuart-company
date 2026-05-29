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
        'quote_number',
        'quote_pdf_disk',
        'quote_pdf_path',
        'quote_pdf_filename',
        'quote_pdf_mime_type',
        'quote_pdf_size',
        'quote_pdf_uploaded_at',
        'whatsapp_conversation_id',
        'google_ads_whatsapp_conversion_sent_at',
        'google_ads_whatsapp_conversion_status',
        'google_ads_whatsapp_conversion_error',
    ];

    protected $casts = [
        'privacy_consent' => 'boolean',
        'marketing_consent' => 'boolean',
        'quote_amount' => 'decimal:2',
        'payment_amount' => 'decimal:2',
        'quote_pdf_uploaded_at' => 'datetime',
        'google_ads_whatsapp_conversion_sent_at' => 'datetime',
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
