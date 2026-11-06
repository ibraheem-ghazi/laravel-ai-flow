# Sharing finished-task results

[Documentation home](README.md) · [Workflow ordering](workflows.md)

Use one task's completed output in a later task within the same run. For example, generate a catalog outline first, then write an introduction from that outline without repeating the first AI call.

## Store the producer's result

Inside an `OutlineTask`:

```php
$outline = $this->generateStructured($schema);
$this->setStatus(true, ['outline' => $outline]);
```

The payload is saved when execution records success. `setStatus()` selects the outcome and data; it does not immediately publish the payload during `handle()`. Use JSON-compatible data. A task that returns normally without calling `setStatus()` completes with an empty result.

## Read the result

Inside a dependent task:

```php
use App\AiFlows\Catalog\OutlineTask;
use IbraheemGhazi\LaravelAiFlow\Support\FlowProgressTracker;

public function handle(): void
{
    $result = FlowProgressTracker::getFinishedMetaWithinRun(
        $this,
        OutlineTask::class,
    );

    if (! array_key_exists('outline', $result)) {
        throw new \RuntimeException('The completed outline is unavailable.');
    }

    $text = $this->generateText(
        'Write an introduction from: '.json_encode($result['outline']),
        usePlan: true,
    );

    $this->setStatus(true, ['text' => $text]);
}
```

The first argument supplies the current run context; the second names the producer task. Outside a task, a flow instance with the existing run ID can supply that context.

## Establish the dependency

Put the producer in an earlier step, or register both tasks in a sequential step with `public bool $shouldChain = true;`. A sibling in a parallel batch may still be running when the consumer reads its result.

The lookup does not wait or create a dependency. It returns data only when exactly one finished row matches within the run. Missing, unfinished, or ambiguous results return `[]`. An intentionally empty result is also `[]`, so check expected fields.

Use distinct task classes for distinct outputs. Reusing a task class in several steps can make class-based lookup ambiguous.

## Other reads

Import `IbraheemGhazi\LaravelAiFlow\Support\FlowProgressTracker`:

| Call | Purpose |
| --- | --- |
| `getFinishedMetaWithinRun($source, TaskClass::class)` | Read a finished task's stored output in this run |
| `getMeta($component)` | Read metadata without a finished-status filter |
| `inputParamsFor($runId, FlowClass::class)` | Read saved flow inputs |
| `getRunInformation($runId)` | Read statuses, messages, timestamps, and decoded metadata for the run |

A step target reads its own metadata, not an aggregate of task results. A flow target reads flow metadata, including saved inputs. Read task classes for generated content.

Results remain available until run data is pruned. The tracker does not enforce ownership: associate run IDs with application records and authorize access before returning their results.
