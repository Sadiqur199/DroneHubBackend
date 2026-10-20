<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            [
                'id' => 1,
                'name' => 'Retail Customer',
                'slug' => 'retail',
                'is_default' => true,
                'requires_approval' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'Approved Wholesale Partner',
                'slug' => 'wholesale',
                'is_default' => false,
                'requires_approval' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'name' => 'Authorized Service Agent',
                'slug' => 'agent',
                'is_default' => false,
                'requires_approval' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('customer_groups')->upsert($groups, ['id'], ['name', 'slug', 'is_default', 'requires_approval', 'updated_at']);
    }
}
