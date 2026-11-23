<?php

declare(strict_types=1);

namespace Igne\ProblemDetails\OpenApi;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use Dedoc\Scramble\Support\Generator\Types\Type;

/**
 * A `oneOf` schema: exactly one of its items matches, which generators turn into a discriminated
 * union. Scramble ships `allOf` and `anyOf` only.
 */
final class OneOf extends Type
{
    /**
     * @param  list<Type>  $items
     */
    public function __construct(public array $items)
    {
        parent::__construct('oneOf');
    }

    public function serializeAs31(): mixed
    {
        return $this->serialize(parent::serializeAs31(), static fn (OpenApiSerializable $item): mixed => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(parent::serializeAs32(), static fn (OpenApiSerializable $item): mixed => $item->serializeAs32());
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  callable(OpenApiSerializable): mixed  $serializeItem
     * @return array<string, mixed>
     */
    private function serialize(array $schema, callable $serializeItem): array
    {
        unset($schema['type']);

        return [...$schema, 'oneOf' => array_map($serializeItem, $this->items)];
    }
}
