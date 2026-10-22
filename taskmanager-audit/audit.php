<?php
/**
 * TaskManager Resmi Mimari Denetim Motoru
 * Konum: taskmanager-audit/audit.php
 * Kullanım: php taskmanager-audit/audit.php [ModülAdı veya ALL]
 */

$target = $argv[1] ?? 'ALL';
$modulesDir = dirname(__DIR__) . '/Modules';

if (!is_dir($modulesDir)) {
    die("❌ Modules dizini bulunamadı: $modulesDir\n");
}

$modules = ($target === 'ALL')
    ? array_map('basename', glob($modulesDir . '/*', GLOB_ONLYDIR))
    : [$target];

$totalErrors = 0;

foreach ($modules as $moduleName) {
    $modulePath = $modulesDir . '/' . $moduleName;
    if (!is_dir($modulePath)) {
        echo "❌ Modül bulunamadı: $modulePath\n";
        exit(1);
    }

    $errors = [];

    // Modülün TR, EN, DE sözlüklerini yükle (Eksik anahtar kontrolü için)
    $dicts = [];
    foreach (['tr', 'en', 'de'] as $loc) {
        $lf = "$modulePath/Lang/$loc.php";
        $dicts[$loc] = file_exists($lf) ? (array) include $lf : [];
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modulePath));
    foreach ($iterator as $file) {
        if ($file->isDir()) continue;
        $ext = $file->getExtension();
        if (!in_array($ext, ['php', 'js'])) continue;

        $filePath = $file->getPathname();
        $relPath  = str_replace('\\', '/', substr($filePath, strlen($modulePath) + 1));
        if (str_starts_with($relPath, 'Bin/')) continue;
        $content  = file_get_contents($filePath);
        $lines    = explode("\n", $content);

        foreach ($lines as $i => $line) {
            $lineNo  = $i + 1;
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '/*')) {
                continue;
            }

            // KURAL 1: View dosyaları Modele veya DB'ye doğrudan erişemez!
            if (str_starts_with($relPath, 'Views/') && $ext === 'php') {
                if (preg_match('/(new\s+\w*Model|\w*Model::|\$this->db|\$db\b|\bPDO\b|Database::|DataLayer|SELECT\s+.*FROM|INSERT\s+INTO|UPDATE\s+.*SET|DELETE\s+FROM)/i', $line)) {
                    $errors[] = "[KURAL 1 - View->Model/DB Yasağı] $relPath:$lineNo => $trimmed";
                }
            }

            // KURAL 2: Controller, Service ve init.php içinde SQL veya doğrudan DB sorgusu olamaz!
            if ((str_starts_with($relPath, 'Controllers/') || str_starts_with($relPath, 'Services/') || $relPath === 'init.php') && $ext === 'php') {
                if (preg_match('/(\$this->db->|\$db->|global\s+\$db|Database::|exec_query|row_query|->query\(|->prepare\(|->insert\(|->delete\(|SELECT\s+.*FROM|INSERT\s+INTO|UPDATE\s+.*SET|DELETE\s+FROM)/i', $line)) {
                    $errors[] = "[KURAL 2 - Controller/Service SQL Yasağı] $relPath:$lineNo => $trimmed";
                }
            }

            // KURAL 3: Sabit (Hardcoded) Türkçe metin veya çevirisiz mesaj olamaz! (PHP + JS dahil, Lang hariç)
            if (!str_starts_with($relPath, 'Lang/')) {
                if (
                    preg_match('/[\'"`][^\'"`]*[ğüşıöçĞÜŞİÖÇ][^\'"`]*[\'"`]/u', $line) ||
                    preg_match('/respond\s*\(\s*(true|false)\s*,\s*[\'"][^\'"]+[\'"]/i', $line)
                ) {
                    $errors[] = "[KURAL 3 - Sabit Dil Metni (PHP/JS)] $relPath:$lineNo => $trimmed";
                }

                // Çağrılan __('Modül::key') sözlükte gerçekten var mı?
                if (preg_match_all('/(?:__|_t|lang)\s*\(\s*[\'"]' . preg_quote($moduleName, '/') . '::([a-zA-Z0-9_.-]+)[\'"]\s*[\),]/', $line, $mKeys)) {
                    foreach ($mKeys[1] as $k) {
                        foreach (['tr', 'en', 'de'] as $loc) {
                            if (!array_key_exists($k, $dicts[$loc])) {
                                $errors[] = "[KURAL 3 - Eksik Çeviri Anahtarı] $relPath:$lineNo => '$moduleName::$k' Lang/$loc.php içinde YOK!";
                            }
                        }
                    }
                }
            }

            // KURAL 4: View veya PHP dosyalarında <style>, <script> bloğu veya inline style="" / onclick="" olamaz!
            if ($ext === 'php') {
                if (preg_match('/(<style\b|<script\b(?!\s+src=)|style\s*=\s*[\'"]|on(click|change|submit|keyup|load)\s*=\s*[\'"])/i', $line)) {
                    $errors[] = "[KURAL 4 - Inline CSS/JS Yasağı] $relPath:$lineNo => $trimmed";
                }
            }

            // KURAL 5: Sabit '/taskmanager/' URL veya dosya yolu kullanımı YASAKTIR!
            if (preg_match('/[\'"`][^\'"`]*\/taskmanager(\/|[\'"`])/i', $line)) {$errors[] = "[KURAL 5 - Sabit '/taskmanager/' Yolu Yasağı] $relPath:$lineNo => Dinamik BASE_URL / rota kullanılmalı! ($trimmed)";
            }
        }
    }

    echo "========================================\n";
    echo "🔍 MODÜL DENETİMİ: $moduleName\n";
    echo "========================================\n";

    if (empty($errors)) {
        echo "✅ [PASS] $moduleName modülü 5 kuralın tamamına %100 uyuyor (0 İhlal).\n";
    } else {
        $totalErrors += count($errors);
        echo "❌ [FAIL] $moduleName içinde " . count($errors) . " ihlal bulundu:\n";
        foreach ($errors as$err) {
            echo "  - $err\n";
        }
    }
}

exit($totalErrors > 0 ? 1 : 0);