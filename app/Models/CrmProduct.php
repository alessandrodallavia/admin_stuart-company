<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmProduct extends Model
{
    protected $fillable = ['code', 'name', 'slug', 'unit_cost', 'is_active', 'crm_brand_id', 'crm_product_category_id', 'is_visible_on_site', 'supplier_product_url', 'datasheet_path', 'datasheet_filename', 'catalog_model', 'catalog_gender', 'catalog_fit', 'catalog_range', 'catalog_composition', 'catalog_dye', 'catalog_weight_gsm', 'catalog_usage'];

    protected $casts = ['catalog_usage' => 'array', 'catalog_weight_gsm' => 'integer', 'unit_cost' => 'decimal:4', 'is_active' => 'boolean', 'is_visible_on_site' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (CrmProduct $product) {
            if ($product->slug) {
                return;
            }
            $base = substr(Str::slug($product->name.'-'.$product->code), 0, 240);
            if (! preg_match('/[a-z]/', $base)) {
                $base = 'prodotto-'.$base;
            }
            $base = rtrim($base, '-');
            $slug = $base;
            for ($suffix = 2; static::where('slug', $slug)->exists(); $suffix++) {
                $slug = $base.'-'.$suffix;
            }
            $product->slug = $slug;
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CrmProductCategory::class, 'crm_product_category_id');
    }

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

    public function supplierDetails(): HasMany
    {
        return $this->hasMany(CrmProductSupplierDetail::class);
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(CrmProductPriceTier::class)->orderBy('min_quantity');
    }
}
