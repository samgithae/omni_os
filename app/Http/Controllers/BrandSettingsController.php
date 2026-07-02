<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BrandSettingsController extends Controller
{
    /**
     * Show brand settings page.
     */
    public function edit(Brand $brand)
    {
        // Load all brands for the brand switcher
        $allBrands = Brand::select('id', 'name', 'slug', 'color', 'is_active')
            ->orderBy('name')
            ->get();

        return Inertia::render('Brands/Settings', [
            'brand' => [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'description' => $brand->description,
                'primary_market' => $brand->primary_market,
                'primary_kpi' => $brand->primary_kpi,
                'brand_voice' => $brand->brand_voice,
                'color' => $brand->color,
                'is_active' => $brand->is_active,
                'sender_name' => $brand->sender_name,
                'sender_emails' => $brand->sender_emails ?? [],
                'settings' => $brand->settings ?? [],
            ],
            'brands' => $allBrands,
        ]);
    }

    /**
     * Update brand settings.
     */
    public function update(Request $request, Brand $brand)
    {
        $validated = $request->validate([
            'sender_name' => ['nullable', 'string', 'max:255'],
            'sender_emails' => ['nullable', 'array'],
            'sender_emails.*' => ['email:rfc,dns'],
            'settings' => ['nullable', 'array'],
            // MCP Configuration
            'codex_api_key' => ['nullable', 'string'],
            'elementor_mcp_endpoint' => ['nullable', 'string', 'url'],
            'elementor_mcp_auth' => ['nullable', 'string'],
            'elementor_mcp_enabled' => ['boolean'],
        ]);

        // Update standard fields
        if (isset($validated['sender_name'])) {
            $brand->sender_name = $validated['sender_name'];
        }

        if (isset($validated['sender_emails'])) {
            $brand->sender_emails = array_values(array_unique($validated['sender_emails'] ?? []));
        }

        if (isset($validated['settings'])) {
            $existing = $brand->settings ?? [];
            $brand->settings = array_merge($existing, $validated['settings']);
        }

        // Update MCP fields
        if (isset($validated['codex_api_key']) || isset($validated['elementor_mcp_endpoint']) || isset($validated['elementor_mcp_auth']) || isset($validated['elementor_mcp_enabled'])) {
            if (isset($validated['codex_api_key'])) {
                $brand->codex_api_key = $validated['codex_api_key'];
            }
            if (isset($validated['elementor_mcp_endpoint'])) {
                $brand->elementor_mcp_endpoint = $validated['elementor_mcp_endpoint'];
            }
            if (isset($validated['elementor_mcp_auth'])) {
                $brand->elementor_mcp_auth = $validated['elementor_mcp_auth'];
            }
            if (isset($validated['elementor_mcp_enabled'])) {
                $brand->elementor_mcp_enabled = $validated['elementor_mcp_enabled'];
            }
            
            $brand->save();
        } else {
            $brand->save();
        }

        return back()->with('success', 'Brand settings saved.');
    }
}
