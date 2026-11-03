<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse\Tests;

use GlpiPlugin\Brandpulse\BrandAssetStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class BrandAssetStoreTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testPublicAssetResolutionRejectsUnrelatedFilesAndEscapingSymlinks(): void
    {
        $directory = sys_get_temp_dir() . '/brandpulse-assets-' . bin2hex(random_bytes(8));
        define('GLPI_PLUGIN_DOC_DIR', $directory);
        self::assertTrue(BrandAssetStore::ensureBrandDirectory());
        $brand = BrandAssetStore::brandDirectory();
        file_put_contents($brand . '/logo.svg', '<svg/>');
        file_put_contents($brand . '/backup.sql', 'private fixture');
        file_put_contents($brand . '/.hidden.svg', 'private fixture');
        file_put_contents($directory . '/outside.svg', 'outside fixture');
        symlink($directory . '/outside.svg', $brand . '/link.svg');
        try {
            self::assertSame($brand . '/logo.svg', BrandAssetStore::assetPath('logo.svg'));
            foreach (['backup.sql', '.hidden.svg', '../logo.svg', 'x\\logo.svg',
                      "logo.svg\0", 'link.svg', 'missing.svg'] as $file) {
                self::assertNull(BrandAssetStore::assetPath($file), $file);
            }
        } finally {
            foreach (['logo.svg', 'backup.sql', '.hidden.svg', 'link.svg'] as $file) unlink($brand . '/' . $file);
            unlink($directory . '/outside.svg');
            rmdir($brand);
            rmdir(dirname($brand));
            rmdir($directory);
        }
    }

    public function testStaticSvgWithLocalReferencesAndStylesIsAccepted(): void
    {
        self::assertTrue(BrandAssetStore::isSafeSvg('<svg xmlns="http://www.w3.org/2000/svg">
            <style>.logo {fill: red}</style><defs><path id="logo" d="M0 0L10 10"/>
            <linearGradient id="gradient"/></defs><use href="#logo" fill="url(#gradient)"/>
            </svg>'));
    }

    #[DataProvider('unsafeSvgSamples')]
    public function testActiveOrExternalSvgContentIsRejected(string $svg): void
    {
        self::assertFalse(BrandAssetStore::isSafeSvg($svg));
    }

    public static function unsafeSvgSamples(): iterable
    {
        yield ['<svg'];
        yield ['<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg">&x;</svg>'];
        yield ['<svg xmlns="http://www.w3.org/1999/xhtml"/>'];
        yield ['<?xml-stylesheet type="text/css" href="https://example.test/style.css"?><svg xmlns="http://www.w3.org/2000/svg"/>'];
        foreach ([
            '<script>alert(1)</script>',
            '<path onload="alert(1)"/>',
            '<style>@import "https://example.test/style.css";</style>',
            '<style>path {fill: u\\72l(https://example.test/image)}</style>',
            '<image href="external.svg"/>',
            '<image href="data:image/svg+xml,evil"/>',
            '<use href="https://example.test/image.svg#x"/>',
            '<path fill="url(https://example.test/image)"/>',
            '<path fill="u\\72l(https://example.test/image)"/>',
            '<path style="fill:url(#x)"/>',
            '<animate attributeName="href" to="javascript:alert(1)"/>',
            '<set attributeName="href" to="https://example.test/image.svg"/>',
            '<foreignObject><div xmlns="http://www.w3.org/1999/xhtml"/></foreignObject>',
        ] as $content) {
            yield ['<svg xmlns="http://www.w3.org/2000/svg">' . $content . '</svg>'];
        }
        yield ['<svg xmlns="http://www.w3.org/2000/svg" xml:base="https://example.test/"><use href="#x"/></svg>'];
    }
}
