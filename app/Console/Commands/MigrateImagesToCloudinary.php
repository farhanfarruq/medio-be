<?php

namespace App\Console\Commands;

use App\Models\Product;
use Cloudinary\Cloudinary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MigrateImagesToCloudinary extends Command
{
    protected $signature = 'images:migrate-to-cloudinary 
                            {--dry-run : Lihat preview tanpa mengubah database}
                            {--product= : Migrate hanya satu produk berdasarkan ID}';

    protected $description = 'Migrasi semua gambar produk dari URL eksternal ke Cloudinary';

    private Cloudinary $cloudinary;

    public function handle(): int
    {
        $this->cloudinary = new Cloudinary(env('CLOUDINARY_URL'));

        $isDryRun  = $this->option('dry-run');
        $productId = $this->option('product');

        if ($isDryRun) {
            $this->warn('🔍 MODE DRY-RUN: Tidak ada perubahan yang akan disimpan ke database.');
            $this->newLine();
        }

        $query = Product::withTrashed();

        if ($productId) {
            $query->where('id', $productId);
        }

        $products = $query->get();

        if ($products->isEmpty()) {
            $this->error('Tidak ada produk yang ditemukan.');
            return self::FAILURE;
        }

        $this->info("🚀 Mulai migrasi gambar untuk {$products->count()} produk...");
        $this->newLine();

        $totalImages   = 0;
        $migratedCount = 0;
        $skippedCount  = 0;
        $failedCount   = 0;
        $failedList    = [];

        $bar = $this->output->createProgressBar($products->count());
        $bar->start();

        foreach ($products as $product) {
            $images = $product->images ?? [];

            if (empty($images)) {
                $bar->advance();
                continue;
            }

            $newImages = [];
            $changed   = false;

            foreach ($images as $image) {
                $totalImages++;

                // Skip jika sudah Cloudinary URL
                if (str_contains($image, 'res.cloudinary.com')) {
                    $newImages[] = $image;
                    $skippedCount++;
                    continue;
                }

                // Skip jika bukan URL valid
                if (!str_starts_with($image, 'http')) {
                    $this->newLine();
                    $this->warn("  ⚠️  [{$product->name}] Path lokal dilewati: {$image}");
                    $newImages[] = $image;
                    $skippedCount++;
                    continue;
                }

                // Upload ke Cloudinary langsung dari URL eksternal
                try {
                    if (!$isDryRun) {
                        $result = $this->cloudinary->uploadApi()->upload($image, [
                            'folder'        => 'products',
                            'resource_type' => 'auto',
                        ]);
                        $newImages[] = $result['secure_url'];
                    } else {
                        // Dry run: tampilkan apa yang akan dimigrasi
                        $this->newLine();
                        $this->line("  📤 [{$product->name}] Akan upload: " . substr($image, 0, 80) . '...');
                        $newImages[] = $image; // Tidak diubah saat dry run
                    }

                    $migratedCount++;
                    $changed = true;

                } catch (\Throwable $e) {
                    $this->newLine();
                    $this->error("  ❌ [{$product->name}] Gagal: " . substr($image, 0, 60) . ' — ' . $e->getMessage());
                    Log::error("Cloudinary migration failed for product {$product->id}: " . $e->getMessage(), [
                        'url' => $image,
                    ]);
                    $newImages[] = $image; // Tetap simpan URL lama jika gagal
                    $failedCount++;
                    $failedList[] = "[{$product->name}] {$image}";
                }
            }

            // Simpan ke database jika ada perubahan
            if ($changed && !$isDryRun) {
                $product->withoutTimestamps(fn () => $product->update(['images' => $newImages]));
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // ---- Ringkasan ----
        $this->info('✅ Migrasi selesai!');
        $this->table(
            ['Status', 'Jumlah'],
            [
                ['Total gambar ditemukan', $totalImages],
                ['Berhasil dimigrasi ke Cloudinary', $migratedCount],
                ['Dilewati (sudah Cloudinary / path lokal)', $skippedCount],
                ['Gagal', $failedCount],
            ]
        );

        if (!empty($failedList)) {
            $this->newLine();
            $this->warn('Daftar gambar yang gagal dimigrasi:');
            foreach ($failedList as $failed) {
                $this->line("  - {$failed}");
            }
        }

        if ($isDryRun) {
            $this->newLine();
            $this->info('Jalankan tanpa --dry-run untuk memulai migrasi sesungguhnya:');
            $this->line('  php artisan images:migrate-to-cloudinary');
        }

        return self::SUCCESS;
    }
}
