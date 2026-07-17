<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LeadSalesItemPrint extends Model { protected $fillable=['lead_sales_item_id','crm_print_type_id','print_code','print_name','unit_cost','unit_price']; protected $casts=['unit_cost'=>'decimal:4','unit_price'=>'decimal:4']; }
