<?php

namespace App\Models;

use Database\Factories\SignatureComboFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A one-click creation shown in the builder: one bread, one flavor and any number of toppings,
 * all pointing at rows of the `components` table.
 */
class SignatureCombo extends Model
{
    /** @use HasFactory<SignatureComboFactory> */
    use HasFactory;

    protected $fillable = ['name', 'bread_id', 'flavor_id', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function bread(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'bread_id');
    }

    public function flavor(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'flavor_id');
    }

    public function toppings(): BelongsToMany
    {
        return $this->belongsToMany(Component::class, 'signature_combo_topping', 'signature_combo_id', 'topping_id')
            ->orderByPivot('id');
    }

    /**
     * What the builder and the home page read: the combo by ingredient name.
     *
     * @return array{name: string, bread: string, flavor: string, toppings: list<string>}
     */
    public function toPreset(): array
    {
        return [
            'name' => $this->name,
            'bread' => $this->bread->name,
            'flavor' => $this->flavor->name,
            'toppings' => $this->toppings->pluck('name')->values()->all(),
        ];
    }

    /**
     * The price of one creation at today's menu prices.
     */
    public function price(): int
    {
        return (int) $this->bread->price + (int) $this->flavor->price + (int) $this->toppings->sum('price');
    }
}
