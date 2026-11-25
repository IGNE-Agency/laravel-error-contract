<?php

declare(strict_types=1);

namespace Igne\ErrorContract\Tests\Fixtures;

use Igne\ErrorContract\ProblemException;
use Symfony\Component\HttpFoundation\Response;

final class PaymentRequiredException extends ProblemException
{
    public function __construct()
    {
        parent::__construct(Response::HTTP_PAYMENT_REQUIRED, 'The article must be paid for.');
    }
}
