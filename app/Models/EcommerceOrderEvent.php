<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EcommerceOrderEvent extends Model {
 protected $guarded = [];
 protected $casts = ['details' => 'array', 'notified_at' => 'datetime'];
 public function order() { return $this->belongsTo(EcommerceOrder::class, 'ecommerce_order_id'); }
 public function actor() { return $this->belongsTo(AdminUser::class, 'admin_user_id'); }
}
