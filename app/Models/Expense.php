<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory;

    public const CATEGORIES = ['Ingredients', 'Utilities', 'Salaries', 'Equipment', 'Rent', 'Other'];

    protected $fillable = [
        'description',
        'category',
        'amount',
        'expense_date',
        'user_id',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
