<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse;

use RuntimeException;

/** Manage the existing native Pulse right without introducing another ACL. */
final class ProfileAccess
{
    public static function rows(): array
    {
        global $DB;
        $rows = [];
        foreach ($DB->request([
            'SELECT' => ['id', 'name', 'interface'],
            'FROM' => \Profile::getTable(),
            'ORDER' => ['name ASC'],
        ]) as $row) {
            $profile = new \Profile();
            $id = (int) $row['id'];
            $rows[] = [
                'id' => $id,
                'name' => (string) $row['name'],
                'interface' => (string) $row['interface'],
                'allowed' => Profile::profileCanViewGeneralPulse($id),
                'editable' => \Session::haveRight('profile', UPDATE) && $profile->can($id, UPDATE),
            ];
        }
        return $rows;
    }

    /** Match composition choices to native Pulse authorization, including read-only profiles. */
    public static function compositionProfiles(array $profiles, array $rows, ?array $selection = null): array
    {
        $selected = [];
        foreach ($selection ?? [] as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT);
            if ($id !== false) $selected[$id] = true;
        }
        $allowed = [];
        foreach ($rows as $row) {
            $enabled = $selection !== null && $row['editable']
                ? isset($selected[(int) $row['id']]) : $row['allowed'];
            if ($enabled) $allowed[(int) $row['id']] = true;
        }
        return array_intersect_key($profiles, $allowed);
    }

    /** Convert a native multi-selection to changes without touching read-only profiles. */
    public static function selectionChanges(array $selected, array $rows): array
    {
        if (!\Session::haveRight('profile', UPDATE)) {
            throw new RuntimeException('You do not have permission to modify GLPI profiles.');
        }
        $editable = [];
        foreach ($rows as $row) {
            if ($row['editable']) $editable[(int) $row['id']] = (bool) $row['allowed'];
        }
        $ids = [];
        foreach ($selected as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT);
            if ($id === false || !array_key_exists($id, $editable)) {
                throw new RuntimeException('A selected profile is unavailable or cannot be modified.');
            }
            $ids[$id] = true;
        }
        $changes = [];
        foreach ($editable as $id => $allowed) {
            if ($allowed !== isset($ids[$id])) $changes[$id] = isset($ids[$id]) ? '1' : '0';
        }
        return $changes;
    }

    /** Check every target before changing anything; persist through native Profile. */
    public static function save(array $submitted): void
    {
        global $DB;
        if (!\Session::haveRight('profile', UPDATE)) {
            throw new RuntimeException('You do not have permission to modify GLPI profiles.');
        }
        $updates = [];
        foreach ($submitted as $id => $value) {
            $id = filter_var($id, FILTER_VALIDATE_INT);
            $profile = new \Profile();
            if ($id === false || $id <= 0 || !in_array($value, ['0', '1', 0, 1], true)
                || !$profile->can($id, UPDATE)) {
                throw new RuntimeException('A selected profile is unavailable or cannot be modified.');
            }
            $updates[] = [$profile, $id, (int) $value];
        }
        $DB->beginTransaction();
        try {
            foreach ($updates as [$profile, $id, $allowed]) {
                // Native rights-form input; preserves all other profile rights
                // and invokes GLPI history and session-rights invalidation.
                if (!$profile->update([
                    'id' => $id,
                    '_' . Profile::RIGHT_VIEW_GENERAL_PULSE => [READ => $allowed],
                ])) {
                    throw new RuntimeException('Unable to save Pulse profile permissions.');
                }
            }
            $DB->commit();
        } catch (\Throwable $exception) {
            $DB->rollBack();
            throw new RuntimeException('Unable to save Pulse profile permissions.', 0, $exception);
        }
        \Session::reloadCurrentProfile();
    }
}
