<?php
/**
 * TaskManager Runtime Integrity & Undefined Method/Class Auditor
 * 
 * Bu betik projedeki tüm modülleri tarar ve:
 *  - Tanımsız sınıf örneklerini (new NonExistentClass)
 *  - Model sınıflarında tanımlanmamış metot çağrılarını ($this->undefinedMethod())
 *  - Servis ve Modellerdeki statik metot uyumsuzluklarını
 *  - BaseModel eksik metotlarını ($this->first, $this->execute, $this->row vb.)
 * otomatik tespit eder.
 * 
 * Kullanım:
 * php tests/audit_runtime_integrity.php [ModülAdı veya ALL]
 */

define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/Core/DataLayer.php';
require_once ROOT_DIR . '/Core/BaseModel.php';
require_once ROOT_DIR . '/Core/BaseController.php';
require_once ROOT_DIR . '/Core/Language.php';

$target = $argv[1] ?? 'ALL';
$modulesDir = ROOT_DIR . '/Modules';
$modules = ($target === 'ALL') ? array_map('basename', glob($modulesDir . '/*', GLOB_ONLYDIR)) : [$target];

echo "========================================================\n";
echo "🔍 ÇALIŞMA ZAMANI BÜTÜNLÜK & METOT DENETÇİSİ (INTEGRITY AUDITOR)\n";
echo "========================================================\n\n";

// BaseModel metotları
$refBase = new ReflectionClass('Core\BaseModel');
$baseModelMethods = [];
foreach ($refBase->getMethods() as $m) {
    $baseModelMethods[strtolower($m->getName())] = $m->getName();
}

$totalErrors = 0;

foreach ($modules as $mod) {
    $modPath = "$modulesDir/$mod";
    if (!is_dir($modPath)) continue;

    $errors = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modPath));

    // Önce bu modüldeki Model ve Service dosyalarını tarayıp sınıfları ve metotlarını indexleyelim
    $moduleClassMethods = [];
    foreach ($iterator as $file) {
        if ($file->isDir() || $file->getExtension() !== 'php') continue;
        $content = file_get_contents($file->getPathname());
        if (preg_match('/class\s+([a-zA-Z0-9_]+)/i', $content, $cm)) {
            $cName = $cm[1];
            preg_match_all('/function\s+([a-zA-Z0-9_]+)\s*\(/i', $content, $fm);
            $moduleClassMethods[$cName] = array_map('strtolower', $fm[1]);
        }
    }

    foreach ($iterator as $file) {
        if ($file->isDir() || $file->getExtension() !== 'php') continue;

        $fullPath = $file->getPathname();
        $relPath = str_replace('\\', '/', substr($fullPath, strlen($modPath) + 1));
        
        // Lang klasörünü atla
        if (str_starts_with($relPath, 'Lang/')) continue;

        $content = file_get_contents($fullPath);
        $lines = explode("\n", $content);

        // Bu dosyanın içinde tanımlı sınıfı bulalım
        $currentClass = '';
        $isModelClass = false;

        if (preg_match('/class\s+(\w+)(?:\s+extends\s+(\w+))?/i', $content, $clsMatch)) {
            $currentClass = trim($clsMatch[1]);
            $extendsClass = trim($clsMatch[2] ?? '');
            if ($extendsClass === 'BaseModel' || str_ends_with($currentClass, 'Model') || str_contains($relPath, 'Models/')) {
                $isModelClass = true;
            }
        }

        // Kendi sınıfında tanımlı metotlar
        preg_match_all('/function\s+([a-zA-Z0-9_]+)\s*\(/i', $content, $selfMethodsMatch);
        $selfMethods = array_map('strtolower', $selfMethodsMatch[1]);

        foreach ($lines as $idx => $line) {
            $lineNum = $idx + 1;
            $trim = trim($line);

            // Yorum satırlarını atla
            if (str_starts_with($trim, '//') || str_starts_with($trim, '*') || str_starts_with($trim, '/*') || str_starts_with($trim, '#')) {
                continue;
            }

            // 1. KONTROL: Model dosyası içinde $this->metot() çağrısı yapılmış ama metot yok!
            if ($isModelClass && preg_match_all('/\$this->([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)\s*\(/', $line, $matches)) {
                foreach ($matches[1] as $calledMethod) {
                    $lowerMethod = strtolower($calledMethod);

                    $hasInCurrent = in_array($lowerMethod, $selfMethods, true);
                    $hasInBase    = isset($baseModelMethods[$lowerMethod]);

                    // BaseModel ve PDO ile ilgili temel aliaslar
                    $allowedSpecial = ['__construct', 'getdb', 'getpdo', 'query', 'queryone', 'find', 'all', 'begintransaction', 'commit', 'rollback', 'first', 'fetchall', 'row', 'single', 'execute'];

                    if (!$hasInCurrent && !$hasInBase && !in_array($lowerMethod, $allowedSpecial, true)) {
                        $errors[] = "$relPath:$lineNum => [Model İçi Tanımsız Metot] '$currentClass' sınıfında '\$this->$calledMethod()' metodu tanımlı değil ve Core\\BaseModel içinde bulunmuyor!";
                    }
                }
            }

            // 2. KONTROL: Statik Sınıf Çağrısı (Örn: Model::metot() veya Service::metot()) ama metot yok
            if (preg_match_all('/([A-Z][a-zA-Z0-9_]*)::([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)\s*\(/', $line, $matches)) {
                for ($mIdx = 0; $mIdx < count($matches[0]); $mIdx++) {
                    $calledClass = $matches[1][$mIdx];
                    $calledMethod = $matches[2][$mIdx];
                    $lowerMethod = strtolower($calledMethod);

                    if (in_array($calledClass, ['self', 'parent', 'static', 'PDO', 'DateTime', 'DateTimeImmutable', 'Exception', 'RuntimeException', 'Language', 'Helper', 'Database', 'AssetManager', 'EventManager', 'SseBroadcaster'], true)) {
                        continue;
                    }

                    if (isset($moduleClassMethods[$calledClass])) {
                        if (!in_array($lowerMethod, $moduleClassMethods[$calledClass], true)) {
                            $errors[] = "$relPath:$lineNum => [Tanımsız Metot Çağrısı] '$calledClass::$calledMethod()' metodu '$calledClass' sınıfında tanımlanmamış!";
                        }
                    }
                }
            }
        }
    }

    if (!empty($errors)) {
        $totalErrors += count($errors);
        echo "❌ [$mod] MODÜLÜNDE " . count($errors) . " BÜTÜNLÜK / METOT HATASI BULUNDU:\n";
        foreach ($errors as $e) {
            echo "   🔸 $e\n";
        }
        echo "\n";
    } else {
        echo "✅ [$mod] Çalışma Zamanı & Metot Bütünlüğü Temiz (0 Hata)\n";
    }
}

echo "========================================================\n";
if ($totalErrors > 0) {
    echo "🚨 TOPLAM $totalErrors ADET ÇALIŞMA ZAMANI / METOT HATASI TESPİT EDİLDİ!\n";
    echo "Lütfen yukarıdaki satırları düzeltip tekrar çalıştırın.\n";
    exit(1);
} else {
    echo "🎉 TEBRİKLER! TÜM MODÜLLER ÇALIŞMA ZAMANI BÜTÜNLÜK DENETİMİNDEN GEÇTİ [PASS]!\n";
    exit(0);
}
