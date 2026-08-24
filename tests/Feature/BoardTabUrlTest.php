<?php

namespace Alle80\Griglia\Tests\Feature;

use Alle80\Griglia\Models\Checklist;
use Alle80\Griglia\Settings\AppSettings;
use Alle80\Griglia\Support\Board;
use Alle80\Griglia\Tests\TestCase;
use Illuminate\Support\Facades\Route;

/**
 * The slide-out tab has to frame the board. It used to point at the raw `dashboard_route` path, which a
 * host application may well own itself (Breeze and Jetstream both take «/dashboard»): the panel then
 * showed the host's dashboard instead of the board (task 646).
 */
class BoardTabUrlTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        // A path the host application keeps for itself: the package route never wins, so the tab must
        // not be built from it.
        $app['config']->set('griglia.dashboard_route', '/host-dashboard');
    }

    protected function enableTab(): void
    {
        $user = $this->actingAsUser();
        Checklist::create(['name' => 'dev', 'user_id' => $user->id]);

        $settings = app(AppSettings::class);
        $settings->show_dashboard_tab = true;
        $settings->save();
    }

    public function test_the_board_url_is_the_home_route(): void
    {
        $this->assertSame(route('griglia.home'), Board::url());
    }

    public function test_the_tab_frames_the_board_and_not_the_dashboard_path(): void
    {
        $this->enableTab();

        $tab = view('griglia::components.board-tab', ['standalone' => true])->render();

        $this->assertStringContainsString('data-db-url="'.e(route('griglia.home')).'"', $tab);
        $this->assertStringNotContainsString('host-dashboard', $tab);
    }

    public function test_the_injected_tab_frames_the_board(): void
    {
        $this->enableTab();
        Route::middleware('web')->get('/host-page', fn () => '<html><body><h1>Host</h1></body></html>');

        $this->get('/host-page')
            ->assertOk()
            ->assertSee('data-db-url="'.e(route('griglia.home')).'"', false)
            ->assertDontSee('host-dashboard');
    }
}
