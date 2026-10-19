<?php
/**
 * Eksik Dil Anahtarı (Undefined Translation Key) Avcısı
 * Kullanım: php tests/audit_missing_lang.php [ModülAdı veya ALL]
 */

$target = $argv[1] ?? 'ALL';
$modulesDir = __DIR__ . '/../Modules';
$modules = ($target === 'ALL') ? array_map('basename', glob($modulesDir . '/*', GLOB_ONLYDIR)) : [$target];

$totalMissing = 0;

foreach ($modules as $mod) {
    $modPath = "$modulesDir/$mod";
    if (!is_dir($modPath))
        continue;

    // Modülün mevcut TR, EN, DE sözlüklerini yükle
    $langs = ['tr' => [], 'en' => [], 'de' => []];
    foreach (['tr', 'en', 'de'] as $locale) {
        $langFile = "$modPath/Lang/$locale.php";
        if (file_exists($langFile)) {
            $data = include $langFile;
            if (is_array($data))
                $langs[$locale] = $data;
        }
    }

    $usedKeys = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modPath));

    foreach ($iterator as $file) {
        if ($file->isDir() || !in_array($file->getExtension(), ['php', 'js']))
            continue;
        $relPath = str_replace('\\', '/', substr($file->getPathname(), strlen($modPath) + 1));
        if (str_starts_with($relPath, 'Lang/'))
            continue;

        $content = file_get_contents($file->getPathname());

        // __('ModuleName::key') veya $this->lang('ModuleName::key') veya __t('ModuleName::key') yakala
        if (preg_match_all('/(?:__|_t|lang)\s*\(\s*[\'"]([a-zA-Z0-9_]+)::([a-zA-Z0-9_.-]+)[\'"]\s*[\),]/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $calledMod = $m[1];
                $key = $m[2];
                $usedKeys["$calledMod::$key"] = ['mod' => $calledMod, 'key' => $key, 'file' => $relPath];
            }
        }
    }

    $missingInMod = [];
    foreach ($usedKeys as $fullKey => $info) {
        $targetMod = $info['mod'];
        $key = $info['key'];

        // Çağrılan modülün kendi Lang dosyalarını kontrol et
        foreach (['tr', 'en', 'de'] as $locale) {
            $targetLangFile = "$modulesDir/$targetMod/Lang/$locale.php";
            $dict = file_exists($targetLangFile) ? (array) include $targetLangFile : [];

            if (!array_key_exists($key, $dict) || trim((string) $dict[$key]) === '' || str_contains((string) $dict[$key], '::')) {
                $missingInMod[] = "Eksik Anahtar: [$targetMod::$key] -> Bulunamadı: Modules/$targetMod/Lang/$locale.php (Kullanan: $mod/{$info['file']})";
            }
        }
    }

    if (!empty($missingInMod)) {
        $totalMissing += count($missingInMod);
        echo "\n❌ [$mod] MODÜLÜNDE " . count($missingInMod) . " EKSİK ÇEVİRİ ANAHTARI BULUNDU:\n";
        foreach ($missingInMod as $err) {
            echo "   🔸 $err\n";
        }
    } else {
        echo "✅ [$mod] Tüm çeviri anahtarları TR, EN ve DE sözlüklerinde eksiksiz tanımlı!\n";
    }
}

echo "\n========================================\n";
if ($totalMissing > 0) {
    echo "🚨 TOPLAM $totalMissing ADET EKSİK DİL ANAHTARI BULUNDU! Derhal Lang/{tr,en,de}.php dosyalarına ekleyin.\n";
    exit(1);
} else {
    echo "🎉 [PASS] Projede hiçbir eksik dil anahtarı (Module::key) kalmadı!\n";
    exit(0);
}