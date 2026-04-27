<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\Discount;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. App Settings
        AppSetting::firstOrCreate(['key' => 'store_name'],  ['group' => 'general',  'value' => 'Optik Medio Premium', 'type' => 'string']);
        AppSetting::firstOrCreate(['key' => 'tax_rate'],    ['group' => 'finance',   'value' => '11',                  'type' => 'integer']);
        AppSetting::firstOrCreate(['key' => 'loyalty_conversion'], ['group' => 'loyalty', 'value' => '100',            'type' => 'integer']);

        // 2. Discounts
        Discount::firstOrCreate(['code' => 'WELCOME2026'], [
            'type'       => 'percentage',
            'value'      => 10,
            'start_date' => now()->subDays(10),
            'end_date'   => now()->addMonths(2),
            'is_active'  => true,
        ]);
        Discount::firstOrCreate(['code' => 'FLAT50K'], [
            'type'      => 'fixed',
            'value'     => 50000,
            'is_active' => true,
        ]);

        // 3. Admin User
        User::firstOrCreate(['email' => 'admin@toko.com'], [
            'name'     => 'Admin Optik',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);

        // 4. Demo Customer
        User::firstOrCreate(['email' => 'customer@toko.com'], [
            'name'           => 'Customer Setia',
            'password'       => Hash::make('password'),
            'role'           => 'user',
            'loyalty_points' => 1500,
        ]);

        $this->command->info('✅ Seeder selesai: Settings, Discounts, dan Users berhasil dibuat.');
        $this->command->info('');
        $this->command->info('📦 Untuk import produk, jalankan:');
        $this->command->info('   php artisan import:optik-products');
    }
}
