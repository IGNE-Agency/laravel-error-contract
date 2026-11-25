<?php

declare(strict_types=1);

namespace Igne\ProblemDetails;

use Closure;
use Illuminate\Auth\Middleware\RequirePassword as LaravelRequirePassword;

/**
 * Laravel's `password.confirm`, except that a JSON request without a recent confirmation gets an
 * exception instead of a response. It is Laravel's one error answer that is not an exception: the
 * middleware builds a 423 `{ message }` itself, which no exception handler ever sees.
 *
 * Thrown, it reaches the handler like every other error. Outside the configured paths Laravel
 * renders it as the same status and message as before, so nothing changes there.
 */
final class RequirePassword extends LaravelRequirePassword
{
    /**
     * @throws PasswordConfirmationRequiredException
     */
    public function handle($request, Closure $next, $redirectToRoute = null, $passwordTimeoutSeconds = null)
    {
        $timeout = $passwordTimeoutSeconds === null ? null : (int) $passwordTimeoutSeconds;

        if ($request->expectsJson() && $this->shouldConfirmPassword($request, $timeout)) {
            throw new PasswordConfirmationRequiredException;
        }

        return parent::handle($request, $next, $redirectToRoute, $passwordTimeoutSeconds);
    }
}
