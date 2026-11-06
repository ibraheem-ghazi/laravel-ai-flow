<?php

namespace IbraheemGhazi\LaravelAiFlow\Jobs;

use IbraheemGhazi\LaravelAiFlow\Blueprints\Flow;
use IbraheemGhazi\LaravelAiFlow\Blueprints\Step;
use IbraheemGhazi\LaravelAiFlow\Blueprints\Task;
use IbraheemGhazi\LaravelAiFlow\Support\FlowProgressTracker;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class AiTaskJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private readonly Flow $flow;

    private readonly Step $step;

    public int $uniqueFor = 3600;

    public function __construct(
        public Task $task
    ) {
        $this->step = $this->task->getParent();
        $this->flow = $this->step->getParent();
    }

    public function uniqueId(): string
    {
        return implode('|', [
            $this->flow->runId,
            $this->flow::class,
            $this->step::class,
            $this->task::class,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        if (FlowProgressTracker::status($this->task) === FlowProgressTracker::STATUS_FINISHED) {
            return;
        }

        try {
            $this->task->executeComponent();
        } catch (Throwable $exception) {
            $this->task->triggerException($exception);
            throw $exception;
        }
    }

    /**
     * Get the display name for the queued job.
     */
    public function displayName(): string
    {
        return implode('::', [
            Str::studly($this->flow->label()),
            Str::studly($this->step->label()),
            Str::studly($this->task->label()),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return array_unique([
            ...$this->flow->additionalTags(),
            ...$this->step->additionalTags(),
            ...$this->task->additionalTags(),
        ]);
    }
}
