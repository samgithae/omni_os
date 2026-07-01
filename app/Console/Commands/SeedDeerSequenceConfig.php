<?php

namespace App\Console\Commands;

use App\Models\BrandSequenceConfig;
use Illuminate\Console\Command;

class SeedDeerSequenceConfig extends Command
{
    protected $signature = 'seed:deer-sequence-config';

    protected $description = 'Create the deer-specific BrandSequenceConfig with Hiring Deer rules';

    public function handle(): int
    {
        $existing = BrandSequenceConfig::where('brand_id', 2)->where('segment', 'deer')->first();
        if ($existing) {
            $this->info("Deer config already exists (ID: {$existing->id})");

            return self::SUCCESS;
        }

        $promptText = "# UjuziPlus Email Sequence Playbook — DEER Segment\n"
            ."\n"
            ."## CRITICAL: THIS IS THE DEER-SPECIFIC PLAYBOOK\n"
            ."Applies to leads with `segment=deer` including Hiring Deer pipeline leads\n"
            ."(source starts with `hiring_signal_`). For Rabbit or other segments, the\n"
            ."generic `all` playbook is used as fallback.\n"
            ."\n"
            ."## OVERVIEW\n"
            ."Deer leads are organisations that BUY training for their people:\n"
            ."SACCOs, corporations, NGOs, government agencies, manufacturers, financial\n"
            ."institutions, hospitals, hotels, schools.\n"
            ."\n"
            ."Two sub-types:\n"
            ."1. Standard Deer — identified via Google Maps/Search mining (SACCOs, NGOs, etc.)\n"
            ."2. Hiring Deer — identified via job-board mining (active multi-role hiring signal)\n"
            ."\n"
            ."## UJUZIPLUS DUAL VALUE PROPOSITION\n"
            ."UjuziPlus is always a PLATFORM — never a training company.\n"
            ."\n"
            ."For Deer (TYPE B — Training Buyers):\n"
            ."- Platform only: structured learning system with dashboards and certifications\n"
            ."- Bundle: platform + certified trainer for a specific programme\n"
            ."- Pitch: \"A structured learning system for your team — and if you need content, we bring a certified trainer too.\"\n"
            ."\n"
            ."## DEER MODE — Email Sequence (4 emails)\n"
            ."\n"
            ."Email 1 — Need Opener: Show understanding of their training/compliance needs.\n"
            ."Open with observation, ask a question. For SACCOs: member education needs.\n"
            ."\n"
            ."Email 2 — Peer Story: How a similar organisation solved their training challenge.\n"
            ."Directional close.\n"
            ."\n"
            ."Email 3 — The Offer: Build their learning system inside UjuziPlus with their\n"
            ."branding. Bundle offer if appropriate. 20-min demo. WhatsApp + Calendar CTAs.\n"
            ."\n"
            ."Email 4 — Clean Breakup: Everything comes back to their people equipped.\n"
            ."If timing wrong, ask for referral.\n"
            ."\n"
            ."## SACCO-SPECIFIC RULES\n"
            ."- Pitch: member education portal for financial literacy compliance (SASRA records)\n"
            ."- Bundle angle: \"We can also connect you with a certified financial literacy trainer.\"\n"
            ."- Email 1 must NOT open with \"SASRA guidelines now require\"\n"
            ."- Open with genuine observation about member education needs instead\n"
            ."\n"
            ."## HIRING DEER PIPELINE RULES\n"
            ."For leads whose `raw_data` contains a `hiring_signal` object\n"
            ."(source starts with `hiring_signal_`), these rules OVERRIDE the standard DEER rules:\n"
            ."\n"
            ."### Personalisation Hook\n"
            ."- The hiring event IS the hook. Reference the specific roles from\n"
            ."  `raw_data.hiring_signal.vacancy_titles` and vacancy count from\n"
            ."  `raw_data.hiring_signal.vacancy_count` — NOT a generic Deer opener.\n"
            ."\n"
            ."### Core Pitch\n"
            ."- Lead with AI Simulations: \"AI-native onboarding + scored roleplay practice\".\n"
            ."  NOT a generic LMS pitch. Consistent with AI-native-vs-bolt-on positioning.\n"
            ."\n"
            ."### Email 1 Structure\n"
            ."1. Observation about their hiring velocity (specific roles, multiple branches)\n"
            ."2. Insight about onboarding scale challenges\n"
            ."3. Curiosity about their current new-hire training approach\n"
            ."4. Question — the hiring signal is the door, not the pitch\n"
            ."\n"
            ."### Banned Hiring-Angle Openers\n"
            ."- \"I see you're hiring...\" ❌\n"
            ."- \"Congratulations on your growth...\" ❌\n"
            ."- \"With your expansion...\" ❌\n"
            ."- \"As you scale your team...\" ❌\n"
            ."- \"I noticed your recent job postings...\" ❌\n"
            ."- Generic hiring cliché is worse than no hiring reference at all\n"
            ."\n"
            ."### Effective Openers (do this instead)\n"
            ."- Connect the roles to an operational challenge they create\n"
            ."- Be specific about the industry context, not the hiring event itself\n"
            ."- Example: \"When a company opens five field officer roles across three counties,\n"
            ."  onboarding consistency becomes an operational challenge.\"\n"
            ."\n"
            ."## CONTENT PHILOSOPHY\n"
            ."- Relationship-based outreach: genuine interest, observation before insight\n"
            ."- Never lead with UjuziPlus, LMS, software, or features\n"
            ."- Every email must pass the \"would a consultant send this?\" test\n"
            ."- Start conversations, not demos\n"
            ."\n"
            ."## SUBJECT LINE RULES\n"
            ."- MAX 50 characters\n"
            ."- Company name in subject OK for Email 1 only\n"
            ."- Never use: \"Quick thought on\", \"Curious if this resonates\", \"Following up\"\n"
            ."- No em dashes in subjects\n"
            ."\n"
            ."## BODY WRITING RULES\n"
            ."- MAX 160 words. Strict check: len(body.split()) <= 160\n"
            ."- No em dashes. No bracket placeholders.\n"
            ."- Sign every email: Samuel (never Samuel Githae)\n"
            ."- Email 3 includes WhatsApp: https://wa.link/dshsuz and Calendar: https://calendar.app.google/A9P72gVWPVm9pPBw6\n"
            ."\n"
            ."## FORBIDDEN PATTERNS\n"
            ."- Em dashes, bracket placeholders, INDUSTRY_HOOK prefixes\n"
            ."- References to LMS, software, SaaS\n"
            ."- Banned subject openers: Quick thought on, Following up, Checking in\n"
            ."\n"
            ."## OUTPUT FORMAT\n"
            ."Return ONLY valid JSON — no markdown, no preamble.\n"
            ."\n"
            ."## PRE-SEND CHECKLIST\n"
            ."- Subject under 50 chars\n"
            ."- Body under 160 words\n"
            ."- No em dashes\n"
            ."- No bracket placeholders visible\n"
            ."- Sign-off: Samuel (not Samuel Githae)\n"
            ."- SACCO Email 1 not opening with SASRA\n"
            ."- WhatsApp + Calendar CTAs in Email 3\n"
            ."- Feels like consultant, not salesperson\n"
            ."- Hiring lead: hiring_signal woven naturally, not pasted\n"
            ."- Hiring lead: skip SACCO rules (not a SACCO)\n";

        BrandSequenceConfig::create([
            'brand_id' => 2,
            'segment' => 'deer',
            'sequence_steps' => 4,
            'is_active' => true,
            'prompt_text' => $promptText,
        ]);

        $this->info('Created deer-specific sequence config.');

        return self::SUCCESS;
    }
}
