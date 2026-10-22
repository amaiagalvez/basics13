<?php

namespace Basics13\Http\Middleware;

use Closure;
use Basics13\Enums\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\Response;

class SetUserLocale
{
    /**
     * Shows the interface in the authenticated user's language. A user without one, or with a code
     * that is no longer offered, gets the application locale.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $lang = $user instanceof Model ? $user->getAttribute('lang') : null;
        $language = is_string($lang) ? Language::tryFrom($lang) : null;

        App::setLocale($language->value ?? config()->string('app.locale'));

        return $next($request);
    }
}
