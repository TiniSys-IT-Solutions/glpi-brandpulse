<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse\Tests;

use GlpiPlugin\Brandpulse\Profile;
use GlpiPlugin\Brandpulse\ProfileAccess;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProfileAccessTest extends TestCase
{
    private object $db;

    protected function setUp(): void
    {
        \Session::$brandpulseRight = true;
        \Session::$reloads = 0;
        \Profile::$updates = [];
        \Profile::$failId = 0;
        $this->db = new class {
            public array $events = [];
            public function beginTransaction(): void { $this->events[] = 'begin'; }
            public function commit(): void { $this->events[] = 'commit'; }
            public function rollBack(): void { $this->events[] = 'rollback'; }
        };
        $GLOBALS['DB'] = $this->db;
    }

    protected function tearDown(): void
    {
        \Session::$brandpulseRight = true;
        \Profile::$failId = 0;
        unset($GLOBALS['DB']);
    }

    public function testCannotChangePermissionsWithoutNativeProfileUpdateRight(): void
    {
        \Session::$brandpulseRight = false;
        try {
            ProfileAccess::save([1 => '1']);
            self::fail('Missing native permission accepted');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('permission', $exception->getMessage());
        }
        self::assertSame([], \Profile::$updates);
        self::assertSame([], $this->db->events);
    }

    public function testValidatesAllTargetsBeforeChangingAnyRight(): void
    {
        foreach ([[1 => '1', 99 => '1'], [1 => '1', 2 => ['1']], [-1 => '1']] as $submitted) {
            try {
                ProfileAccess::save($submitted);
                self::fail('Invalid target accepted');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('cannot be modified', $exception->getMessage());
            }
            self::assertSame([], \Profile::$updates);
            self::assertSame([], $this->db->events);
        }
    }

    public function testUsesOnlyTheNativePulseRightAndReloadsCurrentSessionAfterCommit(): void
    {
        ProfileAccess::save([1 => '1', 2 => '0']);
        self::assertSame([
            ['id' => 1, '_' . Profile::RIGHT_VIEW_GENERAL_PULSE => [READ => 1]],
            ['id' => 2, '_' . Profile::RIGHT_VIEW_GENERAL_PULSE => [READ => 0]],
        ], \Profile::$updates);
        self::assertSame(['begin', 'commit'], $this->db->events);
        self::assertSame(1, \Session::$reloads);
    }

    public function testRollsBackWhenANativeProfileUpdateFails(): void
    {
        \Profile::$failId = 2;
        try {
            ProfileAccess::save([1 => '1', 2 => '1']);
            self::fail('Failed native write accepted');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Unable to save', $exception->getMessage());
        }
        self::assertSame(['begin', 'rollback'], $this->db->events);
        self::assertSame(0, \Session::$reloads);
    }
}
