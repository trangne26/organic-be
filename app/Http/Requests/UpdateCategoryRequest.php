<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = is_object($category) ? $category->id : $category;
        
        return [
            'name' => 'required|string|max:160|unique:categories,name,' . $categoryId,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $category = $this->route('category');
            $categoryId = is_object($category) ? $category->id : $category;
            
            $slug = \App\Models\Category::generateUniqueSlug($this->name, $categoryId);
            
            $this->merge([
                'slug' => $slug,
            ]);
        }
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên danh mục là bắt buộc.',
            'name.unique' => 'Tên danh mục đã tồn tại.',
            'name.max' => 'Tên danh mục không được vượt quá 160 ký tự.',
        ];
    }
}
