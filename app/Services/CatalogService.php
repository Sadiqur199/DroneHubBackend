<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reads the catalogue and applies customer-group visibility + pricing rules.
 *
 * Visibility model
 * ----------------
 * A product is offered to a customer group when BOTH conditions hold:
 *   1. At least one of its variants has a price row for that group, and
 *   2. If the product has explicit `visibility_rules`, the group is listed there.
 *
 * This means "set a wholesale price for this product" is what makes it visible
 * to wholesale buyers — there is no second, contradictory switch to keep in sync.
 */
class CatalogService
{
    /**
     * Map a category slug to the storefront `type` label.
     */
    public static function typeForSlug(?string $slug): string
    {
        return match ($slug) {
            'drones'      => 'drone',
            'parts'       => 'part',
            'accessories' => 'accessory',
            'combos'      => 'combo',
            default       => 'part',
        };
    }

    /**
     * Build the storefront product list for the requesting customer group.
     */
    public static function listFor(Request $request, int $groupId, bool $isAdmin): array
    {
        $category = $request->query('category');
        $subcategory = $request->query('subcategory');
        $type = $request->query('type');
        $search = $request->query('search');

        $query = DB::table('products')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->whereNull('products.deleted_at')
            ->where('products.status', 'active')
            ->select('products.*', 'categories.name as category_name', 'categories.slug as category_slug');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                  ->orWhere('products.model_number', 'like', "%{$search}%")
                  ->orWhere('products.short_description', 'like', "%{$search}%");
            });
        }

        if ($category && $category !== 'all') {
            $query->where(function ($q) use ($category) {
                $q->where('categories.slug', $category)
                  ->orWhere('categories.name', 'like', "%{$category}%");
            });
        }

        if ($subcategory && $subcategory !== 'all') {
            $query->where('products.subcategory', $subcategory);
        }

        if ($type && $type !== 'all') {
            $query->where('categories.type', $type);
        }

        $products = $query->orderByDesc('products.id')->get();

        if ($products->isEmpty()) {
            return [];
        }

        $productIds = $products->pluck('id')->all();
        $variantRows = DB::table('product_variants')
            ->whereNull('deleted_at')
            ->whereIn('product_id', $productIds)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->groupBy('product_id');

        // Which groups may see each product?
        $visibility = DB::table('visibility_rules')
            ->where('visible_type', 'product')
            ->whereIn('visible_id', $productIds)
            ->get()
            ->groupBy('visible_id');

        $droneNames = DB::table('products')
            ->whereIn('id', array_filter($products->pluck('drone_id')->all()))
            ->pluck('name', 'id');

        $out = [];

        foreach ($products as $product) {
            $variants = $variantRows->get($product->id, collect());

            if ($variants->isEmpty()) {
                continue;
            }

            // Price rows for every variant of this product.
            $variantIds = $variants->pluck('id')->all();
            $priceRows = DB::table('product_prices')
                ->whereIn('variant_id', $variantIds)
                ->get();

            $pricesByVariant = [];
            foreach ($priceRows as $price) {
                if (!isset($pricesByVariant[$price->variant_id])) {
                    $pricesByVariant[$price->variant_id] = [];
                }
                $pricesByVariant[$price->variant_id][] = $price;
            }

            // Groups that have at least one price on this product.
            $pricedGroups = $priceRows->pluck('customer_group_id')->unique()->values();

            // Explicit visibility rules narrow it further when present.
            $rules = $visibility->get($product->id);
            $allowedGroups = $rules && $rules->isNotEmpty()
                ? $rules->pluck('customer_group_id')->unique()->values()
                : $pricedGroups;

            // Retailer products are always available to all (guest, retail, wholesale, agent).
            $isAllowed = $isAdmin 
                || $allowedGroups->contains($groupId) 
                || $allowedGroups->contains(1) 
                || $pricedGroups->contains(1);

            if (!$isAllowed) {
                continue;   // this group may not see this product at all
            }

            $resolved = self::resolveGroupPrice($variants, $pricesByVariant, $groupId);

            // If wholesale (group 2) or agent (group 3) has no specific price tier, derive from retail price
            if ($resolved === null && ($groupId === 2 || $groupId === 3)) {
                $retailPrice = self::resolveGroupPrice($variants, $pricesByVariant, 1);
                if ($retailPrice !== null) {
                    $discountFactor = ($groupId === 2) ? 0.88 : 0.80;
                    $tierCents = (int) round($retailPrice['price_cents'] * $discountFactor);
                    $resolved = [
                        'price_cents'          => $tierCents,
                        'original_price_cents' => $retailPrice['original_price_cents'],
                        'gst_cents'            => (int) round($tierCents / 11),
                        'is_sale'              => $retailPrice['is_sale'],
                    ];
                }
            }

            if ($resolved === null && $isAdmin) {
                // Admins must be able to manage every product, even ones that
                // are not yet priced for their own group.
                $resolved = self::resolveAnyPrice($variants, $pricesByVariant);
            }

            if ($resolved === null) {
                continue;   // visible by rule but no price for this group
            }

            $out[] = self::format($product, $variants->first(), $resolved, $groupId, $isAdmin, $droneNames);
        }

        return $out;
    }

    /**
     * Resolve the price for one group across the product's variants.
     */
    private static function resolveGroupPrice($variants, array $pricesByVariant, int $groupId): ?array
    {
        foreach ($variants as $variant) {
            $rows = $pricesByVariant[$variant->id] ?? [];

            foreach ($rows as $row) {
                if ((int) $row->customer_group_id !== $groupId) {
                    continue;
                }

                $price = PricingService::priceFor($variant->id, $groupId);

                if ($price) {
                    return $price + ['variant' => $variant];
                }
            }
        }

        return null;
    }

    /**
     * Fallback used for admins: any configured price on the product.
     */
    private static function resolveAnyPrice($variants, array $pricesByVariant): ?array
    {
        foreach ($variants as $variant) {
            foreach ($pricesByVariant[$variant->id] ?? [] as $row) {
                $price = PricingService::priceFor($variant->id, (int) $row->customer_group_id);

                if ($price) {
                    return $price + ['variant' => $variant];
                }
            }
        }

        return null;
    }

    /**
     * Shape a product for the storefront / admin payload.
     */
    public static function format(
        object $product,
        object $variant,
        array $price,
        int $groupId,
        bool $isAdmin,
        $droneNames = null
    ): array {
        $slug = $product->category_slug ?: 'parts';

        $payload = [
            'id'                 => 'p-' . $product->id,
            'raw_id'             => $product->id,
            'type'               => self::typeForSlug($slug),
            'name'               => $product->name,
            'sku'                => $variant->sku,
            'model_number'       => $product->model_number ?: $variant->sku,
            'brand'              => 'DroneHab Australia',
            'slug'               => $product->slug,
            'category'           => $slug,
            'category_name'      => $product->category_name ?: 'General Parts',
            'subcategory'        => $product->subcategory ?: 'General',
            'part_section'       => $product->subcategory ?: 'General',
            'price_cents'        => $price['price_cents'],
            'original_price_cents' => $price['original_price_cents'],
            'price_formatted'    => PricingService::formatAud($price['price_cents']),
            'is_sale'            => (bool) $price['is_sale'],
            'gst_cents'          => $price['gst_cents'],
            'stock_qty'          => (int) $variant->stock_qty,
            'stock_status'       => ((int) $variant->stock_qty) > 0 ? 'In Stock' : 'Out of Stock',
            'image_url'          => $product->image_url ?: '',
            'short_description'  => $product->short_description ?: '',
            'full_description'   => $product->description ?: '',
            'description'        => $product->description ?: '',
            'flight_time'        => $product->flight_time,
            'specs'              => json_decode($product->specs ?? '[]', true) ?: [],
            'drone_id'           => $product->drone_id ? 'p-' . $product->drone_id : null,
            'drone_name'         => $product->drone_id && $droneNames
                ? ($droneNames[$product->drone_id] ?? null)
                : null,
            'status'             => $product->status,
            'created_at'         => $product->created_at,
        ];

        $allPrices = PricingService::pricesForVariant($variant->id);

        $retailCents = $allPrices[1]['price_cents'] ?? ($groupId === 1 ? $price['price_cents'] : null);
        if ($retailCents === null) {
            $retailCents = $price['price_cents'];
        }
        $wholesaleCents = $allPrices[2]['price_cents'] ?? (int) round($retailCents * 0.88);
        $agentCents = $allPrices[3]['price_cents'] ?? (int) round($retailCents * 0.80);

        $rolePrices = [
            'normal'    => $retailCents,
            'wholesale' => $wholesaleCents,
            'agent'     => $agentCents,
        ];

        $payload['role_prices'] = $rolePrices;

        if (!$isAdmin) {
            return $payload;
        }

        $visibilityRules = DB::table('visibility_rules')
            ->where('visible_type', 'product')
            ->where('visible_id', $product->id)
            ->pluck('customer_group_id')
            ->unique()
            ->map(fn ($gid) => PricingService::roleKeyFor((int) $gid))
            ->filter()
            ->values()
            ->all();

        if (empty($visibilityRules)) {
            $visibilityRules = array_keys(array_filter(
                $rolePrices,
                fn ($price) => $price !== null
            ));
        }

        $payload['role_prices'] = $rolePrices;
        $payload['visibility_roles'] = $visibilityRules;
        $payload['discount_type'] = $price['is_sale'] ? 'percentage' : 'none';
        $payload['discount_value'] = 0;
        $payload['sale_price_cents'] = $price['price_cents'];

        return $payload;
    }
}
