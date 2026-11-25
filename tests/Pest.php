<?php

declare(strict_types=1);

use Igne\ErrorContract\Tests\Packages\LaravelAuthTestCase;
use Igne\ErrorContract\Tests\TestCase;

uses(TestCase::class)->in('Rendering');

// Against a real package, when it is installed: it needs Laravel 13.
uses(LaravelAuthTestCase::class)->in('Packages');
