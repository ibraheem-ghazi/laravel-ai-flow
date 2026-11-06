<?php

namespace IbraheemGhazi\LaravelAiFlow\Concerns;

use IbraheemGhazi\LaravelAiFlow\Contracts\AiComponent;
use InvalidArgumentException;

/**
 * @template TComponent of AiComponent
 */
trait HasSubComponents
{
    /**
     * @var list<class-string<TComponent>>
     */
    private array $components = [];

    /**
     * @return list<class-string<TComponent>>
     */
    final public function components(): array
    {
        return $this->components;
    }

    /**
     * @param  class-string<TComponent>  $component
     */
    final protected function register(string $component): void
    {
        if (! is_a($component, $this->allowedComponentClass(), true)) {
            throw new InvalidArgumentException(sprintf(
                '%s component must be of type %s.',
                $component,
                $this->allowedComponentClass(),
            ));
        }

        $this->components[] = $component;
    }

    abstract protected function allowedComponentClass(): string;

    abstract public function mapComponentsToJob(): array;
}
