<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse;

use RuntimeException;
use Throwable;

final class ProfileCounterStore
{
    public const TABLE = 'glpi_plugin_brandpulse_profilecounters';

    public static function install(array $catalog): bool
    {
        global $DB;

        if (!isset($DB) || !method_exists($DB, 'tableExists')) {
            return false;
        }

        try {
            if (!$DB->tableExists(self::TABLE)) {
                $DB->doQuery(
                    'CREATE TABLE `' . self::TABLE . '` ('
                    . '`id` int unsigned NOT NULL AUTO_INCREMENT,'
                    . '`profiles_id` int unsigned NOT NULL DEFAULT 0,'
                    . '`counter_key` varchar(191) NOT NULL,'
                    . '`position` smallint unsigned NOT NULL DEFAULT 0,'
                    . '`is_active` tinyint NOT NULL DEFAULT 0,'
                    . '`is_mandatory` tinyint NOT NULL DEFAULT 0,'
                    . 'PRIMARY KEY (`id`),'
                    . 'UNIQUE KEY `unicity` (`profiles_id`, `counter_key`),'
                    . 'KEY `profile_order` (`profiles_id`, `position`)'
                    . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
            }

            self::cleanupOrphanProfiles();
            self::seedAllProfiles($catalog);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function uninstall(): bool
    {
        global $DB;

        if (!isset($DB) || !method_exists($DB, 'tableExists')) {
            return true;
        }

        try {
            if ($DB->tableExists(self::TABLE)) {
                $DB->doQuery('DROP TABLE `' . self::TABLE . '`');
            }
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function assignmentsForProfile(int $profileId, array $catalog): array
    {
        global $DB;

        if ($profileId <= 0 || !isset($DB) || !method_exists($DB, 'tableExists') || !$DB->tableExists(self::TABLE)) {
            return PulseResolver::defaultAssignments($catalog);
        }

        try {
            $rows = iterator_to_array($DB->request([
                'SELECT' => ['counter_key', 'position', 'is_active', 'is_mandatory'],
                'FROM' => self::TABLE,
                'WHERE' => ['profiles_id' => $profileId],
                'ORDER' => ['position ASC', 'id ASC'],
            ]));
        } catch (Throwable) {
            return PulseResolver::defaultAssignments($catalog);
        }

        if ($rows === []) {
            return PulseResolver::defaultAssignments($catalog);
        }

        $assignments = array_map(static fn (array $row): array => [
            'counter_key' => (string) $row['counter_key'],
            'position' => (int) $row['position'],
            'is_active' => (bool) (int) $row['is_active'],
            'is_mandatory' => (bool) (int) $row['is_mandatory'],
        ], $rows);

        // Keep stored order and choices, while making newly-created catalog
        // entries available to existing profiles without enabling them silently.
        return self::normalizeSubmittedAssignments($assignments, $catalog);
    }

    public static function resolvedForProfile(int $profileId, array $catalog): array
    {
        return PulseResolver::resolve(
            $catalog,
            self::assignmentsForProfile($profileId, $catalog),
            [],
            Config::values()['max_profile_counters']
        );
    }

    public static function normalizeSubmittedAssignments(array $submitted, array $catalog): array
    {
        $validKeys = [];
        foreach ($catalog as $definition) {
            $key = (string) ($definition['key'] ?? '');
            if ($key !== '') {
                $validKeys[$key] = true;
            }
        }

        $submittedByKey = [];
        foreach ($submitted as $position => $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = (string) ($row['counter_key'] ?? '');
            if ($key === '' || !isset($validKeys[$key]) || isset($submittedByKey[$key])) {
                continue;
            }
            $submittedByKey[$key] = [
                'counter_key' => $key,
                'position' => max(0, (int) $position),
                'is_active' => !empty($row['is_active']),
                'is_mandatory' => !empty($row['is_mandatory']) && !empty($row['is_active']),
            ];
        }

        $normalized = [];
        $nextPosition = 0;
        foreach ($submittedByKey as $row) {
            $row['position'] = $nextPosition++;
            $normalized[] = $row;
        }
        foreach (array_keys($validKeys) as $key) {
            if (!isset($submittedByKey[$key])) {
                $normalized[] = [
                    'counter_key' => $key,
                    'position' => $nextPosition++,
                    'is_active' => false,
                    'is_mandatory' => false,
                ];
            }
        }

        return $normalized;
    }

    public static function saveProfile(
        int $profileId,
        array $submitted,
        array $catalog,
        ?int $maxProfileCounters = null
    ): void
    {
        global $DB;

        if ($profileId <= 0 || !isset($DB) || !$DB->tableExists(self::TABLE)) {
            throw new RuntimeException('Pulse profile storage is unavailable.');
        }

        $assignments = self::normalizeSubmittedAssignments($submitted, $catalog);
        $activeCount = count(array_filter($assignments, static fn (array $row): bool => $row['is_active']));
        $maximum = Config::normalizeMaxProfileCounters(
            $maxProfileCounters ?? Config::values()['max_profile_counters']
        );
        if ($activeCount > $maximum) {
            throw new RuntimeException(sprintf(
                __('A profile can display at most %d Pulse counters.', 'brandpulse'),
                $maximum
            ));
        }

        $DB->beginTransaction();
        try {
            $DB->delete(self::TABLE, ['profiles_id' => $profileId]);
            foreach ($assignments as $assignment) {
                $DB->insert(self::TABLE, [
                    'profiles_id' => $profileId,
                    'counter_key' => $assignment['counter_key'],
                    'position' => $assignment['position'],
                    'is_active' => $assignment['is_active'] ? 1 : 0,
                    'is_mandatory' => $assignment['is_mandatory'] ? 1 : 0,
                ]);
            }
            $DB->commit();
        } catch (Throwable $exception) {
            $DB->rollBack();
            throw new RuntimeException('Unable to save the Pulse profile configuration.', 0, $exception);
        }
    }

    private static function seedAllProfiles(array $catalog): void
    {
        global $DB;

        if (!class_exists(\Profile::class)) {
            return;
        }

        $defaults = PulseResolver::defaultAssignments($catalog);
        foreach ($DB->request(['SELECT' => ['id'], 'FROM' => \Profile::getTable()]) as $profile) {
            $profileId = (int) $profile['id'];
            if ($profileId <= 0 || countElementsInTable(self::TABLE, ['profiles_id' => $profileId]) > 0) {
                continue;
            }
            foreach ($defaults as $assignment) {
                $DB->insert(self::TABLE, [
                    'profiles_id' => $profileId,
                    'counter_key' => $assignment['counter_key'],
                    'position' => $assignment['position'],
                    'is_active' => $assignment['is_active'] ? 1 : 0,
                    'is_mandatory' => 0,
                ]);
            }
        }
    }

    private static function cleanupOrphanProfiles(): void
    {
        global $DB;

        if (!class_exists(\Profile::class)) {
            return;
        }

        $profilesTable = \Profile::getTable();
        $DB->doQuery(
            'DELETE assignments FROM `' . self::TABLE . '` AS assignments '
            . 'LEFT JOIN `' . $profilesTable . '` AS profiles ON profiles.id = assignments.profiles_id '
            . 'WHERE profiles.id IS NULL'
        );
    }
}
