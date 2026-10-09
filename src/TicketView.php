<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse;

/** Per-counter presentation, applied only to links into GLPI's ticket search. */
final class TicketView
{
    public static function normalize(array $view): array
    {
        $columns = [];
        foreach (is_array($view['columns'] ?? null) ? $view['columns'] : [] as $column) {
            $id = filter_var($column, FILTER_VALIDATE_INT);
            if ($id !== false && $id > 0) {
                $columns[$id] = $id;
            }
        }
        $sort = filter_var($view['sort'] ?? 0, FILTER_VALIDATE_INT);

        return [
            'use_glpi' => !array_key_exists('use_glpi', $view) || !empty($view['use_glpi']),
            'columns' => array_values($columns),
            'sort' => $sort !== false ? max(0, $sort) : 0,
            'order' => ($view['order'] ?? '') === 'ASC' ? 'ASC' : 'DESC',
        ];
    }

    /** @return array<int, array{name: string, group: string, fixed: bool, sortable: bool}> */
    public static function options(): array
    {
        if (!class_exists(\Search::class) || !class_exists(\Ticket::class) || !\Ticket::canView()) {
            return [];
        }

        $fixed = \Search::addDefaultToView('Ticket', []);
        $options = [];
        $group = '';
        foreach (\Search::getCleanedOptions('Ticket', READ) as $id => $option) {
            if (!is_array($option)) {
                $group = (string) $option;
            } elseif (count($option) === 1) {
                $group = (string) ($option['name'] ?? '');
            } elseif (is_numeric($id) && empty($option['nodisplay'])) {
                $options[(int) $id] = [
                    'name' => (string) ($option['name'] ?? $id),
                    'group' => $group,
                    'fixed' => in_array((int) $id, $fixed, true),
                    'sortable' => empty($option['nosort']),
                ];
            }
        }

        return $options;
    }

    /** Validate again in the clicking user's native GLPI rights context. */
    public static function parameters(array $view, string $itemtype): array
    {
        $view = self::normalize($view);
        if ($view['use_glpi'] || $itemtype !== 'Ticket') {
            return [];
        }

        $options = self::options();
        if ($options === []) {
            return [];
        }
        $params = [
            'forcetoview' => array_values(array_filter($view['columns'], static fn (int $id): bool =>
                isset($options[$id]) && !$options[$id]['fixed'])),
        ];
        if (isset($options[$view['sort']]) && $options[$view['sort']]['sortable']) {
            $params['sort'] = [$view['sort']];
            $params['order'] = [$view['order']];
        }

        return $params;
    }
}
