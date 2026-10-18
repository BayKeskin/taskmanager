<?php

if (!class_exists('Language')) {
class Language
{
    private static array $loaded = [];
    private static ?string $currentLocale = null;
    private static ?string $fallbackLocale = null;
    private static ?array $config = null;
    private static ?string $systemDefaultLanguage = null;

    /**
     * Dil motorunu başlatır
     */
    public static function init(?string $locale = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        self::loadConfig();

        // 1. URL veya POST üzerinden parametre ile gelmişse (?set_lang=de veya ?lang=de)
        $urlLang = $_GET['set_lang'] ?? $_GET['lang'] ?? $_POST['set_lang'] ?? $_POST['lang'] ?? null;
        if (!empty($urlLang) && is_string($urlLang)) {
            $sanitized = preg_replace('/[^a-zA-Z_-]/', '', $urlLang);
            if (self::isLocaleAvailable($sanitized)) {
                self::$currentLocale = $sanitized;
                $_SESSION['admin_lang'] = $sanitized;
                $_SESSION['lang'] = $sanitized;
                return;
            }
        }

        // 2. Kullanıcı oturum açmışsa profilindeki dili önceliklendir
        if (!empty($_SESSION['user']->language) && self::isLocaleAvailable($_SESSION['user']->language)) {
            self::$currentLocale = $_SESSION['user']->language;
            $_SESSION['admin_lang'] = $_SESSION['user']->language;
            $_SESSION['lang'] = $_SESSION['user']->language;
            return;
        }

        // 3. Oturumda kayıtlı kullanıcı tercihi varsa
        if (!empty($_SESSION['lang']) && self::isLocaleAvailable($_SESSION['lang'])) {
            self::$currentLocale = $_SESSION['lang'];
            $_SESSION['admin_lang'] = $_SESSION['lang'];
            return;
        }

        if (!empty($_SESSION['admin_lang']) && self::isLocaleAvailable($_SESSION['admin_lang'])) {
            self::$currentLocale = $_SESSION['admin_lang'];
            return;
        }

        // 4. Kod içinden manuel belirtilmişse
        if ($locale !== null && self::isLocaleAvailable($locale)) {
            self::$currentLocale = $locale;
            $_SESSION['admin_lang'] = $locale;
            $_SESSION['lang'] = $locale;
            return;
        }

        // 5. Sistem Varsayılan Dili (Veritabanı: settings.default_language)
        $defaultLang = self::getSystemDefaultLanguage();
        self::$currentLocale = $defaultLang;
        $_SESSION['admin_lang'] = $defaultLang;
        $_SESSION['lang'] = $defaultLang;
    }

    /**
     * Veritabanından sistem varsayılan dilini okur
     */
    public static function getSystemDefaultLanguage(): string
    {
        if (self::$systemDefaultLanguage !== null) {
            return self::$systemDefaultLanguage;
        }

        global $db;
        try {
            if (!$db || empty($db->connect)) {
                require_once __DIR__ . '/../Model/DataLayer.php';
                $db = new Database();
            }
            $s = $db->row_query("SELECT default_language FROM settings WHERE id = 1 LIMIT 1");
            if ($s && !empty($s->default_language) && self::isLocaleAvailable($s->default_language)) {
                self::$systemDefaultLanguage = $s->default_language;
                return self::$systemDefaultLanguage;
            }
        } catch (\Throwable $e) {
            // Veritabanı sorgusu başarısız olursa config fallback kullanılır
        }

        self::loadConfig();
        self::$systemDefaultLanguage = self::$config['locale'] ?? 'tr';
        return self::$systemDefaultLanguage;
    }

    /**
     * Sistem varsayılan dil önbelleğini temizler veya günceller
     */
    public static function setSystemDefaultLanguageCache(?string $lang = null): void
    {
        self::$systemDefaultLanguage = $lang;
    }

    /**
     * Konfigürasyon dosyasını okur
     */
    private static function loadConfig(): void
    {
        if (self::$config === null) {
            $configFile = __DIR__ . '/../Core/Config/app.php';
            if (!file_exists($configFile)) {
                $configFile = __DIR__ . '/../config/app.php';
            }
            if (file_exists($configFile)) {
                self::$config = require $configFile;
            } else {
                self::$config = [
                    'locale' => 'tr',
                    'fallback_locale' => 'en',
                    'locales' => ['tr' => ['name' => 'Türkçe', 'code' => 'tr']]
                ];
            }
            self::$fallbackLocale = self::$config['fallback_locale'] ?? 'en';
        }
    }

    /**
     * Dilin geçerli ve desteklenen bir dil olup olmadığını denetler
     */
    public static function isLocaleAvailable(string $locale): bool
    {
        self::loadConfig();
        $locales = self::$config['locales'] ?? [];
        return array_key_exists($locale, $locales);
    }

    /**
     * Aktif dili döner
     */
    public static function current(): string
    {
        if (self::$currentLocale === null) {
            self::init();
        }
        return self::$currentLocale ?? 'tr';
    }

    /**
     * Geriye dönük uyumluluk: current() alias'ı
     */
    public static function getCurrentLang(): string
    {
        return self::current();
    }

    public static function getCurrentLanguage(): string
    {
        return self::current();
    }

    /**
     * Dili dinamik olarak değiştirir
     */
    public static function setLocale(string $locale): bool
    {
        if (self::isLocaleAvailable($locale)) {
            self::$currentLocale = $locale;
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $_SESSION['admin_lang'] = $locale;
            $_SESSION['admin_lang_user_chosen'] = true;
            return true;
        }
        return false;
    }

    /**
     * Desteklenen tüm dilleri döner
     */
    public static function locales(): array
    {
        self::loadConfig();
        return self::$config['locales'] ?? [];
    }

    /**
     * İlgili dil grubu dosyasını yükler
     */
    private static function loadGroup(string $group, string $locale): void
    {
        if ($group === 'admin') {
            $group = 'app';
        } elseif ($group === 'tasks') {
            $group = 'Board';
        }

        if (isset(self::$loaded[$locale][$group])) {
            return;
        }

        // Modüle özel dil dosyası mı? (Örn: LiveDeploy veya LiveDeploy::key)
        $baseDir = dirname(__DIR__);
        $moduleLangPath = $baseDir . "/Modules/{$group}/Lang/{$locale}.php";
        if (file_exists($moduleLangPath)) {
            self::$loaded[$locale][$group] = require $moduleLangPath;
            return;
        }

        $filePath = __DIR__ . "/../Core/Lang/{$locale}/{$group}.php";
        if (!file_exists($filePath)) {
            $filePath = __DIR__ . "/../languages/{$locale}/{$group}.php";
        }
        if (file_exists($filePath)) {
            self::$loaded[$locale][$group] = require $filePath;
        } else {
            self::$loaded[$locale][$group] = [];
        }
    }

    /**
     * Çeviri metnini getirir (Dot notation ve parametre desteği)
     * Örnek: Language::get('app.save')
     * Örnek: Language::get('messages.required_field', ['field' => 'Başlık'])
     */
    public static function get(string $key, $replace = [], $default = null): string|array
    {
        // 2. parametre olarak string (varsayılan metin) verilmişse esnek davran
        if (is_string($replace)) {
            $default = $replace;
            $replace = [];
        } elseif (!is_array($replace)) {
            $replace = [];
        }

        if (self::$currentLocale === null) {
            self::init();
        }

        $locale = self::$currentLocale;
        $fallback = self::$fallbackLocale ?? 'en';

        if (str_contains($key, '::')) {
            [$group, $remain] = explode('::', $key, 2);
            $parts = explode('.', $remain);
        } else {
            $parts = explode('.', $key);
            $group = array_shift($parts);
            if ($group === 'admin') {
                $group = 'app';
            } elseif ($group === 'tasks') {
                $group = 'Board';
            }
        }

        // 1. Aktif dilden dene
        self::loadGroup($group, $locale);
        $value = self::extractValue(self::$loaded[$locale][$group] ?? [], $parts);

        // 2. Bulunamadıysa fallback dilden dene
        if ($value === null && $locale !== $fallback) {
            self::loadGroup($group, $fallback);
            $value = self::extractValue(self::$loaded[$fallback][$group] ?? [], $parts);
        }

        // 3. Eğer app grubunda bulunamadıysa, ilgili modüllerin Lang dosyalarında ara
        if ($value === null && $group === 'app') {
            static $activeModules = null;
            if ($activeModules === null) {
                $activeModules = ['Board', 'Projects', 'Settings', 'Team', 'Docs', 'Chat', 'LiveDeploy', 'ExcelExport', 'AiArchitect', 'Mcp'];
            }
            $singlePart = end($parts);
            foreach ($activeModules as $mod) {
                self::loadGroup($mod, $locale);
                if (isset(self::$loaded[$locale][$mod][$singlePart])) {
                    $value = self::$loaded[$locale][$mod][$singlePart];
                    break;
                }
            }
            if ($value === null && $locale !== $fallback) {
                foreach ($activeModules as $mod) {
                    self::loadGroup($mod, $fallback);
                    if (isset(self::$loaded[$fallback][$mod][$singlePart])) {
                        $value = self::$loaded[$fallback][$mod][$singlePart];
                        break;
                    }
                }
            }
        }

        // 4. Hala bulunamadıysa default ya da key'i dön
        if ($value === null) {
            $value = $default ?? $key;
        }

        // 4. Parametre değişimlerini uygula (:field veya {field})
        if (!empty($replace) && is_string($value)) {
            foreach ($replace as $k => $v) {
                $value = str_replace([":{$k}", "{{$k}}"], (string)$v, $value);
            }
        }

        return is_array($value) ? $value : (string)$value;
    }

    /**
     * İç içe dizi içinden değeri çeker
     */
    private static function extractValue(array $array, array $keys)
    {
        $current = $array;
        foreach ($keys as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }
        return $current;
    }

    /**
     * Çok dilli tarih formatlayıcı (TR, EN, DE)
     */
    public static function formatDateLocalized(string $mysql_datetime, ?string $locale = null): string
    {
        $timestamp = strtotime($mysql_datetime);
        if ($timestamp === false) {
            $cur = self::current();
            if ($cur === 'tr') return "Geçersiz Tarih";
            if ($cur === 'de') return "Ungültiges Datum";
            return "Invalid Date";
        }

        $locale = $locale ?? self::current();

        $day = date('j', $timestamp);
        $month = (int)date('n', $timestamp);
        $year = date('Y', $timestamp);

        $monthNames = [
            'tr' => [1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'],
            'de' => [1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'],
            'en' => [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'],
        ];

        $mName = $monthNames[$locale][$month] ?? $monthNames['en'][$month] ?? date('F', $timestamp);

        if ($locale === 'tr') {
            return "{$day} {$mName} {$year}";
        } elseif ($locale === 'de') {
            return "{$day}. {$mName} {$year}";
        } else {
            return "{$mName} {$day}, {$year}";
        }
    }
}
}

/**
 * Global Dil Yardımcısı Fonksiyonu
 */
if (!function_exists('__')) {
    function __(string $key, $replace = [], $default = null): string|array
    {
        return Language::get($key, $replace, $default);
    }
}

if (!function_exists('format_date_localized')) {
    function format_date_localized(string $mysql_datetime, ?string $locale = null): string
    {
        return Language::formatDateLocalized($mysql_datetime, $locale);
    }
}
