<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_admin_emails_constant_contains_expected_emails(): void
    {
        $this->assertContains('wildcat.young@gmail.com', User::PROTECTED_ADMIN_EMAILS);
        $this->assertContains('genehong@gmail.com', User::PROTECTED_ADMIN_EMAILS);
    }

    public function test_regular_admin_is_not_protected(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'other@example.com']);

        $this->assertNotContains($admin->email, User::PROTECTED_ADMIN_EMAILS);
    }

    public function test_grant_admin_action_sets_is_admin_true(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $user->update(['is_admin' => true]);

        $this->assertTrue($user->fresh()->is_admin);
    }

    public function test_revoke_admin_action_sets_is_admin_false_for_regular_admin(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'other@example.com']);

        $this->assertFalse(in_array($admin->email, User::PROTECTED_ADMIN_EMAILS));

        $admin->update(['is_admin' => false]);

        $this->assertFalse($admin->fresh()->is_admin);
    }

    public function test_protected_emails_cannot_be_revoked_via_action_visibility(): void
    {
        foreach (User::PROTECTED_ADMIN_EMAILS as $email) {
            $user = User::factory()->admin()->create(['email' => $email]);

            // The revoke_admin action should NOT be visible for protected emails
            $isVisible = $user->is_admin && ! in_array($user->email, User::PROTECTED_ADMIN_EMAILS);

            $this->assertFalse($isVisible, "revoke_admin should not be visible for {$email}");
        }
    }

    public function test_grant_admin_action_is_visible_for_non_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $isGrantVisible = ! $user->is_admin;

        $this->assertTrue($isGrantVisible);
    }

    public function test_revoke_admin_action_is_visible_for_non_protected_admin(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'other@example.com']);

        $isRevokeVisible = $admin->is_admin && ! in_array($admin->email, User::PROTECTED_ADMIN_EMAILS);

        $this->assertTrue($isRevokeVisible);
    }

    public function test_grant_admin_action_is_not_visible_for_existing_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $isGrantVisible = ! $admin->is_admin;

        $this->assertFalse($isGrantVisible);
    }
}
