<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\BrandSequenceConfig;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SequenceConfigController extends Controller
{
    public function index(Request $request)
    {
        $query = BrandSequenceConfig::with('brand');

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->filled('segment')) {
            $query->where('segment', $request->segment);
        }

        $configs = $query->orderBy('brand_id')->paginate(25);

        return Inertia::render('SequenceConfigs/Index', [
            'configs' => $configs,
            'brands' => Brand::where('is_active', true)->get(['id', 'name', 'slug']),
            'filters' => $request->only(['brand_id', 'segment']),
        ]);
    }

    public function create()
    {
        return Inertia::render('SequenceConfigs/Edit', [
            'config' => null,
            'brands' => Brand::where('is_active', true)->get(['id', 'name', 'slug']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'brand_id' => 'required|exists:brands,id',
            'segment' => 'required|in:all,rabbit,deer,mouse,elephant',
            'subcategory' => 'required|string|max:255',
            'prompt_text' => 'required|string',
            'sequence_steps' => 'required|integer|min:1|max:10',
            'is_active' => 'boolean',
        ]);

        BrandSequenceConfig::create($validated);

        return redirect()->route('sequence-configs.index')
            ->with('success', 'Sequence config created.');
    }

    public function edit(BrandSequenceConfig $brandSequenceConfig)
    {
        return Inertia::render('SequenceConfigs/Edit', [
            'config' => $brandSequenceConfig->load('brand'),
            'brands' => Brand::where('is_active', true)->get(['id', 'name', 'slug']),
        ]);
    }

    public function update(Request $request, BrandSequenceConfig $brandSequenceConfig)
    {
        $validated = $request->validate([
            'brand_id' => 'required|exists:brands,id',
            'segment' => 'required|in:all,rabbit,deer,mouse,elephant',
            'subcategory' => 'required|string|max:255',
            'prompt_text' => 'required|string',
            'sequence_steps' => 'required|integer|min:1|max:10',
            'is_active' => 'boolean',
        ]);

        $brandSequenceConfig->update($validated);

        return redirect()->route('sequence-configs.index')
            ->with('success', 'Sequence config updated.');
    }

    public function destroy(BrandSequenceConfig $brandSequenceConfig)
    {
        $brandSequenceConfig->delete();

        return redirect()->route('sequence-configs.index')
            ->with('success', 'Sequence config deleted.');
    }
}
