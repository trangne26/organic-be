<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'total' => $this->total,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                    'address' => $this->user->address,
                ];
            }),
            'items' => $this->whenLoaded('orderItems', function () {
                return $this->orderItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product' => new ProductResource($item->product),
                        'qty' => $item->qty,
                        'unit_price' => $item->unit_price,
                        'line_total' => $item->line_total,
                    ];
                });
            }),
            'payments' => $this->whenLoaded('payments', function () {
                return $this->payments->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'method' => $payment->method,
                        'amount' => $payment->amount,
                        'status' => $payment->status,
                        'paid_at' => $payment->paid_at?->toISOString(),
                    ];
                });
            }),
            'shipments' => $this->whenLoaded('shipments', function () {
                return $this->shipments->map(function ($shipment) {
                    return [
                        'id' => $shipment->id,
                        'provider' => $shipment->provider,
                        'tracking_no' => $shipment->tracking_no,
                        'status' => $shipment->status,
                        'shipped_at' => $shipment->shipped_at?->toISOString(),
                        'delivered_at' => $shipment->delivered_at?->toISOString(),
                    ];
                });
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
