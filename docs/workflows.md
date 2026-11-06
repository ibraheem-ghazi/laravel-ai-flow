# Designing workflows

[Documentation home](README.md) · [API reference](reference.md)

## Flow, step, and task

| Component | Your responsibility | Useful for |
| --- | --- | --- |
| Flow | Register steps, provide shared AI instructions, name the operation | A complete user request such as creating a catalog |
| Step | Register related tasks and choose their execution order | Research, writing, or publishing |
| Task | Implement `handle()` and optionally store a result | One generation call, database operation, or service call |

Register children inside `protected function init(): void`. A flow accepts step classes; a step accepts task classes. Keep `init()` focused on registration. Read runtime parameters in prompts, tags, and task work.

Every component implements `label()`. Flows also implement `getSystemPrompt()` and `additionalTags()`; use `return [];` for no flow tags. Tasks implement `handle()` and `getPlanPrompt()`. Steps provide an empty plan by default.

## Parallel tasks or ordered tasks

Steps always run in the order you register them. A later step waits for the previous step's work to complete successfully.

Within a step, tasks run as a batch by default:

```php
protected function init(): void
{
    $this->register(HeadlineTask::class);
    $this->register(DescriptionTask::class);
    $this->register(BrandImageTask::class);
}
```

Use this for independent operations. Multiple workers can process those tasks together; registration order does not give them a dependency.

When one task needs another's result, declare this property on the step:

```php
public bool $shouldChain = true;
```

Now the tasks execute in registration order. Alternatively, put the producer in an earlier step. See [sharing results](sharing-results.md).

This is an ordered hierarchy rather than an arbitrary dependency graph. You can choose which classes to register using application logic in `init()`, but registered work is built when the flow is dispatched. Newly generated output does not automatically add new tasks to that run.

## Shared input parameters

```php
$flow = new CreateCatalogFlow([
    'tenant_id' => 42,
    'brand' => 'Northwind',
    'locale' => ['language' => 'English'],
]);
```

Inside any component:

```php
$tenantId = $this->param('tenant_id');
$language = $this->param('locale.language', 'English');
$allInputs = $this->params();
```

Use serializable input such as IDs, strings, and arrays because work crosses the queue boundary. The package persists flow inputs for later retries. Changing a local variable in one task does not update sibling tasks' inputs; share generated data through stored results instead.

## Run IDs and dispatch

Constructing a flow assigns a run ID before queueing:

```php
$flow = new CreateCatalogFlow($params);
$runId = $flow->runId;
$flow->dispatch(connection: 'redis', queue: 'ai');
```

`CreateCatalogFlow::execute($params)` constructs and dispatches in one call, then returns the run ID. A returned ID is a correlation key, not proof that new jobs were queued: live-run protection may cause dispatch to return without queueing.

To refer to an existing run, pass its ID as the second constructor argument. Reusing an ID participates in that run's completion and retry rules. Use a new ID for a new generation request.

## Finalization

Opt in on a flow when application work should run after all steps succeed:

```php
protected bool $executeFinalizeCallback = true;

public function finalize(): void
{
    // Update an application record or send a completion notification.
}
```

Finalization is disabled by default. The optional final job runs after the task work succeeds. It is separate from task progress: the flow can already report `finished` before `finalize()` completes.

A finalize error fails the final job without changing the successful tasks or dispatching the default flow-failure marker. Retrying a run whose tasks are all finished does not queue finalization again. Track application readiness separately if finalization is required for the feature to be usable.

## Flexibility beyond text

A task is regular application PHP. It can save generated records, generate an image, call another service, or perform deterministic work without an AI call. The package does not impose a content model or save output into application models for you.

Use flow, step, and task `additionalTags()` for monitoring context. Use task hooks for application callbacks. [Monitoring](monitoring-and-failures.md) and [hooks](hooks-and-events.md) cover both.
