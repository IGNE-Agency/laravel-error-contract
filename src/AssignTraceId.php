<?php

declare(strict_types=1);

namespace Igne\ErrorContract;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request a trace id in the log context, so each log line written while handling it
 * carries the same `trace_id` as the `traceId` member of a Problem Details error response.
 */
final class AssignTraceId
{
    public const string CONTEXT_KEY = 'trace_id';

    public function handle(Request $request, Closure $next): Response
    {
        Context::add(self::CONTEXT_KEY, (string) Str::uuid());

        return $next($request);
    }
}
