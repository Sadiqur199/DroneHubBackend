<?php

namespace App\Enums;

enum JobStatus: string
{
    case BOOKED = 'booked';
    case RECEIVED = 'received';
    case DIAGNOSTICS = 'diagnostics';
    case WAITING_PARTS = 'waiting_parts';
    case BENCH_TEST = 'bench_test';
    case READY_DISPATCH = 'ready_dispatch';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match($this) {
            self::BOOKED => 'Job Scheduled',
            self::RECEIVED => 'Hardware Received at Workshop',
            self::DIAGNOSTICS => 'Diagnostics & Flight Data Analysis',
            self::WAITING_PARTS => 'Awaiting Genuine Replacement Parts',
            self::BENCH_TEST => 'Assembly & Flight Bench Testing',
            self::READY_DISPATCH => 'Ready for Courier / Customer Pickup',
            self::COMPLETED => 'Work Completed & Certified',
        };
    }
}
