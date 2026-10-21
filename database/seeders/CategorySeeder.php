<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            [
                'name' => 'Commercial Drones',
                'slug' => 'drones',
                'type' => 'drone',
                'description' => 'Heavy-lift agricultural spraying drones, radiometric mining survey platforms, and cinema airframes.',
                'subcategories' => [
                    'Agricultural & Spraying',
                    'Enterprise & Mining RTK',
                    'Thermal & Inspection',
                    'Cinema & Film',
                    'Survey & Mapping',
                ],
            ],
            [
                'name' => 'Genuine Spare Parts',
                'slug' => 'parts',
                'type' => 'part',
                'description' => 'Original factory replacement parts, propellers, brushless spray pumps, arm locks, and harnesses.',
                'subcategories' => [
                    'Propellers & Blades',
                    'Spray Pumps & Nozzles',
                    'Arm Locks & Collars',
                    'Cables & Harnesses',
                    'Motors & ESC',
                    'Frame & Landing Gear',
                    'Gimbals & Sensors',
                    'Avionics & Radar',
                ],
            ],
            [
                'name' => 'Accessories & Payloads',
                'slug' => 'accessories',
                'type' => 'accessory',
                'description' => 'Intelligent high-energy batteries, fast charging generators, smart controllers, and RTK base stations.',
                'subcategories' => [
                    'Intelligent Batteries',
                    'Charging Hubs & Generators',
                    'Remote Controllers & Monitors',
                    'Payloads & Thermal Sensors',
                    'RTK Base Stations',
                    'Transport Cases & Mounts',
                ],
            ],
            [
                'name' => 'Turnkey Combos',
                'slug' => 'combos',
                'type' => 'combo',
                'description' => 'Turnkey commercial fleet packages with multiple batteries, rapid chargers, and field gear.',
                'subcategories' => [
                    'Agricultural Spray Bundles',
                    'Mining Survey Bundles',
                    'Rapid Thermal & Bushfire Kits',
                    'Dual-Pilot Cinema Kits',
                ],
            ],
        ];

        $sort = 0;

        foreach ($catalog as $entry) {
            $sort += 10;

            $parentId = DB::table('categories')->where('slug', $entry['slug'])->value('id');

            if (!$parentId) {
                $parentId = DB::table('categories')->insertGetId([
                    'parent_id'       => null,
                    'type'            => $entry['type'],
                    'name'            => $entry['name'],
                    'slug'            => $entry['slug'],
                    'description'     => $entry['description'],
                    'sort'            => $sort,
                    'is_active'       => true,
                    'visibility_mode' => 'public',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }

            $subSort = 0;

            foreach ($entry['subcategories'] as $subName) {
                $subSort += 10;
                $subSlug = Str::slug($subName);

                $exists = DB::table('categories')
                    ->where('parent_id', $parentId)
                    ->where('slug', $subSlug)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('categories')->insert([
                    'parent_id'       => $parentId,
                    'type'            => $entry['type'],
                    'name'            => $subName,
                    'slug'            => $subSlug,
                    'description'     => null,
                    'sort'            => $subSort,
                    'is_active'       => true,
                    'visibility_mode' => 'public',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }
    }
}
