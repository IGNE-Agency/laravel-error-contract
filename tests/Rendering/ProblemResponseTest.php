<?php

declare(strict_types=1);

use Igne\ProblemDetails\AssignTraceId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

it('answers an exception that extends ProblemException with its status, its message and the code of its class name', function () {
    $response = $this->getJson('/api/fixtures/articles/1');

    $this->assertProblem($response, 404, 'article_not_found')
        ->assertJson([
            'type' => 'https://www.rfc-editor.org/rfc/rfc9110#name-404-not-found',
            'title' => 'Not Found',
            'detail' => 'The article does not exist.',
            'instance' => '/api/fixtures/articles/1',
        ])
        ->assertJsonMissingPath('errors');

    expect(Str::isUuid($response->json('traceId')))->toBeTrue()
        ->and($response->json('traceId'))->toBe(Context::get(AssignTraceId::CONTEXT_KEY));
});

it('answers a validation error with the errors keyed by input, the failed rule and its parameters named after the placeholders of its message', function () {
    $response = $this->postJson('/api/fixtures/articles', ['summary' => 'lowercase', 'rating' => 9, 'tags' => [str_repeat('a', 21)]]);

    $this->assertValidationProblem($response, ['title', 'summary', 'rating', 'tags.0'])
        ->assertJson([
            'type' => 'https://www.rfc-editor.org/rfc/rfc9110#name-422-unprocessable-content',
            'title' => 'Unprocessable Content',
        ])
        ->assertExactJsonStructure(['type', 'title', 'status', 'detail', 'instance', 'traceId', 'code', 'errors'])
        ->assertJsonPath('errors', [
            'title' => [[
                'validation_rule_code' => 'required_without_all',
                'message' => __('validation.required_without_all', ['attribute' => 'title', 'values' => 'slug / subtitle']),
                'properties' => ['values' => ['slug', 'subtitle']],
            ]],
            'summary' => [[
                'validation_rule_code' => 'regex',
                'message' => __('validation.regex', ['attribute' => 'summary']),
            ]],
            'rating' => [[
                'validation_rule_code' => 'between',
                'message' => __('validation.between.numeric', ['attribute' => 'rating', 'min' => 1, 'max' => 5]),
                'properties' => ['min' => 1, 'max' => 5],
            ]],
            'tags.0' => [[
                'validation_rule_code' => 'max',
                'message' => __('validation.max.string', ['attribute' => 'tags.0', 'max' => 20]),
                'properties' => ['max' => 20],
            ]],
        ]);
});

it('summarises a validation error with the message Laravel gives the exception', function () {
    $response = $this->postJson('/api/fixtures/articles', ['rating' => 9]);

    expect($response->json('detail'))->toBe($response->exception->getMessage())
        ->and($response->json('detail'))->toStartWith($response->json('errors.title.0.message'));
});

it('gives every message of a rule object the code of its class', function () {
    $response = $this->postJson('/api/fixtures/articles', ['title' => 'Title', 'slug' => 'Upper', 'password' => '!!!']);

    $this->assertValidationProblem($response, ['slug', 'password'])
        ->assertJsonCount(1, 'errors.slug')
        ->assertJsonPath('errors.slug.0.validation_rule_code', 'lowercase')
        ->assertJsonCount(3, 'errors.password')
        ->assertJsonPath('errors.password.*.validation_rule_code', ['password', 'password', 'password']);
});

it('gives a message added without a rule the invalid code', function () {
    $response = $this->postJson('/api/fixtures/articles/1/publish');

    $this->assertValidationProblem($response, 'title')
        ->assertJsonPath('errors.title', [['validation_rule_code' => 'invalid', 'message' => 'The article is already published.']]);
});

it('answers an exception of the framework with the code, title and type of its status', function (Throwable $exception, int $status, string $code, string $title, string $type) {
    Route::get('api/fixtures/framework-exception', fn () => throw $exception);

    $this->assertProblem($this->getJson('/api/fixtures/framework-exception'), $status, $code)
        ->assertJson(['type' => $type, 'title' => $title, 'instance' => '/api/fixtures/framework-exception'])
        ->assertJsonMissingPath('detail');
})->with([
    'unauthenticated' => [new AuthenticationException, 401, 'unauthorized', 'Unauthorized', 'https://www.rfc-editor.org/rfc/rfc9110#name-401-unauthorized'],
    'denied authorization' => [new AuthorizationException, 403, 'forbidden', 'Forbidden', 'https://www.rfc-editor.org/rfc/rfc9110#name-403-forbidden'],
    'HTTP exception of Symfony' => [new ConflictHttpException('Not shown'), 409, 'conflict', 'Conflict', 'https://www.rfc-editor.org/rfc/rfc9110#name-409-conflict'],
    'expired CSRF token' => [new TokenMismatchException, 419, 'page_expired', 'Page Expired', 'about:blank'],
]);

it('answers an unknown path with a 404 whose code is the status', function () {
    $this->assertProblem($this->getJson('/api/fixtures/does-not-exist'), 404, 'not_found')
        ->assertJsonMissingPath('detail');
});

it('answers a throttled request with a 429 and keeps the headers of the exception', function () {
    Route::get('api/fixtures/throttled', fn () => response()->noContent())->middleware('throttle:1,1');

    $this->getJson('/api/fixtures/throttled')->assertNoContent();

    $this->assertProblem($this->getJson('/api/fixtures/throttled'), 429, 'too_many_requests')
        ->assertHeader('Retry-After')
        ->assertJsonPath('type', 'https://www.rfc-editor.org/rfc/rfc6585#section-4');
});

it('answers an unexpected error with a 500 whose detail is the message only while debugging', function (bool $debug, ?string $detail) {
    config(['app.debug' => $debug]);
    Route::get('api/fixtures/unexpected', fn () => throw new RuntimeException('Kaboom'));

    $response = $this->assertProblem($this->getJson('/api/fixtures/unexpected'), 500, 'internal_server_error');

    expect($response->json('detail'))->toBe($detail);
})->with([
    'debugging' => [true, 'Kaboom'],
    'not debugging' => [false, null],
]);

it('leaves an error outside the configured paths to Laravel', function () {
    $this->get('/does-not-exist')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
});
