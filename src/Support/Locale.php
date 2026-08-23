<?php

namespace Alle80\Griglia\Support;

use Alle80\Griglia\Settings\AppSettings;
use Carbon\Carbon;

/**
 * Interface language of the board. It is chosen by the `app.locale` setting in /settings
 * ('' = same as the application's `app.locale` config, i.e. APP_LOCALE) and applied by the
 * SetLocale middleware on every board request (Livewire ones included).
 * The available languages are the translation folders of the package (and the published ones).
 */
class Locale
{
    /** Names shown in the selector; for a language not listed here intl or the code is used. */
    public const NAMES = [
        'en' => 'English',
        'it' => 'Italiano',
    ];

    /** Codes of the languages the board is translated into (folders in resources/lang + lang/vendor/griglia). */
    public static function available(): array
    {
        $dirs = [__DIR__.'/../../resources/lang'];
        if (function_exists('lang_path')) {
            $dirs[] = lang_path('vendor/griglia');
        }

        $codes = [];
        foreach ($dirs as $dir) {
            foreach ((array) glob(rtrim($dir, '/').'/*', GLOB_ONLYDIR) as $path) {
                $codes[basename($path)] = true;
            }
        }
        $codes = array_keys($codes);
        sort($codes);

        return $codes;
    }

    /** Name of the language in the language itself («Italiano», «English»). */
    public static function name(string $code): string
    {
        if (isset(self::NAMES[$code])) {
            return self::NAMES[$code];
        }
        if (class_exists(\Locale::class)) {
            $label = \Locale::getDisplayLanguage($code, $code);
            if ($label !== '' && $label !== $code) {
                return ucfirst($label);
            }
        }

        return strtoupper($code);
    }

    /** Options of the select in /settings: '' = same as the config, then one entry per language. */
    public static function options(): array
    {
        $options = ['' => __('griglia::t.settings_options.locale_app', ['locale' => strtoupper((string) config('app.locale', 'en'))])];
        foreach (self::available() as $code) {
            $options[$code] = self::name($code);
        }

        return $options;
    }

    /** Language chosen in /settings, or '' when none was chosen (or it is no longer available). */
    public static function chosen(): string
    {
        $chosen = '';
        try {
            $chosen = (string) app(AppSettings::class)->locale;
        } catch (\Throwable) {
            // settings not migrated yet
        }

        return in_array($chosen, self::available(), true) ? $chosen : '';
    }

    /** Language the board shows itself in right now: the chosen one, otherwise the application's. */
    public static function current(): string
    {
        return self::chosen() ?: app()->getLocale();
    }

    /**
     * Apply the chosen language to the application (and to Carbon, for dates like «3 hours ago»).
     * Without a choice it touches nothing: the board stays in the host application's language.
     */
    public static function apply(): void
    {
        $locale = self::chosen();
        if ($locale === '') {
            return;
        }
        app()->setLocale($locale);
        Carbon::setLocale($locale);
    }
}
