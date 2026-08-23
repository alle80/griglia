<?php

namespace Alle80\Griglia\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Describes an image attached to a todo to make it searchable by free text.
 *
 * Provider and model are NOT fixed here: they come from config('ai.image_description')
 * (env AI_IMAGE_PROVIDERS / AI_IMAGE_MODEL) and, failing that, from the SDK's default
 * provider, with the cheapest model of that provider. See Alle80\Griglia\Support\ImageDescription.
 */
#[UseCheapestModel]
#[MaxTokens(300)]
#[Timeout(60)]
class ImageDescriber implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        $lang = match (app()->getLocale()) {
            'it' => 'Italian', 'en' => 'English', default => app()->getLocale()
        };

        return <<<TXT
        You describe images for a searchable archive.
        Answer only with the description, in {$lang}, in 2-3 sentences: what the image shows,
        main objects and subjects, places, dominant colours, and transcribe any readable text.
        Do not identify people. No preamble, no bullet points.
        TXT;
    }
}
