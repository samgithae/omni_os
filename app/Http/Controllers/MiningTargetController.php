<?php

namespace App\Http\Controllers;

use App\Models\MiningTarget;
use App\Models\Brand;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MiningTargetController extends Controller
{
    public function index(Request $request)
    {
        $query = MiningTarget::with('brand');

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->filled('segment')) {
            $query->where('segment', $request->segment);
        }

        if ($request->filled('country')) {
            $query->where('country', $request->country);
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->active === 'true');
        }

        $targets = $query->orderBy('country')->orderBy('city')->paginate(25);

        return Inertia::render('MiningTargets/Index', [
            'targets' => $targets,
            'brands' => Brand::where('is_active', true)->get(['id', 'name', 'slug']),
            'filters' => $request->only(['brand_id', 'segment', 'country', 'active']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'brand_id' => 'required|exists:brands,id',
            'country' => 'required|string|max:100',
            'city' => 'nullable|string|max:255',
            'category' => 'required|string|max:255',
            'search_template' => 'nullable|string|max:255',
            'segment' => 'required|in:rabbit,deer,mouse,elephant',
            'cadence' => 'required|in:daily,weekly,monthly',
            'is_active' => 'boolean',
        ]);

        MiningTarget::create($validated);

        return redirect()->route('mining-targets.index')
            ->with('success', 'Mining target created.');
    }

    public function destroy(MiningTarget $miningTarget)
    {
        $miningTarget->delete();

        return redirect()->route('mining-targets.index')
            ->with('success', 'Mining target deleted.');
    }

    public function toggleActive(MiningTarget $miningTarget)
    {
        $miningTarget->update(['is_active' => !$miningTarget->is_active]);

        return redirect()->route('mining-targets.index')
            ->with('success', 'Mining target updated.');
    }
}
