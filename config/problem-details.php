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

];
