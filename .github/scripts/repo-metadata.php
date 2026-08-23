#!/usr/bin/env php
<?php

/**
 * Repository metadata — the settings that are not in the code.
 *
 * Description, homepage, topics and the feature toggles of github.com/alle80/griglia live in
 * `.github/repository.json`, so a change is reviewable and does not depend on somebody remembering a
 * settings page. This script reads that file and either compares it with the live repository or writes
 * it back through the GitHub API.
 *
 *   php .github/scripts/repo-metadata.php            # show what differs (exit 1 when something does)
 *   php .github/scripts/repo-metadata.php --apply    # write the file to the repository
 *
 * The social preview image is the exception: GitHub has no API for it. It is uploaded by hand once, from
 * Settings → General → Social preview, using the file named in `social_preview`.
 *
 * Needs the GitHub CLI (`gh auth login`). No autoloader on purpose: it runs on a bare checkout.
 */
$root = dirname(__DIR__, 2);
$file = $root.'/.github/repository.json';
$apply = in_array('--apply', array_slice($argv, 1), true);

/** The keys of `repository.json` that PATCH /repos accepts, in the order they are shown. */
const PATCHABLE = ['description', 'homepage', 'has_issues', 'has_discussions', 'has_wiki', 'has_projects'];

/** Private vulnerability reporting is not in PATCH /repos: it has an endpoint of its own. */
const VULNERABILITY_REPORTING = 'private_vulnerability_reporting';

function fail(string $message): never
{
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}

function gh(string ...$arguments): string
{
    $command = 'gh '.implode(' ', array_map('escapeshellarg', $arguments)).' 2>&1';
    exec($command, $output, $status);
    $text = implode(PHP_EOL, $output);

    if ($status !== 0) {
        fail('gh failed: '.$command.PHP_EOL.$text);
    }

    return $text;
}

$wanted = json_decode((string) file_get_contents($file), true);

if (! is_array($wanted) || ! isset($wanted['repository'])) {
    fail("{$file} is not a valid metadata file");
}

$repository = $wanted['repository'];
$live = json_decode(gh('api', 'repos/'.$repository), true);

$differences = [];

foreach (PATCHABLE as $key) {
    if (($live[$key] ?? null) !== $wanted[$key]) {
        $differences[$key] = [$live[$key] ?? null, $wanted[$key]];
    }
}

$liveReporting = json_decode(gh('api', 'repos/'.$repository.'/'.str_replace('_', '-', VULNERABILITY_REPORTING)), true);

if (($liveReporting['enabled'] ?? null) !== $wanted[VULNERABILITY_REPORTING]) {
    $differences[VULNERABILITY_REPORTING] = [$liveReporting['enabled'] ?? null, $wanted[VULNERABILITY_REPORTING]];
}

$liveTopics = $live['topics'] ?? [];
sort($liveTopics);
$wantedTopics = $wanted['topics'];
sort($wantedTopics);

if ($liveTopics !== $wantedTopics) {
    $differences['topics'] = [implode(', ', $liveTopics), implode(', ', $wantedTopics)];
}

if ($differences === []) {
    echo "{$repository}: description, homepage, topics, features and security reporting already match .github/repository.json".PHP_EOL;
    exit(0);
}

foreach ($differences as $key => [$from, $to]) {
    printf("%-16s %s  ->  %s%s", $key, var_export($from, true), var_export($to, true), PHP_EOL);
}

if (! $apply) {
    echo PHP_EOL.'Run with --apply to write these to the repository.'.PHP_EOL;
    exit(1);
}

$patch = ['gh', 'api', '-X', 'PATCH', 'repos/'.$repository];

foreach (PATCHABLE as $key) {
    $patch[] = is_bool($wanted[$key])
        ? '-F'
        : '-f';
    $patch[] = $key.'='.(is_bool($wanted[$key]) ? var_export($wanted[$key], true) : $wanted[$key]);
}

gh(...array_slice($patch, 1));

gh('api', '-X', $wanted[VULNERABILITY_REPORTING] ? 'PUT' : 'DELETE',
    'repos/'.$repository.'/'.str_replace('_', '-', VULNERABILITY_REPORTING));

$topics = ['api', '-X', 'PUT', 'repos/'.$repository.'/topics'];

foreach ($wanted['topics'] as $topic) {
    $topics[] = '-f';
    $topics[] = 'names[]='.$topic;
}

gh(...$topics);

echo PHP_EOL."Applied to {$repository}.".PHP_EOL;
echo 'The social preview is not in the API: upload '.$wanted['social_preview'].' from Settings → General → Social preview.'.PHP_EOL;
