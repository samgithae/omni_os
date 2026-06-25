<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\MiningTarget;
use Illuminate\Http\Request;

class MiningTargetController extends Controller
{
    public function index(Request $request)
    {
        $query = MiningTarget::query()->with('brand:id,name,slug');

        if ($brandSlug = $request->get('brand_slug')) {
            $brand = Brand::where('slug', $brandSlug)->first();
            if ($brand) {
                $query->where('brand_id', $brand->id);
            }
        }

        if ($segment = $request->get('segment')) {
            $query->where('segment', $segment);
        }

        if ($country = $request->get('country')) {
            $query->where('country', $country);
        }

        if ($city = $request->get('city')) {
            $query->where('city', $city);
        }

        $query->where('is_active', true);

        return response()->json($query->get()->map(fn ($t) => [
            'id' => $t->id,
            'brand_slug' => $t->brand->slug,
            'country' => $t->country,
            'city' => $t->city,
            'category' => $t->category,
            'search_template' => $t->search_template,
            'segment' => $t->segment,
            'cadence' => $t->cadence,
            'last_mined_at' => $t->last_mined_at?->toIso8601String(),
        ]));
    }

    public function markMined(Request $request, MiningTarget $miningTarget)
    {
        $validated = $request->validate([
            'leads_found' => ['nullable', 'integer', 'min:0'],
        ]);

        $miningTarget->update([
            'last_mined_at' => now(),
        ]);

        return response()->json([
            'message' => 'Mining target marked as mined.',
            'target_id' => $miningTarget->id,
            'last_mined_at' => $miningTarget->fresh()->last_mined_at->toIso8601String(),
        ]);
    }
}
