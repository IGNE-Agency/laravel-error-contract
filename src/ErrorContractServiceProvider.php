<?php

declare(strict_types=1);

namespace Igne\ErrorContract;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Foundation\Http\Kernel as FoundationKernel;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Answers every error on a configured path as RFC 9457 Problem Details. The package only renders:
 * it holds no documentation and knows no documentation tool.
 */
final class ErrorContractServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/error-contract.php', 'error-contract');

        $this->callAfterResolving(Handler::class, function (Handler $handler): void {
            $handler->renderable($this->render(...));
        });
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/error-contract.php' => $this->app->configPath('error-contract.php')], 'error-contract-config');

        $kernel = $this->app->make(Kernel::class);

        // First, so every log line of the request and a Problem Details error share one trace id.
        $kernel->prependMiddleware(AssignTraceId::class);

        // Laravel's `password.confirm` answers a JSON request itself; ours throws, so the 423 is an error like
        // any other. It is set on the kernel, which writes its aliases to the router whenever its middleware changes.
        if ($kernel instanceof FoundationKernel) {
            $kernel->setMiddlewareAliases([...$kernel->getMiddlewareAliases(), 'password.confirm' => RequirePassword::class]);
        }
    }

    /**
     * Null leaves the response to Laravel: a path outside the configuration, or an exception that
     * is a response already.
     */
    private function render(Throwable $exception, Request $request): ?Response
    {
        if (! $request->is(...config('error-contract.paths')) || $exception instanceof HttpResponseException) {
            return null;
        }

        return Problem::fromException($exception, $request)->toResponse($request);
    }
}
