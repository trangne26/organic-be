<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query();

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Price range filter
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Sort by price
        if ($request->has('sort_price')) {
            $query->orderBy('price', $request->sort_price);
        } else {
            $query->latest();
        }

        // Pagination with custom per_page
        $perPage = $request->get('per_page', 15);
        $perPage = max(1, min(100, (int)$perPage)); // Limit between 1-100

        $products = $query->with(['category', 'images'])
            ->withCount('orderItems')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        Log::info('Creating product', [
            'request_data' => $request->except(['images']),
            'has_images' => $request->hasFile('images'),
            'images_count' => $request->hasFile('images') ? count($request->file('images')) : 0,
            'primary_image_index' => $request->primary_image_index,
            'validated_data' => $request->validated()
        ]);

        DB::beginTransaction();

        try {
            Log::info('Creating product record');
            $product = Product::create($request->validated());
            Log::info('Product created successfully', ['product_id' => $product->id]);

            // Handle product images upload
            if ($request->hasFile('images')) {
                Log::info('Processing images', ['count' => count($request->file('images'))]);
                
                foreach ($request->file('images') as $index => $image) {
                    Log::debug('Processing image', [
                        'index' => $index,
                        'filename' => $image->getClientOriginalName(),
                        'size' => $image->getSize(),
                        'mime_type' => $image->getMimeType()
                    ]);
                    
                    // Generate unique filename
                    $filename = time() . '_' . $index . '.' . $image->getClientOriginalExtension();
                    Log::info('Generated filename', ['filename' => $filename]);
                    
                    // Store image in storage/app/public/products
                    $path = $image->storeAs('products', $filename, 'public');
                    Log::info('Image stored', ['path' => $path]);
                    
                    // Create product image record
                    $imageData = [
                        'product_id' => $product->id,
                        'image_url' => Storage::url($path),
                        'is_primary' => $index === $request->primary_image_index,
                    ];
                    Log::info('Creating product image record', $imageData);
                    
                    ProductImage::create($imageData);
                    Log::info('Product image record created successfully');
                }
            } else {
                Log::info('No images to process');
            }

            DB::commit();
            Log::info('Product creation completed successfully', ['product_id' => $product->id]);

            $product->load(['category', 'images']);

            return response()->json([
                'success' => true,
                'message' => 'Sản phẩm đã được tạo thành công.',
                'data' => new ProductResource($product),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product creation failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo sản phẩm.',
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load(['category', 'images', 'orderItems']);

        return response()->json([
            'success' => true,
            'data' => new ProductResource($product),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        // Debug validation data
        $this->debugValidationErrors($request);
        
        Log::info('Updating product', [
            'product_id' => $product->id,
            'request_data' => $request->except(['images']),
            'has_images' => $request->hasFile('images'),
            'images_count' => $request->hasFile('images') ? count($request->file('images')) : 0,
            'primary_image_index' => $request->primary_image_index,
            'validated_data' => $request->validated(),
            'removed_image_ids' => $request->input('removed_image_ids'),
        ]);

        DB::beginTransaction();

        try {
            Log::info('Updating product record', ['product_id' => $product->id]);
            $product->update($request->validated());
            Log::info('Product updated successfully', ['product_id' => $product->id]);

            // Handle remove old images by IDs (optional)
            $removedImageIds = $request->input('removed_image_ids', []);
            if (is_array($removedImageIds) && count($removedImageIds) > 0) {
                Log::info('Removing selected images', ['removed_image_ids' => $removedImageIds]);

                $imagesToRemove = $product->images()
                    ->whereIn('id', $removedImageIds)
                    ->get();

                foreach ($imagesToRemove as $image) {
                    $path = str_replace('/storage/', '', $image->image_url);
                    Storage::disk('public')->delete($path);
                    Log::info('Deleted selected old image', ['path' => $path, 'image_id' => $image->id]);
                }

                $product->images()->whereIn('id', $removedImageIds)->delete();
                Log::info('Deleted selected old image records', ['removed_image_ids' => $removedImageIds]);
            }

            $primaryIndex = $request->filled('primary_image_index')
                ? (int) $request->input('primary_image_index')
                : null;

            // Handle product images update
            if ($request->hasFile('images')) {
                Log::info('Uploading additional images', ['count' => count($request->file('images'))]);

                // Lấy lại danh sách ảnh hiện có (sau khi đã xóa removed_image_ids)
                $existingImages = $product->images()->orderBy('id')->get();
                $existingCount = $existingImages->count();

                // Upload new images (keep existing ones unless they are in removed_image_ids)
                foreach ($request->file('images') as $index => $image) {
                    Log::debug('Processing image', [
                        'index' => $index,
                        'filename' => $image->getClientOriginalName(),
                        'size' => $image->getSize(),
                        'mime_type' => $image->getMimeType()
                    ]);
                    
                    // Generate unique filename
                    $filename = time() . '_' . $index . '.' . $image->getClientOriginalExtension();
                    Log::info('Generated filename', ['filename' => $filename]);
                    
                    // Store image in storage/app/public/products
                    $path = $image->storeAs('products', $filename, 'public');
                    Log::info('Image stored', ['path' => $path]);
                    
                    // Create product image record
                    $globalIndex = $existingCount + $index;
                    $imageData = [
                        'product_id' => $product->id,
                        'image_url' => Storage::url($path),
                        'is_primary' => $primaryIndex !== null && $globalIndex === $primaryIndex,
                    ];
                    Log::info('Creating product image record', $imageData);
                    
                    ProductImage::create($imageData);
                    Log::info('Product image record created successfully');
                }
            } else {
                Log::info('No images to update');
            }

            // Finalize primary image flag after all mutations (works with or without new images)
            if ($primaryIndex !== null) {
                $orderedImages = $product->images()->orderBy('id')->get();

                foreach ($orderedImages as $index => $image) {
                    $shouldBePrimary = $index === $primaryIndex;

                    if ((bool)$image->is_primary !== $shouldBePrimary) {
                        $image->update(['is_primary' => $shouldBePrimary]);
                        Log::debug('Adjusted primary flag after sync', [
                            'image_id' => $image->id,
                            'index' => $index,
                            'is_primary' => $shouldBePrimary,
                        ]);
                    }
                }
            }

            DB::commit();
            Log::info('Product update completed successfully', ['product_id' => $product->id]);

            $product->load(['category', 'images']);

            return response()->json([
                'success' => true,
                'message' => 'Sản phẩm đã được cập nhật thành công.',
                'data' => new ProductResource($product),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product update failed', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật sản phẩm.',
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): JsonResponse
    {
        Log::info('Deleting product', ['product_id' => $product->id]);

        // Check if product has orders
        if ($product->orderItems()->count() > 0) {
            Log::warning('Cannot delete product with orders', [
                'product_id' => $product->id,
                'orders_count' => $product->orderItems()->count()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa sản phẩm đã có đơn hàng.',
            ], 422);
        }

        // Delete product images from storage
        foreach ($product->images as $image) {
            $path = str_replace('/storage/', '', $image->image_url);
            Storage::disk('public')->delete($path);
            Log::info('Deleted image file', ['path' => $path]);
        }

        $product->delete();
        Log::info('Product deleted successfully', ['product_id' => $product->id]);

        return response()->json([
            'success' => true,
            'message' => 'Sản phẩm đã được xóa thành công.',
        ]);
    }

    /**
     * Debug validation errors helper method
     */
    private function debugValidationErrors($request): void
    {
        Log::info('Validation Debug', [
            'all_input' => $request->all(),
            'has_name' => $request->has('name'),
            'name_value' => $request->input('name'),
            'has_price' => $request->has('price'),
            'price_value' => $request->input('price'),
            'has_category_id' => $request->has('category_id'),
            'category_id_value' => $request->input('category_id'),
            'has_description' => $request->has('description'),
            'description_value' => $request->input('description'),
            'has_is_active' => $request->has('is_active'),
            'is_active_value' => $request->input('is_active'),
            'has_primary_image_index' => $request->has('primary_image_index'),
            'primary_image_index_value' => $request->input('primary_image_index'),
            'has_images' => $request->hasFile('images'),
            'images_count' => $request->hasFile('images') ? count($request->file('images')) : 0,
        ]);
    }
}