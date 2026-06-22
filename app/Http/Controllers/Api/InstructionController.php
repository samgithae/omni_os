<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityEventComment;
use Illuminate\Http\Request;

class InstructionController extends Controller
{
    /**
     * Get the pending instruction queue.
     * Hermes polls this before a run to see what Sam has flagged.
     */
    public function index(Request $request)
    {
        $request->validate([
            'brand' => ['nullable', 'string', 'exists:brands,slug'],
            'status' => ['nullable', 'string', 'in:pending,acknowledged,addressed'],
        ]);

        $query = ActivityEventComment::with([
            'event:id,title,body,metadata,brand_id',
            'event.brand:id,slug,name',
        ])->where('is_instruction', true);

        if ($request->filled('status')) {
            $query->where('instruction_status', $request->status);
        } else {
            $query->where('instruction_status', 'pending');
        }

        // Brand filter via parent event
        if ($request->filled('brand')) {
            $brand = \App\Models\Brand::where('slug', $request->brand)->first();
            if ($brand) {
                $query->whereHas('event', fn ($q) => $q->where('brand_id', $brand->id));
            }
        } else {
            // No brand filter = control-tower: only show comments on events with no brand
            $query->whereHas('event', fn ($q) => $q->whereNull('brand_id'));
        }

        $instructions = $query->latest()->take(50)->get()->map(function ($comment) {
            return [
                'id' => $comment->id,
                'body' => $comment->body,
                'instruction_status' => $comment->instruction_status,
                'created_at' => $comment->created_at->toIso8601String(),
                'event' => $comment->event ? [
                    'id' => $comment->event->id,
                    'title' => $comment->event->title,
                    'body' => $comment->event->body,
                    'metadata' => $comment->event->metadata,
                    'brand' => $comment->event->brand ? [
                        'slug' => $comment->event->brand->slug,
                        'name' => $comment->event->brand->name,
                    ] : null,
                ] : null,
            ];
        });

        return response()->json([
            'instructions' => $instructions,
            'count' => count($instructions),
        ]);
    }

    /**
     * Update instruction status (acknowledged / addressed).
     */
    public function update(Request $request, ActivityEventComment $comment)
    {
        $request->validate([
            'instruction_status' => ['required', 'string', 'in:acknowledged,addressed'],
        ]);

        if (!$comment->is_instruction) {
            return response()->json(['message' => 'Comment is not an instruction.'], 422);
        }

        $update = [
            'instruction_status' => $request->instruction_status,
        ];

        if ($request->instruction_status === 'acknowledged' && !$comment->acknowledged_at) {
            $update['acknowledged_at'] = now();
        }

        $comment->update($update);

        return response()->json([
            'message' => "Instruction {$request->instruction_status}.",
            'id' => $comment->id,
            'instruction_status' => $comment->instruction_status,
            'acknowledged_at' => $comment->acknowledged_at?->toIso8601String(),
        ]);
    }
}