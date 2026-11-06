<?php

namespace IbraheemGhazi\LaravelAiFlow\Contracts;

interface HasParentComponent
{
    public function getParent();

    public function setParent(AiComponent $parentComponent): void;
}
