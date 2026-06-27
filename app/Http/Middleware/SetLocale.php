<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('streamwave.locales', ['en']);

        // Precedence: authenticated user's saved locale > session > app default.
        $locale = null;

        if (Auth::check() && Auth::user()->locale) {
            $locale = Auth::user()->locale;
        }

        $locale ??= $request->session()->get('locale', config('app.locale'));

        if (! in_array($locale, $supported, true)) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
