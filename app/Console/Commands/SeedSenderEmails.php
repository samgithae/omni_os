<?php

namespace App\Console\Commands;

use App\Models\Brand;
use Illuminate\Console\Command;

class SeedSenderEmails extends Command
{
    protected $signature = 'brands:seed-senders
                            {--brand= : Brand slug to update (default: all brands)}';

    protected $description = 'Seed sender email pools for each brand';

    public function handle(): int
    {
        $brands = [];

        $brands['ujuziplus'] = [
            'sender_name' => 'UjuziPlus',
            'sender_emails' => [
                'business@tryujuziplus.com',
                'connect@tryujuziplus.com',
                'engage@tryujuziplus.com',
                'hello@ujuziplus.com',
                'hello@tryujuziplus.com',
                'ian@ujuziplus.com',
                'info@ujuziplus.com',
                'office@tryujuziplus.com',
                'partnerships@tryujuziplus.com',
                'relations@tryujuziplus.com',
                's.githae@tryujuziplus.com',
                'sales@tryujuziplus.com',
                'samuel@ujuziplus.com',
                'samuel@tryujuziplus.com',
                'support@ujuziplus.com',
                'training@ujuziplus.com',
            ],
        ];

        $brands['hudutech'] = [
            'sender_name' => 'Hudutech Innovations',
            'sender_emails' => [
                'info@hudutech.co.ke',
                'hello@hudutech.co.ke',
            ],
        ];

        $brands['phantomflix'] = [
            'sender_name' => 'Phantomflix',
            'sender_emails' => [
                'info@phantomflix.com',
                'hello@phantomflix.com',
            ],
        ];

        $brands['phantom-tutors'] = [
            'sender_name' => 'Phantom Tutors',
            'sender_emails' => [
                'hello@phantontutors.com',
                'support@phantontutors.com',
            ],
        ];

        $brandSlug = $this->option('brand');
        $query = Brand::query();
        if ($brandSlug) {
            $query->where('slug', $brandSlug);
        }

        $updated = 0;
        foreach ($query->get() as $brand) {
            if (isset($brands[$brand->slug])) {
                $data = $brands[$brand->slug];
                $brand->update([
                    'sender_emails' => $data['sender_emails'],
                    'sender_name' => $data['sender_name'],
                ]);
                $this->info("  {$brand->name}: " . count($data['sender_emails']) . " sender emails seeded");
                $updated++;
            } else {
                $this->warn("  {$brand->name}: no sender emails configured — skipped");
            }
        }

        $this->info("Updated {$updated} brands with sender email pools.");

        return self::SUCCESS;
    }
}