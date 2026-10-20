<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Get all products with category filtering, search, and type filters
     */
    public function index(Request $request)
    {
        $category = $request->query('category', 'all');
        $type = $request->query('type');
        $search = $request->query('search');

        $query = DB::table('dealer_products')
            ->leftJoin('product_categories', 'dealer_products.category_id', '=', 'product_categories.id')
            ->select(
                'dealer_products.id',
                'dealer_products.name',
                'dealer_products.price',
                'dealer_products.price_dealer_a',
                'dealer_products.price_dealer_b',
                'dealer_products.stock',
                'dealer_products.image_url',
                'dealer_products.status',
                'dealer_products.created_at',
                'product_categories.name as category_name',
                'product_categories.code as category_code'
            );

        if ($search) {
            $query->where('dealer_products.name', 'like', "%{$search}%");
        }

        if ($category && $category !== 'all') {
            $query->where(function ($q) use ($category) {
                $q->where('product_categories.code', $category)
                  ->orWhere('product_categories.name', 'like', "%{$category}%")
                  ->orWhere('dealer_products.name', 'like', "%{$category}%");
            });
        }

        $items = $query->orderBy('dealer_products.id', 'desc')->get();

        // Also fetch drones for complete catalog experience
        $drones = DB::table('drones')->get();

        $formattedProducts = $items->map(function ($item) {
            $priceCents = (int) round(($item->price > 0 ? $item->price : ($item->price_dealer_a ?: 1500)) * 100);
            return [
                'id'            => 'p-' . $item->id,
                'raw_id'        => $item->id,
                'type'          => 'part',
                'name'          => $item->name,
                'sku'           => 'DH-SKU-' . str_pad($item->id, 4, '0', STR_PAD_LEFT),
                'price_cents'   => $priceCents,
                'price_formatted' => '$' . number_format($priceCents / 100, 2),
                'stock'         => $item->stock,
                'stock_status'  => $item->stock > 0 ? 'In Stock' : 'Out of Stock',
                'image_url'     => $this->resolveImageUrl($item->image_url),
                'category'      => $item->category_name ?: 'General Parts',
                'status'        => $item->status,
                'created_at'    => $item->created_at,
            ];
        });

        // Map drones into products list
        $formattedDrones = $drones->map(function ($drone) {
            $priceCents = $drone->model_code === 'T100' ? 3850000 : 2950000;
            return [
                'id'            => 'd-' . $drone->id,
                'raw_id'        => $drone->id,
                'type'          => 'drone',
                'name'          => $drone->model_name,
                'sku'           => 'DH-DRN-' . $drone->model_code,
                'price_cents'   => $priceCents,
                'price_formatted' => '$' . number_format($priceCents / 100, 2),
                'stock'         => 12,
                'stock_status'  => 'In Stock',
                'image_url'     => $this->resolveImageUrl($drone->image_url),
                'category'      => 'Agricultural Drone',
                'status'        => 'active',
                'specs'         => json_decode($drone->specs, true) ?: [],
                'accessories'   => json_decode($drone->accessories, true) ?: [],
                'created_at'    => $drone->created_at,
            ];
        });

        $merged = $formattedDrones->concat($formattedProducts);

        if ($type === 'drone') {
            $merged = $formattedDrones;
        } elseif ($type === 'part') {
            $merged = $formattedProducts;
        }

        return response()->json([
            'success' => true,
            'count'   => $merged->count(),
            'data'    => $merged->values()
        ]);
    }

    /**
     * Get single product details
     */
    public function show($id)
    {
        if (str_starts_with($id, 'd-') || is_numeric($id)) {
            $droneId = str_replace('d-', '', $id);
            $drone = DB::table('drones')->where('id', $droneId)->first();
            if ($drone) {
                // Fetch linked parts for this drone
                $parts = DB::table('parts')->where('drone_id', $drone->id)->get()->map(function($p) {
                    $cents = (int) round($p->price * 100);
                    return [
                        'id' => 'pt-' . $p->id,
                        'name' => $p->name,
                        'section' => $p->section_title,
                        'price_cents' => $cents,
                        'price_formatted' => '$' . number_format($cents / 100, 2),
                        'stock' => $p->stock_count,
                        'image_url' => $this->resolveImageUrl($p->image_url),
                    ];
                });

                $priceCents = $drone->model_code === 'T100' ? 3850000 : 2950000;
                return response()->json([
                    'success' => true,
                    'data'    => [
                        'id'            => 'd-' . $drone->id,
                        'raw_id'        => $drone->id,
                        'type'          => 'drone',
                        'name'          => $drone->model_name,
                        'model_code'    => $drone->model_code,
                        'sku'           => 'DH-DRN-' . $drone->model_code,
                        'price_cents'   => $priceCents,
                        'price_formatted' => '$' . number_format($priceCents / 100, 2),
                        'stock'         => 12,
                        'stock_status'  => 'In Stock',
                        'image_url'     => $this->resolveImageUrl($drone->image_url),
                        'parts_image_url' => $this->resolveImageUrl($drone->parts_image_url),
                        'warranty_status' => $drone->warranty_status,
                        'flight_hours'  => $drone->flight_hours,
                        'battery_sets'  => $drone->battery_sets,
                        'specs'         => json_decode($drone->specs, true) ?: [],
                        'accessories'   => json_decode($drone->accessories, true) ?: [],
                        'technical_specs' => json_decode($drone->technical_specifications, true) ?: [],
                        'compatible_parts' => $parts,
                    ]
                ]);
            }
        }

        // Check dealer_products
        $cleanId = str_replace('p-', '', $id);
        $product = DB::table('dealer_products')
            ->leftJoin('product_categories', 'dealer_products.category_id', '=', 'product_categories.id')
            ->where('dealer_products.id', $cleanId)
            ->select('dealer_products.*', 'product_categories.name as category_name')
            ->first();

        if ($product) {
            $priceCents = (int) round(($product->price > 0 ? $product->price : ($product->price_dealer_a ?: 1500)) * 100);
            return response()->json([
                'success' => true,
                'data'    => [
                    'id'            => 'p-' . $product->id,
                    'raw_id'        => $product->id,
                    'type'          => 'part',
                    'name'          => $product->name,
                    'sku'           => 'DH-SKU-' . str_pad($product->id, 4, '0', STR_PAD_LEFT),
                    'price_cents'   => $priceCents,
                    'price_formatted' => '$' . number_format($priceCents / 100, 2),
                    'stock'         => $product->stock,
                    'stock_status'  => $product->stock > 0 ? 'In Stock' : 'Out of Stock',
                    'image_url'     => $this->resolveImageUrl($product->image_url),
                    'category'      => $product->category_name ?: 'Genuine Parts',
                    'status'        => $product->status,
                ]
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Product not found'], 404);
    }

    /**
     * Admin create/upload new product
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'nullable|integer|min:0',
            'category_id' => 'nullable|integer',
            'image'       => 'nullable|file|mimes:jpeg,png,jpg,webp,svg|max:10240',
            'image_url'   => 'nullable|string',
            'type'        => 'nullable|string|in:drone,part,accessory,combo',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors()
            ], 422);
        }

        $imageUrl = $request->input('image_url', '/drone.png');

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $imageUrl = '/storage/' . $path;
        }

        $type = $request->input('type', 'part');

        if ($type === 'drone') {
            $droneId = DB::table('drones')->insertGetId([
                'drone_category_id' => 1,
                'model_name'        => $request->name,
                'model_code'        => strtoupper(Str::slug($request->name)),
                'serial_number'     => 'DH-AU-' . rand(10000, 99999),
                'registration_number' => 'VH-DH' . rand(100, 999),
                'purchase_date'     => now()->toDateString(),
                'purchased_from'    => 'DroneHub Mackay Hub',
                'warranty_expires'  => now()->addYears(2)->toDateString(),
                'warranty_status'   => 'Active',
                'warranty_remaining'=> '24 Months',
                'firmware_version'  => 'v01.04.0500',
                'firmware_status'   => 'Latest',
                'next_service_due'  => now()->addMonths(6)->toDateString(),
                'last_serviced'     => now()->subMonths(1)->toDateString(),
                'flight_hours'      => '0.0 hrs',
                'battery_sets'      => '4 Sets',
                'image_url'         => $imageUrl,
                'specs'             => json_encode([
                    ['label' => 'Payload Capacity', 'value' => '40 L / 50 kg'],
                    ['label' => 'Spray Width', 'value' => '11 m'],
                    ['label' => 'Radar Range', 'value' => '50 m Omnidirectional'],
                ]),
                'dashboard_stats'   => json_encode([]),
                'component_health'  => json_encode([]),
                'accessories'       => json_encode([]),
                'technical_specifications' => json_encode([]),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Drone created successfully',
                'id'      => 'd-' . $droneId
            ], 201);
        }

        // Otherwise insert into dealer_products
        $id = DB::table('dealer_products')->insertGetId([
            'category_id'    => $request->input('category_id', 1),
            'name'           => $request->name,
            'price'          => $request->price,
            'price_dealer_a' => $request->price,
            'price_dealer_b' => round($request->price * 0.95, 2),
            'stock'          => $request->input('stock', 10),
            'image_url'      => $imageUrl,
            'status'         => 'active',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'id'      => 'p-' . $id
        ], 201);
    }

    /**
     * Admin delete product
     */
    public function destroy($id)
    {
        if (str_starts_with($id, 'd-')) {
            $cleanId = str_replace('d-', '', $id);
            DB::table('drones')->where('id', $cleanId)->delete();
        } else {
            $cleanId = str_replace('p-', '', $id);
            DB::table('dealer_products')->where('id', $cleanId)->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Product removed successfully'
        ]);
    }

    private function resolveImageUrl($path)
    {
        if (!$path) return '/drone.png';
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        if (str_starts_with($path, '/storage/')) {
            return 'http://127.0.0.1:8000' . $path;
        }
        return $path;
    }
}
