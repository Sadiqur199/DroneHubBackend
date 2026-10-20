<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Submit checkout with customer info and document upload
     */
    public function checkout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|max:255',
            'phone'           => 'required|string|max:50',
            'address'         => 'required|string|max:500',
            'company'         => 'nullable|string|max:255',
            'po_number'       => 'nullable|string|max:100',
            'shipping_method' => 'nullable|string',
            'notes'           => 'nullable|string',
            'document'        => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx,zip|max:20480', // 20MB max
            'items'           => 'required', // can be array or JSON string
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors()
            ], 422);
        }

        // Parse items
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

        // Handle document file upload
        $documentPath = null;
        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('documents', $fileName, 'public');
            $documentPath = '/storage/' . $path;
        }

        // Calculate Subtotal
        $subtotal = 0;
        foreach ($items as $item) {
            $price = isset($item['price_cents']) ? ($item['price_cents'] / 100) : ($item['price'] ?? 0);
            $qty = $item['qty'] ?? 1;
            $subtotal += ($price * $qty);
        }

        // Calculate Shipping
        $shippingMethod = $request->input('shipping_method', 'startrack');
        $shippingCost = 0;
        if ($shippingMethod === 'startrack') {
            $shippingCost = 65.00;
        } elseif ($shippingMethod === 'standard') {
            $shippingCost = $subtotal > 500 ? 0.00 : 25.00;
        } else {
            $shippingCost = 0.00; // Mackay Hub pickup
        }

        $grandTotal = $subtotal + $shippingCost;
        $gstAmount = round($grandTotal / 11, 2); // 10% GST Inclusive Australian Standard

        $orderNumber = 'DH-' . rand(100000, 999999);

        // Try to associate with user if logged in email matches
        $userId = null;
        $existingUser = DB::table('users')->where('email', $request->email)->first();
        if ($existingUser) {
            $userId = $existingUser->id;
        }

        $orderId = DB::table('part_orders')->insertGetId([
            'user_id'             => $userId,
            'drone_id'            => 1, // Default primary fleet hub
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

        // Insert items
        foreach ($items as $item) {
            $price = isset($item['price_cents']) ? ($item['price_cents'] / 100) : ($item['price'] ?? 0);
            $qty = $item['qty'] ?? 1;
            $rawId = isset($item['raw_id']) ? $item['raw_id'] : 1;

            DB::table('part_order_items')->insert([
                'part_order_id'  => $orderId,
                'part_id'        => is_numeric($rawId) ? $rawId : 1,
                'part_name'      => $item['name'] ?? 'Genuine Drone Equipment',
                'original_price' => $price,
                'price'          => $price,
                'discount'       => 0.00,
                'discount_type'  => 'flat',
                'quantity'       => $qty,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

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
                ]
            ]
        ], 201);
    }

    /**
     * Admin list all orders
     */
    public function index()
    {
        $orders = DB::table('part_orders')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($order) {
                $items = DB::table('part_order_items')
                    ->where('part_order_id', $order->id)
                    ->get();

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
            });

        return response()->json([
            'success' => true,
            'count'   => $orders->count(),
            'data'    => $orders
        ]);
    }

    /**
     * Admin update order status
     */
    public function updateStatus(Request $request, $id)
    {
        $status = $request->input('status');
        if (!$status) {
            return response()->json(['success' => false, 'message' => 'Status is required'], 422);
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
}
