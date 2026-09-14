<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse\Tests;

use GlpiPlugin\Brandpulse\PulseResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PulseResolver::class)]
final class PulseResolverTest extends TestCase
{
    private array $catalog;

    protected function setUp(): void
    {
        $this->catalog = [
            ['key' => 'new', 'label' => 'New', 'enabled' => true, 'source_type' => 'preset'],
            ['key' => 'mine', 'label' => 'Mine', 'enabled' => true, 'source_type' => 'preset'],
            ['key' => 'infra', 'label' => 'Infra', 'enabled' => true, 'source_type' => 'saved_search'],
        ];
    }

    public function testProfilesResolveDifferentOrderedCounters(): void
    {
        $infra = PulseResolver::resolve($this->catalog, [
            ['counter_key' => 'infra', 'position' => 0, 'is_active' => true],
            ['counter_key' => 'mine', 'position' => 1, 'is_active' => true],
        ]);
        $support = PulseResolver::resolve($this->catalog, [
            ['counter_key' => 'mine', 'position' => 0, 'is_active' => true],
            ['counter_key' => 'new', 'position' => 1, 'is_active' => false],
        ]);

        self::assertSame(['infra', 'mine'], array_column($infra, 'key'));
        self::assertSame(['mine'], array_column($support, 'key'));
    }

    public function testDeletedReferencesAndDuplicatesAreIgnored(): void
    {
        $resolved = PulseResolver::resolve($this->catalog, [
            ['counter_key' => 'missing', 'position' => 0, 'is_active' => true],
            ['counter_key' => 'mine', 'position' => 1, 'is_active' => true],
            ['counter_key' => 'mine', 'position' => 2, 'is_active' => true],
        ]);

        self::assertSame(['mine'], array_column($resolved, 'key'));
    }

    public function testMandatoryFlagIsPropagatedForHybridResolution(): void
    {
        $resolved = PulseResolver::resolve($this->catalog, [[
            'counter_key' => 'new',
            'position' => 0,
            'is_active' => true,
            'is_mandatory' => true,
        ]]);

        self::assertTrue($resolved[0]['is_mandatory']);
    }

    public function testPersonalLayerOnlyAcceptsTwoUniqueSavedSearches(): void
    {
        $resolved = PulseResolver::resolve($this->catalog, [[
            'counter_key' => 'mine', 'position' => 0, 'is_active' => true,
        ]], [
            ['key' => 'mine', 'source_type' => 'saved_search'],
            ['key' => 'personal_1', 'source_type' => 'saved_search'],
            ['key' => 'personal_2', 'source_type' => 'saved_search'],
            ['key' => 'personal_3', 'source_type' => 'saved_search'],
            ['key' => 'personal_preset', 'source_type' => 'preset'],
        ]);

        self::assertSame(['mine', 'personal_1', 'personal_2'], array_column($resolved, 'key'));
    }

    public function testDefaultAssignmentsPreserveCatalogStateAndOrder(): void
    {
        $this->catalog[1]['enabled'] = false;
        $assignments = PulseResolver::defaultAssignments($this->catalog);

        self::assertSame(['new', 'mine', 'infra'], array_column($assignments, 'counter_key'));
        self::assertSame([true, false, true], array_column($assignments, 'is_active'));
    }

    public function testProfileLayerIsCappedAtSixCounters(): void
    {
        $catalog = [];
        $assignments = [];
        for ($index = 0; $index < 8; ++$index) {
            $key = 'counter_' . $index;
            $catalog[] = ['key' => $key, 'enabled' => true, 'source_type' => 'preset'];
            $assignments[] = ['counter_key' => $key, 'position' => $index, 'is_active' => true];
        }

        self::assertCount(6, PulseResolver::resolve($catalog, $assignments));
        self::assertCount(8, PulseResolver::resolve($catalog, $assignments, [], 8));
        self::assertCount(8, PulseResolver::resolve($catalog, $assignments, [], 10));
    }
}
