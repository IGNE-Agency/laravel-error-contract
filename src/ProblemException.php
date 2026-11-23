<?php

declare(strict_types=1);

namespace Igne\ProblemDetails;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * An error the application describes itself, made to be answered as RFC 9457 Problem Details:
 * extend it once per error and pass the status to the constructor.
 *
 * Beyond what any HTTP exception tells (its status, and so the `type` and the `title`), the class
 * tells the rest of the problem:
 *
 * - its name is the `code` a client switches on (`FileNotFoundException` is `file_not_found`);
 * - its message is the `detail`, shown to the user, so it must be safe to show;
 * - the first paragraph of its docblock describes the problem in the API document.
 *
 * Any other exception, Symfony's and Laravel's own HTTP exceptions included, is answered with the
 * `code` of its status and without a `detail`.
 */
abstract class ProblemException extends HttpException {}
