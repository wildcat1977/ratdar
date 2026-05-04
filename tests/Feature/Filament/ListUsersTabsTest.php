<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Schemas\Components\Tabs\Tab;
use Tests\TestCase;

class ListUsersTabsTest extends TestCase
{
    public function test_get_tabs_returns_all_admin_and_blocked(): void
    {
        $tabs = (new ListUsers())->getTabs();

        $this->assertSame(['all', 'admin', 'blocked'], array_keys($tabs));
        $this->assertContainsOnlyInstancesOf(Tab::class, $tabs);
    }

    public function test_all_tab_does_not_modify_query(): void
    {
        $tabs = (new ListUsers())->getTabs();

        $expected = User::query()->toSql();
        $actual = $tabs['all']->modifyQuery(User::query())->toSql();

        $this->assertSame($expected, $actual);
    }

    public function test_admin_tab_filters_is_admin_true(): void
    {
        $tabs = (new ListUsers())->getTabs();

        $query = $tabs['admin']->modifyQuery(User::query());

        $this->assertMatchesRegularExpression('/"is_admin"\s*=\s*\?/i', $query->toSql());
        $this->assertContains(true, $query->getBindings());
    }

    public function test_blocked_tab_filters_is_banned_true(): void
    {
        $tabs = (new ListUsers())->getTabs();

        $query = $tabs['blocked']->modifyQuery(User::query());

        $this->assertMatchesRegularExpression('/"is_banned"\s*=\s*\?/i', $query->toSql());
        $this->assertContains(true, $query->getBindings());
    }
}
