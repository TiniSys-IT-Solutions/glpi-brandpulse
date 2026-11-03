<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse;

final class Config
{
    public const CONTEXT = 'plugin:brandpulse';
    public const SCHEMA_VERSION_KEY = 'schema_version';
    public const DEFAULT_PULSE_ICON = 'pulse:Notifications/Bell.svg';

    public static function installDefaults(): void
    {
        if (!class_exists(\Config::class)) {
            return;
        }

        $current = \Config::getConfigurationValues(self::CONTEXT);
        $current = is_array($current) ? $current : [];
        $missing = array_diff_key(self::defaults(), $current);

        if ($missing !== []) {
            \Config::setConfigurationValues(self::CONTEXT, $missing);
        }
    }

    public static function uninstall(): void
    {
        if (class_exists(\Config::class) && method_exists(\Config::class, 'deleteConfigurationValues')) {
            \Config::deleteConfigurationValues(self::CONTEXT);
        }
    }

    public static function schemaVersion(): string
    {
        return (string) self::rawValues()[self::SCHEMA_VERSION_KEY];
    }

    public static function setSchemaVersion(string $version): void
    {
        if (!class_exists(\Config::class)) {
            return;
        }

        \Config::setConfigurationValues(self::CONTEXT, [
            self::SCHEMA_VERSION_KEY => $version,
        ]);
    }

    public static function values(): array
    {
        $values = self::rawValues();

        $values['enabled'] = (bool) (int) $values['enabled'];
        $values['compact_search_enabled'] = (bool) (int) $values['compact_search_enabled'];
        $values['pulse_mode'] = in_array($values['pulse_mode'], ['global', 'profile'], true)
            ? $values['pulse_mode']
            : 'global';
        $values['global_selfservice_enabled'] = (bool) (int) $values['global_selfservice_enabled'];
        $values['asset_management_enabled'] = (bool) (int) $values['asset_management_enabled'];
        $values['asset_management_scheme'] = in_array($values['asset_management_scheme'], ['https', 'http'], true)
            ? $values['asset_management_scheme']
            : 'https';
        $values['asset_management_itemtypes'] = AssetManagementLink::normalizeItemtypes(
            self::decodeJson($values['asset_management_itemtypes_json'], AssetManagementLink::supportedItemtypes())
        );
        $values['refresh_interval'] = max(15, (int) $values['refresh_interval']);
        $values['max_profile_counters'] = self::normalizeMaxProfileCounters(
            (int) $values['max_profile_counters']
        );
        $values['branding'] = self::normalizeBranding(
            self::decodeJson($values['branding_json'], self::defaultBranding())
        );
        $values['counters'] = self::normalizeCounters(
            self::decodeJson($values['counters_json'], self::defaultCounters())
        );

        return $values;
    }

    public static function rawValues(): array
    {
        $values = self::defaults();

        if (class_exists(\Config::class)) {
            $current = \Config::getConfigurationValues(self::CONTEXT);
            $values = array_replace($values, is_array($current) ? $current : []);
        }

        return $values;
    }

    /** Anonymous pages need only branding that is currently enabled. */
    public static function publicBranding(): array
    {
        $branding = self::values()['branding'];
        if (!$branding['enabled']) {
            return ['enabled' => false];
        }
        if (!$branding['login_alert_enabled']) {
            $branding['login_alert_message'] = '';
        }
        return $branding;
    }

    public static function save(array $values): void
    {
        if (!class_exists(\Config::class)) {
            return;
        }

        \Config::setConfigurationValues(self::CONTEXT, array_intersect_key($values, self::defaults()));
    }

    public static function saveBranding(array $branding): void
    {
        self::save([
            'branding_json' => self::encodeJson(self::normalizeBranding($branding)),
        ]);
    }

    public static function savePulse(
        bool $enabled,
        int $refreshInterval,
        bool $compactSearchEnabled,
        array $counters,
        int $maxProfileCounters = PulseResolver::DEFAULT_MAX_PROFILE_COUNTERS,
        string $pulseMode = 'global',
        bool $globalSelfserviceEnabled = false
    ): void
    {
        $counters = self::normalizeCounters($counters);
        self::save([
            'enabled' => $enabled ? '1' : '0',
            'refresh_interval' => (string) max(15, $refreshInterval),
            'compact_search_enabled' => $compactSearchEnabled ? '1' : '0',
            'max_profile_counters' => (string) self::normalizeMaxProfileCounters($maxProfileCounters),
            'pulse_mode' => in_array($pulseMode, ['global', 'profile'], true) ? $pulseMode : 'global',
            'global_selfservice_enabled' => $globalSelfserviceEnabled ? '1' : '0',
            'counters_json' => self::encodeJson($counters),
        ]);
        ProfileCounterStore::pruneCatalog($counters);
    }

    public static function saveAssetManagement(bool $enabled, string $scheme, array $itemtypes): void
    {
        self::save([
            'asset_management_enabled' => $enabled ? '1' : '0',
            'asset_management_scheme' => in_array($scheme, ['https', 'http'], true) ? $scheme : 'https',
            'asset_management_itemtypes_json' => self::encodeJson(
                AssetManagementLink::normalizeItemtypes($itemtypes)
            ),
        ]);
    }

    public static function defaults(): array
    {
        return [
            'enabled' => '1',
            'refresh_interval' => '60',
            'max_profile_counters' => (string) PulseResolver::DEFAULT_MAX_PROFILE_COUNTERS,
            'pulse_mode' => 'global',
            'global_selfservice_enabled' => '0',
            'compact_search_enabled' => '0',
            'asset_management_enabled' => '1',
            'asset_management_scheme' => 'https',
            'asset_management_itemtypes_json' => self::encodeJson(AssetManagementLink::supportedItemtypes()),
            'branding_json' => self::encodeJson(self::defaultBranding()),
            'counters_json' => self::encodeJson(self::defaultCounters()),
            self::SCHEMA_VERSION_KEY => '',
        ];
    }

    public static function defaultBranding(): array
    {
        return [
            'enabled' => false,
            'title' => 'GLPI BrandPulse',
            'favicon' => '',
            'login_logo' => '',
            'menu_logo' => '',
            'login_logo_light' => '',
            'login_logo_dark' => '',
            'login_logo_grey' => '',
            'logo_sidebar_expanded_light' => '',
            'logo_sidebar_expanded_dark' => '',
            'logo_sidebar_expanded_grey' => '',
            'logo_sidebar_collapsed_light' => '',
            'logo_sidebar_collapsed_dark' => '',
            'logo_sidebar_collapsed_grey' => '',
            'login_background' => '',
            'login_alert_enabled' => false,
            'login_alert_type' => 'info',
            'login_alert_icon' => self::DEFAULT_PULSE_ICON,
            'login_alert_expanded' => false,
            'login_alert_message' => '',
        ];
    }

    public static function defaultCounters(): array
    {
        return [
            [
                'key' => 'my_tasks',
                'label' => 'My pending tasks',
                'icon' => 'pulse:List/Checklist Minimalistic.svg',
                'color' => '#27ab3c',
                'enabled' => true,
                'source_type' => 'preset',
                'savedsearches_id' => 0,
                'warning_threshold' => 0,
                'critical_threshold' => 0,
            ],
            [
                'key' => 'my_waiting_tickets',
                'label' => 'Waiting tickets',
                'icon' => self::DEFAULT_PULSE_ICON,
                'color' => '#f59f00',
                'enabled' => true,
                'source_type' => 'preset',
                'savedsearches_id' => 0,
                'warning_threshold' => 0,
                'critical_threshold' => 0,
            ],
            [
                'key' => 'my_open_tickets',
                'label' => 'My open tickets',
                'icon' => 'pulse:Money/Ticket.svg',
                'color' => '#3b82f6',
                'enabled' => true,
                'source_type' => 'preset',
                'savedsearches_id' => 0,
                'warning_threshold' => 0,
                'critical_threshold' => 0,
            ],
            [
                'key' => 'all_open_tickets',
                'label' => 'All open tickets',
                'icon' => 'pulse:List/List Check.svg',
                'color' => '#ff3d2a',
                'enabled' => true,
                'source_type' => 'preset',
                'savedsearches_id' => 0,
                'warning_threshold' => 0,
                'critical_threshold' => 0,
            ],
            [
                'key' => 'unassigned_tickets',
                'label' => 'Unassigned tickets',
                'icon' => 'pulse:Users/User Cross.svg',
                'color' => '#ffdc64',
                'enabled' => true,
                'source_type' => 'preset',
                'savedsearches_id' => 0,
                'warning_threshold' => 0,
                'critical_threshold' => 0,
            ],
        ];
    }

    public static function normalizeMaxProfileCounters(int $maximum): int
    {
        return max(
            PulseResolver::MIN_PROFILE_COUNTERS,
            min(PulseResolver::MAX_PROFILE_COUNTERS, $maximum)
        );
    }

    public static function pulseIcons(): array
    {
        $manifestPath = __DIR__ . '/../public/icons/pulse/index.json';
        $icons = [];

        if (is_readable($manifestPath)) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            $entries = is_array($manifest['preferred'] ?? null) ? $manifest['preferred'] : ($manifest['icons'] ?? []);

            foreach ($entries as $entry) {
                $rawPath = $entry['path'] ?? $entry['p'] ?? '';
                if (!is_array($entry) || $rawPath === '') {
                    continue;
                }

                $path = str_replace('\\', '/', (string) $rawPath);
                $label = trim((string) ($entry['label'] ?? $entry['l'] ?? pathinfo($path, PATHINFO_FILENAME)));
                $category = trim((string) ($entry['category'] ?? $entry['c'] ?? ''));
                $icons['pulse:' . $path] = $category !== '' ? $category . ' / ' . $label : $label;
            }
        }

        return $icons !== [] ? $icons : [
            'pulse:Search/Magnifer.svg' => 'Search / Magnifer',
            self::DEFAULT_PULSE_ICON => 'Notifications / Bell',
            'pulse:List/Checklist Minimalistic.svg' => 'List / Checklist Minimalistic',
        ];
    }

    public static function presetCounters(): array
    {
        return [
            'my_tasks' => 'My pending tasks',
            'my_waiting_tickets' => 'Waiting tickets',
            'my_open_tickets' => 'My open tickets',
            'all_open_tickets' => 'All open tickets',
            'unassigned_tickets' => 'Unassigned tickets',
        ];
    }

    public static function normalizeBranding(array $branding): array
    {
        $branding = array_replace(self::defaultBranding(), $branding);
        $allowedAlertTypes = ['info', 'warning', 'danger', 'success'];

        $menuLogo = self::normalizeAssetReference((string) $branding['menu_logo']);
        $loginLogo = self::normalizeAssetReference((string) $branding['login_logo']);
        $sidebarExpandedLight = self::normalizeAssetReference((string) $branding['logo_sidebar_expanded_light']) ?: $menuLogo;
        $loginLogoLight = self::normalizeAssetReference((string) $branding['login_logo_light']) ?: $loginLogo;

        return [
            'enabled' => !empty($branding['enabled']),
            'title' => trim((string) $branding['title']),
            'favicon' => self::normalizeAssetReference((string) $branding['favicon']),
            'login_logo' => $loginLogo,
            'menu_logo' => $menuLogo,
            'login_logo_light' => $loginLogoLight,
            'login_logo_dark' => self::normalizeAssetReference((string) $branding['login_logo_dark']),
            'login_logo_grey' => self::normalizeAssetReference((string) $branding['login_logo_grey']),
            'logo_sidebar_expanded_light' => $sidebarExpandedLight,
            'logo_sidebar_expanded_dark' => self::normalizeAssetReference((string) $branding['logo_sidebar_expanded_dark']),
            'logo_sidebar_expanded_grey' => self::normalizeAssetReference((string) $branding['logo_sidebar_expanded_grey']),
            'logo_sidebar_collapsed_light' => self::normalizeAssetReference((string) $branding['logo_sidebar_collapsed_light']),
            'logo_sidebar_collapsed_dark' => self::normalizeAssetReference((string) $branding['logo_sidebar_collapsed_dark']),
            'logo_sidebar_collapsed_grey' => self::normalizeAssetReference((string) $branding['logo_sidebar_collapsed_grey']),
            'login_background' => self::normalizeAssetReference((string) $branding['login_background']),
            'login_alert_enabled' => !empty($branding['login_alert_enabled']),
            'login_alert_type' => in_array($branding['login_alert_type'], $allowedAlertTypes, true)
                ? (string) $branding['login_alert_type']
                : 'info',
            'login_alert_icon' => self::normalizeIcon((string) ($branding['login_alert_icon'] ?? self::DEFAULT_PULSE_ICON)),
            'login_alert_expanded' => !empty($branding['login_alert_expanded']),
            'login_alert_message' => trim((string) $branding['login_alert_message']),
        ];
    }

    public static function normalizeCounters(array $counters): array
    {
        $normalized = [];
        $seen = [];

        foreach ($counters as $counter) {
            if (!is_array($counter)) {
                continue;
            }

            $key = trim((string) ($counter['key'] ?? ''));
            $label = trim((string) ($counter['label'] ?? ''));
            $sourceType = (string) ($counter['source_type'] ?? $counter['scope_type'] ?? 'preset');
            $savedSearchId = max(0, (int) ($counter['savedsearches_id'] ?? 0));

            if ($key === '' && $label !== '') {
                $key = self::slug($label);
            }

            if ($key === '' || $label === '') {
                continue;
            }

            if (!in_array($sourceType, ['preset', 'saved_search'], true)) {
                $sourceType = 'preset';
            }

            if ($sourceType === 'preset' && !array_key_exists($key, self::presetCounters())) {
                continue;
            }

            if ($sourceType === 'saved_search') {
                if ($savedSearchId <= 0) {
                    continue;
                }

                $key = 'saved_search_' . $savedSearchId;
            }

            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $normalized[] = [
                'key' => $key,
                'label' => $label,
                'icon' => self::normalizeIcon((string) ($counter['icon'] ?? '')),
                'color' => self::normalizeColor((string) ($counter['color'] ?? '#3b82f6')),
                'enabled' => !empty($counter['enabled']),
                'source_type' => $sourceType,
                'savedsearches_id' => $savedSearchId,
                'warning_threshold' => max(0, (int) ($counter['warning_threshold'] ?? 0)),
                'critical_threshold' => max(0, (int) ($counter['critical_threshold'] ?? 0)),
                'view' => TicketView::normalize(is_array($counter['view'] ?? null) ? $counter['view'] : []),
            ];
        }

        return $normalized;
    }

    private static function encodeJson(array $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private static function decodeJson(string $json, array $fallback): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : $fallback;
    }

    private static function normalizeColor(string $color): string
    {
        $color = trim($color);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? $color : '#3b82f6';
    }

    private static function normalizeIcon(string $icon): string
    {
        $icon = trim($icon);

        if ($icon === '') {
            return self::DEFAULT_PULSE_ICON;
        }

        if (!str_starts_with($icon, 'pulse:')) {
            return self::DEFAULT_PULSE_ICON;
        }

        $path = str_replace('\\', '/', substr($icon, 6));
        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, "\0")) {
            return self::DEFAULT_PULSE_ICON;
        }

        $parts = array_values(array_filter(explode('/', $path), static fn (string $part): bool => $part !== ''));
        if (in_array('..', $parts, true) || in_array('.', $parts, true)) {
            return self::DEFAULT_PULSE_ICON;
        }
        $path = implode('/', $parts);

        if (!str_ends_with(strtolower($path), '.svg')) {
            $path .= '.svg';
        }

        return self::localPulseIconExists($path) ? 'pulse:' . $path : self::DEFAULT_PULSE_ICON;
    }

    private static function normalizeAssetReference(string $value): string
    {
        $value = trim($value);
        if ($value === '' || str_contains($value, '\\') || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            return '';
        }

        $parts = parse_url($value);
        if ($parts === false) {
            return '';
        }
        // These URLs are exposed on the anonymous login page.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return '';
        }
        $scheme = $parts['scheme'] ?? null;
        if ($scheme !== null && !in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
            return '';
        }

        if (($scheme !== null || str_starts_with($value, '//')) && empty($parts['host'])) {
            return '';
        }

        return $value;
    }

    private static function localPulseIconExists(string $path): bool
    {
        $baseDirectory = realpath(__DIR__ . '/../public/icons/pulse');
        if ($baseDirectory === false) {
            return false;
        }

        $realPath = realpath($baseDirectory . '/' . $path);

        return $realPath !== false
            && is_file($realPath)
            && str_starts_with($realPath, $baseDirectory . DIRECTORY_SEPARATOR);
    }

    private static function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?: '';
        $value = trim($value, '_');

        return $value !== '' ? $value : 'counter';
    }
}
