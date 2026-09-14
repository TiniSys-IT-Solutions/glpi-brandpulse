<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse\Tests;

use ArrayIterator;
use GlpiPlugin\Brandpulse\ProfileCounterStore;
use GlpiPlugin\Brandpulse\Config as BrandpulseConfig;
use GlpiPlugin\Brandpulse\Migrator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProfileCounterStore::class)]
final class ProfileCounterStoreTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['DB']);
        \Config::$values = [];
    }

    public function testSubmittedAssignmentsAreValidatedCompletedAndRenumbered(): void
    {
        $catalog = [
            ['key' => 'one'],
            ['key' => 'two'],
            ['key' => 'three'],
        ];
        $assignments = ProfileCounterStore::normalizeSubmittedAssignments([
            8 => ['counter_key' => 'two', 'is_active' => '1', 'is_mandatory' => '1'],
            9 => ['counter_key' => 'missing', 'is_active' => '1'],
            10 => ['counter_key' => 'one'],
            11 => ['counter_key' => 'two', 'is_active' => '1'],
        ], $catalog);

        self::assertSame(['two', 'one', 'three'], array_column($assignments, 'counter_key'));
        self::assertSame([0, 1, 2], array_column($assignments, 'position'));
        self::assertSame([true, false, false], array_column($assignments, 'is_active'));
        self::assertSame([true, false, false], array_column($assignments, 'is_mandatory'));
    }

    public function testInstallAndUninstallAreIdempotentAndSeedProfilesOnce(): void
    {
        $database = new ProfileCounterStoreFakeDatabase();
        $GLOBALS['DB'] = $database;
        $catalog = [
            ['key' => 'one', 'enabled' => true],
            ['key' => 'two', 'enabled' => false],
        ];

        self::assertTrue(ProfileCounterStore::install($catalog));
        self::assertTrue(ProfileCounterStore::install($catalog));
        self::assertCount(4, $database->rows);
        self::assertTrue(ProfileCounterStore::uninstall());
        self::assertTrue(ProfileCounterStore::uninstall());
        self::assertFalse($database->tableExists(ProfileCounterStore::TABLE));
    }

    public function testMigrationFromPreviousSchemaSeedsExistingProfiles(): void
    {
        $database = new ProfileCounterStoreFakeDatabase();
        $GLOBALS['DB'] = $database;
        \Config::$values[BrandpulseConfig::CONTEXT] = [
            BrandpulseConfig::SCHEMA_VERSION_KEY => '0.1.43',
        ];

        self::assertTrue(Migrator::migrate('0.1.44'));
        self::assertSame('0.1.44', BrandpulseConfig::schemaVersion());
        self::assertCount(count(BrandpulseConfig::defaultCounters()) * 2, $database->rows);
    }

    public function testNewCatalogEntryBecomesAvailableToExistingProfileWithoutBeingEnabled(): void
    {
        $database = new ProfileCounterStoreFakeDatabase();
        $GLOBALS['DB'] = $database;
        ProfileCounterStore::install([['key' => 'existing', 'enabled' => true]]);

        $assignments = ProfileCounterStore::assignmentsForProfile(1, [
            ['key' => 'existing', 'enabled' => true],
            ['key' => 'new', 'enabled' => true],
        ]);

        self::assertSame(['existing', 'new'], array_column($assignments, 'counter_key'));
        self::assertSame([true, false], array_column($assignments, 'is_active'));
    }

    public function testSaveUsesTheLimitSubmittedWithTheSameRequest(): void
    {
        $database = new ProfileCounterStoreFakeDatabase();
        $GLOBALS['DB'] = $database;
        $catalog = [];
        $submitted = [];
        for ($index = 0; $index < 8; ++$index) {
            $key = 'counter_' . $index;
            $catalog[] = ['key' => $key, 'enabled' => true];
            $submitted[] = ['counter_key' => $key, 'is_active' => true];
        }
        ProfileCounterStore::install($catalog);

        ProfileCounterStore::saveProfile(1, $submitted, $catalog, 10);

        self::assertCount(8, ProfileCounterStore::assignmentsForProfile(1, $catalog));
    }
}

final class ProfileCounterStoreFakeDatabase
{
    public array $rows = [];
    private bool $exists = false;

    public function tableExists(string $table): bool
    {
        return $this->exists;
    }

    public function doQuery(string $query): void
    {
        if (str_starts_with($query, 'CREATE TABLE')) {
            $this->exists = true;
        } elseif (str_starts_with($query, 'DROP TABLE')) {
            $this->exists = false;
            $this->rows = [];
        }
    }

    public function request(array $criteria): ArrayIterator
    {
        if (($criteria['FROM'] ?? '') === 'glpi_profiles') {
            return new ArrayIterator([['id' => 1], ['id' => 2]]);
        }

        $profileId = (int) ($criteria['WHERE']['profiles_id'] ?? 0);
        return new ArrayIterator(array_values(array_filter(
            $this->rows,
            static fn (array $row): bool => (int) $row['profiles_id'] === $profileId
        )));
    }

    public function insert(string $table, array $row): bool
    {
        $this->rows[] = $row;
        return true;
    }

    public function countElements(string $table, array $condition): int
    {
        return count(array_filter($this->rows, static function (array $row) use ($condition): bool {
            foreach ($condition as $key => $value) {
                if (($row[$key] ?? null) !== $value) {
                    return false;
                }
            }
            return true;
        }));
    }

    public function beginTransaction(): void
    {
    }

    public function commit(): void
    {
    }

    public function rollBack(): void
    {
    }

    public function delete(string $table, array $condition): bool
    {
        $this->rows = array_values(array_filter($this->rows, static function (array $row) use ($condition): bool {
            foreach ($condition as $key => $value) {
                if (($row[$key] ?? null) !== $value) {
                    return true;
                }
            }
            return false;
        }));
        return true;
    }
}
