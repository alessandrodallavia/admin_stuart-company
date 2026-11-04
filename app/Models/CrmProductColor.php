<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmProductColor extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'name', 'hex', 'sort_order'];
}
