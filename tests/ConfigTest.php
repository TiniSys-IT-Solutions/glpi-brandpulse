<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse\Tests;

use GlpiPlugin\Brandpulse\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        \Config::$values = [];
    }

    public function testProfileCounterLimitIsClampedBetweenSixAndTen(): void
    {
        self::assertSame(6, Config::normalizeMaxProfileCounters(2));
        self::assertSame(8, Config::normalizeMaxProfileCounters(8));
        self::assertSame(10, Config::normalizeMaxProfileCounters(20));
    }

    public function testPulseModeDefaultsToGlobalAndNormalizesInvalidValues(): void
    {
        self::assertSame('global', Config::values()['pulse_mode']);
        self::assertArrayNotHasKey('pulse_interface', Config::defaults());
        \Config::$values[Config::CONTEXT] = ['pulse_mode' => 'invalid'];
        self::assertSame('global', Config::values()['pulse_mode']);
    }

    public function testAssetManagementDefaultsAndSubmittedValuesAreNormalized(): void
    {
        $defaults = Config::values();
        self::assertTrue($defaults['asset_management_enabled']);
        self::assertSame('https', $defaults['asset_management_scheme']);
        self::assertContains('NetworkEquipment', $defaults['asset_management_itemtypes']);

        Config::saveAssetManagement(true, 'http', ['Printer', 'User', 'Printer']);
        $saved = Config::values();
        self::assertSame('http', $saved['asset_management_scheme']);
        self::assertSame(['Printer'], $saved['asset_management_itemtypes']);
    }

    public function testBrandingIsNormalizedAndLegacyLogosRemainCompatible(): void
    {
        $branding = Config::normalizeBranding([
            'enabled' => 1,
            'title' => '  Service desk  ',
            'menu_logo' => ' menu.svg ',
            'login_logo' => ' login.svg ',
            'login_alert_type' => 'unsupported',
            'login_alert_icon' => 'https://example.test/icon.svg',
        ]);

        self::assertTrue($branding['enabled']);
        self::assertSame('Service desk', $branding['title']);
        self::assertSame('menu.svg', $branding['logo_sidebar_expanded_light']);
        self::assertSame('login.svg', $branding['login_logo_light']);
        self::assertSame('info', $branding['login_alert_type']);
        self::assertSame(Config::DEFAULT_PULSE_ICON, $branding['login_alert_icon']);
    }

    public function testBrandAssetsRejectActiveOrMalformedSchemes(): void
    {
        $branding = Config::normalizeBranding([
            'favicon' => 'javascript:alert(1)',
            'login_background' => "https://example.test/background.png\nInjected",
            'logo_sidebar_expanded_dark' => 'https://example.test/logo.svg',
            'logo_sidebar_collapsed_dark' => '/plugins/brandpulse/front/asset.php?file=logo.svg',
        ]);

        self::assertSame('', $branding['favicon']);
        self::assertSame('', $branding['login_background']);
        self::assertSame('https://example.test/logo.svg', $branding['logo_sidebar_expanded_dark']);
        self::assertSame(
            '/plugins/brandpulse/front/asset.php?file=logo.svg',
            $branding['logo_sidebar_collapsed_dark']
        );
    }

    public function testMalformedAssetUrlsAreRejected(): void
    {
        foreach (['http://', 'https:logo.svg', 'https:///logo.svg', '/\\example.test/logo.svg',
                  'http://example.test:invalid/logo.svg'] as $reference) {
            self::assertSame('', Config::normalizeBranding(['favicon' => $reference])['favicon']);
        }
        self::assertSame('//example.test/logo.svg',
            Config::normalizeBranding(['favicon' => '//example.test/logo.svg'])['favicon']);
    }

    public function testSavedSearchCounterGetsStableKeyAndSafeValues(): void
    {
        $counters = Config::normalizeCounters([[
            'key' => 'ignored',
            'label' => '  Tickets critiques  ',
            'source_type' => 'saved_search',
            'savedsearches_id' => 42,
            'icon' => 'pulse:../secret.svg',
            'color' => 'red',
            'enabled' => 1,
            'warning_threshold' => -5,
            'critical_threshold' => 10,
        ]]);

        self::assertCount(1, $counters);
        self::assertSame('saved_search_42', $counters[0]['key']);
        self::assertSame('Tickets critiques', $counters[0]['label']);
        self::assertSame(Config::DEFAULT_PULSE_ICON, $counters[0]['icon']);
        self::assertSame('#3b82f6', $counters[0]['color']);
        self::assertSame(0, $counters[0]['warning_threshold']);
        self::assertSame(10, $counters[0]['critical_threshold']);
    }

    public function testInvalidAndIncompleteCountersAreDiscarded(): void
    {
        $counters = Config::normalizeCounters([
            ['key' => 'unknown_preset', 'label' => 'Unknown', 'source_type' => 'preset'],
            ['key' => '', 'label' => '', 'source_type' => 'preset'],
            ['key' => 'saved', 'label' => 'Missing search', 'source_type' => 'saved_search'],
        ]);

        self::assertSame([], $counters);
    }

    public function testBundledPulseIconIsAccepted(): void
    {
        $counter = Config::normalizeCounters([[
            'key' => 'my_tasks',
            'label' => 'Tasks',
            'icon' => 'pulse:List/Checklist Minimalistic',
        ]])[0];

        self::assertSame('pulse:List/Checklist Minimalistic.svg', $counter['icon']);
    }
}
