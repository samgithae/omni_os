<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Suppression;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SuppressionController extends Controller
{
    public function index(Request $request)
    {
        $query = Suppression::with('brand');

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->filled('reason')) {
            $query->where('reason', $request->reason);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('email', 'ilike', "%{$s}%")
                  ->orWhere('notes', 'ilike', "%{$s}%");
            });
        }

        $suppressions = $query->orderByDesc('id')->paginate(25);

        return Inertia::render('Suppressions/Index', [
            'suppressions' => $suppressions,
            'brands' => Brand::where('is_active', true)->get(['id', 'name', 'slug', 'color']),
            'filters' => $request->only(['brand_id', 'reason', 'search']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'brand_id' => 'required|exists:brands,id',
            'email' => 'required|email|max:255',
            'reason' => 'required|in:unsubscribe,hard_bounce,complaint,manual',
            'notes' => 'nullable|string|max:1000',
        ]);

        Suppression::updateOrCreate(
            ['brand_id' => $validated['brand_id'], 'email' => $validated['email']],
            $validated
        );

        return redirect()->route('suppressions.index')
            ->with('success', 'Suppression added.');
    }

    public function destroy(Suppression $suppression)
    {
        $suppression->delete();

        return redirect()->route('suppressions.index')
            ->with('success', 'Suppression removed.');
    }
}
