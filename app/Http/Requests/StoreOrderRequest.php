<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'payment_method' => 'required|string|in:COD,Momo,VNPAY',
            'shipping_name' => 'required|string|max:120',
            'shipping_phone' => 'required|string|max:30',
            'shipping_address' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'shipping_provider' => 'nullable|string|in:GHN,GHTK',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Đơn hàng phải có ít nhất một sản phẩm.',
            'items.min' => 'Đơn hàng phải có ít nhất một sản phẩm.',
            'items.*.product_id.required' => 'ID sản phẩm là bắt buộc.',
            'items.*.product_id.exists' => 'Sản phẩm không tồn tại.',
            'items.*.qty.required' => 'Số lượng là bắt buộc.',
            'items.*.qty.integer' => 'Số lượng phải là số nguyên.',
            'items.*.qty.min' => 'Số lượng phải lớn hơn 0.',
            'payment_method.required' => 'Phương thức thanh toán là bắt buộc.',
            'payment_method.in' => 'Phương thức thanh toán không hợp lệ.',
            'shipping_name.required' => 'Tên người nhận là bắt buộc.',
            'shipping_name.max' => 'Tên người nhận không được quá 120 ký tự.',
            'shipping_phone.required' => 'Số điện thoại người nhận là bắt buộc.',
            'shipping_phone.max' => 'Số điện thoại không được quá 30 ký tự.',
            'shipping_address.required' => 'Địa chỉ giao hàng là bắt buộc.',
            'shipping_address.max' => 'Địa chỉ giao hàng không được quá 255 ký tự.',
            'notes.string' => 'Ghi chú phải là chuỗi ký tự.',
            'shipping_provider.in' => 'Nhà vận chuyển không hợp lệ.',
        ];
    }
}
