<?php

declare(strict_types=1);

use Igne\Auth\AuthenticationServiceProvider;
use Igne\Auth\Http\Exceptions\CurrentSessionException;
use Igne\Auth\Http\Exceptions\LoginCodeUnavailableException;
use Igne\Auth\Http\Exceptions\PasswordUnavailableException;
use Igne\Auth\TwoFactor\Exceptions\TwoFactorRequiredException;
use Igne\Auth\TwoFactor\Exceptions\TwoFactorSetupRequiredException;
use Igne\ErrorContract\ProblemDetails;
use Igne\ErrorContract\ProblemException;

// The auth package needs Laravel 13; on an older Laravel it is not installed and there is nothing to test.
if (! class_exists(AuthenticationServiceProvider::class)) {
    return;
}

// igne-agency/laravel-auth is a real package that follows the rules: it throws plain HTTP exceptions and depends on
// nothing of this package. These tests are the proof that its errors come out as Problem Details all the same.

it('names every error of the auth package after its class, although none extends a class of this package', function (string $exception, string $code) {
    expect(is_subclass_of($exception, ProblemException::class))->toBeFalse()
        ->and(ProblemDetails::isNamedError($exception))->toBeTrue()
        ->and(ProblemDetails::code($exception, (new $exception)->getStatusCode()))->toBe($code);
})->with([
    [TwoFactorSetupRequiredException::class, 'two_factor_setup_required'],
    [TwoFactorRequiredException::class, 'two_factor_required'],
    [CurrentSessionException::class, 'current_session'],
    [LoginCodeUnavailableException::class, 'login_code_unavailable'],
    [PasswordUnavailableException::class, 'password_unavailable'],
]);

it('answers a refusal of the two-factor enforcement as a problem with its own code and message', function () {
    $response = $this->withToken($this->user()->createToken('test')->plainTextToken)->getJson('/api/user/sessions');

    $this->assertProblem($response, 403, 'two_factor_setup_required')
        ->assertJsonPath('detail', 'Set up two-factor authentication to continue.')
        ->assertJsonPath('instance', '/api/user/sessions');
});

it('answers the missing password confirmation of a Fortify route as a problem', function () {
    $response = $this->withToken($this->user()->createToken('test')->plainTextToken)->postJson('/api/user/two-factor-authentication');

    $this->assertProblem($response, 423, 'password_confirmation_required');
});

it('answers wrong credentials as a validation problem on the e-mail address', function () {
    $this->user();

    $this->assertValidationProblem($this->postJson('/api/auth/login', ['email' => 'jan@example.com', 'password' => 'wrong']), 'email');
});

it('answers a guest as unauthorized', function () {
    $this->assertProblem($this->getJson('/api/user/sessions'), 401, 'unauthorized')->assertJsonMissingPath('detail');
});
