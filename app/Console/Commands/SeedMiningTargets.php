<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\MiningTarget;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;

class SeedMiningTargets extends Command
{
    protected $signature = 'mining:seed-targets
        {--append : Skip truncating existing targets, just append}
        {--brand= : Only seed targets for a specific brand slug}';

    protected $description = 'Seed mining_targets with geo config for UjuziPlus and Hudutech';

    /**
     * Geo targets organized by priority tier.
     * Each tier maps to a cadence: daily for tier 1, weekly for tier 2-3, monthly for tier 4.
     */
    private array $geoByPriority = [
        1 => [ // First Priority: Kenya — daily cadence
            'Kenya' => [
                'Nairobi', 'Mombasa', 'Kisumu', 'Nakuru', 'Eldoret',
                'Thika', 'Kiambu', 'Machakos', 'Kitengela', 'Ruiru',
                'Juja', 'Nyeri', 'Meru', 'Embu', 'Nanyuki',
                'Kericho', 'Kakamega', 'Bungoma', 'Malindi', 'Kilifi',
                'Naivasha', 'Narok', 'Garissa', 'Isiolo', 'Voi',
            ],
        ],
        2 => [ // Second Priority: East Africa — weekly cadence
            'Uganda' => ['Kampala', 'Entebbe', 'Jinja', 'Mbarara', 'Gulu'],
            'Tanzania' => ['Dar es Salaam', 'Arusha', 'Mwanza', 'Dodoma', 'Mbeya'],
            'Rwanda' => ['Kigali', 'Musanze', 'Huye'],
            'Burundi' => ['Bujumbura', 'Gitega'],
        ],
        3 => [ // Third Priority: English-Speaking Africa — weekly cadence
            'Nigeria' => ['Lagos', 'Abuja', 'Port Harcourt', 'Ibadan', 'Kano'],
            'Ghana' => ['Accra', 'Kumasi', 'Takoradi'],
            'South Africa' => ['Johannesburg', 'Cape Town', 'Durban', 'Pretoria', 'Port Elizabeth'],
            'Zambia' => ['Lusaka', 'Ndola', 'Kitwe'],
            'Zimbabwe' => ['Harare', 'Bulawayo'],
        ],
        4 => [ // Fourth Priority: Global Expansion — monthly cadence
            'United Kingdom' => ['London', 'Manchester', 'Birmingham', 'Leeds', 'Liverpool'],
            'United States' => ['New York City', 'Houston', 'Dallas', 'Atlanta', 'Washington', 'Los Angeles', 'Chicago'],
            'Canada' => ['Toronto', 'Ottawa', 'Calgary', 'Edmonton', 'Vancouver'],
            'Australia' => ['Sydney', 'Melbourne', 'Brisbane', 'Perth'],
            'New Zealand' => ['Auckland', 'Wellington'],
            'United Arab Emirates' => ['Dubai', 'Abu Dhabi', 'Sharjah'],
        ],
    ];

    /**
     * UjuziPlus: corporate training / LMS / upskilling
     * Rabbit = private training providers, small consulting firms, TVETs
     * Deer = SACCOs, universities, NGOs, government agencies, large corporations
     */
    private array $ujuziPlusCategories = [
        'rabbit' => [
            'name' => 'Private Training Provider',
            'template' => '{category} {city}',
            'keywords' => [
                'Private Training Provider', 'Training Consultant', 'TVET Institution',
                'Vocational Training Centre', 'Professional Development Firm',
                'Corporate Training Company', 'Skills Development Provider',
                'E-learning Platform', 'Training Institute',
            ],
        ],
        'deer' => [
            'name' => 'Corporate Training Department',
            'template' => '{category} in {city}',
            'keywords' => [
                'SACCO', 'Sacco Society', 'Credit Union',
                'University', 'College', 'Higher Education Institution',
                'NGO', 'Non-Governmental Organization', 'International NGO',
                'Government Agency', 'Ministry', 'State Corporation',
                'Insurance Company', 'Bank', 'Microfinance Institution',
                'Manufacturing Company', 'Hospital', 'Hotel Chain',
            ],
        ],
    ];

    /**
     * Hudutech: Odoo ERP / POS / AI automation
     * Rabbit = SMEs, retail shops, restaurants, small manufacturing
     * Deer = manufacturers, distributors, schools, NGOs, government
     */
    private array $hudutechCategories = [
        'rabbit' => [
            'name' => 'Small Business / Retail',
            'template' => '{category} {city}',
            'keywords' => [
                'Retail Shop', 'General Store', 'Supermarket',
                'Restaurant', 'Cafe', 'Hotel',
                'Small Business', 'Startup', 'SME',
                'Wholesaler', 'Distributor',
                'Pharmacy', 'Chemist', 'Hardware Store',
            ],
        ],
        'deer' => [
            'name' => 'Medium-Large Enterprise',
            'template' => '{category} in {city}',
            'keywords' => [
                'Manufacturing Company', 'Factory', 'Production Plant',
                'School', 'Academy', 'Education Institution',
                'NGO', 'Non-Profit', 'International Organization',
                'Government Department', 'County Government',
                'Hospital', 'Health Centre', 'Clinic Chain',
                'Logistics Company', 'Transport Firm', 'Warehouse',
                'Real Estate Developer', 'Property Management Firm',
                'Accounting Firm', 'Audit Firm', 'Consultancy',
            ],
        ],
    ];

    private array $cadenceByTier = [
        1 => 'daily',
        2 => 'weekly',
        3 => 'weekly',
        4 => 'monthly',
    ];

    public function handle(): int
    {
        $append = $this->option('append');
        $brandSlug = $this->option('brand');

        if (! $append) {
            if ($brandSlug) {
                $brand = Brand::where('slug', $brandSlug)->first();
                if ($brand) {
                    MiningTarget::where('brand_id', $brand->id)->delete();
                    $this->warn("Truncated existing targets for {$brand->name}");
                }
            } else {
                MiningTarget::truncate();
                $this->warn('Truncated all existing mining targets');
            }
        }

        // UjuziPlus
        $ujuzi = Brand::where('slug', 'ujuziplus')->first();
        if ($ujuzi && (! $brandSlug || $brandSlug === 'ujuziplus')) {
            $this->seedBrand($ujuzi, $this->ujuziPlusCategories);
        }

        // Hudutech
        $hudu = Brand::where('slug', 'hudutech')->first();
        if ($hudu && (! $brandSlug || $brandSlug === 'hudutech')) {
            $this->seedBrand($hudu, $this->hudutechCategories);
        }

        // Log
        if (! $brandSlug || $brandSlug === 'ujuziplus' || $brandSlug === 'hudutech') {
            $logger = app(ActivityLogger::class);
            $brandNames = [];
            if ($ujuzi && (! $brandSlug || $brandSlug === 'ujuziplus')) {
                $brandNames[] = 'UjuziPlus';
            }
            if ($hudu && (! $brandSlug || $brandSlug === 'hudutech')) {
                $brandNames[] = 'Hudutech';
            }

            $total = MiningTarget::count();
            $logger->log([
                'source' => 'laravel.cli.mining-targets',
                'event_type' => 'system',
                'title' => 'Mining targets seeded — '.implode(', ', $brandNames)." ({$total} targets)",
                'metadata' => [
                    'brands' => $brandNames,
                    'total_targets' => $total,
                    'tiers' => count($this->geoByPriority),
                ],
                'severity' => 'info',
            ]);
        }

        $this->info('Seeded '.MiningTarget::count().' mining targets.');

        return self::SUCCESS;
    }

    private function seedBrand(Brand $brand, array $categories): void
    {
        $count = 0;

        foreach ($this->geoByPriority as $tier => $countries) {
            $cadence = $this->cadenceByTier[$tier];

            foreach ($countries as $country => $cities) {
                foreach ($categories as $segment => $catConfig) {
                    foreach ($catConfig['keywords'] as $keyword) {
                        // Country-level target (mining the whole country for a category)
                        MiningTarget::create([
                            'brand_id' => $brand->id,
                            'country' => $country,
                            'city' => null,
                            'category' => $keyword,
                            'search_template' => $catConfig['template'],
                            'segment' => $segment,
                            'cadence' => $cadence,
                            'is_active' => true,
                        ]);
                        $count++;

                        // City-level targets (only for tier 1-2 where we have cities)
                        if ($tier <= 3) {
                            foreach ($cities as $city) {
                                MiningTarget::create([
                                    'brand_id' => $brand->id,
                                    'country' => $country,
                                    'city' => $city,
                                    'category' => $keyword,
                                    'search_template' => $catConfig['template'],
                                    'segment' => $segment,
                                    'cadence' => $cadence,
                                    'is_active' => true,
                                ]);
                                $count++;
                            }
                        }
                    }
                }
            }
        }

        $this->line("Seeded {$count} targets for {$brand->name}");
    }
}
