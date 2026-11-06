# API quick reference

[Documentation home](README.md)

## Flow entry points

| Call | Purpose |
| --- | --- |
| `new YourFlow($params = [], $runId = null)` | Allocate a run ID or refer to an existing run |
| `YourFlow::execute($params = [], $connection = null, $queue = null, $runId = null, $force = false)` | Construct, dispatch, and return the run ID |
| `$flow->dispatch($connection = null, $queue = null, $force = false)` | Dispatch unfinished work; returns `void` |
| `YourFlow::retry($runId, $params = [], $freshParams = false, $connection = null, $queue = null, $force = false)` | Resume using stored/supplied inputs; returns `void` |
| `$flow->runId` | Read the ID assigned during construction |

Prefer named arguments for queue and retry options. See [retry behavior](retries-and-idempotency.md).

## Define components

| Member | Where | Purpose |
| --- | --- | --- |
| `init(): void` | Flow, step | Register child classes |
| `label(): string` | All components | Required human-readable name |
| `getSystemPrompt(): string` | Flow | Required shared agent instructions |
| `getPlanPrompt(): string` | Step, task | Step guidance defaults empty; implement on tasks |
| `additionalTags(): array` | All components | Implement on flows; defaults empty on steps/tasks |
| `$shouldChain = true` | Step | Execute tasks sequentially |
| `handle(): void` | Task | Required application work |
| `$executeFinalizeCallback = true`, `finalize(): void` | Flow | Enable and define final callback |

Read inputs with protected `param($key, $default)` or public `params()`. [Workflow design](workflows.md) and [hooks](hooks-and-events.md) cover customization.

## Task helpers

| Method | Return and defaults |
| --- | --- |
| `generateText(string $prompt, bool $usePlan = false)` | String; plans off |
| `generateStructured(array $schema, ?string $prompt = null, bool $usePlan = true)` | Array; plans on |
| `generateStructuredFromFile(string $filePath, ?string $prompt = null, bool $usePlan = true)` | Array; plans on |
| `setStatus(bool $success, array $data = [])` | Select outcome and result; returns `void` |

Prompt helpers are available on all components: `promptFromFile($path, $variables = [])` and `replacePromptVariables($prompt, $variables = [])`, both returning strings. See [generation](ai-generation.md) and [replacers](prompts-and-replacers.md).

## Tracking

Use `IbraheemGhazi\LaravelAiFlow\Support\FlowProgressTracker`:

| Static method | Return |
| --- | --- |
| `status($component)` | Raw status or `null` |
| `getMeta($component)` | Metadata array |
| `getFinishedMetaWithinRun($source, $targetClass)` | Finished component metadata or `[]` |
| `inputParamsFor($runId, $flowClass)` | Saved inputs |
| `getRunInformation($runId)` | Tracked row objects with decoded metadata |
| `prune($days, $statuses = [FlowProgressTracker::STATUS_FINISHED])` | Deleted row count |

See [result lookup](sharing-results.md) and [progress](progress.md) for access rules and response shape.
