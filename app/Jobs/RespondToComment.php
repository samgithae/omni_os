<?php

namespace App\Jobs;

use App\Models\ActivityEvent;
use App\Models\ActivityEventComment;
use App\Services\CommentResponseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RespondToComment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ActivityEvent $event,
        public ActivityEventComment $comment,
    ) {}

    public function handle(CommentResponseService $responder): void
    {
        try {
            // Generate a contextual Hermes response
            $response = $responder->generate($this->event, $this->comment->body);

            // Post as a Hermes comment on the same event thread
            $this->event->comments()->create([
                'author' => 'hermes',
                'body' => $response,
                'metadata' => [
                    'trigger_comment_id' => $this->comment->id,
                    'is_auto_response' => true,
                ],
                'is_instruction' => false,
            ]);

            // Update instruction status if the original comment was an instruction
            if ($this->comment->is_instruction && $this->comment->instruction_status === 'pending') {
                $this->comment->update([
                    'instruction_status' => 'acknowledged',
                    'acknowledged_at' => now(),
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('RespondToComment failed', [
                'event_id' => $this->event->id,
                'comment_id' => $this->comment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}