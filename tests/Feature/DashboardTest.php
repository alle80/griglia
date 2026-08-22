<?php

namespace Alle80\Griglia\Tests\Feature;

use Alle80\Griglia\Models\Checklist;
use Alle80\Griglia\Models\Todo;
use Alle80\Griglia\Settings\AppSettings;
use Alle80\Griglia\Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_dashboard_route_renders_the_board(): void
    {
        $user = $this->actingAsUser();
        $list = Checklist::create(['name' => 'dev', 'user_id' => $user->id]);
        Todo::create(['title' => 'Ship it', 'order' => 1, 'checklist_id' => $list->id]);

        $this->get(config('griglia.dashboard_route'))
            ->assertOk()
            ->assertSee('Ship it')
            // full-width desktop container: no centred max-width, and the grid columns are free to
            // multiply (see .tl-page-wide in griglia.css)
            ->assertSee('tl-page-wide max-w-none', false)
            ->assertDontSee("view === 'grid' ? 'max-w-6xl'", false);
    }

    public function test_the_plain_board_keeps_its_narrow_container(): void
    {
        $user = $this->actingAsUser();
        $list = Checklist::create(['name' => 'dev', 'user_id' => $user->id]);
        Todo::create(['title' => 'Ship it', 'order' => 1, 'checklist_id' => $list->id]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('tl-page-wide', false)
            ->assertSee("view === 'grid' ? 'max-w-6xl' : 'max-w-2xl'", false);
    }

    public function test_side_tab_follows_its_settings(): void
    {
        $user = $this->actingAsUser();
        Checklist::create(['name' => 'dev', 'user_id' => $user->id]);

        $settings = app(AppSettings::class);
        $settings->show_dashboard_tab = true;
        $settings->tab_side = 'left';
        $settings->save();

        $this->get('/')->assertOk()->assertSee('db-tab-left', false);

        $settings->show_dashboard_tab = false;
        $settings->save();

        $this->get('/')->assertOk()->assertDontSee('db-tab-handle', false);
    }

    public function test_tab_side_setting_exists_with_default(): void
    {
        $this->assertContains(app(AppSettings::class)->tab_side, ['right', 'left']);
        $this->assertArrayHasKey('tab_side', AppSettings::fields());
    }
}
