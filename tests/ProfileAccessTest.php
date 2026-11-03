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

    public function testCompositionChoicesContainOnlyAuthorizedProfilesInCatalogueOrder(): void
    {
        $profiles = [2 => 'Second', 1 => 'First', 3 => 'Read only'];
        $rows = [
            ['id' => 1, 'allowed' => true, 'editable' => true],
            ['id' => 2, 'allowed' => false, 'editable' => true],
            ['id' => 3, 'allowed' => true, 'editable' => false],
        ];
        self::assertSame([1 => 'First', 3 => 'Read only'], ProfileAccess::compositionProfiles($profiles, $rows));
        self::assertSame([2 => 'Second', 3 => 'Read only'], ProfileAccess::compositionProfiles($profiles, $rows, ['2']));
        self::assertSame([3 => 'Read only'], ProfileAccess::compositionProfiles($profiles, $rows, []));
        self::assertSame([], ProfileAccess::compositionProfiles($profiles, []));
    }

    public function testSelectionOnlyChangesEditableProfilesAndKeepsOtherPermissions(): void
    {
        $rows = [
            ['id' => 1, 'editable' => true, 'allowed' => true],
            ['id' => 2, 'editable' => true, 'allowed' => false],
            ['id' => 3, 'editable' => false, 'allowed' => true],
        ];
        self::assertSame([1 => '0', 2 => '1'], ProfileAccess::selectionChanges(['2', '2'], $rows));
        self::assertSame([1 => '0'], ProfileAccess::selectionChanges([], $rows));
        self::assertSame([], ProfileAccess::selectionChanges(['1'], $rows));
    }

    public function testSelectionRejectsUnknownOrReadOnlyTargets(): void
    {
        $rows = [['id' => 1, 'editable' => true, 'allowed' => false]];
        foreach ([[99], [['nested']], ['malformed']] as $selected) {
            try {
                ProfileAccess::selectionChanges($selected, $rows);
                self::fail('Invalid selection accepted');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('cannot be modified', $exception->getMessage());
            }
        }
    }

    public function testSelectionRequiresNativeProfileUpdateRight(): void
    {
        \Session::$brandpulseRight = false;
        $this->expectException(RuntimeException::class);
        ProfileAccess::selectionChanges([], []);
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
