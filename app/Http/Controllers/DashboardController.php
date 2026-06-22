<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\EmailMessage;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\Suppression;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $totalLeads = Lead::count();
        $enrichedLeads = Lead::where('status', 'enriched')->count();
        $newLeads = Lead::where('status', 'new')->count();
        $noEmailLeads = Lead::where('status', 'no_email_found')->count();
        $totalEmails = Lead::whereNotNull('email')->count();
        $suppressedCount = Suppression::count();
        $activeBrands = Brand::where('is_active', true)->count();

        $rabbits = Lead::where('segment', 'rabbit')->count();
        $deer = Lead::where('segment', 'deer')->count();

        // Email sequence stats
        $totalEmailMessages = EmailMessage::count();
        $pendingApproval = EmailMessage::where('approval_status', 'pending')->count();
        $approvedEmails = EmailMessage::where('approval_status', 'approved')->count();
        $rejectedEmails = EmailMessage::where('approval_status', 'rejected')->count();
        $sentEmails = EmailMessage::where('status', 'sent')->count();
        $queuedEmails = EmailMessage::where('status', 'queued')->count();
        $draftEmails = EmailMessage::where('status', 'draft')->count();
        $failedEmails = EmailMessage::where('status', 'failed')->count();
        $openedEmails = EmailMessage::whereNotNull('opened_at')->count();
        $clickedEmails = EmailMessage::whereNotNull('clicked_at')->count();
        $leadsWithEmailSequences = Lead::has('emailMessages')->count();

        // Leads by brand
        $leadsByBrand = Brand::select('id', 'name', 'slug', 'color')
            ->withCount(['leads'])
            ->get()
            ->map(function ($brand) {
                return [
                    'name' => $brand->name,
                    'slug' => $brand->slug,
                    'color' => $brand->color,
                    'leads_count' => $brand->leads_count,
                ];
            });

        // Leads by segment
        $leadsBySegment = Lead::selectRaw('segment, COUNT(*) as count')
            ->groupBy('segment')
            ->pluck('count', 'segment')
            ->toArray();

        // Leads by status
        $leadsByStatus = Lead::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Top cities
        $topCities = Lead::selectRaw('city, COUNT(*) as count')
            ->whereNotNull('city')
            ->groupBy('city')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'city')
            ->toArray();

        // Email sequence by step
        $emailsByStep = EmailMessage::selectRaw('sequence_step, COUNT(*) as count')
            ->groupBy('sequence_step')
            ->orderBy('sequence_step')
            ->pluck('count', 'sequence_step')
            ->toArray();

        // Email approval breakdown
        $emailApprovalBreakdown = [
            'pending' => $pendingApproval,
            'approved' => $approvedEmails,
            'rejected' => $rejectedEmails,
        ];

        // Email send status breakdown
        $emailStatusBreakdown = [
            'draft' => $draftEmails,
            'queued' => $queuedEmails,
            'sent' => $sentEmails,
            'failed' => $failedEmails,
        ];

        // Recent events (activity feed)
        $recentEvents = LeadEvent::with('lead:id,company_name,email')
            ->select('id', 'lead_id', 'brand_id', 'event_type', 'created_at')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'company' => $event->lead?->company_name ?? 'Unknown',
                    'created_at' => $event->created_at->diffForHumans(),
                ];
            });

        // Lead scoring stats
        $avgScore = $totalLeads > 0 ? round(Lead::avg('score'), 1) : 0;
        $scoreTiers = [
            'hot' => Lead::where('score', '>=', 80)->count(),
            'warm' => Lead::whereBetween('score', [60, 79])->count(),
            'moderate' => Lead::whereBetween('score', [40, 59])->count(),
            'cold' => Lead::whereBetween('score', [20, 39])->count(),
            'frigid' => Lead::where('score', '<', 20)->count(),
        ];

        // Top scored leads
        $topLeads = Lead::with('brand:id,name,slug,color')
            ->select('id', 'company_name', 'email', 'segment', 'city', 'score', 'status', 'brand_id')
            ->orderByDesc('score')
            ->limit(10)
            ->get()
            ->map(function ($lead) {
                return [
                    'id' => $lead->id,
                    'company_name' => $lead->company_name,
                    'email' => $lead->email,
                    'segment' => $lead->segment,
                    'city' => $lead->city,
                    'score' => $lead->score,
                    'status' => $lead->status,
                    'brand' => $lead->brand ? [
                        'name' => $lead->brand->name,
                        'slug' => $lead->brand->slug,
                        'color' => $lead->brand->color,
                    ] : null,
                ];
            });

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_leads' => $totalLeads,
                'enriched_leads' => $enrichedLeads,
                'new_leads' => $newLeads,
                'no_email_leads' => $noEmailLeads,
                'total_emails' => $totalEmails,
                'suppressed' => $suppressedCount,
                'active_brands' => $activeBrands,
                'rabbits' => $rabbits,
                'deer' => $deer,
                // Email sequence stats
                'total_email_messages' => $totalEmailMessages,
                'pending_approval' => $pendingApproval,
                'approved_emails' => $approvedEmails,
                'rejected_emails' => $rejectedEmails,
                'sent_emails' => $sentEmails,
                'queued_emails' => $queuedEmails,
                'draft_emails' => $draftEmails,
                'failed_emails' => $failedEmails,
                'opened_emails' => $openedEmails,
                'clicked_emails' => $clickedEmails,
                'leads_with_sequences' => $leadsWithEmailSequences,
            ],
            'leadsByBrand' => $leadsByBrand,
            'leadsBySegment' => $leadsBySegment,
            'leadsByStatus' => $leadsByStatus,
            'topCities' => $topCities,
            'recentEvents' => $recentEvents,
            'emailsByStep' => $emailsByStep,
            'emailApprovalBreakdown' => $emailApprovalBreakdown,
            'emailStatusBreakdown' => $emailStatusBreakdown,
            'avgScore' => $avgScore,
            'scoreTiers' => $scoreTiers,
            'topLeads' => $topLeads,
        ]);
    }
}