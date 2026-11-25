<?php

declare(strict_types=1);

namespace Igne\ErrorContract\Tests;

use Igne\ErrorContract\ErrorContractServiceProvider;
use Igne\ErrorContract\Tests\Concerns\InteractsWithProblemDetails;
use Orchestra\Testbench\TestCase as Testbench;

/**
 * An application with the package and the fixture routes.
 */
abstract class TestCase extends Testbench
{
    use InteractsWithProblemDetails;

    protected function getPackageProviders($app): array
    {
        return [ErrorContractServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // The session and its cookies, for the routes of the `web` group.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }

    protected function defineRoutes($router): void
    {
        require __DIR__.'/Fixtures/routes.php';
    }
}
