<?php

namespace App\Console\Commands;

use App\Models\EmailMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SendEmailBatch extends Command
{
    protected $signature = 'emails:send-batch
                            {--brand= : Brand slug to send for}
                            {--limit=20 : Max emails to send per run}';

    protected $description = 'Send approved/queued emails via SMTP2GO';

    public function handle(): int
    {
        $apiKey = config('services.smtp2go.api_key');
        $apiEndpoint = config('services.smtp2go.api_endpoint', 'https://api.smtp2go.com/v3');

        if (! $apiKey) {
            $this->error('SMTP2GO API key not configured.');

            return 1;
        }

        $query = EmailMessage::query()
            ->where('approval_status', 'approved')
            ->where('status', 'queued')
            ->with(['lead:id,company_name,email,contact_name', 'brand:id,name,slug']);

        if ($brandSlug = $this->option('brand')) {
            $brand = \App\Models\Brand::where('slug', $brandSlug)->first();
            if ($brand) {
                $query->where('brand_id', $brand->id);
            }
        }

        $limit = (int) $this->option('limit');
        $emails = $query->limit($limit)->get();

        if ($emails->isEmpty()) {
            $this->info('No queued emails to send.');

            return 0;
        }

        $this->info("Found {$emails->count()} queued emails.");

        $sent = 0;
        $failed = 0;

        foreach ($emails as $email) {
            $lead = $email->lead;
            $brand = $email->brand;

            $this->line("  Sending to {$lead->email}: {$email->subject}");

            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'X-API-Key' => $apiKey,
                ])->post(rtrim($apiEndpoint, '/').'/email/send', [
                    'to' => [[
                        'address' => $lead->email,
                        'name' => $lead->contact_name ?? $lead->company_name,
                    ]],
                    'sender' => config('mail.from.address'),
                    'sender_name' => config('mail.from.name'),
                    'subject' => $email->subject,
                    'html' => nl2br(e($email->body)),
                    'text' => $email->body,
                    'custom_headers' => [
                        ['header' => 'X-Omni-OS-Email-ID', 'value' => (string) $email->id],
                    ],
                ]);

                if ($response->successful()) {
                    $email->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);

                    $lead->transitionTo(\App\Enums\LeadStatus::Emailed, 'cli.emails.send', [
                        'email_id' => $email->id,
                        'sequence_step' => $email->sequence_step,
                    ]);

                    $this->info("    ✅ Sent (ID: {$email->id})");
                    $sent++;
                } else {
                    $email->update([
                        'status' => 'failed',
                        'error_message' => substr($response->body(), 0, 500),
                    ]);
                    $this->error("    ❌ Failed: {$response->status()} {$response->body()}");
                    $failed++;
                }
            } catch (\Throwable $e) {
                $email->update([
                    'status' => 'failed',
                    'error_message' => substr($e->getMessage(), 0, 500),
                ]);
                $this->error("    ❌ Exception: {$e->getMessage()}");
                $failed++;
            }

            // Small delay to avoid SMTP2GO rate limits
            if ($sent + $failed < $emails->count()) {
                usleep(200000); // 200ms
            }
        }

        $this->newLine();
        $this->info("Done: {$sent} sent, {$failed} failed.");

        return $failed > 0 ? 1 : 0;
    }
}
