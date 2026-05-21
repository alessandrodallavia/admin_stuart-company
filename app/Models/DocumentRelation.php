<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;

class DocumentRelation extends Model
{
    protected $fillable = [
        'from_type',
        'from_id',
        'to_type',
        'to_id',
        'relation_type',
    ];

    protected $casts = [
        'from_type' => DocumentType::class,
        'to_type' => DocumentType::class,
    ];
}
