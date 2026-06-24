<?php

namespace App\Models\Concerns;

use App\Models\Scopes\BrandScope;

/**
 * Trait for models that have a brand_id column.
 *
 * Adds:
 * 1. A global BrandScope that filters queries by session('active_brand_id')
 *    in HTTP context only (console/API stay cross-brand).
 * 2. An auto-assign creating hook that sets brand_id from the active session
 *    brand when a record is created while a specific brand is selected.
 *
 * The brand() relationship is NOT declared here — each model declares its own
 * to avoid method-duplication conflicts.
 */
trait BelongsToBrand
{
    protected static function bootBelongsToBrand(): void
    {
        static::addGlobalScope(new BrandScope);

        // Auto-assign brand_id on create when a specific brand is active.
        // When "All" is active (session value null) brand_id is left untouched,
        // so the form field stays required and the operator must choose.
        static::creating(function ($model) {
            if (empty($model->brand_id) && session()->has('active_brand_id')) {
                $brandId = session('active_brand_id');
                if ($brandId !== null) {
                    $model->brand_id = $brandId;
                }
            }
        });
    }
}