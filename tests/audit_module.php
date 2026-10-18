<?php
// tests/audit_module.php
// Kullanım: php tests/audit_module.php AiArchitect

$moduleName = $argv[1] ?? 'AiArchitect';
$modulePath = __DIR__ . '/../Modules/' . $moduleName;

if (!is_dir($modulePath)) {
    die("❌ Modül bulunamadı: $modulePath\n");
}

$errors = [];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modulePath));
foreach ($iterator as $file) {
    if ($file->isDir())
        continue;
    $filePath = $file->getPathname();
    $relPath = str_replace($modulePath . DIRECTORY_SEPARATOR, '', $filePath);
    $content = file_get_contents($filePath);
    $lines = explode("\n", $content);

    // KURAL 1: View dosyaları Modele veya DB'ye doğrudan erişemez!
    if (str_starts_with($relPath, 'Views')) {
        foreach ($lines as $i => $line) {
            if (preg_match('/(new\s+\w*Model|\w*Model::|\$this->db|\bPDO\b|SELECT\s+.*FROM|INSERT\s+INTO|UPDATE\s+.*SET|DELETE\s+FROM)/i', $line)) {
                $errors[] = "[KURAL 1 İHLALİ - View->Model/DB] $relPath (Satır " . ($i + 1) . "): " . trim($line);
            }
        }
    }

    // KURAL 2: Controller içinde SQL veya doğrudan DB sorgusu olamaz!
    if (str_starts_with($relPath, 'Controllers') || str_starts_with($relPath, 'Services') || $relPath === 'init.php') {
        foreach ($lines as $i => $line) {
            if (preg_match('/(\$this->db->|exec_query|->query\(|->prepare\(|SELECT\s+.*FROM|INSERT\s+INTO|UPDATE\s+.*SET|DELETE\s+FROM)/i', $line)) {
                $errors[] = "[KURAL 2 İHLALİ - Controller SQL] $relPath (Satır " . ($i + 1) . "): " . trim($line);
            }
        }
    }

    // KURAL 3: Sabit (Hardcoded) Türkçe metin veya çevirisiz respond() mesajı olamaz! (Lang klasörü hariç)
    if (!str_starts_with($relPath, 'Lang') && str_ends_with($relPath, '.php')) {
        foreach ($lines as $i => $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '#'))
                continue;

            // Türkçe karakter içeren string veya doğrudan string basan respond() yakala
            if (
                preg_match('/[\'"][^\'"]*[ğüşıöçĞÜŞİÖÇ][^\'"]*[\'"]/u', $line) ||
                preg_match('/respond\s*\(\s*(true|false)\s*,\s*[\'"][^\'"]+[\'"]/i', $line)
            ) {
                $errors[] = "[KURAL 3 İHLALİ - Sabit Dil Metni] $relPath (Satır " . ($i + 1) . "): " . $trimmed;
            }
        }
    }

    // KURAL 4: View veya PHP dosyalarında <style>, <script> bloğu veya inline style="" / onclick="" olamaz!
    if (str_ends_with($relPath, '.php')) {
        foreach ($lines as $i => $line) {
            if (preg_match('/(<style\b|<script\b(?!\s+src=)|style\s*=\s*[\'"]|on(click|change|submit|keyup|load)\s*=\s*[\'"])/i', $line)) {
                $errors[] = "[KURAL 4 İHLALİ - Inline CSS/JS] $relPath (Satır " . ($i + 1) . "): " . trim($line);
            }
        }
    }
}

echo "========================================\n";
echo "🔍 MODÜL MİMARİ DENETİMİ: $moduleName\n";
echo "========================================\n";

if (empty($errors)) {
    echo "✅ [PASS] Harika! $moduleName modülü 4 mimari kuralın tamamına %100 uyuyor.\n";
    exit(0);
} else {
    echo "❌ [FAIL] Toplam " . count($errors) . " mimari kural ihlali bulundu:\n\n";
    foreach ($errors as $err) {
        echo "  - $err\n";
    }
    exit(1);
}