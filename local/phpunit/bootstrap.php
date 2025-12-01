<?php
declare(strict_types=1);

// Базовый bootstrap для PHPUnit: подключает composer autoload и битрикс-стабы при наличии.
$autoload = dirname(__DIR__, 1) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

$bitrixStub = dirname(__DIR__, 1) . '/phpstan/stubs/bitrix.stub.php';
if (is_file($bitrixStub)) {
    require_once $bitrixStub;
}

// Здесь можно инициализировать окружение тестов (стабы БД, глобальные переменные, контейнеры).
