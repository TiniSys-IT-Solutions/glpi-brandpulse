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
