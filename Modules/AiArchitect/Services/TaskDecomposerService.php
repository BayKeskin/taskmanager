<?php
namespace Modules\AiArchitect\Services;

use Core\BaseModel;
use Core\Language;

/**
 * TaskDecomposerService - Brif ve dökümanları analiz ederek modüler görev ağaçları üretir.
 */
class TaskDecomposerService extends BaseModel
{
    /**
     * Yüklenen Dökümandan (.docx, .doc, .pdf, .txt, .md, görsel) Metin ve Gömülü Resimleri Çıkarır.
     */
    public static function extractContentAndImagesFromDocument(array $file): array
    {
        $result = [
            'text'           => '',
            'extracted_imgs' => [],
            'original_doc'   => null
        ];

        if (empty($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            return $result;
        }

        $origName = $file['name'] ?? 'document';
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $uploadDir = dirname(__DIR__, 3) . '/Public/uploads/tasks';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        $safeOrigName = 'brief_' . time() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '_', $origName);
        $origDestPath = $uploadDir . '/' . $safeOrigName;
        $copied = @copy($file['tmp_name'], $origDestPath) || @move_uploaded_file($file['tmp_name'], $origDestPath);
        if ($copied) {
            $result['original_doc'] = [
                'file_name' => $origName,
                'file_path' => '/taskmanager/Public/uploads/tasks/' . $safeOrigName,
                'file_type' => $file['type'] ?? 'application/octet-stream',
                'file_size' => (int)($file['size'] ?? (file_exists($origDestPath) ? filesize($origDestPath) : 0))
            ];
        }

        $sourceFileToRead = file_exists($origDestPath) ? $origDestPath : $file['tmp_name'];

        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            if ($result['original_doc']) {
                $result['extracted_imgs'][] = $result['original_doc'];
            }
            return $result;
        }

        if (in_array($ext, ['txt', 'md', 'markdown', 'csv', 'json', 'html', 'htm'], true)) {
            $raw = (string)@file_get_contents($sourceFileToRead);
            $result['text'] = strip_tags($raw);
            return $result;
        }

        if (($ext === 'docx' || $ext === 'docm' || $ext === 'dotx') && class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            $opened = ($zip->open($sourceFileToRead, \ZipArchive::RDONLY) === true)
                   || ($zip->open($sourceFileToRead) === true);

            if ($opened) {
                $textParts = [];
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entryName = $zip->getNameIndex($i);
                    if (preg_match('/^word\/(document\d*|header\d*|footer\d*|footnotes)\.xml$/i', $entryName)) {
                        $xmlData = $zip->getFromIndex($i);
                        if ($xmlData) {
                            $xmlData = preg_replace('/<w:p(?: [^>]*)?>/i', "\n", $xmlData);
                            $xmlData = str_replace(['</w:p>', '</w:tr>', '<w:br/>', '<w:cr/>'], ["\n", "\n", "\n", "\n"], $xmlData);
                            $xmlData = str_replace(['</w:tc>'], ["\t"], $xmlData);
                            $clean = html_entity_decode(strip_tags($xmlData), ENT_QUOTES | ENT_XML1, 'UTF-8');
                            $clean = trim(preg_replace("/[ \t]+/", " ", $clean));
                            if (!empty($clean)) {
                                $textParts[] = $clean;
                            }
                        }
                    }
                }
                if (!empty($textParts)) {
                    $result['text'] = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n\n", $textParts)));
                }

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entryName = $zip->getNameIndex($i);
                    if (str_starts_with(strtolower($entryName), 'word/media/')) {
                        $imgExt = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));
                        if (in_array($imgExt, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'], true)) {
                            $imgBuffer = $zip->getFromIndex($i);
                            if ($imgBuffer && strlen($imgBuffer) > 512) {
                                $savedImgName = 'docx_img_' . time() . '_' . ($i + 1) . '.' . $imgExt;
                                $savedFullPath = $uploadDir . '/' . $savedImgName;
                                if (@file_put_contents($savedFullPath, $imgBuffer)) {
                                    $result['extracted_imgs'][] = [
                                        'file_name' => basename($entryName),
                                        'file_path' => '/taskmanager/Public/uploads/tasks/' . $savedImgName,
                                        'file_type' => 'image/' . ($imgExt === 'jpg' ? 'jpeg' : $imgExt),
                                        'file_size' => strlen($imgBuffer)
                                    ];
                                }
                            }
                        }
                    }
                }
                $zip->close();
            }
            return $result;
        }

        if ($ext === 'doc') {
            $raw = (string)@file_get_contents($sourceFileToRead);
            $extractedDocText = '';
            if (preg_match_all('/[\x20-\x7E\t\r\n]{4,}/', $raw, $matches)) {
                $extractedDocText = implode(" ", $matches[0]);
            }
            if (mb_strlen($extractedDocText) < 50 && preg_match_all('/(?:[\x20-\x7E]\x00){4,}/', $raw, $matches16)) {
                $extractedDocText .= " " . mb_convert_encoding(implode("", $matches16[0]), 'UTF-8', 'UTF-16LE');
            }
            $result['text'] = trim(strip_tags($extractedDocText));
            return $result;
        }

        if ($ext === 'pdf') {
            $rawPdf = (string)@file_get_contents($sourceFileToRead);
            $extracted = '';
            if (preg_match_all('/\(([^\)]+)\)\s*Tj/s', $rawPdf, $matches)) {
                $extracted = implode(' ', $matches[1]);
            }
            if (mb_strlen(trim($extracted)) < 20 && preg_match_all('/\[([^\]]+)\]\s*TJ/s', $rawPdf, $matches2)) {
                $extracted .= ' ' . preg_replace('/[\(\)\-\d\.]+/', ' ', implode(' ', $matches2[1]));
            }
            $result['text'] = trim($extracted);
            return $result;
        }

        return $result;
    }

    /**
     * AI Proje Mimarı (Task Decomposer)
     */
    public static function decomposeBriefWithAi(string $brief, int $projectId = 1, array $docInfo = []): array
    {
        $docText = trim($docInfo['text'] ?? '');
        $hasDoc = !empty($docInfo['original_doc']);
        $combinedBrief = trim($brief . ($docText ? "\n\n--- UPLOADED DOCUMENT CONTENT ---\n" . $docText : ''));

        if (empty($combinedBrief) && empty($docInfo['extracted_imgs']) && !$hasDoc) {
            $isTr = (class_exists('Core\\Language') && Language::current() === 'tr');
            return [
                'success' => false, 
                'error' => $isTr 
                    ? 'Lütfen ayrıştırılacak proje brifini yazın veya bir Word (.docx) / PDF dökümanı yükleyin.' 
                    : 'Please provide the project brief or upload a Word (.docx) / PDF document to decompose.'
            ];
        }

        if (empty($combinedBrief) && !empty($docInfo['extracted_imgs'])) {
            $combinedBrief = "Analyze the uploaded UI screenshots, architecture mockups, and wireframes to generate full frontend and backend implementation tasks.";
        }

        if (empty($combinedBrief) && $hasDoc) {
            $docName = $docInfo['original_doc']['file_name'] ?? 'Proje Şartnamesi';
            $combinedBrief = "Yüklenen dökümanı ([{$docName}]) analiz ederek projenin gereksinim duyduğu modülleri, arayüz geliştirmelerini, teknik altyapıyı ve canlı ortam kabul kriterlerini oluştur.";
        }

        $db = $GLOBALS['db'] ?? null;
        if (!$db || empty($db->connect)) {
            $db = new \Core\DataLayer();
        }

        $project = $db->row_query("SELECT project_code, title, description, agent_rules FROM projects WHERE id = ?", [$projectId]);
        $settings = $db->row_query("SELECT active_ai_provider, default_ai_model, gemini_api_key, openai_api_key, deepseek_api_key FROM settings LIMIT 1");

        $projTitle = $project->title ?? 'Proje';
        $projCode  = $project->project_code ?? 'PRJ';
        $projDesc  = $project->description ?? '';
        $projRules = $project->agent_rules ?? '';
        $imgCount  = count($docInfo['extracted_imgs'] ?? []);

        $systemPrompt = "You are a Senior Technical Project Architect and Software Engineering Lead.
Your task is to analyze the user's project brief, contract specifications, or uploaded document and decompose it into modular Parent Tasks (Functional Epics/Modules) and concrete, single-purpose Subtasks.

CRITICAL ARCHITECTURAL RULES:
1. NEVER output mere titles with empty or generic descriptions! For EVERY Parent Task ('description') and EVERY Subtask ('description'), you MUST provide a detailed, actionable breakdown containing:
   - Purpose & Functional Scope (Görev Amacı ve Kapsamı)
   - Step-by-Step Implementation Guide (🛠️ Nasıl Yapılacak / Adımlar)
   - Acceptance & Test Criteria / Definition of Done (✅ Kabul ve Test Kriterleri)
2. Break features into distinct functional modules.
3. Subtask category MUST be one of: 'coding', 'design', 'seo', 'marketing', 'legal', 'content'. NEVER assign the 'ai' tag!
4. 'execution_mode': If automated software infrastructure, mark as 'auto'; if gated, mark as 'gated'.
5. MULTILINGUAL OUTPUT MANDATE: Match the user input language.
6. Return ONLY valid JSON matching this schema:
{
  \"epics\": [
    {
      \"title\": \"Functional Module Title\",
      \"description\": \"Architectural scope, purpose, and integration notes\",
      \"priority\": \"high\",
      \"execution_mode\": \"auto\",
      \"subtasks\": [
        {
          \"title\": \"Concrete subtask title\",
          \"description\": \"🛠️ NASIL YAPILACAK (Uygulama Adımları):\\n1. ...\\n\\n✅ KABUL VE TEST KRİTERLERİ (DoD):\\n- ...\",
          \"category\": \"coding\",
          \"priority\": \"normal\",
          \"time_estimate\": \"2h\"
        }
      ]
    }
  ]
}";

        $userPrompt = "Project: [{$projCode}] {$projTitle}\n"
            . ($projDesc ? "Project Overview: {$projDesc}\n" : "")
            . ($projRules ? "Technical Rules & Stack: {$projRules}\n" : "")
            . ($imgCount > 0 ? "Note: {$imgCount} reference screenshots/diagrams were extracted.\n" : "")
            . "\nUser Brief & Document Content:\n" . mb_substr($combinedBrief, 0, 18000) . "\n\n"
            . "Please analyze the requirements and generate the JSON task tree:";

        $apiKey = trim($settings->gemini_api_key ?? '');
        if (!empty($apiKey) && !str_contains($apiKey, 'test_') && strlen($apiKey) >= 20) {
            $candidateModels = ['gemini-3.7-flash', 'gemini-3.5-flash-lite', 'gemini-3.8-flash'];
            $configuredModel = $settings->default_ai_model ?? '';
            if (!empty($configuredModel) && !in_array($configuredModel, $candidateModels, true)) {
                array_unshift($candidateModels, $configuredModel);
            }

            foreach ($candidateModels as $tryModel) {
                if (str_contains($tryModel, '1.5') || str_contains($tryModel, '2.5')) {
                    continue;
                }
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$tryModel}:generateContent?key=" . urlencode($apiKey);
                $payload = [
                    'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                    'contents'          => [['role' => 'user', 'parts' => [['text' => $userPrompt]]]],
                    'generationConfig'  => [
                        'temperature'      => 0.2,
                        'maxOutputTokens'  => 8192,
                        'responseMimeType' => 'application/json'
                    ]
                ];

                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    CURLOPT_TIMEOUT        => 90,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200 && $response) {
                    $data = json_decode($response, true);
                    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    $cleaned = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)));
                    $parsed = json_decode($cleaned, true);
                    if (!empty($parsed['epics']) && is_array($parsed['epics'])) {
                        return \Modules\Board\Services\TaskLifecycleService::sanitizeUtf8Deep([
                            'success'        => true,
                            'epics'          => $parsed['epics'],
                            'extracted_imgs' => $docInfo['extracted_imgs'] ?? [],
                            'original_doc'   => $docInfo['original_doc'] ?? null,
                            'engine'         => 'gemini (' . $tryModel . ')'
                        ]);
                    }
                }
            }
        }

        // Heuristic fallback
        $epics = self::decomposeBriefDynamicParser($combinedBrief, $projTitle, $projRules);
        return \Modules\Board\Services\TaskLifecycleService::sanitizeUtf8Deep([
            'success'        => true,
            'epics'          => $epics,
            'extracted_imgs' => $docInfo['extracted_imgs'] ?? [],
            'original_doc'   => $docInfo['original_doc'] ?? null,
            'engine'         => 'dynamic_parser'
        ]);
    }

    /**
     * API Anahtarı olmadığında veya çevrimdışı modda çalışan uzman motor
     */
    public static function decomposeBriefDynamicParser(string $brief, string $projTitle = 'Proje', string $projRules = ''): array
    {
        $epics = [];
        $cleanBrief = str_replace("--- UPLOADED DOCUMENT CONTENT ---", "\n", $brief);
        $cleanBrief = str_replace("--- YÜKLENEN DÖKÜMAN İÇERİĞİ ---", "\n", $cleanBrief);
        $rawLines = preg_split('/[\r\n]+/', trim($cleanBrief));

        $buildDetailedHowTo = function(string $title, string $context, string $category) use ($projTitle): string {
            return "🛠️ **NASIL YAPILACAK (Uygulama Adımları):**\n"
                 . "1. `{$title}` gereksinimleri doğrultusunda ilgili model, controller ve view dosyalarını belirle.\n"
                 . "2. Temiz, modüler ve güvenli kodlama prensiplerine uygun olarak iş mantığını yaz.\n"
                 . "3. Veri doğrulaması, hata yakalama ve arayüz entegrasyonunu tamamla.\n\n"
                 . "✅ **KABUL VE TEST KRİTERLERİ (Definition of Done):**\n"
                 . "- Canlı ortamda ve tarayıcıda ilgili ekran hatasız açılmalı.\n"
                 . "- Sıfır console hatası, sıfır PHP warning/notice.\n"
                 . "- Responsive uyumluluk tam sağlanmalı.";
        };

        $epics[] = [
            'title'          => 'Temel Altyapı ve Veri Modeli',
            'description'    => "{$projTitle} projesinin ana omurgasını ve temel veri akışlarını oluşturur.",
            'priority'       => 'high',
            'execution_mode' => 'auto',
            'subtasks'       => [
                [
                    'title'         => 'Veri ve Altyapı Hazırlığı',
                    'description'   => $buildDetailedHowTo('Veri ve Altyapı', 'İsterler doğrultusunda veritabanı şeması ve model metodlarının oluşturulması.', 'coding'),
                    'category'      => 'coding',
                    'priority'      => 'high',
                    'time_estimate' => '2h'
                ],
                [
                    'title'         => 'Kullanıcı Arayüzü ve İş Akışı Entegrasyonu',
                    'description'   => $buildDetailedHowTo('Arayüz Entegrasyonu', 'Önyüz bileşenlerinin ve form akışlarının kodlanması.', 'coding'),
                    'category'      => 'coding',
                    'priority'      => 'normal',
                    'time_estimate' => '3h'
                ]
            ]
        ];

        return $epics;
    }
}
