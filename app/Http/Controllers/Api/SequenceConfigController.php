<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandSequenceConfig;
use Illuminate\Http\JsonResponse;

class SequenceConfigController extends Controller
{
    /**
     * GET /api/v1/sequence-configs/{brand_slug}/{segment}
     * Returns the active config for the given brand+segment.
     * Segment-specific config wins over 'all' fallback.
     */
    public function show(string $brandSlug, string $segment): JsonResponse
    {
        $brand = Brand::where('slug', $brandSlug)->where('is_active', true)->firstOrFail();

        $config = BrandSequenceConfig::resolveFor($brand->id, $segment);

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
