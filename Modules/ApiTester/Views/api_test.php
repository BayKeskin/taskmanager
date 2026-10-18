<?php
/**
 * TaskManager Enterprise REST API Live Test & Explorer Laboratory
 * Interactive Swagger / Postman style live testing console
 */
require_once dirname(__DIR__, 3) . '/Core/System.php';
require_once dirname(__DIR__, 3) . '/Core/Language.php';

Language::init();
$currentLang = Language::current();

global $db;
$dynCfg = Helper::getDynamicApiConfig();
$currentUser = $_SESSION['user'] ?? null;
$userToken = (!empty($currentUser->api_token)) ? $currentUser->api_token : ($dynCfg['api_token'] ?: 'tm_live_demo_token');
$baseUrl = $dynCfg['base_url'];

// Örnek proje ve görevleri otomatik çek (pre-fill kolaylığı için)
$sampleProject = '01';
$sampleTask = 'TSK-101';
try {
    if ($db && !empty($db->connect)) {
        $pRow = $db->row_query("SELECT project_code FROM projects WHERE status = 'active' ORDER BY id ASC LIMIT 1");
        if ($pRow && !empty($pRow->project_code)) $sampleProject = $pRow->project_code;

        $tRow = $db->row_query("SELECT task_code FROM tasks ORDER BY id DESC LIMIT 1");
        if ($tRow && !empty($tRow->task_code)) $sampleTask = $tRow->task_code;
    }
} catch (\Throwable $e) {}
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

    <style>
        :root {
            --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-mono: 'JetBrains Mono', Consolas, Monaco, monospace;
            --bg-base: #0b1120;
            --bg-surface: #0f172a;
            --bg-card: #1e293b;
            --bg-card-hover: #273549;
            --border-subtle: #334155;
            --border-focus: #7B68EE;
            --brand-primary: #7B68EE;
            --brand-primary-hover: #6952e0;
            --accent-green: #10b981;
            --accent-blue: #3b82f6;
            --accent-amber: #f59e0b;
            --accent-red: #ef4444;
            --accent-cyan: #06b6d4;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font-sans);
            background-color: var(--bg-base);
            color: var(--text-primary);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* Top Header */
        header.api-lab-header {
            height: 60px;
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            flex-shrink: 0;
            z-index: 100;
        }
        .header-title-box {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header-badge {
            background: rgba(16, 185, 129, 0.15);
            color: var(--accent-green);
            border: 1px solid rgba(16, 185, 129, 0.3);
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn-lab {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 12.5px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }
        .btn-lab-primary {
            background: var(--brand-primary);
            color: #fff;
        }
        .btn-lab-primary:hover {
            background: var(--brand-primary-hover);
        }
        .btn-lab-secondary {
            background: rgba(255, 255, 255, 0.06);
            border-color: var(--border-subtle);
            color: var(--text-primary);
        }
        .btn-lab-secondary:hover {
            background: rgba(255, 255, 255, 0.12);
        }
        .btn-lab-success {
            background: rgba(16, 185, 129, 0.18);
            border-color: rgba(16, 185, 129, 0.4);
            color: #34d399;
        }
        .btn-lab-success:hover {
            background: rgba(16, 185, 129, 0.28);
        }

        /* Main Workspace Split */
        .lab-workspace {
            display: flex;
            flex: 1;
            height: calc(100vh - 60px);
            overflow: hidden;
        }

        /* Left Sidebar: Endpoints */
        .lab-sidebar {
            width: 360px;
            min-width: 320px;
            max-width: 420px;
            background: var(--bg-surface);
            border-right: 1px solid var(--border-subtle);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .sidebar-search-box {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-subtle);
            background: rgba(0, 0, 0, 0.2);
        }
        .search-input-wrapper {
            position: relative;
        }
        .search-input-wrapper i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 13px;
        }
        .sidebar-search-input {
            width: 100%;
            height: 36px;
            background: var(--bg-base);
            border: 1px solid var(--border-subtle);
            border-radius: 6px;
            padding: 0 12px 0 34px;
            color: var(--text-primary);
            font-size: 13px;
            outline: none;
            transition: border-color 0.2s;
        }
        .sidebar-search-input:focus {
            border-color: var(--brand-primary);
        }
        .sidebar-endpoint-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px 0;
        }
        .endpoint-category-title {
            padding: 10px 16px 4px 16px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .endpoint-item {
            padding: 8px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.15s ease;
            border-left: 3px solid transparent;
            text-decoration: none;
        }
        .endpoint-item:hover {
            background: rgba(255, 255, 255, 0.04);
        }
        .endpoint-item.active {
            background: rgba(123, 104, 238, 0.12);
            border-left-color: var(--brand-primary);
        }
        .endpoint-name {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
        }
        .endpoint-path-sub {
            font-size: 11px;
            font-family: var(--font-mono);
            color: var(--text-muted);
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Method Badges */
        .method-badge {
            font-size: 10px;
            font-weight: 800;
            font-family: var(--font-mono);
            padding: 2px 6px;
            border-radius: 4px;
            min-width: 44px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            flex-shrink: 0;
        }
        .method-get    { background: rgba(59, 130, 246, 0.18); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
        .method-post   { background: rgba(16, 185, 129, 0.18); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .method-put    { background: rgba(245, 158, 11, 0.18); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .method-delete { background: rgba(239, 68, 68, 0.18); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }

        /* Right Content Panel */
        .lab-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: var(--bg-base);
        }

        /* URL Request Bar */
        .request-bar-panel {
            padding: 16px 20px;
            background: var(--bg-surface);
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .method-select {
            height: 42px;
            font-family: var(--font-mono);
            font-weight: 800;
            font-size: 13px;
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            color: var(--accent-green);
            padding: 0 12px;
            border-radius: 6px;
            outline: none;
            cursor: pointer;
        }
        .url-input-group {
            flex: 1;
            display: flex;
            align-items: center;
            background: var(--bg-base);
            border: 1px solid var(--border-subtle);
            border-radius: 6px;
            padding: 0 14px;
            height: 42px;
            transition: border-color 0.2s;
        }
        .url-input-group:focus-within {
            border-color: var(--brand-primary);
        }
        .url-prefix {
            font-family: var(--font-mono);
            font-size: 12.5px;
            color: var(--text-muted);
            margin-right: 4px;
            user-select: none;
        }
        .url-input {
            flex: 1;
            background: transparent;
            border: none;
            color: var(--text-primary);
            font-family: var(--font-mono);
            font-size: 13px;
            outline: none;
        }
        .btn-send {
            height: 42px;
            padding: 0 24px;
            background: linear-gradient(135deg, #7B68EE 0%, #6350d8 100%);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(123, 104, 238, 0.35);
            transition: all 0.2s ease;
        }
        .btn-send:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(123, 104, 238, 0.45);
        }

        /* Two Column Explorer Workspace */
        .explorer-panels {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 1.15fr;
            overflow: hidden;
        }

        /* Request Config Panel */
        .request-config-panel {
            border-right: 1px solid var(--border-subtle);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: var(--bg-surface);
        }
        .panel-tab-bar {
            display: flex;
            gap: 4px;
            padding: 8px 16px;
            background: rgba(0, 0, 0, 0.15);
            border-bottom: 1px solid var(--border-subtle);
        }
        .panel-tab-btn {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-size: 12.5px;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .panel-tab-btn.active {
            background: var(--bg-card);
            color: var(--text-primary);
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }
        .tab-content-area {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
        }

        /* Form Controls */
        .form-label-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }
        .form-input-text {
            width: 100%;
            height: 36px;
            background: var(--bg-base);
            border: 1px solid var(--border-subtle);
            border-radius: 6px;
            padding: 0 12px;
            color: var(--text-primary);
            font-size: 12.5px;
            font-family: var(--font-mono);
            outline: none;
        }
        .form-input-text:focus {
            border-color: var(--brand-primary);
        }
        .form-textarea-json {
            width: 100%;
            height: 240px;
            background: #090d16;
            border: 1px solid var(--border-subtle);
            border-radius: 6px;
            padding: 12px;
            color: #38bdf8;
            font-family: var(--font-mono);
            font-size: 12.5px;
            line-height: 1.5;
            outline: none;
            resize: vertical;
        }
        .form-textarea-json:focus {
            border-color: var(--brand-primary);
        }

        /* Response Panel */
        .response-panel {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #070b14;
        }
        .response-header {
            padding: 10px 18px;
            background: rgba(15, 23, 42, 0.8);
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .status-badge {
            font-family: var(--font-mono);
            font-size: 12px;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 4px;
        }
        .status-2xx { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); }
        .status-4xx { background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.4); }
        .status-5xx { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); }
        .status-none { background: rgba(100, 116, 139, 0.2); color: #94a3b8; border: 1px solid var(--border-subtle); }

        .response-meta {
            display: flex;
            gap: 14px;
            font-size: 12px;
            font-family: var(--font-mono);
            color: var(--text-secondary);
        }
        .response-body-scroll {
            flex: 1;
            overflow: auto;
            padding: 16px;
            font-family: var(--font-mono);
            font-size: 12.5px;
            line-height: 1.6;
        }

        /* Batch Modal */
        .batch-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            z-index: 200;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .batch-modal {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            width: 100%;
            max-width: 680px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
        }
        .batch-modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .batch-modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }
        .batch-item-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-subtle);
            border-radius: 6px;
            margin-bottom: 8px;
            font-size: 13px;
        }

        /* Code snippets */
        .snippet-card {
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--border-subtle);
            border-radius: 6px;
            padding: 12px;
            font-family: var(--font-mono);
            font-size: 12px;
            color: #a5b4fc;
            position: relative;
            margin-top: 10px;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .btn-copy-float {
            position: absolute;
            right: 8px;
            top: 8px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid var(--border-subtle);
            color: #fff;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
        }
    </style>
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
        const BASE_API_URL = '<?= rtrim($baseUrl, '/') ?>/api';
        const ACTIVE_TOKEN = '<?= addslashes($userToken) ?>';
        const SAMPLE_PROJECT = '<?= addslashes($sampleProject) ?>';
        const SAMPLE_TASK = '<?= addslashes($sampleTask) ?>';

        // Endpoint Kataloğu
        const ENDPOINTS = [
            // Kategori 1: Sistem & Sağlık
            {
                category: '⚡ Sistem & Sağlık',
                id: 'health',
                method: 'GET',
                name: 'API Sağlık & Durum',
                path: 'health',
                desc: 'API servisinin ayakta olup olmadığını ve güncel sürüm durumunu döner.',
                body: null,
                tip: 'Halka açık (public) bir uç noktadır; token gerektirmez.'
            },
            {
                category: '⚡ Sistem & Sağlık',
                id: 'auth_me',
                method: 'GET',
                name: 'Aktif Oturum Bilgisi (/auth/me)',
                path: 'auth/me',
                desc: 'Bearer token sahibinin kullanıcı bilgilerini, rolünü ve aktif görev sayısını döner.',
                body: null,
                tip: 'Token doğruluğunu kontrol etmek için ilk başvurulan uç noktadır.'
            },
            {
                category: '⚡ Sistem & Sağlık',
                id: 'auth_regen',
                method: 'POST',
                name: 'Token Yenile (/auth/regenerate-token)',
                path: 'auth/regenerate-token',
                desc: 'Mevcut kullanıcının API anahtarını güvenli bir şekilde sıfırlayıp yeni bir tm_live_... token üretir.',
                body: {},
                tip: 'Üretilen yeni token veritabanına otomatik işlenir.'
            },

            // Kategori 2: Projeler (Spaces)
            {
                category: '🏛️ Projeler (Spaces)',
                id: 'get_projects',
                method: 'GET',
                name: 'Tüm Projeleri Listele',
                path: 'projects',
                desc: 'Sistemdeki tüm aktif projeleri görev istatistikleriyle birlikte listeler.',
                body: null,
                tip: '?status=active veya ?status=all parametreleri eklenebilir.'
            },
            {
                category: '🏛️ Projeler (Spaces)',
                id: 'get_project_detail',
                method: 'GET',
                name: 'Tekil Proje Detayı',
                path: 'projects/' + SAMPLE_PROJECT,
                desc: 'Belirli bir projenin tam detaylarını, durum dağılımını ve metriklerini getirir.',
                body: null,
                tip: 'Proje ID (örn: 1) veya Proje Kodu (örn: 01, TKMDEV-01) kullanılabilir.'
            },
            {
                category: '🏛️ Projeler (Spaces)',
                id: 'get_project_tasks',
                method: 'GET',
                name: 'Projenin Görevlerini Çek',
                path: 'projects/' + SAMPLE_PROJECT + '/tasks',
                desc: 'Belirli bir projeye ait görevleri listeler. Durum ve öncelik filtreleri desteklenir.',
                body: null,
                tip: 'Ajanlar için filtre örneği: ?status=assigned&priority=urgent'
            },
            {
                category: '🏛️ Projeler (Spaces)',
                id: 'get_project_docs',
                method: 'GET',
                name: 'Proje Dökümanları (Docs)',
                path: 'projects/' + SAMPLE_PROJECT + '/docs',
                desc: 'Projeye ait FTP, mimari ve kılavuz dökümanlarının listesini ve tam içeriklerini döner.',
                body: null,
                tip: 'Yapay zeka ajanları projenin FTP veya veritabanı şifrelerini buradan okur.'
            },
            {
                category: '🏛️ Projeler (Spaces)',
                id: 'post_project_docs',
                method: 'POST',
                name: 'Projeye Döküman Ekle',
                path: 'projects/' + SAMPLE_PROJECT + '/docs',
                desc: 'Projeye doğrudan teknik bir doküman veya kılavuz kaydeder.',
                body: {
                    title: "Test Dokümanı",
                    icon: "📄",
                    content: "### Doküman Başlığı\nBu doküman API Test Konsolu üzerinden oluşturulmuştur."
                },
                tip: 'Ajanlar geliştirdikleri modüllerin mimari notlarını bu uçla ekleyebilir.'
            },
            {
                category: '🏛️ Projeler (Spaces)',
                id: 'test_project_webhook',
                method: 'POST',
                name: 'Webhook Test Pingi Gönder',
                path: 'projects/' + SAMPLE_PROJECT + '/webhook/test',
                desc: 'Projede tanımlı olan yerel veya canlı webhook adresine anında test pingi gönderir.',
                body: {},
                tip: 'Port 4000 (Dispatcher) veya Cloudflare tünelinin canlılığını test eder.'
            },

            // Kategori 3: Görevler (Tasks & Kanban)
            {
                category: '📋 Görevler (Tasks)',
                id: 'get_task_detail',
                method: 'GET',
                name: 'Tekil Görev Detayı',
                path: 'tasks/' + SAMPLE_TASK,
                desc: 'Görevin tam detaylarını, kontrol listesini, aktivitelerini ve atanan kişileri döner.',
                body: null,
                tip: 'Görev ID veya Görev Kodu (örn: TSK-101) ile sorgulanabilir.'
            },
            {
                category: '📋 Görevler (Tasks)',
                id: 'create_task',
                method: 'POST',
                name: 'Yeni Görev Oluştur',
                path: 'projects/' + SAMPLE_PROJECT + '/tasks',
                desc: 'Projede yeni bir görev kartı oluşturur.',
                body: {
                    title: "API Test Konsolundan Yeni Görev",
                    description: "Bu görev test amacıyla REST API üzerinden oluşturuldu.",
                    priority: "normal",
                    tags: "test,api",
                    time_estimate: "2h"
                },
                tip: 'priority: low | normal | high | urgent değerlerini alabilir.'
            },
            {
                category: '📋 Görevler (Tasks)',
                id: 'update_task',
                method: 'PUT',
                name: 'Görev Bilgilerini Güncelle',
                path: 'tasks/' + SAMPLE_TASK,
                desc: 'Görevin başlık, açıklama veya önceliğini günceller.',
                body: {
                    title: "Güncellenmiş Görev Başlığı",
                    priority: "high"
                },
                tip: 'Sadece gönderilen alanlar güncellenir.'
            },
            {
                category: '📋 Görevler (Tasks)',
                id: 'get_task_checklists',
                method: 'GET',
                name: 'Görev Kontrol Listesi',
                path: 'tasks/' + SAMPLE_TASK + '/checklists',
                desc: 'Görevin kontrol maddelerini listeler.',
                body: null,
                tip: 'Kontrol listesi durumunu görmek için kullanılır.'
            },
            {
                category: '📋 Görevler (Tasks)',
                id: 'post_task_comment',
                method: 'POST',
                name: 'Göreve Yorum / Rapor Ekle',
                path: 'tasks/' + SAMPLE_TASK + '/comments',
                desc: 'Görevin altına ilerleme raporu veya geliştirici yorumu ekler.',
                body: {
                    comment: "API Test Konsolu: Geliştirme adımları ve testler tamamlandı."
                },
                tip: 'Ajanlar tamamladıkları her aşamayı buradan yorum olarak kaydeder.'
            },

            // Kategori 4: Otonom İş Akışı (AI Agent Lifecycle)
            {
                category: '🤖 Otonom İş Akışı (AI Agent)',
                id: 'wf_accept',
                method: 'POST',
                name: 'Görevi Kabul Et (in_progress)',
                path: 'tasks/' + SAMPLE_TASK + '/workflow/accept',
                desc: 'Görevi "Çalışılıyor (in_progress)" durumuna geçirir ve zaman sayacını başlatır.',
                body: {},
                tip: 'Kanban panosunda kart anında Çalışılıyor sütununa kayar.'
            },
            {
                category: '🤖 Otonom İş Akışı (AI Agent)',
                id: 'wf_submit',
                method: 'POST',
                name: 'Görevi Teslim Et (submitted - DoD)',
                path: 'tasks/' + SAMPLE_TASK + '/workflow/submit',
                desc: 'Görevi Definition of Done (DoD) raporuyla birlikte İncelemede sütununa taşır. Alt görev rollup ve 4sn kuyruk motorunu tetikler.',
                body: {
                    notes: "1. What Was Done: API testleri yapıldı.\n2. Where to Test: /board\n3. How to Test: Kart kontrol edildi.\n4. Test Evidence: HTTP 200 OK alındı.",
                    attachment_url: "https://github.com/BayKeskin/taskmanager"
                },
                tip: 'Ajanların resmi teslimat uç noktasıdır. notes parametresi zorunludur.'
            },
            {
                category: '🤖 Otonom İş Akışı (AI Agent)',
                id: 'wf_approve',
                method: 'POST',
                name: 'Yönetici Onayı (completed)',
                path: 'tasks/' + SAMPLE_TASK + '/workflow/approve',
                desc: 'Teslim edilen görevi onaylar ve "Tamamlandı (completed)" durumuna taşır.',
                body: {},
                tip: 'Yalnızca yönetici veya müşteri onayı ile çalışır.'
            },
            {
                category: '🤖 Otonom İş Akışı (AI Agent)',
                id: 'wf_reject',
                method: 'POST',
                name: 'Revizyon / Düzeltme İste',
                path: 'tasks/' + SAMPLE_TASK + '/workflow/reject',
                desc: 'Görevi revizyon açıklamasıyla "Revizyon İstendi (modification_requested)" sütununa gönderir.',
                body: {
                    reason: "Mobil görünümde buton hizalaması düzeltilmeli."
                },
                tip: 'Ajan için yeni bir revizyon turu başlatır.'
            },

            // Kategori 5: Kullanıcılar
            {
                category: '👥 Kullanıcılar (Users)',
                id: 'get_users',
                method: 'GET',
                name: 'Tüm Kullanıcıları Listele',
                path: 'users',
                desc: 'Sistemdeki aktif kullanıcıları ve rollerini döner.',
                body: null,
                tip: 'Yeni görev atamalarında kullanıcı ID seçmek için kullanılır.'
            }
        ];

        let currentActiveEndpoint = ENDPOINTS[0];

        // Sayfa Yüklendiğinde Başlat
        document.addEventListener('DOMContentLoaded', () => {
            renderEndpointList();
            selectEndpoint(ENDPOINTS[0].id);
        });

        // Sol Menü Endpoint Listesi Render
        function renderEndpointList(filtered = null) {
            const list = filtered || ENDPOINTS;
            const container = document.getElementById('endpointContainer');
            container.innerHTML = '';

            let currentCategory = '';
            list.forEach(ep => {
                if (ep.category !== currentCategory) {
                    currentCategory = ep.category;
                    const catEl = document.createElement('div');
                    catEl.className = 'endpoint-category-title';
                    catEl.innerText = currentCategory;
                    container.appendChild(catEl);
                }

                const item = document.createElement('div');
                item.className = 'endpoint-item' + (currentActiveEndpoint && currentActiveEndpoint.id === ep.id ? ' active' : '');
                item.id = 'epItem_' + ep.id;
                item.onclick = () => selectEndpoint(ep.id);

                const mBadge = document.createElement('span');
                mBadge.className = 'method-badge method-' + ep.method.toLowerCase();
                mBadge.innerText = ep.method;

                const textDiv = document.createElement('div');
                textDiv.style.flex = '1';
                textDiv.style.minWidth = '0';
                textDiv.innerHTML = `
                    <div class="endpoint-name">${escapeHtml(ep.name)}</div>
                    <span class="endpoint-path-sub">/${escapeHtml(ep.path)}</span>
                `;

                item.appendChild(mBadge);
                item.appendChild(textDiv);
                container.appendChild(item);
            });
        }

        // Endpoint Filtreleme
        function filterEndpoints() {
            const q = document.getElementById('endpointSearchInput').value.toLowerCase().trim();
            if (!q) {
                renderEndpointList();
                return;
            }
            const filtered = ENDPOINTS.filter(ep => 
                ep.name.toLowerCase().includes(q) || 
                ep.path.toLowerCase().includes(q) || 
                ep.method.toLowerCase().includes(q) ||
                ep.category.toLowerCase().includes(q)
            );
            renderEndpointList(filtered);
        }

        // Endpoint Seçildiğinde
        function selectEndpoint(id) {
            const ep = ENDPOINTS.find(e => e.id === id);
            if (!ep) return;
            currentActiveEndpoint = ep;

            // Highlight sidebar item
            document.querySelectorAll('.endpoint-item').forEach(el => el.classList.remove('active'));
            const activeEl = document.getElementById('epItem_' + ep.id);
            if (activeEl) activeEl.classList.add('active');

            // Set method & URL
            const methodSelect = document.getElementById('requestMethodSelect');
            methodSelect.value = ep.method;
            updateMethodColor(ep.method);

            document.getElementById('requestUrlInput').value = ep.path;

            // Set JSON Body
            const bodyArea = document.getElementById('requestBodyJson');
            if (ep.body !== null) {
                bodyArea.value = JSON.stringify(ep.body, null, 2);
                switchRequestTab('body');
            } else {
                bodyArea.value = '';
                switchRequestTab('headers');
            }

            // Set Info tab
            document.getElementById('infoEndpointTitle').innerText = ep.method + ' /api/' + ep.path + ' - ' + ep.name;
            document.getElementById('infoEndpointDesc').innerText = ep.desc;
            document.getElementById('infoDocTip').innerText = ep.tip;

            // Snippets güncelle
            updateSnippetViews();
        }

        // Metod Rengine Göre Select Kutusu Rengi
        function updateMethodColor(method) {
            const el = document.getElementById('requestMethodSelect');
            if (method === 'GET') el.style.color = '#60a5fa';
            else if (method === 'POST') el.style.color = '#34d399';
            else if (method === 'PUT') el.style.color = '#fbbf24';
            else if (method === 'DELETE') el.style.color = '#f87171';
            updateSnippetViews();
        }

        // Tab Değiştirme
        function switchRequestTab(tabKey) {
            const tabs = ['body', 'headers', 'snippets', 'info'];
            tabs.forEach(t => {
                const el = document.getElementById('tabContent' + capitalize(t));
                if (el) el.style.display = (t === tabKey) ? 'block' : 'none';
            });

            document.querySelectorAll('.panel-tab-btn').forEach((btn, idx) => {
                btn.classList.toggle('active', tabs[idx] === tabKey);
            });
        }

        // Canlı İstek Gönder (Fetch Execution)
        async function executeCurrentRequest() {
            const method = document.getElementById('requestMethodSelect').value;
            const subPath = document.getElementById('requestUrlInput').value.trim();
            const fullUrl = BASE_API_URL + '/' + subPath.replace(/^\/+/, '');

            const authHeader = document.getElementById('headerAuthToken').value.trim();
            const contentType = document.getElementById('headerContentType').value.trim();
            const lang = document.getElementById('headerLanguageSelect').value;

            const headers = {
                'Accept': 'application/json',
                'X-Language': lang
            };
            if (authHeader) headers['Authorization'] = authHeader;
            if (contentType && (method === 'POST' || method === 'PUT')) {
                headers['Content-Type'] = contentType;
            }

            let body = undefined;
            if (method === 'POST' || method === 'PUT') {
                const rawJson = document.getElementById('requestBodyJson').value.trim();
                if (rawJson) {
                    try {
                        JSON.parse(rawJson); // Test parse
                        body = rawJson;
                    } catch (e) {
                        alert('JSON İstek Gövdesinde sözdizimi hatası var:\n' + e.message);
                        switchRequestTab('body');
                        return;
                    }
                }
            }

            const sendBtn = document.getElementById('btnSendRequest');
            sendBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>İstek Atılıyor...</span>';
            sendBtn.disabled = true;

            const statusBadge = document.getElementById('responseStatusBadge');
            statusBadge.className = 'status-badge status-none';
            statusBadge.innerText = 'Bekleniyor...';

            const viewer = document.getElementById('responseViewer');
            const startTime = performance.now();

            try {
                const resp = await fetch(fullUrl, {
                    method: method,
                    headers: headers,
                    body: body
                });

                const latency = Math.round(performance.now() - startTime);
                const rawText = await resp.text();
                const sizeKb = (new Blob([rawText]).size / 1024).toFixed(2);

                document.getElementById('responseLatency').innerText = latency + ' ms';
                document.getElementById('responseSize').innerText = sizeKb + ' KB';

                // Status Badge
                statusBadge.innerText = resp.status + ' ' + (resp.statusText || getHttpText(resp.status));
                if (resp.status >= 200 && resp.status < 300) {
                    statusBadge.className = 'status-badge status-2xx';
                } else if (resp.status >= 400 && resp.status < 500) {
                    statusBadge.className = 'status-badge status-4xx';
                } else {
                    statusBadge.className = 'status-badge status-5xx';
                }

                // Render JSON / Content
                try {
                    const parsedJson = JSON.parse(rawText);
                    viewer.innerHTML = '<pre style="margin:0; color:#e2e8f0;">' + syntaxHighlightJson(parsedJson) + '</pre>';
                } catch {
                    viewer.innerHTML = '<pre style="margin:0; color:#94a3b8; white-space:pre-wrap;">' + escapeHtml(rawText) + '</pre>';
                }

            } catch (err) {
                const latency = Math.round(performance.now() - startTime);
                document.getElementById('responseLatency').innerText = latency + ' ms';
                document.getElementById('responseSize').innerText = '0 KB';

                statusBadge.className = 'status-badge status-5xx';
                statusBadge.innerText = 'BAĞLANTI HATASI (Network Error)';
                viewer.innerHTML = '<div style="color:#ef4444; padding:20px;">' +
                    '<strong>Hata:</strong> ' + escapeHtml(err.message) +
                    '<br><br><small style="color:var(--text-muted);">Sunucuya erişilemiyor veya CORS/Network engeline takıldı.</small>' +
                    '</div>';
            } finally {
                sendBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i><span>Gönder</span>';
                sendBtn.disabled = false;
            }
        }

        // cURL ve PowerShell Kod Parçacıklarını Güncelle
        function updateSnippetViews() {
            const method = document.getElementById('requestMethodSelect').value;
            const subPath = document.getElementById('requestUrlInput').value.trim();
            const fullUrl = BASE_API_URL + '/' + subPath.replace(/^\/+/, '');
            const authHeader = document.getElementById('headerAuthToken').value.trim();
            const lang = document.getElementById('headerLanguageSelect').value;
            const bodyVal = document.getElementById('requestBodyJson').value.trim();

            // 1. cURL
            let curl = `curl -X ${method} "${fullUrl}" \\\n  -H "${authHeader}" \\\n  -H "X-Language: ${lang}"`;
            if (method === 'POST' || method === 'PUT') {
                curl += ` \\\n  -H "Content-Type: application/json; charset=utf-8"`;
                if (bodyVal) {
                    curl += ` \\\n  -d '${bodyVal.replace(/'/g, "'\\''")}'`;
                }
            }
            document.getElementById('curlSnippetPre').innerText = curl;

            // 2. PowerShell
            const tokenClean = authHeader.replace(/^Bearer\s+/i, '');
            let ps = `$headers = @{ 'Authorization' = 'Bearer ${tokenClean}'; 'Content-Type' = 'application/json; charset=utf-8'; 'X-Language' = '${lang}' }\n`;
            if (bodyVal && (method === 'POST' || method === 'PUT')) {
                ps += `$body = '${bodyVal.replace(/'/g, "''")}'\n`;
                ps += `Invoke-RestMethod -Uri '${fullUrl}' -Method ${capitalize(method)} -Headers $headers -Body ([System.Text.Encoding]::UTF8.GetBytes($body))`;
            } else {
                ps += `Invoke-RestMethod -Uri '${fullUrl}' -Method ${capitalize(method)} -Headers $headers`;
            }
            document.getElementById('psSnippetPre').innerText = ps;
        }

        // Toplu Sağlık Taraması (Batch Modal)
        function openBatchModal() {
            document.getElementById('batchTestModal').style.display = 'flex';
            const listContainer = document.getElementById('batchItemsList');
            listContainer.innerHTML = '';

            const coreTests = [
                { name: 'Sistem Sağlık Ucu', method: 'GET', path: 'health' },
                { name: 'Oturum Bilgisi (/auth/me)', method: 'GET', path: 'auth/me' },
                { name: 'Tüm Projeler Listesi', method: 'GET', path: 'projects' },
                { name: 'Örnek Proje Detayı', method: 'GET', path: 'projects/' + SAMPLE_PROJECT },
                { name: 'Örnek Görev Detayı', method: 'GET', path: 'tasks/' + SAMPLE_TASK },
                { name: 'Kullanıcı Listesi', method: 'GET', path: 'users' }
            ];

            coreTests.forEach((t, idx) => {
                const row = document.createElement('div');
                row.className = 'batch-item-row';
                row.id = 'batchRow_' + idx;
                row.innerHTML = `
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="method-badge method-${t.method.toLowerCase()}">${t.method}</span>
                        <span style="font-weight:600;">${escapeHtml(t.name)}</span>
                        <span style="font-family:var(--font-mono); font-size:11px; color:var(--text-muted);">/api/${t.path}</span>
                    </div>
                    <div id="batchStatus_${idx}" style="font-family:var(--font-mono); font-size:12px; color:var(--text-muted);">Bekliyor</div>
                `;
                listContainer.appendChild(row);
            });
            document.getElementById('batchSummaryStats').innerText = `${coreTests.length} uç nokta teste hazır.`;
        }

        function closeBatchModal() {
            document.getElementById('batchTestModal').style.display = 'none';
        }

        async function runBatchTests() {
            const btn = document.getElementById('btnStartBatch');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Taranıyor...</span>';

            const coreTests = [
                { name: 'Sistem Sağlık Ucu', method: 'GET', path: 'health' },
                { name: 'Oturum Bilgisi (/auth/me)', method: 'GET', path: 'auth/me' },
                { name: 'Tüm Projeler Listesi', method: 'GET', path: 'projects' },
                { name: 'Örnek Proje Detayı', method: 'GET', path: 'projects/' + SAMPLE_PROJECT },
                { name: 'Örnek Görev Detayı', method: 'GET', path: 'tasks/' + SAMPLE_TASK },
                { name: 'Kullanıcı Listesi', method: 'GET', path: 'users' }
            ];

            let passed = 0;
            let failed = 0;

            for (let i = 0; i < coreTests.length; i++) {
                const t = coreTests[i];
                const statusEl = document.getElementById('batchStatus_' + i);
                statusEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="color:var(--brand-primary);"></i> Test ediliyor...';

                const url = BASE_API_URL + '/' + t.path;
                const startTime = performance.now();

                try {
                    const resp = await fetch(url, {
                        method: t.method,
                        headers: {
                            'Authorization': 'Bearer ' + ACTIVE_TOKEN,
                            'Accept': 'application/json'
                        }
                    });
                    const ms = Math.round(performance.now() - startTime);

                    if (resp.ok) {
                        passed++;
                        statusEl.innerHTML = `<span style="color:#10b981; font-weight:700;">✅ ${resp.status} OK</span> <small style="color:var(--text-muted); font-size:11px;">(${ms}ms)</small>`;
                    } else {
                        failed++;
                        statusEl.innerHTML = `<span style="color:#f59e0b; font-weight:700;">⚠️ ${resp.status}</span> <small style="color:var(--text-muted); font-size:11px;">(${ms}ms)</small>`;
                    }
                } catch (e) {
                    failed++;
                    statusEl.innerHTML = `<span style="color:#ef4444; font-weight:700;">❌ Hata</span>`;
                }
            }

            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-rotate"></i><span>Tekrar Test Et</span>';
            document.getElementById('batchSummaryStats').innerHTML = `<strong style="color:#10b981;">${passed} Başarılı</strong>, <strong style="color:${failed ? '#ef4444' : 'var(--text-muted)'};">${failed} Başarısız</strong>.`;
        }

        // Yardımcı Araçlar
        function formatJsonEditor() {
            const area = document.getElementById('requestBodyJson');
            try {
                const parsed = JSON.parse(area.value);
                area.value = JSON.stringify(parsed, null, 2);
            } catch (e) {
                alert('Geçersiz JSON formatı: ' + e.message);
            }
        }

        function clearJsonEditor() {
            document.getElementById('requestBodyJson').value = '';
            updateSnippetViews();
        }

        function copyActiveToken() {
            navigator.clipboard.writeText(ACTIVE_TOKEN);
            alert('Aktif Bearer Token panoya kopyalandı:\n' + ACTIVE_TOKEN);
        }

        function copyResponseBody() {
            const viewer = document.getElementById('responseViewer');
            navigator.clipboard.writeText(viewer.innerText);
            alert('Yanıt çıktısı panoya kopyalandı.');
        }

        function copySnippetText(id) {
            const txt = document.getElementById(id).innerText;
            navigator.clipboard.writeText(txt);
            alert('Komut panoya kopyalandı!');
        }

        function capitalize(s) {
            return s ? s.charAt(0).toUpperCase() + s.slice(1).toLowerCase() : '';
        }

        function escapeHtml(str) {
            return String(str || '').replace(/[&<>"']/g, m => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[m]));
        }

        function getHttpText(code) {
            const map = { 200: 'OK', 201: 'Created', 400: 'Bad Request', 401: 'Unauthorized', 403: 'Forbidden', 404: 'Not Found', 500: 'Internal Server Error' };
            return map[code] || '';
        }

        // JSON Renklendirme
        function syntaxHighlightJson(json) {
            if (typeof json !== 'string') {
                json = JSON.stringify(json, undefined, 2);
            }
            json = escapeHtml(json);
            return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, match => {
                let cls = 'color:#38bdf8;'; // number
                if (/^"/.test(match)) {
                    if (/:$/.test(match)) {
                        cls = 'color:#c084fc; font-weight:600;'; // key
                    } else {
                        cls = 'color:#4ade80;'; // string
                    }
                } else if (/true|false/.test(match)) {
                    cls = 'color:#facc15; font-weight:700;'; // boolean
                } else if (/null/.test(match)) {
                    cls = 'color:#94a3b8; font-style:italic;'; // null
                }
                return '<span style="' + cls + '">' + match + '</span>';
            });
        }
    </script>
</body>
</html>
