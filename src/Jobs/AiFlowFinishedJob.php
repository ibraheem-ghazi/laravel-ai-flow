<?php

namespace IbraheemGhazi\LaravelAiFlow\Jobs;

use IbraheemGhazi\LaravelAiFlow\Blueprints\Flow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class AiFlowFinishedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Flow $flow) {}

    public function displayName(): string
    {
        return 'FLOW FINISHED: '.$this->flow->label();
    }

    public function tags(): array
    {
        return $this->flow->additionalTags();
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        try {
            $this->flow->finalize();
        } catch (Throwable $e) {
            /**
             * prevent call AiFlowFailedJob, as the flow itself succeed
             * but the issue is in finalizing it.
             */
            $this->chainCatchCallbacks = [];
            throw $e;
        }
    }
}
