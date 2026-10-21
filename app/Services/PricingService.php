<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Resolves the price a given customer group (retail / wholesale / agent)
 * must pay for a product variant.
 *
 * Precedence:
 * 1. Quantity tier row for the group (highest matching min_qty wins)
 * 2. Active promotion window on that row (sale_price_cents)
 * 3. No row for the group => the product is not offered to that group
 */
class PricingService
{
    public const GROUP_IDS = [
        'retail'    => 1,
        'wholesale' => 2,
        'agent'     => 3,
    ];

    /**
     * Storefront role names -> customer group id.
     * The shop speaks "normal", the database speaks "retail".
     */
    public const ROLE_GROUP_IDS = [
        'normal'    => 1,
        'wholesale' => 2,
        'agent'     => 3,
    ];

    public const GROUP_LABELS = [
        1 => 'Retail',
        2 => 'Wholesale',
        3 => 'Service Agent',
    ];

    /**
     * Map a group slug to its primary key.
     */
    public static function groupIdFor(?string $slug): int
    {
        return self::GROUP_IDS[$slug ?? 'retail']
            ?? self::ROLE_GROUP_IDS[$slug ?? 'normal']
            ?? 1;
    }

    /**
     * Reverse lookup: customer group id -> storefront role name.
     */
    public static function roleKeyFor(int $groupId): ?string
    {
        $key = array_search($groupId, self::ROLE_GROUP_IDS, true);

        return $key === false ? null : $key;
    }

    /**
     * Resolve the price for one variant + one customer group.
     */
    public static function priceFor(int $variantId, int $groupId, int $qty = 1): ?array
    {
        $row = DB::table('product_prices')
            ->where('variant_id', $variantId)
            ->where('customer_group_id', $groupId)
            ->where('min_qty', '<=', max(1, $qty))
            ->orderByDesc('min_qty')
            ->first();

        if (!$row) {
            return null;
        }

        return self::applySaleWindow($row, $groupId);
    }

    /**
     * Resolve every group price for a variant.
     * Groups without a price row are simply absent from the result.
     *
     * @return array<int, array{price_cents:int,original_price_cents:int,gst_cents:int,is_sale:bool,min_qty:int}>
     */
    public static function pricesForVariant(int $variantId): array
    {
        $rows = DB::table('product_prices')
            ->where('variant_id', $variantId)
            ->orderBy('customer_group_id')
            ->orderBy('min_qty')
            ->get();

        $resolved = [];

        foreach ($rows as $row) {
            // Keep the highest min_qty tier per group.
            if (isset($resolved[$row->customer_group_id])) {
                continue;
            }

            $resolved[$row->customer_group_id] = self::applySaleWindow($row, $row->customer_group_id);
        }

        return $resolved;
    }

    /**
     * Apply the promotion window, if the row carries one.
     */
    private static function applySaleWindow(object $row, int $groupId): array
    {
        $now = now();
        $priceCents = (int) $row->price_cents;
        $originalCents = $priceCents;
        $isSale = false;

        $hasSalePrice = !empty($row->sale_price_cents);
        $started = empty($row->sale_starts_at) || $now->gte($row->sale_starts_at);
        $notEnded = empty($row->sale_ends_at) || $now->lte($row->sale_ends_at);

        if ($hasSalePrice && $started && $notEnded) {
            $priceCents = (int) $row->sale_price_cents;
            $isSale = true;
        }

        return [
            'price_cents'          => $priceCents,
            'original_price_cents' => $originalCents,
            'gst_cents'            => (int) round($priceCents / 11),
            'is_sale'              => $isSale,
            'min_qty'              => (int) $row->min_qty,
            'customer_group_id'    => $groupId,
        ];
    }

    /**
     * Australian GST (10% inclusive): GST component = Total / 11.
     */
    public static function gstCents(int $priceCents): int
    {
        return (int) round($priceCents / 11);
    }

    /**
     * Format cents into an Australian Dollar string.
     */
    public static function formatAud(int $cents): string
    {
        return '$' . number_format($cents / 100, 2);
    }
}
