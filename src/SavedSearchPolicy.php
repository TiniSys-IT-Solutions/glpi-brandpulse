<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse;

final class SavedSearchPolicy
{
    public static function canUse(array $fields, int $userId, bool $isVisible): bool
    {
        $searchType = class_exists(\SavedSearch::class) ? \SavedSearch::SEARCH : 1;
        if ((int) ($fields['type'] ?? $searchType) !== $searchType) {
            return false;
        }

        $isPrivate = (int) ($fields['is_private'] ?? 1) === 1;
        if ($isPrivate) {
            return (int) ($fields['users_id'] ?? 0) === $userId;
        }

        return $isVisible;
    }
}
