<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CrmProductPriceTier extends Model { protected $fillable=['crm_product_id','min_quantity','max_quantity','unit_price']; protected $casts=['min_quantity'=>'decimal:2','max_quantity'=>'decimal:2','unit_price'=>'decimal:4']; public function product(): BelongsTo { return $this->belongsTo(CrmProduct::class,'crm_product_id'); } }
