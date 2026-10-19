<?php
// TaskManager Interactive REST API & AI Agent Documentation
require_once dirname(__DIR__, 3) . '/Core/System.php';
require_once dirname(__DIR__, 3) . '/Core/Language.php';

Language::init();
$currentLang = Language::current(); // 'tr', 'en', 'de'
$loc = in_array($currentLang, ['tr', 'en', 'de'], true) ? $currentLang : 'en';

$system->title = __('ApiTester::page_title');
include dirname(__DIR__, 3) . '/Core/Views/header.php';

$currentUser = $_SESSION['user'] ?? null;
$dynCfg = \Modules\RestApi\Services\RestApiService::getDynamicApiConfig();
$userToken = (!empty($currentUser->api_token)) ? $currentUser->api_token : ($dynCfg['api_token'] ?: 'tm_live_demo_token');
?>

<div class="cu-view-header cu-docs-header-bar">
    <div>
        <div class="cu-docs-title-wrapper">
            <span class="cu-docs-title-icon">⚡</span>
            <h1 class="cu-docs-title-heading"><?= htmlspecialchars(__('ApiTester::api_version')) ?></h1>
            <span class="cu-docs-badge-active"><?= htmlspecialchars(__('ApiTester::badge_active')) ?></span>
        </div>
        <p class="cu-docs-header-desc">
            <?= htmlspecialchars(__('ApiTester::header_desc')) ?>
        </p>
    </div>
    <div class="cu-docs-header-actions">
        <!-- Dil Degistirici Hizli Dugmeler -->
        <div class="cu-docs-lang-switch-box">
            <a href="?lang=tr" class="cu-btn-sm cu-docs-lang-btn <?= $loc === 'tr' ? 'active' : '' ?>">TR</a>
            <a href="?lang=en" class="cu-btn-sm cu-docs-lang-btn <?= $loc === 'en' ? 'active' : '' ?>">EN</a>
            <a href="?lang=de" class="cu-btn-sm cu-docs-lang-btn <?= $loc === 'de' ? 'active' : '' ?>">DE</a>
        </div>
        <a href="/taskmanager/api-test" class="cu-btn-sm cu-docs-btn-lab">
            <span>🧪</span>
            <span><?= htmlspecialchars(__('ApiTester::live_console_badge')) ?></span>
        </a>
        <a href="/taskmanager/API_DOCUMENTATION.md" target="_blank" class="cu-btn-sm cu-docs-btn-markdown">
            <span>📄</span>
            <span><?= htmlspecialchars(__('ApiTester::btn_markdown')) ?></span>
        </a>
        <a href="/taskmanager/api/health" target="_blank" class="cu-btn-sm cu-docs-btn-health">
            <span>🟢</span>
            <span><?= htmlspecialchars(__('ApiTester::btn_health')) ?></span>
        </a>
    </div>
</div>

<div class="cu-content-scroll cu-docs-scroll-container">
    
    <!-- API ERISIM & TOKEN KARTI -->
    <div class="cu-docs-token-card">
        <div class="cu-docs-token-grid">
            <div>
                <span class="cu-docs-token-pill"><?= htmlspecialchars(__('ApiTester::token_label')) ?></span>
                <h3 class="cu-docs-user-title"><?= htmlspecialchars($currentUser->name ?? 'Talha Keskin') ?> (<?= htmlspecialchars($currentUser->role ?? 'admin') ?>)</h3>
                <p class="cu-docs-token-desc">
                    <?= htmlspecialchars(__('ApiTester::token_desc')) ?>
                </p>
            </div>
            <div>
                <div class="cu-docs-token-row">
                    <div class="cu-docs-token-value-box">
                        Authorization: Bearer <span id="tokenText"><?= htmlspecialchars($userToken) ?></span>
                    </div>
                    <button type="button" class="cu-btn cu-btn-primary cu-docs-copy-token-btn" id="btnCopyApiToken">
                        <span>📋</span> <span><?= htmlspecialchars(__('ApiTester::btn_copy_token')) ?></span>
                    </button>
                </div>
                <div class="cu-docs-meta-row">
                    <span>🌐 <b>Base URL:</b> <code class="cu-docs-meta-code">http://localhost/taskmanager/api/v1</code></span>
                    <span>🔑 <b>Header:</b> <code class="cu-docs-meta-code">Authorization: Bearer &lt;token&gt;</code> <?= htmlspecialchars(__('ApiTester::or_keyword')) ?> <code class="cu-docs-meta-code">X-API-Key</code></span>
                    <span>🌍 <b>Lang:</b> <code class="cu-docs-meta-code-accent">?lang=<?= htmlspecialchars($loc) ?></code></span>
                </div>
            </div>
        </div>
    </div>

    <!-- CANLI API TEST LABORATUVARI TANITIM BANNERI -->
    <div class="cu-docs-banner-card">
        <div class="cu-docs-banner-left">
            <div class="cu-docs-banner-icon">
                🧪
            </div>
            <div>
                <h4 class="cu-docs-banner-title"><?= htmlspecialchars(__('ApiTester::live_lab_banner_title')) ?></h4>
                <p class="cu-docs-banner-desc">
                    <?= htmlspecialchars(__('ApiTester::live_lab_banner_desc')) ?>
                </p>
            </div>
        </div>
        <a href="/taskmanager/api-test" class="cu-btn-sm cu-docs-banner-link">
            <span><?= htmlspecialchars(__('ApiTester::btn_start_console')) ?></span>
            <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>

    <!-- COK DILLI API DESTEGI KARTI -->
    <div class="cu-docs-i18n-card">
        <div class="cu-docs-i18n-header">
            <div class="cu-docs-i18n-title-group">
                <span class="cu-docs-i18n-icon">🌐</span>
                <div>
                    <h3 class="cu-docs-i18n-title"><?= htmlspecialchars(__('ApiTester::i18n_title')) ?></h3>
                    <span class="cu-docs-i18n-subtitle"><?= htmlspecialchars(__('ApiTester::i18n_subtitle')) ?></span>
                </div>
            </div>
            <div class="cu-docs-i18n-pills">
                <a href="?lang=tr" class="cu-docs-pill cu-docs-pill-tr <?= $loc === 'tr' ? 'active' : '' ?>">TR (Türkçe)</a>
                <a href="?lang=en" class="cu-docs-pill cu-docs-pill-en <?= $loc === 'en' ? 'active' : '' ?>">EN (English)</a>
                <a href="?lang=de" class="cu-docs-pill cu-docs-pill-de <?= $loc === 'de' ? 'active' : '' ?>">DE (Deutsch)</a>
            </div>
        </div>
        <div class="cu-docs-i18n-grid">
            <div class="cu-docs-channel-box">
                <div class="cu-docs-channel-title"><?= htmlspecialchars(__('ApiTester::i18n_m1_title')) ?></div>
                <code class="cu-docs-channel-code"><?= htmlspecialchars(__('ApiTester::i18n_m1_desc')) ?></code>
            </div>
            <div class="cu-docs-channel-box">
                <div class="cu-docs-channel-title"><?= htmlspecialchars(__('ApiTester::i18n_m2_title')) ?></div>
                <code class="cu-docs-channel-code"><?= htmlspecialchars(__('ApiTester::i18n_m2_desc')) ?></code>
            </div>
            <div class="cu-docs-channel-box">
                <div class="cu-docs-channel-title"><?= htmlspecialchars(__('ApiTester::i18n_m3_title')) ?></div>
                <code class="cu-docs-channel-code"><?= htmlspecialchars(__('ApiTester::i18n_m3_desc')) ?></code>
            </div>
            <div class="cu-docs-channel-box">
                <div class="cu-docs-channel-title"><?= htmlspecialchars(__('ApiTester::i18n_m4_title')) ?></div>
                <span class="cu-docs-channel-desc"><?= htmlspecialchars(__('ApiTester::i18n_m4_desc')) ?></span>
            </div>
        </div>
    </div>

    <!-- YAPAY ZEKA / ANTIGRAVITY AGENT REHBERI -->
    <div class="cu-docs-ai-card">
        <div class="cu-docs-ai-header">
            <div class="cu-docs-ai-icon">
                🤖
            </div>
            <div>
                <h2 class="cu-docs-ai-heading"><?= htmlspecialchars(__('ApiTester::ai_scenario_title')) ?></h2>
                <span class="cu-docs-ai-subtitle"><?= htmlspecialchars(__('ApiTester::ai_scenario_subtitle')) ?></span>
            </div>
        </div>

        <div class="cu-docs-prompt-box">
            <div class="cu-docs-prompt-label"><?= htmlspecialchars(__('ApiTester::ai_prompt_label')) ?></div>
            <blockquote class="cu-docs-prompt-quote">
                <?= htmlspecialchars(__('ApiTester::ai_prompt_text')) ?>
            </blockquote>
        </div>

        <div class="cu-docs-steps-grid">
            <div class="cu-docs-step-card">
                <span class="cu-docs-step-badge cu-docs-step-badge-1">1. <?= htmlspecialchars(__('ApiTester::step_label')) ?></span>
                <div class="cu-docs-step-title"><?= htmlspecialchars(__('ApiTester::step_1_title')) ?></div>
                <div class="cu-docs-step-endpoint">GET /projects/ETC/tasks?status=pending&lang=<?= htmlspecialchars($loc) ?></div>
                <p class="cu-docs-step-desc"><?= htmlspecialchars(__('ApiTester::step_1_desc')) ?></p>
            </div>

            <div class="cu-docs-step-card">
                <span class="cu-docs-step-badge cu-docs-step-badge-2">2. <?= htmlspecialchars(__('ApiTester::step_label')) ?></span>
                <div class="cu-docs-step-title"><?= htmlspecialchars(__('ApiTester::step_2_title')) ?></div>
                <div class="cu-docs-step-endpoint">POST /tasks/ETC-101/workflow/accept?lang=<?= htmlspecialchars($loc) ?></div>
                <p class="cu-docs-step-desc"><?= htmlspecialchars(__('ApiTester::step_2_desc')) ?></p>
            </div>

            <div class="cu-docs-step-card">
                <span class="cu-docs-step-badge cu-docs-step-badge-3">3. <?= htmlspecialchars(__('ApiTester::step_label')) ?></span>
                <div class="cu-docs-step-title"><?= htmlspecialchars(__('ApiTester::step_3_title')) ?></div>
                <div class="cu-docs-step-endpoint">POST /tasks/ETC-101/comments?lang=<?= htmlspecialchars($loc) ?></div>
                <p class="cu-docs-step-desc"><?= htmlspecialchars(__('ApiTester::step_3_desc')) ?></p>
            </div>

            <div class="cu-docs-step-card">
                <span class="cu-docs-step-badge cu-docs-step-badge-4">4. <?= htmlspecialchars(__('ApiTester::step_label')) ?></span>
                <div class="cu-docs-step-title"><?= htmlspecialchars(__('ApiTester::step_4_title')) ?></div>
                <div class="cu-docs-step-endpoint">POST /tasks/ETC-101/workflow/submit?lang=<?= htmlspecialchars($loc) ?></div>
                <p class="cu-docs-step-desc"><?= htmlspecialchars(__('ApiTester::step_4_desc')) ?></p>
            </div>
        </div>
    </div>

    <!-- DETAYLI API ENDPOINTLERI -->
    <div class="cu-docs-sec-header">
        <h2 class="cu-docs-sec-title">
            <span>📚</span> <?= htmlspecialchars(__('ApiTester::endpoints_title')) ?>
        </h2>
        <span class="cu-docs-sec-note"><?= htmlspecialchars(__('ApiTester::endpoints_note')) ?></span>
    </div>

    <div class="cu-docs-endpoints-container">

        <!-- 1. AUTH & HESAP -->
        <div class="cu-endpoint-card">
            <div class="cu-endpoint-card-header">
                <span class="cu-endpoint-cat-title"><?= htmlspecialchars(__('ApiTester::sec1_title')) ?></span>
                <span class="cu-endpoint-cat-sub">/api/v1/auth & /api/v1/profile</span>
            </div>
            <div class="cu-endpoint-card-body">
                <div class="api-row">
                    <span class="method-badge-post">POST</span>
                    <span class="path-code-default">/auth/login</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec1_ep1')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-get">GET</span>
                    <span class="path-code-default">/auth/me</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec1_ep2')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-put">PUT / POST</span>
                    <span class="path-code-default">/profile</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec1_ep3')) ?></span>
                </div>
            </div>
        </div>

        <!-- 2. PROJELER -->
        <div class="cu-endpoint-card">
            <div class="cu-endpoint-card-header">
                <span class="cu-endpoint-cat-title"><?= htmlspecialchars(__('ApiTester::sec2_title')) ?></span>
                <span class="cu-endpoint-cat-sub">/api/v1/projects</span>
            </div>
            <div class="cu-endpoint-card-body">
                <div class="api-row">
                    <span class="method-badge-get">GET</span>
                    <span class="path-code-default">/projects</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec2_ep1')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-post">POST</span>
                    <span class="path-code-default">/projects</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec2_ep2')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-get">GET</span>
                    <span class="path-code-default">/projects/{id_or_code}</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec2_ep3')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-get">GET</span>
                    <span class="path-code-purple">/projects/{id_or_code}/tasks</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec2_ep4')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-get">GET</span>
                    <span class="path-code-green">/projects/{id_or_code}/docs</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec2_ep5')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-post">POST</span>
                    <span class="path-code-default">/projects/{id_or_code}/docs</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec2_ep6')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-put">PUT</span>
                    <span class="path-code-default">/projects/{id_or_code}</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec2_ep7')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-post">POST</span>
                    <span class="path-code-green">/projects/{id_or_code}/webhook/test</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec2_ep8')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-delete">DELETE</span>
                    <span class="path-code-default">/projects/{id_or_code}</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec2_ep9')) ?></span>
                </div>
            </div>
        </div>

        <!-- 3. GOREVLER & KANBAN -->
        <div class="cu-endpoint-card">
            <div class="cu-endpoint-card-header">
                <span class="cu-endpoint-cat-title"><?= htmlspecialchars(__('ApiTester::sec3_title')) ?></span>
                <span class="cu-endpoint-cat-sub">/api/v1/tasks</span>
            </div>
            <div class="cu-endpoint-card-body">
                <div class="api-row">
                    <span class="method-badge-get">GET</span>
                    <span class="path-code-default">/tasks</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec3_ep1')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-post">POST</span>
                    <span class="path-code-default">/tasks</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec3_ep2')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-get">GET</span>
                    <span class="path-code-default">/tasks/{id_or_code}</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec3_ep3')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-put">PUT</span>
                    <span class="path-code-default">/tasks/{id_or_code}</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec3_ep4')) ?></span>
                </div>
                
                <div class="workflow-divider"></div>
                <div class="workflow-title-label"><?= htmlspecialchars(__('ApiTester::sec3_workflow_label')) ?></div>

                <div class="api-row">
                    <span class="method-badge-post">POST</span>
                    <span class="path-code-green">/tasks/{code}/workflow/accept</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec3_wf1')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-post">POST</span>
                    <span class="path-code-default">/tasks/{code}/workflow/status</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec3_wf2')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-post">POST</span>
                    <span class="path-code-amber">/tasks/{code}/workflow/request-modification</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec3_wf3')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-post">POST</span>
                    <span class="path-code-green">/tasks/{code}/workflow/submit</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec3_wf4')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-post">POST</span>
                    <span class="path-code-purple">/tasks/{code}/workflow/review</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec3_wf5')) ?></span>
                </div>
            </div>
        </div>

        <!-- 4. CHECKLISTS, COMMENTS, ASSIGNEES -->
        <div class="cu-endpoint-card">
            <div class="cu-endpoint-card-header">
                <span class="cu-endpoint-cat-title"><?= htmlspecialchars(__('ApiTester::sec4_title')) ?></span>
                <span class="cu-endpoint-cat-sub">/api/v1/tasks/{code}/[checklists|comments|assignees]</span>
            </div>
            <div class="cu-endpoint-card-body">
                <div class="api-row">
                    <span class="method-badge-post">POST</span>
                    <span class="path-code-default">/tasks/{code}/checklists</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec4_ep1')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-put">PUT</span>
                    <span class="path-code-default">/tasks/{code}/checklists/{id}</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec4_ep2')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-post">POST</span>
                    <span class="path-code-default">/tasks/{code}/comments</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec4_ep3')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-post">POST</span>
                    <span class="path-code-default">/tasks/{code}/assignees</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec4_ep4')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-delete">DELETE</span>
                    <span class="path-code-default">/tasks/{code}/assignees/{user_id}</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec4_ep5')) ?></span>
                </div>
            </div>
        </div>

        <!-- 5. DOKUMANLAR, EKIP & RAPORLAR -->
        <div class="cu-endpoint-card">
            <div class="cu-endpoint-card-header">
                <span class="cu-endpoint-cat-title"><?= htmlspecialchars(__('ApiTester::sec5_title')) ?></span>
                <span class="cu-endpoint-cat-sub">/api/v1/[docs|users|reports]</span>
            </div>
            <div class="cu-endpoint-card-body">
                <div class="api-row">
                    <span class="method-badge-get">GET</span>
                    <span class="path-code-default">/docs</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec5_ep1')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-get">GET</span>
                    <span class="path-code-default">/docs/{id}</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec5_ep2')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-post">POST</span>
                    <span class="path-code-default">/docs</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec5_ep3')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-put">PUT</span>
                    <span class="path-code-default">/docs/{id}</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec5_ep4')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-delete">DELETE</span>
                    <span class="path-code-default">/docs/{id}</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec5_ep5')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-get">GET</span>
                    <span class="path-code-default">/users</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec5_ep6')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-get">GET</span>
                    <span class="path-code-default">/reports</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec5_ep7')) ?></span>
                </div>
            </div>
        </div>

        <!-- 6. WEBHOOKS & AI AGENT KOPRUSU -->
        <div class="cu-endpoint-card">
            <div class="cu-endpoint-card-header">
                <span class="cu-endpoint-cat-title"><?= htmlspecialchars(__('ApiTester::sec6_title')) ?></span>
                <span class="cu-endpoint-cat-sub">/api/v1/[projects/{code}/webhook/test | webhooks/logs]</span>
            </div>
            <div class="cu-endpoint-card-body">
                <div class="api-row">
                    <span class="method-badge-post">POST</span>
                    <span class="path-code-green">/projects/{code}/webhook/test</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec6_ep1')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-get">GET</span>
                    <span class="path-code-default">/webhooks/logs</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec6_ep2')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-put">PUT</span>
                    <span class="path-code-default">/projects/{code}</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec6_ep3')) ?></span>
                </div>
                <div class="api-row">
                    <span class="method-badge-post">POST</span>
                    <span class="path-code-default">/webhooks/listener</span>
                    <span class="desc-text"><?= htmlspecialchars(__('ApiTester::sec6_ep4')) ?></span>
                </div>
            </div>
        </div>

    </div>

</div>

<?php
\Core\AssetManager::localizeScript('api-docs-js', 'APITESTER_DOCS_I18N', [
    'token_copied' => __('ApiTester::token_copied')
]);
?>

<?php include dirname(__DIR__, 3) . '/Core/Views/footer.php'; ?>
