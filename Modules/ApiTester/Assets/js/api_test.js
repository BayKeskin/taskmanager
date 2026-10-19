const _carrierEl = document.getElementById('apitesterConfigCarrier');
const CFG = window.APITESTER_CONFIG || (_carrierEl ? {
    baseUrl: _carrierEl.dataset.baseUrl,
    activeToken: _carrierEl.dataset.activeToken,
    sampleProject: _carrierEl.dataset.sampleProject,
    sampleTask: _carrierEl.dataset.sampleTask
} : {});
const BASE_API_URL = CFG.baseUrl ? CFG.baseUrl + '/api' : '/taskmanager/api';
const ACTIVE_TOKEN = CFG.activeToken || '';
const SAMPLE_PROJECT = CFG.sampleProject || '01';
const SAMPLE_TASK = CFG.sampleTask || '01-01';

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