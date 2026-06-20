<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\EmailMessage;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\MiningTarget;
use App\Models\Suppression;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SampleLeadSeeder extends Seeder
{
    public function run(): void
    {
        $faker = FakerFactory::create('en_US');
        $faker->seed(20260620);

        $profiles = [
            'hudutech' => [
                'categories' => ['SME', 'School', 'Manufacturer'],
                'cities' => ['Nairobi', 'Mombasa', 'Nakuru'],
                'targets' => [
                    ['city' => 'Nairobi', 'category' => 'SME', 'segment' => 'deer'],
                    ['city' => 'Nakuru', 'category' => 'School', 'segment' => 'rabbit'],
                ],
            ],
            'ujuziplus' => [
                'categories' => ['Training Provider', 'Corporate L&D', 'SACCO'],
                'cities' => ['Nairobi', 'Kisumu', 'Eldoret'],
                'targets' => [
                    ['city' => 'Nairobi', 'category' => 'Training Provider', 'segment' => 'rabbit'],
                    ['city' => 'Kisumu', 'category' => 'Corporate L&D', 'segment' => 'deer'],
                ],
            ],
            'phantomflix' => [
                'categories' => ['Campus Community', 'Referral Partner', 'Streaming Buyer'],
                'cities' => ['Nairobi', 'Thika', 'Mombasa'],
                'targets' => [
                    ['city' => 'Nairobi', 'category' => 'Referral Partner', 'segment' => 'mouse'],
                    ['city' => 'Mombasa', 'category' => 'Streaming Buyer', 'segment' => 'rabbit'],
                ],
            ],
            'phantom-tutors' => [
                'categories' => ['College Student', 'Parent Referral', 'Exam Prep'],
                'cities' => ['London', 'Manchester', 'Birmingham'],
                'targets' => [
                    ['city' => 'London', 'category' => 'College Student', 'segment' => 'rabbit'],
                    ['city' => 'Manchester', 'category' => 'Exam Prep', 'segment' => 'deer'],
                ],
            ],
        ];

        $statusCycle = ['new', 'enriched', 'enriched', 'no_email_found', 'enriched', 'new'];
        $segmentCycle = ['rabbit', 'deer', 'rabbit', 'mouse', 'deer', 'rabbit'];

        foreach ($profiles as $slug => $profile) {
            $brand = Brand::query()->where('slug', $slug)->first();

            if (! $brand) {
                continue;
            }

            foreach ($profile['targets'] as $target) {
                MiningTarget::query()->updateOrCreate(
                    [
                        'brand_id' => $brand->id,
                        'country' => $slug === 'phantom-tutors' ? 'United Kingdom' : 'Kenya',
                        'city' => $target['city'],
                        'category' => $target['category'],
                    ],
                    [
                        'search_template' => "sample {$target['category']} in {$target['city']}",
                        'segment' => $target['segment'],
                        'cadence' => 'weekly',
                        'is_active' => true,
                    ],
                );
            }

            for ($index = 0; $index < 6; $index++) {
                $companyName = sprintf(
                    '%s %s %s',
                    $faker->company(),
                    Str::headline($slug),
                    ['Collective', 'Studio', 'Group', 'Partners', 'Hub', 'Works'][$index]
                );

                $hasEmail = $index !== 3;
                $email = $hasEmail ? sprintf('%s-%02d@example.test', $slug, $index + 1) : null;
                $status = $hasEmail ? $statusCycle[$index] : 'no_email_found';
                $country = $slug === 'phantom-tutors' ? 'United Kingdom' : 'Kenya';
                $city = $profile['cities'][$index % count($profile['cities'])];
                $category = $profile['categories'][$index % count($profile['categories'])];

                $lead = Lead::query()->updateOrCreate(
                    [
                        'brand_id' => $brand->id,
                        'company_name' => $companyName,
                    ],
                    [
                        'contact_name' => $faker->name(),
                        'email' => $email,
                        'phone' => $faker->e164PhoneNumber(),
                        'website' => $hasEmail ? sprintf('https://%s.example.test', Str::slug($companyName)) : null,
                        'segment' => $segmentCycle[$index],
                        'category' => $category,
                        'subcategory' => $faker->randomElement(['SMB', 'Growth', 'Enterprise']),
                        'country' => $country,
                        'city' => $city,
                        'address' => $faker->streetAddress(),
                        'status' => $status,
                        'enrichment_attempts' => $hasEmail ? 1 : 2,
                        'email_verified' => $hasEmail,
                        'score' => $hasEmail ? 55 + ($index * 5) : 20,
                        'source' => 'sample_seed',
                        'source_url' => $hasEmail ? sprintf('https://source.example.test/%s/%02d', $slug, $index + 1) : null,
                        'raw_data' => [
                            'seed' => 'sample',
                            'brand' => $slug,
                            'city' => $city,
                            'category' => $category,
                        ],
                    ],
                );

                LeadEvent::query()->updateOrCreate(
                    [
                        'lead_id' => $lead->id,
                        'brand_id' => $brand->id,
                        'event_type' => 'imported',
                        'source' => 'sample_seed',
                    ],
                    [
                        'payload' => ['source' => 'sample_seed'],
                    ],
                );

                if ($hasEmail) {
                    LeadEvent::query()->updateOrCreate(
                        [
                            'lead_id' => $lead->id,
                            'brand_id' => $brand->id,
                            'event_type' => $status === 'new' ? 'enriched' : 'emailed',
                            'source' => 'sample_seed',
                        ],
                        [
                            'payload' => ['status' => $status],
                        ],
                    );
                }

                if ($hasEmail && $index < 3) {
                    EmailMessage::query()->updateOrCreate(
                        [
                            'lead_id' => $lead->id,
                            'sequence_step' => 1,
                        ],
                        [
                            'brand_id' => $brand->id,
                            'subject' => "Sample intro for {$companyName}",
                            'body' => "Hi {$lead->contact_name},\n\nThis is sample seeded outreach for local development only.\n",
                            'status' => $index === 0 ? 'sent' : ($index === 1 ? 'queued' : 'draft'),
                            'approval_status' => $index === 2 ? 'pending' : 'approved',
                            'approved_at' => $index === 2 ? null : now()->subDays(2),
                            'scheduled_for' => $index === 1 ? now()->addDay() : null,
                            'sent_at' => $index === 0 ? now()->subDay() : null,
                            'opened_at' => $index === 0 ? now()->subHours(12) : null,
                            'clicked_at' => $index === 0 ? now()->subHours(6) : null,
                            'approval_notes' => 'Sample-only seeded data',
                        ],
                    );
                }
            }

            $suppressedLead = Lead::query()
                ->where('brand_id', $brand->id)
                ->where('source', 'sample_seed')
                ->where('email', 'like', '%@example.test')
                ->first();

            if ($suppressedLead) {
                Suppression::query()->updateOrCreate(
                    [
                        'brand_id' => $brand->id,
                        'email' => $suppressedLead->email,
                    ],
                    [
                        'reason' => 'manual',
                        'notes' => 'Sample-only suppression for local development.',
                    ],
                );
            }
        }
    }
}
