<?php

declare(strict_types=1);

if (!defined('READ')) {
    define('READ', 1);
}

if (!function_exists('__')) {
    function __(string $message, ?string $domain = null): string
    {
        return $message;
    }
}

if (!class_exists('Profile')) {
    class Profile
    {
        public static function getTable(): string
        {
            return 'glpi_profiles';
        }
    }
}

if (!class_exists('SavedSearch')) {
    class SavedSearch
    {
        public const SEARCH = 1;
    }
}

if (!class_exists('Config')) {
    class Config
    {
        public static array $values = [];

        public static function getConfigurationValues(string $context): array
        {
            return self::$values[$context] ?? [];
        }

        public static function setConfigurationValues(string $context, array $values): void
        {
            self::$values[$context] = array_replace(self::$values[$context] ?? [], $values);
        }

        public static function deleteConfigurationValues(string $context, array $names = []): void
        {
            if ($names === []) {
                unset(self::$values[$context]);
                return;
            }
            foreach ($names as $name) {
                unset(self::$values[$context][$name]);
            }
        }
    }
}

if (!class_exists('Session')) {
    class Session
    {
        public static bool $brandpulseRight = true;

        public static function haveRight(string $right, int $level): bool
        {
            return self::$brandpulseRight;
        }
    }
}

if (!function_exists('countElementsInTable')) {
    function countElementsInTable(string $table, array $condition = []): int
    {
        global $DB;

        return isset($DB) && method_exists($DB, 'countElements')
            ? $DB->countElements($table, $condition)
            : 0;
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';
