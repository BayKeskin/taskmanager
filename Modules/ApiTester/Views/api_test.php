<?php
/**
 * TaskManager Enterprise REST API Live Test & Explorer Laboratory
 * Interactive Swagger / Postman style live testing console
 */
require_once dirname(__DIR__, 3) . '/Core/System.php';
require_once dirname(__DIR__, 3) . '/Core/Language.php';

Language::init();
$currentLang = Language::current();

$dynCfg = \Modules\RestApi\Services\RestApiService::getDynamicApiConfig();
$currentUser = $_SESSION['user'] ?? null;
$userToken = (!empty($currentUser->api_token)) ? $currentUser->api_token : ($dynCfg['api_token'] ?: 'tm_live_demo_token');
$baseUrl = $dynCfg['base_url'];

if (!isset($sampleProject) || !isset($sampleTask)) {
    $model = new \Modules\ApiTester\Models\ApiTesterModel();
    $sampleProject = $model->getFirstActiveProjectCode();
    $sampleTask = $model->getLatestTaskCode();
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧪 Canlı REST API Test Laboratuvarı - TaskManager</title>
    
    <!-- Google Fonts & FontAwesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="/taskmanager/Modules/ApiTester/Assets/css/api_test.css">
</head>
<body>

    <!-- TOP HEADER -->
    <header class="api-lab-header">
        <div class="header-title-box">
            <span style="font-size: 22px;">🧪</span>
            <div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <h1 style="font-size: 16px; font-weight: 800; margin:0;">TaskManager REST API Canlı Test Laboratuvarı</h1>
                    <span class="header-badge">v1.0 Canlı</span>
                </div>
                <div style="font-size: 11.5px; color: var(--text-secondary); margin-top: 2px;">
                    Uç noktaları tarayıcınızdan parametrelerle test edin, cURL/PowerShell kodlarını kopyalayın
                </div>
            </div>
        </div>

        <div class="header-actions">
            <!-- Token Box -->
            <div style="background:rgba(123,104,238,0.12); border:1px solid rgba(123,104,238,0.3); border-radius:6px; padding:4px 10px; display:flex; align-items:center; gap:8px;">
                <span style="font-size:11px; color:#a5b4fc; font-weight:600;">🔑 Token:</span>
                <span id="activeTokenDisplay" style="font-family:var(--font-mono); font-size:11px; color:#fff; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    <?= htmlspecialchars($userToken) ?>
                </span>
                <button type="button" onclick="copyActiveToken()" style="background:none; border:none; color:#c7d2fe; cursor:pointer; font-size:12px;" title="Token Kopyala">
                    <i class="fa-regular fa-copy"></i>
                </button>
            </div>

            <!-- Batch Test Button -->
            <button type="button" onclick="openBatchModal()" class="btn-lab btn-lab-success">
                <i class="fa-solid fa-heart-pulse"></i>
                <span>Çekirdek Sağlık Taraması</span>
            </button>

            <!-- API Docs Link -->
            <a href="/taskmanager/api-docs" class="btn-lab btn-lab-secondary" title="Tam API Dokümantasyonunu Aç">
                <i class="fa-solid fa-book"></i>
                <span>📖 API Dokümanı</span>
            </a>

            <!-- AI Doc Link -->
            <a href="/taskmanager/docs/4?project_id=1" class="btn-lab btn-lab-secondary" title="Yapay Zeka Rehberi">
                <i class="fa-solid fa-robot"></i>
                <span>AI Rehberi</span>
            </a>

            <!-- Board Link -->
            <a href="/taskmanager/board" class="btn-lab btn-lab-primary">
                <i class="fa-solid fa-table-columns"></i>
                <span>Kanban Panosu</span>
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
                    <input type="text" id="endpointSearchInput" class="sidebar-search-input" placeholder="Endpoint veya metod ara (/tasks, submit...)" oninput="filterEndpoints()">
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
                <select id="requestMethodSelect" class="method-select" onchange="updateMethodColor(this.value)">
                    <option value="GET">GET</option>
                    <option value="POST">POST</option>
                    <option value="PUT">PUT</option>
                    <option value="DELETE">DELETE</option>
                </select>

                <div class="url-input-group">
                    <span class="url-prefix" id="urlPrefixDisplay"><?= htmlspecialchars($baseUrl) ?>/api/</span>
                    <input type="text" id="requestUrlInput" class="url-input" value="projects" oninput="updateSnippetViews()">
                </div>

                <button type="button" id="btnSendRequest" class="btn-send" onclick="executeCurrentRequest()">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>Gönder</span>
                </button>
            </div>

            <!-- TWO COLUMN PANELS -->
            <div class="explorer-panels">
                
                <!-- LEFT: REQUEST CONFIGURATION -->
                <div class="request-config-panel">
                    <div class="panel-tab-bar">
                        <button type="button" class="panel-tab-btn active" onclick="switchRequestTab('body')">JSON Body</button>
                        <button type="button" class="panel-tab-btn" onclick="switchRequestTab('headers')">Headers</button>
                        <button type="button" class="panel-tab-btn" onclick="switchRequestTab('snippets')">cURL / PowerShell</button>
                        <button type="button" class="panel-tab-btn" onclick="switchRequestTab('info')">Endpoint Bilgisi</button>
                    </div>

                    <div class="tab-content-area">
                        <!-- TAB 1: JSON BODY -->
                        <div id="tabContentBody">
                            <div class="form-label-title">
                                <span>İstek Gövdesi (JSON Payload)</span>
                                <span style="font-size:11px; color:var(--text-muted);">POST / PUT İstekleri İçin</span>
                            </div>
                            <textarea id="requestBodyJson" class="form-textarea-json" spellcheck="false" oninput="updateSnippetViews()"></textarea>
                            <div style="display:flex; justify-content:space-between; margin-top:8px;">
                                <button type="button" class="btn-lab btn-lab-secondary" style="font-size:11.5px; padding:4px 10px;" onclick="formatJsonEditor()">
                                    ✨ JSON Formatla
                                </button>
                                <button type="button" class="btn-lab btn-lab-secondary" style="font-size:11.5px; padding:4px 10px;" onclick="clearJsonEditor()">
                                    ✕ Temizle
                                </button>
                            </div>
                        </div>

                        <!-- TAB 2: HEADERS -->
                        <div id="tabContentHeaders" style="display:none;">
                            <div style="margin-bottom:14px;">
                                <label class="form-label-title">Authorization (Bearer Token)</label>
                                <input type="text" id="headerAuthToken" class="form-input-text" value="Bearer <?= htmlspecialchars($userToken) ?>" oninput="updateSnippetViews()">
                            </div>
                            <div style="margin-bottom:14px;">
                                <label class="form-label-title">Content-Type</label>
                                <input type="text" id="headerContentType" class="form-input-text" value="application/json; charset=utf-8" oninput="updateSnippetViews()">
                            </div>
                            <div style="margin-bottom:14px;">
                                <label class="form-label-title">Dil Başlığı (X-Language / Accept-Language)</label>
                                <select id="headerLanguageSelect" class="form-input-text" style="height:36px;" onchange="updateSnippetViews()">
                                    <option value="tr" <?= $currentLang === 'tr' ? 'selected' : '' ?>>Türkçe (tr)</option>
                                    <option value="en" <?= $currentLang === 'en' ? 'selected' : '' ?>>English (en)</option>
                                    <option value="de" <?= $currentLang === 'de' ? 'selected' : '' ?>>Deutsch (de)</option>
                                </select>
                            </div>
                        </div>

                        <!-- TAB 3: SNIPPETS (CURL & POWERSHELL) -->
                        <div id="tabContentSnippets" style="display:none;">
                            <div class="form-label-title">
                                <span>Terminal / cURL Komutu</span>
                            </div>
                            <div class="snippet-card">
                                <button type="button" class="btn-copy-float" onclick="copySnippetText('curlSnippetPre')">📋 Kopyala</button>
                                <pre id="curlSnippetPre" style="margin:0;"></pre>
                            </div>

                            <div class="form-label-title" style="margin-top:20px;">
                                <span>Windows PowerShell Komutu (Ajan Standardı)</span>
                            </div>
                            <div class="snippet-card">
                                <button type="button" class="btn-copy-float" onclick="copySnippetText('psSnippetPre')">📋 Kopyala</button>
                                <pre id="psSnippetPre" style="margin:0;"></pre>
                            </div>
                        </div>

                        <!-- TAB 4: INFO -->
                        <div id="tabContentInfo" style="display:none;">
                            <h3 id="infoEndpointTitle" style="font-size:15px; font-weight:700; color:var(--text-white); margin-bottom:8px;">Endpoint Başlığı</h3>
                            <p id="infoEndpointDesc" style="font-size:13px; color:var(--text-secondary); line-height:1.6; margin-bottom:16px;">Açıklama</p>
                            
                            <div style="background:rgba(255,255,255,0.03); border:1px solid var(--border-subtle); border-radius:8px; padding:12px; margin-bottom:16px;">
                                <div style="font-size:12px; font-weight:700; color:var(--brand-primary); margin-bottom:4px;">📖 Dokümantasyon İpucu:</div>
                                <div id="infoDocTip" style="font-size:12px; color:var(--text-muted); line-height:1.5;">Bu uç nokta hakkında detaylı bilgi için resmi dokümanı ziyaret edin.</div>
                            </div>
                            <a href="/taskmanager/api-docs" target="_blank" class="btn-lab btn-lab-secondary" style="font-size:12px;">
                                <span>Ayrıntılı Dokümanı Yeni Sekmede Aç</span>
                                <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:11px;"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: LIVE RESPONSE VIEWER -->
                <div class="response-panel">
                    <div class="response-header">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <span id="responseStatusBadge" class="status-badge status-none">HENÜZ İSTEK ATILMADI</span>
                            <div class="response-meta">
                                <span><i class="fa-regular fa-clock"></i> <span id="responseLatency">-</span></span>
                                <span><i class="fa-solid fa-database"></i> <span id="responseSize">-</span></span>
                            </div>
                        </div>

                        <div style="display:flex; gap:8px;">
                            <button type="button" class="btn-lab btn-lab-secondary" style="padding:4px 10px; font-size:11.5px;" onclick="copyResponseBody()">
                                <i class="fa-regular fa-copy"></i>
                                <span>Yanıtı Kopyala</span>
                            </button>
                        </div>
                    </div>

                    <div class="response-body-scroll" id="responseViewer">
                        <div style="color:var(--text-muted); text-align:center; padding:60px 20px;">
                            <i class="fa-solid fa-paper-plane" style="font-size:32px; margin-bottom:12px; opacity:0.3;"></i>
                            <div style="font-size:14px; font-weight:600;">Sol menüden bir endpoint seçin ve "Gönder" butonuna basın.</div>
                            <div style="font-size:12px; margin-top:4px;">Gerçek zamanlı sunucu yanıtı, HTTP kodu ve süre burada görüntülenecektir.</div>
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
                <div style="display:flex; align-items:center; gap:10px;">
                    <i class="fa-solid fa-heart-pulse" style="color:var(--accent-green); font-size:18px;"></i>
                    <h3 style="font-size:16px; font-weight:700; margin:0;">Çekirdek REST API Sağlık Taraması</h3>
                </div>
                <button type="button" onclick="closeBatchModal()" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:18px;">&times;</button>
            </div>
            <div class="batch-modal-body">
                <p style="font-size:12.5px; color:var(--text-secondary); margin-bottom:16px;">
                    Aşağıdaki çekirdek API uç noktaları sırayla canlı olarak test edilecek ve sistemin sağlığı raporlanacaktır.
                </p>
                <div id="batchItemsList"></div>
            </div>
            <div style="padding:14px 20px; border-top:1px solid var(--border-subtle); display:flex; justify-content:space-between; align-items:center; background:rgba(0,0,0,0.2);">
                <div id="batchSummaryStats" style="font-size:12.5px; font-weight:600; color:var(--text-secondary);">Hazır</div>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn-lab btn-lab-secondary" onclick="closeBatchModal()">Kapat</button>
                    <button type="button" id="btnStartBatch" class="btn-lab btn-lab-success" onclick="runBatchTests()">
                        <i class="fa-solid fa-play"></i>
                        <span>Taramayı Başlat</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

        <script>
        window.APITESTER_CONFIG = {
            baseUrl: <?= json_encode(rtrim($baseUrl, '/')) ?>,
            activeToken: <?= json_encode($userToken) ?>,
            sampleProject: <?= json_encode($sampleProject) ?>,
            sampleTask: <?= json_encode($sampleTask) ?>
        };
    </script>
    <script src="/taskmanager/Modules/ApiTester/Assets/js/api_test.js"></script>
</body>
</html>
