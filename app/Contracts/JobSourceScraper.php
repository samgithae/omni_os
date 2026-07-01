<?php

namespace App\Contracts;

interface JobSourceScraper
{
    /**
     * Fetch job listings from this source.
     * Returns an array of raw listing data.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchListings(): array;

    /**
     * Parse a raw listing into a structured array.
     * Returns null if the listing doesn't match target criteria.
     *
     * @param  array<string, mixed>  $rawListing
     * @return array{company_name: string, website: ?string, job_title: string, posting_date: ?string, job_url: string, source: string}|null
     */
    public function parseListing(array $rawListing): ?array;

    /**
     * Check if there are more pages to fetch.
     */
    public function hasNextPage(): bool;

    /**
     * Get the source identifier (e.g., 'brightermonday', 'fuzu').
     */
    public function sourceName(): string;
}
