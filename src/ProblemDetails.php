<?php

declare(strict_types=1);

namespace Igne\ProblemDetails;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * What an exception and its status say about a problem, shared by the response ({@see Problem})
 * and the API document: every status tells the `type`, the `title` and the `code`, and an error the
 * application describes itself, a {@see ProblemException}, also tells its `code` by its class name
 * (`FileNotFoundException` is `file_not_found`).
 */
final class ProblemDetails
{
    public const string CONTENT_TYPE = 'application/problem+json';

    /**
     * Laravel's status of an expired CSRF token, which no RFC registers.
     */
    public const int HTTP_PAGE_EXPIRED = 419;

    /**
     * The statuses of RFC 6585 and the section that defines each.
     *
     * @var array<int, int>
     */
    private const array RFC_6585_SECTIONS = [428 => 3, 429 => 4, 431 => 5, 511 => 6];

    /**
     * An error the application describes itself: a {@see ProblemException}. Every other exception
     * says nothing beyond its status, and its message is the framework's or not meant to be shown.
     *
     * @param  class-string  $exceptionClass
     */
    public static function isApplicationError(string $exceptionClass): bool
    {
        return is_subclass_of($exceptionClass, ProblemException::class);
    }

    /**
     * The key a client switches on: the class name of an application error in snake_case without
     * its `Exception` suffix, or else the reason phrase of the status (`not_found`).
     *
     * @param  class-string  $exceptionClass
     */
    public static function code(string $exceptionClass, int $status): string
    {
        return self::isApplicationError($exceptionClass)
            ? Str::snake(Str::beforeLast(class_basename($exceptionClass), 'Exception'))
            : self::statusCode($status);
    }

    /**
     * The `code` of a framework error: the reason phrase of the status in snake_case (`not_found`).
     */
    public static function statusCode(int $status): string
    {
        return Str::slug(self::title($status), '_');
    }

    /**
     * The reason phrase of the status, in English (`Not Found`). An unregistered status is
     * described by the first status of its class, as RFC 9110 prescribes for an unknown one.
     */
    public static function title(int $status): string
    {
        return $status === self::HTTP_PAGE_EXPIRED
            ? 'Page Expired'
            : Response::$statusTexts[$status] ?? Response::$statusTexts[intdiv($status, 100) * 100];
    }

    /**
     * The URI of the RFC section that defines the status, or `about:blank`, RFC 9457's own type
     * for a problem the status says everything about.
     */
    public static function type(int $status): string
    {
        $definedByRfc9110 = ($status >= 400 && $status <= 417) || in_array($status, [421, 422, 426], true) || ($status >= 500 && $status <= 505);

        return match (true) {
            isset(self::RFC_6585_SECTIONS[$status]) => 'https://www.rfc-editor.org/rfc/rfc6585#section-'.self::RFC_6585_SECTIONS[$status],
            $definedByRfc9110 => "https://www.rfc-editor.org/rfc/rfc9110#name-{$status}-".Str::slug(self::title($status)),
            default => 'about:blank',
        };
    }
}
