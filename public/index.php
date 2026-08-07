<?php

declare(strict_types=1);

use App\Core\Kernel;

require_once dirname(__DIR__)
    . '/vendor/autoload.php';

$services =
    require dirname(__DIR__)
    . '/config/http.php';

/** @var Kernel $kernel */
$kernel =
    $services['kernel'];

$kernel->handle();