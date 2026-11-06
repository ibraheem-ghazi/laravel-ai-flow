# Installation and configuration

[Documentation home](README.md) · Next: [Your first flow](getting-started.md)

## Requirements

- PHP 8.4 or later within the PHP 8 series.
- Laravel 13.17 or later within Laravel 13.
- Laravel AI 1.x, installed as a package dependency.
- A database for run progress and stored results.
- A shared cache store supporting atomic locks for deployed workers.
- A configured Laravel queue and running workers.

## Install

Once the package is available from your configured Composer repository:

```bash
composer require ibraheem-ghazi/laravel-ai-flow
```

## Laravel registration

Laravel package discovery registers `AiGenerationServiceProvider` automatically. It loads the package migration and connects progress tracking to Laravel queue events. Do not register the provider a second time.

If your application disables package discovery, register this provider in `bootstrap/providers.php`:

```php
IbraheemGhazi\LaravelAiFlow\AiGenerationServiceProvider::class,
```

## Database and queues

Run your application's migrations:

```bash
php artisan migrate
```

The package supplies `ai_flow_trackers`. Your application also needs Laravel's batch storage (`job_batches`) for parallel steps and failed-job storage for queue failure inspection. A database queue additionally needs the `jobs` table. Use your application's existing Laravel queue migrations when present.

Start a worker on the connection and queue used by your flows. For example, if Redis is configured:

```bash
php artisan queue:work redis --queue=ai
```

Dispatch to the same destination:

```php
$runId = CreateCatalogFlow::execute(
    params: ['tenant_id' => 42],
    connection: 'redis',
    queue: 'ai',
);
```

Pass both `connection` and `queue` when selecting a queue. The current package applies the queue name only when a connection argument is supplied. Parallel execution also requires enough workers to process tasks concurrently.

## AI provider configuration

Configure Laravel AI in the consuming application, including the default text provider, model, and credentials. The task helpers use Laravel AI's defaults. The flow input array does not automatically select a model or provider.

The package has no separate provider configuration file. Keep credentials in your application's provider configuration rather than flow parameters: parameters are stored with the run.

## Optional progress endpoint

The package provides a controller but does not register a public route. [Progress](progress.md) shows how to expose it with your application's access controls.