<?php

/**
 * TaskManager Gelişmiş Depolama ve Dosya Yöneticisi (Local & AWS S3 / Cloudflare R2)
 * Sıfır harici bağımlılık (Zero-dependency) ile hem yerel dizinde hem de AWS SigV4 uyumlu bulut sistemlerinde çalışır.
 */
class Storage
{
    private static ?array $config = null;

    /**
     * Yapılandırmayı yükler
     */
    public static function getConfig(): array
    {
        if (self::$config === null) {
            $configFile = __DIR__ . '/../config/storage.php';
            if (file_exists($configFile)) {
                self::$config = require $configFile;
            } else {
                self::$config = [
                    'default' => 'local',
                    'disks' => [
                        'local' => [
                            'driver' => 'local',
                            'root'   => __DIR__ . '/../Public/uploads',
                            'url'    => '/taskmanager/Public/uploads',
                        ]
                    ],
                    'max_size_bytes' => 20 * 1024 * 1024,
                    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'zip', 'rar', '7z', 'txt', 'docx', 'xlsx'],
                    'disallowed_extensions' => ['php', 'phtml', 'phar', 'exe', 'sh', 'bat', 'cmd', 'js']
                ];
            }

            // Veritabanı settings tablosundaki dinamik depolama kısıtlamalarını uygula
            try {
                if (class_exists('Database')) {
                    $db = new Database();
                    $s = $db->connect->query("SELECT max_file_upload_mb, allowed_file_types FROM settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
                    if (!empty($s['max_file_upload_mb']) && (int)$s['max_file_upload_mb'] > 0) {
                        self::$config['max_size_bytes'] = (int)$s['max_file_upload_mb'] * 1024 * 1024;
                    }
                    if (!empty($s['allowed_file_types'])) {
                        $extList = array_map('trim', explode(',', strtolower($s['allowed_file_types'])));
                        $extList = array_filter($extList, fn($e) => !empty($e));
                        if (!empty($extList)) {
                            self::$config['allowed_extensions'] = array_values($extList);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Config varsayılanlarını koru
            }
        }
        return self::$config;
    }

    /**
     * Yüklenen dosyanın güvenlik ve boyut doğrulamasını yapar
     */
    public static function validate(array $file): array
    {
        $config = self::getConfig();
        $isTr = (class_exists('Language') && Language::getCurrentLanguage() === 'tr');

        if (empty($file) || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            $msg = $isTr 
                ? "Dosya yükleme hatası (Hata kodu: {$errCode})." 
                : "File upload error (Error code: {$errCode}).";
            return ['valid' => false, 'error' => $msg];
        }

        // 1. Size Check
        $maxBytes = $config['max_size_bytes'] ?? (20 * 1024 * 1024);
        if ($file['size'] > $maxBytes) {
            $maxMb = round($maxBytes / (1024 * 1024));
            $msg = $isTr 
                ? "Dosya boyutu çok büyük. İzin verilen maksimum boyut: {$maxMb} MB." 
                : "File size too large. Maximum allowed size is {$maxMb} MB.";
            return ['valid' => false, 'error' => $msg];
        }

        $origName = basename($file['name']);
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        // 2. Dangerous Executable Binary Check (Binary executables that must never be uploaded)
        $disallowed = $config['disallowed_extensions'] ?? ['phtml', 'phar', 'exe', 'sh', 'bat', 'cmd', 'bin', 'vbs', 'scr', 'cgi', 'pl', 'msi', 'com'];
        if (in_array($ext, $disallowed, true)) {
            $msg = $isTr 
                ? "Güvenlik uyarısı: '{$ext}' uzantılı çalıştırılabilir dosyalar yüklenemez." 
                : "Security warning: '{$ext}' executable files cannot be uploaded.";
            return ['valid' => false, 'error' => $msg];
        }

        // 3. Allowed Extension Check (Includes source code: php, js, html, css, sql, py, json, etc.)
        $codeExtensions = ['php', 'js', 'css', 'html', 'sql', 'py', 'json', 'ts', 'jsx', 'tsx', 'vue', 'xml', 'yaml', 'yml'];
        $allowedExts = $config['allowed_extensions'] ?? [];
        if (!empty($allowedExts)) {
            $allowedExts = array_values(array_unique(array_merge($allowedExts, $codeExtensions)));
        }

        if (!empty($allowedExts) && !in_array($ext, $allowedExts, true)) {
            $msg = $isTr 
                ? ("Desteklenmeyen dosya türü. İzin verilenler: " . implode(', ', array_slice($allowedExts, 0, 10)) . "...") 
                : ("Unsupported file type. Allowed extensions: " . implode(', ', array_slice($allowedExts, 0, 10)) . "...");
            return ['valid' => false, 'error' => $msg];
        }

        // 4. Real MIME-Type Detection (finfo)
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

        $isCodeFile = in_array($ext, $codeExtensions, true);

        return [
            'valid'        => true,
            'orig_name'    => $origName,
            'ext'          => $ext,
            'is_code_file' => $isCodeFile,
            'mime'         => $mime,
            'size'         => (int)$file['size']
        ];
    }

    /**
     * Güvenli dosya yükleme işlemi yapar
     * @param array $file $_FILES['field'] dizisi
     * @param string $subfolder Alt klasör (tasks, comments, submissions, docs)
     * @param string|null $disk Belirtilmezse varsayılan disk kullanılır
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

        // Benzersiz ve güvenli dosya adı üretimi
        // PHP ve web ortamında çalıştırılabilir betikler diskte .safe uzantısıyla saklanır (güvenlik)
        $diskExt = in_array($val['ext'], ['php', 'phtml', 'phar', 'js', 'html', 'htm', 'cgi'], true) 
            ? $val['ext'] . '.safe' 
            : $val['ext'];
        $safeName = $subfolder . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $diskExt;

        if ($diskCfg['driver'] === 's3' && !empty($diskCfg['key']) && !empty($diskCfg['bucket'])) {
            // S3 / Cloudflare R2 Bulut Yükleme
            $s3Path = trim($subfolder, '/') . '/' . $safeName;
            $uploadRes = self::uploadToS3($file['tmp_name'], $s3Path, $val['mime'], $diskCfg);
            if ($uploadRes['success']) {
                return [
                    'success'   => true,
                    'file_name' => $val['orig_name'],
                    'file_path' => $uploadRes['url'],
                    'storage'   => 's3',
                    'file_size' => $val['size'],
                    'file_type' => $val['mime'],
                    'ext'       => $val['ext'],
                    'is_image'  => str_starts_with($val['mime'], 'image/')
                ];
            }
            // S3 başarısız olursa güvenli fallback olarak lokale yaz
            error_log("Storage S3 upload failed, falling back to local: " . ($uploadRes['error'] ?? ''));
        }

        // Yerel (Local) Depolama
        $localRoot = rtrim($config['disks']['local']['root'] ?? (__DIR__ . '/../Public/uploads'), '/\\');
        $targetDir = $localRoot . DIRECTORY_SEPARATOR . $subfolder;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $destPath = $targetDir . DIRECTORY_SEPARATOR . $safeName;
        $moved = is_uploaded_file($file['tmp_name']) 
            ? move_uploaded_file($file['tmp_name'], $destPath) 
            : (@copy($file['tmp_name'], $destPath) || @rename($file['tmp_name'], $destPath));
        if (!$moved) {
            $isTr = (class_exists('Language') && Language::getCurrentLanguage() === 'tr');
            $msg = $isTr ? 'Dosya sunucu dizinine kaydedilemedi.' : 'Failed to save file to server directory.';
            return ['success' => false, 'error' => $msg];
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
     * String içeriğini (örn. base64 / clipboard blob) dosya olarak depolar
     */
    public static function put(string $content, string $filename, string $subfolder = 'tasks', string $mime = 'application/octet-stream'): array
    {
        $config = self::getConfig();
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'bin';
        $safeName = $subfolder . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;

        $localRoot = rtrim($config['disks']['local']['root'] ?? (__DIR__ . '/../Public/uploads'), '/\\');
        $targetDir = $localRoot . DIRECTORY_SEPARATOR . $subfolder;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $destPath = $targetDir . DIRECTORY_SEPARATOR . $safeName;
        if (file_put_contents($destPath, $content) === false) {
            $isTr = (class_exists('Language') && Language::getCurrentLanguage() === 'tr');
            $msg = $isTr ? 'Dosya içeriği diske yazılamadı.' : 'Failed to write file content to disk.';
            return ['success' => false, 'error' => $msg];
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

        // Eğer yerel URL ise
        if (str_contains($filePathOrUrl, '/Public/uploads/')) {
            $parts = explode('/Public/uploads/', $filePathOrUrl);
            $rel = end($parts);
            $config = self::getConfig();
            $localRoot = rtrim($config['disks']['local']['root'] ?? (__DIR__ . '/../Public/uploads'), '/\\');
            $fullPath = $localRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (file_exists($fullPath)) {
                return @unlink($fullPath);
            }
        }

        return false;
    }

    /**
     * AWS S3 / Cloudflare R2 REST API ile AWS Signature V4 yükleme istemcisi
     */
    private static function uploadToS3(string $tempFilePath, string $s3Key, string $contentType, array $cfg): array
    {
        $accessKey = $cfg['key'] ?? '';
        $secretKey = $cfg['secret'] ?? '';
        $region    = $cfg['region'] ?: 'us-east-1';
        $bucket    = $cfg['bucket'] ?? '';
        $endpoint  = $cfg['endpoint'] ?: "https://{$bucket}.s3.{$region}.amazonaws.com";

        $isTr = (class_exists('Language') && Language::getCurrentLanguage() === 'tr');

        if (empty($accessKey) || empty($secretKey) || empty($bucket)) {
            $msg = $isTr ? 'S3 kimlik bilgileri yapılandırılmamış.' : 'S3 credentials are not configured.';
            return ['success' => false, 'error' => $msg];
        }

        $fileData = file_get_contents($tempFilePath);
        if ($fileData === false) {
            $msg = $isTr ? 'Kaynak dosya okunamadı.' : 'Source file could not be read.';
            return ['success' => false, 'error' => $msg];
        }

        // URL formatlama
        $parsedEndpoint = parse_url($endpoint);
        $host = $parsedEndpoint['host'] ?? "{$bucket}.s3.{$region}.amazonaws.com";
        $scheme = $parsedEndpoint['scheme'] ?? 'https';

        if (!empty($cfg['use_path_style_endpoint'])) {
            $uri = '/' . trim($bucket, '/') . '/' . ltrim($s3Key, '/');
        } else {
            $uri = '/' . ltrim($s3Key, '/');
        }

        $url = "{$scheme}://{$host}{$uri}";
        $timestamp = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $payloadHash = hash('sha256', $fileData);

        // SigV4 Headers
        $headers = [
            'content-type'        => $contentType,
            'host'                => $host,
            'x-amz-content-sha256'=> $payloadHash,
            'x-amz-date'          => $timestamp,
        ];
        ksort($headers);

        $canonicalHeaders = '';
        $signedHeadersArr = [];
        foreach ($headers as $k => $v) {
            $canonicalHeaders .= strtolower($k) . ':' . trim($v) . "\n";
            $signedHeadersArr[] = strtolower($k);
        }
        $signedHeaders = implode(';', $signedHeadersArr);

        $canonicalRequest = "PUT\n{$uri}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        $credentialScope = "{$date}/{$region}/s3/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$timestamp}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        // Signing key calculation
        $kDate    = hash_hmac('sha256', $date, 'AWS4' . $secretKey, true);
        $kRegion  = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = "AWS4-HMAC-SHA256 Credential={$accessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: {$contentType}",
            "Host: {$host}",
            "x-amz-date: {$timestamp}",
            "x-amz-content-sha256: {$payloadHash}",
            "Authorization: {$authorization}"
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $finalUrl = !empty($cfg['url']) ? rtrim($cfg['url'], '/') . '/' . ltrim($s3Key, '/') : $url;
            return ['success' => true, 'url' => $finalUrl];
        }

        $errPrefix = $isTr ? "S3 yanıt kodu: {$httpCode}. " : "S3 response code: {$httpCode}. ";
        return [
            'success'   => false,
            'error'     => $errPrefix . ($curlError ?: substr($response, 0, 200)),
            'http_code' => $httpCode
        ];
    }
}
