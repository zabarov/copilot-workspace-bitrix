<?php
declare(strict_types=1);

/**
 * Общие глобальные заглушки (используются только PHPStan-ом).
 */
namespace {
    if (!defined('LANGUAGE_ID')) {
        define('LANGUAGE_ID', 'ru');
    }

    if (!function_exists('htmlspecialcharsbx')) {
        function htmlspecialcharsbx(string $s, int $flags = \ENT_COMPAT, bool $doubleEncode = true): string
        {
            return $s;
        }
    }

    if (!function_exists('check_bitrix_sessid')) {
        function check_bitrix_sessid(): bool { return true; }
    }

    if (!function_exists('bitrix_sessid')) {
        function bitrix_sessid(): string { return 'stub'; }
    }

    if (!function_exists('LocalRedirect')) {
        function LocalRedirect(string $url): void { /* no-op for stubs */ }
    }

    if (!class_exists('CAdminMessage')) {
        class CAdminMessage
        {
            /**
             * @phpstan-param array{
             *   name?: string,
             *   message?: string,
             *   type?: string,
             *   detail?: string,
             *   html?: bool,
             *   MESSAGE?: string,
             *   TYPE?: string,
             *   DETAILS?: string,
             *   HTML?: bool
             * } $message
             */
            public static function ShowMessage(array $message): void {}
            public static function ShowNote(string $message): void {}
        }
    }

    if (!class_exists('CMain')) {
        class CMain
        {
            public function SetTitle(string $title): void {}
            public function RestartBuffer(): void {}
        }
    }

    if (!class_exists('CDBResult')) {
        class CDBResult
        {
            /** @return array<string,mixed>|false */
            public function Fetch()
            {
                return ['v' => '8.0'];
            }
        }
    }

    if (!class_exists('CDatabase')) {
        class CDatabase
        {
            /** @return CDBResult */
            public function Query(string $sql)
            {
                return new CDBResult();
            }

            public function ForSql(string $value, int $maxLength = 0): string { return $value; }
        }
    }
}

/**
 * Базовые D7 классы.
 */
namespace Bitrix\Main {
    if (!class_exists(\Bitrix\Main\Application::class)) {
        class Application
        {
            public static function getInstance(): self { return new self(); }
            public static function getConnection(): \Bitrix\Main\DB\Connection { return new \Bitrix\Main\DB\Connection(); }
            public function getContext(): object { return new \stdClass(); }
            public function end(): void {}
        }
    }

    if (!class_exists(\Bitrix\Main\Loader::class)) {
        class Loader
        {
            public static function includeModule(string $moduleId): bool { return true; }
        }
    }

    if (!class_exists(\Bitrix\Main\ModuleManager::class)) {
        class ModuleManager
        {
            public static function getVersion(string $moduleId): string { return '22.0.0'; }
            public static function isModuleInstalled(string $moduleId): bool { return true; }
        }
    }
}

namespace Bitrix\Main\Localization {
    if (!class_exists(\Bitrix\Main\Localization\Loc::class)) {
        class Loc
        {
            public static function loadMessages(string $file): void {}
            /** @param array<string, string|int>|null $replace */
            public static function getMessage(string $code, ?array $replace = null, string $lang = null): string
            {
                return $code;
            }
        }
    }
}

namespace Bitrix\Main\Config {
    if (!class_exists(\Bitrix\Main\Config\Option::class)) {
        class Option
        {
            public static function get(string $moduleId, string $name, string $default = ''): string { return $default; }
            public static function set(string $moduleId, string $name, string $value): void {}
            public static function delete(string $moduleId, array $filter = []): void {}
        }
    }
}

namespace Bitrix\Main\Engine {
    if (!class_exists(\Bitrix\Main\Engine\CurrentUser::class)) {
        class CurrentUser
        {
            public static function get(): ?self { return new self(); }
            public function isAdmin(): bool { return true; }
            public function getId(): ?int { return 1; }
        }
    }
}

namespace Bitrix\Main\Web {
    if (!class_exists(\Bitrix\Main\Web\Json::class)) {
        class Json
        {
            /** @param mixed $value */
            public static function encode($value): string
            {
                return is_string($value) ? $value : (string)json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            /** @return mixed */
            public static function decode(string $json)
            {
                return json_decode($json, true);
            }
        }
    }
}

/**
 * DB слой D7: минимально необходимое для наших адаптеров.
 */
namespace Bitrix\Main\DB {
    if (!class_exists(\Bitrix\Main\DB\SqlHelper::class)) {
        class SqlHelper
        {
            public function forSql(string $value, int $maxLength = 0): string { return $value; }
        }
    }

    if (!class_exists(\Bitrix\Main\DB\Connection::class)) {
        class Connection
        {
            public function query(string $sql): Result { return new Result(); }
            public function queryExecute(string $sql): void {}
            public function getInsertedId(): int { return 1; }
            public function getSqlHelper(): SqlHelper { return new SqlHelper(); }
            public function isTableExists(string $table): bool { return true; }
        }
    }

    if (!class_exists(\Bitrix\Main\DB\Result::class)) {
        class Result
        {
            /** @return array<string,mixed>|false */
            public function fetch()
            {
                return false;
            }
        }
    }
}

/**
 * Заглушка под Битрикс-админские сообщения.
 * Разрешаем два формата ключей: нижний (name/message/type/...) и «битриксовый» верхний (MESSAGE/TYPE/...).
 * Это нужно только для статанализа, в рантайме не используется.
 */
namespace {
    if (!class_exists('CAdminMessage')) {
        class CAdminMessage
        {
            /**
             * @phpstan-param array{
             *   // «строгий» вариант:
             *   name?: string,
             *   message?: string,
             *   type?: string,
             *   detail?: string,
             *   html?: bool,
             *   // «битриксовый» вариант:
             *   MESSAGE?: string,
             *   TYPE?: string,
             *   DETAILS?: string,
             *   HTML?: bool
             * } $message
             */
            public static function ShowMessage(array $message): void {}

            /**
             * @phpstan-param array{
             *   MESSAGE?: string,
             *   TYPE?: string,
             *   DETAILS?: string,
             *   HTML?: bool
             * } $params
             */
            public function __construct(array $params) {}

            public function Show(): void {}
        }
    } else {
        // Если класс уже объявлен в другом месте stubs — уточним только сигнатуру метода.
        // (Убедитесь, что ниже соответствует точному неймспейсу/классу из ваших stubs.)
        // Пример (раскомментируйте и адаптируйте, если нужно):
        /*
        class CAdminMessage
        {
            /** @phpstan-param array{name?: string, message?: string, type?: string, detail?: string, html?: bool, MESSAGE?: string, TYPE?: string, DETAILS?: string, HTML?: bool} $message *\/
            public static function ShowMessage(array $message): void {}
        }
        */
    }
}

namespace Bitrix\Main {
    /**
     * Минимальная заглушка контекста приложения.
     * Достаточно для вызовов Context::getCurrent()->getRequest()
     */
    class Context
    {
        /** @return static */
        public static function getCurrent()
        {
            return new static();
        }

        /** @return \Bitrix\Main\HttpRequest */
        public function getRequest()
        {
            return new \Bitrix\Main\HttpRequest();
        }

        public function getLanguage(): string
        {
            return 'ru';
        }
    }

    /**
     * Минимальная заглушка HTTP-запроса.
     * Добавляйте методы по мере жалоб PHPStan из вашего кода.
     */
    class HttpRequest
    {
        /**
         * Возвращает значение параметра из $_GET (или весь массив, если имя не передано).
         * @param string|null $name
         * @param mixed|null $default
         * @return mixed
         */
        public function getQuery($name = null, $default = null)
        {
            return $name === null ? [] : $default;
        }

        /**
         * Универсальный доступ к параметрам запроса (GET/POST/...), как в Битриксе.
         * @param string|null $name
         * @param mixed|null $default
         * @return mixed
         */
        public function get($name = null, $default = null)
        {
            return $name === null ? [] : $default;
        }
    }
}
