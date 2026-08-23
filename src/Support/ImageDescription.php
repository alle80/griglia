<?php

namespace Alle80\Griglia\Support;

use Alle80\Griglia\Ai\Agents\ImageDescriber;
use Alle80\Griglia\Models\Attachment;
use Alle80\Griglia\Settings\AppSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Image;

/**
 * Image description service: uses the Laravel AI SDK with the configured
 * provider (config ai.image_description / ai.default), with failover when
 * more than one is listed. When no provider has a key, it is a no-op.
 */
class ImageDescription
{
    /** Providers to use, in order of preference (the first is the primary one, the others failover). */
    public static function providers(): array
    {
        $settings = app(AppSettings::class);
        if (! $settings->ai_describe_images) {
            return [];
        }

        // Provider chosen in /settings, otherwise from .env (AI_IMAGE_PROVIDERS with failover, then AI_PROVIDER)
        $configured = $settings->ai_image_provider !== '' ? [$settings->ai_image_provider] : config('ai.image_description.providers', []);
        $candidates = $configured ?: [config('ai.default')];

        // Keep only the providers that actually have a key (or that need none, e.g. ollama)
        return array_values(array_filter($candidates, function ($name) {
            $p = config("ai.providers.{$name}");

            return $p && (($p['driver'] ?? '') === 'ollama' || ! empty($p['key']));
        }));
    }

    public static function enabled(): bool
    {
        return self::providers() !== [];
    }

    public static function describe(Attachment $attachment): ?string
    {
        if (! self::enabled() || ! Storage::disk(config('griglia.attachments_disk', 'local'))->exists($attachment->path)) {
            return null;
        }

        try {
            $providers = self::providers();
            $model = app(AppSettings::class)->ai_image_model ?: (config('ai.image_description.model') ?: null);

            $response = (new ImageDescriber)->prompt(
                'Describe this image.',
                attachments: [Image::fromStorage($attachment->path, config('griglia.attachments_disk', 'local'))],
                provider: count($providers) > 1 ? $providers : $providers[0],
                model: $model,
            );

            $text = trim((string) $response);

            if ($text === '') {
                return null;
            }

            $attachment->update(['description' => $text]);

            return $text;
        } catch (\Throwable $e) {
            Log::warning('ImageDescription: descrizione fallita', ['attachment' => $attachment->id, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
