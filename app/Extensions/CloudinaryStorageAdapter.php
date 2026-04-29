<?php

namespace App\Extensions;

use Cloudinary\Cloudinary;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter as FlysystemAdapterInterface;
use League\Flysystem\PathPrefixer;

class CloudinaryStorageAdapter implements FlysystemAdapterInterface
{
    protected $cloudinary;
    protected $prefixer;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary(env('CLOUDINARY_URL'));
        $this->prefixer = new PathPrefixer('');
    }

    public function fileExists(string $path): bool
    {
        return true; // Simple implementation
    }

    public function directoryExists(string $path): bool
    {
        return true;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'cloudinary');
        file_put_contents($tempFile, $contents);
        
        // Simpan dengan public_id yang menyertakan folder dan nama file
        $publicId = pathinfo($path, PATHINFO_DIRNAME) . '/' . pathinfo($path, PATHINFO_FILENAME);
        $publicId = ltrim($publicId, './');

        $this->cloudinary->uploadApi()->upload($tempFile, [
            'public_id' => $publicId,
            'resource_type' => 'auto',
        ]);
        
        unlink($tempFile);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->write($path, stream_get_contents($contents), $config);
    }

    public function read(string $path): string
    {
        return file_get_contents($this->getUrl($path));
    }

    public function readStream(string $path)
    {
        return fopen($this->getUrl($path), 'rb');
    }

    public function delete(string $path): void
    {
        $this->cloudinary->uploadApi()->destroy($path);
    }

    public function deleteDirectory(string $path): void
    {
        // Not implemented for simplicity
    }

    public function createDirectory(string $path, Config $config): void
    {
        // Not needed for Cloudinary
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // Not needed
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, 'public');
    }

    public function mimeType(string $path): FileAttributes
    {
        return new FileAttributes($path, null, null, null, 'image/png');
    }

    public function lastModified(string $path): FileAttributes
    {
        return new FileAttributes($path, null, null, time());
    }

    public function fileSize(string $path): FileAttributes
    {
        return new FileAttributes($path, 0);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        return [];
    }

    public function move(string $source, string $destination, Config $config): void
    {
        // Not implemented
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        // Not implemented
    }

    public function getUrl(string $path): string
    {
        $publicId = pathinfo($path, PATHINFO_DIRNAME) . '/' . pathinfo($path, PATHINFO_FILENAME);
        $publicId = ltrim($publicId, './');
        
        // Paksa skema HTTPS
        return (string) $this->cloudinary->image($publicId)->secure(true)->toUrl();
    }
}
