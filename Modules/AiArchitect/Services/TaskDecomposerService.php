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
        $projRules = $project->agent_rules ?? '';
        $imgCount  = count($docInfo['extracted_imgs'] ?? []);

        // Eğer proje kuralları boşsa veya TaskManager projesiyse katı HMVC mimari kuralını enjekte et
        $enforcedStackRule = "TECHNICAL STACK & ARCHITECTURAL MANDATES FOR THIS PROJECT:\n";
        if (!empty($projRules)) {
            $enforcedStackRule .= $projRules . "\n\n";
        }
        if ((int)$projectId === 1 || stripos($projTitle, 'Task Manager') !== false || stripos($projRules, 'HMVC') !== false) {
            $enforcedStackRule .= "CRITICAL PROJECT CONSTRAINTS: This is a Custom Vanilla PHP 8.2+ HMVC Application (Core\\BaseModel, Core\\BaseController, Modules/{Module}/). IT IS NOT LARAVEL OR SYMFONY! NEVER suggest composer packages like `laravel/socialite` or framework utilities. All controllers must reside in `Modules/{Module}/Controllers/`, models in `Modules/{Module}/Models/`, views in `Modules/{Module}/Views/`, and translations in `Modules/{Module}/Lang/{tr,en,de}.php`. Use native PHP cURL or standard PHP features.\n";
        }

        $systemPrompt = "You are a Senior Technical Project Architect and Software Engineering Lead.
Your task is to analyze the user's project brief, contract specifications, or uploaded document and decompose it into modular Parent Tasks (Functional Epics/Modules) and concrete, single-purpose Subtasks.

CRITICAL ARCHITECTURAL RULES:
1. STRICT STACK COMPLIANCE: Adhere strictly to the project's technical stack defined in the prompt. If the project is a Custom PHP HMVC app, NEVER recommend Laravel, Symfony, or external packages that belong to other frameworks!
2. NEVER output mere titles with empty or generic descriptions! For EVERY Parent Task ('description') and EVERY Subtask ('description'), you MUST provide a detailed, actionable breakdown containing:
   - Purpose & Functional Scope
   - Step-by-Step Implementation Guide (referencing specific target files, methods, database changes)
   - Acceptance & Test Criteria / Definition of Done
3. Break features into distinct functional modules.
4. Subtask category MUST be one of: 'coding', 'design', 'seo', 'marketing', 'legal', 'content'. NEVER assign the 'ai' tag!
5. 'execution_mode': If automated software infrastructure, mark as 'auto'; if gated, mark as 'gated'.
6. MULTILINGUAL OUTPUT MANDATE: Match the user input language completely.
7. Return ONLY valid JSON matching this schema:
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
            . "{$enforcedStackRule}"
            . ($imgCount > 0 ? "Note: {$imgCount} reference screenshots/diagrams were extracted.\n" : "")
            . "\nUser Brief & Document Content:\n" . mb_substr($combinedBrief, 0, 18000) . "\n\n"
            . "Please analyze the requirements according to the project's exact technical stack and generate the JSON task tree:";

        $apiKey = trim($settings->gemini_api_key ?? '');
        if (!empty($apiKey) && !str_contains($apiKey, 'test_') && strlen($apiKey) >= 20) {
            $candidateModels = ['gemini-3.5-flash', 'gemini-3.5-flash-lite', 'gemini-3-flash-preview'];
            $configuredModel = $settings->default_ai_model ?? '';
            if (!empty($configuredModel) && !in_array($configuredModel, $candidateModels, true)) {
                array_unshift($candidateModels, $configuredModel);
            }

            foreach ($candidateModels as $tryModel) {
                if (str_contains($tryModel, '1.5') || str_contains($tryModel, '2.5')) {
                    continue;
                }
                
                // Transient 503 veya 429 kota durumlarında model başına 2 deneme hakkı
                for ($attempt = 1; $attempt <= 2; $attempt++) {
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
                        if ($text) {
                            $parsed = json_decode($text, true);
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
                        usleep(1200000); // 1.2 sn bekle
                        continue;
                    }

                    // Model bulunamadıysa (404) hemen sonraki modele geç
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
