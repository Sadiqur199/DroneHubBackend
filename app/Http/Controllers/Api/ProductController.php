<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CatalogService;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProductController extends Controller
{
    /**
     * Catalogue for the requesting customer group.
     * Retail / wholesale / agent each only receive their own products & prices.
     */
    public function index(Request $request)
    {
        $groupId = $this->groupId($request);
        $isAdmin = $this->isAdmin($request);

        $items = CatalogService::listFor($request, $groupId, $isAdmin);

        return response()->json([
            'success' => true,
            'count'   => count($items),
            'group'   => $request->attributes->get('customer_group_slug', 'retail'),
            'data'    => $items,
        ]);
    }

    /**
     * Single product detail.
     */
    public function show(Request $request, $id)
    {
        $cleanId = str_replace('p-', '', $id);
        $groupId = $this->groupId($request);
        $isAdmin = $this->isAdmin($request);

        $product = DB::table('products')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('products.id', $cleanId)
            ->whereNull('products.deleted_at')
            ->select('products.*', 'categories.name as category_name', 'categories.slug as category_slug')
            ->first();

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        $variant = DB::table('product_variants')
            ->where('product_id', $product->id)
            ->whereNull('deleted_at')
            ->orderBy('sort')
            ->orderBy('id')
            ->first();

        if (!$variant) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        $price = PricingService::priceFor($variant->id, $groupId);

        // Fallback for wholesale (group 2) or agent (group 3) from retail
        if (!$price && ($groupId === 2 || $groupId === 3)) {
            $retailPrice = PricingService::priceFor($variant->id, 1);
            if ($retailPrice) {
                $discountFactor = ($groupId === 2) ? 0.88 : 0.80;
                $tierCents = (int) round($retailPrice['price_cents'] * $discountFactor);
                $price = [
                    'price_cents'          => $tierCents,
                    'original_price_cents' => $retailPrice['original_price_cents'],
                    'gst_cents'            => (int) round($tierCents / 11),
                    'is_sale'              => $retailPrice['is_sale'],
                ];
            }
        }

        if (!$price && !$isAdmin) {
            $price = PricingService::priceFor($variant->id, 1);
        }

        if (!$price && !$isAdmin) {
            // This product is not offered to the caller's group.
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        if (!$price) {
            $any = PricingService::pricesForVariant($variant->id);
            $price = $any ? reset($any) : [
                'price_cents'          => 0,
                'original_price_cents' => 0,
                'gst_cents'            => 0,
                'is_sale'              => false,
            ];
        }

        $droneNames = $product->drone_id
            ? DB::table('products')->where('id', $product->drone_id)->pluck('name', 'id')
            : collect();

        $payload = CatalogService::format($product, $variant, $price, $groupId, $isAdmin, $droneNames);

        // Related parts for drones
        if ($payload['type'] === 'drone') {
            $payload['compatible_parts'] = DB::table('products')
                ->where('drone_id', $product->id)
                ->whereNull('deleted_at')
                ->get()
                ->map(function ($part) use ($groupId) {
                    $variantId = DB::table('product_variants')
                        ->where('product_id', $part->id)
                        ->whereNull('deleted_at')
                        ->orderBy('id')
                        ->value('id');

                    $price = $variantId ? PricingService::priceFor($variantId, $groupId) : null;

                    return [
                        'id'             => 'p-' . $part->id,
                        'name'           => $part->name,
                        'section'        => $part->subcategory ?: 'General',
                        'price_cents'    => $price['price_cents'] ?? null,
                        'price_formatted' => isset($price['price_cents'])
                            ? PricingService::formatAud($price['price_cents'])
                            : null,
                    ];
                })->values();
        }

        return response()->json([
            'success' => true,
            'data'    => $payload,
        ]);
    }

    /**
     * Admin create a product (drone / part / accessory / combo).
     */
    public function store(Request $request)
    {
        $validated = $this->validateProduct($request);

        if ($validated instanceof \Illuminate\Http\JsonResponse) {
            return $validated;
        }

        $productId = DB::transaction(function () use ($request, $validated) {
            $categoryId = $this->resolveCategoryId($validated['category']);

            $productId = DB::table('products')->insertGetId([
                'brand_id'          => null,
                'category_id'       => $categoryId,
                'subcategory'       => $validated['subcategory'],
                'type'              => 'simple',
                'name'              => $validated['name'],
                'slug'              => $this->uniqueSlug($validated['name']),
                'model_number'      => $validated['model_number'],
                'short_description' => $validated['short_description'],
                'description'       => $validated['full_description'],
                'image_url'         => $validated['image_url'],
                'flight_time'       => $validated['flight_time'],
                'drone_id'          => $validated['drone_id'],
                'specs'             => json_encode($validated['specs']),
                'status'            => 'active',
                'visibility_mode'   => 'public',
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            $variantId = DB::table('product_variants')->insertGetId([
                'product_id'   => $productId,
                'sku'          => $validated['sku'],
                'name'         => $validated['name'],
                'stock_qty'    => $validated['stock'],
                'status'       => 'active',
                'sort'         => 0,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            $this->syncPricesAndVisibility($productId, $variantId, $validated);

            return $productId;
        });

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'id'      => 'p-' . $productId,
        ], 201);
    }

    /**
     * Admin EDIT an existing product.
     */
    public function update(Request $request, $id)
    {
        $cleanId = str_replace('p-', '', $id);

        $exists = DB::table('products')->where('id', $cleanId)->whereNull('deleted_at')->first();

        if (!$exists) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        $validated = $this->validateProduct($request, (int) $cleanId);

        if ($validated instanceof \Illuminate\Http\JsonResponse) {
            return $validated;
        }

        DB::transaction(function () use ($cleanId, $request, $validated) {
            $categoryId = $this->resolveCategoryId($validated['category']);

            DB::table('products')->where('id', $cleanId)->update([
                'category_id'       => $categoryId,
                'subcategory'       => $validated['subcategory'],
                'name'              => $validated['name'],
                'model_number'      => $validated['model_number'],
                'short_description' => $validated['short_description'],
                'description'       => $validated['full_description'],
                'image_url'         => $validated['image_url'],
                'flight_time'       => $validated['flight_time'],
                'drone_id'          => $validated['drone_id'],
                'specs'             => json_encode($validated['specs']),
                'status'            => 'active',
                'updated_at'        => now(),
            ]);

            $variant = DB::table('product_variants')
                ->where('product_id', $cleanId)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->first();

            if (!$variant) {
                $variantId = DB::table('product_variants')->insertGetId([
                    'product_id'   => $cleanId,
                    'sku'          => $validated['sku'],
                    'name'         => $validated['name'],
                    'stock_qty'    => $validated['stock'],
                    'status'       => 'active',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            } else {
                $variantId = $variant->id;

                DB::table('product_variants')->where('id', $variantId)->update([
                    'sku'        => $validated['sku'],
                    'name'       => $validated['name'],
                    'stock_qty'  => $validated['stock'],
                    'status'     => 'active',
                    'updated_at' => now(),
                ]);
            }

            $this->syncPricesAndVisibility($cleanId, $variantId, $validated);
        });

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'id'      => 'p-' . $cleanId,
        ]);
    }

    /**
     * Admin delete a product (soft delete + clean up prices / visibility).
     */
    public function destroy($id)
    {
        $cleanId = str_replace('p-', '', $id);

        $product = DB::table('products')->where('id', $cleanId)->first();

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        DB::transaction(function () use ($cleanId) {
            $variantIds = DB::table('product_variants')
                ->where('product_id', $cleanId)
                ->pluck('id')
                ->all();

            if ($variantIds) {
                DB::table('product_prices')->whereIn('variant_id', $variantIds)->delete();
                DB::table('product_variants')
                    ->where('product_id', $cleanId)
                    ->update(['deleted_at' => now(), 'updated_at' => now()]);
            }

            DB::table('visibility_rules')
                ->where('visible_type', 'product')
                ->where('visible_id', $cleanId)
                ->delete();

            DB::table('products')
                ->where('id', $cleanId)
                ->update(['deleted_at' => now(), 'updated_at' => now()]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Product removed successfully',
        ]);
    }

    /**
     * Admin bulk import from an Excel sheet.
     */
    public function import(Request $request)
    {
        if (!$request->hasFile('file')) {
            return response()->json([
                'success' => false,
                'message' => 'Please attach a spreadsheet file (.xlsx / .csv).'
            ], 422);
        }

        $file = $request->file('file');
        $category = $request->input('category', 'parts');

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray();
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not read the spreadsheet: ' . $e->getMessage()
            ], 422);
        }

        $header = array_map(
            fn ($h) => trim(strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', (string) $h)), '_'),
            array_shift($rows) ?: []
        );

        $created = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            if (!array_filter($row, fn ($c) => trim((string) $c) !== '')) {
                continue;
            }

            $data = [];
            foreach ($header as $i => $key) {
                $data[$key] = $row[$i] ?? '';
            }

            $name = trim((string) ($data['drone_name'] ?? $data['part_name'] ?? $data['accessory_name'] ?? $data['product_name'] ?? $data['name'] ?? ''));

            if ($name === '') {
                $errors[] = 'Row ' . ($index + 2) . ': name is missing.';
                continue;
            }

            $normal = $this->priceToCents($data['retail_normal_price_aud'] ?? $data['price_aud'] ?? $data['price'] ?? 0);
            $wholesale = $this->priceToCentsOrNull($data['wholesale_price_aud'] ?? null);
            $agent = $this->priceToCentsOrNull($data['service_agent_price_aud'] ?? null);

            if ($normal <= 0) {
                $errors[] = 'Row ' . ($index + 2) . ': retail price is missing.';
                continue;
            }

            $roles = $this->parseRoles($data['visible_to_normal_wholesale_agent'] ?? $data['visible_to'] ?? 'normal,wholesale,agent');

            $discountPercent = (float) ($data['discount_percent'] ?? $data['discount'] ?? 0);
            $salePrice = $normal;

            if ($discountPercent > 0) {
                $salePrice = (int) round($normal * (1 - $discountPercent / 100));
            }

            $sku = trim((string) ($data['drone_model_number'] ?? $data['part_number_sku'] ?? $data['accessory_number_sku'] ?? $data['sku'] ?? ''));
            $sku = $sku !== '' ? $sku : 'DH-SKU-' . strtoupper(Str::random(8));

            $subcategory = trim((string) ($data['subcategory'] ?? ''));
            $compatibleDrone = trim((string) ($data['compatible_drone'] ?? ''));
            $componentSection = trim((string) ($data['drone_component_section'] ?? ''));

            $droneId = null;
            if ($compatibleDrone !== '') {
                $drone = DB::table('products')
                    ->whereNull('deleted_at')
                    ->where('name', 'like', '%' . $compatibleDrone . '%')
                    ->orderBy('id')
                    ->first();

                if ($drone) {
                    $droneId = $drone->id;
                }
            }

            $payload = [
                'name'              => $name,
                'sku'               => $sku,
                'model_number'      => $sku,
                'category'          => $category,
                'subcategory'       => $subcategory ?: $componentSection,
                'price_normal'      => $normal,
                'price_wholesale'   => $wholesale,
                'price_agent'       => $agent,
                'discount_type'     => $discountPercent > 0 ? 'percentage' : 'none',
                'discount_value'    => $discountPercent,
                'sale_price_cents'  => $salePrice,
                'stock'             => (int) ($data['stock_units'] ?? $data['stock'] ?? 10),
                'short_description' => trim((string) ($data['short_description'] ?? '')),
                'full_description'  => trim((string) ($data['full_description'] ?? '')),
                'image_url'         => trim((string) ($data['image_url'] ?? '')),
                'flight_time'       => trim((string) ($data['max_flight_time'] ?? '')),
                'drone_id'          => $droneId,
                'visibility_roles'  => $roles,
                'specs'             => [],
            ];

            $productId = DB::transaction(function () use ($payload) {
                $categoryId = $this->resolveCategoryId($payload['category']);

                $productId = DB::table('products')->insertGetId([
                    'category_id'       => $categoryId,
                    'subcategory'       => $payload['subcategory'] ?: null,
                    'type'              => 'simple',
                    'name'              => $payload['name'],
                    'slug'              => $this->uniqueSlug($payload['name']),
                    'model_number'      => $payload['model_number'],
                    'short_description' => $payload['short_description'],
                    'description'       => $payload['full_description'],
                    'image_url'         => $payload['image_url'] ?: null,
                    'flight_time'       => $payload['flight_time'] ?: null,
                    'drone_id'          => $payload['drone_id'],
                    'specs'             => json_encode([]),
                    'status'            => 'active',
                    'visibility_mode'   => 'public',
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);

                $variantId = DB::table('product_variants')->insertGetId([
                    'product_id'   => $productId,
                    'sku'          => $payload['sku'],
                    'name'         => $payload['name'],
                    'stock_qty'    => $payload['stock'],
                    'status'       => 'active',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                $this->syncPricesAndVisibility($productId, $variantId, $payload);

                return $productId;
            });

            $created++;
        }

        return response()->json([
            'success'  => true,
            'message'  => "Imported {$created} product(s).",
            'imported' => $created,
            'errors'   => $errors,
        ], 201);
    }

    // ─────────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────────

    /**
     * Validate + normalise the product payload coming from the admin forms.
     */
    private function validateProduct(Request $request, ?int $ignoreProductId = null)
    {
        $validator = Validator::make($request->all(), [
            'name'              => 'required|string|max:255',
            'sku'               => 'nullable|string|max:255',
            'model_number'      => 'nullable|string|max:255',
            'category'          => 'required|string|max:255',
            'subcategory'       => 'nullable|string|max:255',
            'price_normal'      => 'required|numeric|min:0',
            'price_wholesale'   => 'nullable|numeric|min:0',
            'price_agent'       => 'nullable|numeric|min:0',
            'discount_type'     => 'nullable|in:none,percentage,fixed',
            'discount_value'    => 'nullable|numeric|min:0',
            'sale_price_cents'  => 'nullable|numeric|min:0',
            'stock'             => 'nullable|integer|min:0',
            'short_description' => 'nullable|string',
            'full_description'  => 'nullable|string',
            'image_url'         => 'nullable|string|max:500',
            'image'             => 'nullable|file|mimes:jpeg,png,jpg,webp,svg|max:10240',
            'flight_time'       => 'nullable|string|max:255',
            'drone_id'          => 'nullable|integer',
            'visibility_roles'  => 'nullable|array',
            'visibility_roles.*' => 'in:normal,wholesale,agent',
            'specs'             => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $imageUrl = trim((string) ($data['image_url'] ?? ''));

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $imageUrl = '/storage/' . $path;
        }

        $sku = trim((string) ($data['sku'] ?? '')) ?: trim((string) ($data['model_number'] ?? ''));
        $sku = $sku !== '' ? $sku : 'DH-SKU-' . strtoupper(Str::random(8));

        // SKU must be unique across the catalogue.
        $skuTaken = DB::table('product_variants')
            ->where('sku', $sku)
            ->when($ignoreProductId, fn ($q) => $q->whereNotIn('product_id', [$ignoreProductId]))
            ->exists();

        if ($skuTaken) {
            return response()->json([
                'success' => false,
                'message' => "The SKU '{$sku}' is already used by another product. Please choose a different one.",
            ], 422);
        }

        $roles = $data['visibility_roles'] ?? ['normal', 'wholesale', 'agent'];
        $roles = is_array($roles) && count($roles) > 0 ? array_values(array_unique($roles)) : ['normal'];

        $normalCents = (int) round(((float) $data['price_normal']) * 100);

        $discountType = $data['discount_type'] ?? 'none';
        $discountValue = (float) ($data['discount_value'] ?? 0);
        $saleCents = isset($data['sale_price_cents']) && $data['sale_price_cents'] > 0
            ? (int) $data['sale_price_cents']
            : $normalCents;

        if ($discountType === 'percentage' && $discountValue > 0) {
            $saleCents = (int) round($normalCents * (1 - $discountValue / 100));
        } elseif ($discountType === 'fixed' && $discountValue > 0) {
            $saleCents = max(0, $normalCents - (int) round($discountValue * 100));
        }

        return [
            'name'              => $data['name'],
            'sku'               => $sku,
            'model_number'      => $data['model_number'] ?? $sku,
            'category'          => $data['category'],
            'subcategory'       => $data['subcategory'] ?? null,
            'price_normal'      => $normalCents,
            'price_wholesale'   => isset($data['price_wholesale']) && $data['price_wholesale'] !== null && $data['price_wholesale'] !== ''
                ? (int) round(((float) $data['price_wholesale']) * 100)
                : null,
            'price_agent'       => isset($data['price_agent']) && $data['price_agent'] !== null && $data['price_agent'] !== ''
                ? (int) round(((float) $data['price_agent']) * 100)
                : null,
            'sale_price_cents'  => $saleCents,
            'discount_type'     => $discountType,
            'discount_value'    => $discountValue,
            'stock'             => (int) ($data['stock'] ?? 10),
            'short_description' => $data['short_description'] ?? '',
            'full_description'  => $data['full_description'] ?? '',
            'image_url'         => $imageUrl,
            'flight_time'       => $data['flight_time'] ?? null,
            'drone_id'          => $data['drone_id'] ?? null,
            'visibility_roles'  => $roles,
            'specs'             => $data['specs'] ?? [],
        ];
    }

    /**
     * Write the per-group price rows and visibility rules.
     *
     * A group only receives a price row when the admin selected it, so
     * "which roles can buy this product" is defined in exactly one place.
     */
    private function syncPricesAndVisibility(int $productId, int $variantId, array $data): void
    {
        $roles = $data['visibility_roles'];

        $normalCents = $data['price_normal'];
        $saleCents = $data['sale_price_cents'];

        $wholesaleCents = $data['price_wholesale'] ?? null;
        $agentCents = $data['price_agent'] ?? null;

        if (in_array('wholesale', $roles, true) && $wholesaleCents === null) {
            $wholesaleCents = (int) round($normalCents * 0.88);
        }

        if (in_array('agent', $roles, true) && $agentCents === null) {
            $agentCents = (int) round($normalCents * 0.80);
        }

        // Remove price rows for groups that are no longer offered.
        DB::table('product_prices')
            ->where('variant_id', $variantId)
            ->whereNotIn('customer_group_id', $this->groupIdsFor($roles))
            ->delete();

        if (in_array('normal', $roles, true)) {
            $this->upsertPrice($variantId, PricingService::GROUP_IDS['retail'], $normalCents, $saleCents);
        }

        if (in_array('wholesale', $roles, true) && $wholesaleCents !== null) {
            $this->upsertPrice($variantId, PricingService::GROUP_IDS['wholesale'], $wholesaleCents, null);
        }

        if (in_array('agent', $roles, true) && $agentCents !== null) {
            $this->upsertPrice($variantId, PricingService::GROUP_IDS['agent'], $agentCents, null);
        }

        // Visibility rules mirror the selected roles.
        DB::table('visibility_rules')
            ->where('visible_type', 'product')
            ->where('visible_id', $productId)
            ->delete();

        foreach ($this->groupIdsFor($roles) as $groupId) {
            DB::table('visibility_rules')->insert([
                'visible_type'      => 'product',
                'visible_id'        => $productId,
                'customer_group_id' => $groupId,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }
    }

    private function upsertPrice(int $variantId, int $groupId, int $priceCents, ?int $saleCents): void
    {
        $existing = DB::table('product_prices')
            ->where('variant_id', $variantId)
            ->where('customer_group_id', $groupId)
            ->where('min_qty', 1)
            ->first();

        $payload = [
            'price_cents'       => $priceCents,
            'sale_price_cents'  => $saleCents,
            'sale_starts_at'    => $saleCents ? now() : null,
            'sale_ends_at'      => $saleCents ? now()->addYear() : null,
            'updated_at'        => now(),
        ];

        if ($existing) {
            DB::table('product_prices')->where('id', $existing->id)->update($payload);
            return;
        }

        DB::table('product_prices')->insert($payload + [
            'variant_id'        => $variantId,
            'customer_group_id' => $groupId,
            'min_qty'           => 1,
            'created_at'        => now(),
        ]);
    }

    private function groupIdsFor(array $roles): array
    {
        $ids = [];

        foreach ($roles as $role) {
            if (isset(PricingService::ROLE_GROUP_IDS[$role])) {
                $ids[] = PricingService::ROLE_GROUP_IDS[$role];
            }
        }

        return array_values(array_unique($ids)) ?: [PricingService::GROUP_IDS['retail']];
    }

    private function resolveCategoryId(string $slug): ?int
    {
        $category = DB::table('categories')
            ->where('slug', $slug)
            ->whereNull('deleted_at')
            ->first();

        if ($category) {
            return $category->id;
        }

        // Fall back to a top-level category of the matching type.
        $type = match ($slug) {
            'drones'      => 'drone',
            'parts'       => 'part',
            'accessories' => 'accessory',
            'combos'      => 'combo',
            default       => null,
        };

        if ($type) {
            $fallback = DB::table('categories')
                ->where('type', $type)
                ->whereNull('parent_id')
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->first();

            if ($fallback) {
                return $fallback->id;
            }
        }

        return null;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $i = 1;

        while (DB::table('products')->where('slug', $slug)->exists()) {
            $i++;
            $slug = $base . '-' . $i;
        }

        return $slug;
    }

    private function parseRoles($raw): array
    {
        $raw = strtolower((string) $raw);
        $roles = [];

        if (str_contains($raw, 'normal') || str_contains($raw, 'retail') || str_contains($raw, 'all')) {
            $roles[] = 'normal';
        }
        if (str_contains($raw, 'wholesale') || str_contains($raw, 'dealer') || str_contains($raw, 'all')) {
            $roles[] = 'wholesale';
        }
        if (str_contains($raw, 'agent') || str_contains($raw, 'service') || str_contains($raw, 'all')) {
            $roles[] = 'agent';
        }

        return $roles ?: ['normal'];
    }

    private function priceToCents($value): int
    {
        return (int) ($this->priceToCentsOrNull($value) ?? 0);
    }

    /**
     * Convert a spreadsheet price cell to cents.
     * Returns null when the cell is empty so the tier price can be derived
     * from the retail price instead of being stored as zero.
     */
    private function priceToCentsOrNull($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $num = (float) preg_replace('/[$,\s]/', '', (string) $value);

        return $num > 0 ? (int) round($num * 100) : null;
    }

    private function groupId(Request $request): int
    {
        $group = $request->attributes->get('customer_group');

        return (int) ($group->id ?? 1);
    }

    private function isAdmin(Request $request): bool
    {
        $user = $request->user();

        return $user && in_array($user->role, ['super_admin', 'admin', 'operations'], true);
    }
}
