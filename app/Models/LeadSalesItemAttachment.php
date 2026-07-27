<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadSalesItemAttachment extends Model
{
    protected $fillable = ['lead_sales_item_id', 'disk', 'path', 'filename', 'mime_type', 'size'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(LeadSalesItem::class, 'lead_sales_item_id');
    }
}
