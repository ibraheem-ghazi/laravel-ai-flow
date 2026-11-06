<?php

namespace IbraheemGhazi\LaravelAiFlow\Agents;

class GeneratorAgent
{
    /**
     * @param  array<string, mixed>  $templateParams
     */
    public function __construct(
        protected string $systemPrompt,
        protected array $templateParams = [],
    ) {}

    /**
     * @param  array<string, mixed>  $templateParams
     */
    public static function make(string $systemPrompt, array $templateParams = []): static
    {
        return new static($systemPrompt, $templateParams);
    }

    public function textAgent(): ?TextAgent
    {
        return new TextAgent($this->systemPrompt);
    }

    public function structuredOutputAgent(array $rawSchema): ?StructAgent
    {
        return new StructAgent($this->systemPrompt, $rawSchema, $this->templateParams);
    }

    public function structuredOutputAgentFromFile(string $path): ?StructAgent
    {
        $content = file_get_contents($path);
        $rawSchema = json_decode(strval($content), true) ?? [];
        unset($content);

        return $this->structuredOutputAgent($rawSchema);
    }
}
