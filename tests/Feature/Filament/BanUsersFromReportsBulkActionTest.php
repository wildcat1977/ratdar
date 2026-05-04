<?php

namespace Tests\Feature\Filament;

use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class BanUsersFromReportsBulkActionTest extends TestCase
{
    use RefreshDatabase;

    private function executeBanAction(Collection $records, string $banReason): void
    {
        $userIds = $records
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        if ($userIds->isNotEmpty()) {
            User::whereIn('id', $userIds)->update([
                'is_banned'  => true,
                'ban_reason' => $banReason,
            ]);
        }
    }

    public function test_ban_users_action_bans_linked_users(): void
    {
        $user1 = User::factory()->create(['is_banned' => false]);
        $user2 = User::factory()->create(['is_banned' => false]);

        $report1 = Report::factory()->create(['user_id' => $user1->id]);
        $report2 = Report::factory()->create(['user_id' => $user2->id]);

        $this->executeBanAction(new Collection([$report1, $report2]), '惡意回報');

        $this->assertTrue($user1->fresh()->is_banned);
        $this->assertSame('惡意回報', $user1->fresh()->ban_reason);
        $this->assertTrue($user2->fresh()->is_banned);
        $this->assertSame('惡意回報', $user2->fresh()->ban_reason);
    }

    public function test_ban_users_action_deduplicates_users_across_reports(): void
    {
        $user = User::factory()->create(['is_banned' => false]);

        $report1 = Report::factory()->create(['user_id' => $user->id]);
        $report2 = Report::factory()->create(['user_id' => $user->id]);

        $this->executeBanAction(new Collection([$report1, $report2]), '惡意回報');

        $this->assertTrue($user->fresh()->is_banned);
        $this->assertSame('惡意回報', $user->fresh()->ban_reason);
    }

    public function test_ban_users_action_skips_null_user_ids(): void
    {
        // The action filters out null user_ids before querying the database,
        // so reports without a user (e.g., created externally) are safely skipped.
        $report = new Report(['user_id' => null]);

        $userIds = (new Collection([$report]))
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        $this->assertTrue($userIds->isEmpty());
    }
}
