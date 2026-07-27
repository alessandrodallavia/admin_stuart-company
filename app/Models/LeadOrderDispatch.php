<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadOrderDispatch extends Model
{
    protected $fillable = [
        'lead_sales_sheet_id', 'admin_user_id', 'email_message_id', 'order_name', 'version',
        'filename', 'to_email', 'cc_email', 'status', 'error_message', 'sent_at',
    ];

    protected $casts = ['sent_at' => 'datetime'];

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(LeadSalesSheet::class, 'lead_sales_sheet_id');
    }
}
