<?php

namespace IbraheemGhazi\LaravelAiFlow\Concerns;

use Throwable;

trait CanTriggerEventsHandlers
{
    /**
     * @throws Throwable
     */
    final public function triggerException(Throwable $throwable): void
    {
        if (method_exists($this, 'onException')) {
            $this->onException($throwable);
        }
    }
}
