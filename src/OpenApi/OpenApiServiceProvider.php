<?php

declare(strict_types=1);

namespace Igne\ProblemDetails\OpenApi;

use Dedoc\Scramble\Scramble;
use Illuminate\Support\ServiceProvider;

/**
 * Documents the Problem Details answers in the API document, only when Scramble is installed:
 * by the application itself or by another package it requires. Scramble documents each endpoint
 * from the exceptions and the validation rules of its route; nothing else has to be registered or
 * kept up to date.
 */
final class OpenApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! class_exists(Scramble::class)) {
            return;
        }

        Scramble::configure()
            ->withRuleTransformers([ProblemDetailsDocumentation::class])
            ->withOperationTransformers([ProblemDetailsDocumentation::class])
            ->withDocumentTransformers([ProblemSchemas::class]);

        // The code does not change between two deploys, so outside the local environment the
        // document is generated once, by `php artisan optimize` in the deploy, and served from
        // Scramble's cache.
        if (! $this->app->isLocal()) {
            $this->optimizes('scramble:cache', 'scramble:clear');
        }
    }
}
