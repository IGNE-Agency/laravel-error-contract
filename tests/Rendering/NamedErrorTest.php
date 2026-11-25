<?php

declare(strict_types=1);

use Acme\Billing\CardDeclinedException;
use Igne\ProblemDetails\ProblemException;
use Igne\ProblemDetails\Tests\Fixtures\DeviceNotTrustedException;
use Illuminate\Support\Facades\Route;

require_once __DIR__.'/../Fixtures/ThirdParty.php';

it('names an HTTP exception of a listed namespace by its class, although it extends nothing of this package', function () {
    Route::get('api/fixtures/device', fn () => throw new DeviceNotTrustedException);

    expect(is_subclass_of(DeviceNotTrustedException::class, ProblemException::class))->toBeFalse();

    $this->assertProblem($this->getJson('/api/fixtures/device'), 403, 'device_not_trusted')
        ->assertJsonPath('detail', 'Confirm it is you on this device.');
});

it('gives the HTTP exception of a third party the code of its status and does not show its message', function () {
    Route::get('api/fixtures/charge', fn () => throw new CardDeclinedException);

    $this->assertProblem($this->getJson('/api/fixtures/charge'), 402, 'payment_required')
        ->assertJsonMissingPath('detail');
});

it('names the exception of a third party the configuration gives a code', function () {
    config(['problem-details.codes' => [CardDeclinedException::class => 'card_declined']]);
    Route::get('api/fixtures/charge', fn () => throw new CardDeclinedException);

    $this->assertProblem($this->getJson('/api/fixtures/charge'), 402, 'card_declined')
        ->assertJsonPath('detail', 'Issuer 4411 declined the card.');
});

it('names the exceptions of a namespace the application adds', function () {
    config(['problem-details.namespaces' => ['App\\', 'Acme\\']]);
    Route::get('api/fixtures/charge', fn () => throw new CardDeclinedException);

    $this->assertProblem($this->getJson('/api/fixtures/charge'), 402, 'card_declined');
});

it('leaves an exception without a listed namespace unnamed when the list is emptied', function () {
    config(['problem-details.namespaces' => []]);
    Route::get('api/fixtures/device', fn () => throw new DeviceNotTrustedException);

    $this->assertProblem($this->getJson('/api/fixtures/device'), 403, 'forbidden')->assertJsonMissingPath('detail');
});

it('answers a missing password confirmation as a problem, although Laravel answers it without an exception', function () {
    Route::get('api/fixtures/settings', fn () => response()->noContent())->middleware(['web', 'password.confirm']);

    $this->assertProblem($this->getJson('/api/fixtures/settings'), 423, 'password_confirmation_required')
        ->assertJsonPath('detail', 'Password confirmation required.');
});

it('lets a confirmed password through', function () {
    Route::get('api/fixtures/settings', fn () => response()->noContent())->middleware(['web', 'password.confirm']);

    $this->withSession(['auth.password_confirmed_at' => time()])->getJson('/api/fixtures/settings')->assertNoContent();
});

it('answers a missing password confirmation outside the configured paths as Laravel always did', function () {
    Route::get('fixtures/settings', fn () => response()->noContent())->middleware(['web', 'password.confirm']);

    $this->getJson('/fixtures/settings')
        ->assertStatus(423)
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson(['message' => 'Password confirmation required.']);
});
