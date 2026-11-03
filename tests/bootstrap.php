<?php

declare(strict_types=1);

if (!defined('READ')) {
    define('READ', 1);
}

if (!defined('UPDATE')) {
    define('UPDATE', 2);
}

if (!function_exists('__')) {
    function __(string $message, ?string $domain = null): string
    {
        return $message;
    }
}

if (!class_exists('CommonDBTM')) {
    class CommonDBTM
    {
        public array $fields = [];
        public function getID(): int { return (int) ($this->fields['id'] ?? 0); }
    }
}

if (!class_exists('Profile')) {
    class Profile extends CommonDBTM
    {
        public static array $allowedIds = [1, 2];
        public static array $updates = [];
        public static int $failId = 0;
        public function can(int $id, int $right): bool { return in_array($id, self::$allowedIds, true); }
        public function update(array $input): bool {
            if (($input['id'] ?? 0) === self::$failId) return false;
            self::$updates[] = $input;
            return true;
        }
        public static function getTable(): string
        {
            return 'glpi_profiles';
        }
    }
}

if (!class_exists('ProfileRight')) {
    class ProfileRight
    {
        public static array $added = [];
        public function add(array $input): int { self::$added[] = $input; return count(self::$added); }
        public static function getTable(): string
        {
            return 'glpi_profilerights';
        }
    }
}

if (!class_exists('SavedSearch')) {
    class SavedSearch
    {
        public const SEARCH = 1;
    }
}

if (!class_exists('NetworkPort')) {
    class NetworkPort
    {
        public static function getTable(): string
        {
            return 'glpi_networkports';
        }
    }
}

if (!class_exists('NetworkName')) {
    class NetworkName
    {
        public static function getTable(): string
        {
            return 'glpi_networknames';
        }
    }
}

if (!class_exists('IPAddress')) {
    class IPAddress
    {
        public static function getTable(): string
        {
            return 'glpi_ipaddresses';
        }
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
        public static int $reloads = 0;
        public static function reloadCurrentProfile(): void { ++self::$reloads; }
        public static string $interface = 'central';

        public static function haveRight(string $right, int $level): bool
        {
            return self::$brandpulseRight;
        }

        public static function getCurrentInterface(): string
        {
            return self::$interface;
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

if (!class_exists('Ticket')) {
    class Ticket
    {
        public static bool $readAllowed = true;
        public static function canView(): bool { return self::$readAllowed; }
        public static function getSearchURL(): string { return '/glpi/front/ticket.php'; }
    }
}

if (!class_exists('Search')) {
    class Search
    {
        public static array $viewOptions = [
            'common' => 'Tickets',
            2 => ['name' => 'ID', 'field' => 'id'],
            1 => ['name' => 'Title', 'field' => 'name'],
            12 => ['name' => 'Status', 'field' => 'status'],
            7 => ['name' => 'Category', 'field' => 'itilcategories_id'],
            19 => ['name' => 'Last update', 'field' => 'date_mod'],
            21 => ['name' => 'Description', 'field' => 'content', 'nosort' => true],
            999 => ['name' => 'Hidden', 'field' => 'hidden', 'nodisplay' => true],
        ];
        public static function getCleanedOptions(string $itemtype, int $right): array { return self::$viewOptions; }
        public static function addDefaultToView(string $itemtype, array $params): array { return [2, 1]; }
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';
