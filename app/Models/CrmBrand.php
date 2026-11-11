<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmBrand extends Model
{
    protected $fillable = ['name'];

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(CrmSupplier::class, 'crm_brand_supplier')->orderBy('name');
    }

    public function products(): HasMany
    {
        return $this->hasMany(CrmProduct::class);
    }
}
