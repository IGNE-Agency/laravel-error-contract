<?php

declare(strict_types=1);

namespace Igne\ProblemDetails\Tests\Fixtures;

use Igne\ProblemDetails\ProblemException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Drafts cannot be stored; publish the article first.
 */
final class DraftsNotAllowedException extends ProblemException
{
    public function __construct()
    {
        parent::__construct(Response::HTTP_FORBIDDEN, 'Drafts are not allowed.');
    }
}
