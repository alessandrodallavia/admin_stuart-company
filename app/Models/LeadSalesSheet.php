<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class LeadSalesSheet extends Model { protected $fillable=['lead_id','revenue_total','cost_total','margin_total','margin_percentage','notes']; protected $casts=['revenue_total'=>'decimal:2','cost_total'=>'decimal:2','margin_total'=>'decimal:2','margin_percentage'=>'decimal:2']; public function lead(): BelongsTo{return $this->belongsTo(Lead::class);} public function items(): HasMany{return $this->hasMany(LeadSalesItem::class);} }
