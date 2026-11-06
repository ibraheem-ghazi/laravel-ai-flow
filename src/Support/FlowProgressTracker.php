<?php

namespace IbraheemGhazi\LaravelAiFlow\Support;

use IbraheemGhazi\LaravelAiFlow\Blueprints\Flow;
use IbraheemGhazi\LaravelAiFlow\Blueprints\Step;
use IbraheemGhazi\LaravelAiFlow\Blueprints\Task;
use IbraheemGhazi\LaravelAiFlow\Contracts\AiComponent;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FlowProgressTracker
{
    const string STATUS_PENDING = 'pending';

    const string STATUS_RUNNING = 'running';

    const string STATUS_FINISHED = 'finished';

    const string STATUS_ERROR = 'error';

    public static function query(): Builder
    {
        return DB::table('ai_flow_trackers');
    }

    protected static function recognizeComponents(AiComponent $component): array
    {
        $flow = null;
        $step = null;
        $task = null;

        if ($component instanceof Task) {
            $task = $component;
            $step = $component->getParent();
            $flow = $component->getParent()?->getParent();
        } elseif ($component instanceof Step) {
            $step = $component;
            $flow = $component->getParent();
        } elseif ($component instanceof Flow) {
            $flow = $component;
        }

        return [$flow, $step, $task];
    }

    protected static function identifyComponents(AiComponent $component): array
    {
        [$flow, $step, $task] = self::recognizeComponents($component);

        return [
            $flow ? get_class($flow) : null,
            $step ? get_class($step) : null,
            $task ? get_class($task) : null,
        ];
    }

    /**
     * @return array{run_id: string, flow: string, step: string, task: string}|null
     */
    protected static function attributes(AiComponent $component): ?array
    {
        [$flow, $step, $task] = self::identifyComponents($component);
        $runId = self::runId($component);
        $flow = $flow ?? '';

        if ($runId === '' || $flow === '') {
            return null;
        }

        return [
            'run_id' => $runId,
            'flow' => $flow,
            'step' => $step ?? '',
            'task' => $task ?? '',
        ];
    }

    protected static function runId(AiComponent $component): string
    {
        [$flow] = static::recognizeComponents($component);

        return $flow?->runId ?? '';
    }

    public static function status(AiComponent $component): ?string
    {
        $attributes = self::attributes($component);

        if ($attributes === null) {
            return null;
        }

        $status = static::query()->where($attributes)->value('status');

        return is_string($status) ? $status : null;
    }

    public static function setPending(AiComponent $component): void
    {
        $attributes = self::attributes($component);

        if ($attributes === null) {
            return;
        }

        $now = now()->toDateTimeString();
        static::query()->upsert([[
            ...$attributes,
            'status' => self::STATUS_PENDING,
            'message' => null,
            'meta' => json_encode(
                $component instanceof Flow
                    ? ['inputParams' => $component->params()]
                    : []
            ),
            'started_at' => null,
            'failed_at' => null,
            'finished_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['run_id', 'flow', 'step', 'task'], ['updated_at']);
    }

    public static function setRunning(AiComponent $component): void
    {
        $attributes = self::attributes($component);

        if ($attributes === null) {
            return;
        }

        $now = now()->toDateTimeString();
        static::query()->upsert([[
            ...$attributes,
            'status' => self::STATUS_RUNNING,
            'message' => null,
            'meta' => '{}',
            'started_at' => $now,
            'failed_at' => null,
            'finished_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]],
            ['run_id', 'flow', 'step', 'task'],
            ['status', 'started_at', 'message', 'failed_at', 'finished_at', 'updated_at']
        );
    }

    public static function setFinished(AiComponent $component, array $payload = []): void
    {
        $attributes = self::attributes($component);

        if ($attributes === null) {
            return;
        }

        $now = now()->toDateTimeString();
        static::query()->upsert([[
            ...$attributes,
            'status' => self::STATUS_FINISHED,
            'message' => null,
            'meta' => json_encode($payload),
            'started_at' => null,
            'failed_at' => null,
            'finished_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]],
            ['run_id', 'flow', 'step', 'task'],
            ['status', 'meta', 'message', 'failed_at', 'finished_at', 'updated_at']
        );
    }

    public static function setFailed(AiComponent $component, ?string $message = null): void
    {
        $attributes = self::attributes($component);

        if ($attributes === null) {
            return;
        }

        $now = now()->toDateTimeString();
        static::query()->upsert([[
            ...$attributes,
            'status' => self::STATUS_ERROR,
            'message' => $message,
            'meta' => '{}',
            'started_at' => null,
            'failed_at' => $now,
            'finished_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]],
            ['run_id', 'flow', 'step', 'task'],
            ['status', 'message', 'failed_at', 'finished_at', 'updated_at']
        );
    }

    public static function finishIfRunning(AiComponent $component): void
    {
        $attributes = self::attributes($component);

        if ($attributes === null) {
            return;
        }

        static::query()
            ->where($attributes)
            ->where('status', self::STATUS_RUNNING)
            ->update([
                'status' => self::STATUS_FINISHED,
                'finished_at' => now()->toDateTimeString(),
                'message' => null,
                'failed_at' => null,
                'updated_at' => now()->toDateTimeString(),
            ]);
    }

    public static function finishIfIdle(Step|Flow $component): void
    {
        $attributes = self::attributes($component);

        if ($attributes === null) {
            return;
        }

        $open = [self::STATUS_PENDING, self::STATUS_RUNNING, self::STATUS_ERROR];

        static::query()
            ->where($attributes)
            ->where('status', self::STATUS_RUNNING)
            ->whereNotExists(function ($query) use ($attributes, $component, $open) {
                $children = static::query()
                    ->selectRaw('1')
                    ->where('run_id', $attributes['run_id'])
                    ->where('flow', $attributes['flow'])
                    ->whereIn('status', $open);

                if ($component instanceof Step) {
                    $children->where('step', $attributes['step'])->where('task', '!=', '');
                } else {
                    $children->where('task', '')->where('step', '!=', '');
                }

                $query->fromSub($children, 'open_nodes');
            })
            ->update([
                'status' => self::STATUS_FINISHED,
                'finished_at' => now()->toDateTimeString(),
                'message' => null,
                'failed_at' => null,
                'updated_at' => now()->toDateTimeString(),
            ]);
    }

    public static function getMeta(AiComponent $component): array
    {
        $attributes = self::attributes($component);

        if ($attributes === null) {
            return [];
        }

        return static::decodeMeta(static::query()->where($attributes)->value('meta'));
    }

    public static function inputParamsFor(string $runId, string $flowClass): array
    {
        $meta = static::decodeMeta(
            static::query()
                ->where('run_id', $runId)
                ->where('flow', $flowClass)
                ->where('step', '')
                ->where('task', '')
                ->value('meta')
        );

        $input = $meta['inputParams'] ?? [];

        return is_array($input) ? $input : [];
    }

    public static function syncInputParams(Flow $flow): void
    {
        $attributes = self::attributes($flow);

        if ($attributes === null) {
            return;
        }

        $meta = self::getMeta($flow);
        $next = $flow->params();

        if (($meta['inputParams'] ?? null) == $next) {
            return;
        }

        $meta['inputParams'] = $next;

        static::query()->where($attributes)->update([
            'meta' => json_encode($meta),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * @param  class-string<AiComponent>  $target
     */
    public static function getFinishedMetaWithinRun(AiComponent $source, string $target): array
    {
        $runId = self::runId($source);

        if ($runId === '') {
            return [];
        }

        $query = static::query()
            ->where('run_id', $runId)
            ->where('status', self::STATUS_FINISHED);

        if (is_a($target, Task::class, true)) {
            $query->where('task', $target);
        } elseif (is_a($target, Step::class, true)) {
            $query->where('step', $target)->where('task', '');
        } elseif (is_a($target, Flow::class, true)) {
            $query->where('flow', $target)->where('step', '')->where('task', '');
        } else {
            return [];
        }

        $meta = $query->limit(2)->pluck('meta');

        if ($meta->count() !== 1) {
            return [];
        }

        return self::decodeMeta($meta->first());
    }

    protected static function decodeMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (! is_string($meta) || $meta === '') {
            return [];
        }

        return json_decode($meta, true) ?? [];
    }

    public static function getRunInformation(string $runId): array
    {
        return static::query()
            ->where('run_id', $runId)
            ->orderBy('id')
            ->get(['flow', 'step', 'task', 'status', 'message', 'meta', 'started_at', 'failed_at', 'finished_at'])
            ->each(function (object $row): void {
                $row->meta = static::decodeMeta($row->meta);
            })
            ->all();
    }

    public static function prune(int $days, array $statuses = [self::STATUS_FINISHED]): int
    {
        $runIds = static::query()
            ->where('step', '')
            ->where('task', '')
            ->where('updated_at', '<=', now()->subDays($days))
            ->when(! empty($statuses), function ($query) use ($statuses) {
                $query->whereIn('status', $statuses);
            })
            ->pluck('run_id');

        if ($runIds->isEmpty()) {
            return 0;
        }

        return static::query()->whereIn('run_id', $runIds)->delete();
    }

    public static function reopen(AiComponent $component): void
    {
        $attributes = self::attributes($component);

        if ($attributes === null) {
            return;
        }

        $status = static::query()->where($attributes)->value('status');

        if ($status === self::STATUS_FINISHED) {
            return;
        }

        if (! is_string($status)) {
            self::setPending($component);

            return;
        }

        static::query()->where($attributes)->update([
            'status' => self::STATUS_PENDING,
            'message' => null,
            'started_at' => null,
            'failed_at' => null,
            'finished_at' => null,
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    public static function settle(Flow $flow): void
    {
        $attributes = self::attributes($flow);

        if ($attributes === null) {
            return;
        }

        $open = static::query()
            ->where('run_id', $attributes['run_id'])
            ->where('flow', $attributes['flow'])
            ->where('task', '!=', '')
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_RUNNING, self::STATUS_ERROR])
            ->exists();

        if ($open) {
            return;
        }

        $now = now()->toDateTimeString();

        static::query()
            ->where('run_id', $attributes['run_id'])
            ->where('flow', $attributes['flow'])
            ->where('task', '')
            ->where('status', '!=', self::STATUS_FINISHED)
            ->update([
                'status' => self::STATUS_FINISHED,
                'message' => null,
                'failed_at' => null,
                'finished_at' => $now,
                'updated_at' => $now,
            ]);
    }
}
