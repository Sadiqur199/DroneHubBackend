<?php

namespace App\Enums;

enum CustomerGroupSlug: string
{
    case RETAIL = 'retail';
    case WHOLESALE = 'wholesale';
    case AGENT = 'agent';

    public function label(): string
    {
        return match($this) {
            self::RETAIL => 'Retail Customer (GST Incl)',
            self::WHOLESALE => 'Approved Wholesale Partner (ABN)',
            self::AGENT => 'Authorized Service Agent',
        };
    }
}
