<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Reports\Pages\ListReports;
use App\Models\Report;
use Filament\Schemas\Components\Tabs\Tab;
use Tests\TestCase;

class ListReportsTabsTest extends TestCase
{
    public function test_get_tabs_returns_all_and_with_photo(): void
    {
        $tabs = (new ListReports())->getTabs();

        $this->assertSame(['all', 'with_photo'], array_keys($tabs));
        $this->assertContainsOnlyInstancesOf(Tab::class, $tabs);
    }

    public function test_all_tab_does_not_modify_query(): void
    {
        $tabs = (new ListReports())->getTabs();

        $expected = Report::query()->toSql();
        $actual = $tabs['all']->modifyQuery(Report::query())->toSql();

        $this->assertSame($expected, $actual);
    }

    public function test_with_photo_tab_filters_image_path_not_null(): void
    {
        $tabs = (new ListReports())->getTabs();

        $sql = $tabs['with_photo']->modifyQuery(Report::query())->toSql();

        $this->assertMatchesRegularExpression('/"image_path"\s+is\s+not\s+null/i', $sql);
    }
}