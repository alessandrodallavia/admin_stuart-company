<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EcommerceOrder extends Model {
    public const POSITIONS = ['front' => 'Fronte', 'back' => 'Retro', 'sleeve_left' => 'Manica sinistra', 'sleeve_right' => 'Manica destra'];
    public const ORDER_STATUSES = ['new' => 'Da gestire', 'in_production' => 'In lavorazione', 'ready' => 'Pronto', 'shipped' => 'Spedito / consegnato'];
    public const FULFILLMENT_METHODS = ['shipping' => 'Spedizione', 'pickup' => 'Ritiro', 'direct' => 'Consegna diretta'];
    public const FULFILLMENT_STATUSES = ['pending' => 'Da gestire', 'ready' => 'Pronto', 'shipped' => 'Spedito', 'delivered' => 'Consegnato', 'collected' => 'Ritirato'];
    protected $guarded = [];
    protected $casts = ['customer' => 'array', 'items' => 'array', 'net' => 'decimal:2', 'vat' => 'decimal:2', 'gross' => 'decimal:2', 'confirmation_sent_at' => 'datetime', 'paid_at' => 'datetime', 'fulfilled_at' => 'datetime', 'cancelled_at' => 'datetime', 'admin_document_id' => 'integer', 'proforma_sent_at' => 'datetime', 'shipping_gross' => 'decimal:2'];
    public function events() { return $this->hasMany(EcommerceOrderEvent::class); }
    public function invoice() { return $this->belongsTo(AdminDocument::class, 'invoice_document_id'); }
    public function deliveryDocument() { return $this->belongsTo(AdminDocument::class, 'delivery_document_id'); }
    public function offlineDocument(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AdminDocument::class, 'offline_document_id');
    }

    public function proforma() { return $this->belongsTo(AdminDocument::class, 'admin_document_id'); }
    public function getReferenceAttribute(): string { return 'EC-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT); }
}
