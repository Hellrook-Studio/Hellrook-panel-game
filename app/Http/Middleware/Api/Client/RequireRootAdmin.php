<?php

namespace Pterodactyl\Http\Middleware\Api\Client;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Fitur yang dikelola provider (database dan sub-user server): hanya admin yang boleh,
 * baik lewat panel maupun lewat API client. Portal memakai key milik admin, jadi tetap bisa.
 */
class RequireRootAdmin
{
    public function handle(Request $request, \Closure $next): mixed
    {
        if (!$request->user()?->root_admin) {
            throw new AccessDeniedHttpException('This feature is managed by your provider.');
        }

        return $next($request);
    }
}
