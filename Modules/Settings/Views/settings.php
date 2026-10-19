<?php
// TaskManager Gelişmiş Sistem Ayarları & API Yapılandırma Paneli (Enterprise System Settings)
$system->title = __('Settings::settings_title');
include dirname(__DIR__, 3) . '/Core/Views/header.php';

$settingsController = new \Modules\Settings\Controllers\SettingsController();
$settingsData = $settingsController->index();
if (!$settingsData['isAuthorized']) {
    echo '<div class="cu-unauthorized-box">';
    echo '<h2>' . __('Settings::settings_unauthorized_title') . '</h2>';
    echo '<p>' . __('Settings::settings_unauthorized_desc') . '</p>';
    echo '<a href="' . baseUrl('board') . '" class="cu-btn cu-btn-primary cu-unauthorized-btn">' . __('Settings::settings_btn_back_board') . '</a>';
    echo '</div>';
    include dirname(__DIR__, 3) . '/Core/Views/footer.php';
    exit;
}

// Mevcut Ayarlar (Model & Controller üzerinden)
$s = $settingsData['s'];
$title = $s->title ?? 'TaskManager Pro';
$siteurl = $s->siteurl ?? siteUrl();
$employeeCanCreate = (int)($s->employee_can_create_tasks ?? 1);
$employeeCanAi = (int)($s->employee_can_ai_tasks ?? 0);
$defaultLanguage = $s->default_language ?? 'tr';

// AI
$activeAiProvider = $s->active_ai_provider ?? 'gemini';
$defaultAiModel = $s->default_ai_model ?? 'gemini-1.5-pro';
$openaiApiKey = $s->openai_api_key ?? '';
$anthropicApiKey = $s->anthropic_api_key ?? '';
$geminiApiKey = $s->gemini_api_key ?? '';
$deepseekApiKey = $s->deepseek_api_key ?? '';
$aiSystemPromptPrefix = $s->ai_system_prompt_prefix ?? '';

// Bildirim
$telegramBotToken = $s->telegram_bot_token ?? '';
$telegramChatId = $s->telegram_chat_id ?? '';
$telegramNotifications = (int)($s->telegram_notifications_enabled ?? 1);
$slackWebhook = $s->slack_webhook_url ?? '';
$discordWebhook = $s->discord_webhook_url ?? '';

// SMTP
$smtpHost = $s->smtp_host ?? '';
$smtpPort = (int)($s->smtp_port ?? 587);
$smtpSecure = $s->smtp_secure ?? 'tls';
$smtpUser = $s->smtp_user ?? '';
$smtpPass = $s->smtp_pass ?? '';
$smtpFromEmail = $s->smtp_from_email ?? '';
$smtpFromName = $s->smtp_from_name ?? 'TaskManager';

// Entegrasyon
$bugherdApiKey = $s->bugherd_api_key ?? '';
$bugherdProjectId = $s->bugherd_project_id ?? '';
$githubToken = $s->github_token ?? '';
$githubRepo = $s->github_repo ?? '';

// Güvenlik
$sessionTimeout = (int)($s->session_timeout_minutes ?? 120);
$maxFileUpload = (int)($s->max_file_upload_mb ?? 25);
$allowedFileTypes = $s->allowed_file_types ?? 'jpg,png,pdf,zip,docx,xlsx,txt';
?>

<div class="cu-content-scroll">
    <div class="cu-settings-container">
        <!-- SAYFA BAŞLIĞI -->
        <div class="cu-settings-header">
            <div class="cu-settings-header-left">
                <div class="cu-settings-header-icon">
                    🛠️
                </div>
                <div>
                    <h1 class="cu-settings-header-title"><?= __('Settings::settings_header_title') ?></h1>
                    <p class="cu-settings-header-desc"><?= __('Settings::settings_header_desc') ?></p>
                </div>
            </div>

            <div class="cu-settings-header-actions">
                <a href="/taskmanager/board" class="cu-btn-sm cu-settings-btn-back">
                    <?= __('Settings::settings_btn_back_board') ?>
                </a>
                <button type="button" id="btnSaveSettingsTop" class="cu-btn cu-btn-primary cu-settings-btn-save">
                    <span>💾</span>
                    <span><?= __('Settings::settings_btn_save') ?></span>
                </button>
            </div>
        </div>

        <!-- AYARLAR YATAY SEKMELERİ (TABS) -->
        <div class="cu-settings-tabs-bar">
            <button type="button" data-tab="tab-general" class="cu-settings-tab-btn active">
                <span>👥</span> <span><?= __('Settings::settings_tab_general') ?></span>
            </button>
            <button type="button" data-tab="tab-ai" class="cu-settings-tab-btn">
                <span>🤖</span> <span><?= __('Settings::settings_tab_ai') ?></span>
            </button>
            <button type="button" data-tab="tab-webhooks" class="cu-settings-tab-btn">
                <span>🔔</span> <span><?= __('Settings::settings_tab_webhooks') ?></span>
            </button>
            <button type="button" data-tab="tab-email" class="cu-settings-tab-btn">
                <span>✉️</span> <span><?= __('Settings::settings_tab_email') ?></span>
            </button>
            <button type="button" data-tab="tab-integrations" class="cu-settings-tab-btn">
                <span>🔗</span> <span><?= __('Settings::settings_tab_integrations') ?></span>
            </button>
            <button type="button" data-tab="tab-security" class="cu-settings-tab-btn">
                <span>🛡️</span> <span><?= __('Settings::settings_tab_security') ?></span>
            </button>
            <button type="button" id="btnTabModules" data-tab="tab-modules" class="cu-settings-tab-btn">
                <span>📦</span> <span><?= __('Settings::settings_tab_modules') ?></span>
            </button>
        </div>

        <!-- FORM BAŞLANGICI -->
        <form id="formSystemSettings" class="cu-settings-form">

            <!-- 1. SEKME: GENEL & İZİNLER -->
            <div id="tab-general" class="cu-settings-tab-pane active">
                <div class="cu-settings-section-card">
                    <div class="cu-settings-card-header">
                        <span class="cu-settings-card-icon">👥</span>
                        <div>
                            <h3 class="cu-settings-card-title"><?= __('Settings::settings_emp_permissions') ?></h3>
                            <p class="cu-settings-card-sub"><?= __('Settings::settings_emp_permissions_sub') ?></p>
                        </div>
                    </div>

                    <div class="cu-settings-stack-14">
                        <!-- AYAR 1: ÇALIŞANLAR GÖREV AÇABİLSİN -->
                        <div class="cu-settings-switch-row">
                            <div class="cu-settings-switch-info">
                                <label for="switchEmployeeCreate" class="cu-settings-switch-label">
                                    <span>➕</span> <?= __('Settings::settings_emp_can_create') ?>
                                </label>
                                <p class="cu-settings-switch-desc">
                                    <?= __('Settings::settings_emp_can_create_sub') ?>
                                </p>
                            </div>
                            <label class="cu-toggle-switch">
                                <input type="checkbox" id="switchEmployeeCreate" name="employee_can_create_tasks" value="1" <?= $employeeCanCreate ? 'checked' : '' ?>>
                                <span class="cu-toggle-slider"></span>
                            </label>
                        </div>

                        <!-- AYAR 2: ÇALIŞANLAR AI OTONOM GÖREV AÇABİLSİN -->
                        <div class="cu-settings-switch-row">
                            <div class="cu-settings-switch-info">
                                <label for="switchEmployeeAi" class="cu-settings-switch-label">
                                    <span>🤖</span> <?= __('Settings::settings_emp_can_ai') ?>
                                </label>
                                <p class="cu-settings-switch-desc">
                                    <?= __('Settings::settings_emp_can_ai_sub') ?>
                                </p>
                            </div>
                            <label class="cu-toggle-switch">
                                <input type="checkbox" id="switchEmployeeAi" name="employee_can_ai_tasks" value="1" <?= $employeeCanAi ? 'checked' : '' ?>>
                                <span class="cu-toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- GENEL SİSTEM BİLGİLERİ -->
                <div class="cu-settings-section-card">
                    <div class="cu-settings-card-header">
                        <span class="cu-settings-card-icon">⚙️</span>
                        <div>
                            <h3 class="cu-settings-card-title"><?= __('Settings::settings_app_info') ?></h3>
                            <p class="cu-settings-card-sub"><?= __('Settings::settings_app_info_sub') ?></p>
                        </div>
                    </div>

                    <div class="cu-settings-grid-2">
                        <div class="cu-form-group">
                            <label class="cu-settings-field-label"><?= __('Settings::settings_app_title') ?></label>
                            <input type="text" name="title" value="<?= htmlspecialchars($title) ?>" class="cu-form-control" required>
                        </div>
                        <div class="cu-form-group">
                            <label class="cu-settings-field-label"><?= __('Settings::settings_app_url') ?></label>
                            <input type="text" name="siteurl" value="<?= htmlspecialchars($siteurl) ?>" class="cu-form-control" required>
                        </div>
                        <div class="cu-form-group">
                            <label class="cu-settings-field-label-flex">
                                <span>🌐</span> <span><?= __('Settings::settings_default_lang') ?></span>
                            </label>
                            <select name="default_language" id="selectDefaultLanguage" class="cu-form-control">
                                <option value="tr" <?= ($defaultLanguage === 'tr') ? 'selected' : '' ?>>🇹🇷 Türkçe</option>
                                <option value="en" <?= ($defaultLanguage === 'en') ? 'selected' : '' ?>>🇬🇧 English</option>
                                <option value="de" <?= ($defaultLanguage === 'de') ? 'selected' : '' ?>>🇩🇪 Deutsch</option>
                            </select>
                            <p class="cu-settings-field-hint"><?= __('Settings::settings_default_lang_sub') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. SEKME: YAPAY ZEKA (AI / LLM API) -->
            <div id="tab-ai" class="cu-settings-tab-pane">
                <div class="cu-settings-section-card">
                    <div class="cu-settings-card-header">
                        <span class="cu-settings-card-icon">🤖</span>
                        <div>
                            <h3 class="cu-settings-card-title"><?= __('Settings::settings_ai_provider_heading') ?></h3>
                            <p class="cu-settings-card-sub"><?= __('Settings::settings_ai_provider_sub') ?></p>
                        </div>
                    </div>

                    <div class="cu-settings-grid-2">
                        <div class="cu-form-group">
                            <label class="cu-settings-field-label"><?= __('Settings::settings_ai_active_provider') ?></label>
                            <select name="active_ai_provider" class="cu-form-control">
                                <option value="gemini" <?= $activeAiProvider === 'gemini' ? 'selected' : '' ?>>🌟 Google Gemini</option>
                                <option value="openai" <?= $activeAiProvider === 'openai' ? 'selected' : '' ?>>🟢 OpenAI (GPT-4o)</option>
                                <option value="claude" <?= $activeAiProvider === 'claude' ? 'selected' : '' ?>>🟣 Anthropic Claude</option>
                                <option value="deepseek" <?= $activeAiProvider === 'deepseek' ? 'selected' : '' ?>>🔵 DeepSeek</option>
                            </select>
                        </div>

                        <div class="cu-form-group">
                            <label class="cu-settings-field-label"><?= __('Settings::settings_ai_model_name') ?></label>
                            <input type="text" name="default_ai_model" value="<?= htmlspecialchars($defaultAiModel) ?>" class="cu-form-control">
                        </div>
                    </div>

                    <!-- API ANAHTARLARI GRİDİ -->
                    <div>
                        <h4 class="cu-settings-card-title"><?= __('Settings::settings_ai_keys_heading') ?></h4>
                        <div class="cu-settings-grid-2">
                            
                            <!-- Google Gemini API Key -->
                            <div class="cu-form-group">
                                <label class="cu-settings-field-label-between">
                                    <span>Google Gemini API Key</span>
                                    <span><?= __('Settings::settings_ai_active_badge') ?></span>
                                </label>
                                <div class="cu-password-input-wrap">
                                    <input type="password" id="input_gemini_api_key" name="gemini_api_key" value="<?= htmlspecialchars($geminiApiKey) ?>" class="cu-form-control">
                                    <button type="button" class="cu-toggle-pwd-btn" data-target="input_gemini_api_key">👁️</button>
                                </div>
                            </div>

                            <!-- OpenAI API Key -->
                            <div class="cu-form-group">
                                <label class="cu-settings-field-label">OpenAI API Key</label>
                                <div class="cu-password-input-wrap">
                                    <input type="password" id="input_openai_api_key" name="openai_api_key" value="<?= htmlspecialchars($openaiApiKey) ?>" class="cu-form-control">
                                    <button type="button" class="cu-toggle-pwd-btn" data-target="input_openai_api_key">👁️</button>
                                </div>
                            </div>

                            <!-- Anthropic Claude API Key -->
                            <div class="cu-form-group">
                                <label class="cu-settings-field-label">Anthropic Claude API Key</label>
                                <div class="cu-password-input-wrap">
                                    <input type="password" id="input_anthropic_api_key" name="anthropic_api_key" value="<?= htmlspecialchars($anthropicApiKey) ?>" class="cu-form-control">
                                    <button type="button" class="cu-toggle-pwd-btn" data-target="input_anthropic_api_key">👁️</button>
                                </div>
                            </div>

                            <!-- DeepSeek API Key -->
                            <div class="cu-form-group">
                                <label class="cu-settings-field-label">DeepSeek API Key</label>
                                <div class="cu-password-input-wrap">
                                    <input type="password" id="input_deepseek_api_key" name="deepseek_api_key" value="<?= htmlspecialchars($deepseekApiKey) ?>" class="cu-form-control">
                                    <button type="button" class="cu-toggle-pwd-btn" data-target="input_deepseek_api_key">👁️</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SİSTEM PROMPT ÖN EKİ -->
                    <div>
                        <label class="cu-settings-field-label">
                            <?= __('Settings::settings_ai_prompt_prefix') ?>
                        </label>
                        <textarea name="ai_system_prompt_prefix" rows="3" class="cu-form-control cu-settings-textarea-monospace"><?= htmlspecialchars($aiSystemPromptPrefix) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- 3. SEKME: BİLDİRİM & WEBHOOK -->
            <div id="tab-webhooks" class="cu-settings-tab-pane">
                <div class="cu-settings-section-card">
                    <div class="cu-settings-card-header">
                        <span class="cu-settings-card-icon">✈️</span>
                        <div>
                            <h3 class="cu-settings-card-title"><?= __('Settings::settings_telegram_title') ?></h3>
                            <p class="cu-settings-card-sub"><?= __('Settings::settings_telegram_sub') ?></p>
                        </div>
                    </div>

                    <div class="cu-settings-switch-row">
                        <div>
                            <span class="cu-settings-switch-label"><?= __('Settings::settings_telegram_enable') ?></span>
                            <div class="cu-settings-switch-desc"><?= __('Settings::settings_telegram_enable_sub') ?></div>
                        </div>
                        <label class="cu-toggle-switch">
                            <input type="checkbox" name="telegram_notifications_enabled" value="1" <?= $telegramNotifications ? 'checked' : '' ?>>
                            <span class="cu-toggle-slider"></span>
                        </label>
                    </div>

                    <div class="cu-settings-grid-2">
                        <div class="cu-form-group">
                            <label class="cu-settings-field-label"><?= __('Settings::settings_telegram_token') ?></label>
                            <div class="cu-password-input-wrap">
                                <input type="password" id="input_telegram_bot_token" name="telegram_bot_token" value="<?= htmlspecialchars($telegramBotToken) ?>" class="cu-form-control">
                                <button type="button" class="cu-toggle-pwd-btn" data-target="input_telegram_bot_token">👁️</button>
                            </div>
                        </div>

                        <div class="cu-form-group">
                            <label class="cu-settings-field-label"><?= __('Settings::settings_telegram_chat_id') ?></label>
                            <input type="text" name="telegram_chat_id" value="<?= htmlspecialchars($telegramChatId) ?>" class="cu-form-control">
                        </div>
                    </div>

                    <!-- TEST BAĞLANTISI VE LOCALHOST BİLGİ KUTUSU -->
                    <div class="cu-settings-info-banner">
                        <div>
                            <strong class="cu-settings-banner-title">
                                <span>💡</span> <span><?= __('Settings::settings_telegram_model_title') ?></span>
                            </strong>
                            <div class="cu-settings-banner-desc">
                                <?= __('Settings::settings_telegram_model_desc') ?>
                            </div>
                        </div>
                        <button type="button" id="btnTestTelegram" class="cu-btn-test-action cu-btn-test-primary">
                            <span>📡</span> <span><?= __('Settings::settings_telegram_test_btn') ?></span>
                        </button>
                    </div>
                </div>

                <!-- SLACK & DISCORD WEBHOOKS -->
                <div class="cu-settings-section-card">
                    <div class="cu-settings-card-header">
                        <span class="cu-settings-card-icon">💬</span>
                        <div>
                            <h3 class="cu-settings-card-title"><?= __('Settings::settings_slack_discord_title') ?></h3>
                            <p class="cu-settings-card-sub"><?= __('Settings::settings_slack_discord_sub') ?></p>
                        </div>
                    </div>

                    <div class="cu-settings-grid-2">
                        <div class="cu-form-group">
                            <label class="cu-settings-field-label"><?= __('Settings::settings_slack_url') ?></label>
                            <input type="text" name="slack_webhook_url" value="<?= htmlspecialchars($slackWebhook) ?>" class="cu-form-control">
                        </div>

                        <div class="cu-form-group">
                            <label class="cu-settings-field-label"><?= __('Settings::settings_discord_url') ?></label>
                            <input type="text" name="discord_webhook_url" value="<?= htmlspecialchars($discordWebhook) ?>" class="cu-form-control">
                        </div>
                    </div>

                    <div class="cu-settings-test-actions-bar">
                        <button type="button" id="btnTestSlack" class="cu-btn-test-action cu-btn-test-slack">
                            <span>💬</span> <span><?= __('Settings::settings_test_slack') ?></span>
                        </button>
                        <button type="button" id="btnTestDiscord" class="cu-btn-test-action cu-btn-test-discord">
                            <span>🎮</span> <span><?= __('Settings::settings_test_discord') ?></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- 4. SEKME: E-POSTA (SMTP) -->
            <div id="tab-email" class="cu-settings-tab-pane">
                <div class="cu-settings-section-card">
                    <div class="cu-settings-card-header">
                        <span class="cu-settings-card-icon">✉️</span>
                        <div>
                            <h3 class="cu-settings-card-title"><?= __('Settings::settings_smtp_title') ?></h3>
                            <p class="cu-settings-card-sub"><?= __('Settings::settings_smtp_sub') ?></p>
                        </div>
                    </div>

                    <div class="cu-settings-grid-3">
                        <div class="cu-form-group">
                            <label class="cu-settings-field-label"><?= __('Settings::settings_smtp_host') ?></label>
                            <input type="text" name="smtp_host" value="<?= htmlspecialchars($smtpHost) ?>" class="cu-form-control">
                        </div>
                        <div class="cu-form-group">
                            <label class="cu-settings-field-label"><?= __('Settings::settings_smtp_port') ?></label>
                            <input type="number" name="smtp_port" value="<?= htmlspecialchars((string)$smtpPort) ?>" class="cu-form-control">
                        </div>
                        <div class="cu-form-group">
                            <label class="cu-settings-field-label"><?= __('Settings::settings_smtp_security') ?></label>
                            <select name="smtp_secure" class="cu-form-control">
                                <option value="tls" <?= $smtpSecure === 'tls' ? 'selected' : '' ?>>TLS (Port 587)</option>
                                <option value="ssl" <?= $smtpSecure === 'ssl' ? 'selected' : '' ?>>SSL (Port 465)</option>
                                <option value="none" <?= $smtpSecure === 'none' ? 'selected' : '' ?>><?= __('Settings::settings_smtp_sec_none') ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="cu-settings-grid-2">
                        <div class="cu-form-group">
                            <label class="cu-settings-field-label"><?= __('Settings::settings_smtp_user') ?></label>
                            <input type="text" name="smtp_user" value="<?= htmlspecialchars($smtpUser) ?>" class="cu-form-control">
                        </div>
                        <div class="cu-form-group">
                            <label class="cu-settings-field-label"><?= __('Settings::settings_smtp_pass') ?></label>
                            <div class="cu-password-input-wrap">
                                <input type="password" id="input_smtp_pass" name="smtp_pass" value="<?= htmlspecialchars($smtpPass) ?>" class="cu-form-control">
                                <button type="button" class="cu-toggle-pwd-btn" data-target="input_smtp_pass">👁️</button>
                            </div>
                        </div>
                    </div>

                    <div class="cu-settings-grid-2">
                        <div class="cu-form-group">
                            <label class="cu-settings-field-label"><?= __('Settings::settings_smtp_from_email') ?></label>
                            <input type="email" name="smtp_from_email" value="<?= htmlspecialchars($smtpFromEmail) ?>" class="cu-form-control">
                        </div>
                        <div class="cu-form-group">
                            <label class="cu-settings-field-label"><?= __('Settings::settings_smtp_from_name') ?></label>
                            <input type="text" name="smtp_from_name" value="<?= htmlspecialchars($smtpFromName) ?>" class="cu-form-control">
                        </div>
                    </div>

                    <div class="cu-settings-test-actions-bar">
                        <button type="button" id="btnTestSmtp" class="cu-btn-test-action cu-btn-test-primary">
                            <span>✉️</span> <span><?= __('Settings::settings_smtp_test_btn') ?></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- 5. SEKME: ENTEGRASYONLAR -->
            <div id="tab-integrations" class="cu-settings-tab-pane">
                <div class="cu-settings-section-card">
                    <div class="cu-settings-card-header">
                        <span class="cu-settings-card-icon">🐞</span>
                        <div>
                            <h3 class="cu-settings-card-title"><?= __('Settings::settings_bugherd_title') ?></h3>
                            <p class="cu-settings-card-sub"><?= __('Settings::settings_bugherd_sub') ?></p>
                        </div>
                    </div>

                    <div class="cu-settings-grid-2">
                        <div class="cu-form-group">
                            <label class="cu-settings-field-label"><?= __('Settings::settings_bugherd_api_key') ?></label>
                            <div class="cu-password-input-wrap">
                                <input type="password" id="input_bugherd_api_key" name="bugherd_api_key" value="<?= htmlspecialchars($bugherdApiKey) ?>" class="cu-form-control">
                                <button type="button" class="cu-toggle-pwd-btn" data-target="input_bugherd_api_key">👁️</button>
                            </div>
                        </div>
                        <div class="cu-form-group">
                            <label class="cu-settings-field-label"><?= __('Settings::settings_bugherd_project_id') ?></label>
                            <input type="text" name="bugherd_project_id" value="<?= htmlspecialchars($bugherdProjectId) ?>" class="cu-form-control">
                        </div>
                    </div>
                </div>

                <!-- GITHUB ENTEGRASYONU -->
                <div class="cu-settings-section-card">
                    <div class="cu-settings-card-header">
                        <span class="cu-settings-card-icon">🐙</span>
                        <div>
                            <h3 class="cu-settings-card-title"><?= __('Settings::settings_github_title') ?></h3>
                            <p class="cu-settings-card-sub"><?= __('Settings::settings_github_sub') ?></p>
                        </div>
                    </div>

                    <div class="cu-settings-grid-2">
                        <div class="cu-form-group">
                            <label class="cu-settings-field-label"><?= __('Settings::settings_github_token') ?></label>
                            <div class="cu-password-input-wrap">
                                <input type="password" id="input_github_token" name="github_token" value="<?= htmlspecialchars($githubToken) ?>" class="cu-form-control">
                                <button type="button" class="cu-toggle-pwd-btn" data-target="input_github_token">👁️</button>
                            </div>
                        </div>
                        <div class="cu-form-group">
                            <label class="cu-settings-field-label"><?= __('Settings::settings_github_repo') ?></label>
                            <input type="text" name="github_repo" value="<?= htmlspecialchars($githubRepo) ?>" class="cu-form-control">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 6. SEKME: GÜVENLİK & DEPOLAMA -->
            <div id="tab-security" class="cu-settings-tab-pane">
                <div class="cu-settings-section-card">
                    <div class="cu-settings-card-header">
                        <span class="cu-settings-card-icon">🛡️</span>
                        <div>
                            <h3 class="cu-settings-card-title"><?= __('Settings::settings_security_title') ?></h3>
                            <p class="cu-settings-card-sub"><?= __('Settings::settings_security_sub') ?></p>
                        </div>
                    </div>

                    <div class="cu-settings-grid-2">
                        <div class="cu-form-group">
                            <label class="cu-settings-field-label"><?= __('Settings::settings_session_timeout') ?></label>
                            <input type="number" name="session_timeout_minutes" value="<?= htmlspecialchars((string)$sessionTimeout) ?>" class="cu-form-control">
                            <small class="cu-settings-field-hint"><?= __('Settings::settings_session_timeout_hint') ?></small>
                        </div>

                        <div class="cu-form-group">
                            <label class="cu-settings-field-label"><?= __('Settings::settings_max_upload') ?></label>
                            <input type="number" name="max_file_upload_mb" value="<?= htmlspecialchars((string)$maxFileUpload) ?>" class="cu-form-control">
                            <small class="cu-settings-field-hint"><?= __('Settings::settings_max_upload_hint') ?></small>
                        </div>

                        <div class="cu-form-group cu-settings-full-col">
                            <label class="cu-settings-field-label"><?= __('Settings::settings_allowed_files') ?></label>
                            <input type="text" name="allowed_file_types" value="<?= htmlspecialchars($allowedFileTypes) ?>" class="cu-form-control">
                            <small class="cu-settings-field-hint"><?= __('Settings::settings_allowed_files_hint') ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 7. SEKME: MODÜL YÖNETİCİSİ (HMVC) -->
            <div id="tab-modules" class="cu-settings-tab-pane">
                <div class="cu-modules-wrapper-card">
                    <div class="cu-modules-header-bar">
                        <div>
                            <h3 class="cu-modules-title">
                                <span>📦</span> <?= __('Settings::settings_modules_title') ?>
                            </h3>
                            <p class="cu-modules-sub">
                                <?= __('Settings::settings_modules_desc') ?>
                            </p>
                        </div>
                        <?php 
                        $availableModules = $settingsData['availableModules'] ?? [];
                        $totalModules = count($availableModules);
                        $activeCount = count(array_filter($availableModules, fn($m) => !empty($m['is_active'])));
                        $passiveCount = $totalModules - $activeCount;
                        ?>
                        <div class="cu-modules-badges">
                            <span class="cu-mod-badge-total">
                                <?= __('Settings::settings_modules_count_badge') ?>: <?= $totalModules ?>
                            </span>
                            <span class="cu-mod-badge-active">
                                <?= __('Settings::settings_modules_active_badge') ?>: <?= $activeCount ?>
                            </span>
                            <span class="cu-mod-badge-passive">
                                <?= __('Settings::settings_modules_passive_badge') ?>: <?= $passiveCount ?>
                            </span>
                            <button type="button" id="btnRunSmokeTestSettings" class="cu-btn-sm cu-btn-secondary cu-smoketest-trigger-btn">
                                <span>🔍</span> <span><?= __('SmokeTest::btn_run_smoketest') ?></span>
                            </button>
                        </div>
                    </div>


                    <!-- Modül Listesi Tablosu / Kartları -->
                    <div class="cu-modules-list">
                        <?php if (empty($availableModules)): ?>
                            <div class="cu-modules-empty">
                                <?= __('Settings::module_desc') ?>
                            </div>
                        <?php else: ?>
                            <?php foreach ($availableModules as $mod): 
                                $modSlug = htmlspecialchars($mod['slug'] ?? '');
                                $modName = htmlspecialchars($mod['name'] ?? $modSlug);
                                $modDesc = htmlspecialchars($mod['description'] ?? '');
                                $modVer  = htmlspecialchars($mod['version'] ?? '1.0.0');
                                $modAuthor = htmlspecialchars($mod['author'] ?? 'TaskManager Team');
                                $modActive = !empty($mod['is_active']);
                                $modDeps = !empty($mod['dependencies']) ? implode(', ', $mod['dependencies']) : '—';
                            ?>
                            <div class="cu-module-item-card <?= $modActive ? 'is-active' : '' ?>">
                                <div class="cu-module-info">
                                    <div class="cu-module-heading-line">
                                        <span class="cu-module-icon">🧩</span>
                                        <h4 class="cu-module-name">
                                            <?= $modName ?>
                                        </h4>
                                        <span class="cu-module-version">
                                            v<?= $modVer ?>
                                        </span>
                                        <span class="cu-module-slug">
                                            (<?= $modSlug ?>)
                                        </span>
                                    </div>
                                    <p class="cu-module-desc">
                                        <?= $modDesc ?>
                                    </p>
                                    <div class="cu-module-meta">
                                        <span>👤 <strong><?= __('Settings::settings_modules_col_version') ?>:</strong> <?= $modAuthor ?></span>
                                        <span>🔗 <strong><?= __('Settings::settings_modules_col_deps') ?>:</strong> <?= $modDeps ?></span>
                                    </div>
                                </div>

                                <div class="cu-module-actions">
                                    <span id="badge-status-<?= $modSlug ?>" class="cu-module-status-badge <?= $modActive ? 'status-active' : 'status-passive' ?>">
                                        <?= $modActive ? __('Settings::settings_modules_active_badge') : __('Settings::settings_modules_passive_badge') ?>
                                    </span>
                                    <label class="cu-toggle-switch" title="<?= __('Settings::settings_modules_col_status') ?>">
                                        <input type="checkbox" id="toggle-mod-<?= $modSlug ?>" class="cu-mod-toggle-input" data-slug="<?= $modSlug ?>" <?= $modActive ? 'checked' : '' ?>>
                                        <span class="cu-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- KAYDET BUTONLARI (ALT) -->
            <div class="cu-settings-bottom-actions">
                <a href="<?= baseUrl('board') ?>" class="cu-btn-sm cu-settings-btn-cancel-bottom">
                    <?= __('Settings::settings_btn_cancel') ?>
                </a>
                <button type="submit" id="btnSaveSettingsBottom" class="cu-btn cu-btn-primary cu-settings-btn-save-bottom">
                    <span>💾</span>
                    <span><?= __('Settings::settings_btn_save') ?></span>
                </button>
            </div>

        </form>
    </div>
</div>

<?php include dirname(__DIR__, 3) . '/Core/Views/footer.php'; ?>
