<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Component;
use App\Models\SignatureCombo;
use Illuminate\Http\JsonResponse;

class ComponentController extends Controller
{
    public function index(): JsonResponse
    {
        $components = Component::where('is_active', true)
            ->orderBy('price')
            ->orderBy('id')
            ->get()
            ->groupBy('category');

        return response()->json([
            'bread' => $components->get('bread', collect())->values(),
            'ice_cream' => $components->get('ice_cream', collect())->values(),
            'topping' => $components->get('topping', collect())->values(),
            // Combos keep showing when one of their items is hidden or sold out; the builder greys them out.
            'signatures' => SignatureCombo::with(['bread', 'flavor', 'toppings'])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (SignatureCombo $combo) => $combo->toPreset())
                ->values(),
        ]);
    }
}
