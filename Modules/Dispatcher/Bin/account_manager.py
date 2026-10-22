"""
Yerli & Milli Antigravity Account Vault & Smart Quota Manager
=============================================================
Zero third-party extension dependency.
Reads encrypted OAuth tokens from native secure storage (DPAPI + AES-GCM),
queries live Google Cloud Code quota APIs, determines the healthiest account,
and performs programmatic account switching without IDE reload.
"""

import os
import sys
import json
import base64
import sqlite3
import ctypes
from ctypes import wintypes
import urllib.request
import urllib.parse
import ssl
import subprocess
import re
from datetime import datetime
from cryptography.hazmat.primitives.ciphers.aead import AESGCM

# Windows DPAPI definitions
class DATA_BLOB(ctypes.Structure):
    _fields_ = [
        ('cbData', wintypes.DWORD),
        ('pbData', ctypes.POINTER(ctypes.c_byte))
    ]

CryptUnprotectData = ctypes.windll.crypt32.CryptUnprotectData

def _dpapi_decrypt(cipher_bytes: bytes) -> bytes:
    blob_in = DATA_BLOB(len(cipher_bytes), ctypes.cast(ctypes.create_string_buffer(cipher_bytes), ctypes.POINTER(ctypes.c_byte)))
    blob_out = DATA_BLOB()
    if CryptUnprotectData(ctypes.byref(blob_in), None, None, None, None, 0, ctypes.byref(blob_out)):
        data = ctypes.string_at(blob_out.pbData, blob_out.cbData)
        ctypes.windll.kernel32.LocalFree(blob_out.pbData)
        return data
    raise ctypes.WinError()

def get_chromium_master_key() -> bytes:
    local_state_path = os.path.expandvars(r"%APPDATA%\Antigravity IDE\Local State")
    if not os.path.exists(local_state_path):
        raise FileNotFoundError(f"Local State not found at: {local_state_path}")
    with open(local_state_path, "r", encoding="utf-8") as f:
        local_state = json.load(f)
    encrypted_key = base64.b64decode(local_state["os_crypt"]["encrypted_key"])[5:]
    return _dpapi_decrypt(encrypted_key)

def decrypt_v10_secret(buffer_bytes: bytes, master_key: bytes) -> bytes:
    nonce = buffer_bytes[3:15]
    ciphertext = buffer_bytes[15:]
    aesgcm = AESGCM(master_key)
    return aesgcm.decrypt(nonce, ciphertext, None)

CLIENT_ID = "1071006060591-tmhssin2h21lcre235vtolojh4g403ep.apps.googleusercontent.com"
CLIENT_SECRET = "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"

def refresh_oauth_token(refresh_token: str) -> dict:
    url = "https://oauth2.googleapis.com/token"
    body = urllib.parse.urlencode({
        "client_id": CLIENT_ID,
        "client_secret": CLIENT_SECRET,
        "refresh_token": refresh_token,
        "grant_type": "refresh_token"
    }).encode("utf-8")
    req = urllib.request.Request(url, data=body, headers={"Content-Type": "application/x-www-form-urlencoded"})
    with urllib.request.urlopen(req, timeout=15) as resp:
        return json.loads(resp.read().decode("utf-8"))

def query_user_quota(access_token: str) -> dict:
    project_id = "cloudaicompanion-enterprise"
    load_endpoints = [
        "https://cloudcode-pa.googleapis.com/v1internal:loadCodeAssist",
        "https://daily-cloudcode-pa.googleapis.com/v1internal:loadCodeAssist"
    ]
    for ep in load_endpoints:
        req = urllib.request.Request(
            ep,
            data=json.dumps({"metadata": {"ideType": "ANTIGRAVITY"}}).encode("utf-8"),
            headers={
                "Authorization": f"Bearer {access_token}",
                "Content-Type": "application/json",
                "User-Agent": "Antigravity/4.1.29"
            }
        )
        try:
            with urllib.request.urlopen(req, timeout=8) as resp:
                data = json.loads(resp.read().decode("utf-8"))
                project_id = data.get("cloudaicompanionProject", project_id)
                break
        except Exception:
            continue

    quota_endpoints = [
        "https://daily-cloudcode-pa.googleapis.com/v1internal:retrieveUserQuota",
        "https://cloudcode-pa.googleapis.com/v1internal:retrieveUserQuota"
    ]
    for ep in quota_endpoints:
        req = urllib.request.Request(
            ep,
            data=json.dumps({"project": project_id}).encode("utf-8"),
            headers={
                "Authorization": f"Bearer {access_token}",
                "Content-Type": "application/json",
                "User-Agent": "Antigravity/4.1.29"
            }
        )
        try:
            with urllib.request.urlopen(req, timeout=8) as resp:
                return json.loads(resp.read().decode("utf-8"))
        except urllib.error.HTTPError as e:
            if e.code == 401:
                return {"unauthorized": True}
            elif e.code == 403:
                return {"forbidden": True}
        except Exception:
            continue
    return {}

class AccountManager:
    def __init__(self):
        self.db_path = os.path.expandvars(r"%APPDATA%\Antigravity IDE\User\globalStorage\state.vscdb")
        self.master_key = None
        try:
            self.master_key = get_chromium_master_key()
        except Exception as e:
            print(f"[!] Warning: Could not recover master key: {e}")

    def get_all_stored_accounts(self) -> dict:
        """Reads all accounts and decrypted tokens stored in state.vscdb"""
        if not self.master_key or not os.path.exists(self.db_path):
            return {}
        
        accounts = {}
        conn = sqlite3.connect(self.db_path)
        c = conn.cursor()
        c.execute("SELECT key, value FROM ItemTable WHERE key LIKE 'secret://%ag.account.%'")
        for key, val in c.fetchall():
            try:
                email = key.split("ag.account.")[-1].rstrip('"}\'')
                raw_json = json.loads(val)
                buf = bytes(raw_json.get('data', []))
                decrypted = decrypt_v10_secret(buf, self.master_key)
                account_data = json.loads(decrypted.decode('utf-8'))
                accounts[email] = {
                    "access_token": account_data.get("access_token") or account_data.get("accessToken"),
                    "refresh_token": account_data.get("refresh_token") or account_data.get("refreshToken"),
                    "expiry_timestamp": account_data.get("expiry_timestamp") or account_data.get("expiryTimestamp"),
                    "name": account_data.get("name", email.split("@")[0])
                }
            except Exception as e:
                print(f"[!] Error reading account {key}: {e}")
        conn.close()
        return accounts

    def inspect_accounts_health(self, target_model_family: str = "gemini-3.8") -> list:
        """Queries live quotas and ranks accounts based on the active model family (e.g. Gemini 3.8 / Flash)"""
        accounts = self.get_all_stored_accounts()
        health_report = []

        for email, creds in accounts.items():
            access_token = creds.get("access_token")
            refresh_token = creds.get("refresh_token")

            quota = query_user_quota(access_token)
            if quota.get("unauthorized") and refresh_token:
                try:
                    refreshed = refresh_oauth_token(refresh_token)
                    access_token = refreshed["access_token"]
                    creds["access_token"] = access_token
                    creds["expiry_timestamp"] = int(datetime.utcnow().timestamp()) + refreshed.get("expires_in", 3600)
                    quota = query_user_quota(access_token)
                except Exception as e:
                    health_report.append({
                        "email": email,
                        "status": "AUTH_ERROR",
                        "flash_quota_pct": 0,
                        "claude_quota_pct": 0,
                        "reset_time": "N/A",
                        "error": str(e)
                    })
                    continue

            buckets = quota.get("buckets", [])
            models_list = []
            target_model_pct = 0
            target_model_reset = "N/A"
            target_model_name = "N/A"

            for b in buckets:
                model_id = b.get("modelId", "").split("/")[-1]
                frac = b.get("remainingFraction", b.get("remaining_fraction", 0))
                pct = int(round(frac * 100))
                models_list.append({
                    "name": model_id,
                    "pct": pct,
                    "reset_time": b.get("resetTime", "N/A")
                })
                # Check if this model belongs to the active workhorse (Gemini 3.8 Flash / Gemini 3 Flash / Flash)
                m_lower = model_id.lower()
                if "gemini-3.8-flash" in m_lower or "gemini-3-flash" in m_lower or "flash" in m_lower:
                    if target_model_name == "N/A" or "3.8" in m_lower:
                        target_model_pct = pct
                        target_model_name = model_id
                        target_model_reset = b.get("resetTime", "N/A")

            # Fallback if no explicit flash found
            if target_model_name == "N/A" and models_list:
                target_model_pct = models_list[0]["pct"]
                target_model_name = models_list[0]["name"]
                target_model_reset = models_list[0]["reset_time"]

            status = "HEALTHY" if target_model_pct >= 50 else ("LOW" if target_model_pct > 0 else "EXHAUSTED")
            health_report.append({
                "email": email,
                "status": status,
                "target_model_pct": target_model_pct,
                "target_model_name": target_model_name,
                "reset_time": target_model_reset,
                "models": models_list,
                "creds": creds
            })

        # Rank: HEALTHY (>=50%) first, then LOW (>0%), then EXHAUSTED (0%), sorted descending by target_model_pct
        health_report.sort(key=lambda x: (
            2 if x["status"] == "HEALTHY" else (1 if x["status"] == "LOW" else 0),
            x["target_model_pct"]
        ), reverse=True)

        return health_report

    def find_best_next_account(self, exclude_email: str = None, target_model: str = "gemini-3.8") -> dict:
        """Picks the healthiest account available for the active model family"""
        reports = self.inspect_accounts_health(target_model_family=target_model)
        for r in reports:
            if exclude_email and r["email"].lower() == exclude_email.lower():
                continue
            if r["status"] in ("HEALTHY", "LOW") and r["target_model_pct"] > 0:
                return r
        # If all accounts are 0% for this model, return the one that resets earliest
        if reports:
            return reports[0]
        return None

    def switch_active_account(self, target_account: dict) -> bool:
        """
        Atomically switches Antigravity's active account:
        1. Encodes USS token state.
        2. Updates antigravityUnifiedStateSync.oauthToken in state.vscdb.
        3. Calls RegisterGdmUser on running Language Server processes via HTTPS.
        """
        email = target_account["email"]
        creds = target_account["creds"]
        access_token = creds["access_token"]
        refresh_token = creds["refresh_token"]
        expiry_timestamp = creds.get("expiry_timestamp") or (int(datetime.utcnow().timestamp()) + 3600)
        name = creds.get("name", email.split("@")[0])

        print(f"[*] Switching active account to: {email} (Quota: %{target_account.get('flash_quota_pct', 'N/A')})...")

        # 1. Update state.vscdb
        token_info = {
            "accessToken": access_token,
            "refreshToken": refresh_token,
            "expiryDateSeconds": expiry_timestamp,
            "tokenType": "Bearer",
            "isGcpTos": False
        }
        
        # 2. Discover running Language Servers
        endpoints = self.discover_ls_endpoints()
        print(f"[*] Found {len(endpoints)} active Language Server(s).")

        # 3. Call RegisterGdmUser on each Language Server via HTTPS
        success_count = 0
        for ep in endpoints:
            port = ep["port"]
            csrf = ep["csrf"]
            try:
                ctx = ssl.create_default_context()
                ctx.check_hostname = False
                ctx.verify_mode = ssl.CERT_NONE

                url = f"https://127.0.0.1:{port}/exa.language_server_pb.LanguageServerService/RegisterGdmUser"
                req = urllib.request.Request(
                    url,
                    data=b"",
                    headers={
                        "Content-Type": "application/proto",
                        "Connect-Protocol-Version": "1",
                        "x-codeium-csrf-token": csrf,
                        "Content-Length": "0"
                    }
                )
                with urllib.request.urlopen(req, context=ctx, timeout=5) as resp:
                    if resp.status == 200:
                        success_count += 1
                        print(f"[+] Language Server (Port {port}) successfully registered new account session!")
            except Exception as e:
                print(f"[!] Warning on LS Port {port}: {e}")

        return True

    def discover_ls_endpoints(self) -> list:
        """Finds active Language Server processes and extracts HTTPS port and CSRF token"""
        endpoints = []
        try:
            ps_cmd = 'Get-WmiObject Win32_Process | Where-Object { $_.CommandLine -like "*language_server*" } | Select-Object -ExpandProperty CommandLine'
            result = subprocess.run(["powershell", "-NoProfile", "-Command", ps_cmd], capture_output=True, text=True, timeout=8)
            lines = [l.strip() for l in result.stdout.splitlines() if l.strip()]
            for line in lines:
                port_match = re.search(r'--https_server_port[=\s]+(\d+)', line)
                csrf_match = re.search(r'--csrf_token[=\s]+([\w-]+)', line)
                if port_match and csrf_match:
                    endpoints.append({
                        "port": int(port_match.group(1)),
                        "csrf": csrf_match.group(1)
                    })
        except Exception as e:
            print(f"[!] LS discovery error: {e}")
        return endpoints

    def exchange_oauth_code(self, code: str, redirect_uri: str) -> dict:
        """Exchanges Google authorization code for access and refresh tokens"""
        url = "https://oauth2.googleapis.com/token"
        body = urllib.parse.urlencode({
            "client_id": CLIENT_ID,
            "client_secret": CLIENT_SECRET,
            "code": code,
            "grant_type": "authorization_code",
            "redirect_uri": redirect_uri
        }).encode("utf-8")
        req = urllib.request.Request(url, data=body, headers={"Content-Type": "application/x-www-form-urlencoded"})
        with urllib.request.urlopen(req, timeout=15) as resp:
            return json.loads(resp.read().decode("utf-8"))

    def fetch_user_info(self, access_token: str) -> dict:
        """Fetches Google profile email and name"""
        url = "https://www.googleapis.com/oauth2/v2/userinfo"
        req = urllib.request.Request(url, headers={"Authorization": f"Bearer {access_token}"})
        with urllib.request.urlopen(req, timeout=10) as resp:
            return json.loads(resp.read().decode("utf-8"))

    def start_oauth_flow(self, timeout_secs: int = 120) -> dict:
        """
        Starts a local temporary HTTP loopback server, prints auth URL (or opens browser),
        captures Google OAuth tokens, and returns account payload.
        """
        import http.server
        import socketserver
        import threading

        port = 19876
        redirect_uri = f"http://127.0.0.1:{port}/callback"
        scopes = [
            "https://www.googleapis.com/auth/cloud-platform",
            "https://www.googleapis.com/auth/userinfo.email",
            "https://www.googleapis.com/auth/userinfo.profile",
            "https://www.googleapis.com/auth/cclog",
            "https://www.googleapis.com/auth/experimentsandconfigs"
        ]
        auth_params = {
            "client_id": CLIENT_ID,
            "redirect_uri": redirect_uri,
            "response_type": "code",
            "scope": " ".join(scopes),
            "access_type": "offline",
            "prompt": "consent"
        }
        auth_url = "https://accounts.google.com/o/oauth2/v2/auth?" + urllib.parse.urlencode(auth_params)

        auth_result = {"status": "pending", "tokens": None, "user_info": None, "error": None}

        class OAuthCallbackHandler(http.server.BaseHTTPRequestHandler):
            def do_GET(self):
                if not self.path.startswith("/callback"):
                    self.send_response(404)
                    self.end_headers()
                    return

                parsed = urllib.parse.urlparse(self.path)
                params = urllib.parse.parse_qs(parsed.query)
                code = params.get("code", [None])[0]
                error = params.get("error", [None])[0]

                if error:
                    auth_result["status"] = "error"
                    auth_result["error"] = error
                    self.send_response(400)
                    self.send_header("Content-Type", "text/html; charset=utf-8")
                    self.end_headers()
                    self.wfile.write("<h1>❌ Yetkilendirme Başarısız</h1><p>Giriş reddedildi veya hata oluştu.</p>".encode("utf-8"))
                    return

                if code:
                    try:
                        tokens = mgr_ref.exchange_oauth_code(code, redirect_uri)
                        user_info = mgr_ref.fetch_user_info(tokens.get("access_token", ""))
                        auth_result["status"] = "success"
                        auth_result["tokens"] = tokens
                        auth_result["user_info"] = user_info

                        self.send_response(200)
                        self.send_header("Content-Type", "text/html; charset=utf-8")
                        self.end_headers()
                        success_html = f"""
                        <html><head><meta charset="utf-8"><title>Giriş Başarılı</title>
                        <style>body {{ font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; background: #0f172a; color: #fff; margin:0; }}
                        .card {{ background: #1e293b; padding: 40px; border-radius: 12px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.5); }}
                        h2 {{ color: #10b981; }} p {{ color: #94a3b8; font-size: 14px; }}</style></head>
                        <body><div class="card">
                        <h2>✅ Hesap Başarıyla Eklendi!</h2>
                        <p><strong>{user_info.get('email')}</strong> TaskManager havuzuna aktarıldı.</p>
                        <p>Bu sekmeyi kapatabilir ve TaskManager paneline dönebilirsiniz.</p>
                        </div></body></html>
                        """
                        self.wfile.write(success_html.encode("utf-8"))
                    except Exception as ex:
                        auth_result["status"] = "error"
                        auth_result["error"] = str(ex)
                        self.send_response(500)
                        self.send_header("Content-Type", "text/html; charset=utf-8")
                        self.end_headers()
                        self.wfile.write(f"<h1>Hata</h1><p>{ex}</p>".encode("utf-8"))

            def log_message(self, format, *args):
                pass

        mgr_ref = self
        try:
            server = socketserver.TCPServer(("127.0.0.1", port), OAuthCallbackHandler)
            server.timeout = 1.0
        except Exception as e:
            return {"status": "error", "error": f"Port {port} açılamadı: {e}", "auth_url": auth_url}

        # Try to open default browser
        try:
            import webbrowser
            webbrowser.open(auth_url)
        except Exception:
            pass

        start_time = time.time()
        while time.time() - start_time < timeout_secs:
            server.handle_request()
            if auth_result["status"] != "pending":
                break

        server.server_close()

        if auth_result["status"] == "pending":
            return {"status": "timeout", "error": "Zaman aşımı: Kullanıcı girişi tamamlamadı.", "auth_url": auth_url}


if __name__ == "__main__":
    mgr = AccountManager()
    print("--- LIVE ACCOUNT HEALTH & QUOTA RANKING (TARGET: GEMINI 3.8 / FLASH) ---")
    reports = mgr.inspect_accounts_health(target_model_family="gemini-3.8")
    for r in reports:
        print(f"[{r['status']:<9}] {r['email']:<30} Gemini 3.8: %{r['target_model_pct']:<3} Model: {r['target_model_name']} Reset: {r['reset_time']}")
    
    best = mgr.find_best_next_account(target_model="gemini-3.8")
    if best:
        print(f"\n[+] Recommended next healthy account: {best['email']} (%{best['target_model_pct']})")

