# Laravel AI Flow

Composable AI workflows for Laravel. Organize work into ordered steps, run independent tasks in parallel, and resume unfinished work with persisted progress and results.

## Features

- **Flexible workflows:** ordered steps, parallel or sequential tasks, and application lifecycle hooks.
- **Reusable AI generation:** shared prompts, variable replacers, text, and strict structured output.
- **Shared results:** later tasks can read completed output from the same run.
- **Progress and recovery:** run tracking, retry support, and duplicate-work protections.
- **Queue visibility:** descriptive jobs, monitoring tags, and flow-failure markers.

## Get started

Requires PHP 8.4+, Laravel 13.17+, and Laravel AI 1.x.

```bash
composer require ibraheem-ghazi/laravel-ai-flow
php artisan migrate
```

Laravel discovers the provider automatically. Configure your AI provider and queue workers in your application.

**[Read the documentation](docs/README.md)** for installation options and a complete product-catalog example.

- [Installation](docs/installation.md)
- [Your first flow](docs/getting-started.md)
- [Workflow design](docs/workflows.md)
- [Prompts and replacers](docs/prompts-and-replacers.md)
- [AI generation](docs/ai-generation.md)
- [Sharing results](docs/sharing-results.md)
- [Progress](docs/progress.md)
- [Retries and idempotency](docs/retries-and-idempotency.md)
- [Hooks and events](docs/hooks-and-events.md)
- [Monitoring and failures](docs/monitoring-and-failures.md)
- [API reference](docs/reference.md)

## License

[MIT](LICENSE).
