<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            [
                'name' => 'Hudutech Innovations Ltd',
                'slug' => 'hudutech',
                'description' => 'Web & software development, Odoo ERP, CRM systems, digital transformation for SMEs, schools, NGOs, manufacturers, and professional-service firms.',
                'primary_market' => 'Kenya',
                'primary_kpi' => 'Qualified leads → sales revenue',
                'brand_voice' => 'Trusted consultant / digital-transformation expert',
                'color' => '#1a56db',
                'is_active' => true,
            ],
            [
                'name' => 'UjuziPlus',
                'slug' => 'ujuziplus',
                'description' => 'White-label LMS (Kajabi/Teachable-style) + professional training, certification prep, corporate training, and workforce development.',
                'primary_market' => 'Kenya / Africa',
                'primary_kpi' => 'White-label LMS subscriptions + course enrollments → corporate training contracts',
                'brand_voice' => 'Professional, authoritative, career-growth focused',
                'color' => '#059669',
                'is_active' => true,
            ],
            [
                'name' => 'Phantomflix',
                'slug' => 'phantomflix',
                'description' => 'Licensed reseller of streaming subscriptions and entertainment content with affordable bundled access and local payment (M-Pesa).',
                'primary_market' => 'Kenya + diaspora',
                'primary_kpi' => 'Paid subscribers → subscriber retention',
                'brand_voice' => 'Fun, affordable, entertainment-focused',
                'color' => '#7c3aed',
                'is_active' => true,
            ],
            [
                'name' => 'Phantom Tutors',
                'slug' => 'phantom-tutors',
                'description' => 'Academic tutoring, exam prep, and personalized learning support for university/college students, parents, and adult learners.',
                'primary_market' => 'US & UK',
                'primary_kpi' => 'Student enrollments → retention & referrals',
                'brand_voice' => 'Friendly mentor / academic success partner',
                'color' => '#dc2626',
                'is_active' => true,
            ],
        ];

        foreach ($brands as $brand) {
            Brand::updateOrCreate(
                ['slug' => $brand['slug']],
                $brand,
            );
        }
    }
}
