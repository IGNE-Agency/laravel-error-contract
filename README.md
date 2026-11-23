# igne-agency/laravel-problem-details

Answers every API error of a Laravel application as [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) Problem Details (`application/problem+json`). When the application uses [Scramble](https://scramble.dedoc.co), the package also documents those answers per endpoint in the OpenAPI document.

```json
{
    "type": "https://www.rfc-editor.org/rfc/rfc9110#name-404-not-found",
    "title": "Not Found",
    "status": 404,
    "detail": "The article does not exist.",
    "instance": "/api/articles/1",
    "traceId": "9f6e3c1e-6d0b-4a7e-9a55-2c1b0f4a8d11",
    "code": "article_not_found"
}
```

## Requirements

- PHP 8.4
- Laravel 12 or 13
- Optional: dedoc/scramble 0.13.52 or a later 0.13 release

## Install

```bash
composer require igne-agency/laravel-problem-details
```

Nothing else has to be registered. To change the paths, publish the config:

```bash
php artisan vendor:publish --tag=problem-details-config
```

| Key | Default | Meaning |
|---|---|---|
| `paths` | `['api/*']` | The request paths whose errors are answered as Problem Details, as patterns of `Request::is()`. An error on any other path is left to Laravel. |

## The two halves

The package has two service providers.

**`ProblemDetailsServiceProvider` renders.** It needs nothing but Laravel:

- every exception on a configured path becomes a Problem Details response;
- `code` is the key a client switches on: the reason phrase of the status (`not_found`) for a framework exception, the class name for an error of the application;
- a validation error lists its `errors` per input, each with the `validation_rule_code` of the failed rule, Laravel's `message` and the parameters of the rule as `properties`;
- every request gets a trace id in the log context, returned as `traceId`.

**`OpenApi\OpenApiServiceProvider` documents.** It does nothing unless Scramble is installed, by the application itself or by another package it requires. With Scramble, each endpoint documents:

- one Problem Details response per status, with a named schema per `code` (`ArticleNotFoundProblem`);
- for a validation error, the inputs of the request and the rule codes each can fail (`StoreArticleValidationProblem`);
- `Problem` and `ProblemCode`, the closed set of every problem the API can answer.

## Errors of the application

Extend `ProblemException` once per error and pass the status to the constructor:

```php
use Igne\ProblemDetails\ProblemException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The article was removed or never existed.
 */
final class ArticleNotFoundException extends ProblemException
{
    public function __construct()
    {
        parent::__construct(Response::HTTP_NOT_FOUND, 'The article does not exist.');
    }
}
```

- The class name is the `code`: `article_not_found`.
- The message is the `detail`, shown to the user, so it must be safe to show.
- The first paragraph of the docblock describes the problem in the API document.

Any other exception, Symfony's and Laravel's own HTTP exceptions included, is answered with the `code` of its status and without a `detail`.

## What gets documented

The documentation reads the exceptions Scramble knows an action throws. Scramble by itself only sees what the action throws directly, plus a few framework cases (a missing model, the `auth` and `can` middleware, a Form Request).

To also document what the middleware of a route throws and what the methods an action calls throw, install [`igne-agency/laravel-scramble-extensions`](https://github.com/IGNE-Agency/laravel-scramble-extensions). The two packages do not reference each other: that one adds the exceptions to Scramble's model, and this one documents whatever is there.

## Development

```bash
composer install
composer test
```

`tests/Rendering` runs without Scramble; `tests/OpenApi` is skipped when Scramble is not installed.
