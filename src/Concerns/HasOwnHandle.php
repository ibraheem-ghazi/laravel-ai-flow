<?php

namespace IbraheemGhazi\LaravelAiFlow\Concerns;

use IbraheemGhazi\LaravelAiFlow\Support\FlowProgressTracker;
use Throwable;

trait HasOwnHandle
{
    use CanTriggerEventsHandlers;

    private bool $success = true;

    /** @var array<string, mixed> */
    private array $data = [];

    abstract public function handle(): void;

    /**
     * @throws Throwable
     */
    final public function executeComponent(): void
    {
        $this->success = true;
        $this->data = [];

        try {
            $this->onStart();
            $this->handle();
            if ($this->success) {
                FlowProgressTracker::setFinished($this, $this->data);
                $this->onSuccess($this->data);
            } else {
                FlowProgressTracker::setFailed($this, 'Manual failure');
                $this->onFail($this->data);
            }
        } catch (Throwable $throwable) {
            $this->success = false;
            $this->onFail($this->data, $throwable);
        } finally {
            $this->onEnd();
        }
    }

    final protected function setStatus(bool $success, array $data = []): void
    {
        $this->success = $success;
        $this->data = $data;
    }

    protected function onStart(): void
    {
        // no-op
    }

    protected function onEnd(): void
    {
        // no-op
    }

    protected function onSuccess(array $data = []): void
    {
        // no-op
    }

    /** @noinspection PhpUnusedParameterInspection */
    /**
     * @throws Throwable
     */
    protected function onFail(array $data = [], ?Throwable $throwable = null): void
    {
        throw $throwable;
        // no-op
    }

    /**
     * @throws Throwable
     */
    protected function onException(Throwable $throwable): void
    {
        $this->onFail([], $throwable);
    }

    final public function isSuccess(): bool
    {
        return $this->success;
    }
}
