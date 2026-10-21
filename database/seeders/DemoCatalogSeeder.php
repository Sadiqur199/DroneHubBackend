<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds a representative demo catalogue so the storefront is populated on a
 * fresh install. Every product gets a retail / wholesale / agent price and
 * visibility rules for all three customer groups.
 */
class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('products')->whereNull('deleted_at')->count() > 0) {
            $this->command?->info('Catalogue already populated — skipping demo seed.');
            return;
        }

        $categories = DB::table('categories')->whereNull('parent_id')->pluck('id', 'slug');

        $drones = [
            [
                'slug' => 'drones',
                'sub' => 'Agricultural & Spraying',
                'name' => 'DJI Agras T100 Flagship Heavy Spray Drone',
                'sku' => 'DH-AG-T100-PRO',
                'price' => 4890000,
                'stock' => 3,
                'image' => '/images/hero-enterprise-drone.jpg',
                'short' => 'Flagship 100 kg maximum payload broadacre spray drone with coaxial octa-rotor lift.',
                'full' => 'The DJI Agras T100 delivers unprecedented heavy payload aerial chemical discharge up to 32 L/min with dual high-pressure impeller pumps and active phased array obstacle radar.',
                'flight' => '42 Min Max Flight Time',
                'specs' => [
                    ['label' => 'Payload Capacity', 'value' => '100 kg (incl. 70 L tank)'],
                    ['label' => 'Spray Width', 'value' => '11 m'],
                    ['label' => 'Radar Range', 'value' => '50 m Omnidirectional'],
                ],
            ],
            [
                'slug' => 'drones',
                'sub' => 'Agricultural & Spraying',
                'name' => 'DJI Agras T50 Broadacre Spray Drone',
                'sku' => 'DH-AG-T50-AU',
                'price' => 1890000,
                'stock' => 6,
                'image' => '/images/hero-enterprise-drone.jpg',
                'short' => 'Mid-size 50 kg spraying drone for broadacre chemical and liquid fertiliser work.',
                'full' => 'Centimeter-accurate RTK navigation with dual atomising sprayers for consistent droplet deposition across Australian broadacre paddocks.',
                'flight' => '22 Min Max Flight Time',
                'specs' => [
                    ['label' => 'Payload Capacity', 'value' => '50 kg'],
                    ['label' => 'Spray Width', 'value' => '9 m'],
                    ['label' => 'Radar Range', 'value' => '30 m Omnidirectional'],
                ],
            ],
            [
                'slug' => 'drones',
                'sub' => 'Enterprise & Mining RTK',
                'name' => 'DJI Matrice 350 RTK Open-Cut Mining Airframe',
                'sku' => 'DH-M350-RTK-AU',
                'price' => 1499900,
                'stock' => 6,
                'image' => '/images/hero-enterprise-drone.jpg',
                'short' => 'IP55 sealed industrial inspection platform for Bowen Basin open-cut coal pits.',
                'full' => 'Upgraded enterprise flagship with 55 min flight time, 20 km transmission range, and dual-downward payload support.',
                'flight' => '55 Min Max Flight Time',
                'specs' => [
                    ['label' => 'Ingress Protection', 'value' => 'IP55 Weather & Coal Dust Sealed'],
                    ['label' => 'Transmission System', 'value' => 'DJI O3 Enterprise (20 km)'],
                    ['label' => 'Operating Temperature', 'value' => '-20C to 50C'],
                ],
            ],
            [
                'slug' => 'drones',
                'sub' => 'Thermal & Inspection',
                'name' => 'DJI Mavic 3 Thermal Enterprise Drone',
                'sku' => 'DH-M3T-SURVEY',
                'price' => 789000,
                'stock' => 10,
                'image' => '/images/hero-enterprise-drone.jpg',
                'short' => 'Compact radiometric thermal and 56x optical zoom sensor for utility and fire inspections.',
                'full' => 'Under 1 kg rapid deployment thermal drone with 45 minutes flight time.',
                'flight' => '45 Min Max Flight Time',
                'specs' => [
                    ['label' => 'Thermal Resolution', 'value' => '1280 x 1024'],
                    ['label' => 'Optical Zoom', 'value' => '56x Hybrid'],
                ],
            ],
            [
                'slug' => 'drones',
                'sub' => 'Cinema & Film',
                'name' => 'DJI Inspire 3 Dual-Operator Cinema Airframe',
                'sku' => 'DH-INS3-AU',
                'price' => 1649900,
                'stock' => 2,
                'image' => '/images/hero-enterprise-drone.jpg',
                'short' => 'Full-frame 8K cinema platform with dual-pilot remote operation for film and broadcast.',
                'full' => 'The Inspire 3 pairs a full-frame Zenmuse X9-8K Air camera with the RC Plus controller in a cinema-grade folding frame.',
                'flight' => '28 Min Max Flight Time',
                'specs' => [
                    ['label' => 'Camera', 'value' => 'Zenmuse X9-8K Air'],
                    ['label' => 'Colour', 'value' => 'D-Cinema / F-Cinema'],
                ],
            ],
        ];

        $parts = [
            ['slug' => 'parts', 'sub' => 'Propellers & Blades', 'name' => 'Agras T100 60-inch Carbon Coaxial Propeller Set', 'sku' => 'DH-PRP-T100', 'price' => 38000, 'stock' => 24, 'image' => '/images/drone-parts-accessories.jpg', 'short' => 'Genuine balanced 60-inch high-tensile carbon composite propeller pair.', 'full' => 'Engineered to handle high aerodynamic stress and abrasive dust over Australian broadacre farming fields.', 'drone' => 'DH-AG-T100-PRO', 'flight' => null, 'specs' => [['label' => 'Diameter', 'value' => '60 inch']]],
            ['slug' => 'parts', 'sub' => 'Spray Pumps & Nozzles', 'name' => 'Agras T50 Dual Brushless Impeller Spray Pump Unit', 'sku' => 'DH-PMP-T50', 'price' => 89000, 'stock' => 12, 'image' => '', 'short' => 'Chemical resistant 16 L/min dual pump with zirconia ceramic shaft.', 'full' => 'High flow magnetic drive brushless pump impervious to harsh agricultural fertilizers and herbicides.', 'drone' => 'DH-AG-T50-AU', 'flight' => null, 'specs' => [['label' => 'Flow Rate', 'value' => '16 L/min']]],
            ['slug' => 'parts', 'sub' => 'Cables & Harnesses', 'name' => 'Matrice 350 Dual Downward Gimbal Harness Cable', 'sku' => 'DH-CBL-M350', 'price' => 35000, 'stock' => 18, 'image' => '/images/drone-parts-accessories.jpg', 'short' => 'EMI shielded ribbon harness enabling simultaneous thermal and zoom payload operations.', 'full' => 'Factory original dual payload connector harness with gold-plated locking terminal connectors.', 'drone' => 'DH-M350-RTK-AU', 'flight' => null, 'specs' => [['label' => 'Shielding', 'value' => 'EMI Braided']]],
            ['slug' => 'parts', 'sub' => 'Arm Locks & Collars', 'name' => 'Matrice 350 Quick-Release Arm Hinge Lock Kit', 'sku' => 'DH-LCK-M350', 'price' => 21000, 'stock' => 35, 'image' => '/images/drone-parts-accessories.jpg', 'short' => 'Heavy-duty quick-release folding arm lock rated for repeated field deployment.', 'full' => 'Tool-less arm locking mechanism engineered for rapid reconfiguration between survey and inspection payloads.', 'drone' => 'DH-M350-RTK-AU', 'flight' => null, 'specs' => [['label' => 'Material', 'value' => 'Forged Aluminium']]],
            ['slug' => 'parts', 'sub' => 'Propellers & Blades', 'name' => 'Matrice 350 2110 High-Altitude Carbon Propellers (Pair)', 'sku' => 'DH-PRP-2110', 'price' => 14900, 'stock' => 90, 'image' => '/images/drone-parts-accessories.jpg', 'short' => 'Precision balanced genuine replacement carbon composite propellers.', 'full' => 'Original DJI 2110 Low-Noise Propellers for M350/M300.', 'drone' => 'DH-M350-RTK-AU', 'flight' => null, 'specs' => [['label' => 'Diameter', 'value' => '21 inch']]],
        ];

        $accessories = [
            ['slug' => 'accessories', 'sub' => 'Intelligent Batteries', 'name' => 'DJI DB2000 Intelligent Flight Battery (40,000 mAh)', 'sku' => 'DH-BAT-DB2000', 'price' => 365000, 'stock' => 15, 'image' => '/images/drone-parts-accessories.jpg', 'short' => 'Massive 40Ah flight battery with water cooling jacket for 9-minute rapid turnaround.', 'full' => 'High energy density cells designed for heavy-lift continuous operation under harsh Australian summer heat.', 'drone' => 'DH-AG-T100-PRO', 'flight' => null, 'specs' => [['label' => 'Capacity', 'value' => '40,000 mAh']]],
            ['slug' => 'accessories', 'sub' => 'Remote Controllers & Monitors', 'name' => 'DJI RC Plus Enterprise 7-inch Daylight Smart Controller', 'sku' => 'DH-RC-PLUS-AU', 'price' => 289900, 'stock' => 8, 'image' => '', 'short' => '1,200 nit high-bright daylight readable screen with external WB37 hot-swappable battery.', 'full' => 'IP54 weather sealed controller with physical hardware control sticks and physical function switches.', 'drone' => 'DH-M350-RTK-AU', 'flight' => null, 'specs' => [['label' => 'Screen', 'value' => '7 inch 1200-nit']]],
            ['slug' => 'accessories', 'sub' => 'Payloads & Thermal Sensors', 'name' => 'DJI Zenmuse H20T Thermal & Optical Zoom Payload', 'sku' => 'DH-PAY-H20T', 'price' => 1249900, 'stock' => 4, 'image' => '/images/drone-parts-accessories.jpg', 'short' => 'Triple-sensor radiometric payload with 23x optical zoom and 640x512 thermal.', 'full' => 'Combines wide-angle, zoom and thermal sensors for utility inspection, bushfire spotting and cattle surveillance.', 'drone' => 'DH-M350-RTK-AU', 'flight' => null, 'specs' => [['label' => 'Thermal Resolution', 'value' => '640 x 512']]],
        ];

        $combos = [
            ['slug' => 'combos', 'sub' => 'Agricultural Spray Bundles', 'name' => 'Turnkey Agricultural Spray Pack (T50 + 4 Batteries + Fast Charger)', 'sku' => 'DH-CMB-T50-SPRAY', 'price' => 2450000, 'stock' => 2, 'image' => '', 'short' => 'Complete commercial broadacre outfit with 4x batteries and fast charger.', 'full' => 'Includes airframe, four batteries, high-output inverter charger, RTK survey stick, and priority support.', 'drone' => null, 'flight' => null, 'specs' => [['label' => 'Batteries Included', 'value' => '4 sets']]],
        ];

        $droneIds = [];

        foreach (array_merge($drones, $parts, $accessories, $combos) as $item) {
            $categoryId = $categories[$item['slug']] ?? null;

            $productId = DB::table('products')->insertGetId([
                'category_id'       => $categoryId,
                'subcategory'       => $item['sub'],
                'type'              => 'simple',
                'name'              => $item['name'],
                'slug'              => Str::slug($item['name']) . '-' . Str::lower(Str::random(4)),
                'model_number'      => $item['sku'],
                'short_description' => $item['short'],
                'description'       => $item['full'],
                'image_url'         => $item['image'] ?: null,
                'flight_time'       => $item['flight'] ?: null,
                'drone_id'          => null,
                'specs'             => json_encode($item['specs']),
                'status'            => 'active',
                'is_featured'       => false,
                'warranty_months'   => 24,
                'visibility_mode'   => 'public',
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            $variantId = DB::table('product_variants')->insertGetId([
                'product_id' => $productId,
                'sku'        => $item['sku'],
                'name'       => $item['name'],
                'stock_qty'  => $item['stock'],
                'status'     => 'active',
                'sort'       => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $normal = $item['price'];

            $this->priceRow($variantId, 1, $normal, null);
            $this->priceRow($variantId, 2, (int) round($normal * 0.88), null);
            $this->priceRow($variantId, 3, (int) round($normal * 0.80), null);

            foreach ([1, 2, 3] as $groupId) {
                DB::table('visibility_rules')->insert([
                    'visible_type'      => 'product',
                    'visible_id'        => $productId,
                    'customer_group_id' => $groupId,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }

            if ($item['sku'] === 'DH-AG-T100-PRO' || $item['sku'] === 'DH-AG-T50-AU' || $item['sku'] === 'DH-M350-RTK-AU') {
                $droneIds[$item['sku']] = $productId;
            }
        }

        // Link parts / accessories to their parent drone.
        foreach (array_merge($parts, $accessories, $combos) as $item) {
            if (empty($item['drone']) || !isset($droneIds[$item['drone']])) {
                continue;
            }

            DB::table('products')
                ->where('model_number', $item['sku'])
                ->update(['drone_id' => $droneIds[$item['drone']]]);
        }
    }

    private function priceRow(int $variantId, int $groupId, int $priceCents, ?int $saleCents): void
    {
        DB::table('product_prices')->insert([
            'variant_id'        => $variantId,
            'customer_group_id' => $groupId,
            'min_qty'           => 1,
            'price_cents'       => $priceCents,
            'sale_price_cents'  => $saleCents,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }
}
