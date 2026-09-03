<?php

declare(strict_types=1);

defined('GLPI_ROOT') or die('No direct access allowed');

use Glpi\Cache\CacheManager;

function plugin_brandpulse_require_autoload(): void
{
    $autoload = __DIR__ . '/vendor/autoload.php';

    if (file_exists($autoload)) {
        require_once $autoload;
    }
}

function plugin_brandpulse_clear_translation_cache(): void
{
    if (!class_exists(CacheManager::class)) {
        return;
    }

    try {
        (new CacheManager())->getTranslationsCacheInstance()->clear();
    } catch (Throwable) {
        // Cache cleanup must never block plugin install/update.
    }
}

function plugin_brandpulse_install(): bool
{
    plugin_brandpulse_require_autoload();

    if (!class_exists(GlpiPlugin\Brandpulse\Migrator::class)) {
        return false;
    }

    $migrated = GlpiPlugin\Brandpulse\Migrator::migrate(PLUGIN_BRANDPULSE_SCHEMA_VERSION);

    if ($migrated && class_exists(GlpiPlugin\Brandpulse\BrandAssetStore::class)) {
        GlpiPlugin\Brandpulse\BrandAssetStore::ensureBrandDirectory();
    }

    plugin_brandpulse_clear_translation_cache();

    return $migrated;
}

function plugin_brandpulse_uninstall(): bool
{
    plugin_brandpulse_require_autoload();

    if (class_exists(GlpiPlugin\Brandpulse\Profile::class)) {
        GlpiPlugin\Brandpulse\Profile::uninstallRights();
    }

    if (class_exists(GlpiPlugin\Brandpulse\Config::class)) {
        GlpiPlugin\Brandpulse\Config::uninstall();
    }

    plugin_brandpulse_clear_translation_cache();

    return true;
}
