<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    |
    | The request paths whose errors are answered as RFC 9457 Problem Details
    | (application/problem+json), as patterns of Request::is(). An error on
    | any other path is left to Laravel.
    |
    */

    'paths' => ['api/*'],

    /*
    |--------------------------------------------------------------------------
    | Named errors
    |--------------------------------------------------------------------------
    |
    | An HTTP exception declared in one of these namespaces is a named error:
    | its class name is the "code" (TwoFactorRequiredException is
    | "two_factor_required") and its message is the "detail". The application
    | and the IGNE packages follow that rule. Any other HTTP exception, the
    | framework's and a third party's, gets the code of its status and no
    | detail.
    |
    | "codes" names a single class from outside those namespaces, or gives a
    | class another code than its name:
    |
    |     Spatie\Permission\Exceptions\UnauthorizedException::class => 'role_missing',
    |
    */

    'namespaces' => ['App\\', 'Igne\\'],

    'codes' => [],

];
