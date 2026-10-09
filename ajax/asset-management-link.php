<?php

declare(strict_types=1);

$AJAX_INCLUDE = 1;
defined('GLPI_ROOT') or die('No direct access allowed');

$brandpulse_buffer_level = ob_get_level();
ob_start();
set_error_handler(static fn (): bool => true);

$payload = [
    'available' => false,
    'address' => null,
    'addresses' => [],
    'https_url' => null,
    'http_url' => null,
];

try {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    Session::checkLoginUser();

    $itemtype = (string) ($_GET['itemtype'] ?? '');
    $itemsId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
    $config = GlpiPlugin\Brandpulse\Config::values();
    $supported = $config['asset_management_enabled']
        && in_array($itemtype, $config['asset_management_itemtypes'], true);
    $item = $supported && class_exists($itemtype) ? new $itemtype() : null;

    if (
        $item instanceof CommonDBTM
        && $itemsId > 0
        && $item->can($itemsId, READ)
    ) {
        $addresses = GlpiPlugin\Brandpulse\AssetManagementLink::addressesFor($itemtype, $itemsId);
        $address = $addresses[0] ?? null;
        if ($address !== null) {
            $preferredScheme = $config['asset_management_scheme'];
            $fallbackScheme = $preferredScheme === 'https' ? 'http' : 'https';
            $payload = [
                'available' => true,
                'address' => $address,
                'addresses' => $addresses,
                'https_url' => GlpiPlugin\Brandpulse\AssetManagementLink::url($address, 'https'),
                'http_url' => GlpiPlugin\Brandpulse\AssetManagementLink::url($address, 'http'),
                'preferred_scheme' => $preferredScheme,
                'preferred_url' => GlpiPlugin\Brandpulse\AssetManagementLink::url($address, $preferredScheme),
                'fallback_scheme' => $fallbackScheme,
                'fallback_url' => GlpiPlugin\Brandpulse\AssetManagementLink::url($address, $fallbackScheme),
            ];
        }
    }
} catch (Throwable) {
    $payload['available'] = false;
} finally {
    restore_error_handler();
    while (ob_get_level() > $brandpulse_buffer_level) {
        ob_end_clean();
    }
}

Html::header_nocache();
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
