<?php

namespace App\Enums;

enum ClaimStatus: string
{
    case SUBMITTED = 'submitted';
    case REVIEWING = 'reviewing';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case IN_REPAIR = 'in_repair';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match($this) {
            self::SUBMITTED => 'Claim Submitted by Customer',
            self::REVIEWING => 'Technical Review in Progress',
            self::APPROVED => 'Claim Approved (RMA Issued)',
            self::REJECTED => 'Claim Not Covered by Policy',
            self::IN_REPAIR => 'Service Job Active with Certified Agent',
            self::COMPLETED => 'Resolved & Dispatched to Customer',
        };
    }
}
