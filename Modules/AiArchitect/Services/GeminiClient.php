<?php
namespace Modules\AiArchitect\Services;

use Core\BaseModel;

class GeminiClient extends BaseModel
{
    /**
     * Kısa kullanıcı talebini, mockup görsellerini ve kaynak kodları analiz ederek
     * yüksek disiplinli, detaylı bir teknik şartname (specification prompt) üretir.
     */
    public function refineTaskSpecification(
        int $projectId,
        string $userInstruction,
        array $referenceImages = [],
        array $referenceFiles = [],
        bool $skipLiveApi = false
    ): array {
        $project = $this->query(
            "SELECT id, project_code, title, description, agent_rules, workspace_path FROM projects WHERE id = ?",
            [$projectId]
        );
        $projectRow = !empty($project) ? (object)$project[0] : null;

        $settingsRows = $this->query(
            "SELECT active_ai_provider, default_ai_model, gemini_api_key, openai_api_key, deepseek_api_key FROM settings LIMIT 1"
        );
        $settings = !empty($settingsRows) ? (object)$settingsRows[0] : null;

        $projTitle = $projectRow->title ?? 'Proje';
        $projCode  = $projectRow->project_code ?? 'PRJ';
        $projDesc  = $projectRow->description ?? '';
        $projRules = $projectRow->agent_rules ?? '';

        $systemPrompt = "You are a Principal Software Architect and elite AI Agent Prompt Engineer.
Your mission is to examine the provided UI/UX mockup images (wireframes, ChatGPT designs, screenshots), source code files, and user notes, and transform them into an EXTREMELY DETAILED, PIXEL-PERFECT, DEEPLY ANALYZED TECHNICAL SPECIFICATION PROMPT for autonomous coding agents (like Antigravity, Cursor, or Claude Code).

CRITICAL VISUAL ANALYSIS DIRECTIVE (WHEN IMAGES ARE PROVIDED):
You MUST visually analyze the image in extreme detail and explicitly describe every single section you see:
1. Header / Navbar components (Logo, navigation links, buttons, search triggers, badges).
2. Hero Section: Exact headline texts, subheadings, badges, background image style, and exact floating search/filter bars (inputs, dropdowns, placeholder texts, icons, action buttons).
3. Primary Call-to-Action Cards.
4. Middle / Featured Sections (Cards, thumbnail previews, comment counts, links).
5. Quick Submission Forms / Interactive Widgets.
6. Recent Feeds / Tabbed Lists.
7. Footer / News sections.
8. Colors, Typography & Styling: Exact hex color codes, border radiuses, box shadows, gradients, and icons (FontAwesome classes).

MANDATORY RULES:
1. OUTPUT LANGUAGE: Write the entire specification in the exact language used by the developer in the requirement note (if in Turkish, write completely in Turkish; if German, in German; if English, in English).
2. TONE & STRUCTURE: Authoritative, structured, and unambiguous. Use clean Markdown with headers, bold points, and code snippets where appropriate:
   - 🎯 **1. GÖREV AMACI & KAPSAMI** (Purpose & High-level Scope)
   - 🎨 **2. GÖRSEL TASARIM & UI/UX DETAYLI ANALİZİ** (Exhaustive visual breakdown of EVERY SECTION seen in the image: texts, cards, colors, icons, layout)
   - 📁 **3. ETKİLENECEK DOSYALAR & MİMARİ YAPI** (Target Files, classes, methods, or DB tables)
   - 🛠️ **4. ADIM ADIM UYGULAMA YÖNERGESİ** (Concrete step-by-step technical instructions: HTML structure, CSS classes, JS interactions, Mock Data)
   - 🚫 **5. KESİN YASAKLAR & MİMARİ SINIRLAR** (Prohibitions: No breaking changes, strict project standards, no spaghetti code)
   - ✅ **6. KABUL & OTONOM DOĞRULAMA KRİTERLERİ (DoD)** (Checklist of verified behaviors: live tests, zero console/PHP errors, responsiveness)
3. INCORPORATE ATTACHMENTS: If code files are provided, refer to their actual variables/functions. If images/mockups are provided, describe their visual layout, colors, alignment, and interactive behaviors.
4. OUTPUT FORMAT: Output ONLY the specification markdown. Do NOT wrap in outer ```markdown code blocks. No conversational greeting or chitchat.";

        $combinedUserPrompt = "PROJE KODU & BAŞLIĞI: [{$projCode}] {$projTitle}\n";
        if ($projDesc) {
            $combinedUserPrompt .= "PROJE ÖZETİ: {$projDesc}\n";
        }
        if ($projRules) {
            $combinedUserPrompt .= "PROJE KURALLARI & TEKNOLOJİ YIĞINI:\n{$projRules}\n";
        }
        $combinedUserPrompt .= "\n--- KULLANICININ TALEBİ / KISA NOTU ---\n{$userInstruction}\n";

        if (!empty($referenceFiles)) {
            $combinedUserPrompt .= "\n--- SAĞLANAN YAZILIM / KOD DOSYALARI ---\n";
            foreach ($referenceFiles as $fInfo) {
                $fName = $fInfo['name'] ?? 'dosya';
                $fContent = $fInfo['content'] ?? '';
                if (mb_strlen($fContent) > 25000) {
                    $fContent = mb_substr($fContent, 0, 25000) . "\n...[Dosya içeriği uzunluğu nedeniyle kırpıldı]...";
                }
                $combinedUserPrompt .= "Dosya: `{$fName}`\n```\n{$fContent}\n```\n\n";
            }
        }

        if (!empty($referenceImages)) {
            $combinedUserPrompt .= "\nNot: Kullanıcı " . count($referenceImages) . " adet görsel/ekran görüntüsü ekledi. Görseldeki tüm UI bileşenlerini, tipografiyi, renkleri ve etkileşimleri şartnameye yansıt.\n";
        }

        // 1. Google Gemini API Denemesi (Vision / Multimodal Destekli)
        $apiKey = trim($settings->gemini_api_key ?? '');
        if (!$skipLiveApi && !empty($apiKey) && !str_contains($apiKey, 'test_') && strlen($apiKey) >= 20) {
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

                $userParts = [];
                foreach ($referenceImages as $img) {
                    if (!empty($img['base64']) && !empty($img['mime'])) {
                        $userParts[] = [
                            'inline_data' => [
                                'mime_type' => $img['mime'],
                                'data'      => $img['base64']
                            ]
                        ];
                    }
                }
                $userParts[] = ['text' => $combinedUserPrompt];

                $payload = [
                    'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                    'contents'          => [['role' => 'user', 'parts' => $userParts]],
                    'generationConfig'  => [
                        'temperature'     => 0.2,
                        'maxOutputTokens' => 8192,
                    ]
                ];

                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    CURLOPT_TIMEOUT        => 10,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200 && $response) {
                    $data = json_decode($response, true);
                    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    if (!empty($text)) {
                        $cleaned = trim(preg_replace('/^```(?:markdown)?\s*|\s*```$/i', '', trim($text)));
                        return [
                            'success'       => true,
                            'specification' => $cleaned,
                            'engine'        => 'gemini (' . $tryModel . ')'
                        ];
                    }
                }
            }
        }

        // 2. OpenAI / DeepSeek API Denemesi
        $openAiKey = trim($settings->openai_api_key ?? '');
        $deepSeekKey = trim($settings->deepseek_api_key ?? '');
        if (!$skipLiveApi && ((!empty($openAiKey) && !str_contains($openAiKey, 'test_')) || (!empty($deepSeekKey) && !str_contains($deepSeekKey, 'test_')))) {
            $useDeepSeek = empty($openAiKey) || (($settings->active_ai_provider ?? '') === 'deepseek');
            $url = $useDeepSeek ? "https://api.deepseek.com/chat/completions" : "https://api.openai.com/v1/chat/completions";
            $key = $useDeepSeek ? $deepSeekKey : $openAiKey;
            $model = $useDeepSeek ? 'deepseek-chat' : 'gpt-4o';

            $userContent = [];
            $userContent[] = ['type' => 'text', 'text' => $combinedUserPrompt];

            if (!$useDeepSeek) {
                foreach ($referenceImages as $img) {
                    if (!empty($img['base64']) && !empty($img['mime'])) {
                        $userContent[] = [
                            'type'      => 'image_url',
                            'image_url' => [
                                'url' => "data:{$img['mime']};base64,{$img['base64']}"
                            ]
                        ];
                    }
                }
            }

            $payload = [
                'model'       => $model,
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $useDeepSeek ? $combinedUserPrompt : $userContent]
                ],
                'temperature' => 0.3
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $key
                ],
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $text = $data['choices'][0]['message']['content'] ?? '';
                if (!empty($text)) {
                    $cleaned = trim(preg_replace('/^```(?:markdown)?\s*|\s*```$/i', '', trim($text)));
                    return [
                        'success'       => true,
                        'specification' => $cleaned,
                        'engine'        => $useDeepSeek ? 'deepseek' : 'openai'
                    ];
                }
            }
        }

        // 3. Fallback Heuristic Specification Generator
        $fallbackSpec = $this->generateFallbackSpecification(
            $projTitle,
            $projCode,
            $userInstruction,
            $referenceFiles,
            !empty($referenceImages)
        );

        return [
            'success'       => true,
            'specification' => $fallbackSpec,
            'engine'        => 'internal_architect_fallback'
        ];
    }

    /**
     * API anahtarı girilmediğinde veya servis kapalıyken çalışan dahili mimari fallback motoru
     */
    public function generateFallbackSpecification(
        string $projTitle,
        string $projCode,
        string $instruction,
        array $referenceFiles = [],
        bool $hasImages = false
    ): string {
        $fileListStr = "";
        if (!empty($referenceFiles)) {
            $fNames = array_map(fn($f) => '`' . ($f['name'] ?? 'dosya') . '`', $referenceFiles);
            $fileListStr = implode(', ', $fNames);
        }

        $spec = "### 🎯 1. GÖREV AMACI & KAPSAMI\n"
              . "- **Proje:** [{$projCode}] {$projTitle}\n"
              . "- **Özet:** " . trim($instruction) . "\n\n";

        $spec .= "### 📁 2. ETKİLENECEK DOSYALAR & MİMARİ YAPI\n";
        if ($fileListStr) {
            $spec .= "- **İncelenecek & Düzenlenecek Kaynak Dosyalar:** {$fileListStr}\n";
        } else {
            $spec .= "- Projenin ilgili Controller, Model ve Görünüm (View/Component) dosyaları incelenerek mimariye uygun konumlandırılmalıdır.\n";
        }
        $spec .= "- Kod mimarisine sadık kalınmalı, projenin mevcut stil ve kodlama standartları korunmalıdır.\n\n";

        $spec .= "### 🛠️ 3. ADIM ADIM UYGULAMA YÖNERGESİ\n";
        $spec .= "1. **İnceleme & Analiz:** ";
        if ($hasImages) {
            $spec .= "Eklenen referans görselleri dikkatlice incele; layout, renkler, buton hizaları ve responsive davranışları belirle.\n";
        } elseif (!empty($referenceFiles)) {
            $spec .= "Sağlanan kaynak kod dosyalarını inceleyerek mevcut fonksiyonların ve veri yapılarının nasıl çalıştığını anla.\n";
        } else {
            $spec .= "Gereksinimleri analiz ederek etkilenen bileşenleri ve veri akışını haritalandır.\n";
        }

        $spec .= "2. **Geliştirme & Entegrasyon:**\n"
              . "   - İstenen alanları, metotları veya arayüz bileşenlerini temiz, modüler ve semantik olarak kodla.\n"
              . "   - Kullanıcı etkileşimlerini (tıklama, form gönderimi, veri doğrulaması) pürüzsüz çalışacak şekilde bağla.\n"
              . "   - Responsive (Mobil ve Masaüstü) ekran uyumluluğunu tam sağla.\n\n";

        $spec .= "### 🚫 4. KESİN YASAKLAR & MİMARİ SINIRLAR\n"
              . "- Mevcut çalışan diğer fonksiyonları ve bileşenleri bozacak yıkıcı değişiklikler yapmak KESİNLİKLE YASAKTIR.\n"
              . "- Konsolda JavaScript hatası veya arka planda PHP Warning/Notice bırakılamaz.\n"
              . "- Hardcoded statik veriler yerine dinamik ve genişletilebilir yapılar tercih edilmelidir.\n\n";

        $spec .= "### ✅ 5. KABUL VE OTONOM DOĞRULAMA KRİTERLERİ (DoD)\n"
              . "- Geliştirilen özellik canlı ortamda bizzat test edilmeli, formlar ve butonlar tıklanarak doğrulanmalıdır.\n"
              . "- Hiçbir görsel kayma, taşma veya responsive bozukluk olmamalıdır.\n"
              . "- İşlem tamamlandığında yapılan değişiklikler açıklayıcı bir özetle teslim edilmelidir.";

        return $spec;
    }
}
