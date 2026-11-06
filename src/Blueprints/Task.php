<?php

/** @noinspection PhpHierarchyChecksInspection phpstorm keep scream when methods became final despite its correct */

namespace IbraheemGhazi\LaravelAiFlow\Blueprints;

use IbraheemGhazi\LaravelAiFlow\Agents\GeneratorAgent;
use IbraheemGhazi\LaravelAiFlow\Concerns\HasOwnHandle;
use IbraheemGhazi\LaravelAiFlow\Concerns\HasTaskAgent;
use IbraheemGhazi\LaravelAiFlow\Contracts\AiComponent;
use IbraheemGhazi\LaravelAiFlow\Contracts\AiComponentWithHandle;
use IbraheemGhazi\LaravelAiFlow\Contracts\HasParentComponent;
use IbraheemGhazi\LaravelAiFlow\Contracts\HasPlanPrompt;
use IbraheemGhazi\LaravelAiFlow\Contracts\HasSystemPrompt;

abstract class Task extends BaseComponent implements AiComponentWithHandle, HasParentComponent, HasPlanPrompt, HasSystemPrompt
{
    use HasOwnHandle, HasTaskAgent;

    private ?Step $parentComponent = null;

    private ?GeneratorAgent $generatorAgent = null;

    final public function __construct(array $params = [])
    {
        parent::__construct($params);
    }

    protected function init(): void
    {
        // no-op
    }

    public function additionalTags(): array
    {
        return [];
    }

    final public function getParent(): ?Step
    {
        /** @var ?Step $parent */
        $parent = $this->parentComponent;

        return $parent;
    }

    /**
     * @param  Step  $parentComponent
     */
    final public function setParent(AiComponent $parentComponent): void
    {
        if (isset($this->parentComponent)) {
            return;
        }
        $this->parentComponent = $parentComponent;
    }

    final public function getSystemPrompt(): string
    {
        return $this->getParent()?->getSystemPrompt() ?? '';
    }

    final protected function agent()
    {
        if (! $this->generatorAgent) {
            $this->generatorAgent = GeneratorAgent::make($this->getSystemPrompt(), $this->params());
        }

        return $this->generatorAgent;
    }

    final protected function getResolvedPlanPrompt(?string $additional = null): string
    {
        return implode("\n\n", array_filter([
            $this->getParent()?->getPlanPrompt(),
            $this->getPlanPrompt(),
            $additional,
        ]));
    }
}
