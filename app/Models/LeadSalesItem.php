<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class LeadSalesItem extends Model { protected $fillable=['lead_sales_sheet_id','crm_product_id','product_code','product_name','configuration_name','quantity','product_unit_cost','product_unit_price','revenue_total','cost_total','margin_total']; protected $casts=['quantity'=>'decimal:2','product_unit_cost'=>'decimal:4','product_unit_price'=>'decimal:4','revenue_total'=>'decimal:2','cost_total'=>'decimal:2','margin_total'=>'decimal:2']; public function prints(): HasMany{return $this->hasMany(LeadSalesItemPrint::class);} }
