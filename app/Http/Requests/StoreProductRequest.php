<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => 'nullable|exists:categories,id',
            'name' => 'required|string|max:191|unique:products,name',
            'slug' => 'nullable|string|max:191|unique:products,slug',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'nullable|in:0,1,true,false',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'primary_image_index' => 'nullable|integer|min:0',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'slug' => Str::slug($this->name),
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên sản phẩm là bắt buộc.',
            'name.unique' => 'Tên sản phẩm đã tồn tại.',
            'name.max' => 'Tên sản phẩm không được vượt quá 191 ký tự.',
            'price.required' => 'Giá sản phẩm là bắt buộc.',
            'price.numeric' => 'Giá sản phẩm phải là số.',
            'price.min' => 'Giá sản phẩm phải lớn hơn hoặc bằng 0.',
            'category_id.exists' => 'Danh mục không tồn tại.',
            'is_active.in' => 'Trạng thái hoạt động phải là 0, 1, true hoặc false.',
            'images.max' => 'Tối đa 5 ảnh cho mỗi sản phẩm.',
            'images.*.image' => 'File phải là ảnh.',
            'images.*.mimes' => 'Ảnh phải có định dạng: jpeg, png, jpg, gif, webp.',
            'images.*.max' => 'Kích thước ảnh không được vượt quá 2MB.',
        ];
    }
}