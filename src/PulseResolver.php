<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse;

final class PulseResolver
{
    public const DEFAULT_MAX_PROFILE_COUNTERS = 6;
    public const MIN_PROFILE_COUNTERS = 6;
    public const MAX_PROFILE_COUNTERS = 10;
    public const MAX_PERSONAL_COUNTERS = 2;

    public static function defaultAssignments(array $catalog): array
    {
        $assignments = [];
        $position = 0;

        foreach ($catalog as $definition) {
            $key = (string) ($definition['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $assignments[] = [
                'counter_key' => $key,
                'position' => $position++,
                'is_active' => !empty($definition['enabled']),
                'is_mandatory' => false,
            ];
        }

        return $assignments;
    }

    public static function resolve(
        array $catalog,
        array $profileAssignments,
        array $personalDefinitions = [],
        int $maxProfileCounters = self::DEFAULT_MAX_PROFILE_COUNTERS
    ): array
    {
        $maxProfileCounters = max(self::MIN_PROFILE_COUNTERS, min(self::MAX_PROFILE_COUNTERS, $maxProfileCounters));
        $catalogByKey = [];
        foreach ($catalog as $definition) {
            $key = (string) ($definition['key'] ?? '');
            if ($key !== '' && !isset($catalogByKey[$key])) {
                $catalogByKey[$key] = $definition;
            }
        }

        usort($profileAssignments, static function (array $left, array $right): int {
            return ((int) ($left['position'] ?? 0)) <=> ((int) ($right['position'] ?? 0));
        });

        $resolved = [];
        $seen = [];
        foreach ($profileAssignments as $assignment) {
            $key = (string) ($assignment['counter_key'] ?? '');
            if (empty($assignment['is_active']) || isset($seen[$key]) || !isset($catalogByKey[$key])) {
                continue;
            }

            $definition = $catalogByKey[$key];
            $definition['enabled'] = true;
            $definition['is_mandatory'] = !empty($assignment['is_mandatory']);
            $resolved[] = $definition;
            $seen[$key] = true;

            if (count($resolved) >= $maxProfileCounters) {
                break;
            }
        }

        $personalCount = 0;
        foreach ($personalDefinitions as $definition) {
            $key = (string) ($definition['key'] ?? '');
            if (
                $personalCount >= self::MAX_PERSONAL_COUNTERS
                || $key === ''
                || isset($seen[$key])
                || ($definition['source_type'] ?? '') !== 'saved_search'
            ) {
                continue;
            }

            $definition['enabled'] = true;
            $definition['is_mandatory'] = false;
            $resolved[] = $definition;
            $seen[$key] = true;
            ++$personalCount;
        }

        return $resolved;
    }
}
