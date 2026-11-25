<?php

declare(strict_types=1);

namespace Acme\Billing;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * An HTTP exception of a third party: its namespace is not listed, so by default it says nothing
 * beyond its status.
 */
final class CardDeclinedException extends HttpException
{
    public function __construct()
    {
        parent::__construct(402, 'Issuer 4411 declined the card.');
    }
}
