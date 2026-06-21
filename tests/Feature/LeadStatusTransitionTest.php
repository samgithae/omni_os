<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Brand;
use App\Models\Lead;
use App\Models\LeadEvent;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_transitions_are_persisted_and_logged(): void
    {
        $lead = $this->makeLead();

        $lead->transitionTo(LeadStatus::Enriching, 'feature_test', ['reason' => 'begin enrichment']);

        $this->assertSame(LeadStatus::Enriching->value, $lead->fresh()->status);

        $event = LeadEvent::query()->where('lead_id', $lead->id)->where('event_type', 'status_changed')->first();

        $this->assertNotNull($event);
        $this->assertSame('feature_test', $event->source);
        $this->assertSame([
            'from' => LeadStatus::New->value,
            'to' => LeadStatus::Enriching->value,
            'reason' => 'begin enrichment',
        ], $event->payload);
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $lead = $this->makeLead();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid lead status transition [new -> interested].');

        $lead->transitionTo(LeadStatus::Interested, 'feature_test');
    }

    public function test_direct_status_mutations_are_also_guarded(): void
    {
        $lead = $this->makeLead();

        $lead->transitionTo(LeadStatus::Enriching, 'feature_test');
        $lead->transitionTo(LeadStatus::Enriched, 'feature_test');

        $lead->status = LeadStatus::Interested->value;

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid lead status transition [enriched -> interested].');

        $lead->save();
    }

    private function makeLead(): Lead
    {
        $brand = Brand::query()->create([
            'name' => 'Test Brand',
            'slug' => 'test-brand',
            'description' => 'Test brand for lead transition checks.',
            'primary_market' => 'Kenya',
            'primary_kpi' => 'Qualified leads',
            'brand_voice' => 'Professional',
            'color' => '#000000',
            'is_active' => true,
        ]);

        return Lead::query()->create([
            'brand_id' => $brand->id,
            'company_name' => 'Example Company',
            'segment' => 'rabbit',
            'country' => 'Kenya',
            'status' => LeadStatus::New->value,
        ]);
    }
}
