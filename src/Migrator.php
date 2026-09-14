<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse;

final class Migrator
{
    public static function migrate(string $targetVersion): bool
    {
        if (!class_exists(\Config::class)) {
            return false;
        }

        $installedVersion = Config::schemaVersion();

        if ($installedVersion === '') {
            $installedVersion = '0.0.0';
        }

        if (version_compare($installedVersion, '0.1.0', '<=')) {
            self::migrateTo010();
        }

        if (version_compare($installedVersion, '0.1.2', '<')) {
            self::migrateTo012();
        }

        if (version_compare($installedVersion, '0.1.3', '<')) {
            self::migrateTo013();
        }

        if (version_compare($installedVersion, '0.1.43', '<')) {
            self::migrateTo0143();
        }

        if (!ProfileCounterStore::install(Config::values()['counters'])) {
            return false;
        }

        Config::setSchemaVersion($targetVersion);

        return true;
    }

    private static function migrateTo010(): void
    {
        Config::installDefaults();
    }

    private static function migrateTo012(): void
    {
        self::normalizeStoredConfiguration();
    }

    private static function migrateTo013(): void
    {
        self::normalizeStoredConfiguration();
    }

    private static function migrateTo0143(): void
    {
        Profile::installRights();

        if (method_exists(\Config::class, 'deleteConfigurationValues')) {
            \Config::deleteConfigurationValues(Config::CONTEXT, ['hide_pulse_service_catalog']);
        }
    }

    private static function normalizeStoredConfiguration(): void
    {
        $values = Config::values();

        Config::saveBranding($values['branding']);
        Config::savePulse(
            $values['enabled'],
            $values['refresh_interval'],
            $values['compact_search_enabled'],
            $values['counters']
        );
    }
}
