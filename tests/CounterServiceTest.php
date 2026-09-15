<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse\Tests;

use ArrayIterator;
use GlpiPlugin\Brandpulse\Config;
use GlpiPlugin\Brandpulse\CounterService;
use GlpiPlugin\Brandpulse\ProfileCounterStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Session;

#[CoversClass(CounterService::class)]
final class CounterServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['glpiactiveprofile'], $_SESSION['glpiactiveentities'], $_SESSION['glpiactive_entity_recursive']);
        unset($GLOBALS['DB']);
        \Config::$values = [];
        Session::$brandpulseRight = true;
        Session::$interface = 'central';
    }

    public function testGlobalProfileRightStopsPulseBeforeAnyCounterResolution(): void
    {
        $_SESSION['glpiactiveprofile']['id'] = 4;
        Session::$brandpulseRight = false;

        $payload = (new CounterService(10))->getPayload();

        self::assertFalse($payload['enabled']);
        self::assertSame([], $payload['counters']);
    }

    public function testPayloadUsesOnlyTheActiveProfileAssignment(): void
    {
        $catalog = array_slice(Config::defaultCounters(), 0, 2);
        \Config::$values[Config::CONTEXT] = [
            'counters_json' => json_encode($catalog, JSON_THROW_ON_ERROR),
            'pulse_mode' => 'profile',
        ];
        $database = new ActiveProfileFakeDatabase([
            ['profiles_id' => 4, 'counter_key' => $catalog[0]['key'], 'position' => 0, 'is_active' => 1, 'is_mandatory' => 0],
            ['profiles_id' => 5, 'counter_key' => $catalog[1]['key'], 'position' => 0, 'is_active' => 1, 'is_mandatory' => 0],
        ]);
        $GLOBALS['DB'] = $database;

        $_SESSION['glpiactiveprofile']['id'] = 4;
        $profileFour = (new CounterService(10))->getPayload();
        $_SESSION['glpiactiveprofile']['id'] = 5;
        $profileFive = (new CounterService(10))->getPayload();

        self::assertSame([$catalog[0]['key']], array_column($profileFour['counters'], 'key'));
        self::assertSame([$catalog[1]['key']], array_column($profileFive['counters'], 'key'));
    }

    public function testGlobalModeUsesOneCompositionAndCanExcludeSelfService(): void
    {
        $catalog = array_slice(Config::defaultCounters(), 0, 2);
        \Config::$values[Config::CONTEXT] = [
            'counters_json' => json_encode($catalog, JSON_THROW_ON_ERROR),
            'pulse_mode' => 'global',
            'global_selfservice_enabled' => '0',
        ];
        $_SESSION['glpiactiveprofile']['id'] = 4;

        $central = (new CounterService(10))->getPayload();
        Session::$interface = 'helpdesk';
        $selfservice = (new CounterService(10))->getPayload();
        \Config::$values[Config::CONTEXT]['global_selfservice_enabled'] = '1';
        $allowedSelfservice = (new CounterService(10))->getPayload();

        self::assertSame(array_column($catalog, 'key'), array_column($central['counters'], 'key'));
        self::assertFalse($selfservice['enabled']);
        self::assertSame(array_column($catalog, 'key'), array_column($allowedSelfservice['counters'], 'key'));
    }

    public function testCacheKeyIsStableForAnIdenticalContext(): void
    {
        $_SESSION['glpiactiveprofile']['id'] = 4;
        $_SESSION['glpiactiveentities'] = [2, 7];
        $_SESSION['glpiactive_entity_recursive'] = 1;
        $definitions = [['key' => 'my_tasks', 'enabled' => true]];

        self::assertSame(
            $this->cacheKey(new CounterService(10), $definitions),
            $this->cacheKey(new CounterService(10), $definitions)
        );
    }

    public function testCacheKeySeparatesUsersProfilesEntitiesAndDefinitions(): void
    {
        $_SESSION['glpiactiveprofile']['id'] = 4;
        $_SESSION['glpiactiveentities'] = [2];
        $_SESSION['glpiactive_entity_recursive'] = 0;
        $definitions = [['key' => 'my_tasks', 'enabled' => true]];
        $baseline = $this->cacheKey(new CounterService(10), $definitions);

        self::assertNotSame($baseline, $this->cacheKey(new CounterService(11), $definitions));

        $_SESSION['glpiactiveprofile']['id'] = 5;
        self::assertNotSame($baseline, $this->cacheKey(new CounterService(10), $definitions));

        $_SESSION['glpiactiveprofile']['id'] = 4;
        $_SESSION['glpiactiveentities'] = [3];
        self::assertNotSame($baseline, $this->cacheKey(new CounterService(10), $definitions));

        $_SESSION['glpiactiveentities'] = [2];
        self::assertNotSame(
            $baseline,
            $this->cacheKey(new CounterService(10), [['key' => 'my_open_tickets', 'enabled' => true]])
        );
    }

    private function cacheKey(CounterService $service, array $definitions): string
    {
        $method = new ReflectionMethod($service, 'counterCacheKey');

        return $method->invoke($service, $definitions);
    }
}

final class ActiveProfileFakeDatabase
{
    public function __construct(private readonly array $rows)
    {
    }

    public function tableExists(string $table): bool
    {
        return $table === ProfileCounterStore::TABLE;
    }

    public function request(array $criteria): ArrayIterator
    {
        $profileId = (int) ($criteria['WHERE']['profiles_id'] ?? 0);

        return new ArrayIterator(array_values(array_filter(
            $this->rows,
            static fn (array $row): bool => (int) $row['profiles_id'] === $profileId
        )));
    }
}
