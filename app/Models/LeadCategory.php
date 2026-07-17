<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadCategory extends Model
{
    protected $fillable = ['name', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
