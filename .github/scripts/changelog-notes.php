#!/usr/bin/env php
<?php

/**
 * Release notes for one version, read out of CHANGELOG.md.
 *
 * The changelog is the only place where a release is described, so a GitHub Release must not be written a
 * second time by hand: `.github/workflows/release.yml` calls this script when a `vX.Y.Z` tag is pushed and
 * publishes what it prints. No Composer autoloader, no framework — the workflow runs it on a bare checkout.
 *
 *   php .github/scripts/changelog-notes.php 0.89.12              # the notes for that version
 *   php .github/scripts/changelog-notes.php v0.89.12 --previous  # the version released before it
 *   php .github/scripts/changelog-notes.php 0.89.12 --date       # its release date
 *   php .github/scripts/changelog-notes.php --links              # the whole link reference block
 *
 * Options: --previous (print the preceding released version instead of the notes, nothing when it is the
 * first one) · --date (print the release date) · --links (print the `[x.y.z]: …compare…` block that closes
 * the changelog, so it never has to be maintained by hand; takes no version) · --file=PATH (another
 * changelog).
 *
 * Exit codes: 0 found · 2 wrong usage · 3 no section for that version.
 */
$args = array_slice($argv, 1);
$version = null;
$file = dirname(__DIR__, 2).'/CHANGELOG.md';
$want = 'notes';

foreach ($args as $arg) {
    if ($arg === '--previous' || $arg === '--date' || $arg === '--links') {
        $want = ltrim($arg, '-');
    } elseif (str_starts_with($arg, '--file=')) {
        $file = substr($arg, 7);
    } elseif ($arg === '-h' || $arg === '--help') {
        fwrite(STDOUT, "usage: changelog-notes.php <version> [--previous] [--date] [--file=PATH]\n");
        exit(0);
    } elseif ($version === null && ! str_starts_with($arg, '-')) {
        $version = ltrim($arg, 'v');
    } else {
        fwrite(STDERR, "changelog-notes.php: unknown argument {$arg}\n");
        exit(2);
    }
}

if ($version === null && $want !== 'links') {
    fwrite(STDERR, "usage: changelog-notes.php <version> [--previous] [--date] [--file=PATH]\n");
    exit(2);
}

if (! is_file($file)) {
    fwrite(STDERR, "changelog-notes.php: {$file} does not exist\n");
    exit(2);
}

$lines = explode("\n", (string) file_get_contents($file));

/**
 * The changelog closes with the `[0.89.12]: https://…/compare/…` reference definitions. They belong to no
 * release, so the last section has to stop before them or it would swallow the lot.
 */
$body = count($lines);
while ($body > 0) {
    $line = trim($lines[$body - 1]);
    if ($line !== '' && ! preg_match('/^\[[^\]]+\]:\s+http/', $line)) {
        break;
    }
    $body--;
}
$lines = array_slice($lines, 0, $body);

/** Released sections in file order: [version, date, first line, last line]. `Unreleased` is not one of them. */
$sections = [];
$open = null;

foreach ($lines as $index => $line) {
    if (! preg_match('/^## \[([^\]]+)\](?:\s*-\s*(\S+))?/', $line, $match)) {
        continue;
    }

    if ($open !== null) {
        $sections[$open['version']] = $open + ['end' => $index - 1];
        $open = null;
    }

    if (strcasecmp($match[1], 'unreleased') === 0) {
        continue;
    }

    $open = ['version' => $match[1], 'date' => $match[2] ?? '', 'start' => $index + 1];
}

if ($open !== null) {
    $sections[$open['version']] = $open + ['end' => count($lines) - 1];
}

if ($want === 'links') {
    $order = array_keys($sections);
    $repository = 'https://github.com/alle80/griglia';
    $out = $order === [] ? '' : "[Unreleased]: {$repository}/compare/v{$order[0]}...HEAD\n";

    foreach ($order as $position => $released) {
        $previous = $order[$position + 1] ?? null;
        $out .= $previous === null
            ? "[{$released}]: {$repository}/releases/tag/v{$released}\n"
            : "[{$released}]: {$repository}/compare/v{$previous}...v{$released}\n";
    }

    fwrite(STDOUT, $out);
    exit(0);
}

if (! isset($sections[$version])) {
    fwrite(STDERR, "changelog-notes.php: CHANGELOG.md has no '## [{$version}]' section\n");
    exit(3);
}

$section = $sections[$version];

if ($want === 'date') {
    fwrite(STDOUT, $section['date']."\n");
    exit(0);
}

if ($want === 'previous') {
    $order = array_keys($sections);
    $position = array_search($version, $order, true);
    $previous = $order[$position + 1] ?? '';   // the changelog lists the newest release first
    fwrite(STDOUT, $previous === '' ? '' : $previous."\n");
    exit(0);
}

$notes = implode("\n", array_slice($lines, $section['start'], $section['end'] - $section['start'] + 1));
fwrite(STDOUT, trim($notes)."\n");
