<?php

namespace IbraheemGhazi\LaravelAiFlow\Blueprints;

use IbraheemGhazi\LaravelAiFlow\Concerns\HasSubComponents;
use IbraheemGhazi\LaravelAiFlow\Contracts\AiComponent;
use IbraheemGhazi\LaravelAiFlow\Contracts\HasParentComponent;
use IbraheemGhazi\LaravelAiFlow\Contracts\HasPlanPrompt;
use IbraheemGhazi\LaravelAiFlow\Contracts\HasSystemPrompt;
use IbraheemGhazi\LaravelAiFlow\Jobs\AiTaskJob;
use IbraheemGhazi\LaravelAiFlow\Support\FlowProgressTracker;

/**
 * @extends BaseComponent<Task>
 */
abstract class Step extends BaseComponent implements HasParentComponent, HasPlanPrompt, HasSystemPrompt
{
    use HasSubComponents;

    public protected(set) bool $shouldChain = false;

    private ?Flow $parentComponent = null;

    final public function __construct(array $params = [])
    {
        parent::__construct($params);
    }

    final protected function allowedComponentClass(): string
    {
        return Task::class;
    }

    final public function mapComponentsToJob(): array
    {
        $batch = [];

        foreach ($this->components() as $taskClass) {
            /** @var Task $task */
            $task = new $taskClass($this->params());
            $task->setParent($this);

            if (FlowProgressTracker::status($task) === FlowProgressTracker::STATUS_FINISHED) {
                continue;
            }

            FlowProgressTracker::reopen($task);
            $batch[] = $this->mapComponentToJob($task);
        }

        return $batch;
    }

    final protected function mapComponentToJob(Task $task): AiTaskJob
    {
        return new AiTaskJob($task);
    }

    final public function getSystemPrompt(): string
    {
        return $this->getParent()?->getSystemPrompt() ?? '';
    }

    public function getPlanPrompt(): string
    {
        return '';
    }

    public function additionalTags(): array
    {
        return [];
    }

    final public function getParent(): ?Flow
    {
        /** @var ?Flow $parent */
        $parent = $this->parentComponent;

        return $parent;
    }

    /**
     * @param  Flow  $parentComponent
     */
    final public function setParent(AiComponent $parentComponent): void
    {
        if (isset($this->parentComponent)) {
            return;
        }
        $this->parentComponent = $parentComponent;
    }
}
