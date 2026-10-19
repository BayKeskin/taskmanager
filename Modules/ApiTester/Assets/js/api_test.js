const _carrierEl = document.getElementById('apitesterConfigCarrier');
const CFG = window.APITESTER_CONFIG || (_carrierEl ? {
    baseUrl: _carrierEl.dataset.baseUrl,
    activeToken: _carrierEl.dataset.activeToken,
    sampleProject: _carrierEl.dataset.sampleProject,
    sampleTask: _carrierEl.dataset.sampleTask
} : {});
const BASE_API_URL = CFG.baseUrl ? CFG.baseUrl + '/api' : ((typeof window.BASE_URL !== 'undefined') ? window.BASE_URL + '/api' : '/api');
const ACTIVE_TOKEN = CFG.activeToken || '';
const SAMPLE_PROJECT = CFG.sampleProject || '01';
const SAMPLE_TASK = CFG.sampleTask || '01-01';

const _liveData = window.APITESTER_LIVE_DATA || {};
const _i18n = _liveData.i18n || {};
const ENDPOINTS = _liveData.catalog && _liveData.catalog.length > 0 ? _liveData.catalog : [
    {
        category: '⚡ System',
        id: 'health',
        method: 'GET',
        name: 'API Health',
        path: 'health',
        desc: 'API Health Status',
        body: null,
        tip: 'Public endpoint'
    }
];

let currentActiveEndpoint = ENDPOINTS[0];

        // Sayfa Yüklendiğinde Başlat
        document.addEventListener('DOMContentLoaded', () => {
            renderEndpointList();
            selectEndpoint(ENDPOINTS[0].id);

            // Event Listeners for Header Actions
            const btnCopyToken = document.getElementById('btnCopyActiveToken');
            if (btnCopyToken) btnCopyToken.addEventListener('click', copyActiveToken);

            const btnOpenBatch = document.getElementById('btnOpenBatchModal');
            if (btnOpenBatch) btnOpenBatch.addEventListener('click', openBatchModal);

            const btnCloseBatchX = document.getElementById('btnCloseBatchModalX');
            if (btnCloseBatchX) btnCloseBatchX.addEventListener('click', closeBatchModal);

            const btnCloseBatchBtn = document.getElementById('btnCloseBatchModalBtn');
            if (btnCloseBatchBtn) btnCloseBatchBtn.addEventListener('click', closeBatchModal);

            const btnStartBatch = document.getElementById('btnStartBatch');
            if (btnStartBatch) btnStartBatch.addEventListener('click', runBatchTests);

            // Search filter
            const searchInput = document.getElementById('endpointSearchInput');
            if (searchInput) searchInput.addEventListener('input', filterEndpoints);

            // Method select & URL input
            const methodSelect = document.getElementById('requestMethodSelect');
            if (methodSelect) {
                methodSelect.addEventListener('change', (e) => updateMethodColor(e.target.value));
            }

            const urlInput = document.getElementById('requestUrlInput');
            if (urlInput) {
                urlInput.addEventListener('input', updateSnippetViews);
            }

            // Send request button
            const btnSend = document.getElementById('btnSendRequest');
            if (btnSend) btnSend.addEventListener('click', executeCurrentRequest);

            // Tab bar buttons
            document.querySelectorAll('.panel-tab-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const tabKey = btn.dataset.tab;
                    if (tabKey) switchRequestTab(tabKey);
                });
            });

            // JSON Editor buttons & inputs
            const bodyJson = document.getElementById('requestBodyJson');
            if (bodyJson) bodyJson.addEventListener('input', updateSnippetViews);

            const btnFormatJson = document.getElementById('btnFormatJson');
            if (btnFormatJson) btnFormatJson.addEventListener('click', formatJsonEditor);

            const btnClearJson = document.getElementById('btnClearJson');
            if (btnClearJson) btnClearJson.addEventListener('click', clearJsonEditor);

            // Headers tab inputs
            const headerAuthToken = document.getElementById('headerAuthToken');
            if (headerAuthToken) headerAuthToken.addEventListener('input', updateSnippetViews);

            const headerContentType = document.getElementById('headerContentType');
            if (headerContentType) headerContentType.addEventListener('input', updateSnippetViews);

            const headerLanguageSelect = document.getElementById('headerLanguageSelect');
            if (headerLanguageSelect) headerLanguageSelect.addEventListener('change', updateSnippetViews);

            // Copy Snippets
            document.querySelectorAll('[data-copy-target]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const targetId = btn.getAttribute('data-copy-target');
                    if (targetId) copySnippetText(targetId);
                });
            });

            // Response Copy
            const btnCopyResponse = document.getElementById('btnCopyResponse');
            if (btnCopyResponse) btnCopyResponse.addEventListener('click', copyResponseBody);
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

            // Parameters / Values
            const paramsBox = document.getElementById('infoParamsBox');
            if (paramsBox) {
                if (ep.params && Object.keys(ep.params).length > 0) {
                    paramsBox.innerHTML = '<pre style="margin:0; color:#38bdf8;">' + syntaxHighlightJson(ep.params) + '</pre>';
                } else if (ep.body && Object.keys(ep.body).length > 0) {
                    paramsBox.innerHTML = '<pre style="margin:0; color:#38bdf8;">' + syntaxHighlightJson(ep.body) + '</pre>';
                } else {
                    paramsBox.innerHTML = '<span style="color:var(--text-muted); font-style:italic;">' + escapeHtml(_i18n.no_params_needed || 'No parameters required') + '</span>';
                }
            }

            // Expected Response
            const statusPill = document.getElementById('infoStatusPill');
            const respBox = document.getElementById('infoResponseBox');
            if (statusPill && respBox) {
                const expCode = ep.expected_status || 200;
                statusPill.innerText = (_i18n.expected_status || 'Expected Status:') + ' ' + expCode + ' ' + getHttpText(expCode);
                statusPill.className = 'api-lab-info-status-pill ' + (expCode >= 200 && expCode < 300 ? 'status-2xx' : (expCode >= 400 && expCode < 500 ? 'status-4xx' : 'status-5xx'));

                if (ep.response) {
                    respBox.innerHTML = '<pre style="margin:0; color:#e2e8f0;">' + syntaxHighlightJson(ep.response) + '</pre>';
                } else {
                    respBox.innerHTML = '<pre style="margin:0; color:#94a3b8;">{\n  "success": true,\n  "data": { ... }\n}</pre>';
                }
            }

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
                        const errPrefix = _i18n.json_syntax_error || '';
                        alert(errPrefix + (errPrefix ? ':\n' : '') + e.message);
                        switchRequestTab('body');
                        return;
                    }
                }
            }

            const sendBtn = document.getElementById('btnSendRequest');
            sendBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>' + (_i18n.sending_request || '') + '</span>';
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
                statusBadge.innerText = _i18n.network_error || 'Network Error';
                viewer.innerHTML = '<div style="color:#ef4444; padding:20px;">' +
                    '<strong>Error:</strong> ' + escapeHtml(err.message) +
                    '<br><br><small style="color:var(--text-muted);">' + escapeHtml(_i18n.network_error_desc || '') + '</small>' +
                    '</div>';
            } finally {
                sendBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i><span>' + (_i18n.btn_send || '') + '</span>';
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

            const coreTests = ENDPOINTS.slice(0, 6).map(e => ({ name: e.name, method: e.method, path: e.path }));

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
                    <div id="batchStatus_${idx}" style="font-family:var(--font-mono); font-size:12px; color:var(--text-muted);">...</div>
                `;
                listContainer.appendChild(row);
            });
            document.getElementById('batchSummaryStats').innerText = `${coreTests.length} ` + (_i18n.batch_ready_count || '');
        }

        function closeBatchModal() {
            document.getElementById('batchTestModal').style.display = 'none';
        }

        async function runBatchTests() {
            const btn = document.getElementById('btnStartBatch');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>' + (_i18n.batch_scanning || '') + '</span>';

            const coreTests = ENDPOINTS.slice(0, 6).map(e => ({ name: e.name, method: e.method, path: e.path }));

            let passed = 0;
            let failed = 0;

            for (let i = 0; i < coreTests.length; i++) {
                const t = coreTests[i];
                const statusEl = document.getElementById('batchStatus_' + i);
                statusEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="color:var(--brand-primary);"></i> ' + (_i18n.batch_testing || '');

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
                        statusEl.innerHTML = `<span style="color:#10b981; font-weight:700;">OK ${resp.status}</span> <small style="color:var(--text-muted); font-size:11px;">(${ms}ms)</small>`;
                    } else {
                        failed++;
                        statusEl.innerHTML = `<span style="color:#f59e0b; font-weight:700;">${resp.status}</span> <small style="color:var(--text-muted); font-size:11px;">(${ms}ms)</small>`;
                    }
                } catch (e) {
                    failed++;
                    statusEl.innerHTML = `<span style="color:#ef4444; font-weight:700;">FAIL</span>`;
                }
            }

            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-rotate"></i><span>' + (_i18n.batch_retest || '') + '</span>';
            document.getElementById('batchSummaryStats').innerHTML = `<strong style="color:#10b981;">${passed} OK</strong>, <strong style="color:${failed ? '#ef4444' : 'var(--text-muted)'};">${failed} FAIL</strong>.`;
        }

        // Yardımcı Araçlar
        function formatJsonEditor() {
            const area = document.getElementById('requestBodyJson');
            try {
                const parsed = JSON.parse(area.value);
                area.value = JSON.stringify(parsed, null, 2);
            } catch (e) {
                const errPrefix = _i18n.invalid_json || '';
                alert(errPrefix + (errPrefix ? ': ' : '') + e.message);
            }
        }

        function clearJsonEditor() {
            document.getElementById('requestBodyJson').value = '';
            updateSnippetViews();
        }

        function copyActiveToken() {
            navigator.clipboard.writeText(ACTIVE_TOKEN);
            alert((_i18n.token_copied_alert || '') + ':\n' + ACTIVE_TOKEN);
        }

        function copyResponseBody() {
            const viewer = document.getElementById('responseViewer');
            navigator.clipboard.writeText(viewer.innerText);
            alert(_i18n.response_copied || '');
        }

        function copySnippetText(id) {
            const txt = document.getElementById(id).innerText;
            navigator.clipboard.writeText(txt);
            alert(_i18n.command_copied || '');
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