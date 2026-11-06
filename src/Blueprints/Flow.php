<?php

namespace IbraheemGhazi\LaravelAiFlow\Blueprints;

use Closure;
use IbraheemGhazi\LaravelAiFlow\Concerns\HasSubComponents;
use IbraheemGhazi\LaravelAiFlow\Contracts\HasSystemPrompt;
use IbraheemGhazi\LaravelAiFlow\Jobs\AiFlowFailedJob;
use IbraheemGhazi\LaravelAiFlow\Jobs\AiFlowFinishedJob;
use IbraheemGhazi\LaravelAiFlow\Jobs\AiTaskJob;
use IbraheemGhazi\LaravelAiFlow\Support\FlowProgressTracker;
use Illuminate\Bus\Batch;
use Illuminate\Bus\PendingBatch;
use Illuminate\Bus\UniqueLock;
use Illuminate\Foundation\Bus\PendingChain;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Testing\Fakes\PendingBatchFake;
use Illuminate\Support\Testing\Fakes\PendingChainFake;
use Throwable;

/**
 * @extends BaseComponent<Step>
 */
abstract class Flow extends BaseComponent implements HasSystemPrompt
{
    use HasSubComponents;

    public static ?Closure $onDispatchFail = null;

    protected bool $executeFinalizeCallback = false;

    public protected(set) ?string $runId = null;

    final public function __construct(array $params = [], ?string $runId = null)
    {
        $this->runId = filled($runId) ? $runId : Str::ulid()->toString();
        parent::__construct($params);
    }

    final public static function execute(
        array $params = [],
        ?string $connection = null, ?string $queue = null,
        ?string $runId = null, bool $force = false): string
    {
        $flow = new static($params, $runId);
        $flow->dispatch($connection, $queue, $force);

        return $flow->runId ?? '';
    }

    public function getDispatcher(): ?PendingChain
    {
        $jobs = $this->mapComponentsToJob();
        if ($jobs === []) {
            return null;
        }
        $chain = Bus::chain($jobs)
            ->catch(fn (Throwable $e) => $this->onDispatchFail($e));
        if ($this->executeFinalizeCallback) {
            $chain->append(new AiFlowFinishedJob($this));
        }

        return $chain;
    }

    final public function dispatch(
        ?string $connection = null, ?string $queue = null, bool $force = false): void
    {
        $lock = Cache::lock('ai-flow:'.$this->runId, 30);
        if (! $lock->get()) {
            return;
        }

        try {
            $status = FlowProgressTracker::status($this);
            if (! $force && in_array($status, [
                FlowProgressTracker::STATUS_PENDING,
                FlowProgressTracker::STATUS_RUNNING,
            ], true)) {
                return;
            }
            $bus = $this->getDispatcher();
            if ($bus === null) {
                FlowProgressTracker::settle($this);

                return;
            }
            if (is_string($connection) && filled(trim($connection))) {
                $bus = $bus->onConnection($connection);
            }
            if (is_string($connection) && filled(trim($queue ?? ''))) {
                $bus = $bus->onQueue($queue);
            }
            if ($force) {
                $this->releaseUniqueLocks($bus);
            }

            $bus->dispatch();
        } finally {
            $lock->release();
        }
    }

    private function releaseUniqueLocks(PendingChain $bus): void
    {
        $uniqueLock = app(UniqueLock::class);

        foreach ([$bus->job, ...$bus->chain] as $link) {
            if ($link instanceof AiTaskJob) {
                $uniqueLock->release($link);

                continue;
            }

            if (! $link instanceof PendingBatch) {
                continue;
            }

            foreach ($link->jobs as $job) {
                if ($job instanceof AiTaskJob) {
                    $uniqueLock->release($job);
                }
            }
        }
    }

    protected function onDispatchFail(Throwable $throwable): void
    {
        if (is_callable(static::$onDispatchFail)) {
            call_user_func(static::$onDispatchFail, $throwable);

            return;
        }

        AiFlowFailedJob::dispatch(
            message: $throwable->getMessage(),
            exception: $throwable::class,
            file: $throwable->getFile(),
            line: $throwable->getLine(),
            trace: $throwable->getTraceAsString(),
            flow: $this,
        );
    }

    final public static function retry(
        string $runId,
        array $params = [], bool $freshParams = false,
        ?string $connection = null, ?string $queue = null,
        bool $force = false,
    ): void {
        $stored = FlowProgressTracker::inputParamsFor($runId, static::class);

        $resolved = $freshParams
            ? $params
            : array_replace_recursive($stored, $params);

        (new static($resolved, $runId))->dispatch($connection, $queue, $force);
    }

    final protected function allowedComponentClass(): string
    {
        return Step::class;
    }

    final public function mapComponentsToJob(): array
    {
        $chain = [];

        foreach ($this->components() as $stepClass) {
            /** @var Step $step */
            $step = new $stepClass($this->params());
            $step->setParent($this);

            $jobs = $step->mapComponentsToJob();

            if ($jobs === []) {
                continue;
            }

            FlowProgressTracker::reopen($step);

            if ($chain !== []) {
                FlowProgressTracker::reopen($this);
                FlowProgressTracker::syncInputParams($this);
            }

            $output = $this->mapComponentToJob($step, $jobs);

            if ($output instanceof PendingChain) {
                /**
                 * nested Buss::chain(...) is not possible but since we already
                 * in a chain it's simply needed to list these jobs without wrapper
                 */
                array_push($chain, $output->job, ...$output->chain);
            } else {
                $chain[] = $output->catch(fn (Batch $batch, Throwable $e) => $batch->cancel());
            }
        }

        if ($chain !== []) {
            FlowProgressTracker::reopen($this);
        }

        return $chain;
    }

    final protected function mapComponentToJob(Step $step, array $jobs): PendingBatch|PendingBatchFake|PendingChain|PendingChainFake
    {
        if ($step->shouldChain) {
            return Bus::chain($jobs);
        }

        return Bus::batch($jobs)->name(sprintf(
            '%s::%s',
            $step->getParent()->label(),
            $step->label(),
        ));
    }

    public function finalize(): void
    {
        // no-op
    }
}
