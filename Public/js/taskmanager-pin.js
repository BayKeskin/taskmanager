/**
 * TaskManager In-House Visual Pin & Feedback Widget (v2.0 - Pixel-Perfect Edition)
 * Gerçek piksel yakalama (modern-screenshot + SVG ForeignObject) ve sıfır CSS bozulması.
 *
 * Usage:
 * <script src="http://localhost/taskmanager/Public/js/taskmanager-pin.js" data-project="01" async></script>
 */
(function () {
    if (window.__TM_PIN_INITIALIZED__) return;
    window.__TM_PIN_INITIALIZED__ = true;

    const currentScript = document.currentScript || document.querySelector('script[src*="taskmanager-pin.js"]');
    const projectCode = currentScript?.getAttribute('data-project') || '01';
    
    let baseOrigin = 'http://localhost/taskmanager';
    let defaultApi = 'http://localhost/taskmanager/api/v1/visual-feedback';
    if (currentScript && currentScript.src) {
        try {
            const scriptUrl = new URL(currentScript.src);
            baseOrigin = scriptUrl.origin + '/taskmanager';
            defaultApi = baseOrigin + '/api/v1/visual-feedback';
        } catch (e) {}
    }
    const apiEndpoint = currentScript?.getAttribute('data-api') || defaultApi;

    let isPinMode = false;
    let hoveredElement = null;
    let currentPin = null;

    // 1. modern-screenshot (html2canvas'ın modern, CSS3/Flex/Grid/Font bozmayan halefi) yükleyici
    let screenshotLibPromise = null;
    function loadScreenshotEngine() {
        if (window.modernScreenshot) return Promise.resolve({ type: 'modern', fn: window.modernScreenshot.domToCanvas });
        if (screenshotLibPromise) return screenshotLibPromise;

        screenshotLibPromise = new Promise((resolve) => {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/modern-screenshot@4.6.0/dist/index.js';
            script.async = true;
            script.onload = () => {
                if (window.modernScreenshot && window.modernScreenshot.domToCanvas) {
                    resolve({ type: 'modern', fn: window.modernScreenshot.domToCanvas });
                } else {
                    loadFallbackHtml2Canvas().then(resolve);
                }
            };
            script.onerror = () => {
                loadFallbackHtml2Canvas().then(resolve);
            };
            document.head.appendChild(script);
        });
        return screenshotLibPromise;
    }

    function loadFallbackHtml2Canvas() {
        if (window.html2canvas) return Promise.resolve({ type: 'h2c', fn: window.html2canvas });
        return new Promise((resolve) => {
            const script = document.createElement('script');
            script.src = baseOrigin + '/Public/js/html2canvas.min.js';
            script.async = true;
            script.onload = () => resolve(window.html2canvas ? { type: 'h2c', fn: window.html2canvas } : null);
            script.onerror = () => resolve(null);
            document.head.appendChild(script);
        });
    }

    loadScreenshotEngine();

    // 2. CSS Stillerini Enjekte Et
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
            backdrop-filter: blur(8px);
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
        /* Sivri Ucu Tam Tıklanan Noktayı Gösteren Gerçek Teardrop Pin */
        .tm-pin-marker {
            position: absolute;
            z-index: 2147483641;
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, #7b68ee, #9b5de5);
            color: #ffffff;
            border: 2.5px solid #ffffff;
            border-radius: 50% 50% 50% 0;
            /* Sivri sol-alt köşeyi tam (x, y) koordinatına oturtur */
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
            position: absolute;
            z-index: 2147483645;
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
            <img id="tm-pin-preview-img" style="max-width:100%; max-height:130px; display:block; margin:0 auto; object-fit:contain;" src="">
            <div style="font-size:10px; color:#00b894; padding:3px 6px; background:rgba(0,184,148,0.1); display:flex; align-items:center; justify-content:center; gap:4px;">
                <span>📸</span> <span>Birebir ekran görüntüsü & pin yakalandı</span>
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
                📸 Snapshot Hazır
            </div>
        </div>
        <div class="tm-actions">
            <button type="button" class="btn-cancel" id="tm-pin-dialog-cancel">İptal</button>
            <button type="button" class="btn-submit" id="tm-pin-dialog-submit">🚀 TaskManager'a Gönder</button>
        </div>
    `;
    document.body.appendChild(dialog);

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

    triggerBtn.addEventListener('click', () => togglePinMode());
    document.getElementById('tm-pin-cancel-mode').addEventListener('click', () => togglePinMode(false));
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && isPinMode) togglePinMode(false);
    });

    document.addEventListener('mouseover', (e) => {
        if (!isPinMode) return;
        const target = e.target;
        if (target.closest('#tm-pin-trigger, #tm-pin-banner, #tm-pin-dialog, .tm-pin-marker')) return;

        if (hoveredElement && hoveredElement !== target) {
            hoveredElement.classList.remove('tm-pin-hover-target');
        }
        hoveredElement = target;
        hoveredElement.classList.add('tm-pin-hover-target');
    });

    // Pin çizici (Canvas üzerine birebir aynı Teardrop pini çizer)
    function drawPinOnCanvas(ctx, pinX, pinY, dpr) {
        ctx.save();
        ctx.translate(pinX, pinY);

        ctx.shadowColor = 'rgba(0, 0, 0, 0.4)';
        ctx.shadowBlur = 10 * dpr;
        ctx.shadowOffsetY = 4 * dpr;

        // Sivri ucu tam (0, 0) noktasında olan Teardrop Pin
        ctx.beginPath();
        ctx.moveTo(0, 0);
        ctx.bezierCurveTo(-13 * dpr, -12 * dpr, -16 * dpr, -28 * dpr, 0, -38 * dpr);
        ctx.bezierCurveTo(16 * dpr, -28 * dpr, 13 * dpr, -12 * dpr, 0, 0);
        ctx.closePath();

        const pinGrad = ctx.createLinearGradient(-14 * dpr, -38 * dpr, 14 * dpr, 0);
        pinGrad.addColorStop(0, '#7b68ee');
        pinGrad.addColorStop(1, '#9b5de5');
        ctx.fillStyle = pinGrad;
        ctx.fill();

        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 2.5 * dpr;
        ctx.stroke();

        ctx.shadowColor = 'transparent';
        ctx.fillStyle = '#ffffff';
        ctx.font = `bold ${Math.round(12 * dpr)}px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('1', 0, -23 * dpr);

        ctx.restore();
    }

    // 7. Tıklama: Pin Bırak ve Birebir Ekran Görüntüsü Al
    document.addEventListener('click', async (e) => {
        if (!isPinMode) return;
        const target = e.target;
        if (target.closest('#tm-pin-trigger, #tm-pin-banner, #tm-pin-dialog, .tm-pin-marker')) return;

        e.preventDefault();
        e.stopPropagation();

        const x = e.pageX;
        const y = e.pageY;
        const clientX = e.clientX;
        const clientY = e.clientY;
        const scrollX = window.pageXOffset || document.documentElement.scrollLeft || 0;
        const scrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
        const viewportW = window.innerWidth;
        const viewportH = window.innerHeight;
        const selector = getCssSelector(target);

        if (currentPin) currentPin.remove();

        currentPin = document.createElement('div');
        currentPin.className = 'tm-pin-marker';
        currentPin.style.left = x + 'px';
        currentPin.style.top = y + 'px';
        currentPin.innerHTML = '<span>1</span>';
        document.body.appendChild(currentPin);

        togglePinMode(false);

        let screenshotData = '';
        const previewBox = document.getElementById('tm-pin-preview-box');
        const previewImg = document.getElementById('tm-pin-preview-img');
        const snapshotStatus = document.getElementById('tm-pin-snapshot-status');

        if (snapshotStatus) snapshotStatus.textContent = '⏳ Ekran alınıyor...';
        if (previewBox) previewBox.style.display = 'none';

        try {
            triggerBtn.style.visibility = 'hidden';
            banner.style.visibility = 'hidden';
            dialog.style.display = 'none';
            if (currentPin) currentPin.style.visibility = 'hidden';

            if (document.fonts && document.fonts.ready) {
                try { await document.fonts.ready; } catch (_) {}
            }

            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const engine = await loadScreenshotEngine();

            // Nihai Viewport Tuvali
            const finalCanvas = document.createElement('canvas');
            finalCanvas.width = viewportW * dpr;
            finalCanvas.height = viewportH * dpr;
            const ctx = finalCanvas.getContext('2d');

            if (engine && engine.type === 'modern') {
                // YÖNTEM 1: modern-screenshot (Tarayıcının kendi SVG/ForeignObject motorunu kullanır, CSS'i asla bozmaz!)
                const fullCanvas = await engine.fn(document.body, {
                    scale: dpr,
                    backgroundColor: window.getComputedStyle(document.body).backgroundColor || '#ffffff',
                    filter: (node) => {
                        if (node.id === 'tm-pin-trigger' || node.id === 'tm-pin-banner' || node.id === 'tm-pin-dialog') return false;
                        if (node.classList && node.classList.contains('tm-pin-marker')) return false;
                        return true;
                    }
                });

                // Sadece kullanıcının o an gördüğü Viewport alanını kırp
                ctx.drawImage(
                    fullCanvas,
                    scrollX * dpr, scrollY * dpr, viewportW * dpr, viewportH * dpr,
                    0, 0, viewportW * dpr, viewportH * dpr
                );
            } else if (engine && engine.type === 'h2c') {
                // YÖNTEM 2: html2canvas Fallback (Tasarımı bozan onclone döngüleri kaldırıldı!)
                const h2cCanvas = await engine.fn(document.documentElement, {
                    useCORS: true,
                    allowTaint: true,
                    logging: false,
                    scale: dpr,
                    x: scrollX,
                    y: scrollY,
                    width: viewportW,
                    height: viewportH,
                    windowWidth: viewportW,
                    windowHeight: viewportH,
                    onclone: (clonedDoc) => {
                        // Sadece input içindeki metinlerin altının kesilmesini (padding/line-height) düzelt
                        clonedDoc.querySelectorAll('input[type="text"], input[type="search"], input:not([type])').forEach(inp => {
                            inp.style.lineHeight = 'normal';
                        });
                    }
                });
                ctx.drawImage(h2cCanvas, 0, 0, finalCanvas.width, finalCanvas.height);
            }

            // Pini tam tıklanan koordinata (clientX, clientY) damgala
            drawPinOnCanvas(ctx, clientX * dpr, clientY * dpr, dpr);
            screenshotData = finalCanvas.toDataURL('image/jpeg', 0.94);

        } catch (err) {
            console.warn('[TaskManager Pin] Ekran görüntüsü alınamadı:', err);
        } finally {
            triggerBtn.style.visibility = 'visible';
            banner.style.visibility = 'visible';
            if (currentPin) currentPin.style.visibility = 'visible';
        }

        const dialogWidth = 340;
        let leftPos = x + 20;
        if (leftPos + dialogWidth > viewportW + scrollX) {
            leftPos = x - dialogWidth - 20;
        }
        let topPos = y + 10;
        if (topPos + 340 > viewportH + scrollY) {
            topPos = Math.max(scrollY + 10, y - 340);
        }
        dialog.style.left = Math.max(10, leftPos) + 'px';
        dialog.style.top = topPos + 'px';
        dialog.style.display = 'block';

        if (screenshotData && previewBox && previewImg) {
            previewImg.src = screenshotData;
            previewBox.style.display = 'block';
            if (snapshotStatus) snapshotStatus.textContent = '📸 Resim Eklendi';
        } else if (snapshotStatus) {
            snapshotStatus.textContent = '📍 DOM Pinli';
        }

        document.getElementById('tm-pin-meta').textContent = `Öğe: ${selector.substring(0, 48)}...`;
        document.getElementById('tm-pin-title').value = `[Pin] ${target.tagName.toLowerCase()}${target.id ? '#' + target.id : ''} öğesinde sorun`;
        document.getElementById('tm-pin-title').focus();

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
            screenshot: screenshotData
        };
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
                screenshot: data.screenshot || '',
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
                alert(`✅ Görsel Hata Görevi ve Ekran Görüntüsü Başarıyla Oluşturuldu!\nGörev Kodu: ${code}\nEkran görüntüsü panoya eklendi.`);
                dialog.style.display = 'none';
            } else {
                alert('⚠️ Hata: ' + (result.message || 'Görev oluşturulamadı.'));
            }
        } catch (err) {
            alert('❌ Bağlantı hatası: TaskManager API ulaşılamadı.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = '🚀 TaskManager\'a Gönder';
        }
    });
})();