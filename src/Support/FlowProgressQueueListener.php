<?php

namespace IbraheemGhazi\LaravelAiFlow\Support;

use IbraheemGhazi\LaravelAiFlow\Contracts\AiComponent;
use IbraheemGhazi\LaravelAiFlow\Jobs\AiFlowFailedJob;
use IbraheemGhazi\LaravelAiFlow\Jobs\AiTaskJob;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Throwable;

/**
 * This class was totally written by AI,so if you find any bug here.
 * feel free to fix it.
 */
class FlowProgressQueueListener
{
    public static function pending(JobQueued $event): void
    {
        if (! $event->job instanceof AiTaskJob) {
            return;
        }

        self::markPending($event->job);
    }

    public static function running(JobProcessing $event): void
    {
        $job = self::command($event->job);

        if (! $job instanceof AiTaskJob) {
            return;
        }

        self::markPending($job);
        self::markRunning($job);
    }

    public static function failed(JobFailed $event): void
    {
        $job = self::command($event->job);

        if ($job instanceof AiTaskJob) {
            $task = $job->task;
            $step = $task->getParent();
            $flow = $step?->getParent();

            self::markFailed($task, $event->exception->getMessage());
            self::markFailed($step, $event->exception->getMessage());
            self::markFailed($flow, $event->exception->getMessage());

            return;
        }

        if ($job instanceof AiFlowFailedJob) {
            FlowProgressTracker::setFailed($job->flow, $job->message);
        }
    }

    public static function finished(JobProcessed $event): void
    {
        $job = self::command($event->job);

        if (! $job instanceof AiTaskJob || $job->batch()?->cancelled()) {
            return;
        }

        $task = $job->task;

        FlowProgressTracker::finishIfRunning($task);

        if (FlowProgressTracker::status($task) !== FlowProgressTracker::STATUS_FINISHED) {
            return;
        }

        $step = $task->getParent();
        $flow = $step?->getParent();

        if ($step !== null) {
            FlowProgressTracker::finishIfIdle($step);
        }

        if ($flow !== null) {
            FlowProgressTracker::finishIfIdle($flow);
        }
    }

    private static function markPending(AiTaskJob $job): void
    {
        $task = $job->task;
        $step = $task->getParent();
        $flow = $step?->getParent();

        FlowProgressTracker::setPending($task);

        if ($step !== null) {
            FlowProgressTracker::setPending($step);
        }

        if ($flow !== null) {
            FlowProgressTracker::setPending($flow);
        }
    }

    private static function markRunning(AiTaskJob $job): void
    {
        $task = $job->task;

        if (FlowProgressTracker::status($task) === FlowProgressTracker::STATUS_FINISHED) {
            return;
        }

        $step = $task->getParent();
        $flow = $step?->getParent();

        FlowProgressTracker::setRunning($task);
        self::markRunningOnce($step);
        self::markRunningOnce($flow);
    }

    private static function markRunningOnce(?AiComponent $component): void
    {
        if ($component === null || in_array(FlowProgressTracker::status($component), [
            FlowProgressTracker::STATUS_RUNNING,
            FlowProgressTracker::STATUS_FINISHED,
            FlowProgressTracker::STATUS_ERROR,
        ], true)) {
            return;
        }

        FlowProgressTracker::setRunning($component);
    }

    private static function markFailed(?AiComponent $component, ?string $message): void
    {
        if ($component === null) {
            return;
        }

        FlowProgressTracker::setFailed($component, $message);
    }

    private static function command(Job $job): AiTaskJob|AiFlowFailedJob|null
    {
        $payload = $job->payload();
        $class = $payload['data']['commandName'] ?? null;
        $command = $payload['data']['command'] ?? null;

        if (! is_string($command) || ($class !== AiTaskJob::class && $class !== AiFlowFailedJob::class)) {
            return null;
        }

        try {
            $instance = unserialize($command);
        } catch (Throwable) {
            return null;
        }

        if ($instance instanceof AiTaskJob || $instance instanceof AiFlowFailedJob) {
            return $instance;
        }

        return null;
    }
}
