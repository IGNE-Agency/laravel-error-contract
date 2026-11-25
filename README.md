# igne-agency/laravel-problem-details

Answers every API error of a Laravel application as [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) Problem Details (`application/problem+json`). The package only renders: it holds no documentation and knows no documentation tool.

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

## Install

```bash
composer require igne-agency/laravel-problem-details
```

Nothing else has to be registered. To change the settings, publish the config:

```bash
php artisan vendor:publish --tag=problem-details-config
```

| Key | Default | Meaning |
|---|---|---|
| `paths` | `['api/*']` | The request paths whose errors are answered as Problem Details, as patterns of `Request::is()`. An error on any other path is left to Laravel. |
| `namespaces` | `['App\\', 'Igne\\']` | An HTTP exception declared in one of these namespaces is a named error. |
| `codes` | `[]` | A single class from outside those namespaces to name, or a class to give another code than its name. |

## What it does

- Every exception on a configured path becomes a Problem Details response.
- `code` is the key a client switches on: the class name of a named error (`article_not_found`), the reason phrase of the status (`not_found`) for any other exception.
- A validation error lists its `errors` per input, each with the `validation_rule_code` of the failed rule, Laravel's `message` and the parameters of the rule as `properties`.
- Every request gets a trace id in the log context, returned as `traceId`.
- Laravel's `password.confirm` answers a JSON request itself, which no exception handler sees. The package points that alias at a version that throws, so the 423 is a problem like any other (`password_confirmation_required`). Outside the configured paths Laravel renders the same status and message as before.

## Named errors

An error with a class of its own is told by that class. Nothing has to extend a class of this package:

```php
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The article was removed or never existed.
 */
final class ArticleNotFoundException extends NotFoundHttpException
{
    public function __construct()
    {
        parent::__construct('The article does not exist.');
    }
}
```

- The class name is the `code`: `article_not_found`.
- The message is the `detail`, shown to the user, so it must be safe to show.
- The status comes from the exception, as for any HTTP exception.

This holds for an HTTP exception of the application and of any package in a configured namespace. A package that throws Symfony's HTTP exceptions therefore needs no dependency on this one, and answers Laravel's own errors when this package is not installed. `ProblemException` remains as an optional base class for a status Symfony has no exception for.

Any other exception, Symfony's and Laravel's own HTTP exceptions and those of a third party included, is answered with the `code` of its status and without a `detail`. To name one of a third party, list it in `codes`, or map it to an exception of your own with Laravel's `$exceptions->map()`.

## Documentation

This package documents nothing. [`igne-agency/laravel-scramble-extensions`](https://github.com/IGNE-Agency/laravel-api-docs) documents the errors of an API by asking the application what it answers, so it documents Problem Details when this package is installed, without the two referring to each other.

## Development

```bash
composer install
composer test
```
