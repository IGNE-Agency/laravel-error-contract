<?php

declare(strict_types=1);

namespace Igne\ErrorContract;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * One RFC 9457 Problem Details document, served as `application/problem+json`. `type` and `title`
 * follow from the status; `detail` and `errors` are omitted when absent, as the RFC prescribes.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Problem implements Arrayable, Responsable
{
    /**
     * @param  array<string, list<array<string, mixed>>>  $errors  the invalid inputs of a validation error, keyed by input
     * @param  array<string, string>  $headers  the headers the exception carries (`Retry-After`, `Allow`)
     */
    public function __construct(
        public int $status,
        public string $code,
        public string $instance,
        public ?string $detail = null,
        public ?string $traceId = null,
        public array $errors = [],
        public array $headers = [],
    ) {}

    /**
     * The problem an exception is answered with. The exception handler hands over the prepared
     * exception: a model not found is already a 404 HttpException, a denied Gate a 403 one, an
     * expired CSRF token a 419 one.
     *
     * A `detail` is only set when it is safe to show: the message of a named error
     * ({@see ProblemDetails::isNamedError()}) or of a validation error, or the message of an
     * unexpected error while debugging.
     */
    public static function fromException(Throwable $exception, Request $request): self
    {
        [$status, $detail] = match (true) {
            $exception instanceof ValidationException => [$exception->status, $exception->getMessage()],
            $exception instanceof AuthenticationException => [Response::HTTP_UNAUTHORIZED, null],
            $exception instanceof HttpExceptionInterface => [$exception->getStatusCode(), ProblemDetails::isNamedError($exception::class) ? $exception->getMessage() : null],
            default => [Response::HTTP_INTERNAL_SERVER_ERROR, config('app.debug') ? $exception->getMessage() : null],
        };

        return new self(
            status: $status,
            code: ProblemDetails::code($exception::class, $status),
            instance: $request->getPathInfo(),
            detail: $detail,
            traceId: Context::get(AssignTraceId::CONTEXT_KEY),
            errors: $exception instanceof ValidationException ? self::validationErrors($exception) : [],
            headers: $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'type' => ProblemDetails::type($this->status),
            'title' => ProblemDetails::title($this->status),
            'status' => $this->status,
            'detail' => $this->detail,
            'instance' => $this->instance,
            'traceId' => $this->traceId,
            'code' => $this->code,
            'errors' => $this->errors,
        ], static fn (mixed $member): bool => $member !== null && $member !== []);
    }

    public function toResponse($request): JsonResponse
    {
        return new JsonResponse($this->toArray(), $this->status, [...$this->headers, 'Content-Type' => ProblemDetails::CONTENT_TYPE]);
    }

    /**
     * The invalid inputs, keyed by the input, each with one error per message: the failed rule as
     * `validation_rule_code`, Laravel's `message` and the rule's parameters as `properties`.
     *
     * A string rule adds one message; a rule object may add several (`Password`), which only shows
     * as more messages than rules, so when the input failed exactly one rule object the surplus
     * is its own. Any other message without a rule was added by hand and gets the `invalid` code.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private static function validationErrors(ValidationException $exception): array
    {
        $failedRules = $exception->validator->failed();
        $errors = [];

        foreach ($exception->validator->errors()->toArray() as $input => $messages) {
            $rules = $failedRules[$input] ?? [];
            // The validator keys a failed rule object by its class and a string rule by its name.
            $ruleObjects = array_values(array_filter(array_keys($rules), static fn (string $rule): bool => str_contains($rule, '\\')));
            $surplus = max(0, count($messages) - count($rules));
            $inputErrors = [];

            foreach ($rules as $rule => $parameters) {
                $code = ValidationRules::code($rule);
                $properties = ValidationRules::properties($code, array_values($parameters));

                foreach (array_splice($messages, 0, $ruleObjects === [$rule] ? 1 + $surplus : 1) as $message) {
                    $inputErrors[] = array_filter(['validation_rule_code' => $code, 'message' => $message, 'properties' => $properties]);
                }
            }

            foreach ($messages as $messageWithoutRule) {
                $inputErrors[] = ['validation_rule_code' => ValidationRules::INVALID, 'message' => $messageWithoutRule];
            }

            $errors[$input] = $inputErrors;
        }

        return $errors;
    }
}
