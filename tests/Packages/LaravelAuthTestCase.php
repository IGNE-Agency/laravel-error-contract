<?php

declare(strict_types=1);

namespace Igne\ErrorContract\Tests\Packages;

use Igne\Auth\AuthenticationServiceProvider;
use Igne\ErrorContract\ErrorContractServiceProvider;
use Igne\ErrorContract\Tests\Concerns\InteractsWithProblemDetails;
use Illuminate\Contracts\Http\Kernel;
use Laravel\Fortify\Features;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase as Testbench;
use ReflectionClass;

/**
 * An application that installed igne-agency/laravel-auth next to this package. That package knows
 * nothing of this one: it throws plain Symfony HTTP exceptions. This side is where the two are
 * tested together.
 */
abstract class LaravelAuthTestCase extends Testbench
{
    use InteractsWithProblemDetails;

    protected function getPackageProviders($app): array
    {
        return [ErrorContractServiceProvider::class, AuthenticationServiceProvider::class, FortifyServiceProvider::class, SanctumServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set([
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'auth.providers.users.model' => User::class,
            'auth.timebox_duration' => 0,
            'cache.default' => 'array',
            'database.default' => 'testing',
            'fortify.views' => false,
            'fortify.limiters.login' => null,
            'fortify.features' => [Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true])],
            'hashing.bcrypt.rounds' => 4,
            'mail.default' => 'array',
            'queue.default' => 'sync',
            'session.driver' => 'array',
            'sanctum.stateful' => ['localhost'],
        ]);

        // As an application does with `$middleware->statefulApi()`.
        $app->make(Kernel::class)->prependMiddlewareToGroup('api', EnsureFrontendRequestsAreStateful::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();

        foreach ([FortifyServiceProvider::class, SanctumServiceProvider::class, AuthenticationServiceProvider::class] as $provider) {
            $this->loadMigrationsFrom(dirname((string) (new ReflectionClass($provider))->getFileName(), 2).'/database/migrations');
        }
    }

    protected function user(): User
    {
        return User::query()->create(['name' => 'Jan', 'email' => 'jan@example.com', 'password' => bcrypt('password')]);
    }
}
