<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const LANGUAGES = ['en' => 'English', 'ceb' => 'Bisaya', 'fil' => 'Tagalog'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $previous = app()->getLocale();
        $locale = $request->routeIs('admin.two-factor.*') ? 'en'
            : ($request->user()?->locale ?? $request->cookie('autocare_locale', 'en'));
        app()->setLocale(isset(self::LANGUAGES[$locale]) ? $locale : 'en');
        try {
            return $next($request);
        } finally {
            app()->setLocale($previous);
        }
    }
}
