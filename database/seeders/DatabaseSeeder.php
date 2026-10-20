<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CustomerGroupSeeder::class,
        ]);

        // Default Super Admin User
        DB::table('users')->updateOrInsert(
            ['email' => 'admin@dronehub.com.au'],
            [
                'name' => 'DroneHub Operations Lead',
                'username' => 'admin_operations',
                'phone' => '1300 544 968',
                'password' => Hash::make('Admin123!Secure'),
                'customer_group_id' => 1,
                'role' => 'super_admin',
                'status' => 'active',
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Initial Hero Banner Setup
        DB::table('hero_banners')->updateOrInsert(
            ['id' => 1],
            [
                'media_type' => 'video',
                'media_url' => 'https://www.youtube.com/embed/6oPtcmr50Ls?autoplay=1&mute=1&loop=1&playlist=6oPtcmr50Ls&controls=0&showinfo=0&rel=0&iv_load_policy=3&disablekb=1&modestbranding=1&playsinline=1&fs=0',
                'badge_text' => 'DJI Agras T50 & Commercial Fleet | Central QLD Hub · Mackay',
                'title' => 'Australia’s Premier Commercial Drone Fleet & Genuine Aviation Spares',
                'subtitle' => 'Supplying heavy-lift agricultural spraying drones (DJI Agras T50), radiometric thermal survey platforms, and certified factory replacement parts. Serviced in our Mackay workshop with rapid deployment support across Brisbane, Sydney, and Gold Coast.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Store Settings
        $settings = [
            ['group' => 'general', 'key' => 'store_name', 'value' => json_encode('DroneHub Australia')],
            ['group' => 'general', 'key' => 'abn', 'value' => json_encode('48 612 908 124')],
            ['group' => 'general', 'key' => 'phone', 'value' => json_encode('1300 544 968')],
            ['group' => 'general', 'key' => 'email', 'value' => json_encode('enquiries@dronehub.com.au')],
            ['group' => 'general', 'key' => 'base_city', 'value' => json_encode('Mackay, Queensland')],
            ['group' => 'tax', 'key' => 'gst_percentage', 'value' => json_encode(10)],
            ['group' => 'shipping', 'key' => 'free_shipping_threshold_cents', 'value' => json_encode(50000)], // Free over $500 AUD
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['group' => $setting['group'], 'key' => $setting['key']],
                ['value' => $setting['value'], 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
