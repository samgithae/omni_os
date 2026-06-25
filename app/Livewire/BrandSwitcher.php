<?php

namespace App\Livewire;

use App\Models\Brand;
use Livewire\Component;

class BrandSwitcher extends Component
{
    public ?int $activeBrandId = null;

    public function mount(): void
    {
        $this->activeBrandId = session('active_brand_id');
    }

    public function switchBrand(?int $brandId): void
    {
        if ($brandId === null) {
            session()->forget('active_brand_id');
        } else {
            session(['active_brand_id' => $brandId]);
        }
        $this->activeBrandId = $brandId;

        // Full redirect (not a Livewire re-render) so every list, widget, and
        // cached query on the destination page re-evaluates against the new scope.
        $this->redirect(url()->previous());
    }

    public function render()
    {
        return view('livewire.brand-switcher', [
            'brands' => Brand::query()->orderBy('name')->get(),
        ]);
    }
}
