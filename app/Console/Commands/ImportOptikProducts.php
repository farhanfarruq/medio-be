<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportOptikProducts extends Command
{
    /**
     * Signature perintah artisan
     * Gunakan: php artisan import:optik-products
     * Opsi tambahan:
     *   --file=PATH         Path custom ke file JSON (default: cari otomatis)
     *   --limit=N           Batasi jumlah produk yang diimport (untuk testing)
     *   --download-images   Download gambar ke storage lokal (default: simpan URL CDN)
     *   --skip-truncate     Jangan hapus data lama (mode upsert/tambah saja)
     */
    protected $signature = 'import:optik-products
                            {--file= : Path ke file JSON (opsional, default otomatis dicari)}
                            {--limit= : Batas jumlah produk yang diimport}
                            {--download-images : Download gambar ke storage lokal}
                            {--download : Alias untuk download-images}
                            {--skip-truncate : Jangan hapus data lama, hanya upsert}';

    protected $description = '📦 Import data produk optik dari file JSON ke database MySQL';

    public function handle(): int
    {
        $this->line('');
        $this->line('╔══════════════════════════════════════════╗');
        $this->line('║     IMPORT DATA PRODUK OPTIK MEDIO       ║');
        $this->line('╚══════════════════════════════════════════╝');
        $this->line('');

        // ── 1. Cari & baca file JSON ──────────────────────────────────────────
        $jsonFile = $this->findJsonFile();
        if (!$jsonFile) {
            $this->error('❌ File JSON tidak ditemukan!');
            $this->line('   Pastikan file berada di folder project atau berikan path via --file.');
            return self::FAILURE;
        }

        $this->info("📂 Membaca file: {$jsonFile}");
        $data = json_decode(file_get_contents($jsonFile), true);

        if (!$data || !is_array($data)) {
            $this->error('❌ Gagal membaca file JSON atau format tidak valid.');
            return self::FAILURE;
        }

        // ── 2. Terapkan limit jika ada ────────────────────────────────────────
        $limit = $this->option('limit');
        if ($limit && is_numeric($limit)) {
            $data = array_slice($data, 0, (int) $limit);
            $this->warn("⚠️  Mode limit aktif: hanya import {$limit} produk pertama.");
        }

        $total = count($data);
        $this->info("📊 Total produk yang akan diimport: {$total}");
        $this->line('');

        // ── 3. Konfirmasi hapus data lama ─────────────────────────────────────
        if (!$this->option('skip-truncate')) {
            $this->warn('🗑️  PERHATIAN: Semua data produk & kategori lama akan DIHAPUS!');
            if (!$this->confirm('Lanjutkan?', true)) {
                $this->info('Import dibatalkan.');
                return self::SUCCESS;
            }
            $this->truncateData();
        }

        // ── 4. Buat kategori ──────────────────────────────────────────────────
        $this->info('🏷️  Membuat kategori...');
        $categories = $this->createCategories();
        $this->line('   ✅ Kategori berhasil dibuat: ' . count($categories) . ' kategori');
        
        // Load existing slugs dari DB agar tidak duplikat saat --skip-truncate
        $this->usedSlugs = Product::pluck('slug')->flip()->map(fn() => true)->toArray();
        $this->line('   📊 Terdeteksi ' . count($this->usedSlugs) . ' produk sudah ada di database.');
        $this->line('');

        // ── 5. Loop import produk ─────────────────────────────────────────────
        $this->info('🚀 Memulai import produk...');
        $downloadImages = $this->option('download-images') || $this->option('download');
        if ($downloadImages) {
            $this->warn('   📷 Mode download gambar AKTIF (proses akan lebih lama)');
        } else {
            $this->line('   💡 Gambar disimpan sebagai URL CDN (tambah --download-images untuk download lokal)');
        }
        $this->line('');

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | ✅ %message%');
        $bar->setMessage('Memulai...');
        $bar->start();

        $successCount = 0;
        $errorCount   = 0;
        $errors       = [];

        // Proses dalam batch untuk efisiensi memory
        $batchSize   = 100;
        $batchInsert = [];

        foreach ($data as $index => $item) {
            try {
                $categoryId = $this->detectCategory($item, $categories);
                $imagePath  = $this->processImage(
                    $item['image_url'] ?? null,
                    $item['slug'] ?? Str::slug($item['name']),
                    $downloadImages
                );

                $stock = ($item['stock_status']['raya'] ?? 0) + ($item['stock_status']['gudang'] ?? 0);
                
                // Jika file adalah data_semua_merek, paksa harga dan stok 0
                if (str_contains($jsonFile, 'data_semua_merek')) {
                    $item['price_final'] = 0;
                    $stock = 0;
                    // Gunakan slug sebagai name jika name "Unknown"
                    if (($item['name'] ?? 'Unknown') === 'Unknown') {
                        $item['name'] = ucwords(str_replace('-', ' ', $item['slug']));
                    }
                }

                // Bersihkan tags: unik, hapus single-char noise, lowercase-normalize
                $rawTags = $item['tags'] ?? [];
                $cleanTags = array_values(array_unique(
                    array_filter($rawTags, fn($t) => mb_strlen(trim($t)) > 1)
                ));

                $now = now()->toDateTimeString();

                // Ambil jumlah best seller yang sudah ada di kategori ini (untuk pembatasan)
                static $categoryBestSellerCount = [];
                if (!isset($categoryBestSellerCount[$categoryId])) {
                    $categoryBestSellerCount[$categoryId] = \App\Models\Product::where('category_id', $categoryId)->where('is_best_seller', 1)->count();
                }

                $shouldBeBestSeller = ($item['is_bestseller'] ?? false);
                // Batasi maksimal 5 best seller per kategori agar UI rapi
                if ($shouldBeBestSeller && $categoryBestSellerCount[$categoryId] >= 5) {
                    $shouldBeBestSeller = false;
                }
                if ($shouldBeBestSeller) {
                    $categoryBestSellerCount[$categoryId]++;
                }

                $name = $item['name'];
                $sku  = $item['sku'] ?? null;

                // Jika --skip-truncate aktif, kita cek apakah produk ini SUDAH ADA (berdasarkan Nama + SKU)
                if ($this->option('skip-truncate')) {
                    $existing = Product::where('name', $name)
                        ->where('sku', $sku)
                        ->exists();
                    
                    if ($existing) {
                        $bar->advance();
                        continue;
                    }
                }

                $slug = $this->makeUniqueSlug($item['slug'] ?? Str::slug($name));
                
                $batchInsert[] = [
                    'category_id'              => $categoryId,
                    'name'                     => $name,
                    'slug'                     => $slug,
                    'sku'                      => $sku,
                    'description'              => $this->generateDescription($item),
                    'brand'                    => $item['brand'] ?? null,
                    'price'                    => (Str::contains($categoryName, 'Lensa Kacamata') && ($item['price_final'] ?? 0) <= 0) ? 100000 : ($item['price_final'] ?? $item['price_original'] ?? 0),
                    'stock'                    => Str::contains($categoryName, 'Lensa Kacamata') ? 999 : $stock,
                    'weight'                   => 200, // ~200 gram termasuk kemasan
                    'dimensions'               => null,
                    'variants'                 => null,
                    'images'                   => json_encode(array_filter([$imagePath])),
                    'tags'                     => json_encode($cleanTags),
                    'is_active'                => 1,
                    'is_best_seller'           => $shouldBeBestSeller ? 1 : 0,
                    'is_new'                   => ($item['is_new'] ?? false) ? 1 : 0,
                    'is_not_for_sale'          => str_contains($jsonFile, 'data_semua_merek') ? 1 : 0,
                    'is_prescription_required' => 0,
                    'created_at'               => $now,
                    'updated_at'               => $now,
                ];

                $successCount++;

                // Flush batch ke DB setiap 100 produk
                if (count($batchInsert) >= $batchSize) {
                    DB::table('products')->insert($batchInsert);
                    $batchInsert = [];
                }
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = "[{$item['name']}] " . $e->getMessage();
            }

            $bar->setMessage("{$successCount} produk berhasil");
            $bar->advance();
        }

        // Flush sisa batch
        if (!empty($batchInsert)) {
            DB::table('products')->insert($batchInsert);
        }

        $bar->finish();
        $this->line('');
        $this->line('');

        // ── 6. Ringkasan hasil ────────────────────────────────────────────────
        $this->line('╔══════════════════════════════════════════╗');
        $this->line('║              HASIL IMPORT                ║');
        $this->line('╠══════════════════════════════════════════╣');
        $this->line("║  ✅ Berhasil  : {$successCount} produk");
        if ($errorCount > 0) {
            $this->line("║  ❌ Gagal     : {$errorCount} produk");
        }
        $this->line("║  🏷️  Kategori  : " . count($categories) . " kategori");
        $this->line('╚══════════════════════════════════════════╝');

        if (!empty($errors) && $this->output->isVerbose()) {
            $this->line('');
            $this->warn('Detail error (-v untuk lihat):');
            foreach (array_slice($errors, 0, 10) as $err) {
                $this->line("  • {$err}");
            }
        }

        $this->line('');
        $this->info('🎉 Import selesai!');
        $this->line('   Jalankan: php artisan storage:link (jika belum)');
        $this->line('   Lalu buka: http://127.0.0.1:8000/admin untuk melihat hasilnya.');

        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helper: cari file JSON otomatis
    // ──────────────────────────────────────────────────────────────────────────
    private function findJsonFile(): ?string
    {
        // Dari --file option
        if ($customPath = $this->option('file')) {
            return file_exists($customPath) ? $customPath : null;
        }

        // Cari di beberapa lokasi umum
        $candidates = [
            base_path('data_sunglasses.json'),         // Kacamata Hitam
            base_path('../data_sunglasses.json'),
            base_path('data_semua_merek.json'),       // Informasi merek lensa
            base_path('../data_semua_merek.json'),
            base_path('data_lensa_kontak.json'),       // Lensa kontak
            base_path('../data_lensa_kontak.json'),
            base_path('data_optik_lengkap.json'),       // Frame
            base_path('../data_optik_lengkap.json'),
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return realpath($path);
            }
        }

        return null;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helper: hapus data lama
    // ──────────────────────────────────────────────────────────────────────────
    private function truncateData(): void
    {
        $this->line('🗑️  Menghapus data lama...');
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('order_items')->truncate();
        DB::table('products')->truncate();
        DB::table('categories')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $this->line('   ✅ Data lama berhasil dihapus.');
        $this->line('');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helper: buat kategori
    // ──────────────────────────────────────────────────────────────────────────
    private function createCategories(): array
    {
        $categoryList = [
            'frame-pria'    => ['name' => 'Frame Pria',          'description' => 'Koleksi frame kacamata untuk pria modern'],
            'frame-wanita'  => ['name' => 'Frame Wanita',        'description' => 'Koleksi frame kacamata elegan untuk wanita'],
            'frame-unisex'  => ['name' => 'Frame Unisex',        'description' => 'Koleksi frame kacamata unisex untuk semua'],
            'lensa-kacamata'=> ['name' => 'Lensa Kacamata',      'description' => 'Lensa kacamata berkualitas tinggi'],
            'kacamata-hitam'=> ['name' => 'Kacamata Hitam',      'description' => 'Sunglasses & kacamata hitam stylish'],
            'softlens'      => ['name' => 'Softlens',            'description' => 'Lensa kontak softlens berbagai pilihan'],
            'aksesoris'     => ['name' => 'Aksesoris & Perawatan','description' => 'Aksesoris dan produk perawatan kacamata'],
        ];

        $result = [];
        foreach ($categoryList as $slug => $data) {
            $category = Category::firstOrCreate(
                ['slug' => $slug],
                [
                    'name'        => $data['name'],
                    'description' => $data['description'],
                    'is_active'   => true,
                ]
            );
            $result[$slug] = $category->id;
        }

        return $result;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helper: deteksi kategori berdasarkan tags gender
    // ──────────────────────────────────────────────────────────────────────────
    private function detectCategory(array $item, array $categories): int
    {
        $tags = array_map('strtolower', $item['tags'] ?? []);
        $name = strtolower($item['name'] ?? '');
        $url  = strtolower($item['image_url'] ?? '');

        // Cek apakah ini Lensa Kontak (Softlens)
        $lensKeywords = ['contact lens', 'softlens', 'plano', 'monthly', 'daily', 'lensa kontak'];
        $isLens = false;
        foreach ($lensKeywords as $kw) {
            if (str_contains($name, $kw) || in_array($kw, $tags)) {
                $isLens = true;
                break;
            }
        }
        // Cek via URL (zcls = contact lens)
        if (str_contains($url, '/zcls/')) $isLens = true;

        // Cek jika ini adalah Merek Lensa (dari data_semua_merek)
        if (str_contains($url, '/brand/') && !str_contains($url, '/zfrm/')) {
            return $categories['lensa-kacamata'] ?? array_values($categories)[0];
        }

        // Cek apakah ini Kacamata Hitam (Sunglasses)
        $sunglassKeywords = ['sunglasses', 'kacamata hitam', 'kacamata gaya'];
        $isSunglasses = false;
        foreach ($sunglassKeywords as $kw) {
            if (str_contains($name, $kw) || in_array($kw, $tags)) {
                $isSunglasses = true;
                break;
            }
        }
        // Pola URL Sunglasses Melawai: /zsgl/, /zsun/, atau file yang mengandung kata sunglasses
        if (str_contains($url, '/zsgl/') || str_contains($url, '/zsun/') || str_contains($url, 'sunglasses')) $isSunglasses = true;
        // Pola SKU Sunglasses biasanya diawali 'S '
        if (str_starts_with($item['sku'] ?? '', 'S ')) $isSunglasses = true;

        if ($isSunglasses) {
            return $categories['kacamata-hitam'] ?? array_values($categories)[0];
        }

        if ($isLens) {
            return $categories['softlens'] ?? array_values($categories)[0];
        }

        // Jika Frame, cek Gender
        $maleTags   = ['male', 'man', 'men', 'pria'];
        $femaleTags = ['female', 'woman', 'women', 'wanita'];

        $hasMale   = (bool) array_filter($tags, fn($t) => in_array(trim($t), $maleTags));
        $hasFemale = (bool) array_filter($tags, fn($t) => in_array(trim($t), $femaleTags));

        if ($hasMale && !$hasFemale) return $categories['frame-pria'];
        if ($hasFemale && !$hasMale) return $categories['frame-wanita'];

        return $categories['frame-unisex'];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helper: proses gambar (download atau simpan URL)
    // ──────────────────────────────────────────────────────────────────────────
    private function processImage(?string $url, string $slug, bool $download): string
    {
        if (!$url) return '';

        if (!$download) {
            // Simpan URL CDN langsung - gambar akan di-load dari CloudFront
            return $url;
        }

        try {
            $ext      = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
            $safeSlug = Str::limit($slug, 80, ''); // batasi panjang filename
            $filename = "products/{$safeSlug}.{$ext}";

            // Skip jika sudah ada
            if (Storage::disk('public')->exists($filename)) {
                return Storage::disk('public')->url($filename);
            }

            $response = Http::timeout(8)->get($url);

            if ($response->successful()) {
                Storage::disk('public')->put($filename, $response->body());
                return $filename; // Simpan path relatif agar kompatibel dengan Filament Admin
            }
        } catch (\Exception $e) {
            // Fallback ke URL asli jika download gagal
        }

        return $url;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helper: buat slug unik jika ada duplikat
    // ──────────────────────────────────────────────────────────────────────────
    private array $usedSlugs = [];

    private function makeUniqueSlug(string $slug): string
    {
        // Cek di memori dulu (lebih cepat dari DB query untuk setiap produk)
        if (!isset($this->usedSlugs[$slug])) {
            $this->usedSlugs[$slug] = true;
            return $slug;
        }

        // Jika duplikat, tambah suffix
        $counter = 2;
        $newSlug  = $slug . '-' . $counter;
        while (isset($this->usedSlugs[$newSlug])) {
            $counter++;
            $newSlug = $slug . '-' . $counter;
        }
        $this->usedSlugs[$newSlug] = true;
        return $newSlug;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helper: buat deskripsi produk dari data yang ada
    // ──────────────────────────────────────────────────────────────────────────
    private function generateDescription(array $item): string
    {
        $tags  = $item['tags'] ?? [];
        $brand = $item['brand'] ?? 'brand ternama';
        $name  = $item['name'] ?? '';
        
        $tagsUpper = array_map('strtoupper', $tags);
        
        // Deteksi apakah ini Sunglasses (agar tidak tertukar dengan Lensa)
        $isSunglasses = str_contains(strtolower($item['image_url'] ?? ''), '/zsun/') || 
                         str_contains(strtolower($item['image_url'] ?? ''), '/zsgl/') ||
                         str_starts_with($item['sku'] ?? '', 'S ');

        $isLens = !$isSunglasses && (str_contains(strtolower($item['image_url'] ?? ''), '/zcls/') || in_array('COLOUR', $tagsUpper) || (in_array('CLEAR', $tagsUpper) && !in_array('FRAME', $tagsUpper)));

        if ($isLens) {
            $duration = $this->findTag($tags, ['DAILY', 'MONTHLY', 'THREE MONTHLY', '6 MONTHS']);
            $type = in_array('COLOUR', $tagsUpper) ? 'Warna' : 'Bening';
            
            $desc = "Lensa kontak {$brand} {$type}. ";
            if ($duration) $desc .= "Pemakaian " . ucwords(strtolower($duration)) . ". ";
            $desc .= "Memberikan kenyamanan mata sepanjang hari dengan kualitas optik terbaik dari {$brand}. ";
            $desc .= "Cocok untuk penggunaan harian. SKU: {$name}.";
            return $desc;
        }

        // Cari atribut dari tags untuk Frame
        $material = $this->findTag($tags, ['PLASTIC', 'METAL', 'TITANIUM', 'ACETATE', 'TR90', 'TR9', 'STAINLESS STEEL', 'STAINLESS']);
        $shape    = $this->findTag($tags, ['RECTANGLE', 'OVAL', 'ROUND', 'SQUARE', 'CAT EYE', 'AVIATOR', 'WAYFARER', 'BUTTERFLY', 'GEOMETRIC']);
        $frameType= $this->findTag($tags, ['FULL FRAME', 'HALF FRAME', 'RIMLESS', 'SEMI RIMLESS']);
        $color    = $this->findTag($tags, ['BLACK', 'BROWN', 'BLUE', 'RED', 'GREEN', 'GOLD', 'SILVER', 'GREY', 'GRAY', 'CRYSTAL', 'CLEAR', 'WHITE', 'PINK', 'PURPLE']);

        $parts = array_filter([
            $brand,
            $material ? ucwords(strtolower($material)) : null,
            $shape    ? 'Bentuk ' . ucwords(strtolower($shape)) : null,
            $frameType ? ucwords(strtolower($frameType)) : null,
            $color    ? 'Warna ' . ucwords(strtolower($color)) : null,
        ]);

        $desc = 'Frame kacamata ' . implode(', ', $parts) . '. ';
        $desc .= "Produk original {$brand} berkualitas tinggi, cocok untuk gaya sehari-hari maupun formal. ";
        $desc .= "Referensi SKU: {$name}.";

        return $desc;
    }

    /**
     * Cari tag yang cocok dari daftar kandidat (case-insensitive)
     */
    private function findTag(array $tags, array $candidates): ?string
    {
        $tagsUpper = array_map('strtoupper', $tags);
        foreach ($candidates as $candidate) {
            if (in_array(strtoupper($candidate), $tagsUpper)) {
                return $candidate;
            }
        }
        return null;
    }
}
