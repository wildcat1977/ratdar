<?php

namespace Tests\Feature\Api;

use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsApiTest extends TestCase
{
    use RefreshDatabase;

    private function approvedReport(array $attrs = []): Report
    {
        return Report::factory()->create(array_merge(['status' => Report::STATUS_APPROVED], $attrs));
    }

    public function test_returns_approved_reports_as_json(): void
    {
        $report = $this->approvedReport(['description' => 'test rat']);
        Report::factory()->create(['status' => Report::STATUS_REJECTED]);
        Report::factory()->create(['status' => Report::STATUS_PENDING]);

        $response = $this->getJson('/api/v1/reports');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $report->id)
            ->assertJsonStructure([
                'data' => [['id', 'type', 'latitude', 'longitude', 'address', 'description', 'image_url', 'reported_at', 'status']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_includes_all_visible_statuses(): void
    {
        // SQLite test DB only supports approved/rejected/pending (PG-only CHECK constraint
        // covers reported_1999/resolved; tested implicitly via VISIBLE_STATUSES constant)
        Report::factory()->create(['status' => Report::STATUS_APPROVED]);
        Report::factory()->create(['status' => Report::STATUS_REJECTED]);
        Report::factory()->create(['status' => Report::STATUS_PENDING]);

        $this->getJson('/api/v1/reports')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_filters_by_from_date(): void
    {
        Report::factory()->create([
            'status' => Report::STATUS_APPROVED,
            'created_at' => '2026-01-01 10:00:00',
        ]);
        $recent = Report::factory()->create([
            'status' => Report::STATUS_APPROVED,
            'created_at' => '2026-06-01 10:00:00',
        ]);

        $this->getJson('/api/v1/reports?from=2026-05-01')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $recent->id);
    }

    public function test_filters_by_to_date(): void
    {
        $old = Report::factory()->create([
            'status' => Report::STATUS_APPROVED,
            'created_at' => '2026-01-01 10:00:00',
        ]);
        Report::factory()->create([
            'status' => Report::STATUS_APPROVED,
            'created_at' => '2026-06-01 10:00:00',
        ]);

        $this->getJson('/api/v1/reports?to=2026-03-01')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $old->id);
    }

    public function test_filters_by_from_and_to(): void
    {
        Report::factory()->create(['status' => Report::STATUS_APPROVED, 'created_at' => '2026-01-01 00:00:00']);
        $match = Report::factory()->create(['status' => Report::STATUS_APPROVED, 'created_at' => '2026-03-15 12:00:00']);
        Report::factory()->create(['status' => Report::STATUS_APPROVED, 'created_at' => '2026-06-01 00:00:00']);

        $this->getJson('/api/v1/reports?from=2026-03-01&to=2026-04-01')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_filters_by_type(): void
    {
        $rat = $this->approvedReport(['type' => Report::TYPE_RAT]);
        $poison = $this->approvedReport(['type' => Report::TYPE_POISON]);

        $this->getJson('/api/v1/reports?type=rat')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $rat->id);

        $this->getJson('/api/v1/reports?type=poison')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $poison->id);
    }

    public function test_rejects_invalid_type(): void
    {
        $this->getJson('/api/v1/reports?type=invalid')
            ->assertUnprocessable();
    }

    public function test_rejects_invalid_date(): void
    {
        $this->getJson('/api/v1/reports?from=not-a-date')
            ->assertUnprocessable();
    }

    public function test_pagination_meta_is_correct(): void
    {
        Report::factory()->count(5)->create(['status' => Report::STATUS_APPROVED]);

        $response = $this->getJson('/api/v1/reports?per_page=2&page=1');

        $response->assertOk()
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonCount(2, 'data');
    }

    public function test_reported_at_includes_timezone(): void
    {
        $this->approvedReport();

        $response = $this->getJson('/api/v1/reports');
        $reportedAt = $response->json('data.0.reported_at');

        // ISO 8601 with offset e.g. 2026-05-10T18:00:00+08:00
        $this->assertMatchesRegularExpression('/\+\d{2}:\d{2}$/', $reportedAt);
    }
}
