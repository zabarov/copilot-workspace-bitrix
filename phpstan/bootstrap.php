<?php
declare(strict_types=1);

// Минимальная загрузка окружения для PHPStan.
$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

$bitrixStub = __DIR__ . '/stubs/bitrix.stub.php';
if (is_file($bitrixStub)) {
    require_once $bitrixStub;
}

if (!class_exists('CDatabase')) {
    class CDatabase
    {
        /** @return object{Fetch: callable(): array<string,mixed>|false} */
        public function Query(string $sql)
        {
            return new class {
                /** @return array<string,mixed>|false */
                public function Fetch()
                {
                    return false;
                }
            };
        }

        public function ForSql(string $value, int $maxLength = 0): string
        {
            return $value;
        }
    }
}

if (!isset($GLOBALS['DB'])) {
    /** @var \CDatabase $DB */
    $GLOBALS['DB'] = new \CDatabase();
}
