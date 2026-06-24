<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BrandScope implements Scope
{
    /**
     * UI-convenience scope, NOT an authorization boundary.
     *
     * This scope filters by whatever is in session('active_brand_id'). It does
     * not check who the logged-in user is or what they are permitted to see. A
     * forgotten ->withoutGlobalScope somewhere does not leak data to an
     * unauthorized party, because there is currently only one operator who is
     * authorized for every brand.
     *
     * When a second human (VA, contractor) needs per-brand access control,
     * replace this with Filament's native ->tenant() multi-tenancy + Laravel
     * policies. See work order Section 8/10 for the migration path.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Only apply inside an HTTP session context. Queue jobs, artisan
        // commands, and API requests run without session('active_brand_id')
        // and must stay cross-brand.
        if (app()->runningInConsole()) {
            return;
        }

        if (! session()->has('active_brand_id')) {
            return;
        }

        $brandId = session('active_brand_id');
        if ($brandId !== null) {
            $builder->where($model->getTable() . '.brand_id', $brandId);
        }
    }
}