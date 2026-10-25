<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Locales supported by the API.
     *
     * @var list<string>
     */
    public const SUPPORTED_LOCALES = ['ar', 'en'];

    public const DEFAULT_LOCALE = 'ar';

    /**
     * Set the application locale from the Accept-Language header and echo it back.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! ($request->is('api/*') || $request->expectsJson())) {
            return $next($request);
        }

        $locale = $this->resolveLocale($request);

        app()->setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    /**
     * Pick the first supported language from the Accept-Language header, ordered by quality.
     */
    private function resolveLocale(Request $request): string
    {
        $header = (string) $request->headers->get('Accept-Language', '');

        $candidates = [];

        foreach (explode(',', $header) as $index => $part) {
            $segments = explode(';', trim($part));
            $tag = strtolower(trim($segments[0]));
            $quality = 1.0;

            foreach (array_slice($segments, 1) as $parameter) {
                if (str_starts_with(trim($parameter), 'q=')) {
                    $quality = (float) substr(trim($parameter), 2);
                }
            }

            if ($tag !== '' && $quality > 0) {
                $candidates[] = ['locale' => substr($tag, 0, 2), 'quality' => $quality, 'index' => $index];
            }
        }

        usort($candidates, fn (array $a, array $b) => [$b['quality'], $a['index']] <=> [$a['quality'], $b['index']]);

        foreach ($candidates as $candidate) {
            if (in_array($candidate['locale'], self::SUPPORTED_LOCALES, true)) {
                return $candidate['locale'];
            }
        }

        return self::DEFAULT_LOCALE;
    }
}
