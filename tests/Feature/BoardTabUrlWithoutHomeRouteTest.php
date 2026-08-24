<?php

namespace Alle80\Griglia\Tests\Feature;

use Alle80\Griglia\Support\Board;
use Alle80\Griglia\Tests\TestCase;

/** Without a home route the board is served by the dashboard route, so the tab points there. */
class BoardTabUrlWithoutHomeRouteTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('griglia.home_route', false);
    }

    public function test_the_board_url_falls_back_to_the_dashboard_route(): void
    {
        $this->assertSame(route('griglia.dashboard'), Board::url());
    }
}
