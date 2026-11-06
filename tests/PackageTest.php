<?php

namespace IbraheemGhazi\LaravelAiFlow\Tests;

use IbraheemGhazi\LaravelAiFlow\AiGenerationServiceProvider;
use IbraheemGhazi\LaravelAiFlow\Blueprints\Flow;
use IbraheemGhazi\LaravelAiFlow\Blueprints\Step;
use IbraheemGhazi\LaravelAiFlow\Blueprints\Task;
use IbraheemGhazi\LaravelAiFlow\Http\Controllers\AiFlowProgressTrackerController;
use IbraheemGhazi\LaravelAiFlow\Jobs\AiTaskJob;
use IbraheemGhazi\LaravelAiFlow\Support\FlowProgressTracker;
use IbraheemGhazi\LaravelAiFlow\Support\TemplateVariableReplacer;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Bus\PendingBatch;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\MigrationServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;

class PackageTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Application(dirname(__DIR__));
        $this->app->instance('config', new Repository([
            'database' => [
                'default' => 'sqlite',
                'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']],
                'migrations' => ['table' => 'migrations'],
            ],
            'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]],
            'queue' => ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']]],
        ]));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);

        foreach ([FilesystemServiceProvider::class, DatabaseServiceProvider::class, MigrationServiceProvider::class, CacheServiceProvider::class, BusServiceProvider::class, QueueServiceProvider::class, AiGenerationServiceProvider::class] as $provider) {
            $this->app->register($provider);
        }
        $this->app->boot();
        $migrator = $this->app->make('migrator');
        $this->app->make('migration.repository')->createRepository();
        $migrator->run($migrator->paths());
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        $this->app->flush();
        parent::tearDown();
    }

    public function test_provider_loads_migration_from_package_root(): void
    {
        self::assertTrue(Schema::hasTable('ai_flow_trackers'));
    }

    public function test_flow_runs_with_package_jobs_and_reports_stored_results(): void
    {
        $runId = ExampleFlow::execute(['name' => 'Academy']);
        $flow = new ExampleFlow([], $runId);

        self::assertSame('finished', FlowProgressTracker::status($flow));
        self::assertSame(['name' => 'Academy'], FlowProgressTracker::getFinishedMetaWithinRun($flow, ExampleTask::class));
        self::assertSame(['name' => 'Academy'], FlowProgressTracker::inputParamsFor($runId, ExampleFlow::class));
        $progress = (new AiFlowProgressTrackerController)($runId);
        self::assertSame('finished', $progress['status']);
        self::assertSame(100, $progress['percentage']);
        self::assertSame('Example task', $progress['steps'][0]['tasks'][0]['label']);
    }

    public function test_parallel_steps_build_package_task_batches(): void
    {
        $flow = new ParallelFlow(['name' => 'Academy']);
        $jobs = $flow->mapComponentsToJob();

        self::assertCount(1, $jobs);
        self::assertInstanceOf(PendingBatch::class, $jobs[0]);
        self::assertInstanceOf(AiTaskJob::class, $jobs[0]->jobs->first());
        self::assertSame('pending', FlowProgressTracker::status($flow));
    }

    public function test_retry_keeps_finished_task_results(): void
    {
        $runId = ExampleFlow::execute(['name' => 'Original']);
        ExampleFlow::retry($runId, ['name' => 'Replacement']);

        self::assertSame(['name' => 'Original'], FlowProgressTracker::getFinishedMetaWithinRun(new ExampleFlow([], $runId), ExampleTask::class));
    }

    public function test_templates_resolve_parameters_and_fallbacks(): void
    {
        self::assertSame('Academy / Default', TemplateVariableReplacer::replaceInString('{{ tenant.name }} / {{ missing | Default }}', ['tenant' => ['name' => 'Academy']]));
    }
}

class ExampleFlow extends Flow
{
    protected function init(): void
    {
        $this->register(ExampleStep::class);
    }

    public function label(): string
    {
        return 'Example flow';
    }

    public function additionalTags(): array
    {
        return [];
    }

    public function getSystemPrompt(): string
    {
        return 'Generate example content.';
    }
}

class ExampleStep extends Step
{
    public bool $shouldChain = true;

    protected function init(): void
    {
        $this->register(ExampleTask::class);
    }

    public function label(): string
    {
        return 'Example step';
    }
}

class ExampleTask extends Task
{
    public function label(): string
    {
        return 'Example task';
    }

    public function getPlanPrompt(): string
    {
        return '';
    }

    public function handle(): void
    {
        $this->setStatus(true, ['name' => $this->param('name')]);
    }
}

class ParallelFlow extends ExampleFlow
{
    protected function init(): void
    {
        $this->register(ParallelStep::class);
    }
}

class ParallelStep extends Step
{
    protected function init(): void
    {
        $this->register(ExampleTask::class);
    }

    public function label(): string
    {
        return 'Parallel step';
    }
}
