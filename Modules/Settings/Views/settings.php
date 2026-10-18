<?php
// TaskManager Gelişmiş Sistem Ayarları & API Yapılandırma Paneli (Enterprise System Settings)
$system->title = __('app.settings_title', 'Sistem & API Ayarları - TaskManager');
include dirname(__DIR__, 3) . '/Core/Views/header.php';

$settingsController = new \Modules\Settings\Controllers\SettingsController();
$settingsData = $settingsController->index();
if (!$settingsData['isAuthorized']) {
    echo '<div style="padding:40px; text-align:center; color:var(--text-muted);">';
    echo '<h2>' . __('app.settings_unauthorized_title', '🔒 Yetkisiz Erişim') . '</h2>';
    echo '<p>' . __('app.settings_unauthorized_desc', 'Sistem ayarlarına yalnızca yönetici rolündeki kullanıcılar erişebilir.') . '</p>';
    echo '<a href="/taskmanager/board" class="cu-btn cu-btn-primary" style="display:inline-block; margin-top:16px;">' . __('app.settings_btn_back_board', 'Panoya Dön') . '</a>';
    echo '</div>';
    include dirname(__DIR__, 3) . '/Core/Views/footer.php';
    exit;
}

// Mevcut Ayarlar (Model & Controller üzerinden)
$s = $settingsData['s'];
$title = $s->title ?? 'TaskManager Pro';
$siteurl = $s->siteurl ?? 'http://localhost/taskmanager';
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

<div class="cu-content-scroll" style="flex:1; overflow-y:auto; overflow-x:auto; width:100%; box-sizing:border-box; padding:24px 20px;">
    <div style="max-width:1100px; margin:0 auto; width:100%; box-sizing:border-box;">
        <!-- SAYFA BAŞLIĞI -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:44px; height:44px; border-radius:12px; background:linear-gradient(135deg, var(--brand-primary), #6C5CE7); display:flex; align-items:center; justify-content:center; font-size:22px; box-shadow:0 4px 14px rgba(123,104,238,0.35);">
                🛠️
            </div>
            <div>
                <h1 style="font-size:20px; font-weight:800; color:var(--text-white); margin:0;"><?= __('app.settings_header_title', 'Sistem & API Ayarları') ?></h1>
                <p style="font-size:12.5px; color:var(--text-muted); margin:3px 0 0 0;"><?= __('app.settings_header_desc', 'Yapay zeka motorları, webhooklar, SMTP ve sistem parametreleri') ?></p>
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:10px;">
            <a href="/taskmanager/board" class="cu-btn-sm" style="display:inline-flex; align-items:center; gap:6px; padding:8px 14px; text-decoration:none; background:transparent; border:1px solid var(--border-subtle); color:var(--text-primary); border-radius:8px;">
                <?= __('app.settings_btn_back_board', 'Panoya Dön') ?>
            </a>
            <button type="button" onclick="handleSaveSettings(event)" id="btnSaveSettingsTop" class="cu-btn cu-btn-primary" style="display:inline-flex; align-items:center; gap:8px; padding:9px 20px; font-size:13px; font-weight:600; border-radius:8px; box-shadow:0 4px 14px rgba(123,104,238,0.35);">
                <span>💾</span>
                <span><?= __('app.settings_btn_save', 'Değişiklikleri Kaydet') ?></span>
            </button>
        </div>
    </div>

    <!-- AYARLAR YATAY SEKMELERİ (TABS) -->
    <div class="cu-settings-tabs-bar" style="display:flex; gap:6px; overflow-x:auto; -webkit-overflow-scrolling:touch; padding-bottom:12px; border-bottom:1px solid var(--border-subtle); margin-bottom:20px;">
        <button type="button" onclick="switchSettingsTab('tab-general', this)" class="cu-settings-tab-btn active">
            <span>👥</span> <span><?= __('app.settings_tab_general', 'Genel & İzinler') ?></span>
        </button>
        <button type="button" onclick="switchSettingsTab('tab-ai', this)" class="cu-settings-tab-btn">
            <span>🤖</span> <span><?= __('app.settings_tab_ai', 'Yapay Zeka (AI / LLM)') ?></span>
        </button>
        <button type="button" onclick="switchSettingsTab('tab-webhooks', this)" class="cu-settings-tab-btn">
            <span>🔔</span> <span><?= __('app.settings_tab_webhooks', 'Bildirim & Webhook') ?></span>
        </button>
        <button type="button" onclick="switchSettingsTab('tab-email', this)" class="cu-settings-tab-btn">
            <span>✉️</span> <span><?= __('app.settings_tab_email', 'E-Posta (SMTP)') ?></span>
        </button>
        <button type="button" onclick="switchSettingsTab('tab-integrations', this)" class="cu-settings-tab-btn">
            <span>🔗</span> <span><?= __('app.settings_tab_integrations', 'Entegrasyonlar') ?></span>
        </button>
        <button type="button" onclick="switchSettingsTab('tab-security', this)" class="cu-settings-tab-btn">
            <span>🛡️</span> <span><?= __('app.settings_tab_security', 'Güvenlik & Depolama') ?></span>
        </button>
        <button type="button" id="btnTabModules" onclick="switchSettingsTab('tab-modules', this)" class="cu-settings-tab-btn">
            <span>📦</span> <span><?= __('app.settings_tab_modules', 'Modül Yöneticisi (HMVC)') ?></span>
        </button>
    </div>

    <!-- FORM BAŞLANGICI -->
    <form id="formSystemSettings" onsubmit="handleSaveSettings(event)" style="display:flex; flex-direction:column; gap:20px;">

        <!-- 1. SEKME: GENEL & İZİNLER -->
        <div id="tab-general" class="cu-settings-tab-pane active">
            <div style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:22px; box-shadow:var(--shadow-card); margin-bottom:20px;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid var(--border-subtle);">
                    <span style="font-size:18px;">👥</span>
                    <div>
                        <h3 style="font-size:15px; font-weight:700; color:var(--text-white); margin:0;"><?= __('app.settings_emp_permissions', 'Görev & Çalışan İzinleri') ?></h3>
                        <p style="font-size:11.5px; color:var(--text-muted); margin:2px 0 0 0;"><?= __('app.settings_emp_permissions_sub', 'Yönetici dışındaki çalışanların yetkileri') ?></p>
                    </div>
                </div>

                <div style="display:flex; flex-direction:column; gap:14px;">
                    <!-- AYAR 1: ÇALIŞANLAR GÖREV AÇABİLSİN -->
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:14px; background:var(--bg-app); border:1px solid var(--border-subtle); border-radius:var(--radius-sm);">
                        <div style="flex:1; padding-right:20px;">
                            <label for="switchEmployeeCreate" style="font-size:13.5px; font-weight:700; color:var(--text-white); display:flex; align-items:center; gap:8px; cursor:pointer; margin-bottom:4px;">
                                <span>➕</span> <?= __('app.settings_emp_can_create', 'Çalışanlar Görev Açabilsin') ?>
                            </label>
                            <p style="font-size:12px; color:var(--text-muted); margin:0; line-height:1.45;">
                                <?= __('app.settings_emp_can_create_sub', 'Açık olduğunda çalışanlar Kanban panosundan yeni görev oluşturabilir. Kapalı olduğunda yalnızca yöneticiler görev açabilir.') ?>
                            </p>
                        </div>
                        <label class="cu-toggle-switch">
                            <input type="checkbox" id="switchEmployeeCreate" name="employee_can_create_tasks" value="1" <?= $employeeCanCreate ? 'checked' : '' ?>>
                            <span class="cu-toggle-slider"></span>
                        </label>
                    </div>

                    <!-- AYAR 2: ÇALIŞANLAR AI OTONOM GÖREV AÇABİLSİN -->
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:14px; background:var(--bg-app); border:1px solid var(--border-subtle); border-radius:var(--radius-sm);">
                        <div style="flex:1; padding-right:20px;">
                            <label for="switchEmployeeAi" style="font-size:13.5px; font-weight:700; color:var(--text-white); display:flex; align-items:center; gap:8px; cursor:pointer; margin-bottom:4px;">
                                <span>🤖</span> <?= __('app.settings_emp_can_ai', 'Çalışanlar AI Otonom Görev Açabilsin (#ai Yetkisi)') ?>
                            </label>
                            <p style="font-size:12px; color:var(--text-muted); margin:0; line-height:1.45;">
                                <?= __('app.settings_emp_can_ai_sub', 'Kapalı olduğunda çalışanların açtığı görevlerde AI otonom ajanları tetiklenmez. Yalnızca yönetici `#ai` etiketini kullanabilir.') ?>
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
            <div style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:22px; box-shadow:var(--shadow-card);">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid var(--border-subtle);">
                    <span style="font-size:18px;">⚙️</span>
                    <div>
                        <h3 style="font-size:15px; font-weight:700; color:var(--text-white); margin:0;"><?= __('app.settings_app_info', 'Uygulama Bilgileri') ?></h3>
                        <p style="font-size:11.5px; color:var(--text-muted); margin:2px 0 0 0;"><?= __('app.settings_app_info_sub', 'Başlık ve ana erişim URL adresi') ?></p>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px;">
                    <div class="cu-form-group">
                        <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;"><?= __('app.settings_app_title', 'Uygulama Başlığı') ?></label>
                        <input type="text" name="title" value="<?= htmlspecialchars($title) ?>" class="cu-form-control" required>
                    </div>
                    <div class="cu-form-group">
                        <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;"><?= __('app.settings_app_url', "Site / Sunucu URL'si") ?></label>
                        <input type="text" name="siteurl" value="<?= htmlspecialchars($siteurl) ?>" class="cu-form-control" required>
                    </div>
                    <div class="cu-form-group">
                        <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:flex; align-items:center; gap:6px;">
                            <span>🌐</span> <span><?= __('app.settings_default_lang', 'Sistem Varsayılan Dili') ?></span>
                        </label>
                        <select name="default_language" id="selectDefaultLanguage" class="cu-form-control" style="cursor:pointer;">
                            <option value="tr" <?= ($defaultLanguage === 'tr') ? 'selected' : '' ?>>🇹🇷 Türkçe</option>
                            <option value="en" <?= ($defaultLanguage === 'en') ? 'selected' : '' ?>>🇬🇧 English</option>
                            <option value="de" <?= ($defaultLanguage === 'de') ? 'selected' : '' ?>>🇩🇪 Deutsch</option>
                        </select>
                        <p style="font-size:11px; color:var(--text-muted); margin:4px 0 0 0;"><?= __('app.settings_default_lang_sub', 'Oturum açmamış veya profilinde dil tercihi bulunmayan tüm kullanıcılara varsayılan olarak uygulanır.') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. SEKME: YAPAY ZEKA (AI / LLM API) -->
        <div id="tab-ai" class="cu-settings-tab-pane">
            <div style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:22px; box-shadow:var(--shadow-card); margin-bottom:20px;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid var(--border-subtle);">
                    <span style="font-size:18px;">🤖</span>
                    <div>
                        <h3 style="font-size:15px; font-weight:700; color:var(--text-white); margin:0;"><?= __('app.settings_ai_provider_heading', 'AI Servis Sağlayıcı & Varsayılan Motor') ?></h3>
                        <p style="font-size:11.5px; color:var(--text-muted); margin:2px 0 0 0;"><?= __('app.settings_ai_provider_sub', 'Otonom görev işleme ve içerik üretimi için kullanılacak yapay zeka servisi') ?></p>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px; margin-bottom:20px;">
                    <div class="cu-form-group">
                        <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;"><?= __('app.settings_ai_active_provider', 'Aktif AI Sağlayıcı') ?></label>
                        <select name="active_ai_provider" class="cu-form-control" style="height:38px;">
                            <option value="gemini" <?= $activeAiProvider === 'gemini' ? 'selected' : '' ?>>🌟 Google Gemini (Önerilen / Varsayılan)</option>
                            <option value="openai" <?= $activeAiProvider === 'openai' ? 'selected' : '' ?>>🟢 OpenAI (GPT-4o / GPT-4o-mini)</option>
                            <option value="claude" <?= $activeAiProvider === 'claude' ? 'selected' : '' ?>>🟣 Anthropic Claude (3.5 Sonnet)</option>
                            <option value="deepseek" <?= $activeAiProvider === 'deepseek' ? 'selected' : '' ?>>🔵 DeepSeek (V3 / R1 Reasoner)</option>
                        </select>
                    </div>

                    <div class="cu-form-group">
                        <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;"><?= __('app.settings_ai_model_name', 'Varsayılan Model Adı') ?></label>
                        <input type="text" name="default_ai_model" value="<?= htmlspecialchars($defaultAiModel) ?>" placeholder="Örn: gemini-1.5-pro, gpt-4o, claude-3-5-sonnet" class="cu-form-control">
                    </div>
                </div>

                <!-- API ANAHTARLARI GRİDİ -->
                <div style="border-top:1px solid var(--border-subtle); padding-top:16px;">
                    <h4 style="font-size:13.5px; font-weight:700; color:var(--text-white); margin:0 0 14px 0;"><?= __('app.settings_ai_keys_heading', '🔑 LLM API Anahtarları (Secret Keys)') ?></h4>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px;">
                        
                        <!-- Google Gemini API Key -->
                        <div class="cu-form-group">
                            <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:flex; justify-content:space-between;">
                                <span>Google Gemini API Key</span>
                                <span style="color:#00b894; font-weight:600; font-size:11px;"><?= __('app.settings_ai_active_badge', 'Aktif') ?></span>
                            </label>
                            <div style="position:relative;">
                                <input type="password" id="input_gemini_api_key" name="gemini_api_key" value="<?= htmlspecialchars($geminiApiKey) ?>" placeholder="AIzaSy..." class="cu-form-control" style="padding-right:36px;">
                                <button type="button" onclick="togglePasswordVisibility('input_gemini_api_key', this)" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:14px;">👁️</button>
                            </div>
                        </div>

                        <!-- OpenAI API Key -->
                        <div class="cu-form-group">
                            <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;">OpenAI API Key</label>
                            <div style="position:relative;">
                                <input type="password" id="input_openai_api_key" name="openai_api_key" value="<?= htmlspecialchars($openaiApiKey) ?>" placeholder="sk-proj-..." class="cu-form-control" style="padding-right:36px;">
                                <button type="button" onclick="togglePasswordVisibility('input_openai_api_key', this)" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:14px;">👁️</button>
                            </div>
                        </div>

                        <!-- Anthropic Claude API Key -->
                        <div class="cu-form-group">
                            <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;">Anthropic Claude API Key</label>
                            <div style="position:relative;">
                                <input type="password" id="input_anthropic_api_key" name="anthropic_api_key" value="<?= htmlspecialchars($anthropicApiKey) ?>" placeholder="sk-ant-..." class="cu-form-control" style="padding-right:36px;">
                                <button type="button" onclick="togglePasswordVisibility('input_anthropic_api_key', this)" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:14px;">👁️</button>
                            </div>
                        </div>

                        <!-- DeepSeek API Key -->
                        <div class="cu-form-group">
                            <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;">DeepSeek API Key</label>
                            <div style="position:relative;">
                                <input type="password" id="input_deepseek_api_key" name="deepseek_api_key" value="<?= htmlspecialchars($deepseekApiKey) ?>" placeholder="sk-..." class="cu-form-control" style="padding-right:36px;">
                                <button type="button" onclick="togglePasswordVisibility('input_deepseek_api_key', this)" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:14px;">👁️</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SİSTEM PROMPT ÖN EKİ -->
                <div style="border-top:1px solid var(--border-subtle); padding-top:16px; margin-top:16px;">
                    <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;">
                        <?= __('app.settings_ai_prompt_prefix', '🧠 Şirket & Ajan Sistem Prompt Ön Eki (System Instructions)') ?>
                    </label>
                    <textarea name="ai_system_prompt_prefix" rows="3" placeholder="<?= htmlspecialchars(__('app.settings_ai_prompt_prefix_ph', 'Yapay zekanın tüm görev çözümlemelerinde uyacağı kurum kuralları, dil tonu veya mimari ilkeler...'), ENT_QUOTES, 'UTF-8') ?>" class="cu-form-control" style="font-family:monospace; font-size:12px;"><?= htmlspecialchars($aiSystemPromptPrefix) ?></textarea>
                </div>
            </div>
        </div>

        <!-- 3. SEKME: BİLDİRİM & WEBHOOK -->
        <div id="tab-webhooks" class="cu-settings-tab-pane">
            <div style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:22px; box-shadow:var(--shadow-card); margin-bottom:20px;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid var(--border-subtle);">
                    <span style="font-size:18px;">✈️</span>
                    <div>
                        <h3 style="font-size:15px; font-weight:700; color:var(--text-white); margin:0;"><?= __('app.settings_telegram_title', 'Telegram Bot Entegrasyonu') ?></h3>
                        <p style="font-size:11.5px; color:var(--text-muted); margin:2px 0 0 0;"><?= __('app.settings_telegram_sub', 'Yeni görevler, SLA gecikmeleri ve durum değişimleri anında Telegram kanalınıza iletilir') ?></p>
                    </div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 14px; background:var(--bg-app); border:1px solid var(--border-subtle); border-radius:var(--radius-sm); margin-bottom:16px;">
                    <div>
                        <span style="font-size:13px; font-weight:700; color:var(--text-white);"><?= __('app.settings_telegram_enable', 'Telegram Bildirimlerini Etkinleştir') ?></span>
                        <div style="font-size:11.5px; color:var(--text-muted);"><?= __('app.settings_telegram_enable_sub', 'Görev olayları bot üzerinden canlı mesaj olarak gönderilsin') ?></div>
                    </div>
                    <label class="cu-toggle-switch">
                        <input type="checkbox" name="telegram_notifications_enabled" value="1" <?= $telegramNotifications ? 'checked' : '' ?>>
                        <span class="cu-toggle-slider"></span>
                    </label>
                </div>

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px; margin-bottom:14px;">
                    <div class="cu-form-group">
                        <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;"><?= __('app.settings_telegram_token', 'Telegram Bot Token') ?></label>
                        <div style="position:relative;">
                            <input type="password" id="input_telegram_bot_token" name="telegram_bot_token" value="<?= htmlspecialchars($telegramBotToken) ?>" placeholder="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ" class="cu-form-control" style="padding-right:36px;">
                            <button type="button" onclick="togglePasswordVisibility('input_telegram_bot_token', this)" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:14px;">👁️</button>
                        </div>
                    </div>

                    <div class="cu-form-group">
                        <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;"><?= __('app.settings_telegram_chat_id', 'Telegram Grup / Chat ID') ?></label>
                        <input type="text" name="telegram_chat_id" value="<?= htmlspecialchars($telegramChatId) ?>" placeholder="-1001234567890 veya @kanaladi" class="cu-form-control">
                    </div>
                </div>

                <!-- TEST BAĞLANTISI VE LOCALHOST BİLGİ KUTUSU -->
                <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding:12px 14px; background:rgba(123, 104, 238, 0.08); border:1px solid rgba(123, 104, 238, 0.25); border-radius:var(--radius-sm); margin-bottom:10px;">
                    <div>
                        <strong style="font-size:12.5px; color:var(--text-white); display:flex; align-items:center; gap:6px;">
                            <span>💡</span> <span><?= __('app.settings_telegram_model_title', 'Localhost & Canlı Sunucu Çalışma Modeli') ?></span>
                        </strong>
                        <div style="font-size:11.5px; color:var(--text-secondary); margin-top:2px; line-height:1.4;">
                            <?= __('app.settings_telegram_model_desc') ?>
                        </div>
                    </div>
                    <button type="button" onclick="handleTestTelegram()" id="btnTestTelegram" class="cu-btn-sm" style="display:inline-flex; align-items:center; gap:6px; background:var(--brand-primary); color:#fff; border:none; padding:7px 14px; border-radius:6px; font-size:12px; font-weight:700; cursor:pointer;">
                        <span>📡</span> <span><?= __('app.settings_telegram_test_btn', 'Bağlantıyı Test Et') ?></span>
                    </button>
                </div>
            </div>

            <!-- SLACK & DISCORD WEBHOOKS -->
            <div style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:22px; box-shadow:var(--shadow-card);">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid var(--border-subtle);">
                    <span style="font-size:18px;">💬</span>
                    <div>
                        <h3 style="font-size:15px; font-weight:700; color:var(--text-white); margin:0;"><?= __('app.settings_slack_discord_title', 'Slack & Discord Webhook Kanalları') ?></h3>
                        <p style="font-size:11.5px; color:var(--text-muted); margin:2px 0 0 0;"><?= __('app.settings_slack_discord_sub', 'Ekip iletişim kanallarına giden otomatik bildirim bağlantıları') ?></p>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px;">
                    <div class="cu-form-group">
                        <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;"><?= __('app.settings_slack_url', 'Slack Incoming Webhook URL') ?></label>
                        <input type="text" name="slack_webhook_url" value="<?= htmlspecialchars($slackWebhook) ?>" placeholder="https://hooks.slack.com/services/..." class="cu-form-control">
                    </div>

                    <div class="cu-form-group">
                        <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;"><?= __('app.settings_discord_url', 'Discord Webhook URL') ?></label>
                        <input type="text" name="discord_webhook_url" value="<?= htmlspecialchars($discordWebhook) ?>" placeholder="https://discord.com/api/webhooks/..." class="cu-form-control">
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:14px; padding-top:12px; border-top:1px solid var(--border-subtle);">
                    <button type="button" onclick="handleTestWebhook('slack')" id="btnTestSlack" class="cu-btn-sm" style="display:inline-flex; align-items:center; gap:6px; background:#4A154B; color:#fff; border:none; padding:7px 14px; border-radius:6px; font-size:12px; font-weight:700; cursor:pointer;">
                        <span>💬</span> <span><?= __('app.settings_test_slack', 'Slack Test Gönder') ?></span>
                    </button>
                    <button type="button" onclick="handleTestWebhook('discord')" id="btnTestDiscord" class="cu-btn-sm" style="display:inline-flex; align-items:center; gap:6px; background:#5865F2; color:#fff; border:none; padding:7px 14px; border-radius:6px; font-size:12px; font-weight:700; cursor:pointer;">
                        <span>🎮</span> <span><?= __('app.settings_test_discord', 'Discord Test Gönder') ?></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 4. SEKME: E-POSTA (SMTP) -->
        <div id="tab-email" class="cu-settings-tab-pane">
            <div style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:22px; box-shadow:var(--shadow-card);">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid var(--border-subtle);">
                    <span style="font-size:18px;">✉️</span>
                    <div>
                        <h3 style="font-size:15px; font-weight:700; color:var(--text-white); margin:0;"><?= __('app.settings_smtp_title', 'SMTP E-Posta Sunucu Yapılandırması') ?></h3>
                        <p style="font-size:11.5px; color:var(--text-muted); margin:2px 0 0 0;"><?= __('app.settings_smtp_sub', 'Şifre sıfırlama, kullanıcı davetleri ve görev e-posta bildirimleri için SMTP ayarları') ?></p>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:16px; margin-bottom:16px;">
                    <div class="cu-form-group">
                        <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;"><?= __('app.settings_smtp_host', 'SMTP Host (Sunucu)') ?></label>
                        <input type="text" name="smtp_host" value="<?= htmlspecialchars($smtpHost) ?>" placeholder="smtp.gmail.com / mail.site.com" class="cu-form-control">
                    </div>
                    <div class="cu-form-group">
                        <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;"><?= __('app.settings_smtp_port', 'SMTP Port') ?></label>
                        <input type="number" name="smtp_port" value="<?= htmlspecialchars((string)$smtpPort) ?>" placeholder="587 veya 465" class="cu-form-control">
                    </div>
                    <div class="cu-form-group">
                        <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;"><?= __('app.settings_smtp_security', 'Güvenlik Protokolü') ?></label>
                        <select name="smtp_secure" class="cu-form-control" style="height:38px;">
                            <option value="tls" <?= $smtpSecure === 'tls' ? 'selected' : '' ?>>TLS (Port 587)</option>
                            <option value="ssl" <?= $smtpSecure === 'ssl' ? 'selected' : '' ?>>SSL (Port 465)</option>
                            <option value="none" <?= $smtpSecure === 'none' ? 'selected' : '' ?>><?= __('app.settings_smtp_sec_none', 'Yok / Düz Bağlantı') ?></option>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:16px; margin-bottom:16px;">
                    <div class="cu-form-group">
                        <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;"><?= __('app.settings_smtp_user', 'SMTP Kullanıcı Adı / E-Posta') ?></label>
                        <input type="text" name="smtp_user" value="<?= htmlspecialchars($smtpUser) ?>" placeholder="bildirim@sirketiniz.com" class="cu-form-control">
                    </div>
                    <div class="cu-form-group">
                        <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;"><?= __('app.settings_smtp_pass', 'SMTP Parolası') ?></label>
                        <div style="position:relative;">
                            <input type="password" id="input_smtp_pass" name="smtp_pass" value="<?= htmlspecialchars($smtpPass) ?>" placeholder="••••••••••••" class="cu-form-control" style="padding-right:36px;">
                            <button type="button" onclick="togglePasswordVisibility('input_smtp_pass', this)" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:14px;">👁️</button>
                        </div>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:16px;">
                    <div class="cu-form-group">
                        <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;"><?= __('app.settings_smtp_from_email', 'Gönderen E-Posta Adresi (From Email)') ?></label>
                        <input type="email" name="smtp_from_email" value="<?= htmlspecialchars($smtpFromEmail) ?>" placeholder="noreply@sirketiniz.com" class="cu-form-control">
                    </div>
                    <div class="cu-form-group">
                        <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;"><?= __('app.settings_smtp_from_name', 'Gönderen Başlığı (From Name)') ?></label>
                        <input type="text" name="smtp_from_name" value="<?= htmlspecialchars($smtpFromName) ?>" placeholder="TaskManager Ekibi" class="cu-form-control">
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; margin-top:14px; padding-top:12px; border-top:1px solid var(--border-subtle);">
                    <button type="button" onclick="handleTestSmtp()" id="btnTestSmtp" class="cu-btn-sm" style="display:inline-flex; align-items:center; gap:6px; background:var(--brand-primary); color:#fff; border:none; padding:7px 14px; border-radius:6px; font-size:12px; font-weight:700; cursor:pointer;">
                        <span>✉️</span> <span><?= __('app.settings_smtp_test_btn', 'SMTP Bağlantısını Test Et') ?></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 5. SEKME: ENTEGRASYONLAR -->
        <div id="tab-integrations" class="cu-settings-tab-pane">
            <div style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:22px; box-shadow:var(--shadow-card); margin-bottom:20px;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid var(--border-subtle);">
                    <span style="font-size:18px;">🐞</span>
                    <div>
                        <h3 style="font-size:15px; font-weight:700; color:var(--text-white); margin:0;"><?= __('app.settings_bugherd_title', 'BugHerd Canlı Hata Takip Entegrasyonu') ?></h3>
                        <p style="font-size:11.5px; color:var(--text-muted); margin:2px 0 0 0;"><?= __('app.settings_bugherd_sub', 'Web sitenizden açılan görsel pin hatalarının otomatik TaskManager görevi haline getirilmesi') ?></p>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px;">
                    <div class="cu-form-group">
                        <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;"><?= __('app.settings_bugherd_api_key', 'BugHerd API Key') ?></label>
                        <div style="position:relative;">
                            <input type="password" id="input_bugherd_api_key" name="bugherd_api_key" value="<?= htmlspecialchars($bugherdApiKey) ?>" placeholder="bh_api_..." class="cu-form-control" style="padding-right:36px;">
                            <button type="button" onclick="togglePasswordVisibility('input_bugherd_api_key', this)" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:14px;">👁️</button>
                        </div>
                    </div>
                    <div class="cu-form-group">
                        <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;"><?= __('app.settings_bugherd_project_id', 'BugHerd Proje ID') ?></label>
                        <input type="text" name="bugherd_project_id" value="<?= htmlspecialchars($bugherdProjectId) ?>" placeholder="Örn: 984521" class="cu-form-control">
                    </div>
                </div>
            </div>

            <!-- GITHUB ENTEGRASYONU -->
            <div style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:22px; box-shadow:var(--shadow-card);">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid var(--border-subtle);">
                    <span style="font-size:18px;">🐙</span>
                    <div>
                        <h3 style="font-size:15px; font-weight:700; color:var(--text-white); margin:0;"><?= __('app.settings_github_title', 'GitHub / Git Deposu Bağlantısı') ?></h3>
                        <p style="font-size:11.5px; color:var(--text-muted); margin:2px 0 0 0;"><?= __('app.settings_github_sub', 'Commit mesajlarındaki TSK kodları üzerinden otomatik durum kapatma ve branch takibi') ?></p>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px;">
                    <div class="cu-form-group">
                        <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;"><?= __('app.settings_github_token', 'GitHub Personal Access Token (PAT)') ?></label>
                        <div style="position:relative;">
                            <input type="password" id="input_github_token" name="github_token" value="<?= htmlspecialchars($githubToken) ?>" placeholder="ghp_..." class="cu-form-control" style="padding-right:36px;">
                            <button type="button" onclick="togglePasswordVisibility('input_github_token', this)" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:14px;">👁️</button>
                        </div>
                    </div>
                    <div class="cu-form-group">
                        <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;"><?= __('app.settings_github_repo', 'Hedef Depo (Repository: owner/repo)') ?></label>
                        <input type="text" name="github_repo" value="<?= htmlspecialchars($githubRepo) ?>" placeholder="organizasyon/proje-adi" class="cu-form-control">
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. SEKME: GÜVENLİK & DEPOLAMA -->
        <div id="tab-security" class="cu-settings-tab-pane">
            <div style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:22px; box-shadow:var(--shadow-card);">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid var(--border-subtle);">
                    <span style="font-size:18px;">🛡️</span>
                    <div>
                        <h3 style="font-size:15px; font-weight:700; color:var(--text-white); margin:0;"><?= __('app.settings_security_title', 'Güvenlik, Oturum & Dosya Yükleme Limitleri') ?></h3>
                        <p style="font-size:11.5px; color:var(--text-muted); margin:2px 0 0 0;"><?= __('app.settings_security_sub', 'Oturum süreleri, ek dosya formatları ve kota kontrolleri') ?></p>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px;">
                    <div class="cu-form-group">
                        <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;"><?= __('app.settings_session_timeout', 'Oturum Zaman Aşımı (Dakika)') ?></label>
                        <input type="number" name="session_timeout_minutes" value="<?= htmlspecialchars((string)$sessionTimeout) ?>" class="cu-form-control">
                        <small style="font-size:11px; color:var(--text-muted); margin-top:4px; display:block;"><?= __('app.settings_session_timeout_hint', 'İşlem yapılmadığında otomatik çıkış süresi') ?></small>
                    </div>

                    <div class="cu-form-group">
                        <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;"><?= __('app.settings_max_upload', 'Maksimum Görev Dosya Boyutu (MB)') ?></label>
                        <input type="number" name="max_file_upload_mb" value="<?= htmlspecialchars((string)$maxFileUpload) ?>" class="cu-form-control">
                        <small style="font-size:11px; color:var(--text-muted); margin-top:4px; display:block;"><?= __('app.settings_max_upload_hint', 'Tek seferde yüklenebilecek en yüksek dosya boyutu') ?></small>
                    </div>

                    <div class="cu-form-group" style="grid-column:1 / -1;">
                        <label style="font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;"><?= __('app.settings_allowed_files', 'İzin Verilen Dosya Uzantıları') ?></label>
                        <input type="text" name="allowed_file_types" value="<?= htmlspecialchars($allowedFileTypes) ?>" class="cu-form-control">
                        <small style="font-size:11px; color:var(--text-muted); margin-top:4px; display:block;"><?= __('app.settings_allowed_files_hint', 'Virgülle ayrılmış uzantılar (örn: jpg, png, pdf, zip, docx, xlsx, txt)') ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 7. SEKME: MODÜL YÖNETİCİSİ (HMVC) -->
        <div id="tab-modules" class="cu-settings-tab-pane">
            <div class="cu-settings-card" style="background:var(--bg-surface); border:1px solid var(--border-subtle); border-radius:12px; padding:24px; margin-bottom:20px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:18px; border-bottom:1px solid var(--border-subtle); padding-bottom:14px; flex-wrap:wrap; gap:12px;">
                    <div>
                        <h3 style="font-size:16px; font-weight:700; color:var(--text-white); margin:0; display:flex; align-items:center; gap:8px;">
                            <span>📦</span> <?= __('app.settings_modules_title', 'Modül Yöneticisi & Eklenti Sistemi') ?>
                        </h3>
                        <p style="font-size:12px; color:var(--text-muted); margin:4px 0 0 0;">
                            <?= __('app.settings_modules_desc', 'Tüm HMVC dikey modüllerini WordPress eklentisi gibi bağımsız açıp kapatın, sürüm ve dizin detaylarını yönetin.') ?>
                        </p>
                    </div>
                    <?php 
                    $availableModules = $settingsData['availableModules'] ?? [];
                    $totalModules = count($availableModules);
                    $activeCount = count(array_filter($availableModules, fn($m) => !empty($m['is_active'])));
                    $passiveCount = $totalModules - $activeCount;
                    ?>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <span style="font-size:11px; font-weight:700; background:rgba(255,255,255,0.06); padding:4px 10px; border-radius:12px; color:var(--text-primary); border:1px solid var(--border-subtle);">
                            <?= __('app.settings_modules_count_badge', 'Toplam Modül') ?>: <?= $totalModules ?>
                        </span>
                        <span style="font-size:11px; font-weight:700; background:rgba(0,184,148,0.15); padding:4px 10px; border-radius:12px; color:#00b894; border:1px solid rgba(0,184,148,0.3);">
                            <?= __('app.settings_modules_active_badge', 'Aktif') ?>: <?= $activeCount ?>
                        </span>
                        <span style="font-size:11px; font-weight:700; background:rgba(235,87,87,0.15); padding:4px 10px; border-radius:12px; color:#eb5757; border:1px solid rgba(235,87,87,0.3);">
                            <?= __('app.settings_modules_passive_badge', 'Pasif') ?>: <?= $passiveCount ?>
                        </span>
                    </div>
                </div>

                <!-- Modül Listesi Tablosu / Kartları -->
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <?php if (empty($availableModules)): ?>
                        <div style="padding:24px; text-align:center; color:var(--text-muted); font-size:13px;">
                            Kayıtlı HMVC modülü bulunamadı.
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
                        <div class="cu-module-item-card" style="display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,0.02); border:1px solid <?= $modActive ? 'rgba(123,104,238,0.3)' : 'var(--border-subtle)' ?>; border-radius:10px; padding:16px 20px; gap:16px; transition:all 0.2s ease;">
                            <div style="flex:1; min-width:260px;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <span style="font-size:18px;">🧩</span>
                                    <h4 style="margin:0; font-size:14px; font-weight:700; color:var(--text-white);">
                                        <?= $modName ?>
                                    </h4>
                                    <span style="font-size:10px; font-weight:700; background:rgba(123,104,238,0.15); color:var(--brand-primary); padding:2px 8px; border-radius:4px; font-family:monospace;">
                                        v<?= $modVer ?>
                                    </span>
                                    <span style="font-size:10px; color:var(--text-muted); font-family:monospace;">
                                        (<?= $modSlug ?>)
                                    </span>
                                </div>
                                <p style="font-size:12px; color:var(--text-muted); margin:4px 0 6px 28px; line-height:1.4;">
                                    <?= $modDesc ?>
                                </p>
                                <div style="display:flex; gap:16px; font-size:11px; color:var(--text-muted); margin-left:28px;">
                                    <span>👤 <strong><?= __('app.settings_modules_col_version', 'Yazar') ?>:</strong> <?= $modAuthor ?></span>
                                    <span>🔗 <strong><?= __('app.settings_modules_col_deps', 'Bağımlılıklar') ?>:</strong> <?= $modDeps ?></span>
                                </div>
                            </div>

                            <div style="display:flex; align-items:center; gap:12px; flex-shrink:0;">
                                <span id="badge-status-<?= $modSlug ?>" style="font-size:11.5px; font-weight:700; color:<?= $modActive ? '#00b894' : '#eb5757' ?>;">
                                    <?= $modActive ? __('app.settings_modules_active_badge', 'Aktif') : __('app.settings_modules_passive_badge', 'Pasif') ?>
                                </span>
                                <label class="cu-toggle-switch" title="<?= __('app.settings_modules_col_status', 'Durum / Anahtar') ?>">
                                    <input type="checkbox" id="toggle-mod-<?= $modSlug ?>" onchange="toggleModuleStatus('<?= $modSlug ?>', this.checked)" <?= $modActive ? 'checked' : '' ?>>
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
        <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:10px;">
            <a href="/taskmanager/board" class="cu-btn-sm" style="display:inline-flex; align-items:center; padding:10px 18px; font-size:13px; text-decoration:none; background:transparent; border:1px solid var(--border-subtle); color:var(--text-primary); border-radius:8px;">
                <?= __('app.settings_btn_cancel', 'İptal / Panoya Dön') ?>
            </a>
            <button type="submit" id="btnSaveSettingsBottom" class="cu-btn cu-btn-primary" style="display:inline-flex; align-items:center; gap:8px; padding:10px 24px; font-size:13px; font-weight:600; border-radius:8px; box-shadow:0 4px 14px rgba(123,104,238,0.35);">
                <span>💾</span>
                <span><?= __('app.settings_btn_save', 'Değişiklikleri Kaydet') ?></span>
            </button>
        </div>

    </form>
    </div>
</div>

<style>
/* Sekme Butonları */
.cu-settings-tab-btn {
    background: transparent;
    border: 1px solid transparent;
    border-radius: 8px;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
    transition: all 0.15s ease;
}
.cu-settings-tab-btn:hover {
    background: rgba(255, 255, 255, 0.04);
    color: var(--text-primary);
}
.cu-settings-tab-btn.active {
    background: rgba(123, 104, 238, 0.15);
    border-color: rgba(123, 104, 238, 0.35);
    color: var(--brand-primary);
    font-weight: 700;
}

/* Sekme İçerikleri */
.cu-settings-tab-pane {
    display: none;
}
.cu-settings-tab-pane.active {
    display: block;
    animation: fadeIn 0.2s ease;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Modern Toggle Switch */
.cu-toggle-switch {
    position: relative;
    display: inline-block;
    width: 46px;
    height: 24px;
    flex-shrink: 0;
}
.cu-toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.cu-toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: rgba(255, 255, 255, 0.15);
    transition: 0.3s;
    border-radius: 24px;
    border: 1px solid var(--border-subtle);
}
.cu-toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 2px;
    bottom: 2px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
}
.cu-toggle-switch input:checked + .cu-toggle-slider {
    background-color: var(--brand-primary);
    border-color: var(--brand-primary);
}
.cu-toggle-switch input:checked + .cu-toggle-slider:before {
    transform: translateX(22px);
}
</style>

<script>
// Sekme Değiştirici
function switchSettingsTab(tabId, btn) {
    document.querySelectorAll('.cu-settings-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.cu-settings-tab-pane').forEach(p => p.classList.remove('active'));

    btn.classList.add('active');
    const pane = document.getElementById(tabId);
    if (pane) pane.classList.add('active');
}

// Şifre Göster / Gizle
function togglePasswordVisibility(inputId, btn) {
    const el = document.getElementById(inputId);
    if (!el) return;
    if (el.type === 'password') {
        el.type = 'text';
        btn.innerText = '🙈';
    } else {
        el.type = 'password';
        btn.innerText = '👁️';
    }
}

// Ayarları Kaydet
async function handleSaveSettings(e) {
    if (e) e.preventDefault();

    const form = document.getElementById('formSystemSettings');
    const fd = new FormData(form);

    const btnTop = document.getElementById('btnSaveSettingsTop');
    const btnBottom = document.getElementById('btnSaveSettingsBottom');
    if (btnTop) btnTop.disabled = true;
    if (btnBottom) btnBottom.disabled = true;

    try {
        const res = await fetch('/taskmanager/ajax?action=save_system_settings', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();

        if (btnTop) btnTop.disabled = false;
        if (btnBottom) btnBottom.disabled = false;

        if (data.success) {
            if (typeof showToast === 'function') {
                showToast(<?= json_encode(__('app.settings_saved_success')) ?>, 'success');
            } else {
                alert(<?= json_encode(__('app.settings_saved_success')) ?>);
            }
        } else {
            if (typeof showToast === 'function') {
                showToast(data.message || <?= json_encode(__('app.settings_saved_fail')) ?>, 'error');
            } else {
                alert('Hata: ' + (data.message || <?= json_encode(__('app.settings_saved_fail')) ?>));
            }
        }
    } catch (err) {
        if (btnTop) btnTop.disabled = false;
        if (btnBottom) btnBottom.disabled = false;
        alert('Sunucu bağlantı hatası: ' + err.message);
    }
}

// Telegram Bağlantı Testi
async function handleTestTelegram() {
    const btn = document.getElementById('btnTestTelegram');
    if (btn) {
        btn.disabled = true;
        btn.innerText = 'Test ediliyor...';
    }

    try {
        const res = await fetch('/taskmanager/ajax?action=test_telegram_connection');
        const data = await res.json();
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span>📡</span> <span>Bağlantıyı Test Et</span>';
        }

        if (data.success) {
            if (typeof showToast === 'function') {
                showToast(data.message || '✅ Bağlantı başarılı!', 'success');
            } else {
                alert(data.message || 'Bağlantı başarılı!');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast(data.message || '❌ Bağlantı kurulamadı.', 'error');
            } else {
                alert(data.message || 'Bağlantı kurulamadı.');
            }
        }
    } catch (err) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span>📡</span> <span>Bağlantıyı Test Et</span>';
        }
        alert('Bağlantı testi hatası: ' + err.message);
    }
}

// SMTP Bağlantı Testi
async function handleTestSmtp() {
    const btn = document.getElementById('btnTestSmtp');
    if (btn) {
        btn.disabled = true;
        btn.innerText = 'Test ediliyor...';
    }

    try {
        const res = await fetch('/taskmanager/ajax?action=test_smtp_connection');
        const data = await res.json();
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span>✉️</span> <span>SMTP Bağlantısını Test Et</span>';
        }

        if (data.success) {
            if (typeof showToast === 'function') {
                showToast(data.message || '✅ SMTP bağlantısı başarılı!', 'success');
            } else {
                alert(data.message || 'SMTP bağlantısı başarılı!');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast(data.message || '❌ SMTP bağlantı hatası.', 'error');
            } else {
                alert(data.message || 'SMTP bağlantı hatası.');
            }
        }
    } catch (err) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span>✉️</span> <span>SMTP Bağlantısını Test Et</span>';
        }
        alert('SMTP test hatası: ' + err.message);
    }
}

// Slack & Discord Webhook Testi
async function handleTestWebhook(type) {
    const btnId = (type === 'slack') ? 'btnTestSlack' : 'btnTestDiscord';
    const action = (type === 'slack') ? 'test_slack_webhook' : 'test_discord_webhook';
    const label = (type === 'slack') ? 'Slack Test Gönder' : 'Discord Test Gönder';
    const btn = document.getElementById(btnId);

    if (btn) {
        btn.disabled = true;
        btn.innerText = 'Gönderiliyor...';
    }

    try {
        const res = await fetch('/taskmanager/ajax?action=' + action);
        const data = await res.json();
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = (type === 'slack' ? '<span>💬</span> ' : '<span>🎮</span> ') + '<span>' + label + '</span>';
        }

        if (data.success) {
            if (typeof showToast === 'function') {
                showToast(data.message || '✅ Webhook mesajı iletildi!', 'success');
            } else {
                alert(data.message || 'Webhook mesajı iletildi!');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast(data.message || '❌ Webhook iletilemedi.', 'error');
            } else {
                alert(data.message || 'Webhook iletilemedi.');
            }
        }
    } catch (err) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = (type === 'slack' ? '<span>💬</span> ' : '<span>🎮</span> ') + '<span>' + label + '</span>';
        }
        alert('Webhook test hatası: ' + err.message);
    }
}

// Modül Durumu Aç / Kapat (Module Manager)
async function toggleModuleStatus(slug, isActive) {
    const badge = document.getElementById('badge-status-slug-' + slug) || document.getElementById('badge-status-' + slug);
    const fd = new FormData();
    fd.append('slug', slug);
    fd.append('status', isActive ? '1' : '0');

    try {
        const res = await fetch('/taskmanager/ajax?action=toggle_module_status', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();
        if (data.success) {
            if (badge) {
                badge.innerText = isActive ? '<?= __('app.settings_modules_active_badge', 'Aktif') ?>' : '<?= __('app.settings_modules_passive_badge', 'Pasif') ?>';
                badge.style.color = isActive ? '#00b894' : '#eb5757';
            }
            if (typeof showToast === 'function') {
                showToast(data.message || '<?= __('app.settings_modules_toggle_success', 'Modül durumu güncellendi.') ?>', 'success');
            }
        } else {
            alert(data.message || '<?= __('app.settings_modules_toggle_fail', 'Modül durumu güncellenemedi.') ?>');
            // Geri al
            const toggle = document.getElementById('toggle-mod-' + slug);
            if (toggle) toggle.checked = !isActive;
        }
    } catch (err) {
        alert('Modül toggle hatası: ' + err.message);
        const toggle = document.getElementById('toggle-mod-' + slug);
        if (toggle) toggle.checked = !isActive;
    }
}

// URL tab parametresi desteği (?tab=modules vb.)
document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const tabParam = params.get('tab');
    if (tabParam) {
        const tabPaneId = 'tab-' + tabParam;
        const targetBtn = document.querySelector(`[onclick*="${tabPaneId}"]`);
        if (targetBtn) {
            targetBtn.click();
        }
    }
});
</script>

<?php include dirname(__DIR__, 3) . '/Core/Views/footer.php'; ?>
