<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandSequenceConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SequenceConfigController extends Controller
{
    /**
     * GET /api/v1/sequence-configs/{brand_slug}/{segment}
     * Returns the active config for the given brand+segment.
     * Optional ?subcategory= param for subcategory-specific rules (e.g. subcategory=hiring).
     * Segment+subcategory-specific config wins over segment+general fallback.
     */
    public function show(string $brandSlug, string $segment, Request $request): JsonResponse
    {
        $brand = Brand::where('slug', $brandSlug)->where('is_active', true)->firstOrFail();

        $config = BrandSequenceConfig::resolveFor(
            $brand->id,
            $segment,
            $request->get('subcategory'),
        );

        if (! $config) {
            return response()->json([
                'error' => "No active sequence config found for brand '{$brandSlug}', segment '{$segment}'. Create one in Filament → Configuration → Sequence Configs.",
            ], 404);
        }

        return response()->json([
            'brand_id' => $brand->id,
            'brand_slug' => $brand->slug,
            'brand_name' => $brand->name,
            'segment' => $config->segment,
            'sequence_steps' => $config->sequence_steps,
            'prompt_text' => $config->prompt_text,
        ]);
    }
}
