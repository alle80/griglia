<?php

namespace Alle80\Griglia\Tests\Feature;

use Alle80\Griglia\Tests\TestCase;

/**
 * The shop window: description, homepage, topics, badges and the social preview image. None of it lives in
 * the code, so `.github/repository.json` holds it and this test keeps that file honest — the same sentence
 * on GitHub and on Packagist, a homepage that matches the documentation site, an image that is really
 * there and really 1280x640. Task 357.
 */
class RepositoryMetadataTest extends TestCase
{
    private const SOCIAL_PREVIEW = 'docs/images/social-preview.png';

    private const SITE = 'https://alle80.github.io/griglia/';

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function contents(string $path): string
    {
        $this->assertFileExists($this->root().'/'.$path);

        return (string) file_get_contents($this->root().'/'.$path);
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        $decoded = json_decode($this->contents($path), true);

        $this->assertIsArray($decoded, "{$path} is not valid JSON");

        return $decoded;
    }

    /** @return array<string, mixed> */
    private function metadata(): array
    {
        return $this->readJson('.github/repository.json');
    }

    public function test_the_metadata_file_describes_the_repository_it_belongs_to(): void
    {
        $metadata = $this->metadata();

        $this->assertSame('alle80/griglia', $metadata['repository']);
        $this->assertSame(self::SITE, $metadata['homepage'], 'the homepage is the documentation site');
        $this->assertSame(self::SOCIAL_PREVIEW, $metadata['social_preview']);

        foreach (['has_issues' => true, 'has_discussions' => true, 'has_wiki' => false, 'has_projects' => false] as $feature => $expected) {
            $this->assertSame($expected, $metadata[$feature], "{$feature} is part of the decision, not a default");
        }
    }

    /**
     * GitHub and Packagist are the two places a stranger reads first; two different sentences there means
     * one of them is out of date. The old product name would be the worst of the two.
     */
    public function test_github_and_packagist_tell_the_same_story(): void
    {
        $description = $this->metadata()['description'];

        $this->assertSame($description, $this->readJson('composer.json')['description']);
        $this->assertLessThanOrEqual(350, strlen($description), 'GitHub truncates a longer description');
        $this->assertStringNotContainsStringIgnoringCase('agent devboard', $description, 'the package is called Griglia');
    }

    public function test_composer_points_at_the_site_the_issues_and_the_security_policy(): void
    {
        $composer = $this->readJson('composer.json');

        $this->assertSame(self::SITE, $composer['homepage']);

        foreach (['issues', 'forum', 'source', 'docs', 'security'] as $channel) {
            $this->assertArrayHasKey($channel, $composer['support'], "Packagist shows no {$channel} link");
            $this->assertStringStartsWith('https://', $composer['support'][$channel]);
        }

        $this->assertStringContainsString('/discussions', $composer['support']['forum']);
        $this->assertSame(self::SITE, $composer['support']['docs']);
        $this->assertContains('laravel', $composer['keywords']);
        $this->assertContains('livewire', $composer['keywords']);
    }

    /**
     * A topic GitHub refuses is dropped in silence, and the repository stays unfindable: lowercase, digits
     * and hyphens only, 50 characters at most, 20 topics at most.
     */
    public function test_the_topics_are_valid_and_cover_what_the_package_is(): void
    {
        $topics = $this->metadata()['topics'];

        $this->assertGreaterThanOrEqual(5, count($topics), 'too few topics to be found by browsing');
        $this->assertLessThanOrEqual(20, count($topics), 'GitHub accepts 20 topics');
        $this->assertSame($topics, array_unique($topics));

        foreach ($topics as $topic) {
            $this->assertMatchesRegularExpression('/^[a-z0-9][a-z0-9-]{0,49}$/', $topic, "{$topic} is not a valid topic");
        }

        foreach (['laravel', 'livewire', 'php'] as $expected) {
            $this->assertContains($expected, $topics, "a Laravel package without the {$expected} topic is invisible");
        }
    }

    public function test_the_social_preview_is_a_1280x640_png(): void
    {
        $path = $this->root().'/'.self::SOCIAL_PREVIEW;

        $this->assertFileExists($path, 'the social preview image is missing');

        $size = getimagesize($path);

        $this->assertIsArray($size, 'the social preview is not an image');
        $this->assertSame(IMAGETYPE_PNG, $size[2], 'GitHub wants a PNG');
        $this->assertSame([1280, 640], [$size[0], $size[1]], 'GitHub renders the preview at 1280x640');
        $this->assertLessThan(1024 * 1024, filesize($path), 'GitHub refuses an image over 1 MB');
    }

    /**
     * Without these tags a link to the documentation is a bare URL in every chat and social app. Material
     * writes none of them by itself.
     */
    public function test_the_documentation_site_carries_the_link_preview_tags(): void
    {
        $override = $this->contents('overrides/main.html');

        foreach ([
            'og:title',
            'og:description',
            'og:url',
            'og:image',
            'og:image:alt',
            'twitter:card',
            'summary_large_image',
            self::SITE.'images/social-preview.png',
        ] as $needle) {
            $this->assertStringContainsString($needle, $override, "the theme override lost {$needle}");
        }

        $this->assertSame(
            self::SITE,
            trim(explode("\n", explode('site_url:', $this->contents('mkdocs.yml'))[1])[0]),
            'the site_url and the repository homepage must be the same URL'
        );
    }

    /**
     * The badges are the first thing that renders on GitHub and on Packagist: one pointing at the wrong
     * repository or at a branch that does not exist shows a grey "invalid" pill for good.
     */
    public function test_the_readme_badges_point_at_this_package_and_at_master(): void
    {
        $readme = $this->contents('README.md');

        foreach ([
            'packagist/v/alle80/griglia.svg',
            'packagist/dt/alle80/griglia.svg',
            'workflow/status/alle80/griglia/tests.yml?branch=master',
            'packagist/l/alle80/griglia.svg',
        ] as $badge) {
            $this->assertStringContainsString($badge, $readme, "the README lost the {$badge} badge");
        }

        $this->assertStringNotContainsString('img.shields.io/badge/', $readme, 'no hand-written badge that cannot go stale');
    }

    /**
     * Every image the README shows has to exist in the repository: GitHub renders a broken icon, and the
     * release mirror only carries what the source tree carries.
     */
    public function test_every_image_the_readme_shows_is_in_the_repository(): void
    {
        preg_match_all('/(?:src|srcset)="([^":]+)"/', $this->contents('README.md'), $matches);

        $this->assertNotEmpty($matches[1], 'the README shows no image any more');

        foreach ($matches[1] as $relative) {
            $this->assertFileExists($this->root().'/'.$relative, "the README points at {$relative}, which is not in the repository");
        }
    }

    public function test_the_metadata_script_is_executable_and_reads_the_metadata_file(): void
    {
        $script = $this->contents('.github/scripts/repo-metadata.php');

        $this->assertStringContainsString('.github/repository.json', $script);
        $this->assertStringContainsString('--apply', $script);
        $this->assertStringContainsString('/topics', $script, 'topics need their own endpoint');
        $this->assertTrue(is_executable($this->root().'/.github/scripts/repo-metadata.php'));
    }
}
