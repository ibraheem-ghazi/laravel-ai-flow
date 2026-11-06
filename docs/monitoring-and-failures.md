# Monitoring and failure handling

[Documentation home](README.md) · [Progress](progress.md) · [Retries](retries-and-idempotency.md)

Descriptive job names and tags help you identify workflow operations in queue monitoring. Telescope and Horizon are optional application tools; the package does not install or configure them.

## Names and tags

| Operation | Example display name |
| --- | --- |
| Task | `CreateCatalog::Content::Introduction` |
| Parallel batch | `Create catalog::Content` |
| Failure marker | `FLOW FAILED: Create catalog` |
| Finalization job | `FLOW FINISHED: Create catalog` |

Define tags on a flow, step, or task:

```php
public function additionalTags(): array
{
    return [
        'tenant:'.$this->param('tenant_id'),
        'feature:catalog-generation',
    ];
}
```

Task jobs combine and deduplicate tags from all three levels. Failure and finalize jobs carry flow tags. Add `'ai-run:'.$this->runId` on the flow when you want a run-specific tag.

Horizon and Telescope's job watcher can use the job-provided tags for filtering. Telescope also shows descriptive job names. Enable its job watcher and recording rules in your application; no package-specific Telescope callback is needed to expose these tags.

## Task failure

An escaping exception follows Laravel queue retry and failure rules. A terminal task failure marks the task, step, and flow `error`. In a parallel step, the batch is cancelled. Tasks that have not begun their work return when they detect cancellation; already running tasks are not forcibly stopped.

A failed chain stops later steps. Resolve the cause, then use the flow's `retry($runId)` to resume unfinished work.

## Failed-job marker

The default flow failure callback dispatches `AiFlowFailedJob` with the original exception context. Its `FLOW FAILED:` display name makes a stopped flow visible even when ordinary task jobs do not make the chain-level issue obvious.

The marker intentionally fails, has one try and a one-second timeout, and supplies the original message for flow tracking. It is diagnostic: retrying it does not resume business work.

It uses default queue settings, so process that destination even when task work runs on a dedicated queue. A custom failure callback can replace the marker behavior.

The current implementation deletes its queue job but does not explicitly remove a failed-job repository record. It can therefore still appear in `failed_jobs`; do not assume broad `queue:retry` commands exclude it. Use package-level flow retry for recovery.

## Custom failure handling

Set a process-wide callback during application boot to replace the default marker:

```php
use IbraheemGhazi\LaravelAiFlow\Blueprints\Flow;

Flow::$onDispatchFail = function (\Throwable $exception): void {
    report($exception);
};
```

The callback receives the exception. Normal task-failure tracking still uses queue events. This static setting persists within the process.

For flow-specific behavior, override the flow method:

```php
protected function onDispatchFail(\Throwable $throwable): void
{
    logger()->error('Catalog flow stopped', [
        'run_id' => $this->runId,
        'message' => $throwable->getMessage(),
    ]);

    parent::onDispatchFail($throwable);
}
```

Calling the parent preserves the global callback or default marker. Omit it to replace that behavior for this flow.

## Finalization failures

The `FLOW FINISHED:` job name identifies the final callback's purpose. It does not prove that callback succeeded. A finalize exception fails that job separately, without dispatching a flow-failure marker or undoing completed tasks. Check its status when application readiness depends on finalization.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Run remains pending | Worker connection/queue, obsolete jobs, and locks |
| Parallel tasks execute one at a time | Worker count and queue capacity |
| Dependent output is empty | Producer completion, expected result fields, unambiguous task class |
| Finished flow but application not ready | Finalize job and application persistence |
| Repeated dispatch queues nothing | Live-run protection or all tasks already completed |
| No failure marker in monitoring | Default worker, custom callback, watcher recording rules |
