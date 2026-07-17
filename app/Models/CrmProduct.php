<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class CrmProduct extends Model { protected $fillable=['code','name','unit_cost','is_active']; protected $casts=['unit_cost'=>'decimal:4','is_active'=>'boolean']; public function priceTiers(): HasMany { return $this->hasMany(CrmProductPriceTier::class)->orderBy('min_quantity'); } }
