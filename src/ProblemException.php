<?php

declare(strict_types=1);

namespace Igne\ErrorContract;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * An optional base class for an error of the application: extend it once per error and pass the
 * status to the constructor.
 *
 * Nothing has to extend it. Any HTTP exception declared in a configured namespace is a named error
 * ({@see ProblemDetails::isNamedError()}): its class name is the `code` a client switches on
 * (`FileNotFoundException` is `file_not_found`), its message is the `detail`, shown to the user, so
 * it must be safe to show. A package that throws Symfony's HTTP exceptions therefore needs no
 * dependency on this one.
 */
abstract class ProblemException extends HttpException {}
