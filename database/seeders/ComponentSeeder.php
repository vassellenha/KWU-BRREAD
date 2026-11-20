<?php

namespace Database\Seeders;

use App\Models\Component;
use Illuminate\Database\Seeder;

/**
 * Runs on every deploy. It only puts the starter menu in place when an item is missing:
 * the Menu module of the admin portal owns the catalog from then on, so prices, names,
 * colors, switched-off items and anything the admin added are never touched here.
 */
class ComponentSeeder extends Seeder
{
    public function run(): void
    {
        $components = [
            // Bases
            ['name' => 'Plain White Bread', 'category' => 'bread', 'price' => 6000, 'hex_color' => null, 'stock_quantity' => 200, 'unit' => 'pcs', 'reorder_point' => 50],
            ['name' => 'Whole Wheat Bread', 'category' => 'bread', 'price' => 7000, 'hex_color' => null, 'stock_quantity' => 150, 'unit' => 'pcs', 'reorder_point' => 50],

            // Ice cream flavors
            ['name' => 'Vanilla', 'category' => 'ice_cream', 'price' => 8000, 'hex_color' => '#F3E9D2', 'stock_quantity' => 120, 'unit' => 'pcs', 'reorder_point' => 40],
            ['name' => 'Strawberry', 'category' => 'ice_cream', 'price' => 8000, 'hex_color' => '#F8B8C4', 'stock_quantity' => 120, 'unit' => 'pcs', 'reorder_point' => 40],
            ['name' => 'Chocolate', 'category' => 'ice_cream', 'price' => 8000, 'hex_color' => '#5C3A21', 'stock_quantity' => 35, 'unit' => 'pcs', 'reorder_point' => 40],
            // Multi-layer flavors: same price and stock levels as Vanilla and Strawberry; the 3D Studio draws
            // them from scripts/build_studio3d_flavors.py, the colour is the swatch in the picker.
            ['name' => 'Neapolitan', 'category' => 'ice_cream', 'price' => 8000, 'hex_color' => '#EE8AA3', 'stock_quantity' => 120, 'unit' => 'pcs', 'reorder_point' => 40],
            ['name' => 'Durian Musang King', 'category' => 'ice_cream', 'price' => 8000, 'hex_color' => '#D6DC7A', 'stock_quantity' => 120, 'unit' => 'pcs', 'reorder_point' => 40],
            ['name' => 'Es Teler', 'category' => 'ice_cream', 'price' => 8000, 'hex_color' => '#F5B82E', 'stock_quantity' => 120, 'unit' => 'pcs', 'reorder_point' => 40],

            // Toppings
            ['name' => 'Chocolate Sauce', 'category' => 'topping', 'price' => 3000, 'hex_color' => '#3D1B13', 'stock_quantity' => 300, 'unit' => 'pcs', 'reorder_point' => 60],
            ['name' => 'Rainbow Sprinkles', 'category' => 'topping', 'price' => 3000, 'hex_color' => null, 'stock_quantity' => 300, 'unit' => 'pcs', 'reorder_point' => 60],
        ];

        foreach ($components as $component) {
            Component::firstOrCreate(['name' => $component['name']], $component + ['is_active' => true]);
        }
    }
}
