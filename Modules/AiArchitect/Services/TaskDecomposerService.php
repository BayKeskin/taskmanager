<?php
namespace Modules\AiArchitect\Services;

use Modules\AiArchitect\Models\AiArchitectModel;

/**
 * TaskDecomposerService - Brif ve dökümanları analiz ederek modüler görev ağaçları üretir.
 */
class TaskDecomposerService
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
                'file_path' => (function_exists('baseUrl') ? baseUrl('/Public/uploads/tasks/') : '/Public/uploads/tasks/') . $safeOrigName,
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
                                        'file_path' => (function_exists('baseUrl') ? baseUrl('/Public/uploads/tasks/') : '/Public/uploads/tasks/') . $savedImgName,
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
            return [
                'success' => false, 
                'error' => __('AiArchitect::empty_brief_error')
            ];
        }

        if (empty($combinedBrief) && !empty($docInfo['extracted_imgs'])) {
            $combinedBrief = "Analyze the uploaded UI screenshots, architecture mockups, and wireframes to generate full frontend and backend implementation tasks.";
        }

        if (empty($combinedBrief) && $hasDoc) {
            $docName = $docInfo['original_doc']['file_name'] ?? __('AiArchitect::default_doc_name');
            $combinedBrief = "Analyze the uploaded document ([{$docName}]) to produce required architecture modules, interface tasks, technical backend, and Definition of Done criteria.";
        }

        $model = new AiArchitectModel();
        $project = $model->getProjectDetails($projectId);
        $settings = $model->getAiSettings();

        $projTitle = $project->title ?? 'Project';
        $projCode  = $project->project_code ?? 'PRJ';
        $projDesc  = $project->description ?? '';
        $projRules = trim($project->agent_rules ?? '');
        $imgCount  = count($docInfo['extracted_imgs'] ?? []);

        $systemPrompt = "You are a Senior Technical Project Architect and Software Engineering Lead.
Your task is to analyze the user's project brief, contract specifications, or uploaded document and decompose it into modular Parent Tasks (Functional Epics/Modules) and concrete, single-purpose Subtasks.

CRITICAL NATURAL LANGUAGE MATCHING MANDATE:
- You MUST detect and STRICTLY MATCH the language of the provided 'User Brief & Document Content'!
- If the brief or uploaded document is written in TURKISH: All output, all module titles ('title'), module descriptions ('description'), subtask titles ('title'), and implementation guides ('description') MUST be 100% in TURKISH! Never switch to English!
- If the brief or uploaded document is written in ENGLISH: All output MUST be 100% in ENGLISH!
- If the brief or uploaded document is written in GERMAN: All output MUST be 100% in GERMAN!
- Whatever language the user or uploaded document speaks, respond in that EXACT SAME language with 0% language pollution!

CRITICAL PROJECT ARCHITECTURE & AGENT_RULES MANDATE:
You are provided with the target project's official `PROJECT RULES & TECH STACK (agent_rules)` from the database.
1. You MUST strictly obey every single architectural rule, framework constraint, directory structure, and coding standard stated in `PROJECT RULES & TECH STACK`!
2. If the project rules specify Custom HMVC (`Modules/{ModuleName}/...`), write all implementation steps, file paths, Controllers, Models, Views, and `Lang/{tr,en,de}.php` strictly for that HMVC structure and include the mandatory `php taskmanager-audit/audit.php` verification step!
3. If the project rules specify CodeIgniter 4, WordPress, Flutter, or Vanilla PHP, write all tasks strictly according to that exact stack!
4. STRICTLY FORBIDDEN: NEVER suggest, assume, or introduce Laravel, Artisan, Blade, Composer packages, or any external framework that contradicts the project's `agent_rules`!

CRITICAL ARCHITECTURAL RULES:
1. NEVER output mere titles with empty or generic descriptions! For EVERY Parent Task ('description') and EVERY Subtask ('description'), you MUST provide a detailed, actionable breakdown containing:
   - Purpose & Functional Scope
   - Step-by-Step Implementation Guide (referencing specific target files, methods, database changes)
   - Acceptance & Test Criteria / Definition of Done
2. Break features into distinct functional modules.
3. Subtask category MUST be one of: 'coding', 'design', 'seo', 'marketing', 'legal', 'content'. NEVER assign the 'ai' tag!
4. 'execution_mode': If automated software infrastructure, mark as 'auto'; if gated, mark as 'gated'.
5. Return ONLY valid JSON matching this schema:
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
          \"description\": \"Implementation steps and Definition of Done criteria\",
          \"category\": \"coding\",
          \"priority\": \"normal\",
          \"time_estimate\": \"2h\"
        }
      ]
    }
  ]
}";

        $userPrompt = "Target Project: [{$projCode}] {$projTitle}\n"
            . ($projDesc ? "Project Overview: {$projDesc}\n" : "")
            . "\n=== MANDATORY PROJECT ARCHITECTURE & AGENT RULES ===\n"
            . (!empty($projRules) ? $projRules : "Follow clean modular software design matching the project codebase.") . "\n"
            . "====================================================\n\n"
            . ($imgCount > 0 ? "Note: {$imgCount} reference screenshots/diagrams were attached. Carefully inspect them.\n" : "")
            . "\nUser Brief & Document Content:\n" . mb_substr($combinedBrief, 0, 18000) . "\n\n"
            . "Analyze the requirements strictly obeying the project's architecture rules and generate the JSON task tree in the EXACT SAME LANGUAGE as the User Brief & Document Content above:";

        $apiKey = trim($settings->gemini_api_key ?? '');
        if (!empty($apiKey) && !str_contains($apiKey, 'test_') && strlen($apiKey) >= 20) {
            $candidateModels = [];
            $configuredModel = trim($settings->default_ai_model ?? '');
            if (!empty($configuredModel)) {
                $candidateModels[] = $configuredModel;
            }
            foreach (['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-1.5-flash', 'gemini-1.5-pro', 'gemini-3.5-flash', 'gemini-3.5-flash-lite'] as $stdModel) {
                if (!in_array($stdModel, $candidateModels, true)) {
                    $candidateModels[] = $stdModel;
                }
            }

            // Çıkarılan görselleri Gemini için inlineData parts dizisine dönüştür
            $imageParts = [];
            if (!empty($docInfo['extracted_imgs']) && is_array($docInfo['extracted_imgs'])) {
                $docUploadDir = dirname(__DIR__, 3);
                foreach ($docInfo['extracted_imgs'] as $imgItem) {
                    $relPath = $imgItem['file_path'] ?? '';
                    $cleanRel = preg_replace('#^https?://[^/]+#i', '', $relPath);
                    $fullImgPath = $docUploadDir . '/' . ltrim($cleanRel, '/\\');
                    if (file_exists($fullImgPath) && is_readable($fullImgPath)) {
                        $rawBytes = @file_get_contents($fullImgPath);
                        if (!empty($rawBytes) && strlen($rawBytes) <= 4 * 1024 * 1024) {
                            $mime = $imgItem['file_type'] ?? 'image/jpeg';
                            $imageParts[] = [
                                'inlineData' => [
                                    'mimeType' => $mime,
                                    'data'     => base64_encode($rawBytes)
                                ]
                            ];
                        }
                    }
                }
            }

            $userParts = array_merge([['text' => $userPrompt]], $imageParts);

            foreach ($candidateModels as $tryModel) {
                for ($attempt = 1; $attempt <= 2; $attempt++) {
                    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$tryModel}:generateContent?key=" . urlencode($apiKey);
                    $payload = [
                        'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                        'contents'          => [['role' => 'user', 'parts' => $userParts]],
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
                        CURLOPT_POSTFIELDS     => json_encode($payload),
                        CURLOPT_TIMEOUT        => 45,
                        CURLOPT_CONNECTTIMEOUT => 10,
                        CURLOPT_SSL_VERIFYPEER => false
                    ]);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($httpCode === 200 && $response) {
                        $jsonRes = json_decode($response, true);
                        $text = $jsonRes['candidates'][0]['content']['parts'][0]['text'] ?? '';
                        if (!empty($text)) {
                            // Başındaki / sonundaki ```json ve ``` bloklarını temizle
                            $cleaned = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)));
                            $parsed = json_decode($cleaned, true);

                            // Eğer doğrudan decode edilemezse ilk { ile son } arasını ayıkla
                            if ((empty($parsed) || !is_array($parsed)) && preg_match('/\{.*\}/s', $cleaned, $m)) {
                                $parsed = json_decode($m[0], true);
                            }

                            if (!empty($parsed['epics']) && is_array($parsed['epics'])) {
                                return [
                                    'success'        => true,
                                    'epics'          => $parsed['epics'],
                                    'extracted_imgs' => $docInfo['extracted_imgs'] ?? [],
                                    'original_doc'   => $docInfo['original_doc'] ?? null,
                                    'engine'         => 'gemini (' . $tryModel . ')'
                                ];
                            }
                        }
                    }

                    // 503 Service Unavailable veya 429 Too Many Requests durumunda kısa bekleme ve retry/fallback
                    if ($httpCode === 503 || $httpCode === 429) {
                        usleep(1000000); // 1.0 sn bekle
                        continue;
                    }

                    // Model bulunamadıysa (404) sonraki modele geç
                    if ($httpCode === 404) {
                        break;
                    }
                }
            }

            return [
                'success' => false,
                'error'   => __('AiArchitect::err_ai_busy')
            ];
        }

        // API anahtarı boşsa kullanıcıyı dürüstçe uyar
        return [
            'success' => false,
            'error'   => __('AiArchitect::err_missing_api_key')
        ];
    }
}
