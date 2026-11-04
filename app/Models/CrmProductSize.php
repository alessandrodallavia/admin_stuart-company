<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmProductSize extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'sort_order'];
}
