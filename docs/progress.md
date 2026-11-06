# Progress and retention

[Documentation home](README.md) · [Monitoring](monitoring-and-failures.md)

Persisted progress lets your application show the active phase, completed task count, and whether work needs attention.

## Register an endpoint

```php
use IbraheemGhazi\LaravelAiFlow\Http\Controllers\AiFlowProgressTrackerController;
use Illuminate\Support\Facades\Route;

Route::get('/ai-flows/progress/{runId}', AiFlowProgressTrackerController::class)
    ->middleware('auth');
```

The package provides the controller but registers no public route. Authentication identifies the caller; also authorize ownership of the requested run in application middleware or an application endpoint. The controller does not scope access to a user or tenant.

Poll with the run ID returned by dispatch. With one of two tasks complete, a response could be:

```json
{
    "status": "running",
    "percentage": 50,
    "current_step": "Writing",
    "steps": [
        {
            "label": "Research",
            "status": "finished",
            "tasks": [{ "label": "Outline", "status": "finished" }]
        },
        {
            "label": "Writing",
            "status": "running",
            "tasks": [{ "label": "Introduction", "status": "running" }]
        }
    ]
}
```

Stop normal polling at top-level `finished` or `failed`. An unknown run returns `status: null`, `percentage: 0`, `current_step: null`, and `steps: []`, rather than an HTTP 404 by default.

Registering `{runId?}` allows the controller's latest-run lookup when no ID is supplied. That lookup is application-wide, not limited to the caller. Prefer explicit IDs for user-facing screens.

## Status and percentage

| Field | Meaning |
| --- | --- |
| `status` | Flow state; internal `error` is exposed here as `failed` |
| `percentage` | Finished tasks divided by total tracked tasks, rounded to an integer |
| `current_step` | First running or errored step, otherwise first pending step, or `null` |
| `steps` | Step and task labels with their raw statuses |

Raw states are `pending`, `running`, `finished`, and `error`. Nested step/task states keep `error`; only the top-level response maps it to `failed`.

Percentage measures completed task count, not time remaining. Finalization is excluded: a flow can report `finished` before `finalize()` completes.

Labels are read from fresh component instances. Make `label()` usable without input parameters or an attached parent.

## Access from PHP

```php
use IbraheemGhazi\LaravelAiFlow\Support\FlowProgressTracker;

$status = FlowProgressTracker::status($flow);
$rows = FlowProgressTracker::getRunInformation($runId);
```

`status()` returns a raw state or `null`. Run information includes component class names, failure messages, decoded metadata, and timestamps. The default HTTP response omits failure details and result payloads.

You can extend the controller for an application-specific response. Its protected `getFlowInfo(string $runId): array` and `getResponseShape(?string $status, array $steps): array` methods allow custom data access and response shaping.

## Retention

```php
FlowProgressTracker::prune(days: 30);

FlowProgressTracker::prune(days: 14, statuses: [
    FlowProgressTracker::STATUS_FINISHED,
    FlowProgressTracker::STATUS_ERROR,
]);
```

Pruning selects runs by the flow record's last update and deletes all tracker rows in those runs. Only finished runs are selected by default. `statuses: []` removes the status filter. The return value counts deleted rows, not runs.

Schedule pruning in your application if needed; the package does not schedule it. Removed history includes stored inputs, generated results, and completion records needed for recovery. Pruning does not cancel queued work or remove application records or generated assets.
