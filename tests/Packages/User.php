<?php

declare(strict_types=1);

namespace Igne\ErrorContract\Tests\Packages;

use Igne\Auth\Authentication\AuthenticatesWithPassword;
use Igne\Auth\Authentication\Contracts\PasswordAuthenticatable;
use Igne\Auth\TwoFactor\Contracts\TwoFactorAuthenticatable;
use Igne\Auth\TwoFactor\InteractsWithTwoFactorAuthentication;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * The user of an application that installed igne-agency/laravel-auth as its README says.
 */
final class User extends Authenticatable implements PasswordAuthenticatable, TwoFactorAuthenticatable
{
    use AuthenticatesWithPassword, HasApiTokens, InteractsWithTwoFactorAuthentication, Notifiable;

    protected $table = 'users';

    protected $guarded = [];
}
