<?php

declare(strict_types=1);

$AJAX_INCLUDE = 1;
defined('GLPI_ROOT') or die('No direct access allowed');

$brandpulse_buffer_level = ob_get_level();
ob_start();
set_error_handler(static fn (): bool => true);

$payload = [
    'enabled' => false,
    'refresh_interval' => 60,
    'compact_search_enabled' => false,
    'counters' => [],
];

try {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    Session::checkLoginUser();

    $service = new GlpiPlugin\Brandpulse\CounterService();
    // A page entry or completed GLPI action needs current results even inside
    // the short polling cache TTL. Authorization remains in the service.
    $payload = $service->getPayload(($_GET['fresh'] ?? '') === '1');
} catch (Throwable) {
    $payload['enabled'] = false;
} finally {
    restore_error_handler();
    while (ob_get_level() > $brandpulse_buffer_level) {
        ob_end_clean();
    }
}

Html::header_nocache();
header('Content-Type: application/json; charset=UTF-8');
echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
