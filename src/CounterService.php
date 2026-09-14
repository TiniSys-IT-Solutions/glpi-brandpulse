<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse;

final class CounterService
{
    private const CACHE_TTL = 15;

    private int $userId;

    /** @var array<int, array{itemtype: string, params: array}|null> */
    private array $savedSearchCache = [];

    public function __construct(?int $userId = null)
    {
        $this->userId = $userId ?? (int) ($_SESSION['glpiID'] ?? 0);
    }

    public function getPayload(): array
    {
        $config = Config::values();
        $profileId = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);

        if (!$config['enabled'] || !Profile::canViewGeneralPulse() || $this->userId <= 0 || $profileId <= 0) {
            return [
                'enabled' => false,
                'refresh_interval' => $config['refresh_interval'],
                'compact_search_enabled' => false,
                'counters' => [],
            ];
        }

        return [
            'enabled' => true,
            'refresh_interval' => $config['refresh_interval'],
            'compact_search_enabled' => $config['compact_search_enabled'],
            'counters' => $this->getCounters(
                ProfileCounterStore::resolvedForProfile($profileId, $config['counters'])
            ),
        ];
    }

    private function getCounters(array $definitions): array
    {
        $cached = $this->getCachedCounters($definitions);
        if ($cached !== null) {
            return $cached;
        }

        $counters = [];

        foreach ($definitions as $definition) {
            if (empty($definition['enabled']) || empty($definition['key'])) {
                continue;
            }

            $key = (string) $definition['key'];
            $count = $this->count($definition);

            $icon = (string) ($definition['icon'] ?? Config::DEFAULT_PULSE_ICON);

            $counters[] = [
                'key' => $key,
                'label' => __((string) ($definition['label'] ?? $key), 'brandpulse'),
                'icon' => $icon,
                'icon_url' => $this->iconUrl($icon),
                'color' => $this->colorForCount($definition, $count),
                'count' => $count,
                'href' => $this->href($definition, $count),
            ];
        }

        $this->cacheCounters($definitions, $counters);

        return $counters;
    }

    private function count(array $definition): int
    {
        if (($definition['source_type'] ?? 'preset') === 'saved_search') {
            return $this->countSavedSearch((int) ($definition['savedsearches_id'] ?? 0));
        }

        return $this->countSearch('Ticket', $this->presetSearchParams((string) ($definition['key'] ?? '')));
    }

    private function href(array $definition, int $count): string
    {
        if ($count <= 0) {
            return '#';
        }

        if (($definition['source_type'] ?? 'preset') === 'saved_search') {
            return $this->savedSearchUrl((int) ($definition['savedsearches_id'] ?? 0));
        }

        return $this->searchUrl('Ticket', $this->presetSearchParams((string) ($definition['key'] ?? '')));
    }

    private function countSavedSearch(int $savedSearchId): int
    {
        $search = $this->savedSearchData($savedSearchId);

        if ($search === null) {
            return 0;
        }

        return $this->countSearch($search['itemtype'], $search['params']);
    }

    private function savedSearchUrl(int $savedSearchId): string
    {
        $search = $this->savedSearchData($savedSearchId);

        if ($search === null) {
            return '#';
        }

        return $this->searchUrl($search['itemtype'], $search['params']);
    }

    private function savedSearchData(int $savedSearchId): ?array
    {
        if (array_key_exists($savedSearchId, $this->savedSearchCache)) {
            return $this->savedSearchCache[$savedSearchId];
        }

        if ($savedSearchId <= 0 || !class_exists(\SavedSearch::class)) {
            return $this->savedSearchCache[$savedSearchId] = null;
        }

        $savedSearch = new \SavedSearch();
        if (!$savedSearch->getFromDB($savedSearchId) || !$this->canUseSavedSearch($savedSearch)) {
            return $this->savedSearchCache[$savedSearchId] = null;
        }

        $itemtype = (string) ($savedSearch->fields['itemtype'] ?? 'Ticket');
        if ($itemtype === '' || !class_exists($itemtype)) {
            return $this->savedSearchCache[$savedSearchId] = null;
        }

        return $this->savedSearchCache[$savedSearchId] = [
            'itemtype' => $itemtype,
            'params' => $this->paramsFromQuery((string) ($savedSearch->fields['query'] ?? '')),
        ];
    }

    private function countSearch(string $itemtype, array $params): int
    {
        if ($params === [] || !class_exists(\Search::class)) {
            return 0;
        }

        $bufferLevel = ob_get_level();
        ob_start();
        set_error_handler(static fn (): bool => true);

        try {
            $params = array_replace([
                'is_deleted' => 0,
                'start' => 0,
                'list_limit' => 1,
                'display_type' => \Search::HTML_OUTPUT,
                'reset' => 'reset',
            ], $params);

            if (method_exists(\Search::class, 'manageParams')) {
                $managedParams = \Search::manageParams($itemtype, $params, false, false);
                if (is_array($managedParams)) {
                    $params = $managedParams;
                }
            }

            $params['start'] = 0;
            $params['list_limit'] = 1;
            $params['display_type'] = \Search::HTML_OUTPUT;

            $data = \Search::getDatas($itemtype, $params, [2]);

            return (int) ($data['data']['totalcount'] ?? 0);
        } catch (\Throwable) {
            return 0;
        } finally {
            restore_error_handler();
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
        }
    }

    private function presetSearchParams(string $key): array
    {
        return match ($key) {
            'my_tasks' => [
                'criteria' => [
                    ['field' => 12, 'searchtype' => 'equals', 'value' => 'notold'],
                    ['field' => 95, 'searchtype' => 'equals', 'value' => $this->userId],
                ],
            ],
            'my_waiting_tickets' => [
                'criteria' => [
                    ['field' => 5, 'searchtype' => 'equals', 'value' => $this->userId],
                    ['field' => 12, 'searchtype' => 'equals', 'value' => 4],
                ],
            ],
            'my_open_tickets' => [
                'criteria' => [
                    ['field' => 5, 'searchtype' => 'equals', 'value' => $this->userId],
                    ['field' => 12, 'searchtype' => 'equals', 'value' => 'notold'],
                ],
            ],
            'all_open_tickets' => [
                'criteria' => [
                    ['field' => 12, 'searchtype' => 'equals', 'value' => 'notold'],
                ],
            ],
            'unassigned_tickets' => [
                'criteria' => [
                    ['field' => 12, 'searchtype' => 'equals', 'value' => 'notold'],
                    ['field' => 5, 'searchtype' => 'equals', 'value' => 0],
                ],
            ],
            default => [],
        };
    }

    private function searchUrl(string $itemtype, array $params): string
    {
        global $CFG_GLPI;

        if ($params === []) {
            return '#';
        }

        $params = array_replace([
            'is_deleted' => 0,
            'as_map' => 0,
            'browse' => 0,
            'itemtype' => $itemtype,
            'start' => 0,
            'sort' => [0 => 19],
            'order' => [0 => 'DESC'],
        ], $params);

        $path = '/front/ticket.php';
        if (class_exists($itemtype) && method_exists($itemtype, 'getSearchURL')) {
            $path = (string) $itemtype::getSearchURL();
            $rootDoc = (string) ($CFG_GLPI['root_doc'] ?? '');
            if ($rootDoc !== '' && str_starts_with($path, $rootDoc)) {
                $path = substr($path, strlen($rootDoc));
            }
        }

        return ($CFG_GLPI['root_doc'] ?? '') . $path . '?' . http_build_query($params);
    }

    private function paramsFromQuery(string $query): array
    {
        $query = html_entity_decode(trim($query), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $query = ltrim($query, '?&');
        $params = [];
        parse_str($query, $params);

        if (!is_array($params)) {
            return [];
        }

        unset($params['_glpi_csrf_token'], $params['csrf_token']);

        return $params;
    }

    private function canUseSavedSearch(\SavedSearch $savedSearch): bool
    {
        return SavedSearchPolicy::canUse(
            $savedSearch->fields,
            $this->userId,
            $savedSearch->canViewItem()
        );
    }

    private function iconUrl(string $icon): string
    {
        if ($icon === '') {
            $icon = Config::DEFAULT_PULSE_ICON;
        }

        if (str_starts_with($icon, 'pulse:')) {
            $path = substr($icon, 6);
            if ($path === '') {
                $path = substr(Config::DEFAULT_PULSE_ICON, 6);
            }
            if (!str_ends_with($path, '.svg')) {
                $path .= '.svg';
            }

            return $this->pluginWebBase() . '/ajax/icon.php?file=' . rawurlencode($path);
        }

        return $icon;
    }

    private function pluginWebBase(): string
    {
        global $CFG_GLPI;

        return rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/') . '/plugins/brandpulse';
    }

    private function colorForCount(array $definition, int $count): string
    {
        $warning = (int) ($definition['warning_threshold'] ?? 0);
        $critical = (int) ($definition['critical_threshold'] ?? 0);

        if ($critical > 0 && $count >= $critical) {
            return '#ff3d2a';
        }

        if ($warning > 0 && $count >= $warning) {
            return '#f59f00';
        }

        return (string) ($definition['color'] ?? '#3b82f6');
    }

    private function getCachedCounters(array $definitions): ?array
    {
        global $GLPI_CACHE;

        if (!isset($GLPI_CACHE) || !is_object($GLPI_CACHE) || !method_exists($GLPI_CACHE, 'get')) {
            return null;
        }

        try {
            $cached = $GLPI_CACHE->get($this->counterCacheKey($definitions));

            return is_array($cached) ? $cached : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function cacheCounters(array $definitions, array $counters): void
    {
        global $GLPI_CACHE;

        if (!isset($GLPI_CACHE) || !is_object($GLPI_CACHE) || !method_exists($GLPI_CACHE, 'set')) {
            return;
        }

        try {
            $GLPI_CACHE->set($this->counterCacheKey($definitions), $counters, self::CACHE_TTL);
        } catch (\Throwable) {
            // A cache failure must never make the header counters unavailable.
        }
    }

    private function counterCacheKey(array $definitions): string
    {
        $context = [
            'user' => $this->userId,
            'profile' => (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0),
            'entities' => array_values((array) ($_SESSION['glpiactiveentities'] ?? [])),
            'recursive' => (int) ($_SESSION['glpiactive_entity_recursive'] ?? 0),
            'definitions' => $definitions,
        ];

        return 'brandpulse.counters.' . hash('sha256', (string) json_encode($context));
    }
}
