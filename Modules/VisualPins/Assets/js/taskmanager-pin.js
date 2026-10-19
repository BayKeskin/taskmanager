/**
 * TaskManager In-House Visual Pin & Feedback Widget (v2.3 - Modal-Safe & Zero-Freeze Edition)
 * - Bootstrap / Custom Modal açıkken oluşan Focus-Trap sonsuz döngüsünü %100 engeller.
 * - Modal içine tıklandığında arka planı taramak yerine doğrudan modalı yakalar (0ms takılma).
 * - Klonlama sırasında ağır backdrop-filter / blur / animasyon efektlerini devre dışı bırakır.
 */
(function () {
    if (window.__TM_PIN_INITIALIZED__) return;
    window.__TM_PIN_INITIALIZED__ = true;

    function runWidget() {
        if (!document.body) {
            document.addEventListener('DOMContentLoaded', runWidget);
            return;
        }

        const currentScript = document.currentScript || document.querySelector('script[src*="taskmanager-pin.js"]');
        const projectCode = currentScript?.getAttribute('data-project') || '01';

        let baseOrigin = 'http://localhost/taskmanager';
        if (currentScript && currentScript.src) {
            try {
                const scriptUrl = new URL(currentScript.src, window.location.href);
                const basePath = scriptUrl.pathname.replace(/\/Public\/js\/taskmanager-pin\.js.*$/i, '');
                baseOrigin = scriptUrl.origin + basePath;
            } catch (e) { }
        }
        const apiEndpoint = currentScript?.getAttribute('data-api') || (baseOrigin + '/api/v1/visual-feedback');

        let isPinMode = false;
        let hoveredElement = null;
        let currentPin = null;
        let activeCapturePromise = null;

        // 1. Ekran Görüntüsü Motoru
        let screenshotLibPromise = null;
        function loadScreenshotEngine() {
            if (window.html2canvas) {
                return Promise.resolve({ type: 'h2c', fn: window.html2canvas });
            }
            if (screenshotLibPromise) return screenshotLibPromise;

            screenshotLibPromise = new Promise((resolve) => {
                const scriptLocal = document.createElement('script');
                scriptLocal.src = baseOrigin + '/Modules/VisualPins/Assets/js/html2canvas.min.js';
                scriptLocal.async = true;
                scriptLocal.onload = () => {
                    if (window.html2canvas) {
                        resolve({ type: 'h2c', fn: window.html2canvas });
                    } else {
                        loadCdnHtml2Canvas().then(resolve);
                    }
                };
                scriptLocal.onerror = () => {
                    loadCdnHtml2Canvas().then(resolve);
                };
                document.head.appendChild(scriptLocal);
            });
            return screenshotLibPromise;
        }

        function loadCdnHtml2Canvas() {
            return new Promise((resolve) => {
                const scriptCdn = document.createElement('script');
                scriptCdn.src = 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js';
                scriptCdn.async = true;
                scriptCdn.onload = () => resolve(window.html2canvas ? { type: 'h2c', fn: window.html2canvas } : null);
                scriptCdn.onerror = () => resolve(null);
                document.head.appendChild(scriptCdn);
            });
        }

        loadScreenshotEngine();

        // 2. CSS Stillerini Enjekte Et
        if (!document.getElementById('tm-pin-styles')) {
            const style = document.createElement('style');
            style.id = 'tm-pin-styles';
            style.textContent = `
                #tm-pin-trigger {
                    position: fixed;
                    bottom: 24px;
                    right: 24px;
                    z-index: 2147483640;
                    background: linear-gradient(135deg, #7b68ee 0%, #4f8ff7 100%);
                    color: #ffffff;
                    border: none;
                    border-radius: 50px;
                    padding: 10px 18px;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    font-size: 13px;
                    font-weight: 600;
                    cursor: pointer;
                    box-shadow: 0 8px 24px rgba(123, 104, 238, 0.4);
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
                    user-select: none;
                }
                #tm-pin-trigger:hover {
                    transform: translateY(-2px) scale(1.03);
                    box-shadow: 0 12px 30px rgba(123, 104, 238, 0.55);
                }
                #tm-pin-trigger.active {
                    background: #ff4757;
                    box-shadow: 0 8px 24px rgba(255, 71, 87, 0.4);
                }
                #tm-pin-banner {
                    position: fixed;
                    top: 16px;
                    left: 50%;
                    transform: translateX(-50%);
                    z-index: 2147483642;
                    background: rgba(18, 18, 26, 0.95);
                    color: #ffffff;
                    border: 1px solid rgba(123, 104, 238, 0.5);
                    border-radius: 30px;
                    padding: 8px 20px;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    font-size: 12.5px;
                    font-weight: 500;
                    display: none;
                    align-items: center;
                    gap: 12px;
                    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.5);
                }
                .tm-pin-hover-target {
                    outline: 2px dashed #7b68ee !important;
                    outline-offset: 2px !important;
                    cursor: crosshair !important;
                }
                .tm-pin-marker {
                    position: fixed;
                    z-index: 2147483641;
                    width: 30px;
                    height: 30px;
                    background: linear-gradient(135deg, #7b68ee, #9b5de5);
                    color: #ffffff;
                    border: 2.5px solid #ffffff;
                    border-radius: 50% 50% 50% 0;
                    transform: translate(-15px, -34px) rotate(-45deg);
                    transform-origin: center center;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    font-size: 12px;
                    font-weight: 800;
                    box-shadow: 0 6px 16px rgba(0,0,0,0.35);
                    pointer-events: none;
                }
                .tm-pin-marker span {
                    transform: rotate(45deg);
                    display: block;
                    line-height: 1;
                }
                #tm-pin-dialog {
                    position: fixed;
                    z-index: 2147483646;
                    width: 340px;
                    background: #1e1e28;
                    color: #f1f2f6;
                    border: 1px solid rgba(255, 255, 255, 0.12);
                    border-radius: 12px;
                    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    padding: 16px;
                    display: none;
                }
                #tm-pin-dialog h4 {
                    margin: 0 0 10px;
                    font-size: 13.5px;
                    font-weight: 700;
                    color: #ffffff;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                }
                #tm-pin-dialog input, #tm-pin-dialog textarea, #tm-pin-dialog select {
                    width: 100%;
                    background: #14141c;
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 6px;
                    color: #ffffff;
                    padding: 8px 10px;
                    font-size: 12px;
                    margin-bottom: 8px;
                    box-sizing: border-box;
                    outline: none;
                    font-family: inherit;
                }
                #tm-pin-dialog textarea {
                    resize: vertical;
                    min-height: 55px;
                }
                #tm-pin-dialog input:focus, #tm-pin-dialog textarea:focus, #tm-pin-dialog select:focus {
                    border-color: #7b68ee;
                }
                #tm-pin-dialog .tm-meta-badge {
                    font-size: 10.5px;
                    color: #a4b0be;
                    background: rgba(255, 255, 255, 0.04);
                    padding: 4px 8px;
                    border-radius: 4px;
                    margin-bottom: 10px;
                    word-break: break-all;
                    display: block;
                }
                #tm-pin-dialog .tm-actions {
                    display: flex;
                    gap: 8px;
                    justify-content: flex-end;
                    margin-top: 6px;
                }
                #tm-pin-dialog button {
                    border: none;
                    border-radius: 6px;
                    padding: 7px 12px;
                    font-size: 11.5px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.15s;
                }
                #tm-pin-dialog .btn-cancel {
                    background: rgba(255, 255, 255, 0.08);
                    color: #a4b0be;
                }
                #tm-pin-dialog .btn-submit {
                    background: #7b68ee;
                    color: #ffffff;
                }
                #tm-pin-dialog .btn-submit:hover {
                    background: #6a53e6;
                }
            `;
            document.head.appendChild(style);
        }

        // 3. UI Elementlerini Oluştur
        const triggerBtn = document.createElement('button');
        triggerBtn.id = 'tm-pin-trigger';
        triggerBtn.innerHTML = '<span>📍</span> <span>Hata Bildir</span>';
        document.body.appendChild(triggerBtn);

        const banner = document.createElement('div');
        banner.id = 'tm-pin-banner';
        banner.innerHTML = `
            <span>🎯 <b>Görsel Pin Modu:</b> Sayfada hata gördüğünüz öğeye tıklayın</span>
            <button id="tm-pin-cancel-mode" style="background:rgba(255,255,255,0.15); border:none; color:#fff; border-radius:15px; padding:3px 10px; cursor:pointer; font-size:11px;">Çıkış (ESC)</button>
        `;
        document.body.appendChild(banner);

        const dialog = document.createElement('div');
        dialog.id = 'tm-pin-dialog';
        dialog.innerHTML = `
            <h4>
                <span>📍 Görsel Hata Kaydı</span>
                <span style="font-size:11px; color:#7b68ee; font-weight:normal;">[${projectCode}]</span>
            </h4>
            <span class="tm-meta-badge" id="tm-pin-meta">Öğe: ...</span>

            <div id="tm-pin-preview-box" style="display:none; margin-bottom:8px; border-radius:6px; overflow:hidden; border:1px solid rgba(123,104,238,0.4); background:#0c0c14; text-align:center;">
                <img id="tm-pin-preview-img" style="max-width:100%; max-height:130px; display:block; margin:0 auto; object-fit:contain;" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" alt="Preview">
                <div style="font-size:10px; color:#00b894; padding:3px 6px; background:rgba(0,184,148,0.1); display:flex; align-items:center; justify-content:center; gap:4px;">
                    <span>📸</span> <span>Ekran görüntüsü & pin hazır</span>
                </div>
            </div>

            <input type="text" id="tm-pin-title" placeholder="Hata Başlığı (Örn: Buton mobilde taşıyor)" required>
            <textarea id="tm-pin-desc" placeholder="Açıklama ve beklenen davranış..."></textarea>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                <select id="tm-pin-priority">
                    <option value="normal" selected>⚡ Normal Öncelik</option>
                    <option value="high">🔥 Yüksek</option>
                    <option value="urgent">🚨 Acil (Kritik)</option>
                    <option value="low">🌱 Düşük</option>
                </select>
                <div id="tm-pin-snapshot-status" style="font-size:10.5px; color:#00b894; display:flex; align-items:center; justify-content:center; font-weight:600;">
                    ⏳ Ekran alınıyor...
                </div>
            </div>
            <div class="tm-actions">
                <button type="button" class="btn-cancel" id="tm-pin-dialog-cancel">İptal</button>
                <button type="button" class="btn-submit" id="tm-pin-dialog-submit">🚀 TaskManager'a Gönder</button>
            </div>
        `;
        document.body.appendChild(dialog);

        // KRİTİK KORUMA 1: Bootstrap / Modal Focus-Trap Sonsuz Döngüsünü Engelle!
        // Sayfada açık bir modal varken bizim diyaloğun içindeki inputlara tıklanınca
        // modalın odağı zorla geri çalıp tarayıcıyı kilitlemesini (infinite focus loop) önler.
        ['focusin', 'focus', 'mousedown', 'click', 'keydown'].forEach((evtName) => {
            dialog.addEventListener(evtName, (ev) => {
                ev.stopPropagation();
            });
        });
        document.addEventListener('focusin', (ev) => {
            if (dialog.style.display === 'block' && dialog.contains(ev.target)) {
                ev.stopImmediatePropagation();
            }
        }, true);

        function getCssSelector(el) {
            if (!(el instanceof Element)) return '';
            const path = [];
            while (el && el.nodeType === Node.ELEMENT_NODE && el !== document.body && el !== document.documentElement) {
                let selector = el.nodeName.toLowerCase();
                if (el.id) {
                    selector += '#' + el.id;
                    path.unshift(selector);
                    break;
                } else {
                    let sibling = el;
                    let nth = 1;
                    while ((sibling = sibling.previousElementSibling)) {
                        if (sibling.nodeName.toLowerCase() === selector) nth++;
                    }
                    if (nth > 1) selector += `:nth-of-type(${nth})`;
                }
                path.unshift(selector);
                el = el.parentNode;
            }
            return path.join(' > ');
        }

        function togglePinMode(active) {
            isPinMode = typeof active === 'boolean' ? active : !isPinMode;
            if (isPinMode) {
                triggerBtn.classList.add('active');
                triggerBtn.innerHTML = '<span>✕</span> <span>İptal Et</span>';
                banner.style.display = 'flex';
                document.body.style.cursor = 'crosshair';
            } else {
                triggerBtn.classList.remove('active');
                triggerBtn.innerHTML = '<span>📍</span> <span>Hata Bildir</span>';
                banner.style.display = 'none';
                document.body.style.cursor = 'default';
                if (hoveredElement) {
                    hoveredElement.classList.remove('tm-pin-hover-target');
                    hoveredElement = null;
                }
            }
        }

        triggerBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            togglePinMode();
        });
        document.getElementById('tm-pin-cancel-mode').addEventListener('click', (e) => {
            e.stopPropagation();
            togglePinMode(false);
        });
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && isPinMode) togglePinMode(false);
        });

        document.addEventListener('mouseover', (e) => {
            if (!isPinMode) return;
            const target = e.target;
            if (!target || !target.closest) return;
            if (target.closest('#tm-pin-trigger, #tm-pin-banner, #tm-pin-dialog, .tm-pin-marker')) return;

            if (hoveredElement && hoveredElement !== target) {
                hoveredElement.classList.remove('tm-pin-hover-target');
            }
            hoveredElement = target;
            hoveredElement.classList.add('tm-pin-hover-target');
        });

        function drawPinOnCanvas(ctx, pinX, pinY, scale) {
            ctx.save();
            ctx.translate(pinX, pinY);

            ctx.shadowColor = 'rgba(0, 0, 0, 0.45)';
            ctx.shadowBlur = 10 * scale;
            ctx.shadowOffsetY = 4 * scale;

            ctx.beginPath();
            ctx.moveTo(0, 0);
            ctx.bezierCurveTo(-13 * scale, -12 * scale, -16 * scale, -28 * scale, 0, -38 * scale);
            ctx.bezierCurveTo(16 * scale, -28 * scale, 13 * scale, -12 * scale, 0, 0);
            ctx.closePath();

            const pinGrad = ctx.createLinearGradient(-14 * scale, -38 * scale, 14 * scale, 0);
            pinGrad.addColorStop(0, '#7b68ee');
            pinGrad.addColorStop(1, '#9b5de5');
            ctx.fillStyle = pinGrad;
            ctx.fill();

            ctx.strokeStyle = '#ffffff';
            ctx.lineWidth = 2.5 * scale;
            ctx.stroke();

            ctx.shadowColor = 'transparent';
            ctx.fillStyle = '#ffffff';
            ctx.font = `bold ${Math.round(12 * scale)}px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('1', 0, -23 * scale);

            ctx.restore();
        }

        function createInstantFallbackSnapshot(viewportW, viewportH, clientX, clientY, targetRect, selector, pageUrl) {
            const canvas = document.createElement('canvas');
            canvas.width = viewportW;
            canvas.height = viewportH;
            const ctx = canvas.getContext('2d');

            ctx.fillStyle = '#181824';
            ctx.fillRect(0, 0, viewportW, viewportH);

            ctx.strokeStyle = 'rgba(123, 104, 238, 0.12)';
            ctx.lineWidth = 1;
            for (let gx = 0; gx < viewportW; gx += 40) {
                ctx.beginPath(); ctx.moveTo(gx, 0); ctx.lineTo(gx, viewportH); ctx.stroke();
            }
            for (let gy = 0; gy < viewportH; gy += 40) {
                ctx.beginPath(); ctx.moveTo(0, gy); ctx.lineTo(viewportW, gy); ctx.stroke();
            }

            if (targetRect && targetRect.width > 0 && targetRect.height > 0) {
                ctx.fillStyle = 'rgba(123, 104, 238, 0.18)';
                ctx.fillRect(targetRect.left, targetRect.top, targetRect.width, targetRect.height);
                ctx.strokeStyle = '#7b68ee';
                ctx.lineWidth = 2;
                ctx.setLineDash([6, 4]);
                ctx.strokeRect(targetRect.left, targetRect.top, targetRect.width, targetRect.height);
                ctx.setLineDash([]);
            }

            ctx.fillStyle = 'rgba(0, 0, 0, 0.65)';
            ctx.fillRect(16, 16, Math.min(viewportW - 32, 650), 54);
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 13px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
            ctx.fillText('📍 URL: ' + pageUrl, 28, 38);
            ctx.fillStyle = '#a4b0be';
            ctx.font = '12px monospace';
            ctx.fillText('Seçici: ' + selector.substring(0, 80), 28, 58);

            drawPinOnCanvas(ctx, clientX, clientY, 1);
            return canvas.toDataURL('image/jpeg', 0.85);
        }

        // Tıklama Yakalayıcı
        document.addEventListener('click', (e) => {
            if (!isPinMode) return;
            const target = e.target;
            if (!target || !target.closest) return;
            if (target.closest('#tm-pin-trigger, #tm-pin-banner, #tm-pin-dialog, .tm-pin-marker')) return;

            e.preventDefault();
            e.stopPropagation();
            if (e.stopImmediatePropagation) e.stopImmediatePropagation();

            const scrollX = Math.round(window.pageXOffset || document.documentElement.scrollLeft || 0);
            const scrollY = Math.round(window.pageYOffset || document.documentElement.scrollTop || 0);
            const clientX = typeof e.clientX === 'number' && !isNaN(e.clientX) ? Math.round(e.clientX) : 0;
            const clientY = typeof e.clientY === 'number' && !isNaN(e.clientY) ? Math.round(e.clientY) : 0;
            const x = typeof e.pageX === 'number' && !isNaN(e.pageX) ? Math.round(e.pageX) : (clientX + scrollX);
            const y = typeof e.pageY === 'number' && !isNaN(e.pageY) ? Math.round(e.pageY) : (clientY + scrollY);
            const viewportW = window.innerWidth;
            const viewportH = window.innerHeight;
            const selector = getCssSelector(target);
            const targetRect = target.getBoundingClientRect ? target.getBoundingClientRect() : null;

            // Tıklanan yer açık bir Modal / Dialog içinde mi kontrol et!
            const modalContainer = target.closest('.modal.show, .modal.in, .modal[style*="display: block"], [role="dialog"], .swal2-container, .fancybox-container, .mfp-wrap');

            // 1. Pini Fixed olarak ekrana yerleştir (Modal üstünde de kaymadan sabit durur)
            if (currentPin) currentPin.remove();
            currentPin = document.createElement('div');
            currentPin.className = 'tm-pin-marker';
            currentPin.style.left = clientX + 'px';
            currentPin.style.top = clientY + 'px';
            currentPin.innerHTML = '<span>1</span>';
            document.body.appendChild(currentPin);

            togglePinMode(false);

            // 2. Dialogu Fixed olarak konumlandır
            const previewBox = document.getElementById('tm-pin-preview-box');
            const previewImg = document.getElementById('tm-pin-preview-img');
            const snapshotStatus = document.getElementById('tm-pin-snapshot-status');

            if (previewBox) previewBox.style.display = 'none';
            if (snapshotStatus) {
                snapshotStatus.textContent = '⏳ Ekran alınıyor...';
                snapshotStatus.style.color = '#7b68ee';
            }

            const dialogWidth = 340;
            let leftPos = clientX + 20;
            if (leftPos + dialogWidth > viewportW - 10) {
                leftPos = clientX - dialogWidth - 20;
            }
            let topPos = clientY + 10;
            if (topPos + 350 > viewportH - 10) {
                topPos = Math.max(10, viewportH - 360);
            }
            dialog.style.left = Math.max(10, leftPos) + 'px';
            dialog.style.top = Math.max(10, topPos) + 'px';
            dialog.style.display = 'block';

            document.getElementById('tm-pin-meta').textContent = `Öğe: ${selector.substring(0, 48)}...`;
            const titleInput = document.getElementById('tm-pin-title');
            titleInput.value = `[Pin] ${target.tagName.toLowerCase()}${target.id ? '#' + target.id : ''} öğesinde sorun`;

            // Modal açık değilse doğrudan focus ver; modal açıksa focus-trap çakışmasını önlemek için gecikmeli ver
            if (!modalContainer) {
                titleInput.focus();
            }

            dialog._pinData = {
                x,
                y,
                clientX,
                clientY,
                scrollX,
                scrollY,
                selector,
                url: window.location.href,
                viewport: `${viewportW}x${viewportH}`,
                userAgent: navigator.userAgent,
                screenshot: ''
            };

            // 3. Arka Planda Ekran Görüntüsü Yakala (Modal varsa SADECE modalı yakalar -> 0ms donma!)
            activeCapturePromise = new Promise((resolveCapture) => {
                setTimeout(async () => {
                    let screenshotData = '';
                    try {
                        const engine = await loadScreenshotEngine();
                        if (engine && engine.type === 'h2c') {
                            // EĞER MODAL İÇİNDEYSEK: Tüm body yerine sadece modalContainer'ı yakala!
                            const captureRoot = modalContainer || document.body;
                            const isModalCapture = Boolean(modalContainer);

                            const h2cOptions = {
                                useCORS: true,
                                allowTaint: false,
                                logging: false,
                                scale: 1,
                                imageTimeout: 1200,
                                backgroundColor: isModalCapture ? '#181824' : null,
                                onclone: (clonedDoc) => {
                                    // KRİTİK KORUMA 2: html2canvas'ı donduran backdrop-filter (blur) ve animasyonları klonda kapat!
                                    const styleReset = clonedDoc.createElement('style');
                                    styleReset.textContent = `
                                        *, *::before, *::after {
                                            backdrop-filter: none !important;
                                            -webkit-backdrop-filter: none !important;
                                            transition: none !important;
                                            animation: none !important;
                                        }
                                    `;
                                    clonedDoc.head.appendChild(styleReset);
                                },
                                ignoreElements: (el) => {
                                    if (!el) return false;
                                    if (el.id === 'tm-pin-trigger' || el.id === 'tm-pin-banner' || el.id === 'tm-pin-dialog') return true;
                                    if (el.classList && (el.classList.contains('tm-pin-marker') || el.classList.contains('modal-backdrop'))) return true;
                                    if (el.tagName === 'VIDEO' || el.tagName === 'IFRAME') return true;
                                    return false;
                                }
                            };

                            if (!isModalCapture) {
                                h2cOptions.x = scrollX;
                                h2cOptions.y = scrollY;
                                h2cOptions.width = viewportW;
                                h2cOptions.height = viewportH;
                                h2cOptions.windowWidth = viewportW;
                                h2cOptions.windowHeight = viewportH;
                            }

                            const h2cCanvas = await Promise.race([
                                engine.fn(captureRoot, h2cOptions),
                                new Promise((_, reject) => setTimeout(() => reject(new Error('Capture timeout')), 2200))
                            ]);

                            const finalCanvas = document.createElement('canvas');
                            finalCanvas.width = viewportW;
                            finalCanvas.height = viewportH;
                            const ctx = finalCanvas.getContext('2d');

                            if (isModalCapture) {
                                // Modal görüntüsünü tam ekrandaki koordinatına yerleştir
                                ctx.fillStyle = '#14141f';
                                ctx.fillRect(0, 0, viewportW, viewportH);
                                const mRect = modalContainer.getBoundingClientRect();
                                ctx.drawImage(h2cCanvas, mRect.left, mRect.top, mRect.width, mRect.height);
                            } else {
                                ctx.drawImage(h2cCanvas, 0, 0, viewportW, viewportH);
                            }

                            drawPinOnCanvas(ctx, clientX, clientY, 1);
                            screenshotData = finalCanvas.toDataURL('image/jpeg', 0.85);
                        }
                    } catch (err) {
                        console.warn('[TaskManager Pin] Yakalama uyarısı, sentetik harita devrede:', err.message);
                    }

                    if (!screenshotData) {
                        screenshotData = createInstantFallbackSnapshot(
                            viewportW, viewportH, clientX, clientY, targetRect, selector, window.location.href
                        );
                    }

                    if (dialog._pinData) {
                        dialog._pinData.screenshot = screenshotData;
                    }
                    if (previewBox && previewImg) {
                        previewImg.src = screenshotData;
                        previewBox.style.display = 'block';
                    }
                    if (snapshotStatus) {
                        snapshotStatus.textContent = '📸 Resim Hazır';
                        snapshotStatus.style.color = '#00b894';
                    }

                    resolveCapture(screenshotData);
                }, 60);
            });

        }, true);

        document.getElementById('tm-pin-dialog-cancel').addEventListener('click', () => {
            dialog.style.display = 'none';
            if (currentPin) {
                currentPin.remove();
                currentPin = null;
            }
        });

        document.getElementById('tm-pin-dialog-submit').addEventListener('click', async () => {
            const title = document.getElementById('tm-pin-title').value.trim();
            const desc = document.getElementById('tm-pin-desc').value.trim();
            const priority = document.getElementById('tm-pin-priority').value;
            const data = dialog._pinData || {};

            if (!title) {
                alert('Lütfen hata için kısa bir başlık girin.');
                return;
            }

            const submitBtn = document.getElementById('tm-pin-dialog-submit');
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Gönderiliyor...';

            try {
                if (!data.screenshot && activeCapturePromise) {
                    await activeCapturePromise;
                }

                const payload = {
                    project_code: projectCode,
                    title: title,
                    description: desc,
                    priority: priority,
                    selector: data.selector,
                    url: data.url,
                    viewport: data.viewport,
                    user_agent: data.userAgent,
                    coords: `Sayfa: X:${data.x}, Y:${data.y} | Görünüm: X:${data.clientX}, Y:${data.clientY} (Scroll: ${data.scrollY}px)`,
                    screenshot: dialog._pinData?.screenshot || '',
                    created_via: 'tm_visual_pin'
                };

                const res = await fetch(apiEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const result = await res.json();
                if (result.success || result.task_code || result.id) {
                    const code = result.task_code || result.data?.task_code || 'TSK';
                    alert(`✅ Görsel Hata Görevi Başarıyla Oluşturuldu!\nGörev Kodu: ${code}\nEkran görüntüsü ile birlikte TaskManager panosuna kaydedildi.`);
                    dialog.style.display = 'none';
                    if (currentPin) {
                        currentPin.remove();
                        currentPin = null;
                    }
                } else {
                    alert('⚠️ Hata: ' + (result.message || 'Görev oluşturulamadı.'));
                }
            } catch (err) {
                alert('❌ Bağlantı hatası: TaskManager API ulaşılamadı (' + err.message + ')');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = '🚀 TaskManager\'a Gönder';
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runWidget);
    } else {
        runWidget();
    }
})();