<?php

namespace App\Jobs\Scrapers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Trait for scrapers that use Jina Reader (r.jina.ai) via Agent Reach.
 * Provides fetchViaJinaReader() and a default parseListing().
 */
trait UsesJinaReader
{
    private const JINA_READER = 'https://r.jina.ai';

    private const MAX_FETCH_PAGES = 3;

    private const MAX_LISTINGS = 50;

    /** @var array<string, true> */
    private array $seenListings = [];

    /**
     * Fetch a URL via Jina Reader and return the markdown content.
     */
    private function fetchViaJinaReader(string $url): ?string
    {
        try {
            $response = Http::withHeaders([
                'X-Return-Format' => 'markdown',
            ])->timeout(45)->get(self::JINA_READER.'/'.urlencode($url));

            if (! $response->successful()) {
                Log::warning(get_class($this).": Jina Reader HTTP {$response->status()} for {$url}");

                return null;
            }

            return $response->body();
        } catch (\Exception $e) {
            Log::error(get_class($this).": Jina Reader error: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Dedup check — returns true if this listing was already seen.
     */
    private function isDuplicate(string $key): bool
    {
        $k = mb_strtolower(trim($key));
        if (isset($this->seenListings[$k])) {
            return true;
        }
        $this->seenListings[$k] = true;

        return false;
    }

    /**
     * Convert a relative date string to Y-m-d.
     */
    private function parseRelativeDate(?string $dateStr): string
    {
        if (empty($dateStr)) {
            return date('Y-m-d');
        }

        $ds = strtolower(trim($dateStr));

        if ($ds === 'today') {
            return date('Y-m-d');
        }
        if ($ds === 'yesterday') {
            return date('Y-m-d', strtotime('-1 day'));
        }
        if (preg_match('/(\d+)\s+day/', $ds, $m)) {
            return date('Y-m-d', strtotime('-'.(int)$m[1].' days'));
        }
        if (preg_match('/(\d+)\s+week/', $ds, $m)) {
            return date('Y-m-d', strtotime('-'.((int)$m[1] * 7).' days'));
        }
        if (preg_match('/(\d+)\s+month/', $ds, $m)) {
            return date('Y-m-d', strtotime('-'.((int)$m[1] * 30).' days'));
        }

        return date('Y-m-d');
    }
}
