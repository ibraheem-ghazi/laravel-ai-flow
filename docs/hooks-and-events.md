# Hooks and events

[Documentation home](README.md) · [Failure handling](monitoring-and-failures.md)

Task lifecycle hooks let you add application callbacks. Laravel queue events maintain tracking automatically. The package does not define public domain events such as `FlowStarted` or `TaskCompleted`; dispatch your own application events where appropriate.

## Task hooks

Override these protected methods on a task:

| Hook | When it runs |
| --- | --- |
| `onStart(): void` | Before `handle()` |
| `onSuccess(array $data = []): void` | After the result is marked finished |
| `onFail(array $data = [], ?Throwable $throwable = null): void` | During failure and exception handling |
| `onEnd(): void` | In the execution block's `finally` path |
| `onException(Throwable $throwable): void` | When an exception escapes task execution to the job |

For optional application reporting:

```php
protected function onSuccess(array $data = []): void
{
    logger()->info('Catalog introduction generated', [
        'tenant_id' => $this->param('tenant_id'),
    ]);
}
```

Store required business data in `handle()`. The result has already been recorded before `onSuccess()` runs, and a hook failure does not undo it.

## Preserve failures

Throwing from `handle()` is the clearest way to reject a failed business condition:

```php
if ($products === []) {
    throw new \RuntimeException('No usable products were generated.');
}
```

For custom reporting, rethrow from `onFail()` when execution should stop:

```php
protected function onFail(array $data = [], ?\Throwable $throwable = null): void
{
    logger()->warning('Catalog task failed', [
        'message' => $throwable?->getMessage(),
    ]);

    throw $throwable ?? new \RuntimeException('Catalog task failed.');
}
```

`setStatus(false, $data)` is available, but the default `onFail()` throws its nullable exception argument even when no exception was supplied. It does not currently provide a clean exception-free failure path. Override `onFail()` if you choose manual failure status.

Hooks can repeat: an escaping exception can reach `onFail()` during execution and again through the default `onException()`. Retries can repeat callbacks too. Avoid assuming notifications, reports, or cleanup run once. Swallowing a failure can let the job return normally and affect subsequent flow execution.

`onEnd()` runs when execution leaves its block; forcibly terminated processes cannot guarantee application cleanup. Use [flow finalization](workflows.md#finalization) for a separate callback after successful steps.

## Queue events

Provider discovery connects tracking automatically:

| Laravel event | Tracking behavior |
| --- | --- |
| `JobQueued` | Ensures pending entries exist |
| `JobProcessing` | Marks work running |
| `JobProcessed` | Completes running work and rolls completion up when children are finished |
| `JobFailed` | Marks task and parent states errored; also handles flow-failure markers |

Application listeners can use Laravel queue events for additional monitoring. Filter appropriately because those events also cover unrelated jobs. Do not register the package subscriber again; it is already connected by the provider.
