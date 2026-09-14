<?php

declare(strict_types=1);

defined('GLPI_ROOT') or die('No direct access allowed');

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

use GlpiPlugin\Brandpulse\BrandAssetStore;
use GlpiPlugin\Brandpulse\Config as BrandpulseConfig;
use GlpiPlugin\Brandpulse\ProfileCounterStore;
use GlpiPlugin\Brandpulse\PulseResolver;

Session::checkLoginUser();
Session::checkRight('config', UPDATE);

function plugin_brandpulse_h(string $value): string
{
    return Html::entities_deep($value);
}

function plugin_brandpulse_saved_search_rows(): array
{
    global $DB;

    $rows = [0 => __('None', 'brandpulse')];

    if (!isset($DB) || !method_exists($DB, 'request') || !class_exists(SavedSearch::class)) {
        return $rows;
    }

    try {
        $iterator = $DB->request([
            'SELECT' => ['id', 'name', 'users_id', 'is_private'],
            'FROM' => SavedSearch::getTable(),
            'WHERE' => [
                'itemtype' => Ticket::class,
                'type' => SavedSearch::SEARCH,
                'is_private' => 0,
            ],
            'ORDER' => 'name',
        ]);

        foreach ($iterator as $row) {
            $savedSearch = new SavedSearch();
            if (!$savedSearch->getFromDB((int) $row['id']) || !$savedSearch->canViewItem()) {
                continue;
            }
            $rows[(int) $row['id']] = (string) ($row['name'] ?: '#' . $row['id']);
        }
    } catch (Throwable) {
        return $rows;
    }

    return $rows;
}

function plugin_brandpulse_csrf_token_name(): string
{
    return defined('Session::CSRF_TOKEN') ? Session::CSRF_TOKEN : '_glpi_csrf_token';
}

function plugin_brandpulse_select(string $name, array $options, string|int $selected, bool $translate = true): string
{
    $html = "<select class='form-select form-select-sm' name='" . plugin_brandpulse_h($name) . "'>";

    foreach ($options as $value => $label) {
        $isSelected = (string) $value === (string) $selected ? ' selected' : '';
        $text = $translate ? __((string) $label, 'brandpulse') : (string) $label;
        $html .= "<option value='" . plugin_brandpulse_h((string) $value) . "'" . $isSelected . '>' . plugin_brandpulse_h($text) . '</option>';
    }

    return $html . '</select>';
}

function plugin_brandpulse_web_base(): string
{
    global $CFG_GLPI;

    return rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/') . '/plugins/brandpulse';
}

function plugin_brandpulse_config_url(string $tab): string
{
    return plugin_brandpulse_web_base() . '/front/config.php?tab=' . rawurlencode($tab);
}

function plugin_brandpulse_profile_rows(): array
{
    global $DB;

    $profiles = [];
    if (!isset($DB) || !class_exists(Profile::class)) {
        return $profiles;
    }

    try {
        foreach ($DB->request([
            'SELECT' => ['id', 'name', 'interface'],
            'FROM' => Profile::getTable(),
            'ORDER' => ['name ASC'],
        ]) as $profile) {
            $name = (string) ($profile['name'] ?? ('#' . $profile['id']));
            $interface = (string) ($profile['interface'] ?? 'central');
            $profiles[(int) $profile['id']] = $name . ' · ' . $interface;
        }
    } catch (Throwable) {
        return [];
    }

    return $profiles;
}

function plugin_brandpulse_valid_profile_id(int $profileId, array $profiles): int
{
    if (isset($profiles[$profileId])) {
        return $profileId;
    }

    $activeProfileId = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
    if (isset($profiles[$activeProfileId])) {
        return $activeProfileId;
    }

    return (int) (array_key_first($profiles) ?? 0);
}

function plugin_brandpulse_catalog_uses_only_public_searches(array $catalog): bool
{
    foreach ($catalog as $definition) {
        if (($definition['source_type'] ?? 'preset') !== 'saved_search') {
            continue;
        }

        $savedSearch = new SavedSearch();
        if (
            !$savedSearch->getFromDB((int) ($definition['savedsearches_id'] ?? 0))
            || (int) ($savedSearch->fields['is_private'] ?? 1) !== 0
            || (int) ($savedSearch->fields['type'] ?? 0) !== SavedSearch::SEARCH
            || !$savedSearch->canViewItem()
        ) {
            return false;
        }
    }

    return true;
}

function plugin_brandpulse_external_url(string $path): string
{
    return 'https://github.com/TiniSys-IT-Solutions/glpi-brandpulse/' . ltrim($path, '/');
}

function plugin_brandpulse_brand_asset_fields(): array
{
    return [
        'favicon' => [
            'section' => 'identity',
            'label' => 'Favicon',
            'column' => 'col-md-6',
            'extensions' => ['svg', 'png', 'ico'],
            'accept' => 'image/svg+xml,image/png,image/x-icon,.ico',
            'dimensions' => 'Square icon. Recommended source: 64 x 64 px or SVG.',
        ],
        'logo_sidebar_expanded_light' => [
            'section' => 'sidebar',
            'label' => 'Expanded sidebar logo, light theme',
            'column' => 'col-xl-4 col-md-6',
            'extensions' => ['svg', 'png', 'webp'],
            'accept' => 'image/svg+xml,image/png,image/webp',
            'dimensions' => 'Displayed by BrandPulse at up to 180 x 68 px. Recommended source: 360 x 136 px, SVG, PNG or WebP.',
        ],
        'logo_sidebar_expanded_dark' => [
            'section' => 'sidebar',
            'label' => 'Expanded sidebar logo, dark theme',
            'column' => 'col-xl-4 col-md-6',
            'extensions' => ['svg', 'png', 'webp'],
            'accept' => 'image/svg+xml,image/png,image/webp',
            'dimensions' => 'Displayed by BrandPulse at up to 180 x 68 px. Recommended source: 360 x 136 px, SVG, PNG or WebP.',
        ],
        'logo_sidebar_expanded_grey' => [
            'section' => 'sidebar',
            'label' => 'Expanded sidebar logo, neutral theme',
            'column' => 'col-xl-4 col-md-6',
            'extensions' => ['svg', 'png', 'webp'],
            'accept' => 'image/svg+xml,image/png,image/webp',
            'dimensions' => 'Displayed by BrandPulse at up to 180 x 68 px. Recommended source: 360 x 136 px, SVG, PNG or WebP.',
        ],
        'logo_sidebar_collapsed_light' => [
            'section' => 'sidebar',
            'label' => 'Collapsed sidebar logo, light theme',
            'column' => 'col-xl-4 col-md-6',
            'extensions' => ['svg', 'png', 'webp'],
            'accept' => 'image/svg+xml,image/png,image/webp',
            'dimensions' => 'Displayed by BrandPulse at 52 x 52 px. Recommended source: 104 x 104 px, SVG, PNG or WebP.',
        ],
        'logo_sidebar_collapsed_dark' => [
            'section' => 'sidebar',
            'label' => 'Collapsed sidebar logo, dark theme',
            'column' => 'col-xl-4 col-md-6',
            'extensions' => ['svg', 'png', 'webp'],
            'accept' => 'image/svg+xml,image/png,image/webp',
            'dimensions' => 'Displayed by BrandPulse at 52 x 52 px. Recommended source: 104 x 104 px, SVG, PNG or WebP.',
        ],
        'logo_sidebar_collapsed_grey' => [
            'section' => 'sidebar',
            'label' => 'Collapsed sidebar logo, neutral theme',
            'column' => 'col-xl-4 col-md-6',
            'extensions' => ['svg', 'png', 'webp'],
            'accept' => 'image/svg+xml,image/png,image/webp',
            'dimensions' => 'Displayed by BrandPulse at 52 x 52 px. Recommended source: 104 x 104 px, SVG, PNG or WebP.',
        ],
        'login_logo_light' => [
            'section' => 'login',
            'label' => 'Login logo, light theme',
            'column' => 'col-xl-4 col-md-6',
            'extensions' => ['svg', 'png', 'webp'],
            'accept' => 'image/svg+xml,image/png,image/webp',
            'dimensions' => 'Displayed by GLPI 11 at 200 x 110 px. Recommended source: 400 x 220 px, SVG, PNG or WebP.',
        ],
        'login_logo_dark' => [
            'section' => 'login',
            'label' => 'Login logo, dark theme',
            'column' => 'col-xl-4 col-md-6',
            'extensions' => ['svg', 'png', 'webp'],
            'accept' => 'image/svg+xml,image/png,image/webp',
            'dimensions' => 'Displayed by GLPI 11 at 200 x 110 px. Recommended source: 400 x 220 px, SVG, PNG or WebP.',
        ],
        'login_logo_grey' => [
            'section' => 'login',
            'label' => 'Login logo, neutral theme',
            'column' => 'col-xl-4 col-md-6',
            'extensions' => ['svg', 'png', 'webp'],
            'accept' => 'image/svg+xml,image/png,image/webp',
            'dimensions' => 'Displayed by GLPI 11 at 200 x 110 px. Recommended source: 400 x 220 px, SVG, PNG or WebP.',
        ],
        'login_background' => [
            'section' => 'login',
            'label' => 'Login background',
            'column' => 'col-md-12',
            'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
            'accept' => 'image/jpeg,image/png,image/webp',
            'dimensions' => 'Recommended source: 1920 x 1080 px, 16:9.',
        ],
    ];
}

function plugin_brandpulse_brand_asset_sections(): array
{
    return [
        'identity' => ['title' => 'Identity', 'description' => 'Browser title, favicon and global identity assets.'],
        'sidebar' => ['title' => 'Sidebar logos', 'description' => 'Theme-aware logos for expanded and collapsed GLPI navigation.'],
        'login' => ['title' => 'Login page', 'description' => 'Login logos and background image.'],
    ];
}

function plugin_brandpulse_clean_upload_name(string $name, string $field, string $extension): string
{
    $base = pathinfo($name, PATHINFO_FILENAME);
    $base = strtolower((string) preg_replace('/[^a-zA-Z0-9_-]+/', '-', $base));
    $base = trim($base, '-_');
    if ($base === '') {
        $base = $field;
    }

    return $field . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '-' . substr($base, 0, 60) . '.' . $extension;
}

function plugin_brandpulse_unsupported_image_error(string $label): RuntimeException
{
    return new RuntimeException(sprintf(__('Unsupported image type for "%s".', 'brandpulse'), __($label, 'brandpulse')));
}

function plugin_brandpulse_validate_svg_upload(string $tmpName, string $label): void
{
    $content = (string) file_get_contents($tmpName);
    if ($content === '' || str_contains($content, '<!DOCTYPE')) {
        throw plugin_brandpulse_unsupported_image_error($label);
    }

    $previous = libxml_use_internal_errors(true);
    $document = new DOMDocument();
    $loaded = $document->loadXML($content, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded || strtolower((string) $document->documentElement?->localName) !== 'svg') {
        throw plugin_brandpulse_unsupported_image_error($label);
    }

    $blockedElements = ['script', 'foreignobject', 'iframe', 'object', 'embed', 'audio', 'video'];
    foreach ($document->getElementsByTagName('*') as $element) {
        if (in_array(strtolower($element->localName), $blockedElements, true)) {
            throw plugin_brandpulse_unsupported_image_error($label);
        }

        foreach ($element->attributes ?? [] as $attribute) {
            $name = strtolower($attribute->localName);
            $value = html_entity_decode(trim($attribute->value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (str_starts_with($name, 'on')) {
                throw plugin_brandpulse_unsupported_image_error($label);
            }
            if (in_array($name, ['href', 'src'], true) && preg_match('/^\s*(?:javascript:|data:|https?:|\/\/)/i', $value)) {
                throw plugin_brandpulse_unsupported_image_error($label);
            }
            if ($name === 'style' && preg_match('/(?:url\s*\(|expression\s*\(|@import)/i', $value)) {
                throw plugin_brandpulse_unsupported_image_error($label);
            }
        }
    }
}

function plugin_brandpulse_import_brand_asset(string $field, array $fieldConfig): ?string
{
    $file = $_FILES[$field . '_upload'] ?? null;
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $label = (string) $fieldConfig['label'];
    $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException(sprintf(__('Image upload failed for "%s".', 'brandpulse'), __($label, 'brandpulse')));
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException(sprintf(__('Image upload failed for "%s".', 'brandpulse'), __($label, 'brandpulse')));
    }

    $maxSize = 8 * 1024 * 1024;
    if ((int) ($file['size'] ?? 0) > $maxSize) {
        throw new RuntimeException(sprintf(__('The image is too large for "%s".', 'brandpulse'), __($label, 'brandpulse')));
    }

    $originalName = (string) ($file['name'] ?? 'brand-image');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = is_array($fieldConfig['extensions'] ?? null) ? $fieldConfig['extensions'] : [];
    if (!in_array($extension, $allowedExtensions, true)) {
        throw plugin_brandpulse_unsupported_image_error($label);
    }

    $mime = class_exists(finfo::class)
        ? (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmpName)
        : '';
    $allowedMimes = [
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'ico' => ['image/x-icon', 'image/vnd.microsoft.icon', 'application/octet-stream'],
        'svg' => ['image/svg+xml', 'text/xml', 'application/xml', 'text/plain'],
    ];
    if ($mime !== '' && !in_array($mime, $allowedMimes[$extension] ?? [], true)) {
        throw plugin_brandpulse_unsupported_image_error($label);
    }

    if ($extension === 'svg') {
        plugin_brandpulse_validate_svg_upload($tmpName, $label);
    } elseif ($extension !== 'ico' && @getimagesize($tmpName) === false) {
        throw plugin_brandpulse_unsupported_image_error($label);
    }

    $uploadDir = BrandAssetStore::brandDirectory();
    if (!BrandAssetStore::ensureBrandDirectory()) {
        throw new RuntimeException(__('Unable to create the Brand upload directory.', 'brandpulse'));
    }

    $filename = plugin_brandpulse_clean_upload_name($originalName, $field, $extension);
    $destination = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($tmpName, $destination)) {
        throw new RuntimeException(sprintf(__('Unable to store the uploaded image for "%s".', 'brandpulse'), __($label, 'brandpulse')));
    }

    @chmod($destination, 0644);

    return BrandAssetStore::assetUrl($filename);
}

function plugin_brandpulse_brand_asset_filename(string $value): ?string
{
    $parts = parse_url($value);
    if (!is_array($parts) || !str_ends_with((string) ($parts['path'] ?? ''), '/plugins/brandpulse/front/asset.php')) {
        return null;
    }

    parse_str((string) ($parts['query'] ?? ''), $query);
    $file = (string) ($query['file'] ?? '');

    return $file !== '' ? basename(str_replace('\\', '/', $file)) : null;
}

function plugin_brandpulse_brand_asset_status(string $value): array
{
    $value = trim($value);
    if ($value === '') {
        return ['class' => 'secondary', 'label' => 'Not configured', 'detail' => ''];
    }

    $filename = plugin_brandpulse_brand_asset_filename($value);
    if ($filename !== null) {
        return BrandAssetStore::assetPath($filename) !== null
            ? ['class' => 'success', 'label' => 'Stored file OK', 'detail' => $filename]
            : ['class' => 'danger', 'label' => 'Stored file missing', 'detail' => $filename];
    }

    return ['class' => 'info', 'label' => 'External URL or path', 'detail' => $value];
}

function plugin_brandpulse_icon_field(string $name, array $options, string $selected): string
{
    $isKnown = array_key_exists($selected, $options);
    $value = $selected !== '' ? $selected : BrandpulseConfig::DEFAULT_PULSE_ICON;
    $path = str_starts_with($value, 'pulse:') ? substr($value, 6) : '';
    $url = $path !== '' ? plugin_brandpulse_web_base() . '/ajax/icon.php?file=' . rawurlencode($path) : '';
    $fallbackLabel = $path !== '' ? str_replace('/', ' / ', preg_replace('/\.svg$/', '', $path)) : __('Custom icon', 'brandpulse');
    $label = $isKnown ? (string) $options[$selected] : $fallbackLabel;

    $html = "<div class='brandpulse-icon-field' data-icon-field>";
    $html .= "<input type='hidden' data-icon-value name='" . plugin_brandpulse_h($name) . "' value='" . plugin_brandpulse_h($value) . "'>";
    $html .= "<button type='button' class='btn btn-sm btn-outline-secondary brandpulse-icon-open' data-icon-open>";
    $html .= "<span class='brandpulse-icon-preview' data-icon-preview style='-webkit-mask-image: url(&quot;" . plugin_brandpulse_h($url) . "&quot;); mask-image: url(&quot;" . plugin_brandpulse_h($url) . "&quot;);'></span>";
    $html .= "<span data-icon-label>" . plugin_brandpulse_h($label) . '</span>';
    $html .= '</button>';
    $html .= '</div>';

    return $html;
}

function plugin_brandpulse_blank_pulse_counter(): array
{
    return [
        'key' => '',
        'label' => '',
        'icon' => BrandpulseConfig::DEFAULT_PULSE_ICON,
        'color' => '#3b82f6',
        'enabled' => false,
        'source_type' => 'saved_search',
        'savedsearches_id' => 0,
        'warning_threshold' => 0,
        'critical_threshold' => 0,
    ];
}

function plugin_brandpulse_pulse_row(
    string|int $index,
    array $counter,
    array $presetCounters,
    array $savedSearches,
    array $icons,
    array $sourceTypes
): string {
    $sourceType = (string) ($counter['source_type'] ?? 'preset');
    $savedSearchId = (int) ($counter['savedsearches_id'] ?? 0);
    $presetKey = array_key_exists((string) ($counter['key'] ?? ''), $presetCounters) ? (string) $counter['key'] : 'my_tasks';
    $iconValue = (string) ($counter['icon'] ?? BrandpulseConfig::DEFAULT_PULSE_ICON);

    $html = "<tr data-pulse-row>";
    $html .= "<td><input class='form-check-input' type='checkbox' name='counters[{$index}][enabled]' value='1'" . (!empty($counter['enabled']) ? ' checked' : '') . '></td>';
    $html .= '<td>' . plugin_brandpulse_text_input("counters[{$index}][label]", (string) ($counter['label'] ?? ''), 'text', 'form-control form-control-sm') . plugin_brandpulse_text_input("counters[{$index}][key]", (string) ($counter['key'] ?? ''), 'hidden') . '</td>';
    $html .= '<td>' . str_replace('<select ', '<select data-pulse-source ', plugin_brandpulse_select("counters[{$index}][source_type]", $sourceTypes, $sourceType)) . '</td>';
    $html .= "<td class='brandpulse-target-cell'>";
    $html .= "<div data-pulse-target='preset'>" . plugin_brandpulse_select("counters[{$index}][preset_key]", $presetCounters, $presetKey) . '</div>';
    $html .= "<div data-pulse-target='saved_search'>" . plugin_brandpulse_select("counters[{$index}][savedsearches_id]", $savedSearches, $savedSearchId, false) . '</div>';
    $html .= '</td>';
    $html .= '<td>' . plugin_brandpulse_icon_field("counters[{$index}][icon]", $icons, $iconValue) . '</td>';
    $html .= "<td><input class='form-control form-control-sm form-control-color' type='color' name='counters[{$index}][color]' value='" . plugin_brandpulse_h((string) ($counter['color'] ?? '#3b82f6')) . "'></td>";
    $html .= "<td><input class='form-control form-control-sm' type='number' min='0' name='counters[{$index}][warning_threshold]' value='" . (int) ($counter['warning_threshold'] ?? 0) . "'></td>";
    $html .= "<td><input class='form-control form-control-sm' type='number' min='0' name='counters[{$index}][critical_threshold]' value='" . (int) ($counter['critical_threshold'] ?? 0) . "'></td>";
    $html .= "<td class='brandpulse-order-cell'><div class='btn-group btn-group-sm' role='group' aria-label='" . __s('Order', 'brandpulse') . "'>";
    $html .= "<button class='btn btn-outline-secondary' type='button' data-pulse-move='up' title='" . __s('Move up', 'brandpulse') . "' aria-label='" . __s('Move up', 'brandpulse') . "'>&uarr;</button>";
    $html .= "<button class='btn btn-outline-secondary' type='button' data-pulse-move='down' title='" . __s('Move down', 'brandpulse') . "' aria-label='" . __s('Move down', 'brandpulse') . "'>&darr;</button>";
    $html .= '</div></td>';
    $html .= '</tr>';

    return $html;
}

function plugin_brandpulse_text_input(string $name, string $value, string $type = 'text', string $class = 'form-control'): string
{
    return "<input class='" . plugin_brandpulse_h($class) . "' type='" . plugin_brandpulse_h($type) . "' name='" . plugin_brandpulse_h($name) . "' value='" . plugin_brandpulse_h($value) . "'>";
}

function plugin_brandpulse_brand_asset_input(string $name, array $fieldConfig, string $value): string
{
    $label = (string) $fieldConfig['label'];
    $columnClass = (string) $fieldConfig['column'];
    $id = 'brandpulse_' . preg_replace('/[^a-z0-9_]+/', '_', $name);
    $fileId = $id . '_upload';
    $accept = (string) ($fieldConfig['accept'] ?? 'image/*');
    $extensions = implode(', ', array_map(static fn ($extension): string => '.' . $extension, $fieldConfig['extensions'] ?? []));
    $dimensions = trim((string) ($fieldConfig['dimensions'] ?? ''));
    $previewUrl = trim($value);
    $status = plugin_brandpulse_brand_asset_status($value);

    $html = "<div class='" . plugin_brandpulse_h($columnClass) . "'>";
    $html .= "<div class='brandpulse-brand-field'>";
    $html .= "<div class='brandpulse-brand-field-head'>";
    $html .= "<label class='form-label' for='" . plugin_brandpulse_h($id) . "'>" . __s($label, 'brandpulse') . '</label>';
    $html .= "<span class='badge bg-" . plugin_brandpulse_h($status['class']) . "'>" . __s($status['label'], 'brandpulse') . '</span>';
    $html .= '</div>';
    if ($previewUrl !== '') {
        $html .= "<div class='brandpulse-brand-preview'>";
        $html .= "<img src='" . plugin_brandpulse_h($previewUrl) . "' alt='" . __s($label, 'brandpulse') . "'>";
        $html .= "<code title='" . plugin_brandpulse_h($previewUrl) . "'>" . plugin_brandpulse_h($previewUrl) . '</code>';
        $html .= '</div>';
    }
    $html .= "<div class='brandpulse-brand-field-controls'>";
    $html .= "<input class='form-control' id='" . plugin_brandpulse_h($id) . "' type='text' name='" . plugin_brandpulse_h($name) . "' value='" . plugin_brandpulse_h($value) . "' placeholder='" . __s('URL or path', 'brandpulse') . "'>";
    $html .= "<input class='form-control form-control-sm brandpulse-brand-upload' id='" . plugin_brandpulse_h($fileId) . "' type='file' name='" . plugin_brandpulse_h($name . '_upload') . "' accept='" . plugin_brandpulse_h($accept) . "'>";
    $html .= '</div>';
    $html .= "<div class='brandpulse-brand-field-meta'>";
    if ($dimensions !== '') {
        $html .= '<span>' . __s($dimensions, 'brandpulse') . '</span>';
    }
    $html .= '<span>' . sprintf(__s('Accepted formats: %s', 'brandpulse'), plugin_brandpulse_h($extensions)) . '</span>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

function plugin_brandpulse_brand_nav(array $sections): string
{
    $html = "<nav class='brandpulse-brand-nav' aria-label='" . __s('Brand', 'brandpulse') . "'>";

    foreach (['identity', 'sidebar', 'login'] as $sectionKey) {
        if (!isset($sections[$sectionKey])) {
            continue;
        }

        $section = $sections[$sectionKey];
        $html .= "<a href='#brandpulse-brand-" . plugin_brandpulse_h($sectionKey) . "'>";
        $html .= '<strong>' . __s((string) $section['title'], 'brandpulse') . '</strong>';
        $html .= '<span>' . __s((string) $section['description'], 'brandpulse') . '</span>';
        $html .= '</a>';
    }

    return $html . '</nav>';
}

function plugin_brandpulse_brand_section(string $id, string $title, string $description, string $content, bool $open = false): string
{
    return "<details class='brandpulse-brand-section' id='brandpulse-brand-" . plugin_brandpulse_h($id) . "'" . ($open ? ' open' : '') . '>'
        . "<summary class='brandpulse-brand-section-head'><span><strong>" . __s($title, 'brandpulse') . "</strong><small>" . __s($description, 'brandpulse') . "</small></span><i class='ti ti-chevron-down' aria-hidden='true'></i></summary>"
        . "<div class='brandpulse-brand-section-content'>" . $content . '</div>'
        . '</details>';
}

function plugin_brandpulse_brand_diagnostic(array $fields, array $branding): string
{
    $html = "<div class='table-responsive'><table class='table table-sm align-middle brandpulse-brand-diagnostic mb-0'>";
    $html .= '<thead><tr><th>' . __s('Asset', 'brandpulse') . '</th><th>' . __s('Status', 'brandpulse') . '</th><th>' . __s('URL or path', 'brandpulse') . '</th></tr></thead><tbody>';

    foreach ($fields as $field => $fieldConfig) {
        $value = (string) ($branding[$field] ?? '');
        $status = plugin_brandpulse_brand_asset_status($value);
        $detail = $status['detail'] !== '' ? $status['detail'] : $value;
        $html .= '<tr>';
        $html .= '<td>' . __s((string) $fieldConfig['label'], 'brandpulse') . '</td>';
        $html .= "<td><span class='badge bg-" . plugin_brandpulse_h($status['class']) . "'>" . __s($status['label'], 'brandpulse') . '</span></td>';
        $html .= '<td><code>' . plugin_brandpulse_h($detail) . '</code></td>';
        $html .= '</tr>';
    }

    return $html . '</tbody></table></div>';
}


$tab = (string) ($_GET['tab'] ?? $_POST['tab'] ?? 'brand');
$tab = in_array($tab, ['brand', 'alert', 'pulse', 'diagnostic'], true) ? $tab : 'brand';
$errors = [];
$profiles = plugin_brandpulse_profile_rows();
$selectedProfileId = plugin_brandpulse_valid_profile_id(
    (int) ($_POST['profile_id'] ?? $_GET['profile_id'] ?? 0),
    $profiles
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($tab === 'brand') {
        $assetFields = plugin_brandpulse_brand_asset_fields();
        $postedBranding = BrandpulseConfig::values()['branding'];
        $postedBranding['enabled'] = isset($_POST['brand_enabled']);
        $postedBranding['title'] = (string) ($_POST['title'] ?? '');

        foreach ($assetFields as $field => $fieldConfig) {
            $postedBranding[(string) $field] = (string) ($_POST[(string) $field] ?? '');

            try {
                $uploadedUrl = plugin_brandpulse_import_brand_asset((string) $field, $fieldConfig);
                if ($uploadedUrl !== null) {
                    $postedBranding[(string) $field] = $uploadedUrl;
                }
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        $postedBranding['menu_logo'] = (string) ($postedBranding['logo_sidebar_expanded_light'] ?? '');
        $postedBranding['login_logo'] = (string) ($postedBranding['login_logo_light'] ?? '');

        if ($errors === []) {
            BrandpulseConfig::saveBranding($postedBranding);
            Session::addMessageAfterRedirect(__('Brand settings updated.', 'brandpulse'));
            Html::redirect(plugin_brandpulse_config_url('brand'));
        }
    }

    if ($tab === 'alert') {
        $postedBranding = BrandpulseConfig::values()['branding'];
        $postedBranding['enabled'] = isset($_POST['brand_enabled']);
        $postedBranding['login_alert_type'] = (string) ($_POST['login_alert_type'] ?? 'info');
        $postedBranding['login_alert_icon'] = (string) ($_POST['login_alert_icon'] ?? BrandpulseConfig::DEFAULT_PULSE_ICON);
        $postedBranding['login_alert_expanded'] = isset($_POST['login_alert_expanded']);
        $postedBranding['login_alert_message'] = (string) ($_POST['login_alert_message'] ?? '');
        $postedBranding['login_alert_enabled'] = trim($postedBranding['login_alert_message']) !== '';

        BrandpulseConfig::saveBranding($postedBranding);
        Session::addMessageAfterRedirect(__('Login alert settings updated.', 'brandpulse'));
        Html::redirect(plugin_brandpulse_config_url('alert'));
    }

    if ($tab === 'pulse') {
        $postedCounters = is_array($_POST['counters'] ?? null) ? $_POST['counters'] : [];
        $counters = [];

        foreach ($postedCounters as $row) {
            if (!is_array($row)) {
                continue;
            }

            $sourceType = (string) ($row['source_type'] ?? 'preset');
            $savedSearchId = (int) ($row['savedsearches_id'] ?? 0);
            $key = $sourceType === 'saved_search'
                ? 'saved_search_' . $savedSearchId
                : (string) ($row['preset_key'] ?? '');
            $icon = (string) ($row['icon'] ?? BrandpulseConfig::DEFAULT_PULSE_ICON);

            $counters[] = [
                'key' => $key,
                'label' => (string) ($row['label'] ?? ''),
                'icon' => $icon,
                'color' => (string) ($row['color'] ?? '#3b82f6'),
                'enabled' => isset($row['enabled']),
                'source_type' => $sourceType,
                'savedsearches_id' => $savedSearchId,
                'warning_threshold' => (int) ($row['warning_threshold'] ?? 0),
                'critical_threshold' => (int) ($row['critical_threshold'] ?? 0),
            ];
        }

        $normalizedCounters = BrandpulseConfig::normalizeCounters($counters);
        $submittedAssignments = is_array($_POST['profile_counters'] ?? null)
            ? $_POST['profile_counters']
            : [];
        $normalizedAssignments = ProfileCounterStore::normalizeSubmittedAssignments(
            $submittedAssignments,
            $normalizedCounters
        );
        $activeAssignmentCount = count(array_filter(
            $normalizedAssignments,
            static fn (array $assignment): bool => $assignment['is_active']
        ));

        if ($selectedProfileId <= 0) {
            $errors[] = __('Select a valid GLPI profile.', 'brandpulse');
        }
        $maxProfileCounters = BrandpulseConfig::normalizeMaxProfileCounters(
            (int) ($_POST['max_profile_counters'] ?? PulseResolver::DEFAULT_MAX_PROFILE_COUNTERS)
        );
        if ($activeAssignmentCount > $maxProfileCounters) {
            $errors[] = sprintf(
                __('A profile can display at most %d Pulse counters.', 'brandpulse'),
                $maxProfileCounters
            );
        }
        if (!plugin_brandpulse_catalog_uses_only_public_searches($normalizedCounters)) {
            $errors[] = __('Profile counters can only use public, available GLPI saved searches.', 'brandpulse');
        }

        if ($errors === []) {
            try {
                ProfileCounterStore::saveProfile(
                    $selectedProfileId,
                    $normalizedAssignments,
                    $normalizedCounters,
                    $maxProfileCounters
                );
                BrandpulseConfig::savePulse(
                    isset($_POST['enabled']),
                    (int) ($_POST['refresh_interval'] ?? 60),
                    isset($_POST['compact_search_enabled']),
                    $normalizedCounters,
                    $maxProfileCounters
                );

                Session::addMessageAfterRedirect(__('Pulse settings updated.', 'brandpulse'));
                Html::redirect(plugin_brandpulse_config_url('pulse') . '&profile_id=' . $selectedProfileId);
            } catch (RuntimeException $exception) {
                $errors[] = __($exception->getMessage(), 'brandpulse');
            }
        }
    }
}

$config = BrandpulseConfig::values();
$branding = $config['branding'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($tab, ['brand', 'alert'], true) && $errors !== [] && isset($postedBranding)) {
    $branding = BrandpulseConfig::normalizeBranding($postedBranding);
}
$counters = array_values($config['counters']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tab === 'pulse' && $errors !== [] && isset($normalizedCounters)) {
    $config['enabled'] = isset($_POST['enabled']);
    $config['refresh_interval'] = max(15, (int) ($_POST['refresh_interval'] ?? 60));
    $config['max_profile_counters'] = BrandpulseConfig::normalizeMaxProfileCounters(
        (int) ($_POST['max_profile_counters'] ?? PulseResolver::DEFAULT_MAX_PROFILE_COUNTERS)
    );
    $config['compact_search_enabled'] = isset($_POST['compact_search_enabled']);
    $counters = $normalizedCounters;
}
$profileAssignments = isset($normalizedAssignments) && $errors !== []
    ? $normalizedAssignments
    : ProfileCounterStore::normalizeSubmittedAssignments(
        ProfileCounterStore::assignmentsForProfile($selectedProfileId, $counters),
        $counters
    );
$profileDefinitions = [];
foreach ($counters as $definition) {
    $profileDefinitions[(string) ($definition['key'] ?? '')] = $definition;
}
$resolvedProfileCounters = PulseResolver::resolve(
    $counters,
    $profileAssignments,
    [],
    $config['max_profile_counters']
);
$presetCounters = BrandpulseConfig::presetCounters();
$savedSearches = plugin_brandpulse_saved_search_rows();
$icons = BrandpulseConfig::pulseIcons();
$sourceTypes = [
    'preset' => 'GLPI preset',
    'saved_search' => 'GLPI saved search',
];
$alertTypes = [
    'info' => 'Info',
    'warning' => 'Warning',
    'danger' => 'Danger',
    'success' => 'Success',
];

Html::header(__('GLPI BrandPulse', 'brandpulse'), plugin_brandpulse_config_url($tab), 'config', 'plugins');

echo "<div class='brandpulse-config'>";
echo '<h1>' . __s('GLPI BrandPulse', 'brandpulse') . '</h1>';

echo "<ul class='nav nav-tabs mb-3 brandpulse-tabs'>";
echo "<li class='nav-item'><a class='nav-link" . ($tab === 'brand' ? ' active' : '') . "' href='" . plugin_brandpulse_h(plugin_brandpulse_config_url('brand')) . "'><i class='ti ti-palette'></i><span>" . __s('Brand', 'brandpulse') . '</span></a></li>';
echo "<li class='nav-item'><a class='nav-link" . ($tab === 'alert' ? ' active' : '') . "' href='" . plugin_brandpulse_h(plugin_brandpulse_config_url('alert')) . "'><i class='ti ti-message-exclamation'></i><span>" . __s('Login alert', 'brandpulse') . '</span></a></li>';
echo "<li class='nav-item'><a class='nav-link" . ($tab === 'pulse' ? ' active' : '') . "' href='" . plugin_brandpulse_h(plugin_brandpulse_config_url('pulse')) . "'><i class='ti ti-activity'></i><span>" . __s('Pulse', 'brandpulse') . '</span></a></li>';
echo "<li class='nav-item'><a class='nav-link" . ($tab === 'diagnostic' ? ' active' : '') . "' href='" . plugin_brandpulse_h(plugin_brandpulse_config_url('diagnostic')) . "'><i class='ti ti-stethoscope'></i><span>" . __s('Diagnostic', 'brandpulse') . '</span></a></li>';
echo '</ul>';

echo '<form method="post" enctype="multipart/form-data" action="' . plugin_brandpulse_h(plugin_brandpulse_config_url($tab)) . '">';
echo Html::hidden(plugin_brandpulse_csrf_token_name(), ['value' => Session::getNewCSRFToken()]);
echo Html::hidden('tab', ['value' => $tab]);
echo "<input type='hidden' name='MAX_FILE_SIZE' value='8388608'>";

if ($errors !== []) {
    echo "<div class='alert alert-danger'>";
    foreach ($errors as $error) {
        echo '<div>' . plugin_brandpulse_h($error) . '</div>';
    }
    echo '</div>';
}

if ($tab === 'brand') {
    $assetFields = plugin_brandpulse_brand_asset_fields();
    $sections = plugin_brandpulse_brand_asset_sections();

    echo "<div class='brandpulse-brand-layout'>";

    echo "<div class='brandpulse-brand-enable-panel" . ($branding['enabled'] ? ' is-enabled' : '') . "'>";
    echo "<div><strong>" . __s('Brand customizations', 'brandpulse') . '</strong>';
    echo '<span>' . __s('This switch must be enabled before logos, favicon, login background and login alert are applied in GLPI.', 'brandpulse') . '</span></div>';
    echo "<div class='form-check form-switch mb-0'>";
    echo "<input class='form-check-input' id='brand_enabled' type='checkbox' name='brand_enabled' value='1'" . ($branding['enabled'] ? ' checked' : '') . '> ';
    echo "<label class='form-check-label' for='brand_enabled'>" . ($branding['enabled'] ? __s('Brand is active', 'brandpulse') : __s('Brand is inactive', 'brandpulse')) . '</label>';
    echo '</div>';
    echo '</div>';

    $identityContent = "<div class='row g-3'>";
    $identityContent .= "<div class='col-md-6'><div class='brandpulse-brand-field'><div class='brandpulse-brand-field-head'><label class='form-label'>" . __s('Browser title', 'brandpulse') . '</label></div>' . plugin_brandpulse_text_input('title', (string) $branding['title']) . '</div></div>';
    $identityContent .= plugin_brandpulse_brand_asset_input('favicon', $assetFields['favicon'], (string) $branding['favicon']);
    $identityContent .= '</div>';

    echo "<div class='brandpulse-brand-workspace'>";
    echo plugin_brandpulse_brand_nav($sections);
    echo "<div class='brandpulse-brand-main'>";

    echo plugin_brandpulse_brand_section('identity', $sections['identity']['title'], $sections['identity']['description'], $identityContent, true);

    $sidebarContent = "<div class='row g-3'>";
    foreach ($assetFields as $field => $fieldConfig) {
        if (($fieldConfig['section'] ?? '') === 'sidebar') {
            $sidebarContent .= plugin_brandpulse_brand_asset_input((string) $field, $fieldConfig, (string) $branding[$field]);
        }
    }
    $sidebarContent .= '</div>';
    echo plugin_brandpulse_brand_section('sidebar', $sections['sidebar']['title'], $sections['sidebar']['description'], $sidebarContent);

    $loginContent = "<div class='row g-3'>";
    foreach ($assetFields as $field => $fieldConfig) {
        if (($fieldConfig['section'] ?? '') === 'login') {
            $loginContent .= plugin_brandpulse_brand_asset_input((string) $field, $fieldConfig, (string) $branding[$field]);
        }
    }
    $loginContent .= '</div>';
    echo plugin_brandpulse_brand_section('login', $sections['login']['title'], $sections['login']['description'], $loginContent);

    echo '</div>';
    echo '</div>';
    echo '</div>';
} elseif ($tab === 'alert') {
    echo "<div class='brandpulse-brand-enable-panel" . ($branding['enabled'] ? ' is-enabled' : '') . "'>";
    echo "<div><strong>" . __s('Login alert', 'brandpulse') . '</strong>';
    echo '<span>' . __s('The login alert is applied on anonymous GLPI pages when Brand customizations are active.', 'brandpulse') . '</span></div>';
    echo "<div class='form-check form-switch mb-0'>";
    echo "<input class='form-check-input' id='brand_enabled' type='checkbox' name='brand_enabled' value='1'" . ($branding['enabled'] ? ' checked' : '') . '> ';
    echo "<label class='form-check-label' for='brand_enabled'>" . ($branding['enabled'] ? __s('Brand is active', 'brandpulse') : __s('Brand is inactive', 'brandpulse')) . '</label>';
    echo '</div>';
    echo '</div>';

    echo "<section class='brandpulse-brand-section'>";
    echo "<div class='brandpulse-brand-section-head'><strong>" . __s('Alert message', 'brandpulse') . '</strong><span>' . __s('Message, icon and expansion behavior for the login page.', 'brandpulse') . '</span></div>';
    echo "<div class='brandpulse-brand-alert'>";
    echo "<div class='brandpulse-alert-grid'>";
    echo "<div class='brandpulse-alert-switches'>";
    echo "<div class='form-check form-switch'>";
    echo "<input class='form-check-input' id='login_alert_expanded' type='checkbox' name='login_alert_expanded' value='1'" . ($branding['login_alert_expanded'] ? ' checked' : '') . '> ';
    echo "<label class='form-check-label' for='login_alert_expanded'>" . __s('Open long alert by default', 'brandpulse') . '</label>';
    echo '</div>';
    echo '</div>';
    echo "<div><label class='form-label'>" . __s('Alert type', 'brandpulse') . '</label>' . plugin_brandpulse_select('login_alert_type', $alertTypes, (string) $branding['login_alert_type']) . '</div>';
    echo "<div><label class='form-label'>" . __s('Alert icon', 'brandpulse') . '</label>' . plugin_brandpulse_icon_field('login_alert_icon', $icons, (string) $branding['login_alert_icon']) . '</div>';
    echo '</div>';
    echo "<label class='form-label mt-3'>" . __s('Login alert message', 'brandpulse') . '</label>';
    echo "<div class='brandpulse-alert-toolbar' role='toolbar' aria-label='" . __s('Message formatting', 'brandpulse') . "'>";
    echo "<button class='btn btn-outline-secondary btn-sm' type='button' data-alert-format='heading' title='" . __s('Heading', 'brandpulse') . "'><i class='ti ti-heading'></i></button>";
    echo "<button class='btn btn-outline-secondary btn-sm' type='button' data-alert-format='bold' title='" . __s('Bold', 'brandpulse') . "'><i class='ti ti-bold'></i></button>";
    echo "<button class='btn btn-outline-secondary btn-sm' type='button' data-alert-format='list' title='" . __s('Bullet list', 'brandpulse') . "'><i class='ti ti-list'></i></button>";
    echo "<button class='btn btn-outline-secondary btn-sm' type='button' data-alert-format='code' title='" . __s('Code', 'brandpulse') . "'><i class='ti ti-code'></i></button>";
    echo "<button class='btn btn-outline-secondary btn-sm' type='button' data-alert-format='link' title='" . __s('Link', 'brandpulse') . "'><i class='ti ti-link'></i></button>";
    echo '</div>';
    echo "<textarea class='form-control brandpulse-alert-message' name='login_alert_message' rows='7'>" . plugin_brandpulse_h((string) $branding['login_alert_message']) . '</textarea>';
    echo "<div class='form-text'>" . __s('Use short paragraphs, headings with #, bullet lines with -, **bold** and `code` for a richer message.', 'brandpulse') . '</div>';
    echo '</div>';
    echo '</section>';
} elseif ($tab === 'pulse') {
    echo "<div class='brandpulse-brand-enable-panel" . ($config['enabled'] ? ' is-enabled' : '') . "'>";
    echo "<div><strong>" . __s('Pulse counters', 'brandpulse') . '</strong>';
    echo '<span>' . __s('This switch must be enabled before Pulse counters and compact search are applied in the GLPI header.', 'brandpulse') . '</span></div>';
    echo "<div class='form-check form-switch mb-0'>";
    echo "<input class='form-check-input' id='enabled' type='checkbox' name='enabled' value='1'" . ($config['enabled'] ? ' checked' : '') . '> ';
    echo "<label class='form-check-label' for='enabled'>" . ($config['enabled'] ? __s('Pulse is active', 'brandpulse') : __s('Pulse is inactive', 'brandpulse')) . '</label>';
    echo '</div>';
    echo '</div>';

    echo "<div class='card mb-3 brandpulse-defaults-card'>";
    echo "<div class='card-header'><strong>" . __s('Default settings', 'brandpulse') . '</strong></div>';
    echo "<div class='card-body brandpulse-defaults-body'>";
    echo "<label class='brandpulse-refresh-control'><span>" . __s('Counter refresh interval, in seconds', 'brandpulse') . "</span><input class='form-control' type='number' min='15' name='refresh_interval' value='" . (int) $config['refresh_interval'] . "'></label>";
    echo "<label class='brandpulse-profile-limit-control'><span>" . __s('Maximum counters per profile', 'brandpulse') . "</span><span class='brandpulse-range-line'><input class='form-range' type='range' min='6' max='10' step='1' name='max_profile_counters' value='" . (int) $config['max_profile_counters'] . "' data-profile-counter-limit><output data-profile-counter-limit-value>" . (int) $config['max_profile_counters'] . '</output></span></label>';
    echo "<label class='brandpulse-search-control'>";
    echo "<span class='brandpulse-option-label'><span class='brandpulse-search-option-icon' aria-hidden='true'></span><span>" . __s('Minimize the global search field to a magnifier icon', 'brandpulse') . '</span></span>';
    echo "<input class='form-check-input' id='compact_search_enabled' type='checkbox' name='compact_search_enabled' value='1'" . ($config['compact_search_enabled'] ? ' checked' : '') . '> ';
    echo '</label>';
    echo '</div>';
    echo '</div>';

    echo "<div class='alert alert-info d-flex align-items-start gap-2' role='status'>";
    echo "<i class='ti ti-shield-check mt-1' aria-hidden='true'></i><div>";
    echo '<strong>' . __s('Profile-based visibility', 'brandpulse') . '</strong><br>';
    echo __s('General Pulse visibility is managed from the BrandPulse tab of each GLPI profile.', 'brandpulse');
    echo '</div></div>';

    echo "<div class='card mb-3 brandpulse-pulse-card'>";
    echo "<div class='card-header brandpulse-pulse-toolbar'>";
    echo "<div class='brandpulse-pulse-title'><strong>" . __s('Counter catalog', 'brandpulse') . "</strong><span class='badge bg-secondary' data-pulse-row-count>" . count($counters) . '</span></div>';
    echo "<div class='brandpulse-pulse-controls'>";
    echo "<button class='btn btn-outline-secondary brandpulse-pulse-action' type='button' data-pulse-add title='" . __s('Add Pulse counter', 'brandpulse') . "' aria-label='" . __s('Add Pulse counter', 'brandpulse') . "'><i class='ti ti-plus'></i></button>";
    echo "<button class='btn btn-primary brandpulse-pulse-action' type='submit'><i class='ti ti-device-floppy'></i><span>" . __s('Save', 'brandpulse') . '</span></button>';
    echo '</div>';
    echo '</div>';

    echo "<div class='table-responsive'>";
    echo "<table class='table table-sm align-middle mb-0 brandpulse-pulse-table' data-pulse-table>";
    echo '<thead><tr>';
    echo '<th>' . __s('Default', 'brandpulse') . '</th>';
    echo '<th>' . __s('Label', 'brandpulse') . '</th>';
    echo '<th>' . __s('Source', 'brandpulse') . '</th>';
    echo '<th>' . __s('Target', 'brandpulse') . '</th>';
    echo '<th>' . __s('Icon', 'brandpulse') . '</th>';
    echo '<th>' . __s('Color', 'brandpulse') . '</th>';
    echo '<th>' . __s('Warning', 'brandpulse') . '</th>';
    echo '<th>' . __s('Critical', 'brandpulse') . '</th>';
    echo '<th>' . __s('Order', 'brandpulse') . '</th>';
    echo '</tr></thead><tbody data-pulse-rows>';

    foreach ($counters as $index => $counter) {
        echo plugin_brandpulse_pulse_row($index, $counter, $presetCounters, $savedSearches, $icons, $sourceTypes);
    }

    echo '</tbody></table>';
    echo '<template data-pulse-row-template>' . plugin_brandpulse_pulse_row('__INDEX__', plugin_brandpulse_blank_pulse_counter(), $presetCounters, $savedSearches, $icons, $sourceTypes) . '</template>';
    echo '</div>';
    echo "<div class='card-footer text-muted'>" . __s('Use public GLPI saved searches or presets. The default state is inherited by new profiles until their assignment is saved. Save a new catalog counter before assigning it below.', 'brandpulse') . '</div>';
    echo '</div>';

    echo "<section class='card mb-3 brandpulse-profile-pulse-card'>";
    echo "<div class='card-header brandpulse-profile-pulse-header'>";
    echo '<div><strong>' . __s('Profile assignment', 'brandpulse') . '</strong>';
    echo '<span>' . __s('Choose the counters displayed when this GLPI profile is active.', 'brandpulse') . '</span></div>';
    echo "<label><span class='visually-hidden'>" . __s('GLPI profile', 'brandpulse') . "</span>";
    echo str_replace(
        '<select ',
        "<select data-pulse-profile-select data-base-url='" . plugin_brandpulse_h(plugin_brandpulse_config_url('pulse')) . "' ",
        plugin_brandpulse_select('profile_id', $profiles, $selectedProfileId, false)
    );
    echo '</label></div>';

    if ($profiles === []) {
        echo "<div class='card-body'><div class='alert alert-warning mb-0'>";
        echo __s('No GLPI profile is available.', 'brandpulse') . '</div></div>';
    } else {
        echo "<div class='card-body'>";
        echo "<div class='alert alert-info d-flex align-items-start gap-2'>";
        echo "<i class='ti ti-info-circle mt-1' aria-hidden='true'></i><div>";
        echo sprintf(
            __s('Select up to %d counters. Required counters are reserved for the future personal-favourites mode and cannot later be hidden by users.', 'brandpulse'),
            $config['max_profile_counters']
        );
        echo '</div></div>';
        echo "<div class='table-responsive'><table class='table table-sm align-middle mb-0 brandpulse-profile-pulse-table'>";
        echo '<thead><tr><th>' . __s('Visible', 'brandpulse') . '</th>';
        echo '<th>' . __s('Counter', 'brandpulse') . '</th>';
        echo '<th>' . __s('Source', 'brandpulse') . '</th>';
        echo '<th>' . __s('Required', 'brandpulse') . '</th>';
        echo '<th>' . __s('Order', 'brandpulse') . '</th></tr></thead>';
        echo '<tbody data-profile-counter-rows>';
        foreach ($profileAssignments as $index => $assignment) {
            $definition = $profileDefinitions[(string) $assignment['counter_key']] ?? null;
            if (is_array($definition)) {
                echo plugin_brandpulse_profile_counter_row($index, $assignment, $definition);
            }
        }
        echo '</tbody></table></div>';

        echo "<div class='brandpulse-profile-preview'>";
        echo '<strong>' . __s('Header preview', 'brandpulse') . '</strong>';
        echo "<div class='brandpulse-profile-preview-counters' data-profile-counter-preview>";
        if ($resolvedProfileCounters === []) {
            echo "<span class='text-muted'>" . __s('No counter is enabled for this profile.', 'brandpulse') . '</span>';
        } else {
            foreach ($resolvedProfileCounters as $definition) {
                $path = str_starts_with((string) $definition['icon'], 'pulse:')
                    ? substr((string) $definition['icon'], 6)
                    : substr(BrandpulseConfig::DEFAULT_PULSE_ICON, 6);
                $iconUrl = plugin_brandpulse_web_base() . '/ajax/icon.php?file=' . rawurlencode($path);
                echo "<span class='brandpulse-profile-preview-counter'>";
                echo "<span class='brandpulse-icon-preview' style='-webkit-mask-image:url(&quot;"
                    . plugin_brandpulse_h($iconUrl) . "&quot;);mask-image:url(&quot;"
                    . plugin_brandpulse_h($iconUrl) . "&quot;)' aria-hidden='true'></span>";
                echo plugin_brandpulse_h((string) $definition['label']);
                if (!empty($definition['is_mandatory'])) {
                    echo " <i class='ti ti-lock' aria-label='" . __s('Required', 'brandpulse') . "'></i>";
                }
                echo '</span>';
            }
        }
        echo '</div></div>';
        echo '</div>';
        echo "<div class='card-footer d-flex justify-content-between align-items-center gap-2 flex-wrap'>";
        echo '<span class="text-muted" data-profile-counter-count data-count-template="'
            . plugin_brandpulse_h(__s('%d counters currently displayed for this profile.', 'brandpulse')) . '">' . sprintf(
            __s('%d counters currently displayed for this profile.', 'brandpulse'),
            count($resolvedProfileCounters)
        ) . '</span>';
        echo "<button class='btn btn-primary' type='submit'><i class='ti ti-device-floppy'></i><span>"
            . __s('Save Pulse and profile', 'brandpulse') . '</span></button>';
        echo '</div>';
    }
    echo '</section>';
} else {
    $assetFields = plugin_brandpulse_brand_asset_fields();

    echo "<div class='brandpulse-diagnostic-grid'>";
    echo "<div class='brandpulse-diagnostic-card'><strong>" . __s('Plugin version', 'brandpulse') . '</strong><span>' . plugin_brandpulse_h(PLUGIN_BRANDPULSE_VERSION) . '</span></div>';
    echo "<div class='brandpulse-diagnostic-card'><strong>" . __s('Installed schema', 'brandpulse') . '</strong><span>' . plugin_brandpulse_h(BrandpulseConfig::schemaVersion() ?: __('not initialized', 'brandpulse')) . '</span></div>';
    echo "<div class='brandpulse-diagnostic-card'><strong>" . __s('Brand status', 'brandpulse') . '</strong><span>' . ($branding['enabled'] ? __s('Enabled', 'brandpulse') : __s('Disabled', 'brandpulse')) . '</span></div>';
    echo "<div class='brandpulse-diagnostic-card'><strong>" . __s('Pulse status', 'brandpulse') . '</strong><span>' . ($config['enabled'] ? __s('Enabled', 'brandpulse') : __s('Disabled', 'brandpulse')) . '</span></div>';
    echo '</div>';

    echo "<div class='brandpulse-diagnostic-links'>";
    echo "<a class='btn btn-outline-secondary' target='_blank' rel='noopener' href='" . plugin_brandpulse_h(plugin_brandpulse_external_url('')) . "'><i class='ti ti-home-2'></i><span>" . __s('Homepage', 'brandpulse') . '</span></a>';
    echo "<a class='btn btn-outline-secondary' target='_blank' rel='noopener' href='" . plugin_brandpulse_h(plugin_brandpulse_external_url('issues')) . "'><i class='ti ti-bug'></i><span>" . __s('Get help', 'brandpulse') . '</span></a>';
    echo "<a class='btn btn-outline-secondary' target='_blank' rel='noopener' href='" . plugin_brandpulse_h(plugin_brandpulse_external_url('blob/main/README.md')) . "'><i class='ti ti-book'></i><span>" . __s('Readme', 'brandpulse') . '</span></a>';
    echo '</div>';

    echo "<details class='brandpulse-brand-diagnostic-box' open>";
    echo '<summary><strong>' . __s('Stored files', 'brandpulse') . '</strong><span>' . plugin_brandpulse_h(BrandAssetStore::brandDirectory()) . '</span></summary>';
    echo plugin_brandpulse_brand_diagnostic($assetFields, $branding);
    echo '</details>';
}

function plugin_brandpulse_profile_counter_row(string|int $index, array $assignment, array $definition): string
{
    $key = (string) ($definition['key'] ?? $assignment['counter_key'] ?? '');
    $source = ($definition['source_type'] ?? 'preset') === 'saved_search'
        ? __('GLPI saved search', 'brandpulse')
        : __('GLPI preset', 'brandpulse');

    $iconPath = str_starts_with((string) ($definition['icon'] ?? ''), 'pulse:')
        ? substr((string) $definition['icon'], 6)
        : substr(BrandpulseConfig::DEFAULT_PULSE_ICON, 6);
    $iconUrl = plugin_brandpulse_web_base() . '/ajax/icon.php?file=' . rawurlencode($iconPath);
    $html = "<tr data-profile-counter-row data-counter-label='"
        . plugin_brandpulse_h((string) ($definition['label'] ?? $key))
        . "' data-counter-icon-url='" . plugin_brandpulse_h($iconUrl) . "'>";
    $html .= '<td>' . plugin_brandpulse_text_input(
        "profile_counters[{$index}][counter_key]",
        $key,
        'hidden'
    );
    $html .= "<input class='form-check-input' type='checkbox' name='profile_counters[{$index}][is_active]' value='1'"
        . (!empty($assignment['is_active']) ? ' checked' : '') . '></td>';
    $html .= '<td><strong>' . plugin_brandpulse_h((string) ($definition['label'] ?? $key)) . '</strong>';
    $html .= '<small class="d-block text-muted">' . plugin_brandpulse_h($key) . '</small></td>';
    $html .= '<td>' . plugin_brandpulse_h($source) . '</td>';
    $html .= "<td><input class='form-check-input' type='checkbox' name='profile_counters[{$index}][is_mandatory]' value='1'"
        . (!empty($assignment['is_mandatory']) ? ' checked' : '')
        . " aria-label='" . __s('Required profile counter', 'brandpulse') . "'></td>";
    $html .= "<td class='brandpulse-order-cell'><div class='btn-group btn-group-sm' role='group' aria-label='" . __s('Order', 'brandpulse') . "'>";
    $html .= "<button class='btn btn-outline-secondary' type='button' data-profile-counter-move='up' aria-label='" . __s('Move up', 'brandpulse') . "'>&uarr;</button>";
    $html .= "<button class='btn btn-outline-secondary' type='button' data-profile-counter-move='down' aria-label='" . __s('Move down', 'brandpulse') . "'>&darr;</button>";
    $html .= '</div></td></tr>';

    return $html;
}

if (in_array($tab, ['brand', 'alert'], true)) {
    echo "<div class='brandpulse-action-bar'><button class='btn btn-primary' type='submit'><i class='ti ti-device-floppy'></i><span>" . __s('Save', 'brandpulse') . '</span></button></div>';
}
echo '</form>';
if (in_array($tab, ['alert', 'pulse'], true)) {
    echo "<div class='brandpulse-icon-modal' data-icon-modal hidden>";
    echo "<div class='brandpulse-icon-modal-backdrop' data-icon-close></div>";
    echo "<div class='brandpulse-icon-dialog' role='dialog' aria-modal='true' aria-label='" . __s('Choose an icon', 'brandpulse') . "'>";
    echo "<div class='brandpulse-icon-dialog-header'>";
    echo '<strong>' . __s('Choose an icon', 'brandpulse') . '</strong>';
    echo "<button type='button' class='btn-close' data-icon-close aria-label='" . __s('Close', 'brandpulse') . "'></button>";
    echo '</div>';
    echo "<div class='brandpulse-icon-dialog-tools'>";
    echo "<input class='form-control form-control-sm' type='search' data-icon-search placeholder='" . __s('Search', 'brandpulse') . "'>";
    echo "<select class='form-select form-select-sm' data-icon-category aria-label='" . __s('Category', 'brandpulse') . "'>";
    echo "<option value=''>" . __s('All categories', 'brandpulse') . '</option>';
    echo '</select>';
    echo '</div>';
    echo "<div class='brandpulse-icon-results' data-icon-results></div>";
    echo "<div class='brandpulse-icon-dialog-footer'>";
    echo "<button type='button' class='btn btn-sm btn-outline-secondary' data-icon-prev>" . __s('Previous', 'brandpulse') . '</button>';
    echo "<span class='text-muted' data-icon-page></span>";
    echo "<button type='button' class='btn btn-sm btn-outline-secondary' data-icon-next>" . __s('Next', 'brandpulse') . '</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}
echo '</div>';

Html::footer();
