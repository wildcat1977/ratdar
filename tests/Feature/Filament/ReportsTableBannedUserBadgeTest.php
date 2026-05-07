<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Reports\Pages\ListReports;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportsTableBannedUserBadgeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_reports_table_has_no_standalone_banned_badge_column(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ListReports::class)
            ->assertTableColumnDoesNotExist('banned_badge');
    }

    public function test_banned_user_report_shows_banned_prefix_in_reporter_description(): void
    {
        $bannedUser = User::factory()->banned()->create();
        $report = Report::factory()->for($bannedUser)->create(['address' => '台北市中正區']);

        Livewire::actingAs($this->admin)
            ->test(ListReports::class)
            ->assertTableColumnHasDescription('user.name', '⛔ 已封禁 台北市中正區', record: $report);
    }

    public function test_non_banned_user_report_has_no_banned_prefix_in_reporter_description(): void
    {
        $user = User::factory()->create(['is_banned' => false]);
        $report = Report::factory()->for($user)->create(['address' => '台北市信義區']);

        Livewire::actingAs($this->admin)
            ->test(ListReports::class)
            ->assertTableColumnHasDescription('user.name', '台北市信義區', record: $report);
    }

    public function test_reports_table_can_search_by_address(): void
    {
        $matchingReport = Report::factory()->create(['address' => '台北市中正區忠孝西路']);
        $otherReport = Report::factory()->create(['address' => '新北市板橋區文化路']);

        Livewire::actingAs($this->admin)
            ->test(ListReports::class)
            ->searchTable('中正區')
            ->assertCanSeeTableRecords([$matchingReport])
            ->assertCanNotSeeTableRecords([$otherReport]);
    }
}
