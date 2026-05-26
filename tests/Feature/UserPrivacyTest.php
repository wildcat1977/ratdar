<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggle_private_sets_is_private_true(): void
    {
        $user = User::factory()->create(['is_private' => false]);

        Livewire::actingAs($user)
            ->test('user-profile')
            ->call('togglePrivate');

        $this->assertTrue($user->fresh()->is_private);
    }

    public function test_toggle_private_turns_off_again(): void
    {
        $user = User::factory()->create(['is_private' => true]);

        Livewire::actingAs($user)
            ->test('user-profile')
            ->call('togglePrivate');

        $this->assertFalse($user->fresh()->is_private);
    }

    public function test_private_user_shown_anonymously_in_leaderboard(): void
    {
        $user = User::factory()->create(['name' => 'SecretUser', 'is_private' => true]);
        Report::factory()->for($user)->create(['status' => 'approved']);

        Livewire::actingAs(User::factory()->create())
            ->test('leaderboard')
            ->assertDontSee('SecretUser')
            ->assertSee('匿名捕鼠手');
    }

    public function test_public_user_name_visible_in_leaderboard(): void
    {
        $user = User::factory()->create(['name' => 'PublicUser', 'is_private' => false]);
        Report::factory()->for($user)->create(['status' => 'approved']);

        Livewire::actingAs(User::factory()->create())
            ->test('leaderboard')
            ->assertSee('PublicUser');
    }
}
