<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\EmailMessage;
use App\Models\Lead;
use App\Models\Reply;
use App\Models\Suppression;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InboxController extends Controller
{
    public function index(Request $request)
    {
        $query = Reply::with(['lead:id,company_name,email,segment,city,score,status,brand_id', 'brand:id,name,slug,color'])
            ->inbound()
            ->orderByDesc('received_at');

        // Brand filter
        if ($request->filled('brand')) {
            $query->whereHas('brand', fn ($q) => $q->where('slug', $request->brand));
        }

        // Classification filter
        if ($request->filled('classification')) {
            if ($request->classification === 'unclassified') {
                $query->whereNull('classification')->orWhere('classification', 'unclassified');
            } else {
                $query->where('classification', $request->classification);
            }
        }

        // Read/unread filter
        if ($request->filled('read')) {
            if ($request->read === 'unread') {
                $query->unread();
            } elseif ($request->read === 'read') {
                $query->where('read', true);
            }
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'ilike', "%{$search}%")
                    ->orWhere('body', 'ilike', "%{$search}%")
                    ->orWhere('from_email', 'ilike', "%{$search}%")
                    ->orWhereHas('lead', fn ($q) => $q->where('company_name', 'ilike', "%{$search}%"));
            });
        }

        $replies = $query->paginate(20)->through(function ($reply) {
            return [
                'id' => $reply->id,
                'from_email' => $reply->from_email,
                'subject' => $reply->subject,
                'body' => $reply->body,
                'body_html' => $reply->body_html,
                'classification' => $reply->classification,
                'classification_summary' => $reply->classification_summary,
                'read' => $reply->read,
                'received_at' => $reply->received_at?->toIso8601String(),
                'lead' => $reply->lead ? [
                    'id' => $reply->lead->id,
                    'company_name' => $reply->lead->company_name,
                    'email' => $reply->lead->email,
                    'segment' => $reply->lead->segment,
                    'city' => $reply->lead->city,
                    'score' => $reply->lead->score,
                    'status' => $reply->lead->status,
                ] : null,
                'brand' => $reply->brand ? [
                    'id' => $reply->brand->id,
                    'name' => $reply->brand->name,
                    'slug' => $reply->brand->slug,
                    'color' => $reply->brand->color,
                ] : null,
            ];
        });

        // Unread count
        $unreadCount = Reply::inbound()->unread()->count();

        // Brands for filter
        $brands = Brand::where('is_active', true)->get(['id', 'name', 'slug', 'color']);

        return Inertia::render('Inbox/Index', [
            'replies' => $replies,
            'unreadCount' => $unreadCount,
            'filters' => $request->only(['brand', 'classification', 'read', 'search']),
            'brands' => $brands,
        ]);
    }

    /**
     * Fetch conversation thread for a specific lead (replies + sent emails).
     */
    public function conversation(Request $request, Lead $lead)
    {
        // Get all replies (inbound + outbound) for this lead
        $replies = Reply::where('lead_id', $lead->id)
            ->orderBy('received_at')
            ->get()
            ->map(function ($reply) {
                return [
                    'id' => $reply->id,
                    'type' => 'reply',
                    'direction' => $reply->direction,
                    'from_email' => $reply->from_email,
                    'subject' => $reply->subject,
                    'body' => $reply->body,
                    'body_html' => $reply->body_html,
                    'classification' => $reply->classification,
                    'classification_summary' => $reply->classification_summary,
                    'read' => $reply->read,
                    'received_at' => $reply->received_at?->toIso8601String(),
                    'created_at' => $reply->created_at?->toIso8601String(),
                ];
            });

        // Get sent emails for this lead (the outbound sequence emails)
        $sentEmails = EmailMessage::where('lead_id', $lead->id)
            ->orderBy('sent_at')
            ->get()
            ->map(function ($email) {
                return [
                    'id' => $email->id,
                    'type' => 'email',
                    'direction' => 'outbound',
                    'from_email' => config('mail.from.address'),
                    'subject' => $email->subject,
                    'body' => $email->body,
                    'sequence_step' => $email->sequence_step,
                    'status' => $email->status,
                    'sent_at' => $email->sent_at?->toIso8601String(),
                    'opened_at' => $email->opened_at?->toIso8601String(),
                    'clicked_at' => $email->clicked_at?->toIso8601String(),
                ];
            });

        // Merge and sort by time (use sent_at for emails, received_at for replies, created_at as fallback)
        $thread = $replies->merge($sentEmails)->sortBy(function ($item) {
            return $item['sent_at'] ?? $item['received_at'] ?? $item['created_at'] ?? now()->toIso8601String();
        })->values();

        // Lead context
        $leadData = [
            'id' => $lead->id,
            'company_name' => $lead->company_name,
            'email' => $lead->email,
            'segment' => $lead->segment,
            'city' => $lead->city,
            'score' => $lead->score,
            'status' => $lead->status,
            'is_suppressed' => $lead->isSuppressed(),
            'brand' => $lead->brand ? [
                'name' => $lead->brand->name,
                'color' => $lead->brand->color,
            ] : null,
        ];

        // Mark inbound replies as read when viewing
        Reply::where('lead_id', $lead->id)
            ->where('direction', 'inbound')
            ->where('read', false)
            ->update(['read' => true]);

        return response()->json([
            'thread' => $thread,
            'lead' => $leadData,
        ]);
    }

    /**
     * Send a reply to a lead (human-initiated, bypasses approval gate).
     */
    public function reply(Request $request, Lead $lead)
    {
        $request->validate([
            'body' => 'required|string|min:1',
            'subject' => 'nullable|string|max:255',
        ]);

        // Suppression check — never reply to a suppressed lead
        if (Suppression::where('brand_id', $lead->brand_id)
            ->where('email', $lead->email)
            ->exists()) {
            return back()->withErrors(['body' => 'This lead is suppressed — cannot send.']);
        }

        // Create outbound reply record
        $reply = Reply::create([
            'lead_id' => $lead->id,
            'brand_id' => $lead->brand_id,
            'from_email' => config('mail.from.address'),
            'subject' => $request->subject ?: 'Re: ' . ($lead->emailMessages()->latest()->first()?->subject ?? 'Your inquiry'),
            'body' => $request->body,
            'direction' => 'outbound',
            'read' => true,
            'received_at' => now(),
        ]);

        // Dispatch the send job
        \App\Jobs\SendLeadReply::dispatch($lead, $request->body, $reply);

        // Log to activity feed
        app(\App\Services\ActivityLogger::class)->log([
            'brand_id' => $lead->brand_id,
            'source' => 'inbox.reply',
            'event_type' => 'email_sent_batch',
            'title' => "Reply sent to {$lead->company_name}",
            'body' => substr($request->body, 0, 500),
            'metadata' => [
                'lead_id' => $lead->id,
                'reply_id' => $reply->id,
                'to_email' => $lead->email,
            ],
            'severity' => 'success',
        ]);

        return back()->with('success', 'Reply sent to ' . $lead->email);
    }
}