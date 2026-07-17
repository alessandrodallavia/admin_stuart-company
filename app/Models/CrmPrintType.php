<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class CrmPrintType extends Model { protected $fillable=['code','name','is_active']; protected $casts=['is_active'=>'boolean']; public function priceTiers(): HasMany { return $this->hasMany(CrmPrintPriceTier::class)->orderBy('min_quantity'); } }
