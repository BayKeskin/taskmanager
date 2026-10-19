<?php
namespace Modules\Storage\Services;

class StorageService
{
    private static ?array $config = null;

    /**
     * Yapılandırmayı yükler
     */
    public static function getConfig(): array
    {
        if (self::$config === null) {
            $configFile = dirname(__DIR__) . '/Config/storage.php';
            if (!file_exists($configFile)) {
                $configFile = dirname(__DIR__, 2) . '/Core/Config/storage.php';
            }
            if (file_exists($configFile)) {
                self::$config = require $configFile;
            } else {
                self::$config = [
                    'default' => 'local',
                    'disks' => [
                        'local' => [
                            'driver' => 'local',
                            'root'   => dirname(__DIR__, 3) . '/Public/uploads',
                            'url'    => '/taskmanager/Public/uploads',
                        ]
                    ],
                    'max_size_bytes' => 20 * 1024 * 1024,
                    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'zip', 'rar', '7z', 'txt', 'docx', 'xlsx'],
                    'disallowed_extensions' => ['php', 'phtml', 'phar', 'exe', 'sh', 'bat', 'cmd', 'js']
                ];
            }

            // Settings tablosundaki dinamik depolama kısıtlamalarını uygula
            try {
                $storageModel = new \Modules\Storage\Models\StorageModel();
                $s = $storageModel->getStorageSettings();
                if ($s) {
                    if (!empty($s['max_file_upload_mb']) && (int)$s['max_file_upload_mb'] > 0) {
                        self::$config['max_size_bytes'] = (int)$s['max_file_upload_mb'] * 1024 * 1024;
                    }
                    if (!empty($s['allowed_file_types'])) {
                        $rawList = array_map(function($e) {
                            return ltrim(trim(strtolower($e)), '.');
                        }, explode(',', $s['allowed_file_types']));
                        $extList = array_values(array_filter($rawList, fn($e) => !empty($e)));
                        if (in_array('jpg', $extList, true) && !in_array('jpeg', $extList, true)) {
                            $extList[] = 'jpeg';
                        }
                        if (in_array('jpeg', $extList, true) && !in_array('jpg', $extList, true)) {
                            $extList[] = 'jpg';
                        }
                        if (!empty($extList)) {
                            self::$config['allowed_extensions'] = array_values(array_unique($extList));
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }
        return self::$config;
    }

    /**
     * Yüklenen dosyanın güvenlik ve boyut doğrulamasını yapar
     */
    public static function validate(array $file): array
    {
        $config = self::getConfig();

        if (empty($file) || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            $msg = __('Storage::upload_error');
            return ['valid' => false, 'error' => $msg];
        }

        // 1. Size Check
        $maxBytes = $config['max_size_bytes'] ?? (20 * 1024 * 1024);
        if ($file['size'] > $maxBytes) {
            $maxMb = round($maxBytes / (1024 * 1024));
            $msg = __('Storage::file_too_large');
            return ['valid' => false, 'error' => $msg];
        }

        $origName = basename($file['name']);
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        // 2. Dangerous Executable Binary Check
        $disallowed = $config['disallowed_extensions'] ?? ['phtml', 'phar', 'exe', 'sh', 'bat', 'cmd', 'bin', 'vbs', 'scr', 'cgi', 'pl', 'msi', 'com'];
        if (in_array($ext, $disallowed, true)) {
            $msg = __('Storage::disallowed_extension');
            return ['valid' => false, 'error' => $msg];
        }

        // 3. Allowed Extension Check
        $codeExtensions = ['php', 'js', 'css', 'html', 'sql', 'py', 'json', 'ts', 'jsx', 'tsx', 'vue', 'xml', 'yaml', 'yml'];
        $allowedExts = $config['allowed_extensions'] ?? [];
        if (!empty($allowedExts)) {
            $allowedExts = array_values(array_unique(array_merge($allowedExts, $codeExtensions)));
            // jpeg ve jpg eşdeğer kabul edilir
            if (in_array('jpg', $allowedExts, true) && !in_array('jpeg', $allowedExts, true)) {
                $allowedExts[] = 'jpeg';
            }
            if (in_array('jpeg', $allowedExts, true) && !in_array('jpg', $allowedExts, true)) {
                $allowedExts[] = 'jpg';
            }
        }

        if (!empty($allowedExts) && !in_array($ext, $allowedExts, true)) {
            $msg = __('Storage::invalid_extension');
            return ['valid' => false, 'error' => $msg];
        }

        // 4. MIME-Type Detection
        $mime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detectedMime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if ($detectedMime) {
                    $mime = $detectedMime;
                }
            }
        } elseif (function_exists('mime_content_type')) {
            $mime = mime_content_type($file['tmp_name']) ?: $mime;
        }

        return [
            'valid'        => true,
            'orig_name'    => $origName,
            'ext'          => $ext,
            'is_code_file' => in_array($ext, $codeExtensions, true),
            'mime'         => $mime,
            'size'         => (int)$file['size']
        ];
    }

    /**
     * Güvenli dosya yükleme işlemi yapar
     */
    public static function upload(array $file, string $subfolder = 'tasks', ?string $disk = null): array
    {
        $val = self::validate($file);
        if (!$val['valid']) {
            return ['success' => false, 'error' => $val['error']];
        }

        $config = self::getConfig();
        $diskName = $disk ?: ($config['default'] ?? 'local');
        $diskCfg = $config['disks'][$diskName] ?? $config['disks']['local'];

        $diskExt = in_array($val['ext'], ['php', 'phtml', 'phar', 'js', 'html', 'htm', 'cgi'], true) 
            ? $val['ext'] . '.safe' 
            : $val['ext'];
        $safeName = $subfolder . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $diskExt;

        // Yerel (Local) Depolama
        $localRoot = rtrim($config['disks']['local']['root'] ?? (dirname(__DIR__, 3) . '/Public/uploads'), '/\\');
        $targetDir = $localRoot . DIRECTORY_SEPARATOR . $subfolder;
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        $destPath = $targetDir . DIRECTORY_SEPARATOR . $safeName;
        $moved = is_uploaded_file($file['tmp_name']) 
            ? move_uploaded_file($file['tmp_name'], $destPath) 
            : (@copy($file['tmp_name'], $destPath) || @rename($file['tmp_name'], $destPath));

        if (!$moved) {
            return ['success' => false, 'error' => __('Storage::save_error')];
        }

        $webBase = rtrim($config['disks']['local']['url'] ?? '/taskmanager/Public/uploads', '/');
        $webUrl = $webBase . '/' . $subfolder . '/' . $safeName;

        return [
            'success'   => true,
            'file_name' => $val['orig_name'],
            'file_path' => $webUrl,
            'url'       => $webUrl,
            'path'      => $destPath,
            'storage'   => 'local',
            'file_size' => $val['size'],
            'file_type' => $val['mime'],
            'ext'       => $val['ext'],
            'is_image'  => str_starts_with($val['mime'], 'image/')
        ];
    }

    /**
     * String içeriğini dosya olarak depolar
     */
    public static function put(string $content, string $filename, string $subfolder = 'tasks', string $mime = 'application/octet-stream'): array
    {
        $config = self::getConfig();
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'bin';
        $safeName = $subfolder . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;

        $localRoot = rtrim($config['disks']['local']['root'] ?? (dirname(__DIR__, 2) . '/Public/uploads'), '/\\');
        $targetDir = $localRoot . DIRECTORY_SEPARATOR . $subfolder;
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        $destPath = $targetDir . DIRECTORY_SEPARATOR . $safeName;
        if (file_put_contents($destPath, $content) === false) {
            return ['success' => false, 'error' => __('Storage::write_error')];
        }

        $webBase = rtrim($config['disks']['local']['url'] ?? '/taskmanager/Public/uploads', '/');
        $webUrl = $webBase . '/' . $subfolder . '/' . $safeName;

        return [
            'success'   => true,
            'file_name' => $filename,
            'file_path' => $webUrl,
            'storage'   => 'local',
            'file_size' => strlen($content),
            'file_type' => $mime,
            'ext'       => $ext,
            'is_image'  => str_starts_with($mime, 'image/')
        ];
    }

    /**
     * Dosyayı siler
     */
    public static function delete(string $filePathOrUrl): bool
    {
        if (empty($filePathOrUrl)) {
            return false;
        }

        if (str_contains($filePathOrUrl, '/Public/uploads/')) {
            $parts = explode('/Public/uploads/', $filePathOrUrl);
            $rel = end($parts);
            $config = self::getConfig();
            $localRoot = rtrim($config['disks']['local']['root'] ?? (dirname(__DIR__, 2) . '/Public/uploads'), '/\\');
            $fullPath = $localRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (file_exists($fullPath)) {
                return @unlink($fullPath);
            }
        }

        return false;
    }
}
