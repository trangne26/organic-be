<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = Category::all();

        $products = [
            [
                'name' => 'Cà chua hữu cơ',
                'price' => 45000,
                'description' => 'Cà chua hữu cơ tươi ngon, được trồng không sử dụng hóa chất.',
                'category' => 'Rau củ quả hữu cơ',
                'images' => [
                    'https://example.com/images/ca-chua-1.jpg',
                    'https://example.com/images/ca-chua-2.jpg',
                ],
            ],
            [
                'name' => 'Rau xà lách hữu cơ',
                'price' => 25000,
                'description' => 'Rau xà lách tươi xanh, giòn ngon, an toàn cho sức khỏe.',
                'category' => 'Rau củ quả hữu cơ',
                'images' => [
                    'https://example.com/images/xa-lach-1.jpg',
                ],
            ],
            [
                'name' => 'Táo hữu cơ',
                'price' => 120000,
                'description' => 'Táo hữu cơ giòn ngọt, giàu vitamin và chất xơ.',
                'category' => 'Trái cây hữu cơ',
                'images' => [
                    'https://example.com/images/tao-1.jpg',
                    'https://example.com/images/tao-2.jpg',
                ],
            ],
            [
                'name' => 'Chuối hữu cơ',
                'price' => 35000,
                'description' => 'Chuối hữu cơ chín tự nhiên, giàu kali và vitamin B6.',
                'category' => 'Trái cây hữu cơ',
                'images' => [
                    'https://example.com/images/chuoi-1.jpg',
                ],
            ],
            [
                'name' => 'Gạo lứt hữu cơ',
                'price' => 85000,
                'description' => 'Gạo lứt hữu cơ giàu chất xơ và vitamin.',
                'category' => 'Gạo và ngũ cốc hữu cơ',
                'images' => [
                    'https://example.com/images/gao-lut-1.jpg',
                ],
            ],
            [
                'name' => 'Thịt bò hữu cơ',
                'price' => 450000,
                'description' => 'Thịt bò hữu cơ tươi ngon, được nuôi theo tiêu chuẩn hữu cơ.',
                'category' => 'Thịt và hải sản hữu cơ',
                'images' => [
                    'https://example.com/images/thit-bo-1.jpg',
                    'https://example.com/images/thit-bo-2.jpg',
                ],
            ],
            [
                'name' => 'Sữa tươi hữu cơ',
                'price' => 55000,
                'description' => 'Sữa tươi hữu cơ giàu canxi và protein.',
                'category' => 'Sữa và sản phẩm từ sữa',
                'images' => [
                    'https://example.com/images/sua-tuoi-1.jpg',
                ],
            ],
            [
                'name' => 'Mật ong hữu cơ',
                'price' => 180000,
                'description' => 'Mật ong hữu cơ nguyên chất, tốt cho sức khỏe.',
                'category' => 'Gia vị và thảo mộc',
                'images' => [
                    'https://example.com/images/mat-ong-1.jpg',
                ],
            ],
        ];

        foreach ($products as $productData) {
            $category = $categories->firstWhere('name', $productData['category']);
            
            $product = Product::create([
                'category_id' => $category?->id,
                'name' => $productData['name'],
                'slug' => Str::slug($productData['name']),
                'price' => $productData['price'],
                'description' => $productData['description'],
                'is_active' => true,
            ]);

            // Create product images
            foreach ($productData['images'] as $index => $imageUrl) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_url' => $imageUrl,
                    'is_primary' => $index === 0,
                ]);
            }
        }
    }
}
