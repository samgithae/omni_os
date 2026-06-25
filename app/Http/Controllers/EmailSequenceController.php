<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\EmailMessage;
use App\Models\Lead;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmailSequenceController extends Controller
{
    public function index(Request $request)
    {
        $query = Lead::with(['brand', 'emailMessages' => function ($q) {
            $q->orderBy('sequence_step');
        }]);

        $query->whereHas('emailMessages');

        // Brand filter
        if ($request->filled('brand')) {
            $query->whereHas('brand', fn ($q) => $q->where('slug', $request->brand));
        }

        // Segment filter
        if ($request->filled('segment')) {
            $query->where('segment', $request->segment);
        }

        // Approval filter — checks if ANY email has that approval status
        if ($request->filled('approval')) {
            $query->whereHas('emailMessages', fn ($q) => $q->where('approval_status', $request->approval));
        }

        // Sequence progress filter
        if ($request->filled('progress')) {
            switch ($request->progress) {
                case 'not_started':
                    $query->whereDoesntHave('emailMessages');
                    break;
                case 'in_progress':
                    $query->whereHas('emailMessages', fn ($q) => $q->where('status', 'draft'))
                        ->orWhereHas('emailMessages', fn ($q) => $q->where('approval_status', 'pending'));
                    break;
                case 'completed':
                    $query->whereDoesntHave('emailMessages', fn ($q) => $q->where('status', '!=', 'sent'));
                    break;
            }
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('contact_name', 'ilike', "%{$search}%")
                    ->orWhereHas('emailMessages', fn ($q) => $q->where('subject', 'ilike', "%{$search}%"));
            });
        }

        $leads = $query->orderBy('company_name')
            ->paginate(20)
            ->through(function ($lead) {
                $steps = collect(range(1, 5))->map(function ($step) use ($lead) {
                    $email = $lead->emailMessages->firstWhere('sequence_step', $step);

                    return [
                        'step' => $step,
                        'exists' => $email !== null,
                        'subject' => $email?->subject,
                        'body' => $email?->body,
                        'approval_status' => $email?->approval_status,
                        'send_status' => $email?->status,
                        'sent_at' => $email?->sent_at?->toIso8601String(),
                        'opened_at' => $email?->opened_at?->toIso8601String(),
                        'clicked_at' => $email?->clicked_at?->toIso8601String(),
                        'scheduled_for' => $email?->scheduled_for?->toIso8601String(),
                        'id' => $email?->id,
                    ];
                });

                return [
                    'id' => $lead->id,
                    'company_name' => $lead->company_name,
                    'email' => $lead->email,
                    'contact_name' => $lead->contact_name,
                    'segment' => $lead->segment,
                    'city' => $lead->city,
                    'brand' => $lead->brand ? [
                        'id' => $lead->brand->id,
                        'name' => $lead->brand->name,
                        'slug' => $lead->brand->slug,
                        'color' => $lead->brand->color,
                    ] : null,
                    'steps' => $steps,
                    'has_pending' => $steps->contains(fn ($s) => $s['exists'] && $s['approval_status'] === 'pending'),
                    'sequence_complete' => $lead->hasCompleteEmailSequence(),
                    'missing_steps' => $lead->missingEmailSequenceSteps(),
                ];
            });

        // Aggregate stats
        $stats = [
            'total' => EmailMessage::count(),
            'needs_content' => EmailMessage::where('approval_status', 'needs_content')->count(),
            'pending' => EmailMessage::where('approval_status', 'pending')->count(),
            'approved' => EmailMessage::where('approval_status', 'approved')->count(),
            'rejected' => EmailMessage::where('approval_status', 'rejected')->count(),
            'sent' => EmailMessage::where('status', 'sent')->count(),
            'opened' => EmailMessage::whereNotNull('opened_at')->count(),
            'clicked' => EmailMessage::whereNotNull('clicked_at')->count(),
        ];

        $brands = Brand::where('is_active', true)->get(['id', 'name', 'slug', 'color']);

        return Inertia::render('EmailSequences/Index', [
            'leads' => $leads,
            'stats' => $stats,
            'filters' => $request->only(['brand', 'segment', 'approval', 'progress', 'search']),
            'brands' => $brands,
        ]);
    }

    public function bulkApprove(Request $request)
    {
        $request->validate([
            'lead_ids' => 'required|array',
            'lead_ids.*' => 'exists:leads,id',
        ]);

        // Completeness gate — check every lead has a complete sequence before approving
        $incompleteLeads = [];
        foreach ($request->lead_ids as $leadId) {
            $lead = Lead::with('emailMessages')->find($leadId);
            if ($lead) {
                $complete = $lead->hasCompleteEmailSequence();
                if ($complete === false) {
                    $missing = $lead->missingEmailSequenceSteps();
                    $incompleteLeads[] = "Lead {$leadId} ({$lead->company_name}) — missing steps [".implode(',', $missing).']';
                }
            }
        }

        if (! empty($incompleteLeads)) {
            return back()->withErrors([
                'completeness' => 'Cannot approve — incomplete sequences: '.implode('; ', $incompleteLeads),
            ]);
        }

        $count = EmailMessage::whereIn('lead_id', $request->lead_ids)
            ->where('approval_status', 'pending')
            ->update([
                'approval_status' => 'approved',
                'approved_at' => now(),
                'status' => 'queued',
            ]);

        return back()->with('success', "Approved {$count} emails.");
    }

    public function bulkReject(Request $request)
    {
        $request->validate([
            'lead_ids' => 'required|array',
            'lead_ids.*' => 'exists:leads,id',
        ]);

        $count = EmailMessage::whereIn('lead_id', $request->lead_ids)
            ->where('approval_status', 'pending')
            ->update([
                'approval_status' => 'rejected',
                'rejected_at' => now(),
            ]);

        return back()->with('success', "Rejected {$count} emails.");
    }

    public function approve(EmailMessage $emailMessage)
    {
        // Completeness gate — verify the lead's sequence is complete before approving
        $lead = $emailMessage->lead()->with('emailMessages')->first();
        if ($lead) {
            $complete = $lead->hasCompleteEmailSequence();
            if ($complete === false) {
                $missing = $lead->missingEmailSequenceSteps();

                return back()->withErrors([
                    'completeness' => "Cannot approve — the lead's email sequence is incomplete. Missing steps: [".implode(',', $missing).']. Wait for Hermes to generate the missing emails.',
                ]);
            }
        }

        $emailMessage->approve();

        return back()->with('success', 'Email approved.');
    }

    public function reject(EmailMessage $emailMessage)
    {
        $emailMessage->reject();

        return back()->with('success', 'Email rejected.');
    }
}
