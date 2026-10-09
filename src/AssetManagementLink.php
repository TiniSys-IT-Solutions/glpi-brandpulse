<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse;

final class AssetManagementLink
{
    /** @var list<string> */
    private const SUPPORTED_ITEMTYPES = [
        'Computer',
        'NetworkEquipment',
        'Printer',
        'Phone',
        'PDU',
    ];

    public static function supports(string $itemtype): bool
    {
        return in_array($itemtype, self::SUPPORTED_ITEMTYPES, true);
    }

    /** @return list<string> */
    public static function supportedItemtypes(): array
    {
        return self::SUPPORTED_ITEMTYPES;
    }

    /** @return list<string> */
    public static function normalizeItemtypes(array $itemtypes): array
    {
        $selected = [];
        foreach ($itemtypes as $itemtype) {
            $itemtype = (string) $itemtype;
            if (self::supports($itemtype)) {
                $selected[$itemtype] = $itemtype;
            }
        }

        return array_values($selected);
    }

    /**
     * Resolve the IP addresses displayed by GLPI through the asset's network
     * ports. The asset permission check must happen before this method is used.
     *
     * @return list<string>
     */
    public static function addressesFor(string $itemtype, int $itemsId): array
    {
        global $DB;

        if (
            $itemsId <= 0
            || !self::supports($itemtype)
            || !isset($DB)
            || !method_exists($DB, 'request')
        ) {
            return [];
        }

        $portIds = self::idsFromRows($DB->request([
            'SELECT' => ['id'],
            'FROM' => \NetworkPort::getTable(),
            'WHERE' => [
                'itemtype' => $itemtype,
                'items_id' => $itemsId,
                'is_deleted' => 0,
            ],
        ]));
        if ($portIds === []) {
            return [];
        }

        $networkNameIds = self::idsFromRows($DB->request([
            'SELECT' => ['id'],
            'FROM' => \NetworkName::getTable(),
            'WHERE' => [
                'itemtype' => 'NetworkPort',
                'items_id' => $portIds,
                'is_deleted' => 0,
            ],
        ]));
        if ($networkNameIds === []) {
            return [];
        }

        $addresses = [];
        foreach ($DB->request([
            'SELECT' => ['name'],
            'FROM' => \IPAddress::getTable(),
            'WHERE' => [
                'itemtype' => 'NetworkName',
                'items_id' => $networkNameIds,
                'is_deleted' => 0,
            ],
        ]) as $row) {
            $address = self::normalizeAddress((string) ($row['name'] ?? ''));
            if ($address !== null) {
                $addresses[$address] = $address;
            }
        }

        $addresses = array_values($addresses);
        usort($addresses, static fn (string $left, string $right): int =>
            self::addressScore($left) <=> self::addressScore($right) ?: strnatcasecmp($left, $right)
        );

        return $addresses;
    }

    public static function normalizeAddress(string $address): ?string
    {
        $address = trim($address);
        if (str_starts_with($address, '[') && str_ends_with($address, ']')) {
            $address = substr($address, 1, -1);
        }

        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        // Canonicalize IPv6 so expanded loopback and mapped IPv4 cannot bypass
        // the unusable-address checks.
        $packed = inet_pton($address);
        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
            $packed = substr($packed, 12);
        }
        $address = inet_ntop($packed);

        if (
            $address === '0.0.0.0'
            || $address === '::'
            || $address === '::1'
            || str_starts_with($address, '127.')
            || (strlen($packed) === 16 && ord($packed[0]) === 0xfe && (ord($packed[1]) & 0xc0) === 0x80)
            || (strlen($packed) === 16 && ord($packed[0]) === 0xff)
            || (strlen($packed) === 4 && ord($packed[0]) >= 224)
        ) {
            return null;
        }

        return $address;
    }

    public static function url(string $address, string $scheme = 'https'): ?string
    {
        $address = self::normalizeAddress($address);
        if ($address === null || !in_array($scheme, ['https', 'http'], true)) {
            return null;
        }

        $host = str_contains($address, ':') ? '[' . $address . ']' : $address;
        return $scheme . '://' . $host . '/';
    }

    /** @return list<int> */
    private static function idsFromRows(iterable $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private static function addressScore(string $address): int
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $isPublic = filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false;
            return $isPublic ? 20 : 10;
        }

        return 30;
    }
}
