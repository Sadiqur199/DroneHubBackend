<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case PROCESSING = 'processing';
    case PACKED = 'packed';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Awaiting Payment',
            self::PAID => 'Payment Received',
            self::PROCESSING => 'Processing in Warehouse',
            self::PACKED => 'Packed & Inspected',
            self::SHIPPED => 'Dispatched with Carrier',
            self::DELIVERED => 'Delivered & Signed',
            self::CANCELLED => 'Order Cancelled',
            self::REFUNDED => 'Refunded',
        };
    }
}
