<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmPrintType extends Model
{
    protected $fillable = ['code', 'name', 'is_active', 'technique', 'preview_layout', 'size_label', 'allowed_sides', 'setup_print_type_id', 'fixed_price', 'max_width_cm', 'max_height_cm'];

    protected $casts = ['is_active' => 'boolean', 'allowed_sides' => 'array', 'fixed_price' => 'decimal:2'];

    public function priceTiers(): HasMany
    {
        return $this->hasMany(CrmPrintPriceTier::class)->orderBy('min_quantity');
    }
}
