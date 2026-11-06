<?php

namespace IbraheemGhazi\LaravelAiFlow;


use IbraheemGhazi\LaravelAiFlow\Support\EventSubscriber;
use Illuminate\Support\ServiceProvider;

class AiGenerationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    public function boot(): void
    {
        EventSubscriber::subscribe();
    }
    
    
}
