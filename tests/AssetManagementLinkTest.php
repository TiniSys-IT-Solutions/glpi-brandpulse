<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse\Tests;

use GlpiPlugin\Brandpulse\AssetManagementLink;
use PHPUnit\Framework\TestCase;

final class AssetManagementLinkTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['DB']);
    }

    public function testItOnlySupportsAssetTypesWithNetworkPorts(): void
    {
        self::assertTrue(AssetManagementLink::supports('NetworkEquipment'));
        self::assertFalse(AssetManagementLink::supports('User'));
        self::assertSame(
            ['Printer', 'NetworkEquipment'],
            AssetManagementLink::normalizeItemtypes(['Printer', 'User', 'Printer', 'NetworkEquipment'])
        );
    }

    public function testItBuildsHttpAndHttpsUrlsForIpv4AndIpv6(): void
    {
        self::assertSame('https://192.168.10.1/', AssetManagementLink::url('192.168.10.1'));
        self::assertSame('http://[2001:db8::1]/', AssetManagementLink::url('2001:db8::1', 'http'));
    }

    public function testItRejectsInvalidOrUnusableAddressesAndSchemes(): void
    {
        self::assertNull(AssetManagementLink::url('not-an-ip'));
        self::assertNull(AssetManagementLink::url('127.0.0.1'));
        self::assertNull(AssetManagementLink::url('192.168.1.1', 'javascript'));
        self::assertNull(AssetManagementLink::normalizeAddress('fe80::1'));
    }

    public function testEquivalentIpv6FormsCannotBypassAddressValidation(): void
    {
        foreach (['0:0:0:0:0:0:0:1', '0:0:0:0:0:0:0:0', '::ffff:127.0.0.1',
                  'fe90::1', 'febf::1', 'ff02::1', '224.0.0.1', '255.255.255.255'] as $address) {
            self::assertNull(AssetManagementLink::normalizeAddress($address), $address);
        }
        self::assertSame('192.168.10.1', AssetManagementLink::normalizeAddress('::ffff:192.168.10.1'));
    }

    public function testItResolvesAndRanksAddressesFromNetworkPorts(): void
    {
        $GLOBALS['DB'] = new class {
            public function request(array $criteria): iterable
            {
                return match ($criteria['FROM']) {
                    'glpi_networkports' => [['id' => 41], ['id' => 42]],
                    'glpi_networknames' => [['id' => 81], ['id' => 82]],
                    'glpi_ipaddresses' => [
                        ['name' => '2001:db8::10'],
                        ['name' => '203.0.113.5'],
                        ['name' => '192.168.20.1'],
                        ['name' => '192.168.20.1'],
                        ['name' => '127.0.0.1'],
                    ],
                };
            }
        };

        self::assertSame(
            ['192.168.20.1', '203.0.113.5', '2001:db8::10'],
            AssetManagementLink::addressesFor('NetworkEquipment', 7)
        );
    }
}
