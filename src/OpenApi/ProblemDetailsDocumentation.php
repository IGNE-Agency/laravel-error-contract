<?php

declare(strict_types=1);

namespace Igne\ProblemDetails\OpenApi;

use Dedoc\Scramble\Contracts\AllRulesSchemasTransformer;
use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Support\ExceptionToResponseExtensions\HttpExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Generator\UniqueNameOptions;
use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\RuleType;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;
use Dedoc\Scramble\Support\RuleTransforming\SchemaBag;
use Dedoc\Scramble\Support\Type\ObjectType;
use Igne\ProblemDetails\ProblemDetails;
use Igne\ProblemDetails\ValidationRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ReflectionClass;
use ReflectionNamedType;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Documents the error responses of every operation in the OpenAPI document Scramble generates,
 * as the Problem Details {@see ProblemDetails} really answers: per status, the named problems
 * ({@see ProblemSchemas}) the operation can answer, from the exceptions Scramble knows its action
 * throws, and for a validation error the inputs of its request and the rules each can fail.
 *
 * Scramble itself only sees what the action throws directly. What its middleware throws, and what
 * the methods it calls throw, is added to the action by `igne-agency/laravel-scramble-extensions` when
 * the application installs it; this class documents whatever is there.
 *
 * Scramble documents one operation at a time and uses this class twice for each: first as a rule
 * transformer, which sees every validation rule of the request (from a Form Request or an inline
 * `validate()` alike) and records what it can fail with, then as an operation transformer, which
 * writes the error responses. The recorded rules are handed over in a static property.
 */
final class ProblemDetailsDocumentation implements AllRulesSchemasTransformer, OperationTransformer
{
    /**
     * The rules of the operation being documented: input => validation rule code => the parameters of the rule.
     *
     * @var array<string, array<string, list<mixed>>>
     */
    private static array $failuresByInput = [];

    private readonly ProblemSchemas $schemas;

    public function __construct(
        private readonly Infer $infer,
        private readonly TypeTransformer $openApiTransformer,
    ) {
        $this->schemas = new ProblemSchemas($openApiTransformer);
    }

    public function shouldHandle(NormalizedRule $rule): bool
    {
        return true;
    }

    /**
     * Records the rule; no schema is changed. A string rule that has no line in the validation
     * translations never fails with a message (`nullable`, `bail`, `sometimes`). A rule Scramble
     * could only read from the source is a RuleType around the type of its object.
     */
    public function transformAll(SchemaBag $schemaBag, NormalizedRule $rule, RuleTransformerContext $context): void
    {
        $value = $rule->getRule();

        $code = match (true) {
            is_string($value) => in_array(ValidationRules::code($value), ValidationRules::all(), true) ? ValidationRules::code($value) : null,
            $value instanceof RuleType => $value->type instanceof ObjectType ? ValidationRules::code($value->type->name) : null,
            default => ValidationRules::code($value::class),
        };

        if ($code !== null) {
            self::$failuresByInput[$context->field][$code] = $rule->getParameters();
        }
    }

    /**
     * Replaces the error responses Scramble documented itself (Laravel's default `{ message }`
     * bodies) with one Problem Details response per status.
     */
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        [$failuresByInput, self::$failuresByInput] = [self::$failuresByInput, []];

        $operation->responses = array_values(array_filter(
            $operation->responses ?? [],
            static fn (Response|Reference $response): bool => $response instanceof Response && (int) $response->code < HttpResponse::HTTP_BAD_REQUEST,
        ));

        foreach ($this->exceptionsByStatus($routeInfo) as $status => $exceptionClasses) {
            $problems = [];
            foreach ($this->exceptionByCode($status, $exceptionClasses) as $code => $exceptionClass) {
                $problems[$code] = is_a($exceptionClass, ValidationException::class, true)
                    ? $this->schemas->requestValidationProblem($this->requestName($operation, $routeInfo), $failuresByInput)
                    : $this->schemas->problem($code, $status, ProblemDetails::isApplicationError($exceptionClass) ? $exceptionClass : null);
            }

            $response = Response::make($status)
                ->setDescription(ProblemDetails::title($status))
                ->setContent(ProblemDetails::CONTENT_TYPE, Schema::fromType($this->schemas->response($problems)));

            if ($status === HttpResponse::HTTP_TOO_MANY_REQUESTS) {
                $response->addHeader('Retry-After', new Header('The number of seconds to wait before sending the request again.', schema: Schema::fromType(new IntegerType)));
            }

            $operation->addResponse($response);
        }
    }

    /**
     * The exceptions the operation can throw whose status is known, in the order Scramble lists them.
     *
     * @return array<int, list<class-string>>
     */
    private function exceptionsByStatus(RouteInfo $routeInfo): array
    {
        $byStatus = [];

        foreach ($routeInfo->getActionType()?->exceptions ?? [] as $exception) {
            $status = $exception instanceof ObjectType ? $this->status($exception) : null;

            if ($status !== null) {
                $byStatus[$status][] = $exception->name;
            }
        }

        ksort($byStatus);

        return $byStatus;
    }

    /**
     * One exception per `code` of a status: the code of the status itself first, when a framework
     * exception answers it, then the codes of the application errors.
     *
     * @param  list<class-string>  $exceptionClasses
     * @return array<string, class-string>
     */
    private function exceptionByCode(int $status, array $exceptionClasses): array
    {
        $byCode = [];
        foreach ($exceptionClasses as $exceptionClass) {
            $byCode[ProblemDetails::code($exceptionClass, $status)] ??= $exceptionClass;
        }

        $statusCode = ProblemDetails::statusCode($status);

        return isset($byCode[$statusCode]) ? [$statusCode => $byCode[$statusCode]] + $byCode : $byCode;
    }

    /**
     * The name of the request a validation problem belongs to: its Form Request without the
     * `Request` suffix (`LoginRequest` is `Login`), or else the operation (route `api.news.index` is `NewsIndex`).
     */
    private function requestName(Operation $operation, RouteInfo $routeInfo): string
    {
        foreach ($routeInfo->reflectionAction()?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && is_a($type->getName(), FormRequest::class, true)) {
                return Str::beforeLast(class_basename($type->getName()), 'Request');
            }
        }

        // The operationId itself is only set once every operation is documented; its parts are already known.
        $operationName = $operation->getAttribute('operationId');
        $name = $operationName instanceof UniqueNameOptions
            ? ($operationName->eloquent ?: implode(' ', $operationName->unique))
            : $routeInfo->method.' '.$routeInfo->route->uri;

        return Str::studly((string) preg_replace('/[^A-Za-z0-9]+/', ' ', (string) $name));
    }

    /**
     * The status an exception is answered with, told from its type alone: an application error
     * without constructor arguments is asked, a framework exception gets the status Laravel's
     * exception handler gives it, and any other HttpException the one Scramble reads from it (a
     * known subclass, or the literal code of `abort(409)`). Null when the type does not tell.
     */
    private function status(ObjectType $exception): ?int
    {
        $class = $exception->name;

        return match (true) {
            ProblemDetails::isApplicationError($class) && (new ReflectionClass($class))->getConstructor()?->getNumberOfRequiredParameters() === 0 => (new $class)->getStatusCode(),
            $exception->isInstanceOf(ValidationException::class) => HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
            $exception->isInstanceOf(AuthenticationException::class) => HttpResponse::HTTP_UNAUTHORIZED,
            $exception->isInstanceOf(AuthorizationException::class) => HttpResponse::HTTP_FORBIDDEN,
            $exception->isInstanceOf(RecordsNotFoundException::class),
            $exception->isInstanceOf(NotFoundHttpException::class) => HttpResponse::HTTP_NOT_FOUND,
            $exception->isInstanceOf(TokenMismatchException::class) => ProblemDetails::HTTP_PAGE_EXPIRED,
            $exception->isInstanceOf(HttpExceptionInterface::class) => (new HttpExceptionToResponseExtension($this->infer, $this->openApiTransformer, $this->openApiTransformer->getComponents()))->toResponse($exception)?->code,
            default => null,
        };
    }
}
