<?php

namespace Database\Seeders;

use App\Models\Component;
use Illuminate\Database\Seeder;

class ComponentSeeder extends Seeder
{
    public function run(): void
    {
        $components = [
            // Bases
            ['name' => 'Plain White Bread', 'category' => 'bread', 'price' => 6000, 'hex_color' => null],
            ['name' => 'Whole Wheat Bread', 'category' => 'bread', 'price' => 7000, 'hex_color' => null],

            // Ice cream flavors
            ['name' => 'Vanilla', 'category' => 'ice_cream', 'price' => 8000, 'hex_color' => '#F3E9D2'],
            ['name' => 'Strawberry', 'category' => 'ice_cream', 'price' => 8000, 'hex_color' => '#F8B8C4'],
            ['name' => 'Chocolate', 'category' => 'ice_cream', 'price' => 8000, 'hex_color' => '#5C3A21'],

            // Toppings
            ['name' => 'Chocolate Sauce', 'category' => 'topping', 'price' => 3000, 'hex_color' => '#3D1B13'],
            ['name' => 'Rainbow Sprinkles', 'category' => 'topping', 'price' => 3000, 'hex_color' => null],
        ];

        foreach ($components as $component) {
            Component::updateOrCreate(
                ['name' => $component['name']],
                $component + ['is_active' => true]
            );
        }

        Component::whereNotIn('name', array_column($components, 'name'))->delete();
    }
}
