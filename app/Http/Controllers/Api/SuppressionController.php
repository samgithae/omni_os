<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Suppression;
use Illuminate\Http\Request;

class SuppressionController extends Controller
{
    public function check(Request $request)
    {
        $validated = $request->validate([
            'brand_slug' => ['required', 'string', 'exists:brands,slug'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $brand = Brand::where('slug', $validated['brand_slug'])->firstOrFail();

        $suppressed = Suppression::query()
            ->where('brand_id', $brand->id)
            ->where('email', $validated['email'])
            ->exists();

        $suppression = null;
        if ($suppressed) {
            $suppression = Suppression::query()
                ->where('brand_id', $brand->id)
                ->where('email', $validated['email'])
                ->first(['reason', 'notes']);
        }

        return response()->json([
            'email' => $validated['email'],
            'brand_slug' => $validated['brand_slug'],
            'suppressed' => $suppressed,
            'reason' => $suppression?->reason,
            'notes' => $suppression?->notes,
        ]);
    }
}
