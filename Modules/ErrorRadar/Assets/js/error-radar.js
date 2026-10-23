/**
 * TaskManager Autonomous Error & Network Radar Client Engine
 * - Zero-Performance-Loss Background Tracker
 * - Intercepts JS Console Errors & Unhandled Promise Rejections
 * - Intercepts 404/500 HTTP Network Failures and Xdebug/PHP Fatals in AJAX
 * - Scans Visible Text for Broken/Untranslated i18n Keys
 * - Deduplicates via In-Memory Set and reports to ErrorRadarController
 */
(function () {
    'use strict';

    if (window.__TM_ERROR_RADAR_INITIALIZED__) {
        return;
    }
    window.__TM_ERROR_RADAR_INITIALIZED__ = true;

    var reportedSet = new Set();
    var isSending = false;
    var queue = [];

    function getBaseUrl() {
        if (window.BASE_URL) {
            return window.BASE_URL.replace(/\/+$/, '');
        }
        var loc = window.location;
        var p = loc.pathname;
        var m = p.match(/^(\/[^\/]+)/);
        return m ? m[1] : '';
    }

    function getProjectId() {
        try {
            var params = new URLSearchParams(window.location.search);
            if (params.has('project_id')) {
                return params.get('project_id');
            }
        } catch (e) {}

        if (window.CURRENT_PROJECT_ID) {
            return window.CURRENT_PROJECT_ID;
        }
        return '01';
    }

    function sendReport(payload) {
        if (!payload || !payload.message) {
            return;
        }

        var key = (payload.type || '') + '|' + (payload.message || '') + '|' + (payload.endpoint || '') + '|' + (payload.location || '');
        if (reportedSet.has(key)) {
            return;
        }
        reportedSet.add(key);

        payload.url = window.location.href;
        payload.project_id = getProjectId();
        payload.timestamp = new Date().toISOString();

        queue.push(payload);
        processQueue();
    }

    function processQueue() {
        if (isSending || queue.length === 0) {
            return;
        }

        isSending = true;
        var item = queue.shift();
        var targetUrl = getBaseUrl() + '/api/v1/error-radar';

        try {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', targetUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/json; charset=utf-8');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.__tm_radar_skip = true;

            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    isSending = false;
                    setTimeout(processQueue, 300);
                }
            };
            xhr.send(JSON.stringify(item));
        } catch (e) {
            isSending = false;
        }
    }

    // 1. JS Console & Runtime Errors
    window.addEventListener('error', function (event) {
        try {
            var target = event.target || event.srcElement;
            if (target && (target.tagName === 'LINK' || target.tagName === 'SCRIPT' || target.tagName === 'IMG')) {
                var assetSrc = target.src || target.href || '';
                if (assetSrc) {
                    sendReport({
                        type: 'static_asset',
                        message: 'Failed to load static asset: ' + assetSrc,
                        endpoint: assetSrc,
                        location: target.tagName.toLowerCase()
                    });
                }
                return;
            }

            var errMsg = event.message || (event.error && event.error.message) || 'Unknown Script Error';
            if (errMsg === 'Script error.' && !event.filename) {
                return;
            }

            var loc = (event.filename || '') + (event.lineno ? (':' + event.lineno + (event.colno ? ':' + event.colno : '')) : '');
            var stack = (event.error && event.error.stack) ? event.error.stack : '';

            sendReport({
                type: 'js_console_error',
                message: errMsg,
                location: loc,
                stack: stack
            });
        } catch (e) {}
    }, true);

    // 2. Unhandled Promise Rejections
    window.addEventListener('unhandledrejection', function (event) {
        try {
            var reason = event.reason;
            var msg = 'Unhandled Promise Rejection';
            var stack = '';

            if (reason) {
                if (typeof reason === 'string') {
                    msg = reason;
                } else if (reason.message) {
                    msg = reason.message;
                    stack = reason.stack || '';
                } else {
                    msg = JSON.stringify(reason);
                }
            }

            sendReport({
                type: 'unhandled_rejection',
                message: msg,
                stack: stack
            });
        } catch (e) {}
    });

    // 3. Network Failures & PHP/Xdebug Sniffer
    var PHP_ERROR_PATTERNS = [
        'xdebug-error',
        'Fatal error:',
        'PDOException:',
        'Uncaught Error:',
        'Parse error:',
        'Warning:',
        'Notice:'
    ];

    function inspectResponse(responseText, endpoint, status) {
        if (!responseText || typeof responseText !== 'string') {
            return;
        }

        var hasPhpError = false;
        var matchedPattern = '';
        for (var i = 0; i < PHP_ERROR_PATTERNS.length; i++) {
            if (responseText.indexOf(PHP_ERROR_PATTERNS[i]) !== -1) {
                hasPhpError = true;
                matchedPattern = PHP_ERROR_PATTERNS[i];
                break;
            }
        }

        // 1. Eğer istek görev detaylarını veya kart HTML çıktısını çekiyorsa, içinde bug raporu metni geçmesi doğaldır, yoksay
        if (endpoint.indexOf('get_task_detail') !== -1 || endpoint.indexOf('get_task_card_html') !== -1) {
            return;
        }

        // 2. Eğer yanıt geçerli ve ayrıştırılabilir bir JSON ise, bu bir sunucu çökmesi/xdebug tablosu değil, normal bir API yanıtıdır
        var trimmed = responseText.trim();
        if ((trimmed.startsWith('{') && trimmed.endsWith('}')) || (trimmed.startsWith('[') && trimmed.endsWith(']'))) {
            try {
                JSON.parse(trimmed);
                return;
            } catch (e) {}
        }

        if (hasPhpError) {
            var snippet = responseText;
            var cleanText = snippet.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
            sendReport({
                type: 'php_xdebug',
                message: 'PHP/Xdebug error detected in response (' + matchedPattern + ')',
                endpoint: endpoint,
                status: status,
                response: cleanText.substring(0, 1500)
            });
            return;
        }

        if (status >= 400) {
            sendReport({
                type: 'network_error',
                message: 'HTTP ' + status + ' error on endpoint ' + endpoint,
                endpoint: endpoint,
                status: status,
                response: responseText.substring(0, 1000)
            });
        }
    }

    // Intercept XMLHttpRequest
    var origXhrOpen = XMLHttpRequest.prototype.open;
    var origXhrSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function (method, url) {
        this.__tm_endpoint = url;
        return origXhrOpen.apply(this, arguments);
    };

    XMLHttpRequest.prototype.send = function () {
        var self = this;
        var endpoint = self.__tm_endpoint || '';

        if (!self.__tm_radar_skip && endpoint.indexOf('error-radar') === -1) {
            self.addEventListener('load', function () {
                inspectResponse(self.responseText, endpoint, self.status);
            });
            self.addEventListener('error', function () {
                sendReport({
                    type: 'network_error',
                    message: 'Network connection failure on ' + endpoint,
                    endpoint: endpoint,
                    status: self.status || 0
                });
            });
        }

        return origXhrSend.apply(this, arguments);
    };

    // Intercept window.fetch
    if (window.fetch) {
        var origFetch = window.fetch;
        window.fetch = function () {
            var args = arguments;
            var url = '';
            if (typeof args[0] === 'string') {
                url = args[0];
            } else if (args[0] && args[0].url) {
                url = args[0].url;
            }

            if (url && url.indexOf('error-radar') !== -1) {
                return origFetch.apply(this, args);
            }

            return origFetch.apply(this, args).then(function (res) {
                var clone = res.clone();
                clone.text().then(function (bodyText) {
                    inspectResponse(bodyText, url, res.status);
                }).catch(function () {});
                return res;
            }).catch(function (err) {
                sendReport({
                    type: 'network_error',
                    message: (err && err.message) ? err.message : 'Fetch request failed',
                    endpoint: url,
                    status: 0
                });
                throw err;
            });
        };
    }

    // 4. Broken Translation (i18n) Key Sniffer
    var I18N_REGEX = /\b([A-Z][a-zA-Z0-9]+)::([a-z0-9_]+)\b/g;

    function scanVisibleI18nKeys(rootNode) {
        try {
            var target = rootNode || document.body;
            if (!target) return;

            var walker = document.createTreeWalker(target, NodeFilter.SHOW_TEXT, null, false);
            var node;
            while ((node = walker.nextNode())) {
                var text = node.nodeValue;
                if (!text || text.indexOf('::') === -1) continue;

                var matches;
                while ((matches = I18N_REGEX.exec(text)) !== null) {
                    var fullKey = matches[0];
                    if (fullKey && fullKey.length > 5) {
                        sendReport({
                            type: 'broken_i18n',
                            message: 'Untranslated / Broken i18n key visible on page: ' + fullKey,
                            location: fullKey
                        });
                    }
                }
            }
        } catch (e) {}
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(scanVisibleI18nKeys, 1000);
        });
    } else {
        setTimeout(scanVisibleI18nKeys, 1000);
    }

    // Observe Dynamic DOM & Modal Openings
    var observer = new MutationObserver(function (mutations) {
        for (var i = 0; i < mutations.length; i++) {
            var m = mutations[i];
            if (m.addedNodes && m.addedNodes.length > 0) {
                for (var j = 0; j < m.addedNodes.length; j++) {
                    var n = m.addedNodes[j];
                    if (n.nodeType === 1) {
                        scanVisibleI18nKeys(n);
                    }
                }
            }
        }
    });

    if (document.body) {
        observer.observe(document.body, { childList: true, subtree: true });
    } else {
        document.addEventListener('DOMContentLoaded', function () {
            if (document.body) {
                observer.observe(document.body, { childList: true, subtree: true });
            }
        });
    }

    // Expose for testing and manual autonomous dispatch
    window.ErrorRadar = {
        report: sendReport,
        scanI18n: scanVisibleI18nKeys,
        getReportedSet: function () { return reportedSet; }
    };
})();
