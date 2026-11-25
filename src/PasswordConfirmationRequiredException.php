<?php

declare(strict_types=1);

namespace Igne\ErrorContract;

use Symfony\Component\HttpKernel\Exception\LockedHttpException;

/**
 * The action needs a recent password confirmation. Confirm the password, then send the request again.
 */
final class PasswordConfirmationRequiredException extends LockedHttpException
{
    public function __construct()
    {
        // The message Laravel's own `password.confirm` answers, untranslated.
        parent::__construct('Password confirmation required.');
    }
}
