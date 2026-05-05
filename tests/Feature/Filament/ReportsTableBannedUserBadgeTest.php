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

    public function test_reports_table_has_banned_badge_column(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ListReports::class)
            ->assertTableColumnExists('banned_badge');
    }

    public function test_banned_user_report_shows_banned_badge_state(): void
    {
        $bannedUser = User::factory()->banned()->create();
        $report = Report::factory()->create(['user_id' => $bannedUser->id]);

        Livewire::actingAs($this->admin)
            ->test(ListReports::class)
            ->assertTableColumnStateSet('banned_badge', '已封禁', record: $report);
    }

    public function test_non_banned_user_report_does_not_show_banned_badge(): void
    {
        $user = User::factory()->create(['is_banned' => false]);
        $report = Report::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($this->admin)
            ->test(ListReports::class)
            ->assertTableColumnStateSet('banned_badge', null, record: $report);
    }
}
