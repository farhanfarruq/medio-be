<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\Discount;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. App Settings
        \App\Models\AppSetting::updateOrCreate(['key' => 'store_name'],  ['group' => 'general',  'value' => 'Optik Medio', 'type' => 'string']);
        \App\Models\AppSetting::updateOrCreate(['key' => 'store_address'], ['group' => 'general', 'value' => 'Pasar, Bandarsari, Kec. Padang Ratu, Kabupaten Lampung Tengah, Lampung 34175', 'type' => 'string']);
        \App\Models\AppSetting::updateOrCreate(['key' => 'store_phone'], ['group' => 'general', 'value' => '0813-1196-9585', 'type' => 'string']);
        \App\Models\AppSetting::updateOrCreate(['key' => 'store_opening_hours'], ['group' => 'general', 'value' => 'Buka setiap hari, tutup pukul 20.30', 'type' => 'string']);
        \App\Models\AppSetting::updateOrCreate(['key' => 'store_location_url'], ['group' => 'general', 'value' => 'https://www.google.com/maps/place/Optik+Medio/@-5.0873184,104.9593006,17z/data=!4m16!1m9!3m8!1s0x2e474dfd0f3db101:0xfdf2736fd871343f!2sOptik+Medio!8m2!3d-5.0873184!4d104.9618755!9m1!1b1!16s%2Fg%2F11tsn13pql!3m5!1s0x2e474dfd0f3db101:0xfdf2736fd871343f!8m2!3d-5.0873184!4d104.9618755!16s%2Fg%2F11tsn13pql?entry=ttu&g_ep=EgoyMDI2MDQyNi4wIKXMDSoASAFQAw%3D%3D', 'type' => 'string']);
        \App\Models\AppSetting::updateOrCreate(['key' => 'store_testimonials'], [
            'group' => 'general', 
            'value' => json_encode([
                ['name' => 'Ryan Fajar', 'review' => 'Koleksi frame terkini, keren keren..', 'rating' => 5],
                ['name' => 'Ronaldi Putra', 'review' => 'Optik nya bagus, mewah, pelayanan nya ramah.. Owner nya jujur dan berpengalaman..', 'rating' => 5]
            ]), 
            'type' => 'json'
        ]);
        \App\Models\AppSetting::updateOrCreate(['key' => 'tax_rate'],    ['group' => 'finance',   'value' => '11',                  'type' => 'integer']);
        \App\Models\AppSetting::updateOrCreate(['key' => 'loyalty_conversion'], ['group' => 'loyalty', 'value' => '100',            'type' => 'integer']);

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

        // 5. Auto Import Products if empty
        $productCount = Product::count();
        $this->command->info("📊 Jumlah produk terdeteksi: {$productCount}");

        if ($productCount === 0) {
            $this->command->warn('⚠️  Database produk kosong! Memulai import otomatis...');
            
            // Daftar file yang akan dicoba diimport
            $filesToImport = [
                'data_optik_lengkap.json',
                'data_lensa_kontak.json',
                'data_sunglasses.json',
                'data_semua_merek.json',
            ];

            $importedAny = false;

            foreach ($filesToImport as $fileName) {
                $filePath = base_path($fileName);
                
                if (file_exists($filePath)) {
                    $this->command->info("📂 Mengimport file: {$fileName}...");
                    $result = $this->command->call('import:optik-products', [
                        '--file' => $filePath,
                        '--skip-truncate' => true, // Gunakan skip-truncate agar data dari file sebelumnya tidak terhapus
                    ]);

                    if ($result === 0) {
                        $importedAny = true;
                    }
                }
            }

            if (!$importedAny) {
                $this->command->error('❌ Gagal: Tidak ada file JSON produk yang ditemukan di root project!');
                $this->command->warn('   Pastikan file data_*.json sudah di-push ke repository.');
            } else {
                $this->command->info('✅ Seluruh data produk berhasil diproses.');
            }
        } else {
            $this->command->info('✅ Produk sudah ada di database, melewati import otomatis.');
        }

        $this->command->info('✅ Seeder selesai: Settings, Discounts, dan Users berhasil diproses.');
        $this->command->info('');
    }
}
