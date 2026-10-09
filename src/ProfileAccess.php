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
