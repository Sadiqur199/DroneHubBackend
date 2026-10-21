<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Submit checkout. Requires a signed-in customer.
     */
    public function checkout(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|max:255',
            'phone'           => 'required|string|max:50',
            'address'         => 'required|string|max:500',
            'company'         => 'nullable|string|max:255',
            'po_number'       => 'nullable|string|max:100',
            'shipping_method' => 'nullable|string',
            'notes'           => 'nullable|string',
            'document'        => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx,zip|max:20480',
            'items'           => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $items = $request->items;

        if (is_string($items)) {
            $items = json_decode($items, true);
        }

        if (empty($items) || !is_array($items)) {
            return response()->json([
                'success' => false,
                'message' => 'Your cart is empty. Please add items before checkout.'
            ], 400);
        }

        $documentPath = null;

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('documents', $fileName, 'public');
            $documentPath = '/storage/' . $path;
        }

        // Prices are always taken from the server catalogue, never from the browser.
        $groupId = (int) ($request->attributes->get('customer_group')->id ?? 1);
        $lines = [];
        $subtotal = 0;

        foreach ($items as $item) {
            $productId = (int) str_replace('p-', '', (string) ($item['id'] ?? $item['raw_id'] ?? 0));
            $qty = max(1, (int) ($item['qty'] ?? 1));

            if (!$productId) {
                continue;
            }

            $variant = DB::table('product_variants')
                ->where('product_id', $productId)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->first();

            if (!$variant) {
                continue;
            }

            $price = PricingService::priceFor($variant->id, $groupId, $qty);

            if (!$price) {
                return response()->json([
                    'success' => false,
                    'message' => 'One of the items in your cart is not available for your account type.'
                ], 422);
            }

            $name = DB::table('products')->where('id', $productId)->value('name') ?: 'Genuine Drone Equipment';

            $lineTotal = ($price['price_cents'] / 100) * $qty;
            $subtotal += $lineTotal;

            $lines[] = [
                'product_id'     => $productId,
                'part_name'      => $name,
                'unit_price'     => $price['price_cents'] / 100,
                'quantity'       => $qty,
                'line_total'     => $lineTotal,
                'is_sale'        => $price['is_sale'],
                'original_price' => $price['original_price_cents'] / 100,
            ];
        }

        if (empty($lines)) {
            return response()->json([
                'success' => false,
                'message' => 'None of the items in your cart are available.'
            ], 422);
        }

        $shippingMethod = $request->input('shipping_method', 'startrack');

        if ($shippingMethod === 'startrack') {
            $shippingCost = 65.00;
        } elseif ($shippingMethod === 'standard') {
            $shippingCost = $subtotal > 500 ? 0.00 : 25.00;
        } else {
            $shippingCost = 0.00;
        }

        $grandTotal = $subtotal + $shippingCost;
        $gstAmount = round($grandTotal / 11, 2);
        $orderNumber = 'DH-' . rand(100000, 999999);

        $orderId = DB::transaction(function () use ($request, $user, $documentPath, $shippingMethod, $shippingCost, $grandTotal, $gstAmount, $orderNumber, $lines) {
            $orderId = DB::table('part_orders')->insertGetId([
                'user_id'             => $user->id,
                'drone_id'            => null,
                'order_number'        => $orderNumber,
                'name'                => $request->name,
                'email'               => $request->email,
                'phone'               => $request->phone,
                'address'             => $request->address,
                'shipping_method'     => $shippingMethod,
                'shipping_cost'       => $shippingCost,
                'po_number'           => $request->po_number,
                'company'             => $request->company,
                'bank_transfer_proof' => $documentPath,
                'document_path'       => $documentPath,
                'notes'               => $request->notes,
                'total_price'         => $grandTotal,
                'total_discount'      => 0.00,
                'gst_amount'          => $gstAmount,
                'status'              => 'Pending',
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            foreach ($lines as $line) {
                DB::table('part_order_items')->insert([
                    'part_order_id'  => $orderId,
                    'product_id'     => $line['product_id'],
                    'part_name'      => $line['part_name'],
                    'original_price' => $line['original_price'],
                    'price'          => $line['unit_price'],
                    'discount'       => round($line['original_price'] - $line['unit_price'], 2),
                    'discount_type'  => $line['is_sale'] ? 'flat' : 'flat',
                    'quantity'       => $line['quantity'],
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }

            return $orderId;
        });

        return response()->json([
            'success'      => true,
            'message'      => 'Order submitted successfully',
            'order_id'     => $orderId,
            'order_number' => $orderNumber,
            'data'         => [
                'order_number'   => $orderNumber,
                'total_charged'  => '$' . number_format($grandTotal, 2),
                'gst_included'   => '$' . number_format($gstAmount, 2),
                'shipping_cost'  => '$' . number_format($shippingCost, 2),
                'document_url'   => $documentPath ? ('http://127.0.0.1:8000' . $documentPath) : null,
                'customer'       => [
                    'name'    => $request->name,
                    'email'   => $request->email,
                    'phone'   => $request->phone,
                    'company' => $request->company,
                    'address' => $request->address,
                ],
            ],
        ], 201);
    }

    /**
     * Orders belonging to the signed-in customer.
     */
    public function mine(Request $request)
    {
        $user = $request->user();

        $orders = DB::table('part_orders')
            ->where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn ($order) => $this->formatOrder($order));

        return response()->json([
            'success' => true,
            'count'   => $orders->count(),
            'data'    => $orders,
        ]);
    }

    /**
     * Admin: list every order.
     */
    public function index()
    {
        $orders = DB::table('part_orders')
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn ($order) => $this->formatOrder($order));

        return response()->json([
            'success' => true,
            'count'   => $orders->count(),
            'data'    => $orders,
        ]);
    }

    /**
     * Admin: update order status.
     */
    public function updateStatus(Request $request, $id)
    {
        $status = $request->input('status');

        if (!$status) {
            return response()->json(['success' => false, 'message' => 'Status is required'], 422);
        }

        $allowed = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];

        if (!in_array($status, $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Invalid status value'], 422);
        }

        DB::table('part_orders')
            ->where('id', $id)
            ->update([
                'status'     => $status,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => "Order #{$id} status updated to {$status}"
        ]);
    }

    private function formatOrder($order)
    {
        $items = DB::table('part_order_items')->where('part_order_id', $order->id)->get();

        $docUrl = null;
        $docPath = $order->document_path ?: $order->bank_transfer_proof;

        if ($docPath) {
            $docUrl = str_starts_with($docPath, 'http') ? $docPath : ('http://127.0.0.1:8000' . $docPath);
        }

        return [
            'id'              => $order->id,
            'order_number'    => $order->order_number,
            'customer_name'   => $order->name,
            'email'           => $order->email,
            'phone'           => $order->phone,
            'address'         => $order->address,
            'company'         => $order->company,
            'po_number'       => $order->po_number,
            'shipping_method' => $order->shipping_method ?: 'StarTrack Express',
            'shipping_cost'   => (float) $order->shipping_cost,
            'total_price'     => (float) $order->total_price,
            'total_formatted' => '$' . number_format($order->total_price, 2),
            'gst_amount'      => (float) $order->gst_amount,
            'document_url'    => $docUrl,
            'has_document'    => !empty($docUrl),
            'status'          => $order->status,
            'item_count'      => $items->sum('quantity'),
            'items'           => $items,
            'created_at'      => $order->created_at,
        ];
    }
}
