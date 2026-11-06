<?php

namespace IbraheemGhazi\LaravelAiFlow\Http\Controllers;

use IbraheemGhazi\LaravelAiFlow\Blueprints\BaseComponent;
use IbraheemGhazi\LaravelAiFlow\Support\FlowProgressTracker;
use Illuminate\Routing\Controller;

/**
 * This controller was written completely by AI
 * feel free to fix any bug you found.
 */
class AiFlowProgressTrackerController extends Controller
{
    /** @var array<string, string> */
    protected array $labels = [];

    /**
     * @return array{status: ?string, percentage: int, current_step: ?string, steps: list<array{label: string, status: string, tasks: list<array{label: string, status: string}>}>}
     */
    public function __invoke(?string $runId = null): array
    {
        $runId = $this->resolveRunId($runId);

        if ($runId === null) {
            return $this->emptyProgress();
        }

        [$status, $steps] = $this->groupSteps(
            $this->getFlowInfo($runId)
        );
    
        return $this->getResponseShape($status, $steps);
    }
    
    /**
     * @param  ?string $status
     * @param  array<string, array{label?: string, status?: string, tasks?: list<array{label: string, status: string}>}>  $steps
     * @return array
     */
    protected function getResponseShape(?string $status, array $steps): array
    {
         return [
            'status' => $status,
            'percentage' => $this->percentage($steps),
            'current_step' => $this->currentStep($steps),
            'steps' => $steps,
        ];
    }
    
    protected function getFlowInfo(string $runId): array
    {
        return FlowProgressTracker::getRunInformation($runId);
    }

    /**
     * @param  list<object>  $rows
     * @return array{0: ?string, 1: list<array{label: string, status: string, tasks: list<array{label: string, status: string}>}>}
     */
    protected function groupSteps(array $rows): array
    {
        $status = null;
        $steps = [];

        foreach ($rows as $row) {
            if ($row->step === '' && $row->task === '') {
                $status = $this->publicStatus($row->status);

                continue;
            }

            if ($row->step === '') {
                continue;
            }

            if ($row->task !== '') {
                $this->addTask($steps, $row);

                continue;
            }

            $this->addStep($steps, $row);
        }

        return [$status, array_values($steps)];
    }

    /**
     * @param  array<string, array{label?: string, status?: string, tasks: list<array{label: string, status: string}>}>  $steps
     */
    protected function addTask(array &$steps, object $row): void
    {
        $steps[$row->step]['tasks'][] = [
            'label' => $this->label($row->task),
            'status' => $row->status,
        ];
    }

    /**
     * @param  array<string, array{label?: string, status?: string, tasks?: list<array{label: string, status: string}>}>  $steps
     */
    protected function addStep(array &$steps, object $row): void
    {
        $steps[$row->step] = [
            'label' => $this->label($row->step),
            'status' => $row->status,
            'tasks' => $steps[$row->step]['tasks'] ?? [],
        ];
    }

    /**
     * @param  list<array{label: string, status: string, tasks: list<array{label: string, status: string}>}>  $steps
     */
    protected function percentage(array $steps): int
    {
        $total = 0;
        $finished = 0;

        foreach ($steps as $step) {
            foreach ($step['tasks'] as $task) {
                $total++;

                if ($task['status'] === FlowProgressTracker::STATUS_FINISHED) {
                    $finished++;
                }
            }
        }

        if ($total === 0) {
            return 0;
        }

        return (int) round(($finished / $total) * 100);
    }

    /**
     * @param  list<array{label: string, status: string, tasks: list<array{label: string, status: string}>}>  $steps
     */
    protected function currentStep(array $steps): ?string
    {
        foreach ($steps as $step) {
            if (in_array($step['status'], [
                FlowProgressTracker::STATUS_RUNNING,
                FlowProgressTracker::STATUS_ERROR,
            ], true)) {
                return $step['label'];
            }
        }

        foreach ($steps as $step) {
            if ($step['status'] === FlowProgressTracker::STATUS_PENDING) {
                return $step['label'];
            }
        }

        return null;
    }

    protected function publicStatus(string $status): string
    {
        if ($status === FlowProgressTracker::STATUS_ERROR) {
            return 'failed';
        }

        return $status;
    }

    protected function resolveRunId(?string $runId): ?string
    {
        if (is_string($runId) && $runId !== '') {
            return $runId;
        }

        $latest = FlowProgressTracker::query()->orderByDesc('id')->value('run_id');

        return is_string($latest) && $latest !== '' ? $latest : null;
    }

    /**
     * @return array{status: null, percentage: int, current_step: null, steps: array{}}
     */
    protected function emptyProgress(): array
    {
        return [
            'status' => null,
            'percentage' => 0,
            'current_step' => null,
            'steps' => [],
        ];
    }

    /**
     * @param class-string<BaseComponent> $class
     */
    protected function label(string $class): string
    {
        if (! isset($this->labels[$class])) {
            $this->labels[$class] = (new $class)->label();
        }

        return $this->labels[$class];
    }
}
