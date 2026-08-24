<?php

namespace Alle80\Griglia\Tests\Feature;

use Alle80\Griglia\Models\Checklist;
use Alle80\Griglia\Settings\AppSettings;
use Alle80\Griglia\Support\Board;
use Alle80\Griglia\Tests\TestCase;
use Illuminate\Support\Facades\Route;

/** No board route, no tab: there would be nothing to frame. */
class BoardTabWithoutBoardRouteTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('griglia.home_route', false);
        $app['config']->set('griglia.dashboard_route', null);
    }

    public function test_the_tab_is_not_injected(): void
    {
        $user = $this->actingAsUser();
        Checklist::create(['name' => 'dev', 'user_id' => $user->id]);

        $settings = app(AppSettings::class);
        $settings->show_dashboard_tab = true;
        $settings->save();

        Route::middleware('web')->get('/host-page', fn () => '<html><body><h1>Host</h1></body></html>');

        $this->assertNull(Board::url());
        $this->get('/host-page')->assertOk()->assertDontSee('db-tab-handle');
    }
}
