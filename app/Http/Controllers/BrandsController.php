<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BrandsController extends Controller
{
    public function index()
    {
        $brands = Brand::withCount('leads')->orderBy('name')->get();

        return Inertia::render('Brands/Index', [
            'brands' => $brands,
        ]);
    }

    public function edit(Brand $brand)
    {
        return Inertia::render('Brands/Edit', [
            'brand' => $brand,
        ]);
    }

    public function update(Request $request, Brand $brand)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:brands,slug,' . $brand->id,
            'description' => 'nullable|string|max:1000',
            'primary_market' => 'nullable|string|max:255',
            'primary_kpi' => 'nullable|string|max:255',
            'brand_voice' => 'nullable|string|max:1000',
            'color' => 'nullable|string|max:7',
            'is_active' => 'boolean',
        ]);

        $brand->update($validated);

        return redirect()->route('brands.index')
            ->with('success', 'Brand updated.');
    }
}
