<?php

namespace IbraheemGhazi\LaravelAiFlow\Concerns;

use IbraheemGhazi\LaravelAiFlow\Support\TemplateVariableReplacer;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;

trait HasPromptsActions
{
    final protected function replacePromptVariables(
        string $prompt,
        array $variables = []
    ): string {
        return TemplateVariableReplacer::replaceInString(
            $prompt,
            array_merge($this->params(), $variables)
        );
    }

    /**
     * @throws FileNotFoundException
     */
    final protected function promptFromFile(
        string $path,
        array $variables = []
    ): string {
        $prompt = File::get($path);

        return $this->replacePromptVariables($prompt, $variables);
    }
}
