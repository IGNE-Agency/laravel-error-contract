<?php

declare(strict_types=1);

namespace Igne\ProblemDetails\Tests\Fixtures;

use Igne\ProblemDetails\ProblemException;
use Symfony\Component\HttpFoundation\Response;

final class ArticleNotFoundException extends ProblemException
{
    public function __construct()
    {
        parent::__construct(Response::HTTP_NOT_FOUND, 'The article does not exist.');
    }
}
