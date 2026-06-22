<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActivityEventCommentRequest;
use App\Models\ActivityEvent;

class ActivityEventCommentController extends Controller
{
    /**
     * Get all comments for an event thread.
     */
    public function index(ActivityEvent $event)
    {
        $comments = $event->comments()->with('event:id,title,body,metadata,brand_id')
            ->get()
            ->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'author' => $comment->author,
                    'body' => $comment->body,
                    'metadata' => $comment->metadata,
                    'is_instruction' => $comment->is_instruction,
                    'instruction_status' => $comment->instruction_status,
                    'created_at' => $comment->created_at->toIso8601String(),
                    'relative_time' => $comment->created_at->diffForHumans(),
                ];
            });

        return response()->json(['comments' => $comments]);
    }

    /**
     * Hermes/Agent posts a comment to an event thread.
     */
    public function store(StoreActivityEventCommentRequest $request, ActivityEvent $event)
    {
        $data = $request->validated();

        $comment = $event->comments()->create([
            'author' => $data['author'],
            'body' => $data['body'],
            'metadata' => $data['metadata'] ?? null,
            'is_instruction' => false,
        ]);

        return response()->json([
            'comment' => [
                'id' => $comment->id,
                'author' => $comment->author,
                'body' => $comment->body,
                'metadata' => $comment->metadata,
                'created_at' => $comment->created_at->toIso8601String(),
                'relative_time' => $comment->created_at->diffForHumans(),
            ],
        ], 201);
    }
}