<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\SequenceSchedule;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SequenceScheduleController extends Controller
{
    public function index(Request $request)
    {
        $query = SequenceSchedule::with('brand');

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->filled('segment')) {
            $query->where('segment', $request->segment);
        }

        $schedules = $query->orderBy('brand_id')->orderBy('segment')->orderBy('step')->paginate(50);

        return Inertia::render('SequenceSchedules/Index', [
            'schedules' => $schedules,
            'brands' => Brand::where('is_active', true)->get(['id', 'name', 'slug']),
            'filters' => $request->only(['brand_id', 'segment']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'brand_id' => 'required|exists:brands,id',
            'segment' => 'required|in:rabbit,deer',
            'step' => 'required|integer|min:1|max:10',
            'days_after_previous' => 'required|integer|min:0',
            'purpose' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        SequenceSchedule::updateOrCreate(
            ['brand_id' => $validated['brand_id'], 'segment' => $validated['segment'], 'step' => $validated['step']],
            $validated
        );

        return redirect()->route('sequence-schedules.index')
            ->with('success', 'Schedule saved.');
    }

    public function update(Request $request, SequenceSchedule $sequenceSchedule)
    {
        $validated = $request->validate([
            'days_after_previous' => 'required|integer|min:0',
            'purpose' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $sequenceSchedule->update($validated);

        return redirect()->route('sequence-schedules.index')
            ->with('success', 'Schedule updated.');
    }

    public function destroy(SequenceSchedule $sequenceSchedule)
    {
        $sequenceSchedule->delete();

        return redirect()->route('sequence-schedules.index')
            ->with('success', 'Schedule deleted.');
    }
}
