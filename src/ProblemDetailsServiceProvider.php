<?php

declare(strict_types=1);

namespace Igne\ProblemDetails;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Answers every error on a configured path as RFC 9457 Problem Details. Nothing here knows about
 * an API document: {@see OpenApi\OpenApiServiceProvider} documents the answers when the
 * application uses Scramble.
 */
final class ProblemDetailsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/problem-details.php', 'problem-details');

        $this->callAfterResolving(Handler::class, function (Handler $handler): void {
            $handler->renderable($this->render(...));
        });
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/problem-details.php' => $this->app->configPath('problem-details.php')], 'problem-details-config');

        // First, so every log line of the request and a Problem Details error share one trace id.
        $this->app->make(Kernel::class)->prependMiddleware(AssignTraceId::class);
    }

    /**
     * Null leaves the response to Laravel: a path outside the configuration, or an exception that
     * is a response already.
     */
    private function render(Throwable $exception, Request $request): ?Response
    {
        if (! $request->is(...config('problem-details.paths')) || $exception instanceof HttpResponseException) {
            return null;
        }

        return Problem::fromException($exception, $request)->toResponse($request);
    }
}
