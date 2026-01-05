<?php

namespace ShaunTheGeek\LaravelLocaleSetter\Middleware;

use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

class DetectLocale
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $firstLang = self::getFirstLang($request->server('HTTP_ACCEPT_LANGUAGE'));
        $map = $this->app['config']['locale']['map'];
        $locales = empty($map) ? $this->getLocales() : [];
        $locale = self::lang2locale($firstLang, $map, $locales);
        if (empty($locale)) {
            $locale = $this->app['config']['app']['locale'];
        }
        $this->app->setLocale($locale);

        return $next($request);
    }

    /**
     * decode Accept-Language, return first choice.
     * for example: accept-language:zh-CN,zh;q=0.8,zh-TW;q=0.6,zh-HK;q=0.4,en;q=0.2,ja;q=0.2
     * will return zh-CN
     */
    public static function getFirstLang(?string $accept): string|bool
    {
        if (empty($accept)) {
            return false;
        }
        $firstLang = $accept;
        if (strpos($accept, ';') !== false) {
            $tmp = explode(';', $accept);
            $accept = $tmp[0];
        }
        if (strpos($accept, ',') !== false) {
            $tmp = explode(',', $accept);
            foreach ($tmp as $one) {
                if (strpos($one, 'q=') !== false) {
                    continue;
                } else {
                    $firstLang = trim($one);
                    break;
                }
            }
        }

        return $firstLang;
    }

    /**
     * convert language to locale and match locales.
     * for example: zh-CN to zh_CN, en-US to en_US
     * then: match locales en_US, if there is no en_US but en, match en_US to en
     */
    public static function lang2locale(?string $lang, array $map, array $locales): ?string
    {
        if (empty($lang)) {
            return null;
        }
        $locale = str_replace('-', '_', $lang);
        if (! empty($map) && isset($map[$locale])) {
            return $map[$locale];
        }

        if (in_array($locale, $locales)) {
            return $locale;
        }
        // If exact match not found, try to find the best match
        // if locale is en_US, locales are en and zh_CN, match en_US to en
        if (strpos($locale, '_') !== false) {
            $langPrefix = substr($locale, 0, strpos($locale, '_'));
            foreach ($locales as $availableLocale) {
                if ($availableLocale === $langPrefix) {
                    return $availableLocale;
                }
            }
        }

        // if locale is en, locales are en_US and zh_CN, match en to en_US
        foreach ($locales as $availableLocale) {
            if (strpos($availableLocale, '_') !== false) {
                $availablePrefix = substr($availableLocale, 0, strpos($availableLocale, '_'));
                if ($availablePrefix === $locale) {
                    return $availableLocale;
                }
            }
        }

        // if locale is en_GB, locales are en_US and zh_CN, match en_GB to en_US
        // Find the best match by comparing language prefixes
        if (strpos($locale, '_') !== false) {
            $langPrefix = substr($locale, 0, strpos($locale, '_'));
            $bestMatch = null;

            foreach ($locales as $availableLocale) {
                // Check if available locale has a country part (contains underscore)
                if (strpos($availableLocale, '_') !== false) {
                    $availablePrefix = substr($availableLocale, 0, strpos($availableLocale, '_'));
                    // If prefixes match, we have a potential match
                    if ($availablePrefix === $langPrefix) {
                        $bestMatch = $availableLocale;
                        break; // Return first match
                    }
                }
            }

            return $bestMatch;
        }

        return null;
    }

    /**
     * get locales, first match in config, then scan lang directory
     */
    public function getLocales(): array
    {
        $locales = $this->app['config']['locale']['locales'];
        if (! empty($locales)) {
            return $locales;
        }
        $locales = [];
        $langDir = $this->app->langPath();
        if (! is_dir($langDir)) {
            return [];
        }
        // scan json files first: en.json, zh_CN.json, ...
        $jsonFiles = glob($langDir.'/*.json');
        $jsonLocales = [];
        if (! empty($jsonFiles)) {
            $jsonLocales = array_map(function ($file) {
                return pathinfo($file, PATHINFO_FILENAME);
            }, $jsonFiles);
        }

        // scan dir: en, zh_CN, ... expect . and ..
        $dirLocales = [];
        $dirs = scandir($langDir);
        $dirLocales = array_filter($dirs, function ($dir) {
            return $dir !== '.' && $dir !== '..';
        });

        // combine json locales and dir locales, with json taking precedence
        $locales = array_values(array_unique(array_merge($jsonLocales, $dirLocales)));

        return $locales;
    }
}
