<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmProductSupplierDetail extends Model
{
    public $timestamps = false;

    // Supplier evidence is read-only here; PDFs can be attached separately.
    protected $guarded = ['*'];

    protected $casts = ['source_checked_at' => 'date', 'weight_gsm' => 'integer'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(CrmSupplier::class, 'crm_supplier_id');
    }
}
