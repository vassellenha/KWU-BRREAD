<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Component;
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
        ]);
    }
}
