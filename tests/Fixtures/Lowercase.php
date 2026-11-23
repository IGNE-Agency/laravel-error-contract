<?php

declare(strict_types=1);

namespace Igne\ProblemDetails\Tests\Fixtures;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class Lowercase implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value !== mb_strtolower($value)) {
            $fail('The :attribute must be lowercase.');
        }
    }
}
