<?php

namespace Alle80\Griglia\Tests\Feature;

use Alle80\Griglia\Models\Checklist;
use Alle80\Griglia\Models\Todo;
use Alle80\Griglia\Settings\AppSettings;
use Alle80\Griglia\Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_dashboard_route_redirects_to_the_board(): void
    {
        $this->actingAsUser();

        // One board, one page: the old wider «dashboard» route only keeps old links alive (task 617)
        $this->get(config('griglia.dashboard_route'))->assertRedirect('/');
    }

    public function test_the_board_fills_the_window_up_to_a_readable_cap(): void
    {
        $user = $this->actingAsUser();
        $list = Checklist::create(['name' => 'dev', 'user_id' => $user->id]);
        Todo::create(['title' => 'Ship it', 'order' => 1, 'checklist_id' => $list->id]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Ship it')
            // full-width container capped at 1920px and centred, with grid columns free to multiply
            // (see .tl-page-wide in griglia.css) — no narrow container left
            ->assertSee('tl-page tl-page-wide relative mx-auto', false)
            ->assertDontSee("view === 'grid' ? 'max-w-6xl'", false);
    }

    public function test_every_application_page_uses_the_same_full_width_container(): void
    {
        $this->actingAsUser();

        foreach (['/settings', '/context', '/plans', '/plans/new', '/stats', '/agents'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('tl-page-wide mx-auto w-full', false);
        }
    }

    public function test_side_tab_follows_its_settings(): void
    {
        $user = $this->actingAsUser();
        Checklist::create(['name' => 'dev', 'user_id' => $user->id]);

        $settings = app(AppSettings::class);
        $settings->show_dashboard_tab = true;
        $settings->tab_side = 'left';
        $settings->save();

        // the tab frames the board from the other pages; on the board itself there is nothing to frame
        $this->get('/plans')->assertOk()->assertSee('db-tab-left', false);
        $this->get('/')->assertOk()->assertDontSee('db-tab-handle', false);

        $settings->show_dashboard_tab = false;
        $settings->save();

        $this->get('/plans')->assertOk()->assertDontSee('db-tab-handle', false);
    }

    public function test_tab_side_setting_exists_with_default(): void
    {
        $this->assertContains(app(AppSettings::class)->tab_side, ['right', 'left']);
        $this->assertArrayHasKey('tab_side', AppSettings::fields());
    }
}
