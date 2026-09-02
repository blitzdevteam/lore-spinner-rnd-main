<?php

declare(strict_types=1);

namespace App\Ai\Agents\Chaos;

use Prism\Prism\Contracts\Schema;

/**
 * Carries one already-serialized ChaosNarrationSchema property into a Prism
 * Tool parameter.
 *
 * Prism builds a tool's `input_schema` from `Tool::parametersAsArray()`, which
 * expects Prism Schema objects. The narration schema is authored once in
 * ChaosNarrationSchema and normalized by NativeObjectSchema, so this adapter
 * passes those serialized fragments through untouched rather than restating
 * the contract in a second, drift-prone definition.
 */
final readonly class NarrationToolProperty implements Schema
{
    /**
     * @param  array<string, mixed>  $schema
     */
    public function __construct(
        private string $name,
        private array $schema,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->schema;
    }
}
