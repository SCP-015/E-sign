<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = 'en';

        $header = (string) $request->header('Accept-Language', '');
        $header = strtolower(trim($header));

        if ($header !== '') {
            $first = explode(',', $header)[0] ?? '';
            $first = trim($first);
            $first = explode(';', $first)[0] ?? $first;
            $first = trim($first);

            if (str_starts_with($first, 'en')) {
                $locale = 'en';
            }

            if (str_starts_with($first, 'id')) {
                $locale = 'id';
            }
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
