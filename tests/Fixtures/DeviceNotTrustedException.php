<?php

declare(strict_types=1);

namespace Igne\ProblemDetails\Tests\Fixtures;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * An error as a package throws it: a plain Symfony HTTP exception, named after the error, that
 * extends nothing of this package and does not render itself.
 */
final class DeviceNotTrustedException extends AccessDeniedHttpException
{
    public function __construct()
    {
        parent::__construct('Confirm it is you on this device.');
    }
}
