<?php
/**
 * ProjectAnalyzer - Akıllı Proje, Ortam Değişkeni ve Canlı Site URL Analizcisi
 * 
 * Bu sınıf, yerel çalışma alanlarındaki projelerin mimari çerçevesini tespit eder,
 * kaynak kodları ve yapılandırma dosyalarını tarayarak canlıya dağıtımda değişmesi
 * zorunlu olan ayarları (URL, Veritabanı, Environment, SSL, Debug vb.) çıkarır.
 * 
 * KRİTİK İLKE: Yerel dosyalara ASLA yazma yapmaz, sadece okur ve canlı için
 * izole edilmiş hedef değer şablonunu (Live Env Payload) üretir.
 */
class ProjectAnalyzer
{
    /**
     * Projeyi analiz eder ve tespit edilen çerçeve, konfigürasyon dosyaları,
     * ortam değişkenleri ile canlı payload şablonunu döner.
     *
     * @param string $workspacePath Projenin yerel klasör yolu
     * @param array $liveOverrides Canlı sunucu için kullanıcı/sistem tanımlı hedef değerler
     * @return array
     */
    public static function analyze(string $workspacePath, array $liveOverrides = []): array
    {
        $normalizedPath = rtrim(str_replace('\\', '/', $workspacePath), '/');

        if (!is_dir($normalizedPath)) {
            return [
                'success' => false,
                'error'   => "Workspace directory does not exist or is not readable: {$workspacePath}",
                'path'    => $normalizedPath
            ];
        }

        // 1. Çerçeve (Framework / Stack) Tespiti
        $frameworkInfo = self::detectFramework($normalizedPath);

        // 2. Yapılandırma Dosyaları ve Ortam Değişkenleri Analizi
        $envAnalysis = self::scanConfigurations($normalizedPath, $frameworkInfo['type']);

        // 3. Canlı Sunucu Hedef Değer Şablonu (Live Env Payload) Üretimi
        $livePayload = self::buildLiveEnvPayload($frameworkInfo, $envAnalysis, $liveOverrides);

        return [
            'success'          => true,
            'workspace_path'   => $normalizedPath,
            'framework'        => $frameworkInfo,
            'detected_configs' => $envAnalysis['detected_files'],
            'environment_vars' => $envAnalysis['variables'],
            'database_config'  => $envAnalysis['database'],
            'live_payload'     => $livePayload,
            'analyzed_at'      => date('c')
        ];
    }

    /**
     * Proje dizin yapısını ve kilit dosyaları inceleyerek çerçeveyi belirler.
     */
    public static function detectFramework(string $path): array
    {
        $composerJson = file_exists("{$path}/composer.json") ? @json_decode(@file_get_contents("{$path}/composer.json"), true) : null;
        $packageJson  = file_exists("{$path}/package.json")  ? @json_decode(@file_get_contents("{$path}/package.json"), true)  : null;

        // 1. Laravel
        if (file_exists("{$path}/artisan") && (is_dir("{$path}/app/Http") || isset($composerJson['require']['laravel/framework']))) {
            $laravelVersion = $composerJson['require']['laravel/framework'] ?? 'unknown';
            return [
                'type'         => 'laravel',
                'name'         => 'Laravel PHP Framework',
                'category'     => 'php_framework',
                'version'      => $laravelVersion,
                'public_dir'   => 'public',
                'env_standard' => 'dotenv',
                'icon'         => 'fab fa-laravel text-danger'
            ];
        }

        // 2. WordPress
        if (file_exists("{$path}/wp-config.php") || file_exists("{$path}/wp-config-sample.php") || (file_exists("{$path}/wp-load.php") && is_dir("{$path}/wp-includes"))) {
            return [
                'type'         => 'wordpress',
                'name'         => 'WordPress CMS',
                'category'     => 'cms',
                'version'      => self::detectWordpressVersion($path),
                'public_dir'   => '',
                'env_standard' => 'php_constants',
                'icon'         => 'fab fa-wordpress text-primary'
            ];
        }

        // 3. CodeIgniter 4
        if (file_exists("{$path}/spark") && is_dir("{$path}/app/Config")) {
            return [
                'type'         => 'codeigniter4',
                'name'         => 'CodeIgniter 4',
                'category'     => 'php_framework',
                'version'      => $composerJson['require']['codeigniter4/framework'] ?? '4.x',
                'public_dir'   => 'public',
                'env_standard' => 'dotenv',
                'icon'         => 'fas fa-fire text-warning'
            ];
        }

        // 4. CodeIgniter 3
        if (file_exists("{$path}/application/config/config.php") && file_exists("{$path}/system/core/CodeIgniter.php")) {
            return [
                'type'         => 'codeigniter3',
                'name'         => 'CodeIgniter 3',
                'category'     => 'php_framework',
                'version'      => '3.x',
                'public_dir'   => '',
                'env_standard' => 'php_arrays',
                'icon'         => 'fas fa-fire text-danger'
            ];
        }

        // 5. Symfony
        if (file_exists("{$path}/bin/console") && file_exists("{$path}/config/bundles.php")) {
            return [
                'type'         => 'symfony',
                'name'         => 'Symfony Framework',
                'category'     => 'php_framework',
                'version'      => $composerJson['require']['symfony/framework-bundle'] ?? 'unknown',
                'public_dir'   => 'public',
                'env_standard' => 'dotenv',
                'icon'         => 'fab fa-symfony'
            ];
        }

        // 6. Node.js / Express / NestJS / Next.js
        if ($packageJson !== null) {
            $deps = array_merge($packageJson['dependencies'] ?? [], $packageJson['devDependencies'] ?? []);
            if (isset($deps['next'])) {
                return [
                    'type'         => 'nextjs',
                    'name'         => 'Next.js React Framework',
                    'category'     => 'node_webapp',
                    'version'      => $deps['next'],
                    'public_dir'   => 'public',
                    'env_standard' => 'dotenv',
                    'icon'         => 'fab fa-react text-info'
                ];
            }
            if (isset($deps['express']) || isset($deps['@nestjs/core']) || isset($packageJson['main'])) {
                return [
                    'type'         => 'nodejs',
                    'name'         => 'Node.js Application',
                    'category'     => 'node_app',
                    'version'      => $packageJson['version'] ?? '1.0.0',
                    'public_dir'   => is_dir("{$path}/public") ? 'public' : '',
                    'env_standard' => 'dotenv',
                    'icon'         => 'fab fa-node-js text-success'
                ];
            }
        }

        // 7. Custom PHP MVC (TaskManager, Cake, vb. kurumsal mimariler)
        if (is_dir("{$path}/Controller") && is_dir("{$path}/Model") && is_dir("{$path}/View")) {
            return [
                'type'         => 'custom_php_mvc',
                'name'         => 'Custom Enterprise PHP MVC',
                'category'     => 'custom_mvc',
                'version'      => 'Custom',
                'public_dir'   => is_dir("{$path}/Public") ? 'Public' : (is_dir("{$path}/public") ? 'public' : ''),
                'env_standard' => 'config_directory',
                'icon'         => 'fab fa-php text-primary'
            ];
        }

        // 8. Standart PHP Script / Flat PHP
        $hasPhpFiles = count(glob("{$path}/*.php") ?: []) > 0;
        if ($hasPhpFiles) {
            return [
                'type'         => 'flat_php',
                'name'         => 'Standard PHP Application',
                'category'     => 'php_script',
                'version'      => 'Vanilla',
                'public_dir'   => '',
                'env_standard' => 'config_files',
                'icon'         => 'fab fa-php text-secondary'
            ];
        }

        // 9. Statik HTML / JS / Frontend (Örn: Askerlik projesi)
        if (file_exists("{$path}/index.html") || count(glob("{$path}/*.html") ?: []) > 0) {
            return [
                'type'         => 'static_html',
                'name'         => 'Static HTML5 / Client WebApp',
                'category'     => 'static_web',
                'version'      => 'HTML5',
                'public_dir'   => '',
                'env_standard' => 'static',
                'icon'         => 'fab fa-html5 text-danger'
            ];
        }

        // 10. Bilinmeyen / Generic
        return [
            'type'         => 'generic',
            'name'         => 'Generic Web Application',
            'category'     => 'other',
            'version'      => '1.0',
            'public_dir'   => '',
            'env_standard' => 'unknown',
            'icon'         => 'fas fa-folder text-muted'
        ];
    }

    /**
     * WordPress sürümünü wp-includes/version.php dosyasından okur.
     */
    private static function detectWordpressVersion(string $path): string
    {
        $versionFile = "{$path}/wp-includes/version.php";
        if (file_exists($versionFile)) {
            $content = @file_get_contents($versionFile);
            if (preg_match("/\\\$wp_version\\s*=\\s*['\"]([^'\"]+)['\"]/", $content, $matches)) {
                return $matches[1];
            }
        }
        return 'WordPress 6.x';
    }

    /**
     * Konfigürasyon dosyalarını tarar ve kritik değişkenleri ayrıştırır.
     */
    public static function scanConfigurations(string $path, string $frameworkType): array
    {
        $detectedFiles = [];
        $variables = [];
        $database = [
            'host'     => 'localhost',
            'database' => '',
            'username' => '',
            'password' => '',
            'port'     => 3306,
            'driver'   => 'mysql',
            'source'   => null
        ];

        // 1. .env / .env.example / .env.local Taraması
        $envFiles = ['.env', '.env.local', '.env.production', '.env.example', 'env'];
        foreach ($envFiles as $envFile) {
            $fullEnvPath = "{$path}/{$envFile}";
            if (file_exists($fullEnvPath) && is_file($fullEnvPath)) {
                $detectedFiles[] = [
                    'file' => $envFile,
                    'type' => 'dotenv',
                    'size' => filesize($fullEnvPath)
                ];
                $parsedEnv = self::parseDotEnv($fullEnvPath);
                foreach ($parsedEnv as $k => $v) {
                    if (!isset($variables[$k])) {
                        $variables[$k] = [
                            'key'         => $k,
                            'value'       => $v,
                            'source_file' => $envFile,
                            'is_critical' => self::isCriticalLiveVar($k),
                            'category'    => self::categorizeVar($k)
                        ];
                    }
                }

                // DB Değerlerini Çıkar
                if (empty($database['database'])) {
                    if (isset($parsedEnv['DB_DATABASE'])) $database['database'] = $parsedEnv['DB_DATABASE'];
                    if (isset($parsedEnv['DB_HOST']))     $database['host']     = $parsedEnv['DB_HOST'];
                    if (isset($parsedEnv['DB_USERNAME'])) $database['username'] = $parsedEnv['DB_USERNAME'];
                    if (isset($parsedEnv['DB_PASSWORD'])) $database['password'] = $parsedEnv['DB_PASSWORD'];
                    if (isset($parsedEnv['DB_PORT']))     $database['port']     = (int)$parsedEnv['DB_PORT'];
                    if (isset($parsedEnv['DB_CONNECTION'])) $database['driver'] = $parsedEnv['DB_CONNECTION'];
                    if (!empty($database['database']))    $database['source']   = $envFile;
                }
            }
        }

        // 2. Custom PHP MVC config klasörü taraması (config/database.php, config/app.php, vb.)
        $phpConfigFiles = [
            'config/database.php',
            'config/app.php',
            'Config/database.php',
            'Config/app.php',
            'System/Database.php',
            'System/System.php'
        ];
        foreach ($phpConfigFiles as $phpConfig) {
            $fullPath = "{$path}/{$phpConfig}";
            if (file_exists($fullPath) && is_file($fullPath)) {
                $detectedFiles[] = [
                    'file' => $phpConfig,
                    'type' => 'php_config',
                    'size' => filesize($fullPath)
                ];
                $extracted = self::extractPhpConfigVariables($fullPath);
                foreach ($extracted['variables'] as $k => $v) {
                    if (!isset($variables[$k])) {
                        $variables[$k] = [
                            'key'         => $k,
                            'value'       => $v,
                            'source_file' => $phpConfig,
                            'is_critical' => self::isCriticalLiveVar($k),
                            'category'    => self::categorizeVar($k)
                        ];
                    }
                }
                if (empty($database['database']) && !empty($extracted['database']['database'])) {
                    $database = array_merge($database, $extracted['database']);
                    $database['source'] = $phpConfig;
                }
            }
        }

        // 3. WordPress wp-config.php Taraması
        $wpConfigFiles = ['wp-config.php', 'wp-config-sample.php'];
        foreach ($wpConfigFiles as $wpConfig) {
            $fullWpPath = "{$path}/{$wpConfig}";
            if (file_exists($fullWpPath) && is_file($fullWpPath)) {
                $detectedFiles[] = [
                    'file' => $wpConfig,
                    'type' => 'wp_config',
                    'size' => filesize($fullWpPath)
                ];
                $wpVars = self::extractWordpressConstants($fullWpPath);
                foreach ($wpVars['constants'] as $k => $v) {
                    if (!isset($variables[$k])) {
                        $variables[$k] = [
                            'key'         => $k,
                            'value'       => $v,
                            'source_file' => $wpConfig,
                            'is_critical' => self::isCriticalLiveVar($k),
                            'category'    => self::categorizeVar($k)
                        ];
                    }
                }
                if (empty($database['database']) && !empty($wpVars['database']['database'])) {
                    $database = array_merge($database, $wpVars['database']);
                    $database['source'] = $wpConfig;
                }
            }
        }

        // 4. CodeIgniter 3 application/config/config.php & database.php
        $ci3Files = ['application/config/config.php', 'application/config/database.php'];
        foreach ($ci3Files as $ci3File) {
            $fullCiPath = "{$path}/{$ci3File}";
            if (file_exists($fullCiPath) && is_file($fullCiPath)) {
                $detectedFiles[] = [
                    'file' => $ci3File,
                    'type' => 'ci3_config',
                    'size' => filesize($fullCiPath)
                ];
                $ciVars = self::extractCi3Variables($fullCiPath);
                foreach ($ciVars['variables'] as $k => $v) {
                    if (!isset($variables[$k])) {
                        $variables[$k] = [
                            'key'         => $k,
                            'value'       => $v,
                            'source_file' => $ci3File,
                            'is_critical' => self::isCriticalLiveVar($k),
                            'category'    => self::categorizeVar($k)
                        ];
                    }
                }
                if (empty($database['database']) && !empty($ciVars['database']['database'])) {
                    $database = array_merge($database, $ciVars['database']);
                    $database['source'] = $ci3File;
                }
            }
        }

        return [
            'detected_files' => $detectedFiles,
            'variables'      => $variables,
            'database'       => $database
        ];
    }

    /**
     * .env dosyasını güvenli bir şekilde ayrıştırır.
     */
    public static function parseDotEnv(string $filePath): array
    {
        $vars = [];
        $lines = @file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) return $vars;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                // Tırnakları temizle
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }
                $vars[$key] = $value;
            }
        }
        return $vars;
    }

    /**
     * PHP yapılandırma dosyalarından regex ile ayarları çeker (dosyayı include etmeden statik analiz yapar).
     */
    public static function extractPhpConfigVariables(string $filePath): array
    {
        $content = @file_get_contents($filePath);
        $variables = [];
        $db = [];

        if (!$content) return ['variables' => $variables, 'database' => $db];

        // 1. Veritabanı array eşleşmeleri: 'database' => 'dbname'
        $dbFields = [
            'database' => 'database',
            'dbname'   => 'database',
            'host'     => 'host',
            'user'     => 'username',
            'username' => 'username',
            'pass'     => 'password',
            'password' => 'password',
            'port'     => 'port'
        ];

        foreach ($dbFields as $matchKey => $targetKey) {
            if (preg_match("/['\"]" . preg_quote($matchKey, '/') . "['\"]\s*=>\s*['\"]([^'\"]*)['\"]/i", $content, $m)) {
                $db[$targetKey] = $m[1];
                $variables["DB_" . strtoupper($targetKey)] = $m[1];
            }
        }

        // 2. URL ve Genel Ayar Eşleşmeleri
        $urlPatterns = [
            'APP_URL'      => "/['\"](?:url|siteurl|app_url|base_url)['\"]\s*=>\s*['\"]([^'\"]+)['\"]/i",
            'APP_ENV'      => "/['\"](?:env|environment|app_env)['\"]\s*=>\s*['\"]([^'\"]+)['\"]/i",
            'APP_DEBUG'    => "/['\"](?:debug|app_debug)['\"]\s*=>\s*(true|false|1|0)/i",
            'APP_KEY'      => "/['\"](?:key|app_key|secret)['\"]\s*=>\s*['\"]([^'\"]+)['\"]/i",
            'MAIL_HOST'    => "/['\"](?:mail_host|smtp_host)['\"]\s*=>\s*['\"]([^'\"]+)['\"]/i",
            'MAIL_PORT'    => "/['\"](?:mail_port|smtp_port)['\"]\s*=>\s*([0-9]+)/i",
            'MAIL_USER'    => "/['\"](?:mail_user|smtp_user|mail_username)['\"]\s*=>\s*['\"]([^'\"]+)['\"]/i",
            'MAIL_PASS'    => "/['\"](?:mail_pass|smtp_pass|mail_password)['\"]\s*=>\s*['\"]([^'\"]+)['\"]/i",
        ];

        foreach ($urlPatterns as $varKey => $regex) {
            if (preg_match($regex, $content, $m)) {
                $val = $m[1];
                $variables[$varKey] = $val;
            }
        }

        return ['variables' => $variables, 'database' => $db];
    }

    /**
     * wp-config.php içerisindeki define('KEY', 'VALUE') sabitlerini çıkarır.
     */
    public static function extractWordpressConstants(string $filePath): array
    {
        $content = @file_get_contents($filePath);
        $constants = [];
        $db = [];

        if (!$content) return ['constants' => $constants, 'database' => $db];

        if (preg_match_all("/define\s*\(\s*['\"]([^'\"]+)['\"]\s*,\s*['\"]?([^'\"]*?)['\"]?\s*\)\s*;/i", $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $item) {
                $k = $item[1];
                $v = $item[2];
                $constants[$k] = $v;

                if ($k === 'DB_NAME')     $db['database'] = $v;
                if ($k === 'DB_USER')     $db['username'] = $v;
                if ($k === 'DB_PASSWORD') $db['password'] = $v;
                if ($k === 'DB_HOST')     $db['host']     = $v;
            }
        }

        // WP_SITEURL, WP_HOME, WP_DEBUG
        if (isset($constants['WP_SITEURL'])) $constants['SITE_URL'] = $constants['WP_SITEURL'];
        if (isset($constants['WP_HOME']))    $constants['HOME_URL'] = $constants['WP_HOME'];

        return ['constants' => $constants, 'database' => $db];
    }

    /**
     * CodeIgniter 3 config.php ve database.php dosyalarını statik okur.
     */
    public static function extractCi3Variables(string $filePath): array
    {
        $content = @file_get_contents($filePath);
        $vars = [];
        $db = [];

        if (!$content) return ['variables' => $vars, 'database' => $db];

        // $config['base_url'] = '...';
        if (preg_match("/\\\$config\s*\[\s*['\"]base_url['\"]\s*\]\s*=\s*['\"]([^'\"]*)['\"]/i", $content, $m)) {
            $vars['BASE_URL'] = $m[1];
        }

        // CI Database array: $db['default']['database'] = '...';
        $dbKeys = ['hostname' => 'host', 'username' => 'username', 'password' => 'password', 'database' => 'database'];
        foreach ($dbKeys as $ciKey => $targetKey) {
            if (preg_match("/\\\$db\s*\[\s*['\"][^'\"]+['\"]\s*\]\s*\[\s*['\"]" . preg_quote($ciKey, '/') . "['\"]\s*\]\s*=\s*['\"]([^'\"]*)['\"]/i", $content, $m)) {
                $db[$targetKey] = $m[1];
                $vars["DB_" . strtoupper($targetKey)] = $m[1];
            }
        }

        return ['variables' => $vars, 'database' => $db];
    }

    /**
     * Bir değişkenin canlı dağıtımda zorunlu değişmesi gerekip gerekmediğini denetler.
     */
    public static function isCriticalLiveVar(string $key): bool
    {
        $criticalKeywords = [
            'APP_URL', 'SITE_URL', 'BASE_URL', 'WP_SITEURL', 'WP_HOME', 'ASSET_URL',
            'APP_ENV', 'ENVIRONMENT', 'NODE_ENV', 'CI_ENVIRONMENT',
            'APP_DEBUG', 'DEBUG', 'WP_DEBUG', 'DISPLAY_ERRORS',
            'DB_HOST', 'DB_DATABASE', 'DB_NAME', 'DB_USERNAME', 'DB_USER', 'DB_PASSWORD', 'DB_PASS', 'DB_PORT',
            'SESSION_DOMAIN', 'SANCTUM_STATEFUL_DOMAINS', 'CORS_ALLOWED_ORIGINS',
            'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_ENCRYPTION', 'MAIL_FROM_ADDRESS',
            'REDIS_HOST', 'REDIS_PASSWORD', 'REDIS_PORT'
        ];

        $upper = strtoupper($key);
        foreach ($criticalKeywords as $crit) {
            if ($upper === $crit || str_ends_with($upper, '_' . $crit) || str_starts_with($upper, $crit . '_')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Değişkeni kategorize eder.
     */
    public static function categorizeVar(string $key): string
    {
        $upper = strtoupper($key);
        if (str_contains($upper, 'URL') || str_contains($upper, 'HOST') || str_contains($upper, 'DOMAIN') || str_contains($upper, 'ORIGIN')) {
            return 'url_network';
        }
        if (str_contains($upper, 'DB_') || str_contains($upper, 'DATABASE')) {
            return 'database';
        }
        if (str_contains($upper, 'ENV') || str_contains($upper, 'DEBUG')) {
            return 'environment_state';
        }
        if (str_contains($upper, 'MAIL') || str_contains($upper, 'SMTP')) {
            return 'mail_service';
        }
        if (str_contains($upper, 'KEY') || str_contains($upper, 'SECRET') || str_contains($upper, 'TOKEN') || str_contains($upper, 'AUTH')) {
            return 'security_secret';
        }
        return 'general_setting';
    }

    /**
     * Canlı Sunucuya Özel İzolasyonlu Hedef Değer Şablonu (Live Env Payload) Üretir.
     * 
     * Yerel dosyalara ASLA yazmaz; sunucuya gönderilecek live payload JSON'unu hazırlar.
     */
    public static function buildLiveEnvPayload(array $framework, array $envAnalysis, array $overrides = []): array
    {
        $detectedVars = $envAnalysis['variables'];
        $liveDomain   = $overrides['live_domain'] ?? ($overrides['domain'] ?? 'https://example.com');
        $liveDomain   = rtrim($liveDomain, '/');
        if (!str_starts_with($liveDomain, 'http://') && !str_starts_with($liveDomain, 'https://')) {
            $liveDomain = 'https://' . $liveDomain;
        }

        $liveDbHost = $overrides['db_host'] ?? 'localhost';
        $liveDbName = $overrides['db_name'] ?? ($envAnalysis['database']['database'] ? $envAnalysis['database']['database'] . '_live' : 'cpanel_live_db');
        $liveDbUser = $overrides['db_user'] ?? ($envAnalysis['database']['username'] ? $envAnalysis['database']['username'] . '_live' : 'cpanel_live_user');
        $liveDbPass = $overrides['db_pass'] ?? ($overrides['db_password'] ?? 'LiveSecurePassword2026!');

        $targetReplacements = [];

        // 1. URL ve Domain Değişimleri
        foreach ($detectedVars as $k => $info) {
            $currentVal = $info['value'];
            $upper = strtoupper($k);

            // URL Değişimleri
            if ($upper === 'APP_URL' || $upper === 'SITE_URL' || $upper === 'BASE_URL' || $upper === 'WP_SITEURL' || $upper === 'WP_HOME') {
                $targetReplacements[$k] = [
                    'original'    => $currentVal,
                    'target_live' => $liveDomain,
                    'category'    => 'url_network',
                    'reason'      => 'Localhost/Wamp URL replaced with live production domain'
                ];
            }

            // Ortam ve Debug Güvenliği
            if ($upper === 'APP_ENV' || $upper === 'ENVIRONMENT' || $upper === 'NODE_ENV' || $upper === 'CI_ENVIRONMENT') {
                $targetReplacements[$k] = [
                    'original'    => $currentVal,
                    'target_live' => 'production',
                    'category'    => 'environment_state',
                    'reason'      => 'Switched to production environment for performance and security'
                ];
            }
            if ($upper === 'APP_DEBUG' || $upper === 'DEBUG' || $upper === 'WP_DEBUG' || $upper === 'DISPLAY_ERRORS') {
                $targetReplacements[$k] = [
                    'original'    => $currentVal,
                    'target_live' => 'false',
                    'category'    => 'environment_state',
                    'reason'      => 'Disabled error display on live server to prevent data leaks'
                ];
            }

            // Veritabanı Değerleri
            if ($upper === 'DB_HOST') {
                $targetReplacements[$k] = [
                    'original'    => $currentVal,
                    'target_live' => $liveDbHost,
                    'category'    => 'database',
                    'reason'      => 'Production database host'
                ];
            }
            if ($upper === 'DB_DATABASE' || $upper === 'DB_NAME') {
                $targetReplacements[$k] = [
                    'original'    => $currentVal,
                    'target_live' => $liveDbName,
                    'category'    => 'database',
                    'reason'      => 'Live production database name'
                ];
            }
            if ($upper === 'DB_USERNAME' || $upper === 'DB_USER') {
                $targetReplacements[$k] = [
                    'original'    => $currentVal,
                    'target_live' => $liveDbUser,
                    'category'    => 'database',
                    'reason'      => 'Live database user'
                ];
            }
            if ($upper === 'DB_PASSWORD' || $upper === 'DB_PASS') {
                $targetReplacements[$k] = [
                    'original'    => '***',
                    'target_live' => $liveDbPass,
                    'category'    => 'database',
                    'reason'      => 'Live database secure password'
                ];
            }
        }

        // Eğer kritik değişkenler kaynak dosyada yoksa bile Live Payload'a varsayılan olarak ekle
        if (!isset($targetReplacements['APP_URL']) && !isset($targetReplacements['SITE_URL']) && !isset($targetReplacements['BASE_URL'])) {
            $targetReplacements['APP_URL'] = [
                'original'    => 'http://localhost',
                'target_live' => $liveDomain,
                'category'    => 'url_network',
                'reason'      => 'Primary production domain entry point'
            ];
        }

        if (!isset($targetReplacements['APP_ENV'])) {
            $targetReplacements['APP_ENV'] = [
                'original'    => 'local',
                'target_live' => 'production',
                'category'    => 'environment_state',
                'reason'      => 'Enforce production mode'
            ];
        }

        if (!isset($targetReplacements['APP_DEBUG'])) {
            $targetReplacements['APP_DEBUG'] = [
                'original'    => 'true',
                'target_live' => 'false',
                'category'    => 'environment_state',
                'reason'      => 'Enforce debug off'
            ];
        }

        return [
            'target_domain'     => $liveDomain,
            'target_database'   => [
                'host'     => $liveDbHost,
                'database' => $liveDbName,
                'username' => $liveDbUser,
                'password' => $liveDbPass,
                'port'     => 3306
            ],
            'replacements'      => $targetReplacements,
            'deployment_hints'  => [
                'recommended_public_dir' => $framework['public_dir'],
                'force_https'            => true,
                'generate_ht_access'     => ($framework['category'] === 'php_framework' || $framework['category'] === 'custom_mvc')
            ]
        ];
    }
}
