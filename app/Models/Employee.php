<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'position',
        'phone',
        'rotation_order',
        'is_active',
    ];

    protected $casts = [
        'rotation_order' => 'integer',
        'is_active' => 'boolean',
    ];
}
