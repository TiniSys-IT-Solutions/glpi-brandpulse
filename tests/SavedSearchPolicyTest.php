<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse\Tests;

use GlpiPlugin\Brandpulse\SavedSearchPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SavedSearchPolicy::class)]
final class SavedSearchPolicyTest extends TestCase
{
    public function testPublicSearchMustBeVisible(): void
    {
        $fields = ['type' => 1, 'is_private' => 0, 'users_id' => 9];

        self::assertTrue(SavedSearchPolicy::canUse($fields, 4, true));
        self::assertFalse(SavedSearchPolicy::canUse($fields, 4, false));
    }

    public function testPrivateSearchMustBelongToCurrentUser(): void
    {
        $fields = ['type' => 1, 'is_private' => 1, 'users_id' => 4];

        self::assertTrue(SavedSearchPolicy::canUse($fields, 4, true));
        self::assertFalse(SavedSearchPolicy::canUse($fields, 5, true));
    }

    public function testNonSearchSavedItemIsRejected(): void
    {
        self::assertFalse(SavedSearchPolicy::canUse([
            'type' => 3,
            'is_private' => 0,
        ], 4, true));
    }
}
