<?php

use App\Models\Brand;
use App\Models\BrandSequenceConfig;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $brand = Brand::where('slug', 'ujuziplus')->first();
        if (! $brand) {
            return;
        }

        $config = BrandSequenceConfig::resolveFor($brand->id, 'deer');
        if (! $config) {
            $config = BrandSequenceConfig::resolveFor($brand->id, 'all');
        }
        if (! $config) {
            return;
        }

        $existing = $config->prompt_text;

        $hiringSection = "\n\n## SECTION 14: Hiring Deer Pipeline Rules\n\n"
            ."For leads whose `raw_data` contains a `hiring_signal` object (source starts with\n"
            ."`hiring_signal_`), these rules OVERRIDE the standard DEER rules:\n\n"
            ."### Personalisation Hook\n"
            ."- The hiring event IS the hook. Reference the specific roles from\n"
            ."  `raw_data.hiring_signal.vacancy_titles` and vacancy count from\n"
            ."  `raw_data.hiring_signal.vacancy_count` — NOT a generic Deer opener.\n\n"
            ."### Core Pitch\n"
            ."- Lead with AI Simulations: \"AI-native onboarding + scored roleplay practice\".\n"
            ."  NOT a generic LMS pitch. Consistent with AI-native-vs-bolt-on positioning.\n\n"
            ."### Email 1 Structure\n"
            ."1. Observation about their hiring velocity (specific roles, multiple branches)\n"
            ."2. Insight about onboarding scale challenges\n"
            ."3. Curiosity about their current new-hire training approach\n"
            ."4. Question — the hiring signal is the door, not the pitch\n\n"
            ."### Banned Hiring-Angle Openers\n"
            ."- \"I see you're hiring...\" ❌\n"
            ."- \"Congratulations on your growth...\" ❌\n"
            ."- \"With your expansion...\" ❌\n"
            ."- \"As you scale your team...\" ❌\n"
            ."- \"I noticed your recent job postings...\" ❌\n"
            ."- Generic hiring cliché is worse than no hiring reference at all\n\n"
            ."### Effective Openers (do this instead)\n"
            ."- Connect the roles to an operational challenge they create\n"
            ."- Be specific about the industry context, not the hiring event itself\n"
            ."- Example: \"When a company opens five field officer roles across three counties,\n"
            ."  onboarding consistency becomes an operational challenge.\"\n\n"
            ."### Pre-Send Additions (applied in addition to Section 13)\n"
            ."- Hiring lead is not SACCO — skip SACCO rules\n"
            ."- Verify hiring_signal context is woven naturally, not pasted\n"
            ."- The hiring event should feel like context, not the pitch\n";

        $config->update(['prompt_text' => $existing.$hiringSection]);
    }

    public function down(): void
    {
        $brand = Brand::where('slug', 'ujuziplus')->first();
        if (! $brand) {
            return;
        }

        $config = BrandSequenceConfig::resolveFor($brand->id, 'deer');
        if (! $config) {
            $config = BrandSequenceConfig::resolveFor($brand->id, 'all');
        }
        if (! $config) {
            return;
        }

        // Remove the hiring section — it starts after the SACCO rules
        $existing = $config->prompt_text;
        $pos = strpos($existing, "\n\n## SECTION 14: Hiring Deer Pipeline Rules");
        if ($pos !== false) {
            $config->update(['prompt_text' => substr($existing, 0, $pos)]);
        }
    }
};
