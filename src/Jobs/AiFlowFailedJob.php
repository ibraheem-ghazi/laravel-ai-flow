<?php

namespace IbraheemGhazi\LaravelAiFlow\Jobs;

use IbraheemGhazi\LaravelAiFlow\Blueprints\Flow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Trigger this job on a chain failure surely not the best idea
 * but for some cases the jobs processed but chain stopped from
 * queueing the rest jobs making it, no error visible in
 * telescope jobs making all job marked success but not all
 * jobs listed. So this work as a marker of failure job.
 * Of course this behavior can be replaced by :
 * - set global catch callable on Flow::$onDispatchFail
 * - or override "onDispatchFail" method per flow.
 */
class AiFlowFailedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1;

    public function __construct(
        public string $message,
        public string $exception,
        public string $file,
        public int $line,
        public string $trace,
        public Flow $flow,

    ) {}

    public function displayName(): string
    {
        return "FLOW FAILED: {$this->flow->label()}";
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
        ]);
    }

    public function handle(): void
    {
        $this->fail();
    }

    /**
     * once this job executed and fails, it will be removed from failed_jobs
     * to prevent its being retried when the queue:retry command called.
     * leaving it registered in the log and telescope only
     */
    public function failed(): void
    {
        $this->delete();
    }
}
