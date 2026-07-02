<?php

namespace App\Console\Commands;

use App\Models\BrandSequenceConfig;
use Illuminate\Console\Command;

class SeedDeerSequenceConfig extends Command
{
    protected $signature = 'seed:deer-sequence-config';

    protected $description = 'Create deer and hiring-deer BrandSequenceConfig rows';

    public function handle(): int
    {
        // ── Generic Deer config (subcategory='general' — applies to all general Deer leads) ──
        BrandSequenceConfig::updateOrCreate(
            ['brand_id' => 2, 'segment' => 'deer', 'subcategory' => 'general'],
            [
                'source_condition' => null,
                'sequence_steps' => 4,
                'is_active' => true,
                'prompt_text' => "# UjuziPlus Email Sequence Playbook — DEER Segment (Generic)\n"
                    ."\n"
                    ."## OVERVIEW\n"
                    ."Deer leads are organisations that BUY training for their people:\n"
                    ."SACCOs, corporations, NGOs, government agencies, manufacturers,\n"
                    ."financial institutions, hospitals, hotels, schools.\n"
                    ."\n"
                    ."## DUAL VALUE PROPOSITION\n"
                    ."- Platform only: structured learning system with dashboards and certifications\n"
                    ."- Bundle: platform + certified trainer for a specific programme\n"
                    ."\n"
                    ."## DEER MODE — Email Sequence (4 emails)\n"
                    ."Email 1 — Need Opener: Show understanding of their training/compliance needs.\n"
                    ."Email 2 — Peer Story: How a similar organisation solved their training challenge.\n"
                    ."Email 3 — The Offer: Build their learning system. WhatsApp + Calendar CTAs.\n"
                    ."Email 4 — Clean Breakup: Referral ask if timing wrong.\n"
                    ."\n"
                    ."## SACCO-SPECIFIC RULES\n"
                    ."- Pitch: member education portal for financial literacy compliance\n"
                    ."- Email 1 must NOT open with \"SASRA guidelines now require\"\n"
                    ."- Open with genuine observation about member education needs\n"
                    ."\n"
                    ."## CONTENT PHILOSOPHY\n"
                    ."- Relationship-based outreach, never lead with LMS/software\n"
                    ."- Every email must pass the \"would a consultant send this?\" test\n"
                    ."\n"
                    ."## SUBJECT LINE RULES\n"
                    ."- MAX 50 characters. No em dashes.\n"
                    ."- Banned: Quick thought on, Curious if this resonates, Following up\n"
                    ."\n"
                    ."## BODY WRITING RULES\n"
                    ."- MAX 160 words. No em dashes. No bracket placeholders.\n"
                    ."- Sign every email: Samuel (never Samuel Githae)\n"
                    ."- Email 3 includes WhatsApp and Calendar CTAs\n"
                    ."\n"
                    ."## PRE-SEND CHECKLIST\n"
                    ."- Subject under 50 chars, body under 160 words\n"
                    ."- No em dashes or bracket placeholders\n"
                    ."- SACCO Email 1 not opening with SASRA\n"
                    ."- Feels like consultant, not salesperson\n",
            ]
        );
        $this->info('Generic deer sequence config ready.');

        // ── Hiring Deer config (subcategory='hiring' — only Hiring Deer leads) ──
        BrandSequenceConfig::updateOrCreate(
            ['brand_id' => 2, 'segment' => 'deer', 'subcategory' => 'hiring'],
            [
                'source_condition' => 'hiring_signal_%',
                'sequence_steps' => 4,
                'is_active' => true,
                'prompt_text' => "# UjuziPlus Email Sequence Playbook — HIRING DEER\n"
                    ."\n"
                    ."## SCOPE\n"
                    ."This config ONLY applies to leads with `subcategory = 'hiring'`\n"
                    ."(Hiring Deer pipeline). For other Deer leads (`subcategory = 'general'`),\n"
                    ."the generic deer config applies.\n"
                    ."\n"
                    ."## OVERVIEW\n"
                    ."Hiring Deer leads are Kenyan companies with an active multi-role\n"
                    ."hiring signal — a strong proxy for imminent onboarding/training need.\n"
                    ."They are Deer-segment leads (TYPE B — Training Buyers).\n"
                    ."\n"
                    ."## PERSONALISATION HOOK\n"
                    ."- The hiring event IS the hook. Reference the specific roles from\n"
                    ."  `raw_data.hiring_signal.vacancy_titles` and vacancy count from\n"
                    ."  `raw_data.hiring_signal.vacancy_count`.\n"
                    ."\n"
                    ."## CORE PITCH\n"
                    ."- Lead with AI Simulations: \"AI-native onboarding + scored roleplay practice\".\n"
                    ."  NOT a generic LMS pitch.\n"
                    ."\n"
                    ."## Email 1 Structure\n"
                    ."1. Observation about their hiring velocity (specific roles, branches)\n"
                    ."2. Insight about onboarding scale challenges\n"
                    ."3. Curiosity about their current new-hire training approach\n"
                    ."4. Question — the hiring signal is the door, not the pitch\n"
                    ."\n"
                    ."## Banned Hiring-Angle Openers\n"
                    ."- \"I see you're hiring...\" ❌\n"
                    ."- \"Congratulations on your growth...\" ❌\n"
                    ."- \"With your expansion...\" ❌\n"
                    ."- \"As you scale your team...\" ❌\n"
                    ."- \"I noticed your recent job postings...\" ❌\n"
                    ."\n"
                    ."## Effective Openers (do this instead)\n"
                    ."- Connect roles to an operational challenge they create\n"
                    ."- Be specific about industry context, not the hiring event itself\n"
                    ."- Example: \"When a company opens five field officer roles across\n"
                    ."  three counties, onboarding consistency becomes an operational challenge.\"\n"
                    ."\n"
                    ."## SEQUENCE STRUCTURE (4 emails)\n"
                    ."Email 1 — Hiring Need Opener: Hiring velocity observation → onboarding scale\n"
                    ."  insight → question. Lead with AI Simulations angle.\n"
                    ."Email 2 — Peer Story: How a similar high-growth company solved new-hire\n"
                    ."  onboarding with AI-powered roleplay. Directional close.\n"
                    ."Email 3 — The Offer: Build their AI onboarding system. 20-min demo.\n"
                    ."  WhatsApp + Calendar CTAs.\n"
                    ."Email 4 — Clean Breakup: Referral ask if timing wrong.\n"
                    ."\n"
                    ."## CONTENT PHILOSOPHY\n"
                    ."- Relationship-based outreach, never lead with LMS/software\n"
                    ."- Every email must pass the \"would a consultant send this?\" test\n"
                    ."\n"
                    ."## SUBJECT LINE RULES\n"
                    ."- MAX 50 characters. No em dashes.\n"
                    ."- Company name in subject OK for Email 1 only.\n"
                    ."- Banned: Quick thought on, Curious if this resonates, Following up\n"
                    ."\n"
                    ."## BODY WRITING RULES\n"
                    ."- MAX 160 words. No em dashes. No bracket placeholders.\n"
                    ."- Sign every email: Samuel (never Samuel Githae)\n"
                    ."- Email 3 includes WhatsApp: https://wa.link/dshsuz\n"
                    ."  and Calendar: https://calendar.app.google/A9P72gVWPVm9pPBw6\n"
                    ."\n"
                    ."## PRE-SEND CHECKLIST (Hiring Deer additions in bold)\n"
                    ."- Subject under 50 chars, body under 160 words\n"
                    ."- No em dashes or bracket placeholders\n"
                    ."- hiring_signal context woven naturally, not pasted\n"
                    ."- Hiring lead is NOT a SACCO — skip SACCO rules\n"
                    ."- Feels like consultant, not salesperson\n",
            ]
        );
        $this->info('Hiring deer sequence config ready.');

        return self::SUCCESS;
    }
}
