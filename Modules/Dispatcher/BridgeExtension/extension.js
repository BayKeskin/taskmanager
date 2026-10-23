const vscode = require('vscode');
const fs = require('fs');
const path = require('path');
const https = require('https');
const child_process = require('child_process');

// ── Protobuf Codec Helpers (Zero Dependencies) ──
function encodeVarint(value) {
    const bytes = [];
    if (value === 0) return Buffer.from([0]);
    while (value > 0x7F) {
        bytes.push((value & 0x7F) | 0x80);
        value = Math.floor(value / 128);
    }
    bytes.push(value & 0x7F);
    return Buffer.from(bytes);
}

function encodeTag(fieldNumber, wireType) {
    return encodeVarint((fieldNumber << 3) | wireType);
}

function encodeLengthDelimited(fieldNumber, data) {
    const tag = encodeTag(fieldNumber, 2);
    const len = encodeVarint(data.length);
    return Buffer.concat([tag, len, data]);
}

function encodeString(fieldNumber, value) {
    const buf = Buffer.from(value, 'utf-8');
    return Buffer.concat([encodeTag(fieldNumber, 2), encodeVarint(buf.length), buf]);
}

function encodeVarintField(fieldNumber, value) {
    return Buffer.concat([encodeTag(fieldNumber, 0), encodeVarint(value)]);
}

function encodeMessage(fieldNumber, payload) {
    return Buffer.concat([encodeTag(fieldNumber, 2), encodeVarint(payload.length), payload]);
}

function buildUserStatusUpdate(name, email) {
    const proto = Buffer.concat([
        encodeVarintField(2, 1),
        encodeString(3, name || email.split('@')[0]),
        encodeString(7, email)
    ]);
    const row = encodeString(1, proto.toString('base64'));
    const update = Buffer.concat([
        encodeString(1, 'userStatusSentinelKey'),
        encodeMessage(2, row)
    ]);
    return Buffer.concat([
        encodeString(1, 'uss-userStatus'),
        encodeMessage(5, update)
    ]).toString('base64');
}

// ── Language Server Discovery & HTTPS Registration ──
function findLSEndpoints() {
    return new Promise((resolve) => {
        const ps = `powershell -NoProfile -Command "Get-CimInstance Win32_Process -Filter \\"Name = 'language_server_windows_x64.exe'\\" | Select-Object -ExpandProperty CommandLine"`;
        child_process.exec(ps, { timeout: 6000 }, (err, stdout) => {
            if (err || !stdout) {
                return resolve([]);
            }
            const endpoints = [];
            const lines = stdout.split('\n').map(l => l.trim()).filter(Boolean);
            for (const line of lines) {
                const portMatch = line.match(/--https_server_port[=\s]+(\d+)/);
                const csrfMatch = line.match(/--csrf_token[=\s]+([\w-]+)/);
                if (portMatch && csrfMatch) {
                    endpoints.push({
                        port: parseInt(portMatch[1], 10),
                        csrf: csrfMatch[1]
                    });
                }
            }
            resolve(endpoints);
        });
    });
}

function callRegisterGdmUser(endpoint) {
    return new Promise((resolve) => {
        const req = https.request({
            hostname: '127.0.0.1',
            port: endpoint.port,
            path: '/exa.language_server_pb.LanguageServerService/RegisterGdmUser',
            method: 'POST',
            rejectUnauthorized: false,
            headers: {
                'Content-Type': 'application/json',
                'Connect-Protocol-Version': '1',
                'x-codeium-csrf-token': endpoint.csrf
            },
            timeout: 5000
        }, (res) => {
            resolve({ port: endpoint.port, status: res.statusCode });
        });
        req.on('error', (e) => {
            resolve({ port: endpoint.port, error: e.message });
        });
        req.write('{}');
        req.end();
    });
}

// ── Extension Activation ──
function activate(context) {
    console.log('[taskmanager-bridge] Activated successfully.');

    // Path to IPC request & ack files in Modules/Dispatcher/Bin
    let binDir = '';
    const wsFolders = vscode.workspace.workspaceFolders;
    if (wsFolders && wsFolders.length > 0) {
        for (const wf of wsFolders) {
            const candidate = path.join(wf.uri.fsPath, 'Modules', 'Dispatcher', 'Bin');
            if (fs.existsSync(candidate)) {
                binDir = candidate;
                break;
            }
        }
    }
    if (!binDir) {
        // Fallback to relative to extension if run from inside workspace
        const extCandidate = path.resolve(__dirname, '..', 'Bin');
        if (fs.existsSync(extCandidate)) {
            binDir = extCandidate;
        } else {
            // Environment based root
            const sysDrive = process.env.SystemDrive || 'C:';
            binDir = path.join(sysDrive, 'wamp64', 'www', 'task' + 'manager', 'Modules', 'Dispatcher', 'Bin');
        }
    }

    const requestFile = path.join(binDir, 'bridge_switch_request.json');
    const ackFile = path.join(binDir, 'bridge_switch_ack.json');

    async function handleSwitchRequest() {
        if (!fs.existsSync(requestFile)) return;

        let reqData = null;
        try {
            const raw = fs.readFileSync(requestFile, 'utf-8');
            reqData = JSON.parse(raw);
            fs.unlinkSync(requestFile);
        } catch (e) {
            return;
        }

        if (!reqData || !reqData.email) return;

        const email = reqData.email;
        const name = reqData.name || email.split('@')[0];
        const accessToken = reqData.access_token || '';
        const refreshToken = reqData.refresh_token || '';
        const expiryTimestamp = parseInt(reqData.expiry_timestamp, 10) || (Math.floor(Date.now() / 1000) + 3600);

        console.log(`[taskmanager-bridge] Processing account switch to: ${email}`);

        const result = {
            email,
            uss_user_status: false,
            uss_oauth_token: false,
            auth_refresh_command: false,
            ls_endpoints_count: 0,
            ls_responses: [],
            success: false,
            timestamp: Math.floor(Date.now() / 1000)
        };

        try {
            const uss = vscode.antigravityUnifiedStateSync || null;
            if (uss) {
                // 1. Instant UserStatus update to USS (RAM)
                try {
                    const updatePayload = buildUserStatusUpdate(name, email);
                    await uss.pushSerializedUpdateIPC(updatePayload);
                    result.uss_user_status = true;
                } catch (e_u) {
                    console.error('[taskmanager-bridge] Error pushing userStatus:', e_u);
                }

                // 2. Set OAuthTokenInfo to USS (RAM)
                if (accessToken && uss.OAuthPreferences && typeof uss.OAuthPreferences.setOAuthTokenInfo === 'function') {
                    try {
                        await uss.OAuthPreferences.setOAuthTokenInfo({
                            accessToken,
                            refreshToken,
                            expiryDateSeconds: expiryTimestamp,
                            tokenType: 'Bearer',
                            isGcpTos: false
                        });
                        result.uss_oauth_token = true;
                    } catch (e_t) {
                        console.error('[taskmanager-bridge] Error setting oauthTokenInfo:', e_t);
                    }
                }
            }

            // 3. Trigger Antigravity internal auth refresh command
            try {
                await vscode.commands.executeCommand('antigravity.handleAuthRefresh');
                result.auth_refresh_command = true;
            } catch (e_cmd) {
                console.warn('[taskmanager-bridge] handleAuthRefresh command error (non-fatal):', e_cmd?.message);
            }

            // 4. Register new user session on Language Server processes
            const endpoints = await findLSEndpoints();
            result.ls_endpoints_count = endpoints.length;
            for (const ep of endpoints) {
                const res = await callRegisterGdmUser(ep);
                result.ls_responses.push(res);
            }

            result.success = true;
            console.log(`[taskmanager-bridge] Switch completed for ${email} across ${endpoints.length} Language Server(s).`);
        } catch (err) {
            result.error = err.message;
            console.error('[taskmanager-bridge] Fatal switch error:', err);
        }

        try {
            fs.writeFileSync(ackFile, JSON.stringify(result, null, 2), 'utf-8');
        } catch (e_ack) {
            console.error('[taskmanager-bridge] Error writing ack file:', e_ack);
        }
    }

    // Periodic check every 1 second + fs.watch on directory
    const timer = setInterval(() => {
        handleSwitchRequest();
    }, 1000);

    const dirToWatch = path.dirname(requestFile);
    let watcher = null;
    try {
        if (fs.existsSync(dirToWatch)) {
            watcher = fs.watch(dirToWatch, (eventType, filename) => {
                if (filename === 'bridge_switch_request.json') {
                    handleSwitchRequest();
                }
            });
        }
    } catch (e) {
        console.warn('[taskmanager-bridge] Watcher error:', e);
    }

    context.subscriptions.push({
        dispose: () => {
            clearInterval(timer);
            if (watcher) watcher.close();
        }
    });
}

function deactivate() {
    console.log('[taskmanager-bridge] Deactivated.');
}

module.exports = {
    activate,
    deactivate
};
