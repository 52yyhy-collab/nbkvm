<?php

declare(strict_types=1);
require dirname(__DIR__) . '/src/Support/helpers.php';
require dirname(__DIR__) . '/src/Support/Autoload.php';
Nbkvm\Support\Autoload::register();
(new Nbkvm\Services\SchemaService())->ensure();
$result = (new Nbkvm\Services\ExpirationService())->process();
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
