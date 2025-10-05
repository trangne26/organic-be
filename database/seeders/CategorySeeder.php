<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Rau củ quả hữu cơ',
                'description' => 'Các loại rau củ quả được trồng theo phương pháp hữu cơ, không sử dụng hóa chất độc hại.',
                'is_active' => true,
            ],
            [
                'name' => 'Trái cây hữu cơ',
                'description' => 'Trái cây tươi ngon được trồng hữu cơ, đảm bảo an toàn cho sức khỏe.',
                'is_active' => true,
            ],
            [
                'name' => 'Gạo và ngũ cốc hữu cơ',
                'description' => 'Gạo và các loại ngũ cốc được trồng theo tiêu chuẩn hữu cơ.',
                'is_active' => true,
            ],
            [
                'name' => 'Thịt và hải sản hữu cơ',
                'description' => 'Thịt và hải sản được nuôi trồng theo phương pháp hữu cơ.',
                'is_active' => true,
            ],
            [
                'name' => 'Sữa và sản phẩm từ sữa',
                'description' => 'Sữa và các sản phẩm từ sữa hữu cơ.',
                'is_active' => true,
            ],
            [
                'name' => 'Gia vị và thảo mộc',
                'description' => 'Các loại gia vị và thảo mộc hữu cơ.',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::create([
                'name' => $category['name'],
                'slug' => Str::slug($category['name']),
                'description' => $category['description'],
                'is_active' => $category['is_active'],
            ]);
        }
    }
}
