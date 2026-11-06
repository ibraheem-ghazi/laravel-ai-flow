# Retries and idempotency

[Documentation home](README.md) · [Failures](monitoring-and-failures.md)

Resume unfinished work while retaining successful task results. This saves generation calls when a later operation fails.

## Retry a failed run

```php
CreateCatalogFlow::retry($runId);
```

Use the original flow class and run ID. Stored inputs are loaded, finished tasks are skipped, and the remaining work is queued. Completed payloads remain available to later tasks.

To correct input for the remaining work:

```php
CreateCatalogFlow::retry(
    runId: $runId,
    params: ['language' => 'English'],
    connection: 'redis',
    queue: 'ai',
);
```

Overrides merge with stored inputs through `array_replace_recursive()`. Pass `freshParams: true` to use only the supplied inputs. Changed parameters do not regenerate completed tasks or replace their results. Start a new run when you want fresh output from all tasks.

If every task is finished, retry queues nothing, including no finalization job.

## Duplicate-work protections

| Protection | Behavior |
| --- | --- |
| Run lock | A 30-second cache lock prevents overlapping dispatch calls while held |
| Live-run guard | Pending or running flows are not dispatched again unless forced |
| Completed-task checks | Finished tasks are skipped when rebuilding and before execution |
| Task uniqueness declaration | Identifies a task by run, flow, step, and task, with a one-hour duration where Laravel's dispatch path applies uniqueness |

These reduce duplicate work; they do not guarantee exactly-once side effects. Chains and batches have their own dispatch behavior, so job uniqueness is not a universal deduplication guarantee. Workers must share the tracking database and lock-capable cache.

## Recover a stuck run

After confirming the previous worker has stopped and old jobs are no longer queued or running:

```php
CreateCatalogFlow::retry(
    runId: $runId,
    connection: 'redis',
    queue: 'ai',
    force: true,
);
```

Force bypasses the live-run guard and releases task uniqueness locks before dispatch. It still honors the run dispatch lock and skips finished tasks. It does not cancel old jobs or interrupt workers; forcing an active run can overlap work.

## Make application actions repeatable

A worker can stop after an external action succeeds but before completion is saved. Retrying may repeat that action. Use unique database keys, transactions for related writes, or an external API's idempotency key.

For example, update a catalog by a unique business key instead of blindly creating another record. Use a run ID in an external idempotency key when an action should happen once per run; use a business-operation ID when several runs represent the same action.

Perform required persistence in `handle()` before it returns successfully. The package marks the task finished before `onSuccess()`, so a hook failure can leave a completed result that a retry skips.

## Recovery details

Stored inputs are not refreshed consistently for every retry shape in the current implementation, particularly when only one unfinished step remains. Pass amended inputs explicitly on subsequent retries rather than assuming they became the saved defaults.

Laravel worker retry settings still govern individual queue jobs. Package-level `retry($runId)` rebuilds unfinished flow work and is distinct from `queue:retry`. Align worker timeouts and queue visibility/retry intervals with the time your AI calls can take.
