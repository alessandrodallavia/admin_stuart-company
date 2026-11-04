<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmProduct extends Model
{
    protected $fillable = ['code', 'name', 'unit_cost', 'is_active', 'crm_brand_id', 'is_visible_on_site', 'supplier_product_url', 'datasheet_path', 'datasheet_filename'];

    protected $casts = ['unit_cost' => 'decimal:4', 'is_active' => 'boolean', 'is_visible_on_site' => 'boolean'];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(CrmBrand::class, 'crm_brand_id');
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(CrmSupplier::class, 'crm_product_supplier')->orderBy('name');
    }

    public function colors(): HasMany
    {
        return $this->hasMany(CrmProductColor::class)->orderBy('sort_order')->orderBy('id');
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(CrmProductSize::class)->orderBy('sort_order')->orderBy('id');
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(CrmProductPriceTier::class)->orderBy('min_quantity');
    }
}
