<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'ga_client_id',
        'ga_session_id',

        'landing_page',
        'entry_page',
        'referrer',

        'ip',
        'user_agent',
        'device',

        'pipeline_lead_id',
        'payment_link',
        'payment_checkout_token',
        'stripe_customer_id',
        'quote_amount',
        'payment_amount',
        'billing_customer_type',
        'billing_name',
        'billing_email',
        'billing_phone',
        'billing_tax_code',
        'billing_vat_number',
        'billing_recipient_code',
        'billing_pec',
        'billing_address_line1',
        'billing_address_line2',
        'billing_postal_code',
        'billing_city',
        'billing_province',
        'billing_country',
        'billing_completed_at',
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
        'ga4_quote_sent_at',
        'ga4_quote_sent_status',
        'ga4_quote_sent_error',
        'ga4_payment_link_sent_at',
        'ga4_payment_link_sent_status',
        'ga4_payment_link_sent_error',
        'ga4_purchase_sent_at',
        'ga4_purchase_sent_status',
        'ga4_purchase_sent_error',
        'email_welcome_sent_at',
        'email_welcome_status',
        'email_welcome_error',
        'whatsapp_payment_thank_you_sent_at',
        'whatsapp_payment_thank_you_status',
        'whatsapp_payment_thank_you_error',
    ];

    protected $casts = [
        'privacy_consent' => 'boolean',
        'marketing_consent' => 'boolean',
        'quote_amount' => 'decimal:2',
        'payment_amount' => 'decimal:2',
        'billing_completed_at' => 'datetime',
        'quote_pdf_uploaded_at' => 'datetime',
        'google_ads_whatsapp_conversion_sent_at' => 'datetime',
        'ga4_quote_sent_at' => 'datetime',
        'ga4_payment_link_sent_at' => 'datetime',
        'ga4_purchase_sent_at' => 'datetime',
        'email_welcome_sent_at' => 'datetime',
        'whatsapp_payment_thank_you_sent_at' => 'datetime',
    ];

    public function whatsappConversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class);
    }

    public function linkedWhatsappConversation(): HasOne
    {
        return $this->hasOne(WhatsappConversation::class);
    }

    public function emailConversations(): HasMany
    {
        return $this->hasMany(EmailConversation::class);
    }
}
