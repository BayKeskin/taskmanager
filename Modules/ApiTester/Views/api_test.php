<?php
/**
 * TaskManager Enterprise REST API Live Test & Explorer Laboratory
 * Interactive Swagger / Postman style live testing console
 */
$currentLang = \Language::current();
$userToken = $userToken ?? 'tm_live_demo_token';
$baseUrl = $baseUrl ?? '';
$sampleProject = $sampleProject ?? 'PROJECT';
$sampleTask = $sampleTask ?? 'TASK-1';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧪 <?= htmlspecialchars(__('ApiTester::live_lab_title')) ?> - TaskManager</title>
    
    <!-- Google Fonts & FontAwesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?= \Core\AssetManager::renderStyles() ?>
</head>
<body>

    <!-- TOP HEADER -->
    <header class="api-lab-header">
        <div class="header-title-box">
            <span class="api-lab-header-icon">🧪</span>
            <div>
                <div class="api-lab-header-row">
                    <h1 class="api-lab-header-title"><?= htmlspecialchars(__('ApiTester::live_lab_title')) ?></h1>
                    <span class="header-badge"><?= htmlspecialchars(__('ApiTester::live_lab_version')) ?></span>
                </div>
                <div class="api-lab-header-desc">
                    <?= htmlspecialchars(__('ApiTester::live_lab_desc')) ?>
                </div>
            </div>
        </div>

        <div class="header-actions">
            <!-- Token Box -->
            <div class="api-lab-token-pill">
                <span class="api-lab-token-label">🔑 Token:</span>
                <span id="activeTokenDisplay" class="api-lab-token-text">
                    <?= htmlspecialchars($userToken) ?>
                </span>
                <button type="button" id="btnCopyActiveToken" class="api-lab-btn-copy-token" title="<?= htmlspecialchars(__('ApiTester::token_title_attr')) ?>">
                    <i class="fa-regular fa-copy"></i>
                </button>
            </div>

            <!-- Batch Test Button -->
            <button type="button" id="btnOpenBatchModal" class="btn-lab btn-lab-success">
                <i class="fa-solid fa-heart-pulse"></i>
                <span><?= htmlspecialchars(__('ApiTester::core_health_scan')) ?></span>
            </button>

            <!-- API Docs Link -->
            <a href="<?= baseUrl('api-docs') ?>" class="btn-lab btn-lab-secondary" title="<?= htmlspecialchars(__('ApiTester::api_doc')) ?>">
                <i class="fa-solid fa-book"></i>
                <span>📖 <?= htmlspecialchars(__('ApiTester::api_doc')) ?></span>
            </a>

            <!-- AI Doc Link -->
            <a href="<?= baseUrl('docs?project_id=1') ?>" class="btn-lab btn-lab-secondary" title="<?= htmlspecialchars(__('ApiTester::ai_guide')) ?>">
                <i class="fa-solid fa-robot"></i>
                <span><?= htmlspecialchars(__('ApiTester::ai_guide')) ?></span>
            </a>

            <!-- Board Link -->
            <a href="<?= baseUrl('board') ?>" class="btn-lab btn-lab-primary">
                <i class="fa-solid fa-table-columns"></i>
                <span><?= htmlspecialchars(__('ApiTester::kanban_board')) ?></span>
            </a>
        </div>
    </header>

    <!-- MAIN WORKSPACE -->
    <div class="lab-workspace">
        
        <!-- LEFT SIDEBAR: ENDPOINTS -->
        <aside class="lab-sidebar">
            <div class="sidebar-search-box">
                <div class="search-input-wrapper">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="endpointSearchInput" class="sidebar-search-input" placeholder="<?= htmlspecialchars(__('ApiTester::search_endpoints_ph')) ?>">
                </div>
            </div>

            <div class="sidebar-endpoint-list" id="endpointContainer">
                <!-- Dynamically populated via JS -->
            </div>
        </aside>

        <!-- RIGHT CONTENT AREA -->
        <main class="lab-content">
            
            <!-- REQUEST URL BAR -->
            <div class="request-bar-panel">
                <select id="requestMethodSelect" class="method-select">
                    <option value="GET">GET</option>
                    <option value="POST">POST</option>
                    <option value="PUT">PUT</option>
                    <option value="DELETE">DELETE</option>
                </select>

                <div class="url-input-group">
                    <span class="url-prefix" id="urlPrefixDisplay"><?= htmlspecialchars($baseUrl) ?>/api/</span>
                    <input type="text" id="requestUrlInput" class="url-input" value="projects">
                </div>

                <button type="button" id="btnSendRequest" class="btn-send">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span><?= htmlspecialchars(__('ApiTester::btn_send')) ?></span>
                </button>
            </div>

            <!-- TWO COLUMN PANELS -->
            <div class="explorer-panels">
                
                <!-- LEFT: REQUEST CONFIGURATION -->
                <div class="request-config-panel">
                    <div class="panel-tab-bar">
                        <button type="button" class="panel-tab-btn active" data-tab="body"><?= htmlspecialchars(__('ApiTester::json_body_tab')) ?></button>
                        <button type="button" class="panel-tab-btn" data-tab="headers"><?= htmlspecialchars(__('ApiTester::headers_tab')) ?></button>
                        <button type="button" class="panel-tab-btn" data-tab="snippets"><?= htmlspecialchars(__('ApiTester::snippets_tab')) ?></button>
                        <button type="button" class="panel-tab-btn" data-tab="info"><?= htmlspecialchars(__('ApiTester::endpoint_info_tab')) ?></button>
                    </div>

                    <div class="tab-content-area">
                        <!-- TAB 1: JSON BODY -->
                        <div id="tabContentBody">
                            <div class="form-label-title">
                                <span><?= htmlspecialchars(__('ApiTester::json_payload_label')) ?></span>
                                <span class="api-lab-note-text"><?= htmlspecialchars(__('ApiTester::post_put_note')) ?></span>
                            </div>
                            <textarea id="requestBodyJson" class="form-textarea-json" spellcheck="false"></textarea>
                            <div class="api-lab-json-btn-row">
                                <button type="button" id="btnFormatJson" class="btn-lab btn-lab-secondary api-lab-btn-sm-action">
                                    <?= htmlspecialchars(__('ApiTester::btn_format_json')) ?>
                                </button>
                                <button type="button" id="btnClearJson" class="btn-lab btn-lab-secondary api-lab-btn-sm-action">
                                    <?= htmlspecialchars(__('ApiTester::btn_clear_json')) ?>
                                </button>
                            </div>
                        </div>

                        <!-- TAB 2: HEADERS -->
                        <div id="tabContentHeaders" class="tab-content-hidden">
                            <div class="api-lab-field-group">
                                <label class="form-label-title"><?= htmlspecialchars(__('ApiTester::auth_header_label')) ?></label>
                                <input type="text" id="headerAuthToken" class="form-input-text" value="Bearer <?= htmlspecialchars($userToken) ?>">
                            </div>
                            <div class="api-lab-field-group">
                                <label class="form-label-title"><?= htmlspecialchars(__('ApiTester::content_type_label')) ?></label>
                                <input type="text" id="headerContentType" class="form-input-text" value="application/json; charset=utf-8">
                            </div>
                            <div class="api-lab-field-group">
                                <label class="form-label-title"><?= htmlspecialchars(__('ApiTester::lang_header_label')) ?></label>
                                <select id="headerLanguageSelect" class="form-input-text api-lab-select-lang">
                                    <option value="tr" <?= $currentLang === 'tr' ? 'selected' : '' ?>>TR</option>
                                    <option value="en" <?= $currentLang === 'en' ? 'selected' : '' ?>>EN</option>
                                    <option value="de" <?= $currentLang === 'de' ? 'selected' : '' ?>>DE</option>
                                </select>
                            </div>
                        </div>

                        <!-- TAB 3: SNIPPETS (CURL & POWERSHELL) -->
                        <div id="tabContentSnippets" class="tab-content-hidden">
                            <div class="form-label-title">
                                <span><?= htmlspecialchars(__('ApiTester::curl_cmd_title')) ?></span>
                            </div>
                            <div class="snippet-card">
                                <button type="button" class="btn-copy-float" data-copy-target="curlSnippetPre"><?= htmlspecialchars(__('ApiTester::btn_copy')) ?></button>
                                <pre id="curlSnippetPre" class="api-lab-pre-reset"></pre>
                            </div>

                            <div class="form-label-title api-lab-label-spaced">
                                <span><?= htmlspecialchars(__('ApiTester::ps_cmd_title')) ?></span>
                            </div>
                            <div class="snippet-card">
                                <button type="button" class="btn-copy-float" data-copy-target="psSnippetPre"><?= htmlspecialchars(__('ApiTester::btn_copy')) ?></button>
                                <pre id="psSnippetPre" class="api-lab-pre-reset"></pre>
                            </div>
                        </div>

                        <!-- TAB 4: INFO -->
                        <div id="tabContentInfo" class="tab-content-hidden">
                            <h3 id="infoEndpointTitle" class="api-lab-info-heading"><?= htmlspecialchars(__('ApiTester::endpoint_title_default')) ?></h3>
                            <p id="infoEndpointDesc" class="api-lab-info-desc"><?= htmlspecialchars(__('ApiTester::endpoint_desc_default')) ?></p>
                            
                            <div class="api-lab-info-box">
                                <div class="api-lab-info-tip-label"><?= htmlspecialchars(__('ApiTester::doc_tip_label')) ?></div>
                                <div id="infoDocTip" class="api-lab-info-tip-text"><?= htmlspecialchars(__('ApiTester::doc_tip_default')) ?></div>
                            </div>

                            <div class="api-lab-info-section-title">
                                <i class="fa-solid fa-sliders"></i>
                                <span><?= htmlspecialchars(__('ApiTester::accepted_params_title')) ?></span>
                            </div>
                            <div id="infoParamsBox" class="api-lab-info-code-block"></div>

                            <div class="api-lab-info-section-title">
                                <i class="fa-solid fa-code"></i>
                                <span><?= htmlspecialchars(__('ApiTester::expected_response_title')) ?></span>
                            </div>
                            <div id="infoStatusPill" class="api-lab-info-status-pill status-2xx"></div>
                            <div id="infoResponseBox" class="api-lab-info-code-block"></div>

                            <a href="<?= baseUrl('api-docs') ?>" target="_blank" class="btn-lab btn-lab-secondary api-lab-info-doc-link">
                                <span><?= htmlspecialchars(__('ApiTester::open_doc_new_tab')) ?></span>
                                <i class="fa-solid fa-arrow-up-right-from-square api-lab-info-link-icon"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: LIVE RESPONSE VIEWER -->
                <div class="response-panel">
                    <div class="response-header">
                        <div class="api-lab-response-left">
                            <span id="responseStatusBadge" class="status-badge status-none"><?= htmlspecialchars(__('ApiTester::response_not_sent')) ?></span>
                            <div class="response-meta">
                                <span><i class="fa-regular fa-clock"></i> <span id="responseLatency">-</span></span>
                                <span><i class="fa-solid fa-database"></i> <span id="responseSize">-</span></span>
                            </div>
                        </div>

                        <div class="api-lab-response-actions">
                            <button type="button" id="btnCopyResponse" class="btn-lab btn-lab-secondary api-lab-btn-sm-action">
                                <i class="fa-regular fa-copy"></i>
                                <span><?= htmlspecialchars(__('ApiTester::btn_copy_response')) ?></span>
                            </button>
                        </div>
                    </div>

                    <div class="response-body-scroll" id="responseViewer">
                        <div class="api-lab-response-empty">
                            <i class="fa-solid fa-paper-plane api-lab-empty-icon"></i>
                            <div class="api-lab-empty-title"><?= htmlspecialchars(__('ApiTester::empty_response_title')) ?></div>
                            <div class="api-lab-empty-desc"><?= htmlspecialchars(__('ApiTester::empty_response_desc')) ?></div>
                        </div>
                    </div>
                </div>

            </div>

        </main>
    </div>

    <!-- BATCH HEALTH TEST MODAL -->
    <div id="batchTestModal" class="batch-modal-overlay">
        <div class="batch-modal">
            <div class="batch-modal-header">
                <div class="batch-modal-header-left">
                    <i class="fa-solid fa-heart-pulse batch-modal-pulse-icon"></i>
                    <h3 class="batch-modal-title-text"><?= htmlspecialchars(__('ApiTester::batch_modal_title')) ?></h3>
                </div>
                <button type="button" id="btnCloseBatchModalX" class="batch-modal-close-btn">&times;</button>
            </div>
            <div class="batch-modal-body">
                <p class="batch-modal-desc-text">
                    <?= htmlspecialchars(__('ApiTester::batch_modal_desc')) ?>
                </p>
                <div id="batchItemsList"></div>
            </div>
            <div class="batch-modal-footer-bar">
                <div id="batchSummaryStats" class="batch-modal-stats-text"><?= htmlspecialchars(__('ApiTester::batch_ready')) ?></div>
                <div class="batch-modal-btn-row">
                    <button type="button" id="btnCloseBatchModalBtn" class="btn-lab btn-lab-secondary"><?= htmlspecialchars(__('ApiTester::btn_close')) ?></button>
                    <button type="button" id="btnStartBatch" class="btn-lab btn-lab-success">
                        <i class="fa-solid fa-play"></i>
                        <span><?= htmlspecialchars(__('ApiTester::btn_start_scan')) ?></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div id="apitesterConfigCarrier" 
         data-base-url="<?= htmlspecialchars(rtrim($baseUrl, '/'), ENT_QUOTES, 'UTF-8') ?>"
         data-active-token="<?= htmlspecialchars($userToken, ENT_QUOTES, 'UTF-8') ?>"
         data-sample-project="<?= htmlspecialchars($sampleProject, ENT_QUOTES, 'UTF-8') ?>"
         data-sample-task="<?= htmlspecialchars($sampleTask, ENT_QUOTES, 'UTF-8') ?>"></div>
    <?= \Core\AssetManager::renderScripts() ?>
</body>
</html>
