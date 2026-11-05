<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CrmProductColor extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'name', 'hex', 'sort_order', 'sizes_restricted'];

    protected $casts = ['sizes_restricted' => 'boolean'];

    public function sizes(): BelongsToMany
    {
        return $this->belongsToMany(CrmProductSize::class, 'crm_product_color_sizes', 'crm_product_color_id', 'crm_product_size_id')->orderBy('sort_order')->orderBy('crm_product_sizes.id');
    }
}
