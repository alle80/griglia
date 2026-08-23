<?php

namespace Alle80\Griglia\Tests\Feature;

use Alle80\Griglia\GrigliaServiceProvider;
use Alle80\Griglia\Support\Locale;
use Alle80\Griglia\Tests\TestCase;
use Alle80\Griglia\Themes;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

/**
 * The seams a host application is invited to use, documented in docs/configuration/extending.md:
 * published views, published translations (a third board language included), registered styles and
 * settings skins. A recipe nobody exercises is a recipe that quietly stops working — these tests are
 * the promise behind that page.
 */
class ExtensionPointsTest extends TestCase
{
    protected string $viewsDir = '';

    protected string $langDir = '';

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Both overrides must be on disk before the view factory and the translator resolve:
        // ServiceProvider::loadViewsFrom only registers resources/views/vendor/griglia when it is a directory.
        $this->viewsDir = $app->resourcePath('views/vendor/griglia');
        $this->langDir = $app->langPath('vendor/griglia');

        File::ensureDirectoryExists($this->viewsDir.'/components');
        File::put($this->viewsDir.'/components/icon.blade.php', 'PUBLISHED-ICON');

        // Only one key of one group: what is left out must keep coming from the package
        File::ensureDirectoryExists($this->langDir.'/en');
        File::put($this->langDir.'/en/t.php', "<?php return ['theme' => ['add' => 'PUBLISHED-ADD']];");

        // A language the package does not ship at all
        File::ensureDirectoryExists($this->langDir.'/fr');
        File::put($this->langDir.'/fr/t.php', "<?php return ['theme' => ['add' => 'ajouter']];");
    }

    protected function tearDown(): void
    {
        foreach ([$this->viewsDir, $this->langDir] as $dir) {
            if ($dir !== '') {
                File::deleteDirectory($dir);
            }
        }

        parent::tearDown();
    }

    public function test_a_published_view_wins_over_the_package_one(): void
    {
        // Blade components resolve as `griglia::components.<name>`, so publishing overrides them too
        $this->assertStringContainsString('PUBLISHED-ICON', Blade::render('<x-griglia::icon name="working" />'));

        // …file by file: a view that was not published still comes from the package
        $this->assertStringNotContainsString(
            $this->viewsDir,
            view('griglia::components.toasts')->getPath(),
        );
    }

    public function test_published_translations_merge_over_the_package_ones_key_by_key(): void
    {
        $this->assertSame('PUBLISHED-ADD', __('griglia::t.theme.add'));
        $this->assertSame('write here…', __('griglia::t.theme.placeholder'), 'a key left out keeps following the package');
    }

    public function test_a_third_board_language_is_a_folder_in_lang_vendor_griglia(): void
    {
        $this->assertContains('fr', Locale::available());
        $this->assertArrayHasKey('fr', Locale::options(), 'the new language is offered in Settings → App');
        $this->assertSame('ajouter', __('griglia::t.theme.add', [], 'fr'));

        // an untranslated key falls back to the fallback locale instead of showing the raw key
        config(['app.fallback_locale' => 'en']);
        $this->assertSame('PUBLISHED-ADD', __('griglia::t.theme.add', [], 'de'));
    }

    public function test_a_dedicated_style_registers_its_route_and_its_settings_skin(): void
    {
        $styles = new \ReflectionProperty(Themes::class, 'styles');
        $skins = new \ReflectionProperty(Themes::class, 'skins');
        $before = [$styles->getValue(), $skins->getValue()];

        try {
            Themes::registerStyle('retro', ['label' => 'Retro', 'icon' => '🕹️', 'route' => '/retro']);

            $switcher = Themes::switcher();
            $this->assertSame('/retro', $switcher['retro']['url']);
            $this->assertSame('retro', array_key_first($switcher), 'dedicated styles come before generic themes');
            $this->assertTrue(Themes::known('retro'));

            // without a skin the page falls back to the generic one; with it, every key is used as given
            $this->assertSame('griglia::layouts.themed', Themes::settingsSkin('retro')['layout']);
            Themes::registerSkin('retro', ['layout' => 'layouts.retro', 'layoutData' => [], 'home' => '/retro', 'card' => 'retro-card']);
            $skin = Themes::settingsSkin('retro');
            $this->assertSame(['layouts.retro', '/retro', 'retro-card'], [$skin['layout'], $skin['home'], $skin['card']]);
        } finally {
            $styles->setValue(null, $before[0]);
            $skins->setValue(null, $before[1]);
        }
    }

    public function test_the_documented_publish_tags_exist(): void
    {
        foreach (['griglia-config', 'griglia-views', 'griglia-lang', 'griglia-assets', 'griglia-scripts', 'griglia-agents'] as $tag) {
            $this->assertNotEmpty(
                ServiceProvider::pathsToPublish(GrigliaServiceProvider::class, $tag),
                "vendor:publish --tag={$tag} publishes nothing",
            );
        }
    }

    public function test_the_extending_page_covers_every_seam_and_is_reachable(): void
    {
        $root = realpath(__DIR__.'/../..');
        $page = (string) file_get_contents($root.'/docs/configuration/extending.md');

        foreach ([
            'griglia-views', 'griglia-lang', 'lang/vendor/griglia', 'registerTheme', 'registerStyle',
            'registerSkin', 'TodoChanged', 'canAccessGriglia', 'canManageGriglia', 'GRIGLIA_USER_MODEL',
            'register_routes',
        ] as $seam) {
            $this->assertStringContainsString($seam, $page, "the extending page does not document {$seam}");
        }

        $this->assertFileExists($root.'/docs/configuration/extending.it.md');
        $this->assertStringContainsString('configuration/extending.md', (string) file_get_contents($root.'/mkdocs.yml'), 'the page is not in the nav');
        $this->assertStringContainsString('docs/configuration/extending.md', (string) file_get_contents($root.'/README.md'), 'the README does not link the page');

        // the sample pack is shipped and pointed at, in both languages
        $this->assertFileExists($root.'/resources/themes/pollon/theme.json');
        foreach (['themes.md', 'themes.it.md'] as $themes) {
            $this->assertStringContainsString(
                'resources/themes/pollon',
                (string) file_get_contents($root.'/docs/features/'.$themes),
                "{$themes} does not link the sample pack",
            );
        }
    }
}
