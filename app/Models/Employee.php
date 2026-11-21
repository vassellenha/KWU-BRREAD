<?php

namespace App\Models;

use App\Support\RowVersion;
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

    /** Fingerprint of what the HR page edits, for its "save all" (see App\Support\RowVersion). */
    public function rowVersion(): string
    {
        return RowVersion::of($this, ['name', 'position', 'phone', 'rotation_order', 'is_active']);
    }

    protected $casts = [
        'rotation_order' => 'integer',
        'is_active' => 'boolean',
    ];
}
