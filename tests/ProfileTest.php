<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse\Tests;

use GlpiPlugin\Brandpulse\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Profile::class)]
final class ProfileTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['DB']);
        \ProfileRight::$added = [];
    }

    public function testNativeProfileCreationInitializesOnlyMissingRights(): void
    {
        $GLOBALS['DB'] = new class {
            public int $existing = 0;
            public function countElements(string $table, array $condition): int { return $this->existing; }
        };
        $profile = new \Profile();
        $profile->fields = ['id' => 7, 'interface' => 'central'];
        Profile::initializeProfileRights($profile);
        self::assertSame(READ, \ProfileRight::$added[0]['rights']);
        $profile->fields = ['id' => 8, 'interface' => 'helpdesk'];
        Profile::initializeProfileRights($profile);
        self::assertSame(0, \ProfileRight::$added[1]['rights']);
        $GLOBALS['DB']->existing = 1;
        Profile::initializeProfileRights($profile);
        self::assertCount(2, \ProfileRight::$added);
    }

    public function testStoredReadBitControlsProfilePulseVisibility(): void
    {
        $GLOBALS['DB'] = new class ([['rights' => READ]]) {
            public function __construct(private array $rows)
            {
            }

            public function request(array $query): iterable
            {
                return $this->rows;
            }
        };

        self::assertTrue(Profile::profileCanViewGeneralPulse(7));

        $GLOBALS['DB'] = new class ([['rights' => 0]]) {
            public function __construct(private array $rows)
            {
            }

            public function request(array $query): iterable
            {
                return $this->rows;
            }
        };

        self::assertFalse(Profile::profileCanViewGeneralPulse(7));
    }

    public function testMissingProfileOrStorageFailsClosed(): void
    {
        self::assertFalse(Profile::profileCanViewGeneralPulse(0));

        $GLOBALS['DB'] = new class {
            public function request(array $query): iterable
            {
                throw new \RuntimeException('Unavailable storage');
            }
        };

        self::assertFalse(Profile::profileCanViewGeneralPulse(7));
    }
}
