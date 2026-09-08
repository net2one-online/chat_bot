<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * Inside the Bitrix24 iframe the interface language follows the language
     * Bitrix24 reports: Spanish when Bitrix24 is in Spanish, English for any
     * other language. Outside the iframe the default application locale is used.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $inIframe = session('iframe_mode') === true
            || $request->filled('LANG')
            || $request->has('auth')
            || $request->query('embed') === '1';

        $lang = (string) session('bitrix_lang', '');
        $lang = $lang ?: strtolower((string) ($request->input('LANG') ?: $request->query('LANG', '')));

        if ($lang !== '') {
            $request->session()->put('bitrix_lang', $lang);
        }

        App::setLocale($inIframe ? $this->localeFromBitrix($lang) : config('app.locale'));

        return $next($request);
    }

    /**
     * Map a Bitrix24 language code to an application locale.
     */
    protected function localeFromBitrix(string $lang): string
    {
        if (in_array($lang, ['es', 'la', 'es-mx', 'spanish'], true)) {
            return 'es';
        }

        return 'en';
    }
}
