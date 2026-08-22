<?php

namespace Alle80\Griglia\Tests\Feature;

use Alle80\Griglia\Models\Checklist;
use Alle80\Griglia\Models\Todo;
use Alle80\Griglia\Tests\TestCase;

/**
 * Host apps may disable the home route and keep the board on the dashboard path: there the dashboard
 * route has to render the board instead of redirecting to a route that does not exist (task 617).
 */
class DashboardWithoutHomeRouteTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('griglia.home_route', false);
    }

    public function test_the_dashboard_route_shows_the_board(): void
    {
        $user = $this->actingAsUser();
        $list = Checklist::create(['name' => 'dev', 'user_id' => $user->id]);
        Todo::create(['title' => 'Ship it', 'order' => 1, 'checklist_id' => $list->id]);

        $this->get(config('griglia.dashboard_route'))
            ->assertOk()
            ->assertSee('Ship it')
            ->assertSee('tl-page tl-page-wide relative mx-auto', false);
    }
}
