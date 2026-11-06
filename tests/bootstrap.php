<?php

$autoload = getenv('AI_FLOW_TEST_AUTOLOAD') ?: __DIR__.'/../vendor/autoload.php';
$loader = require $autoload;
$loader->addPsr4('IbraheemGhazi\\LaravelAiFlow\\', __DIR__.'/../src/');
