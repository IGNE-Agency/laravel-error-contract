<?php

declare(strict_types=1);

use Dedoc\Scramble\Scramble;
use Igne\ProblemDetails\ValidationRules;

if (! class_exists(Scramble::class)) {
    return;
}

beforeEach(function () {
    $this->document = $this->getJson('/docs/api.json')->assertOk()->json();
    $this->schemas = $this->document['components']['schemas'];
});

it('documents the codes an operation answers per status, from the exceptions of its action', function () {
    $store = $this->document['paths']['/fixtures/articles']['post']['responses'];
    $show = $this->document['paths']['/fixtures/articles/{article}']['get']['responses'];
    $purchase = $this->document['paths']['/fixtures/articles/{article}/purchase']['post']['responses'];

    expect($this->openApiErrorCodes($store, '403'))->toBe(['drafts_not_allowed'])
        ->and($this->openApiErrorCodes($store, '422'))->toBe(['unprocessable_content'])
        ->and($this->openApiErrorCodes($show, '404'))->toBe(['article_not_found'])
        ->and($this->openApiErrorCodes($purchase, '402'))->toBe(['payment_required'])
        ->and($show)->not->toHaveKey('422');
});

it('answers every error as application/problem+json and leaves the other responses as they are', function () {
    $store = $this->document['paths']['/fixtures/articles']['post']['responses'];

    expect(array_keys($store['403']['content']))->toBe(['application/problem+json'])
        ->and(array_keys($store['422']['content']))->toBe(['application/problem+json'])
        ->and($store)->toHaveKey('204');
});

it('references the one problem a status can answer by name', function () {
    $schema = $this->document['paths']['/fixtures/articles/{article}']['get']['responses']['404']['content']['application/problem+json']['schema'];

    expect($schema)->toBe(['$ref' => '#/components/schemas/ArticleNotFoundProblem'])
        ->and($this->schemas['ArticleNotFoundProblem']['allOf'])->toBe([
            ['$ref' => '#/components/schemas/ProblemDetails'],
            ['type' => 'object', 'properties' => ['code' => ['type' => 'string', 'const' => 'article_not_found']]],
        ]);
});

it('answers several problems of a status as one of them, told apart by code', function () {
    $schema = $this->document['paths']['/fixtures/problems']['get']['responses']['404']['content']['application/problem+json']['schema'];

    expect($schema['oneOf'])->toBe([
        ['$ref' => '#/components/schemas/NotFoundProblem'],
        ['$ref' => '#/components/schemas/ArticleNotFoundProblem'],
    ])
        ->and($schema['discriminator'])->toBe([
            'propertyName' => 'code',
            'mapping' => [
                'not_found' => '#/components/schemas/NotFoundProblem',
                'article_not_found' => '#/components/schemas/ArticleNotFoundProblem',
            ],
        ]);
});

it('describes a problem with the docblock of its exception, or else with what its status means', function () {
    expect($this->schemas['DraftsNotAllowedProblem']['description'])->toBe('Drafts cannot be stored; publish the article first.')
        ->and($this->schemas['TooManyRequestsProblem']['description'])->toContain('Retry-After');
});

it('documents the header that tells how long to wait after too many requests', function () {
    $response = $this->document['paths']['/fixtures/articles/export']['post']['responses']['429'];

    expect($response['headers']['Retry-After']['schema'])->toBe(['type' => 'integer']);
});

it('names the validation problem of a form request after the request and of an inline validation after the operation', function () {
    $store = $this->document['paths']['/fixtures/articles']['post']['responses']['422']['content']['application/problem+json']['schema'];
    $index = $this->document['paths']['/fixtures/articles']['get']['responses']['422']['content']['application/problem+json']['schema'];

    expect($store)->toBe(['$ref' => '#/components/schemas/StoreArticleValidationProblem'])
        ->and($index)->toBe(['$ref' => '#/components/schemas/FixturesArticlesIndexValidationProblem'])
        ->and($this->schemas['StoreArticleValidationProblem']['allOf'][0])->toBe(['$ref' => '#/components/schemas/ValidationProblem']);
});

it('documents the rules each input of a form request can fail and the properties they carry', function () {
    $responses = $this->document['paths']['/fixtures/articles']['post']['responses'];
    $inputs = $this->schemas['StoreArticleValidationProblem']['allOf'][1]['properties']['errors']['properties'];

    expect($this->openApiValidationRuleCodes($responses, 'title'))->toBe(['string', 'required_without_all', 'max', 'invalid'])
        ->and($this->openApiValidationRuleCodes($responses, 'slug'))->toBe(['lowercase', 'invalid'])
        ->and($this->openApiValidationRuleCodes($responses, 'summary'))->toBe(['string', 'regex', 'invalid'])
        ->and($this->openApiValidationRuleCodes($responses, 'rating'))->toBe(['integer', 'between', 'invalid'])
        ->and($this->openApiValidationRuleCodes($responses, 'password'))->toBe(['password', 'invalid'])
        ->and($inputs)->not->toHaveKey('tags.*')
        ->and($inputs['title']['items']['properties']['properties']['properties'])->toBe([
            'values' => ['type' => 'array', 'items' => ['type' => 'string']],
            'max' => ['type' => 'integer'],
        ])
        ->and($inputs['rating']['items']['properties']['properties']['properties'])->toBe([
            'min' => ['type' => 'integer'],
            'max' => ['type' => 'integer'],
        ])
        ->and($inputs['summary']['items']['properties'])->not->toHaveKey('properties');
});

it('documents the rules of an inline validation', function () {
    $responses = $this->document['paths']['/fixtures/articles']['get']['responses'];

    expect($this->openApiValidationRuleCodes($responses, 'per_page'))->toBe(['integer', 'between', 'invalid']);
});

it('answers an operation without rules with the general validation problem', function () {
    $schema = $this->document['paths']['/fixtures/articles/{article}/publish']['post']['responses']['422']['content']['application/problem+json']['schema'];

    expect($schema)->toBe(['$ref' => '#/components/schemas/ValidationProblem'])
        ->and($this->schemas['ValidationProblem']['allOf'][1]['properties']['errors']['additionalProperties'])
        ->toBe(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ValidationError']]);
});

it('lists every problem the API can answer, including the ones any request can get', function () {
    $codes = $this->schemas['ProblemCode']['enum'];

    expect($codes)->toContain('article_not_found', 'drafts_not_allowed', 'payment_required', 'unprocessable_content', 'too_many_requests')
        ->and($codes)->toContain('not_found', 'method_not_allowed', 'content_too_large', 'page_expired', 'internal_server_error', 'service_unavailable')
        ->and($this->schemas['ProblemDetails']['properties']['code'])->toBe(['$ref' => '#/components/schemas/ProblemCode'])
        ->and($this->schemas['Problem']['discriminator']['propertyName'])->toBe('code')
        ->and(array_keys($this->schemas['Problem']['discriminator']['mapping']))->toBe($codes)
        ->and($this->schemas['Problem']['discriminator']['mapping']['unprocessable_content'])->toBe('#/components/schemas/ValidationProblem')
        ->and($this->document['components'])->not->toHaveKey('responses');
});

it('shares the validation error and the rule codes of Laravel', function () {
    expect($this->schemas['ValidationError']['required'])->toBe(['validation_rule_code', 'message'])
        ->and($this->schemas['ValidationError']['properties']['validation_rule_code'])->toBe(['$ref' => '#/components/schemas/ValidationRuleCode'])
        ->and($this->schemas['ValidationRuleCode']['enum'])->toBe(ValidationRules::all());
});
