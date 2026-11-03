<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse;

final class BrandAssetStore
{
    public static function isSafeSvg(string $content): bool
    {
        if ($content === '' || stripos($content, '<!DOCTYPE') !== false) {
            return false;
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument();
            if (!$document->loadXML($content, LIBXML_NONET | LIBXML_NOBLANKS)) {
                return false;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($document->documentElement?->localName !== 'svg'
            || $document->documentElement->namespaceURI !== 'http://www.w3.org/2000/svg') {
            return false;
        }
        if ((new \DOMXPath($document))->query('//processing-instruction()')->length > 0) {
            return false;
        }

        // Animation can change a safe attribute into an external or active URL.
        $blocked = ['script', 'foreignobject', 'iframe', 'object', 'embed', 'audio', 'video',
            'animate', 'animatetransform', 'animatemotion', 'set', 'discard'];
        $activeCss = '/(?:url\s*\(|expression\s*\(|@import|\\\\)/i';
        foreach ($document->getElementsByTagName('*') as $element) {
            if ($element->namespaceURI !== 'http://www.w3.org/2000/svg'
                || in_array(strtolower($element->localName), $blocked, true)) {
                return false;
            }
            if (strtolower($element->localName) === 'style' && preg_match($activeCss, $element->textContent)) {
                return false;
            }
            foreach ($element->attributes ?? [] as $attribute) {
                $name = strtolower($attribute->localName);
                $value = trim($attribute->value);
                if (str_starts_with($name, 'on')
                    || str_contains($value, '\\')
                    || (in_array($name, ['href', 'src'], true) && $value !== '' && !str_starts_with($value, '#'))
                    || ($name === 'base')
                    || ($name === 'style' && preg_match($activeCss, $value))
                    || preg_match('/url\s*\(\s*["\']?\s*(?!#)[^\s"\')]/i', $value)) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function pluginDocumentDirectory(): string
    {
        if (defined('GLPI_PLUGIN_DOC_DIR')) {
            return rtrim((string) GLPI_PLUGIN_DOC_DIR, '/\\') . '/brandpulse';
        }

        if (defined('GLPI_DOC_DIR')) {
            return rtrim((string) GLPI_DOC_DIR, '/\\') . '/_plugins/brandpulse';
        }

        return dirname(__DIR__) . '/files/_plugins/brandpulse';
    }

    public static function brandDirectory(): string
    {
        return self::pluginDocumentDirectory() . '/brand';
    }

    public static function ensureBrandDirectory(): bool
    {
        $directory = self::brandDirectory();

        return is_dir($directory) || mkdir($directory, 0755, true) || is_dir($directory);
    }

    public static function assetUrl(string $filename): string
    {
        global $CFG_GLPI;

        return rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/')
            . '/plugins/brandpulse/front/asset.php?file='
            . rawurlencode($filename);
    }

    public static function assetPath(string $filename): ?string
    {
        // This directory is publicly readable through front/asset.php. Never
        // serve unrelated files accidentally placed beside the uploaded images.
        if ($filename === '' || str_contains($filename, "\0")
            || str_contains($filename, '/') || str_contains($filename, '\\')
            || str_starts_with($filename, '.')
            || !in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)),
                ['svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico'], true)) {
            return null;
        }

        $path = self::brandDirectory() . '/' . $filename;
        if (!is_file($path)) {
            return null;
        }

        $realPath = realpath($path);
        $realDirectory = realpath(self::brandDirectory());
        if ($realPath === false || $realDirectory === false || !str_starts_with($realPath, $realDirectory . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realPath;
    }
}
