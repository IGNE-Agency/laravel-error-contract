<?php

declare(strict_types=1);

namespace Igne\ProblemDetails\Tests;

use Dedoc\Scramble\ScrambleServiceProvider;
use Igne\ProblemDetails\OpenApi\OpenApiServiceProvider;
use Igne\ProblemDetails\ProblemDetailsServiceProvider;
use Igne\ProblemDetails\Tests\Concerns\InteractsWithProblemDetails;
use Orchestra\Testbench\TestCase as Testbench;

/**
 * An application with the package and the fixture routes. Scramble is part of it only when it is
 * installed, so the same tests of the responses run with and without it.
 */
abstract class TestCase extends Testbench
{
    use InteractsWithProblemDetails;

    protected function getPackageProviders($app): array
    {
        return array_values(array_filter([
            class_exists(ScrambleServiceProvider::class) ? ScrambleServiceProvider::class : null,
            ProblemDetailsServiceProvider::class,
            OpenApiServiceProvider::class,
        ]));
    }

    protected function defineEnvironment($app): void
    {
        // The document is read without the access check of Scramble's own middleware.
        $app['config']->set('scramble.middleware', []);
    }

    protected function defineRoutes($router): void
    {
        require __DIR__.'/Fixtures/routes.php';
    }
}
