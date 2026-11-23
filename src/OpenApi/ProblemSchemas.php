<?php

declare(strict_types=1);

namespace Igne\ProblemDetails\OpenApi;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Combined\AllOf;
use Dedoc\Scramble\Support\Generator\Discriminator;
use Dedoc\Scramble\Support\Generator\OpenApi as Document;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types as OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Igne\ProblemDetails\ProblemDetails;
use Igne\ProblemDetails\ValidationRules;
use Illuminate\Support\Str;
use LogicException;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response;

/**
 * The named schemas of the problems, so a client generates a type for each:
 *
 * - `ProblemDetails`, the base every problem extends, whose `code` is the `ProblemCode` enum;
 * - one `<Code>Problem` per code, fixing its `code` (`UnauthorizedProblem`, `FileNotFoundProblem`);
 * - `ValidationProblem`, with the `errors` per input, and one `<Request>ValidationProblem` per
 *   request that names its inputs and the rules each can fail (`LoginValidationProblem`);
 * - once the document is complete, `ProblemCode` and `Problem`, the closed set of every problem
 *   the API can answer, as a `oneOf` discriminated by `code`.
 */
final readonly class ProblemSchemas implements DocumentTransformer
{
    /**
     * The statuses Laravel can answer any request with, whatever its endpoint documents.
     *
     * @var list<int>
     */
    private const array ANY_REQUEST = [404, 405, 413, ProblemDetails::HTTP_PAGE_EXPIRED, 500, 503];

    /**
     * When a framework problem happens and what a client does about it.
     *
     * @var array<int, string>
     */
    private const array FRAMEWORK_DESCRIPTIONS = [
        400 => 'The request is malformed. Fix the request before sending it again.',
        401 => 'The request has no valid session. Log in and send it again.',
        403 => 'The user may not do this. Do not retry; hide or disable the action.',
        404 => 'The resource or the path does not exist, or the user may not see it.',
        405 => 'The path does not accept this HTTP method.',
        413 => 'The request body is larger than the server accepts.',
        ProblemDetails::HTTP_PAGE_EXPIRED => 'The CSRF token of the session expired. Fetch a new CSRF cookie and send the request again.',
        422 => 'One or more inputs are invalid. Show the errors per input and let the user correct them.',
        429 => 'Too many requests. Wait the number of seconds of the `Retry-After` header before sending it again.',
        500 => 'An unexpected error on the server. Retry later; quote the `traceId` when reporting it.',
        503 => 'The API is unavailable, for maintenance for example. Retry later.',
    ];

    public function __construct(private TypeTransformer $openApiTransformer) {}

    /**
     * Writes the closed set once every operation is documented: the problems the endpoints
     * answer and the ones any request can get.
     */
    public function handle(Document $document, OpenApiContext $context): void
    {
        foreach (self::ANY_REQUEST as $status) {
            $this->problem(ProblemDetails::statusCode($status), $status, null);
        }
        $this->validationProblem();

        $components = $this->openApiTransformer->getComponents();
        $problems = [];
        foreach (array_keys($components->schemas) as $name) {
            $code = $this->codeOf($name);

            if ($code !== null) {
                $problems[$code] = new Reference('schemas', $name, $components);
            }
        }
        ksort($problems);

        $components->addSchema('ProblemCode', Schema::fromType((new OpenApi\StringType)
            ->setDescription('The `code` of every problem the API can answer, which a client switches on.')
            ->enum(array_keys($problems))));
        $components->addSchema('Problem', Schema::fromType($this->oneOf($problems)
            ->setDescription('Every problem the API can answer, told apart by `code`.')));
    }

    /**
     * The body of an error response: the problem itself, or one of several told apart by `code`.
     *
     * @param  non-empty-array<string, Reference>  $problemsByCode
     */
    public function response(array $problemsByCode): OpenApi\Type
    {
        return count($problemsByCode) === 1 ? reset($problemsByCode) : $this->oneOf($problemsByCode);
    }

    /**
     * The problem of a code, described by the docblock of the application error that answers it,
     * or else by what the status means.
     *
     * @param  class-string|null  $applicationError
     */
    public function problem(string $code, int $status, ?string $applicationError): Reference
    {
        return $this->shared(Str::studly($code).'Problem', fn (): OpenApi\Type => (new AllOf)
            ->setItems([$this->problemDetails(), $this->withCode($code)])
            ->setDescription($this->summaryOf($applicationError) ?? self::FRAMEWORK_DESCRIPTIONS[$status] ?? ProblemDetails::title($status).'.'));
    }

    public function validationProblem(): Reference
    {
        return $this->shared('ValidationProblem', fn (): OpenApi\Type => (new AllOf)
            ->setItems([
                $this->problemDetails(),
                $this->withCode(ProblemDetails::statusCode(Response::HTTP_UNPROCESSABLE_ENTITY))
                    ->addProperty('errors', (new OpenApi\ObjectType)
                        ->setDescription('The invalid inputs, each with one error per failed rule.')
                        ->additionalProperties((new OpenApi\ArrayType)->setItems($this->validationError())))
                    ->setRequired(['errors']),
            ])
            ->setDescription(self::FRAMEWORK_DESCRIPTIONS[Response::HTTP_UNPROCESSABLE_ENTITY]));
    }

    /**
     * The validation problem of one request: its inputs and the rules each can fail, with the
     * properties they carry. An input the request cannot name (an item of a list) keeps the
     * unnarrowed errors of {@see validationProblem()}.
     *
     * @param  array<string, array<string, list<mixed>>>  $failuresByInput  input => validation rule code => the parameters of the rule
     */
    public function requestValidationProblem(string $requestName, array $failuresByInput): Reference
    {
        $namedInputs = array_filter($failuresByInput, static fn (string $input): bool => ! str_contains($input, '*'), ARRAY_FILTER_USE_KEY);

        if ($namedInputs === []) {
            return $this->validationProblem();
        }

        return $this->shared($requestName.'ValidationProblem', function () use ($requestName, $namedInputs): OpenApi\Type {
            $errors = new OpenApi\ObjectType;

            foreach ($namedInputs as $input => $failures) {
                $errors->addProperty($input, (new OpenApi\ArrayType)->setItems($this->inputError($failures)));
            }

            return (new AllOf)
                ->setItems([$this->validationProblem(), (new OpenApi\ObjectType)->addProperty('errors', $errors)])
                ->setDescription("A validation error of {$requestName}: its inputs and the rules each can fail.");
        });
    }

    /**
     * @param  array<string, list<mixed>>  $failures  validation rule code => the parameters of the rule
     */
    private function inputError(array $failures): OpenApi\ObjectType
    {
        $error = (new OpenApi\ObjectType)
            ->addProperty('validation_rule_code', (new OpenApi\StringType)->enum([...array_keys($failures), ValidationRules::INVALID]))
            ->addProperty('message', new OpenApi\StringType)
            ->setRequired(['validation_rule_code', 'message']);

        $properties = new OpenApi\ObjectType;
        foreach ($failures as $code => $parameters) {
            foreach (ValidationRules::properties($code, $parameters) as $name => $value) {
                $properties->addProperty($name, match (true) {
                    is_int($value) => new OpenApi\IntegerType,
                    is_float($value) => new OpenApi\NumberType,
                    is_string($value) => new OpenApi\StringType,
                    default => (new OpenApi\ArrayType)->setItems(new OpenApi\StringType),
                });
            }
        }

        return $properties->properties === [] ? $error : $error->addProperty('properties', $properties);
    }

    /**
     * @param  non-empty-array<string, Reference>  $problemsByCode
     */
    private function oneOf(array $problemsByCode): OneOf
    {
        $discriminator = new Discriminator('code');
        foreach ($problemsByCode as $code => $problem) {
            $discriminator->addMapping($code, '#/components/schemas/'.$problem->fullName);
        }

        $oneOf = new OneOf(array_values($problemsByCode));
        $oneOf->setDiscriminator($discriminator);

        return $oneOf;
    }

    private function withCode(string $code): OpenApi\ObjectType
    {
        return (new OpenApi\ObjectType)->addProperty('code', (new OpenApi\StringType)->const($code));
    }

    /**
     * The code a problem schema fixes; null for any other schema, a request's validation problem included.
     */
    private function codeOf(string $schemaName): ?string
    {
        $schema = $this->openApiTransformer->getComponents()->schemas[$schemaName]->type ?? null;

        if (! $schema instanceof AllOf || ! ($schema->items[0] ?? null) instanceof Reference || $schema->items[0]->fullName !== 'ProblemDetails') {
            return null;
        }

        $code = ($schema->items[1] ?? null) instanceof OpenApi\ObjectType ? ($schema->items[1]->properties['code'] ?? null)?->const : null;

        return is_string($code) ? $code : null;
    }

    /**
     * The first paragraph of the class docblock.
     *
     * @param  class-string|null  $class
     */
    private function summaryOf(?string $class): ?string
    {
        $docComment = $class === null ? false : (new ReflectionClass($class))->getDocComment();

        if ($docComment === false) {
            return null;
        }

        $summary = [];
        foreach (explode("\n", $docComment) as $line) {
            $line = trim(ltrim(trim($line), '/*'));

            if (str_starts_with($line, '@') || ($line === '' && $summary !== [])) {
                break;
            }

            if ($line !== '') {
                $summary[] = $line;
            }
        }

        return $summary === [] ? null : implode(' ', $summary);
    }

    private function problemDetails(): Reference
    {
        return $this->shared('ProblemDetails', fn (): OpenApi\Type => (new OpenApi\ObjectType)
            ->setDescription('RFC 9457 Problem Details: the members every problem has.')
            ->addProperty('type', (new OpenApi\StringType)
                ->format('uri')
                ->setDescription('Identifies the problem type: the RFC section of the status, or `about:blank` when the status says everything.')
                ->examples([ProblemDetails::type(Response::HTTP_NOT_FOUND)]))
            ->addProperty('title', (new OpenApi\StringType)
                ->setDescription('Short summary of the problem type: the reason phrase of the status, in English.')
                ->examples([ProblemDetails::title(Response::HTTP_NOT_FOUND)]))
            ->addProperty('status', (new OpenApi\IntegerType)
                ->setDescription('The HTTP status of the response.')
                ->examples([Response::HTTP_NOT_FOUND]))
            ->addProperty('detail', (new OpenApi\StringType)
                ->setDescription('User-safe explanation of this occurrence, in the language of the application. Omitted when the status says everything.'))
            ->addProperty('instance', (new OpenApi\StringType)
                ->setDescription('The path of the request.'))
            ->addProperty('traceId', (new OpenApi\StringType)
                ->format('uuid')
                ->setDescription('Identifies the request in the server logs; quote it when reporting the error.'))
            ->addProperty('code', new Reference('schemas', 'ProblemCode', $this->openApiTransformer->getComponents()))
            ->setRequired(['type', 'title', 'status', 'instance', 'traceId', 'code']));
    }

    private function validationError(): Reference
    {
        return $this->shared('ValidationError', fn (): OpenApi\Type => (new OpenApi\ObjectType)
            ->setDescription('One failed validation rule on one input.')
            ->addProperty('validation_rule_code', $this->shared('ValidationRuleCode', static fn (): OpenApi\Type => (new OpenApi\StringType)
                ->setDescription('The validation rule that failed, in snake_case: a rule of Laravel, the class name of a rule object, or `invalid` for a message without a rule. A request narrows it to the rules of each of its inputs.')
                ->enum(ValidationRules::all())))
            ->addProperty('message', (new OpenApi\StringType)
                ->setDescription('The message of the rule, in the language of the application.'))
            ->addProperty('properties', (new OpenApi\ObjectType)
                ->setDescription('The parameters of the rule, named as in its message (`{ "max": 255 }`, `{ "min": 1, "max": 5 }`, `{ "values": ["a", "b"] }`). Omitted for a rule without any.'))
            ->setRequired(['validation_rule_code', 'message']));
    }

    /**
     * The reference to a schema under `components/schemas`, built on first use.
     *
     * @param  callable(): OpenApi\Type  $build
     */
    private function shared(string $name, callable $build): Reference
    {
        $reference = $this->openApiTransformer->getOrCreateSchemaReference(
            new Reference('schemas', $name, $this->openApiTransformer->getComponents()),
            $build,
        );

        return $reference instanceof Reference ? $reference : throw new LogicException("Schema [{$name}] could not be built.");
    }
}
