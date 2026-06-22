<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Models\Reply;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendLeadReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Lead $lead,
        public string $body,
        public Reply $reply,
    ) {}

    public function handle(): void
    {
        $apiKey = config('services.smtp2go.api_key');
        $apiEndpoint = config('services.smtp2go.api_endpoint', 'https://api.smtp2go.com/v3');

        if (!$apiKey) {
            Log::error('SendLeadReply: SMTP2GO API key not configured');
            return;
        }

        $brand = $this->lead->brand;
        $fromAddress = $brand?->randomSenderEmail() ?? config('mail.from.address');
        $fromName = $brand?->sender_name ?? config('mail.from.name', 'Omni OS');

        // Find the most recent sent email to this lead for threading
        $lastSentEmail = $this->lead->emailMessages()
            ->where('status', 'sent')
            ->latest('sent_at')
            ->first();

        $customHeaders = [
            ['header' => 'X-Omni-OS-Reply-ID', 'value' => (string) $this->reply->id],
        ];

        // Add In-Reply-To header for threading if we have a sent email
        if ($lastSentEmail) {
            $customHeaders[] = ['header' => 'X-Omni-OS-Email-ID', 'value' => (string) $lastSentEmail->id];
        }

        $subject = $this->reply->subject ?? 'Re: Your inquiry';

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Smtp2go-Api-Key' => $apiKey,
        ])->post(rtrim($apiEndpoint, '/') . '/email/send', [
            'to' => [$this->lead->email],
            'sender' => $fromAddress,
            'sender_name' => $fromName,
            'subject' => $subject,
            'text_body' => $this->body,
            'html_body' => nl2br(e($this->body)),
            'custom_headers' => $customHeaders,
        ]);

        if ($response->successful()) {
            Log::info("SendLeadReply: Reply sent to {$this->lead->email} (reply ID: {$this->reply->id})");
        } else {
            Log::error("SendLeadReply: Failed to send to {$this->lead->email}", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }
}