<?php

namespace Alle80\Griglia\Tests\Feature;

use Alle80\Griglia\Models\Checklist;
use Alle80\Griglia\Settings\AppSettings;
use Alle80\Griglia\Tests\TestCase;
use Illuminate\Support\Facades\Route;

/**
 * The board tab is injected in every HTML page of the host application (debugbar style), and only there:
 * see Alle80\Griglia\Http\Middleware\InjectBoardTab.
 */
class InjectBoardTabTest extends TestCase
{
    protected function hostRoutes(): void
    {
        Route::middleware('web')->group(function () {
            Route::get('/host-page', fn () => '<html><body><h1>Host</h1></body></html>');
            Route::get('/host-json', fn () => ['ok' => true]);
            Route::get('/host-redirect', fn () => redirect('/host-page'));
            Route::get('/host-download', fn () => response('id,name', 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="export.csv"',
            ]));
            Route::get('/admin/host-page', fn () => '<html><body><h1>Admin</h1></body></html>');
            Route::post('/livewire/update', fn () => '<html><body>partial</body></html>');
        });
    }

    protected function enableTab(): void
    {
        $user = $this->actingAsUser();
        Checklist::create(['name' => 'dev', 'user_id' => $user->id]);

        $settings = app(AppSettings::class);
        $settings->show_dashboard_tab = true;
        $settings->save();

        $this->hostRoutes();
    }

    public function test_the_tab_lands_in_a_plain_page_of_the_host_application(): void
    {
        $this->enableTab();

        $html = $this->get('/host-page')->assertOk()->getContent();

        $this->assertStringContainsString('db-tab-handle', $html);
        // spliced in just before </body>, with the host page left untouched
        $this->assertStringContainsString('<h1>Host</h1>', $html);
        $this->assertLessThan(strpos($html, '</body>'), strpos($html, 'db-tab-handle'));
    }

    public function test_it_is_not_injected_in_json_redirects_downloads_or_partial_updates(): void
    {
        $this->enableTab();

        $this->assertStringNotContainsString('db-tab-handle', $this->get('/host-json')->assertOk()->getContent());
        $this->assertStringNotContainsString('db-tab-handle', $this->get('/host-redirect')->assertRedirect()->getContent());
        $this->assertStringNotContainsString('db-tab-handle', $this->get('/host-download')->assertOk()->getContent());

        // AJAX / Livewire / Turbo fetch fragments of a page: a tab in there would be a tab inside a tab
        $ajax = $this->get('/host-page', ['X-Requested-With' => 'XMLHttpRequest'])->getContent();
        $this->assertStringNotContainsString('db-tab-handle', $ajax);
        $this->assertStringNotContainsString('db-tab-handle', $this->get('/host-page', ['X-Livewire' => 'true'])->getContent());
        $this->assertStringNotContainsString('db-tab-handle', $this->get('/host-page', ['Turbo-Frame' => 'main'])->getContent());
        $this->assertStringNotContainsString('db-tab-handle', $this->post('/livewire/update')->getContent());
    }

    public function test_the_settings_switch_turns_the_injection_off(): void
    {
        $this->enableTab();

        $settings = app(AppSettings::class);
        $settings->show_dashboard_tab = false;
        $settings->save();

        $this->assertStringNotContainsString('db-tab-handle', $this->get('/host-page')->assertOk()->getContent());
    }

    public function test_the_deny_list_of_paths_is_respected(): void
    {
        $this->enableTab();
        config()->set('griglia.inject_tab_except', ['admin/*']);

        $this->assertStringNotContainsString('db-tab-handle', $this->get('/admin/host-page')->assertOk()->getContent());
        $this->assertStringContainsString('db-tab-handle', $this->get('/host-page')->assertOk()->getContent());
    }

    public function test_the_board_pages_keep_exactly_one_tab(): void
    {
        $this->enableTab();

        // the package layout prints it itself: the middleware must not add a second one
        $this->assertSame(1, substr_count($this->get('/plans')->assertOk()->getContent(), 'data-db-url'));
        // and on the board itself there is nothing to frame
        $this->assertStringNotContainsString('db-tab-handle', $this->get('/')->assertOk()->getContent());
    }

    public function test_a_visitor_who_may_not_open_the_board_does_not_see_the_tab(): void
    {
        $settings = app(AppSettings::class);
        $settings->show_dashboard_tab = true;
        $settings->save();

        $this->hostRoutes();

        // server mode, nobody logged in: the tab would open a board this visitor cannot read
        $this->assertStringNotContainsString('db-tab-handle', $this->get('/host-page')->assertOk()->getContent());
    }

    public function test_the_tab_carries_its_own_css_and_needs_no_framework(): void
    {
        $this->enableTab();

        $html = $this->get('/host-page')->assertOk()->getContent();

        $this->assertStringContainsString('.db-tab-handle {', $html);   // inline stylesheet
        $this->assertStringNotContainsString('x-data', $html);          // no Alpine on a host page
    }
}
