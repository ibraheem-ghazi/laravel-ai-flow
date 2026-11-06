# Laravel AI Flow documentation

Build multi-step AI features using Laravel queues. Define the work as a **flow**, group related work into **steps**, and implement each operation as a **task**. The package handles execution order, tracks progress, stores task results, and lets you resume unfinished work.

These guides are for application developers using `ibraheem-ghazi/laravel-ai-flow`. All PHP examples use the namespace `IbraheemGhazi\LaravelAiFlow`. Example application classes belong to your application; the package does not generate or bundle them.

## Start here

1. [Install and configure](installation.md) the package, queue, and AI provider.
2. [Build your first flow](getting-started.md) with a complete, minimal example.
3. [Design workflows](workflows.md) with parallel tasks, ordered dependencies, shared inputs, and finalization.

## What it provides

- **Flexible execution:** ordered steps with parallel or sequential tasks.
- **Reusable generation:** shared prompts, replacers, and structured output.
- **Connected tasks:** read finished results from the same run.
- **Progress and recovery:** track runs and retry unfinished work.
- **Application integration:** lifecycle hooks and queue-monitoring support.

## Topic guides

| Guide | Covers |
| --- | --- |
| [Prompts and replacers](prompts-and-replacers.md) | Prompt layers, files, placeholders, fallbacks |
| [AI generation](ai-generation.md) | Text, strict structured output, schemas, images |
| [Sharing results](sharing-results.md) | Store output and read it in dependent tasks |
| [Progress](progress.md) | Statuses, endpoint, retention |
| [Retries and idempotency](retries-and-idempotency.md) | Resume runs, duplicate-work protections, safe side effects |
| [Hooks and events](hooks-and-events.md) | Task callbacks and Laravel queue events |
| [Monitoring and failures](monitoring-and-failures.md) | Telescope, tags, failure markers, troubleshooting |

## A typical workflow

```text
Create catalog flow
  ├─ Research step: audience + product research run in parallel
  ├─ Writing step: outline → descriptions run sequentially
  └─ Publishing step: persist the generated catalog
```

Steps always run in registration order. Each step chooses whether its tasks run together or one after another. A task can make AI calls, save application records, call another service, or combine these operations.

The package provides orchestration and stored results. Your application owns business validation, authorization, persistence, and idempotent side effects. Read the [retry guarantees and limits](retries-and-idempotency.md) before adding writes or external actions.

## Looking for a specific call?

[API quick reference](reference.md) lists the main consumer methods and their defaults.
