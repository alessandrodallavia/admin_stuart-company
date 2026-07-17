<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CrmPrintPriceTier extends Model { protected $fillable=['crm_print_type_id','min_quantity','max_quantity','unit_cost','unit_price']; protected $casts=['min_quantity'=>'decimal:2','max_quantity'=>'decimal:2','unit_cost'=>'decimal:4','unit_price'=>'decimal:4']; }
