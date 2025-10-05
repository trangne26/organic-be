<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::query();

        // Filter by user (for non-admin users)
        if (!Auth::user()?->isAdmin()) {
            $query->where('user_id', Auth::id());
        } elseif ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Date range filter
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Pagination with custom per_page
        $perPage = $request->get('per_page', 15);
        $perPage = max(1, min(100, (int)$perPage)); // Limit between 1-100

        $orders = $query->with(['user', 'orderItems.product', 'payments', 'shipments'])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => OrderResource::collection($orders),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        // Debug: Log all request data
        \Log::info('StoreOrderRequest received', [
            'all_data' => $request->all(),
            'shipping_name' => $request->shipping_name,
            'shipping_phone' => $request->shipping_phone,
            'shipping_address' => $request->shipping_address,
            'notes' => $request->notes
        ]);
        
        DB::beginTransaction();

        try {
            // Calculate total
            $total = 0;
            $orderItems = [];

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $lineTotal = $product->price * $item['qty'];
                $total += $lineTotal;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'qty' => $item['qty'],
                    'unit_price' => $product->price,
                    'line_total' => $lineTotal,
                ];
            }

            // Debug: Log request data
            \Log::info('Order creation request data', [
                'shipping_name' => $request->shipping_name,
                'shipping_phone' => $request->shipping_phone,
                'shipping_address' => $request->shipping_address,
                'notes' => $request->notes,
                'all_data' => $request->all()
            ]);
            
            // Create order
            $order = Order::create([
                'user_id' => Auth::id(),
                'status' => 'pending',
                'total' => $total,
                'shipping_name' => $request->shipping_name,
                'shipping_phone' => $request->shipping_phone,
                'shipping_address' => $request->shipping_address,
                'notes' => $request->notes,
            ]);

            // Create order items
            foreach ($orderItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                ]);
            }

            // Create payment record
            Payment::create([
                'order_id' => $order->id,
                'method' => $request->payment_method,
                'amount' => $total,
                'status' => 'pending',
            ]);

            // Create shipment record
            Shipment::create([
                'order_id' => $order->id,
                'provider' => $request->shipping_provider, // Có thể null, admin sẽ chọn sau
                'status' => 'pending',
                'shipping_fee' => 0.00, // Sẽ được tính sau khi chọn provider
            ]);

            DB::commit();

            $order->load(['user', 'orderItems.product', 'payments', 'shipments']);

            return response()->json([
                'success' => true,
                'message' => 'Đơn hàng đã được tạo thành công.',
                'data' => new OrderResource($order),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log lỗi chi tiết
            \Log::error('Order creation failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo đơn hàng.',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order): JsonResponse
    {
        // Check authorization
        if (!Auth::user()?->isAdmin() && $order->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền xem đơn hàng này.',
            ], 403);
        }

        $order->load(['user', 'orderItems.product', 'payments', 'shipments']);

        return response()->json([
            'success' => true,
            'data' => new OrderResource($order),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,paid,shipped,delivered,cancelled',
        ]);

        $order->update(['status' => $request->status]);

        // Update related records based on status
        if ($request->status === 'paid') {
            $order->payments()->update(['status' => 'paid', 'paid_at' => now()]);
        } elseif ($request->status === 'shipped') {
            $order->shipments()->update(['status' => 'shipped', 'shipped_at' => now()]);
        } elseif ($request->status === 'delivered') {
            $order->shipments()->update(['status' => 'delivered', 'delivered_at' => now()]);
        }

        $order->load(['user', 'orderItems.product', 'payments', 'shipments']);

        return response()->json([
            'success' => true,
            'message' => 'Trạng thái đơn hàng đã được cập nhật.',
            'data' => new OrderResource($order),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order): JsonResponse
    {
        // Only allow deletion of pending orders
        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể xóa đơn hàng đang chờ xử lý.',
            ], 422);
        }

        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đơn hàng đã được xóa thành công.',
        ]);
    }
}
