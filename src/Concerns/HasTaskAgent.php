<?php

namespace IbraheemGhazi\LaravelAiFlow\Concerns;

use IbraheemGhazi\LaravelAiFlow\Blueprints\Task;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * @mixin Task
 */
trait HasTaskAgent
{
    final protected function generateText(string $prompt, bool $usePlan = false): string
    {
        $inputPrompt = $usePlan ? $this->getResolvedPlanPrompt($prompt) : $prompt;
        $response = $this->agent()->textAgent()->prompt($inputPrompt);

        return $response->text;
    }

    final protected function generateStructured(array $schema, ?string $prompt = null, bool $usePlan = true): array
    {
        $inputPrompt = $usePlan ? $this->getResolvedPlanPrompt($prompt) : $prompt;

        $structAgent = $this->agent()->structuredOutputAgent($schema);
        $response = $structAgent->prompt($inputPrompt);

        if ($response instanceof StructuredAgentResponse) {
            return $structAgent->unwrapStructured($response->structured);
        }

        return [];
    }

    final protected function generateStructuredFromFile(string $filePath, ?string $prompt = null, bool $usePlan = true): array
    {
        $inputPrompt = ($usePlan ? $this->getResolvedPlanPrompt($prompt) : $prompt) ?? '';

        $structAgent = $this->agent()->structuredOutputAgentFromFile($filePath);
        $response = $structAgent->prompt($inputPrompt);

        if ($response instanceof StructuredAgentResponse) {
            return $structAgent->unwrapStructured($response->structured);
        }

        return [];
    }

    // image generation can use directly $path = Image::of()->generate()->storeAs();
}
