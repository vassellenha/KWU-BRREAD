<?php

namespace Database\Seeders;

use App\Models\Component;
use App\Models\SignatureCombo;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

/**
 * Runs on every deploy but only puts the starter combos in place once. After that the Menu module owns them,
 * so a combo the admin deleted or changed does not come back.
 */
class SignatureComboSeeder extends Seeder
{
    private const SEEDED_KEY = 'signature_combos_seeded';

    public function run(): void
    {
        if (SiteSetting::allValues()[self::SEEDED_KEY] ?? null) {
            return;
        }

        $starters = [
            ['Classic Vanilla Slab', 'Plain White Bread', 'Vanilla', []],
            ['Strawberry Sprinkle Stack', 'Plain White Bread', 'Strawberry', ['Rainbow Sprinkles']],
            ['Wholegrain Choco Crunch', 'Whole Wheat Bread', 'Chocolate', []],
            ['Wheat Vanilla Drizzle', 'Whole Wheat Bread', 'Vanilla', ['Chocolate Sauce']],
            ['Neapolitan Trio Slab', 'Plain White Bread', 'Neapolitan', []],
            ['Musang King Wheat Slab', 'Whole Wheat Bread', 'Durian Musang King', []],
            ['Es Teler Sprinkle Stack', 'Plain White Bread', 'Es Teler', ['Rainbow Sprinkles']],
        ];

        $ids = Component::pluck('id', 'name');

        // A fresh install may not have every item yet; the flag is only set when all starters could be placed.
        $complete = true;

        foreach ($starters as $position => [$name, $bread, $flavor, $toppings]) {
            $needed = [$bread, $flavor, ...$toppings];

            if (collect($needed)->contains(fn (string $item) => ! $ids->has($item))) {
                $complete = false;

                continue;
            }

            $combo = SignatureCombo::firstOrCreate(
                ['name' => $name],
                ['bread_id' => $ids[$bread], 'flavor_id' => $ids[$flavor], 'is_active' => true, 'sort_order' => $position + 1],
            );

            if ($combo->wasRecentlyCreated) {
                $combo->toppings()->sync(collect($toppings)->map(fn (string $item) => $ids[$item])->all());
            }
        }

        if ($complete) {
            SiteSetting::put([self::SEEDED_KEY => '1']);
        }
    }
}
