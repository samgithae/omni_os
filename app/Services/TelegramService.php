<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private ?string $botToken;

    private ?string $chatId;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->chatId = config('services.telegram.chat_id');
    }

    public function isConfigured(): bool
    {
        return $this->botToken !== null && $this->chatId !== null;
    }

    /**
     * Send a plain text message to the configured Telegram chat.
     */
    public function sendMessage(string $text, string $parseMode = 'HTML'): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('Telegram not configured — message not sent.', ['preview' => substr($text, 0, 200)]);

            return false;
        }

        $response = Http::withOptions([
            'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
        ])->post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
            'chat_id' => $this->chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => true,
        ]);

        if (! $response->successful()) {
            Log::error('Telegram send failed', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Send a structured email approval request to Telegram.
     * Each email gets numbered so Sam can reply APPROVE 123 / REJECT 123.
     */
    public function sendApprovalRequest(array $emailData): bool
    {
        [$text, $replyMarkup] = $this->buildApprovalMessage($emailData);

        $payload = [
            'chat_id' => $this->chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        $response = Http::withOptions([
            'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
        ])->post("https://api.telegram.org/bot{$this->botToken}/sendMessage", $payload);

        if (! $response->successful()) {
            Log::error('Telegram approval request failed', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Build a formatted approval message with inline keyboard.
     */
    private function buildApprovalMessage(array $emailData): array
    {
        $brandName = $emailData['brand_name'] ?? 'Unknown';
        $emails = $emailData['emails'] ?? [];
        $totalEmails = $emailData['total'] ?? count($emails);

        $text = "📬 <b>Approval Request — {$brandName}</b>\n";
        $text .= "─────────────────\n";

        if (count($emails) === 0) {
            $text .= "No pending emails to review.\n";

            return [$text, null];
        }

        // Group by lead for readability
        $grouped = [];
        foreach ($emails as $email) {
            $leadName = $email['company_name'] ?? 'Unknown';
            $grouped[$leadName][] = $email;
        }

        foreach ($grouped as $company => $leadEmails) {
            $text .= "\n🏢 <b>{$company}</b>\n";
            foreach ($leadEmails as $e) {
                $subject = $e['subject'] ?? '(no subject)';
                $step = $e['sequence_step'] ?? '?';
                $id = $e['id'] ?? 0;
                $text .= "  <code>{$id}</code> Step {$step}: \"{$subject}\"\n";
            }
            $text .= "\n";
        }

        // Batch approval hint
        if ($totalEmails > 3) {
            $text .= "\n💡 <i>Reply:</i>\n";
            $text .= "  <code>APPROVE {id}</code> — approve one\n";
            $text .= "  <code>REJECT {id}</code> — reject one\n";
            $text .= "  <code>APPROVE ALL</code> — approve all pending\n";
            $text .= "  <code>REJECT ALL</code> — reject all pending\n";
        }

        // Build inline keyboard for quick actions
        $keyboard = [];
        $row = [];
        foreach ($emails as $i => $e) {
            $row[] = ['text' => "✅ {$e['id']}", 'callback_data' => "approve:{$e['id']}"];
            if (count($row) >= 3 || $i === count($emails) - 1) {
                $keyboard[] = $row;
                $row = [];
            }
        }

        // Add batch buttons
        $keyboard[] = [
            ['text' => '✅ Approve All', 'callback_data' => 'approve_all'],
            ['text' => '❌ Reject All', 'callback_data' => 'reject_all'],
        ];

        return [$text, ['inline_keyboard' => $keyboard]];
    }
}
