<?php

namespace IbraheemGhazi\LaravelAiFlow\Contracts;

interface AiComponent
{
    public function label(): string;

    /**
     * @return array<string>
     */
    public function additionalTags(): array;
}
