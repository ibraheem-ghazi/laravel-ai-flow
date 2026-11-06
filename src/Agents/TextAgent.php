<?php

namespace IbraheemGhazi\LaravelAiFlow\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

class TextAgent implements Agent
{
    use Promptable;

    public function __construct(
        protected string $systemPrompt = '',
    ) {}

    public function instructions(): Stringable|string
    {
        return $this->systemPrompt;
    }
}
