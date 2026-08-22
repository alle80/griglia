<?php

namespace Alle80\Griglia\Tests\Feature;

use Alle80\Griglia\Tests\TestCase;

/**
 * Griglia is distributed under the MIT license: the text, the composer metadata and the documentation must
 * say the same thing, and the README links to them must exist (it pointed at a LICENSE.md that never did).
 * Task 350.
 */
class LicenseTest extends TestCase
{
    protected function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_the_mit_license_text_ships_with_the_package(): void
    {
        $license = (string) file_get_contents($this->root().'/LICENSE');

        $this->assertStringStartsWith('MIT License', $license);
        $this->assertStringContainsString('Copyright (c) 2026 Alessandro (alle80)', $license);
        $this->assertStringContainsString('Permission is hereby granted, free of charge', $license);
        $this->assertStringContainsString('THE SOFTWARE IS PROVIDED "AS IS"', $license);
    }

    public function test_composer_declares_the_same_license(): void
    {
        $composer = json_decode((string) file_get_contents($this->root().'/composer.json'), true);

        $this->assertSame('MIT', $composer['license'], 'the SPDX identifier Packagist reads must stay MIT');
    }

    public function test_the_license_is_documented_in_both_languages_and_in_the_navigation(): void
    {
        foreach (['docs/contributing/license.md', 'docs/contributing/license.it.md'] as $page) {
            $this->assertFileExists($this->root().'/'.$page);
            $this->assertStringContainsString('MIT', (string) file_get_contents($this->root().'/'.$page));
        }

        $this->assertStringContainsString(
            'contributing/license.md',
            (string) file_get_contents($this->root().'/mkdocs.yml'),
            'the license page must be in the documentation navigation'
        );
    }

    public function test_the_readme_links_only_to_files_that_exist(): void
    {
        $readme = (string) file_get_contents($this->root().'/README.md');
        $this->assertStringContainsString('](LICENSE)', $readme);

        preg_match_all('/]\((?!https?:|#|mailto:)([^)#]+)/', $readme, $matches);
        $this->assertNotEmpty($matches[1]);

        $missing = [];
        foreach (array_unique($matches[1]) as $target) {
            if (! file_exists($this->root().'/'.trim($target))) {
                $missing[] = $target;
            }
        }

        $this->assertSame([], $missing, 'README links to missing files: '.implode(', ', $missing));
    }
}
