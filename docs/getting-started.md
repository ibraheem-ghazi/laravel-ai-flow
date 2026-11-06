# Your first flow

[Documentation home](README.md) · Previous: [Installation](installation.md) · Next: [Workflow design](workflows.md)

This example generates a catalog introduction, stores the generated text as a task result, and gives the caller a run ID for progress tracking. Create the following three files in your application after completing installation.

## 1. Define the flow

`app/AiFlows/Catalog/CreateCatalogFlow.php`:

```php
<?php

namespace App\AiFlows\Catalog;

use IbraheemGhazi\LaravelAiFlow\Blueprints\Flow;

class CreateCatalogFlow extends Flow
{
    protected function init(): void
    {
        $this->register(ContentStep::class);
    }

    public function label(): string
    {
        return 'Create catalog';
    }

    public function additionalTags(): array
    {
        return ['tenant:'.$this->param('tenant_id')];
    }

    public function getSystemPrompt(): string
    {
        return $this->replacePromptVariables(
            'Write clear catalog content for {{ brand | our store }}.'
        );
    }
}
```

The flow receives input once and makes it available to all its steps and tasks. Its system prompt supplies the shared AI instructions.

## 2. Register a task in a step

`app/AiFlows/Catalog/ContentStep.php`:

```php
<?php

namespace App\AiFlows\Catalog;

use IbraheemGhazi\LaravelAiFlow\Blueprints\Step;

class ContentStep extends Step
{
    protected function init(): void
    {
        $this->register(IntroductionTask::class);
    }

    public function label(): string
    {
        return 'Content';
    }

    public function getPlanPrompt(): string
    {
        return 'Use a friendly tone. Return plain text.';
    }
}
```

## 3. Implement the task

`app/AiFlows/Catalog/IntroductionTask.php`:

```php
<?php

namespace App\AiFlows\Catalog;

use IbraheemGhazi\LaravelAiFlow\Blueprints\Task;

class IntroductionTask extends Task
{
    public function label(): string
    {
        return 'Introduction';
    }

    public function getPlanPrompt(): string
    {
        return 'Write a short introduction to the catalog.';
    }

    public function handle(): void
    {
        $text = $this->generateText(
            'Products: '.$this->param('products'),
            usePlan: true,
        );

        $this->setStatus(true, ['text' => $text]);
    }
}
```

Implement `getPlanPrompt()` on every task, returning an empty string when no task-specific plan is needed. `generateText()` includes step and task plans only when `usePlan: true` is passed.

## 4. Dispatch the flow

From your controller or application service:

```php
use App\AiFlows\Catalog\CreateCatalogFlow;

$runId = CreateCatalogFlow::execute(
    params: [
        'tenant_id' => 42,
        'brand' => 'Northwind',
        'products' => 'Notebooks, pens, and desk accessories',
    ],
    connection: 'redis',
    queue: 'ai',
);
```

Save `$runId` on the application record that owns this operation, or return it to the client. With an asynchronous queue, this call queues the work; it does not return the generated content. The worker performs the AI call later.

## 5. Read the result

After progress reports the flow as finished:

```php
use App\AiFlows\Catalog\IntroductionTask;
use IbraheemGhazi\LaravelAiFlow\Support\FlowProgressTracker;

$flow = new CreateCatalogFlow([], $runId);
$result = FlowProgressTracker::getFinishedMetaWithinRun(
    $flow,
    IntroductionTask::class,
);

$text = $result['text'] ?? null;
```

The result is persisted with the run. It is not automatically saved to your catalog model. Add your own application persistence in a task when needed, and make that write safe to repeat.

See [progress reporting](progress.md), [dependent task results](sharing-results.md), and [retrying a run](retries-and-idempotency.md) for the next steps.
