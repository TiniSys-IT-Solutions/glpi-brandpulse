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

/**
 * Provide a stable GLPI-owned insertion point at the beginning of supported
 * asset forms. The frontend fills it only when a usable network IP exists.
 *
 * @param array{item?: mixed, options?: array} $params
 */
function plugin_brandpulse_pre_item_form(array $params): void
{
    plugin_brandpulse_require_autoload();

    $item = $params['item'] ?? null;
    if (!$item instanceof CommonDBTM || $item->isNewItem() || !$item->can($item->getID(), READ)) {
        return;
    }

    $itemtype = $item::getType();
    $config = GlpiPlugin\Brandpulse\Config::values();
    if (
        !$config['asset_management_enabled']
        || !in_array($itemtype, $config['asset_management_itemtypes'], true)
    ) {
        return;
    }

    echo '<div class="brandpulse-asset-management-slot"'
        . ' data-brandpulse-asset-management-slot'
        . ' data-itemtype="' . htmlspecialchars($itemtype, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-items-id="' . (int) $item->getID() . '"></div>';
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

    if (
        class_exists(GlpiPlugin\Brandpulse\ProfileCounterStore::class)
        && !GlpiPlugin\Brandpulse\ProfileCounterStore::uninstall()
    ) {
        return false;
    }

    if (class_exists(GlpiPlugin\Brandpulse\Config::class)) {
        GlpiPlugin\Brandpulse\Config::uninstall();
    }

    plugin_brandpulse_clear_translation_cache();

    return true;
}
