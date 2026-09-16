<?php

declare(strict_types=1);

/**
 * Compile the plugin's simple gettext PO catalogues into GNU MO files.
 *
 * BrandPulse catalogues intentionally use singular messages without contexts.
 */

function decodePoString(string $value): string
{
    $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

    return is_string($decoded) ? $decoded : '';
}

function readPoCatalogue(string $path): array
{
    $messages = [];
    $entry = ['msgid' => null, 'msgstr' => null];
    $activeField = null;

    $commit = static function () use (&$messages, &$entry, &$activeField): void {
        if (is_string($entry['msgid']) && is_string($entry['msgstr'])) {
            $messages[$entry['msgid']] = $entry['msgstr'];
        }
        $entry = ['msgid' => null, 'msgstr' => null];
        $activeField = null;
    };

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if ($line === '') {
            $commit();
            continue;
        }
        if (str_starts_with($line, 'msgid ')) {
            $entry['msgid'] = decodePoString(substr($line, 6));
            $activeField = 'msgid';
            continue;
        }
        if (str_starts_with($line, 'msgstr ')) {
            $entry['msgstr'] = decodePoString(substr($line, 7));
            $activeField = 'msgstr';
            continue;
        }
        if ($activeField !== null && str_starts_with($line, '"')) {
            $entry[$activeField] .= decodePoString($line);
        }
    }
    $commit();
    ksort($messages, SORT_STRING);

    return $messages;
}

function compileMo(array $messages): string
{
    $count = count($messages);
    $originalTableOffset = 28;
    $translationTableOffset = $originalTableOffset + ($count * 8);
    $originalPoolOffset = $translationTableOffset + ($count * 8);
    $originalPool = '';
    $translationPool = '';
    $originalTable = '';
    $translationTable = '';

    foreach (array_keys($messages) as $message) {
        $originalTable .= pack('V2', strlen($message), $originalPoolOffset + strlen($originalPool));
        $originalPool .= $message . "\0";
    }

    $translationPoolOffset = $originalPoolOffset + strlen($originalPool);
    foreach ($messages as $translation) {
        $translationTable .= pack('V2', strlen($translation), $translationPoolOffset + strlen($translationPool));
        $translationPool .= $translation . "\0";
    }

    return pack(
        'V7',
        0x950412de,
        0,
        $count,
        $originalTableOffset,
        $translationTableOffset,
        0,
        0
    ) . $originalTable . $translationTable . $originalPool . $translationPool;
}

$localeDirectory = dirname(__DIR__) . '/locales';
foreach (glob($localeDirectory . '/*.po') ?: [] as $poPath) {
    $moPath = substr($poPath, 0, -3) . '.mo';
    $contents = compileMo(readPoCatalogue($poPath));
    if (file_put_contents($moPath, $contents) === false) {
        throw new RuntimeException('Unable to write ' . $moPath);
    }
    echo basename($moPath) . ': ' . count(readPoCatalogue($poPath)) . " messages\n";
}
