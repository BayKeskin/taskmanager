<?php
namespace Modules\AiArchitect\Services;

use Modules\AiArchitect\Models\AiArchitectModel;

class GeminiClient
{
    private AiArchitectModel $model;

    public function __construct()
    {
        $this->model = new AiArchitectModel();
    }

    /**
     * Analyzes short developer note, UI mockups, and code files to produce an architectural specification.
     */
    public function refineTaskSpecification(
        int $projectId,
        string $userInstruction,
        array $referenceImages = [],
        array $referenceFiles = [],
        bool $skipLiveApi = false
    ): array {
        $projectRow = $this->model->getProjectDetails($projectId);
        $settings = $this->model->getAiSettings();

        $projTitle = $projectRow->title ?? 'Project';
        $projCode  = $projectRow->project_code ?? 'PRJ';
        $projDesc  = $projectRow->description ?? '';
        $projRules = $projectRow->agent_rules ?? '';

        // Katı mimari sınırları enjekte et (asla Laravel veya farklı framework önermemeli)
        $enforcedStackRule = "TECHNICAL STACK & ARCHITECTURAL MANDATES FOR THIS PROJECT:\n";
        if (!empty($projRules)) {
            $enforcedStackRule .= $projRules . "\n\n";
        }
        if ((int)$projectId === 1 || stripos($projTitle, 'Task Manager') !== false || stripos($projRules, 'HMVC') !== false) {
            $enforcedStackRule .= "CRITICAL PROJECT CONSTRAINTS: This is a Custom Vanilla PHP 8.2+ HMVC Application (Core\\BaseModel, Core\\BaseController, Modules/{Module}/). IT IS NOT LARAVEL OR SYMFONY! NEVER suggest composer packages like `laravel/socialite` or framework utilities. All controllers must reside in `Modules/{Module}/Controllers/`, models in `Modules/{Module}/Models/`, views in `Modules/{Module}/Views/`, and translations in `Modules/{Module}/Lang/{tr,en,de}.php`. Use native PHP cURL or standard PHP features.\n";
        }

        $systemPrompt = "You are a Principal Software Architect and elite AI Agent Prompt Engineer.
Your mission is to examine the provided UI/UX mockup images (wireframes, designs, screenshots), source code files, and user notes, and transform them into an EXTREMELY DETAILED, PIXEL-PERFECT, DEEPLY ANALYZED TECHNICAL SPECIFICATION PROMPT for autonomous coding agents (like Antigravity, Cursor, or Claude Code).

STRICT STACK COMPLIANCE:
Adhere strictly to the project's technical stack defined in the prompt. If the project is a Custom PHP HMVC app, NEVER recommend Laravel, Symfony, or external packages that belong to other frameworks!

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
   - 🎯 **1. SCOPE & OBJECTIVES** (Purpose & High-level Scope)
   - 🎨 **2. UI/UX VISUAL ANALYSIS** (Exhaustive visual breakdown of EVERY SECTION seen in the image: texts, cards, colors, icons, layout)
   - 📁 **3. TARGET FILES & ARCHITECTURE** (Target Files, classes, methods, or DB tables matching the exact project stack)
   - 🛠️ **4. STEP-BY-STEP IMPLEMENTATION** (Concrete step-by-step technical instructions: HTML structure, CSS classes, JS interactions, Controller/Model methods)
   - 🚫 **5. STRICT PROHIBITIONS & BOUNDARIES** (No Laravel/external framework dependencies, no breaking changes, strict project standards)
   - ✅ **6. DEFINITION OF DONE (DoD)** (Checklist of verified behaviors: live tests, zero console/PHP errors, responsiveness)
3. INCORPORATE ATTACHMENTS: If code files are provided, refer to their actual variables/functions. If images/mockups are provided, describe their visual layout, colors, alignment, and interactive behaviors.
4. OUTPUT FORMAT: Output ONLY the specification markdown. Do NOT wrap in outer ```markdown code blocks. No conversational greeting or chitchat.";

        $combinedUserPrompt = "PROJECT CODE & TITLE: [{$projCode}] {$projTitle}\n";
        if ($projDesc) {
            $combinedUserPrompt .= "PROJECT OVERVIEW: {$projDesc}\n";
        }
        $combinedUserPrompt .= "{$enforcedStackRule}\n";
        $combinedUserPrompt .= "\n--- USER INSTRUCTION / REQUIREMENT ---\n{$userInstruction}\n";

        if (!empty($referenceFiles)) {
            $combinedUserPrompt .= "\n--- PROVIDED SOURCE CODE FILES ---\n";
            foreach ($referenceFiles as $fInfo) {
                $fName = $fInfo['name'] ?? 'file';
                $fContent = $fInfo['content'] ?? '';
                if (mb_strlen($fContent) > 25000) {
                    $fContent = mb_substr($fContent, 0, 25000) . "\n...[Truncated due to length]...";
                }
                $combinedUserPrompt .= "File: `{$fName}`\n```\n{$fContent}\n```\n\n";
            }
        }

        if (!empty($referenceImages)) {
            $combinedUserPrompt .= "\nNote: User attached " . count($referenceImages) . " reference images/screenshots. Reflect all UI components, typography, colors, and interactions into the specification.\n";
        }

        // 1. Google Gemini API Call
        $apiKey = trim($settings->gemini_api_key ?? '');
        if (!$skipLiveApi && !empty($apiKey) && !str_contains($apiKey, 'test_') && strlen($apiKey) >= 20) {
            $candidateModels = ['gemini-3.5-flash', 'gemini-3.5-flash-lite', 'gemini-3-flash-preview'];
            $configuredModel = $settings->default_ai_model ?? '';
            if (!empty($configuredModel) && !in_array($configuredModel, $candidateModels, true)) {
                array_unshift($candidateModels, $configuredModel);
            }

            foreach ($candidateModels as $tryModel) {
                if (str_contains($tryModel, '1.5') || str_contains($tryModel, '2.5')) {
                    continue;
                }

                for ($attempt = 1; $attempt <= 2; $attempt++) {
                    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$tryModel}:generateContent?key=" . urlencode($apiKey);

                    $parts = [];
                    $parts[] = ['text' => $combinedUserPrompt];

                    // Append reference images as inlineData
                    foreach ($referenceImages as $img) {
                        if (!empty($img['base64'])) {
                            $parts[] = [
                                'inlineData' => [
                                    'mimeType' => $img['mime'] ?? 'image/jpeg',
                                    'data'     => $img['base64']
                                ]
                            ];
                        }
                    }

                    $payload = [
                        'systemInstruction' => [
                            'parts' => [['text' => $systemPrompt]]
                        ],
                        'contents' => [
                            [
                                'role'  => 'user',
                                'parts' => $parts
                            ]
                        ],
                        'generationConfig' => [
                            'temperature'     => 0.25,
                            'maxOutputTokens' => 8192
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
                            $cleaned = trim(preg_replace('/^```(?:markdown)?\s*|\s*```$/i', '', trim($text)));
                            return [
                                'success'       => true,
                                'specification' => $cleaned,
                                'engine'        => 'gemini (' . $tryModel . ')'
                            ];
                        }
                    }

                    if ($httpCode === 503 || $httpCode === 429) {
                        usleep(1200000); // 1.2 sn bekle
                        continue;
                    }

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

        return [
            'success' => false,
            'error'   => __('AiArchitect::err_missing_api_key')
        ];
    }

    /**
     * Fallback Heuristic Specification Generator
     */
    private function generateHeuristicSpecification(
        string $projCode,
        string $projTitle,
        string $instruction,
        array $referenceFiles = [],
        bool $hasImages = false
    ): string {
        $fileListStr = "";
        if (!empty($referenceFiles)) {
            $fNames = array_map(fn($f) => '`' . ($f['name'] ?? 'file') . '`', $referenceFiles);
            $fileListStr = implode(', ', $fNames);
        }

        $spec = "### 🎯 1. SCOPE & OBJECTIVES\n"
              . "- **Project:** [{$projCode}] {$projTitle}\n"
              . "- **Summary:** " . trim($instruction) . "\n\n";

        $spec .= "### 📁 2. TARGET FILES & ARCHITECTURE\n";
        if ($fileListStr) {
            $spec .= "- **Source Files:** {$fileListStr}\n";
        } else {
            $spec .= "- Corresponding Controller, Model, and View files must be identified and mapped according to HMVC standards.\n";
        }
        $spec .= "- Strict separation of concerns must be preserved.\n\n";

        $spec .= "### 🛠️ 3. STEP-BY-STEP IMPLEMENTATION\n";
        $spec .= "1. **Analysis:** ";
        if ($hasImages) {
            $spec .= "Examine attached screenshots/mockups; identify layout, colors, typography, and responsive breakpoints.\n";
        } elseif (!empty($referenceFiles)) {
            $spec .= "Examine source files to map data structures and method signatures.\n";
        } else {
            $spec .= "Analyze requirements and map data flows.\n";
        }

        $spec .= "2. **Development:**\n"
              . "   - Code requested methods and interface components in a clean, semantic manner.\n"
              . "   - Connect user interactions (clicks, form submits, validations) seamlessly.\n"
              . "   - Ensure complete responsiveness across mobile and desktop breakpoints.\n\n";

        $spec .= "### 🚫 4. STRICT PROHIBITIONS & BOUNDARIES\n"
              . "- Destructive changes breaking existing working features are strictly prohibited.\n"
              . "- Zero console errors and zero PHP warnings/notices.\n"
              . "- Zero hardcoded strings; all strings must use localized dictionary lookups.\n\n";

        $spec .= "### ✅ 5. DEFINITION OF DONE (DoD)\n"
              . "- Feature must be verified live in the browser environment.\n"
              . "- Zero layout overflows or visual flaws.\n"
              . "- Comprehensive delivery summary must be generated.";

        return $spec;
    }

    /**
     * Compiles raw developer rules into structured English markdown rules.
     */
    public function refineRulesWithAi(string $rawRules, string $projectCode = '', string $projectTitle = ''): array
    {
        $rawRules = trim($rawRules);
        if (empty($rawRules)) {
            return [
                'success' => false,
                'error'   => __('AiArchitect::empty_rules_error')
            ];
        }

        $settings = $this->model->getAiSettings();
        $apiKey = trim($settings->gemini_api_key ?? '');

        if (!empty($apiKey) && !str_contains($apiKey, 'test_') && strlen($apiKey) >= 20) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.7-flash:generateContent?key=" . urlencode($apiKey);
            $prompt = "You are a Principal Software Architect. Transform the following project notes into standard, authoritative project agent rules in English markdown format:\n\n{$rawRules}";

            $payload = [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $prompt]]]
                ],
                'generationConfig' => [
                    'temperature'     => 0.2,
                    'maxOutputTokens' => 4096
                ]
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $jsonRes = json_decode($response, true);
                $text = $jsonRes['candidates'][0]['content']['parts'][0]['text'] ?? '';
                if (!empty($text)) {
                    $cleaned = trim(preg_replace('/^```(?:markdown)?\s*|\s*```$/i', '', trim($text)));
                    return [
                        'success'       => true,
                        'refined_rules' => $cleaned,
                        'engine'        => 'gemini'
                    ];
                }
            }
        }

        $heuristicRules = $this->compileRulesHeuristic($rawRules, $projectCode, $projectTitle);
        return [
            'success'       => true,
            'refined_rules' => $heuristicRules,
            'engine'        => 'heuristic'
        ];
    }

    /**
     * Fallback Heuristic Rule Compiler
     */
    public function compileRulesHeuristic(string $raw, string $code = '', string $title = ''): string
    {
        $code = $code ?: 'PROJECT';
        $title = $title ?: 'Custom Project';
        $lower = mb_strtolower($raw, 'UTF-8');
        $rules = [];

        if (str_contains($lower, 'gutenberg') || str_contains($lower, 'wordpress') || str_contains($lower, 'block')) {
            $rules[] = "- **Platform & Core Framework:** WordPress Gutenberg Block Architecture. All UI elements MUST be developed natively as Gutenberg Blocks using `@wordpress/block-editor`, `@wordpress/components`, and `@wordpress/element`.";
            if (str_contains($lower, 'css') && (str_contains($lower, 'prohibit') || str_contains($lower, 'forbidden') || str_contains($lower, 'no css'))) {
                $rules[] = "- **Block Styling Prohibition:** Writing raw CSS is forbidden. All styling must be controlled via theme tokens.";
            } else {
                $rules[] = "- **Block Styling Standards:** Adhere strictly to block-level styling and `theme.json` theme tokens; avoid polluting global CSS scopes.";
            }
            $rules[] = "- **Editor Integrity & Component Hygiene:** Custom controls must use standard Gutenberg InspectorControls and BlockControls. Never inject unvetted third-party JavaScript libraries into the editor canvas.";
            $rules[] = "- **Live Block Verification:** Before submitting any task, the agent MUST verify within the WordPress block editor that blocks render cleanly, block attributes persist correctly upon post save, and zero JavaScript console errors occur.";
        } elseif (str_contains($lower, 'flutter') || str_contains($lower, 'dart') || str_contains($lower, 'mobile')) {
            $rules[] = "- **Technology Stack:** Flutter (Dart) cross-platform mobile development for iOS and Android.";
            if (str_contains($lower, 'riverpod')) {
                $rules[] = "- **State Management:** Riverpod MUST be used as the exclusive state management solution. Avoid mixing conflicting state paradigms.";
            } elseif (str_contains($lower, 'bloc') || str_contains($lower, 'cubit')) {
                $rules[] = "- **State Management:** BLoC / Cubit pattern MUST be used for state separation and business logic.";
            } else {
                $rules[] = "- **State Management:** Follow strict unidirectional data flow and clean state encapsulation.";
            }
            $rules[] = "- **UI & Responsiveness:** Material 3 design guidelines; strictly avoid render overflow errors by wrapping dynamic content in responsive layouts (`LayoutBuilder`, `SingleChildScrollView`, `Expanded`).";
            $rules[] = "- **Mobile QA Verification:** The user is not a QA tester. Form interactions, navigation transitions, and button states must be autonomously verified in the emulator/test environment.";
        } elseif (str_contains($lower, 'codeigniter') || str_contains($lower, 'ci4') || str_contains($lower, 'laravel') || str_contains($lower, 'mvc') || str_contains($lower, 'php')) {
            $rules[] = "- **Backend Architecture:** Strict Model-View-Controller (MVC) architecture. Controllers coordinate request flow, while Models/Entities encapsulate database queries and business logic.";
            if (str_contains($lower, 'view') || str_contains($lower, 'sql') || str_contains($lower, 'db')) {
                $rules[] = "- **Zero Direct SQL in Views:** Executing raw SQL queries or database calls inside View templates is strictly forbidden. All data access must pass through dedicated Model classes.";
            }
        } else {
            $rules[] = "- **Architecture & Framework Guidelines:** Follow strict separation of concerns, modular structure, and clean coding standards throughout the codebase.";
        }

        if (str_contains($lower, 'git') || str_contains($lower, 'pull') || str_contains($lower, 'push') || str_contains($lower, 'commit')) {
            $rules[] = "- **Git Synchronization Protocol:** Run `git pull` before beginning work on any task; upon autonomous verification, commit with descriptive messages and push via `git push`.";
        }

        if (str_contains($lower, 'i18n') || str_contains($lower, 'lang') || str_contains($lower, 'translate')) {
            $rules[] = "- **Mandatory Tri-Lingual Standard (EN, TR, DE):** Zero hardcoded strings. All user-facing labels and messages must resolve dynamically across English, Turkish, and German language dictionaries.";
        }

        $lines = preg_split('/[\r\n,;]+/', $raw);
        $customSpecifics = [];
        foreach ($lines as $ln) {
            $cleanLn = trim($ln);
            if (mb_strlen($cleanLn) > 5 && !preg_match('/^(css|gutenberg|flutter|codeigniter|git)/i', $cleanLn)) {
                $customSpecifics[] = "- **Special Project Constraint:** " . htmlspecialchars($cleanLn, ENT_QUOTES, 'UTF-8');
            }
        }
        if (!empty($customSpecifics)) {
            $rules = array_merge($rules, $customSpecifics);
        }

        return implode("\n", $rules);
    }
}
