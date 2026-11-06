<?php

namespace IbraheemGhazi\LaravelAiFlow\Blueprints;

use IbraheemGhazi\LaravelAiFlow\Concerns\HasPromptsActions;
use IbraheemGhazi\LaravelAiFlow\Contracts\AiComponent;

abstract class BaseComponent implements AiComponent
{
    use HasPromptsActions;

    public function __construct(private readonly array $params = [])
    {
        $this->init();
    }

    /**
     * Used by children of subclasses, to register components
     */
    abstract protected function init(): void;

    final public function params(): array
    {
        return $this->params;
    }

    final protected function param(?string $key = null, mixed $default = null): mixed
    {
        return data_get($this->params, $key, $default);
    }
}
