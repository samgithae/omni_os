<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClassifiedReplyRequest;
use App\Services\ReplyService;

class ReplyController extends Controller
{
    /**
     * Receive a classified reply from Hermes and route it to its outcome.
     *
     * Expects:
     *   - email_message_id: the sent email the user replied to
     *   - lead_id: the lead who replied
     *   - classification: interested|not_interested|out_of_office|unsubscribe|bounce
     *   - summary: human-readable summary of the reply
     *   - reply_body: the full reply text (optional)
     *   - confidence: 0-1 confidence score (optional)
     */
    public function store(StoreClassifiedReplyRequest $request, ReplyService $replyService)
    {
        $data = $request->validated();

        $result = $replyService->route($data);

        if (! $result['success']) {
            return response()->json($result, 422);
        }

        return response()->json($result, 201);
    }
}
