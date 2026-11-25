<?php

declare(strict_types=1);

namespace Igne\ErrorContract;

use Closure;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Validation\ClosureValidationRule;
use Illuminate\Validation\ValidationRuleParser;

/**
 * Laravel's `validation` translation group read as the list of validation rules: its keys are the
 * `validation_rule_code` values (`required`, `max`, `required_without_all`) and the
 * `:placeholders` of each line name the parameters of the rule. Every Laravel application has the
 * group: the framework ships it and an application's `lang/en/validation.php` only overrides it.
 *
 * The response and the API document both read the rules through this class, so they cannot disagree.
 */
final class ValidationRules
{
    /**
     * The code of a message without a rule (`ValidationException::withMessages()`, an `after()` hook).
     */
    public const string INVALID = 'invalid';

    private const string LOCALE = 'en';

    /**
     * The code of a rule, in snake_case: the name of a string rule (`max`, `Max`, `int`) or the
     * class name of a rule object (`password`, `enum`, a custom rule).
     *
     * @param  string|class-string  $rule
     */
    public static function code(string $rule): string
    {
        return Str::snake(class_basename(match (true) {
            $rule === Closure::class => ClosureValidationRule::class,
            str_contains($rule, '\\') => $rule,
            default => ValidationRuleParser::parse($rule)[0],
        }));
    }

    /**
     * Every code of Laravel's own rules, and the code of a message without a rule.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        $lines = Lang::get('validation', [], self::LOCALE);

        return [...array_keys(array_diff_key($lines, ['custom' => null, 'attributes' => null])), self::INVALID];
    }

    /**
     * The parameters of a rule, named after the `:placeholders` of its line, so a client can
     * build its own text: `max:255` on "must not be greater than :max characters" gives
     * `{ "max": 255 }`. The parameters fill the placeholders in the order both appear; `values`,
     * and a last placeholder with several parameters left, take all of them as a list
     * (`required_without_all:a,b` gives `{ "values": ["a", "b"] }`). A rule whose line has no
     * placeholder (`regex`, `in`, `exists`, every rule object) has no properties.
     *
     * @param  list<mixed>  $parameters  as declared: `['1', '5']` for `between:1,5`
     * @return array<string, int|float|string|list<string>>
     */
    public static function properties(string $code, array $parameters): array
    {
        preg_match_all('/:([a-z_]+)/', implode(' ', (array) Lang::get('validation.'.$code, [], self::LOCALE)), $matches);
        $placeholders = array_values(array_diff(array_unique($matches[1]), ['attribute']));
        $properties = [];

        foreach ($placeholders as $position => $placeholder) {
            $remaining = array_slice($parameters, $position);

            if ($remaining === []) {
                break;
            }

            $takesTheRest = $placeholder === 'values' || ($position === array_key_last($placeholders) && count($remaining) > 1);

            $properties[$placeholder] = match (true) {
                $takesTheRest => array_map(strval(...), $remaining),
                is_numeric($remaining[0]) => $remaining[0] + 0,
                default => (string) $remaining[0],
            };
        }

        return $properties;
    }
}
