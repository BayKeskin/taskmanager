<?php
/**
 * Legacy Storage Facade
 * Delegates directly to HMVC Module: Modules\Storage\Services\StorageService
 */
class Storage
{
    public static function getConfig(): array
    {
        return \Modules\Storage\Services\StorageService::getConfig();
    }

    public static function validate(array $file): array
    {
        return \Modules\Storage\Services\StorageService::validate($file);
    }

    public static function upload(array $file, string $subfolder = 'tasks', ?string $disk = null): array
    {
        return \Modules\Storage\Services\StorageService::upload($file, $subfolder, $disk);
    }

    public static function put(string $content, string $filename, string $subfolder = 'tasks', string $mime = 'application/octet-stream'): array
    {
        return \Modules\Storage\Services\StorageService::put($content, $filename, $subfolder, $mime);
    }

    public static function delete(string $filePathOrUrl): bool
    {
        return \Modules\Storage\Services\StorageService::delete($filePathOrUrl);
    }
}
