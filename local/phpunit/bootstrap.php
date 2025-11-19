<?php
declare(strict_types=1);

$autoload = __DIR__ . '/../../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

// Добавьте инициализацию Bitrix окружения, если требуется.
