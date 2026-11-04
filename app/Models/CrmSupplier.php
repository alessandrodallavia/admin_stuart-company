<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CrmSupplier extends Model
{
    protected $fillable = ['name', 'website'];

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(CrmBrand::class, 'crm_brand_supplier')->orderBy('name');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(CrmProduct::class, 'crm_product_supplier');
    }
}
