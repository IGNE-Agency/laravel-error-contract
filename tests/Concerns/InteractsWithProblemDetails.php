<?php

declare(strict_types=1);

namespace Igne\ProblemDetails\Tests\Concerns;

use Igne\ProblemDetails\ProblemDetails;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assertions for a test case: a Problem Details error response by its status and `code`, the two
 * members a client switches on (never the `detail`: messages change), and what the OpenAPI
 * document says an operation can answer.
 */
trait InteractsWithProblemDetails
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $openApiDocument = null;

    protected function assertProblem(TestResponse $response, int $status, string $code): TestResponse
    {
        return $response->assertStatus($status)
            ->assertHeader('Content-Type', ProblemDetails::CONTENT_TYPE)
            ->assertJsonPath('status', $status)
            ->assertJsonPath('code', $code);
    }

    /**
     * @param  string|list<string>  $inputs  the inputs that must have an error
     */
    protected function assertValidationProblem(TestResponse $response, string|array $inputs): TestResponse
    {
        $this->assertProblem($response, Response::HTTP_UNPROCESSABLE_ENTITY, 'unprocessable_content');

        /** @var array<string, mixed> $errors */
        $errors = $response->json('errors') ?? [];

        foreach ((array) $inputs as $input) {
            Assert::assertArrayHasKey($input, $errors, "Expected a validation error for input [{$input}], got: ".json_encode($errors));
        }

        return $response;
    }

    /**
     * The `code` values an operation documents for a status, from the problems it references.
     *
     * @param  array<string, mixed>  $responses  the `responses` object of the operation in the OpenAPI document
     * @return list<string>
     */
    protected function openApiErrorCodes(array $responses, string $status): array
    {
        $schema = $responses[$status]['content'][ProblemDetails::CONTENT_TYPE]['schema'] ?? [];

        return array_values(array_filter(array_map(
            fn (array $problem): ?string => $this->openApiCodeOf($problem),
            $schema['oneOf'] ?? [$schema],
        )));
    }

    /**
     * The `validation_rule_code` values an operation documents for an input of its request.
     *
     * @param  array<string, mixed>  $responses  the `responses` object of the operation in the OpenAPI document
     * @return list<string>
     */
    protected function openApiValidationRuleCodes(array $responses, string $input): array
    {
        $problem = $this->openApiResolve($responses[(string) Response::HTTP_UNPROCESSABLE_ENTITY]['content'][ProblemDetails::CONTENT_TYPE]['schema'] ?? []);

        return $problem['allOf'][1]['properties']['errors']['properties'][$input]['items']['properties']['validation_rule_code']['enum'] ?? [];
    }

    /**
     * The `code` a problem schema fixes, following the problems it extends.
     *
     * @param  array<string, mixed>  $schema
     */
    private function openApiCodeOf(array $schema): ?string
    {
        $schema = $this->openApiResolve($schema);

        foreach (array_reverse($schema['allOf'] ?? []) as $part) {
            $code = $part['properties']['code']['const'] ?? $this->openApiCodeOf($part);

            if (is_string($code)) {
                return $code;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function openApiResolve(array $schema): array
    {
        if (! isset($schema['$ref'])) {
            return $schema;
        }

        $this->openApiDocument ??= $this->getJson('/docs/api.json')->assertOk()->json();

        return $this->openApiDocument['components']['schemas'][str_replace('#/components/schemas/', '', $schema['$ref'])] ?? [];
    }
}
