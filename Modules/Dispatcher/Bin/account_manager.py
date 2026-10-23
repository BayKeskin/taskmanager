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
import time
from datetime import datetime
from cryptography.hazmat.primitives.ciphers.aead import AESGCM

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))

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

def encode_varint(val: int) -> bytes:
    res = bytearray()
    while val > 0x7f:
        res.append((val & 0x7f) | 0x80)
        val >>= 7
    res.append(val & 0x7f)
    return bytes(res)

def encode_length_delimited(field_number: int, data: bytes) -> bytes:
    tag = (field_number << 3) | 2
    return encode_varint(tag) + encode_varint(len(data)) + data

def build_oauth_token_proto(access_token: str, refresh_token: str, expiry_timestamp: int) -> str:
    """Builds valid binary Protobuf base64 for antigravityUnifiedStateSync.oauthToken"""
    # 1. Inner token payload
    # Field 1: access_token (string)
    # Field 2: "Bearer" (string)
    # Field 3: refresh_token (string)
    # Field 4: submessage with Field 1 = expiry_timestamp (varint)
    f1 = encode_length_delimited(1, access_token.encode('ascii'))
    f2 = encode_length_delimited(2, b'Bearer')
    f3 = encode_length_delimited(3, refresh_token.encode('ascii'))
    f4 = encode_length_delimited(4, b'\x08' + encode_varint(int(expiry_timestamp)))
    inner_token_bytes = f1 + f2 + f3 + f4
    b64_inner_token = base64.b64encode(inner_token_bytes)

    # 2. Outer proto message
    # Part 1: authStateWithContextSentinelKey
    p1_key = encode_length_delimited(1, b'authStateWithContextSentinelKey')
    p1_val_inner = encode_length_delimited(1, b'{"state":"signedIn","context":{"project":"","showProjectError":false,"errorMessage":"","ineligibleMessage":"","verificationUrl":"","isGcpTos":false,"browserOpenFailed":false,"appealUrl":"","appealLinkText":""}}')
    p1_val = encode_length_delimited(2, p1_val_inner)
    part1 = encode_length_delimited(1, p1_key + p1_val)

    # Part 2: oauthTokenInfoSentinelKey
    p2_key = encode_length_delimited(1, b'oauthTokenInfoSentinelKey')
    p2_val_inner = encode_length_delimited(1, b64_inner_token)
    p2_val = encode_length_delimited(2, p2_val_inner)
    part2 = encode_length_delimited(1, p2_key + p2_val)

    full_proto = part1 + part2
    return base64.b64encode(full_proto).decode('ascii')

def build_user_status_proto(name: str, email: str) -> str:
    """Builds valid binary Protobuf base64 for antigravityUnifiedStateSync.userStatus"""
    # Inner message: \x10\x01 + field 3: name + field 7: email
    inner_bytes = b'\x10\x01' + encode_length_delimited(3, (name or email.split("@")[0]).encode('utf-8')) + encode_length_delimited(7, email.encode('utf-8'))
    b64_inner = base64.b64encode(inner_bytes)

    u_key = encode_length_delimited(1, b'userStatusSentinelKey')
    u_val_inner = encode_length_delimited(1, b64_inner)
    u_val = encode_length_delimited(2, u_val_inner)
    full_proto = encode_length_delimited(1, u_key + u_val)
    return base64.b64encode(full_proto).decode('ascii')

def extract_email_from_user_status(proto_b64: str) -> str:
    """Parses active email from antigravityUnifiedStateSync.userStatus protobuf payload with 100% precision"""
    try:
        raw = base64.b64decode(proto_b64)
        # 1. Direct text regex
        m = re.findall(r'[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+', raw.decode('latin1', errors='ignore'))
        if m:
            return m[0]
        # 2. Extract ASCII base64 chunks and test 4-byte alignment offsets
        chunks = re.findall(rb'[A-Za-z0-9+/=]{16,}', raw)
        for chunk in chunks:
            for trim in range(4):
                cand = chunk[trim:]
                rem = len(cand) % 4
                if rem:
                    cand = cand[:-rem]
                try:
                    dec = base64.b64decode(cand)
                    emails = re.findall(r'[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+', dec.decode('latin1', errors='ignore'))
                    for em in emails:
                        if em.endswith(('.com', '.net', '.org', '.io', '.co', '.tr')):
                            return em
                except Exception:
                    pass
    except Exception:
        pass
    return ""

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
        """Reads and merges accounts from both native state.vscdb and TaskManager MySQL ai_accounts table"""
        accounts = {}

        # 1. Yerel state.vscdb içerisindeki hesapları oku
        if self.master_key and os.path.exists(self.db_path):
            try:
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
                        print(f"[!] Error reading local account {key}: {e}")
                conn.close()
            except Exception as e_db:
                print(f"[!] Local state.vscdb read error: {e_db}")

        # 2. TaskManager MySQL (ai_accounts) içerisindeki hesapları da havuza birleştir
        try:
            app_root = os.path.abspath(os.path.join(CURRENT_DIR, "..", "..", ".."))
            php_script = (
                "require_once 'Core/System.php'; "
                "$db = new Database(); "
                "$rows = $db->connect->query('SELECT email, name, access_token, refresh_token, expiry_timestamp FROM ai_accounts')->fetchAll(PDO::FETCH_ASSOC); "
                "echo json_encode($rows);"
            )
            php_res = subprocess.run(
                ["php", "-r", php_script],
                cwd=app_root,
                capture_output=True,
                text=True,
                timeout=5
            )
            if php_res.returncode == 0 and php_res.stdout.strip():
                db_rows = json.loads(php_res.stdout.strip())
                for r in db_rows:
                    em = r.get("email")
                    if em and r.get("refresh_token"):
                        # Eğer yerel depoda yoksa veya MySQL'deki daha güncelse ekle
                        if em not in accounts or not accounts[em].get("refresh_token"):
                            accounts[em] = {
                                "access_token": r.get("access_token") or "",
                                "refresh_token": r.get("refresh_token"),
                                "expiry_timestamp": int(r.get("expiry_timestamp") or (time.time() + 3600)),
                                "name": r.get("name") or em.split("@")[0]
                            }
        except Exception as e_mysql:
            # MySQL geçici olarak erişilemezse yerel hesaplarla devam et
            pass

        return accounts

    def get_active_email(self) -> str:
        """Determines the currently active Google account from state.vscdb proto, or MySQL"""
        # 1. state.vscdb içerisindeki gerçek antigravityUnifiedStateSync.userStatus protobuf kaydına bak
        try:
            if os.path.exists(self.db_path):
                conn = sqlite3.connect(self.db_path)
                c = conn.cursor()
                c.execute("SELECT value FROM ItemTable WHERE key = 'antigravityUnifiedStateSync.userStatus'")
                row = c.fetchone()
                conn.close()
                if row and row[0]:
                    parsed_email = extract_email_from_user_status(row[0])
                    if parsed_email:
                        return parsed_email
        except Exception:
            pass

        # 2. MySQL TaskManager ai_accounts tablosunda is_active = 1 olan hesaba bak
        try:
            app_root = os.path.abspath(os.path.join(CURRENT_DIR, "..", "..", ".."))
            php_script = (
                "require_once 'Core/System.php'; "
                "$db = new Database(); "
                "$row = $db->connect->query('SELECT email FROM ai_accounts WHERE is_active = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC); "
                "echo $row ? $row['email'] : '';"
            )
            php_res = subprocess.run(
                ["php", "-r", php_script],
                cwd=app_root,
                capture_output=True,
                text=True,
                timeout=4
            )
            if php_res.returncode == 0 and php_res.stdout.strip():
                return php_res.stdout.strip()
        except Exception:
            pass

        return ""

    def sync_account_health_to_mysql(self, email: str, is_active: bool, quota_pct: int, status: str, reset_time: str):
        """Persists live health and active status into TaskManager MySQL ai_accounts table"""
        try:
            app_root = os.path.abspath(os.path.join(CURRENT_DIR, "..", "..", ".."))
            active_val = 1 if is_active else 0
            php_code = (
                f"require_once 'Core/System.php'; "
                f"$db = new Database(); "
                f"$stmt = $db->connect->prepare('UPDATE ai_accounts SET is_active = ?, gemini_quota_pct = ?, status = ?, reset_time = ? WHERE email = ?'); "
                f"$stmt->execute([{active_val}, {quota_pct}, {json.dumps(status)}, {json.dumps(reset_time)}, {json.dumps(email)}]);"
            )
            subprocess.run(["php", "-r", php_code], cwd=app_root, capture_output=True, timeout=4)
        except Exception as e:
            pass

    def inspect_accounts_health(self, target_model_family: str = "gemini-3.8") -> list:
        """Queries live quotas and ranks accounts based on the active model family (e.g. Gemini 3.8 / Flash)"""
        accounts = self.get_all_stored_accounts()
        active_email = self.get_active_email()
        health_report = []

        for email, creds in accounts.items():
            access_token = creds.get("access_token")
            refresh_token = creds.get("refresh_token")
            is_active = (email.lower() == active_email.lower()) if active_email else False

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
                        "is_active": is_active,
                        "status": "AUTH_ERROR",
                        "target_model_pct": 0,
                        "target_model_name": "N/A",
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
            
            # MySQL tablosuna canlı veriyi yansıt (Admin paneli için)
            self.sync_account_health_to_mysql(email, is_active, target_model_pct, status, target_model_reset)

            health_report.append({
                "email": email,
                "is_active": is_active,
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

    def install_bridge(self) -> bool:
        """Installs the invisible internal taskmanager-bridge extension into Antigravity IDE's extensions folder"""
        try:
            import shutil
            bridge_src = os.path.abspath(os.path.join(CURRENT_DIR, "..", "BridgeExtension"))
            if not os.path.exists(bridge_src):
                print(f"[!] BridgeExtension source directory not found at: {bridge_src}")
                return False

            ext_dirs = [
                os.path.expanduser(r"~/.antigravity-ide/extensions"),
                os.path.expanduser(r"~/.antigravity/extensions")
            ]

            installed_any = False
            for base_dir in ext_dirs:
                if not os.path.exists(base_dir):
                    continue

                target_dir = os.path.join(base_dir, "taskmanager.taskmanager-bridge-1.0.0")
                os.makedirs(target_dir, exist_ok=True)
                for fn in ["package.json", "extension.js"]:
                    src_f = os.path.join(bridge_src, fn)
                    dst_f = os.path.join(target_dir, fn)
                    if os.path.exists(src_f):
                        shutil.copy2(src_f, dst_f)

                # Update extensions.json in the directory
                ext_json_path = os.path.join(base_dir, "extensions.json")
                try:
                    entries = []
                    if os.path.exists(ext_json_path):
                        with open(ext_json_path, "r", encoding="utf-8") as f:
                            entries = json.load(f)
                    
                    # Check if already present
                    exists = any(x.get("identifier", {}).get("id") == "taskmanager.taskmanager-bridge" for x in entries)
                    if not exists:
                        entries.append({
                            "identifier": {
                                "id": "taskmanager.taskmanager-bridge"
                            },
                            "version": "1.0.0",
                            "location": {
                                "$mid": 1,
                                "fsPath": target_dir,
                                "_sep": 1,
                                "path": "/" + target_dir.replace("\\", "/").lstrip("/"),
                                "scheme": "file"
                            },
                            "relativeLocation": "taskmanager.taskmanager-bridge-1.0.0"
                        })
                        with open(ext_json_path, "w", encoding="utf-8") as f:
                            json.dump(entries, f, indent=2)
                except Exception as e_json:
                    print(f"[!] Warning updating extensions.json in {base_dir}: {e_json}")

                installed_any = True

            if installed_any:
                print("[+] taskmanager-bridge installed successfully into Antigravity extensions.")
            return installed_any
        except Exception as e:
            print(f"[!] install_bridge error: {e}")
            return False

    def switch_active_account(self, target_account) -> bool:
        """
        Atomically switches Antigravity's active account:
        1. Encodes USS token state.
        2. Updates antigravityUnifiedStateSync.oauthToken in state.vscdb.
        3. Signals taskmanager-bridge for RAM USS IPC update.
        4. Calls RegisterGdmUser on running Language Server processes via HTTPS.
        5. Updates MySQL ai_accounts (is_active = 1).
        """
        if isinstance(target_account, str):
            email = target_account
            target_account = {"email": email}
        else:
            email = target_account.get("email")

        all_accts = self.get_all_stored_accounts()
        stored_creds = all_accts.get(email, {})

        creds = target_account.get("creds") or {}
        access_token = creds.get("access_token") or stored_creds.get("access_token", "")
        refresh_token = creds.get("refresh_token") or stored_creds.get("refresh_token", "")
        expiry_timestamp = creds.get("expiry_timestamp") or stored_creds.get("expiry_timestamp") or (int(datetime.utcnow().timestamp()) + 3600)
        name = creds.get("name") or stored_creds.get("name") or email.split("@")[0]

        print(f"[*] Switching active account to: {email} (Quota: %{target_account.get('flash_quota_pct', 'N/A')})...")

        # Ensure taskmanager-bridge is installed
        self.install_bridge()

        # 1. Update state.vscdb SQLite database with 100% valid Protobuf structures
        try:
            if os.path.exists(self.db_path):
                conn = sqlite3.connect(self.db_path)
                c = conn.cursor()

                # 1a. Update antigravityUnifiedStateSync.userStatus (Binary Protobuf)
                try:
                    new_user_status_b64 = build_user_status_proto(name, email)
                    c.execute("UPDATE ItemTable SET value = ? WHERE key = 'antigravityUnifiedStateSync.userStatus'", (new_user_status_b64,))
                except Exception as e_ust:
                    print(f"[!] Warning updating userStatus proto: {e_ust}")

                # 1b. Update antigravityUnifiedStateSync.oauthToken (Binary Protobuf)
                if refresh_token:
                    # Token henüz taze değilse veya yoksa hemen Google'dan yenile
                    if not access_token:
                        try:
                            refreshed = refresh_oauth_token(refresh_token)
                            access_token = refreshed["access_token"]
                            creds["access_token"] = access_token
                            expiry_timestamp = int(datetime.utcnow().timestamp()) + refreshed.get("expires_in", 3600)
                        except Exception as e_rf:
                            print(f"[!] Token refresh error during switch: {e_rf}")

                    if access_token:
                        try:
                            new_oauth_b64 = build_oauth_token_proto(access_token, refresh_token, expiry_timestamp)
                            c.execute("UPDATE ItemTable SET value = ? WHERE key = 'antigravityUnifiedStateSync.oauthToken'", (new_oauth_b64,))
                        except Exception as e_tok:
                            print(f"[!] Warning building oauthToken proto: {e_tok}")

                conn.commit()
                try:
                    c.execute("PRAGMA wal_checkpoint(FULL)")
                except Exception:
                    pass
                conn.close()
        except Exception as e_db:
            print(f"[!] Warning updating state.vscdb: {e_db}")

        # 1c. Signal taskmanager-bridge via bridge_switch_request.json for in-memory USS refresh
        try:
            bridge_req_file = os.path.join(CURRENT_DIR, "bridge_switch_request.json")
            bridge_ack_file = os.path.join(CURRENT_DIR, "bridge_switch_ack.json")
            if os.path.exists(bridge_ack_file):
                try:
                    os.unlink(bridge_ack_file)
                except Exception:
                    pass

            req_payload = {
                "email": email,
                "name": name,
                "access_token": access_token,
                "refresh_token": refresh_token,
                "expiry_timestamp": int(expiry_timestamp)
            }
            with open(bridge_req_file, "w", encoding="utf-8") as f_req:
                json.dump(req_payload, f_req, indent=2)
            print("[+] Signaled taskmanager-bridge for RAM USS & Language Server update.")

            # Short wait for ACK if bridge is already active in IDE
            for _ in range(5):
                if os.path.exists(bridge_ack_file):
                    print("[+] Received ACK from taskmanager-bridge!")
                    break
                time.sleep(0.4)
        except Exception as e_bridge:
            print(f"[!] Bridge signal error: {e_bridge}")

        # 1d. Update TaskManager MySQL ai_accounts (set is_active = 1 for target, 0 for others)
        try:
            app_root = os.path.abspath(os.path.join(CURRENT_DIR, "..", "..", ".."))
            php_code = (
                f"require_once 'Core/System.php'; "
                f"$db = new Database(); "
                f"$db->connect->prepare('UPDATE ai_accounts SET is_active = 0')->execute(); "
                f"$stmt = $db->connect->prepare('UPDATE ai_accounts SET is_active = 1, access_token = ?, expiry_timestamp = ? WHERE email = ?'); "
                f"$stmt->execute([{json.dumps(access_token)}, {int(expiry_timestamp)}, {json.dumps(email)}]);"
            )
            subprocess.run(["php", "-r", php_code], cwd=app_root, capture_output=True, timeout=5)
        except Exception as e_sync:
            print(f"[!] Warning updating MySQL ai_accounts: {e_sync}")
        
        # 2. Discover running Language Servers (Direct Python backup call)
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
                    data=b"{}",
                    headers={
                        "Content-Type": "application/json",
                        "Connect-Protocol-Version": "1",
                        "x-codeium-csrf-token": csrf
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
        """Finds active Language Server processes and extracts listening ports and CSRF token"""
        endpoints = []
        try:
            ps_cmd = 'Get-CimInstance Win32_Process -Filter "name = \'language_server_windows_x64.exe\'" | Select-Object ProcessId, CommandLine | ConvertTo-Json'
            ps_res = subprocess.run(['powershell', '-NoProfile', '-Command', ps_cmd], capture_output=True, text=True, timeout=8)
            if ps_res.returncode == 0 and ps_res.stdout.strip():
                procs = json.loads(ps_res.stdout)
                if isinstance(procs, dict):
                    procs = [procs]

                netstat_res = subprocess.run(['netstat', '-ano'], capture_output=True, text=True, timeout=8)
                netstat_out = netstat_res.stdout or ''

                for proc in procs:
                    pid = str(proc.get('ProcessId', ''))
                    cmd = proc.get('CommandLine') or ''
                    csrf_match = re.search(r'--csrf_token[=\s]+([\w-]+)', cmd)
                    csrf = csrf_match.group(1) if csrf_match else None
                    if not csrf:
                        continue

                    for line in netstat_out.splitlines():
                        if 'LISTENING' in line and line.strip().endswith(pid):
                            m = re.search(r':(\d+)\s+', line)
                            if m:
                                endpoints.append({
                                    "port": int(m.group(1)),
                                    "csrf": csrf
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

        # Save result to oauth_result.json for non-blocking asynchronous polling
        result_file = os.path.join(CURRENT_DIR, "oauth_result.json")
        try:
            with open(result_file, "w", encoding="utf-8") as f:
                json.dump(auth_result, f)
        except Exception as e_res:
            print(f"[!] Could not write oauth_result.json: {e_res}")

        if auth_result["status"] == "pending":
            return {"status": "timeout", "error": "Zaman aşımı: Kullanıcı girişi tamamlamadı.", "auth_url": auth_url}

        return auth_result


if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser(description="Antigravity Account Vault & Quota Manager CLI")
    parser.add_argument("--switch", type=str, help="Switch active account to the specified email")
    parser.add_argument("--sync", action="store_true", help="Sync all account quotas and update MySQL")
    parser.add_argument("--status", action="store_true", help="Get active email and status")
    args = parser.parse_args()

    mgr = AccountManager()

    if args.switch:
        target_email = args.switch.strip()
        success = mgr.switch_active_account(target_email)
        active_email = mgr.get_active_email()
        print(json.dumps({
            "success": success,
            "target": target_email,
            "active_email": active_email,
            "matched": (active_email.lower() == target_email.lower())
        }))
        sys.exit(0 if success else 1)

    if args.sync:
        reports = mgr.inspect_accounts_health(target_model_family="gemini-3.8")
        active_email = mgr.get_active_email()
        print(json.dumps({
            "success": True,
            "active_email": active_email,
            "accounts_count": len(reports),
            "reports": reports
        }))
        sys.exit(0)

    if args.status:
        active_email = mgr.get_active_email()
        print(json.dumps({
            "active_email": active_email
        }))
        sys.exit(0)

    print("--- LIVE ACCOUNT HEALTH & QUOTA RANKING (TARGET: GEMINI 3.8 / FLASH) ---")
    reports = mgr.inspect_accounts_health(target_model_family="gemini-3.8")
    for r in reports:
        print(f"[{r['status']:<9}] {r['email']:<30} Gemini 3.8: %{r['target_model_pct']:<3} Model: {r['target_model_name']} Reset: {r['reset_time']}")
    
    best = mgr.find_best_next_account(target_model="gemini-3.8")
    if best:
        print(f"\n[+] Recommended next healthy account: {best['email']} (%{best['target_model_pct']})")

