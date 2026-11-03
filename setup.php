<?php

declare(strict_types=1);

use Glpi\Http\Firewall;
use Glpi\Plugin\Hooks;
use GlpiPlugin\Brandpulse\Config as BrandpulseConfig;
use GlpiPlugin\Brandpulse\Menu as BrandpulseMenu;
use GlpiPlugin\Brandpulse\Profile as BrandpulseProfile;

defined('GLPI_ROOT') or die('No direct access allowed');

const PLUGIN_BRANDPULSE_VERSION = '0.2.12';
const PLUGIN_BRANDPULSE_SCHEMA_VERSION = '0.1.44';
const PLUGIN_BRANDPULSE_MIN_GLPI = '11.0.0';
const PLUGIN_BRANDPULSE_MAX_GLPI = '12.0.0';
const PLUGIN_BRANDPULSE_MIN_PHP = '8.2.0';

function plugin_brandpulse_autoload(): void
{
    $autoload = __DIR__ . '/vendor/autoload.php';

    if (file_exists($autoload)) {
        require_once $autoload;
    }
}

function plugin_init_brandpulse(): void
{
    global $PLUGIN_HOOKS;

    plugin_brandpulse_autoload();

    $PLUGIN_HOOKS['csrf_compliant']['brandpulse'] = true;
    $PLUGIN_HOOKS['config_page']['brandpulse'] = 'front/config.php';
    $PLUGIN_HOOKS[Hooks::MENU_TOADD]['brandpulse'] = [
        'tools' => BrandpulseMenu::class,
    ];

    if (class_exists(Firewall::class)) {
        Firewall::addPluginStrategyForLegacyScripts('brandpulse', '#^/front/config\.php$#', Firewall::STRATEGY_CENTRAL_ACCESS);
        Firewall::addPluginStrategyForLegacyScripts('brandpulse', '#^/front/asset\.php$#', Firewall::STRATEGY_NO_CHECK);
        Firewall::addPluginStrategyForLegacyScripts('brandpulse', '#^/front/branding\.css\.php$#', Firewall::STRATEGY_NO_CHECK);
        Firewall::addPluginStrategyForLegacyScripts('brandpulse', '#^/ajax/counters\.php$#', Firewall::STRATEGY_AUTHENTICATED);
        Firewall::addPluginStrategyForLegacyScripts('brandpulse', '#^/ajax/asset-management-link\.php$#', Firewall::STRATEGY_AUTHENTICATED);
        Firewall::addPluginStrategyForLegacyScripts('brandpulse', '#^/ajax/branding\.php$#', Firewall::STRATEGY_NO_CHECK);
        Firewall::addPluginStrategyForLegacyScripts('brandpulse', '#^/ajax/icons\.php$#', Firewall::STRATEGY_CENTRAL_ACCESS);
        Firewall::addPluginStrategyForLegacyScripts('brandpulse', '#^/ajax/icon\.php$#', Firewall::STRATEGY_NO_CHECK);
    }

    if (class_exists(Plugin::class) && Plugin::isPluginActive('brandpulse')) {
        Plugin::registerClass(BrandpulseProfile::class, ['addtabon' => [Profile::class]]);
        $PLUGIN_HOOKS[Hooks::ITEM_ADD]['brandpulse'] = [
            Profile::class => [BrandpulseProfile::class, 'initializeProfileRights'],
        ];
        $PLUGIN_HOOKS[Hooks::ITEM_PURGE]['brandpulse'] = [
            Profile::class => [GlpiPlugin\Brandpulse\ProfileCounterStore::class, 'purgeProfile'],
        ];

        $PLUGIN_HOOKS[Hooks::ADD_CSS]['brandpulse'][] = 'css/brandpulse.css';
        $PLUGIN_HOOKS[Hooks::ADD_CSS]['brandpulse'][] = 'front/branding.css.php';

        $brandpulse_config = class_exists(BrandpulseConfig::class) ? BrandpulseConfig::rawValues() : [];
        if (
            (bool) (int) ($brandpulse_config['enabled'] ?? 0)
            && (bool) (int) ($brandpulse_config['compact_search_enabled'] ?? 0)
        ) {
            $PLUGIN_HOOKS[Hooks::ADD_CSS]['brandpulse'][] = 'css/brandpulse-compact-search.css';
        }

        $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['brandpulse'][] = 'js/brandpulse.js';
        $PLUGIN_HOOKS[Hooks::PRE_ITEM_FORM]['brandpulse'] = [
            Computer::class => 'plugin_brandpulse_pre_item_form',
            NetworkEquipment::class => 'plugin_brandpulse_pre_item_form',
            Printer::class => 'plugin_brandpulse_pre_item_form',
            Phone::class => 'plugin_brandpulse_pre_item_form',
            PDU::class => 'plugin_brandpulse_pre_item_form',
        ];

        if (defined(Hooks::class . '::ADD_CSS_ANONYMOUS_PAGE')) {
            $PLUGIN_HOOKS[Hooks::ADD_CSS_ANONYMOUS_PAGE]['brandpulse'][] = 'css/brandpulse.css';
            $PLUGIN_HOOKS[Hooks::ADD_CSS_ANONYMOUS_PAGE]['brandpulse'][] = 'front/branding.css.php';
        }
        if (defined(Hooks::class . '::ADD_JAVASCRIPT_ANONYMOUS_PAGE')) {
            $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT_ANONYMOUS_PAGE]['brandpulse'][] = 'js/brandpulse.js';
        }
    }
}

function plugin_version_brandpulse(): array
{
    return [
        'name'           => __('GLPI BrandPulse', 'brandpulse'),
        'version'        => PLUGIN_BRANDPULSE_VERSION,
        'author'         => 'TiniSys IT Solutions',
        'license'        => 'GPLv3+',
        'homepage'       => 'https://github.com/TiniSys-IT-Solutions/glpi-brandpulse',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_BRANDPULSE_MIN_GLPI,
                'max' => PLUGIN_BRANDPULSE_MAX_GLPI,
            ],
            'php' => [
                'min' => PLUGIN_BRANDPULSE_MIN_PHP,
            ],
        ],
    ];
}

function plugin_brandpulse_check_prerequisites(): bool
{
    if (version_compare(PHP_VERSION, PLUGIN_BRANDPULSE_MIN_PHP, '<')) {
        echo 'This plugin requires PHP ' . PLUGIN_BRANDPULSE_MIN_PHP . ' or newer.';
        return false;
    }

    if (defined('GLPI_VERSION') && version_compare(GLPI_VERSION, PLUGIN_BRANDPULSE_MIN_GLPI, '<')) {
        echo 'This plugin requires GLPI ' . PLUGIN_BRANDPULSE_MIN_GLPI . ' or newer.';
        return false;
    }

    if (defined('GLPI_VERSION') && version_compare(GLPI_VERSION, PLUGIN_BRANDPULSE_MAX_GLPI, '>=')) {
        echo 'This plugin requires GLPI older than ' . PLUGIN_BRANDPULSE_MAX_GLPI . '.';
        return false;
    }

    return true;
}

function plugin_brandpulse_check_config(bool $verbose = false): bool
{
    return true;
}
