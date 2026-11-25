<?php

declare(strict_types=1);

namespace Igne\ErrorContract\Tests\Concerns;

use Igne\ErrorContract\ProblemDetails;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assertions for a test case: a Problem Details error response by its status and `code`, the two
 * members a client switches on (never the `detail`: messages change).
 */
trait InteractsWithProblemDetails
{
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
}
