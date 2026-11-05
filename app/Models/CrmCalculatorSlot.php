<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CrmCalculatorSlot extends Model
{
    protected $fillable = ['crm_product_id', 'is_enabled_on_site'];

    protected $casts = ['is_enabled_on_site' => 'boolean'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(CrmProduct::class, 'crm_product_id');
    }

    public function printTypes(): BelongsToMany
    {
        return $this->belongsToMany(CrmPrintType::class, 'crm_calculator_slot_print_type');
    }

    public function getLabelAttribute(): string
    {
        $model = ['hood' => 'Cappuccio', 'crewneck' => 'Girocollo', 'zip' => 'Zip', 'tshirt' => 'T-shirt'][$this->model] ?? $this->model;

        return $model.' · '.ucfirst($this->quality);
    }
}
