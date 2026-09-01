<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 受保護名單來自 PROTECTED_ADMIN_EMAILS（.env），測試固定一份名單，
        // 才不會因為部署環境的設定不同而時綠時紅。
        config(['auth.protected_admin_emails' => ['protected@example.com']]);
    }

    public function test_protected_admin_emails_reads_the_configured_list(): void
    {
        $this->assertSame(['protected@example.com'], User::protectedAdminEmails());
    }

    public function test_protected_admin_emails_is_empty_when_nothing_is_configured(): void
    {
        config(['auth.protected_admin_emails' => []]);

        $admin = User::factory()->admin()->create(['email' => 'other@example.com']);

        $this->assertSame([], User::protectedAdminEmails());
        $this->assertNotContains($admin->email, User::protectedAdminEmails());
    }

    public function test_regular_admin_is_not_protected(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'other@example.com']);

        $this->assertNotContains($admin->email, User::protectedAdminEmails());
    }

    public function test_is_admin_can_be_set_to_true(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $user->update(['is_admin' => true]);

        $this->assertTrue($user->fresh()->is_admin);
    }

    public function test_is_admin_can_be_set_to_false_for_regular_admin(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'other@example.com']);

        $this->assertNotContains($admin->email, User::protectedAdminEmails());

        $admin->update(['is_admin' => false]);

        $this->assertFalse($admin->fresh()->is_admin);
    }

    public function test_protected_emails_are_recognised_as_protected(): void
    {
        foreach (User::protectedAdminEmails() as $email) {
            $user = User::factory()->admin()->create(['email' => $email]);

            $this->assertContains($user->email, User::protectedAdminEmails());
        }
    }
}
