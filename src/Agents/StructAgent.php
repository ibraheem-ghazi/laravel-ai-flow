<?php

namespace IbraheemGhazi\LaravelAiFlow\Agents;

use IbraheemGhazi\LaravelAiFlow\Support\TemplateVariableReplacer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchema as JsonSchemaFactory;
use Illuminate\JsonSchema\Types\ObjectType;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use RuntimeException;
use Stringable;

#[Strict]
class StructAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public const string WRAPPED_ROOT_KEY = 'value';

    /** @var array<string, mixed> */
    protected array $rawSchema = [];

    /**
     * @param  array<string, mixed>  $rawSchema
     * @param  array<string, mixed>  $templateParams
     */
    public function __construct(
        protected string $systemPrompt,
        array $rawSchema = [],
        array $templateParams = [],
    ) {
        $this->rawSchema = TemplateVariableReplacer::replaceInArray($rawSchema, $templateParams);
    }

    public function instructions(): Stringable|string
    {
        return $this->systemPrompt;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        if ($this->usesObjectPropertyMap()) {
            return $this->buildPropertyMap($this->rawSchema);
        }

        $rootType = JsonSchemaFactory::fromArray($this->rawSchema);

        if ($rootType instanceof ObjectType) {
            return $this->buildPropertyMap($this->rawSchema);
        }

        return [
            self::WRAPPED_ROOT_KEY => $rootType->required(),
        ];
    }

    public function usesObjectPropertyMap(): bool
    {
        return ($this->rawSchema['type'] ?? null) === 'object'
            && array_key_exists('properties', $this->rawSchema);
    }

    public function usesWrappedRoot(): bool
    {
        return ! $this->usesObjectPropertyMap();
    }

    /**
     * @param  array<string, mixed>  $structured
     * @return array<string, mixed>
     */
    public function unwrapStructured(array $structured): array
    {
        if (! $this->usesWrappedRoot()) {
            return $structured;
        }

        $root = $structured[self::WRAPPED_ROOT_KEY] ?? null;

        return is_array($root) ? $root : $structured;
    }

    /**
     * @param  array<string, mixed>  $objectSchema
     * @return array<string, Type>
     */
    protected function buildPropertyMap(array $objectSchema): array
    {
        $required = $objectSchema['required'] ?? [];
        $properties = [];

        foreach ($objectSchema['properties'] ?? [] as $name => $propertySchema) {
            if (! is_array($propertySchema)) {
                continue;
            }

            $propertyType = JsonSchemaFactory::fromArray($propertySchema);

            if (in_array($name, $required, true)) {
                $propertyType->required();
            }

            $properties[$name] = $propertyType;
        }

        if ($properties === []) {
            throw new RuntimeException('Invalid JSON schema provided: object schema has no properties.');
        }

        return $properties;
    }
}
