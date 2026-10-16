"""
TaskManager -> Antigravity Central Multi-Agent Dispatcher (v6.0 - Connect-RPC Multi-Window Engine)
1. Açık olmayan proje pencerelerini otomatik başlatır (Antigravity IDE -n <workspace_path>).
2. Her açık pencerenin kendi Language Server sürecini ve Connect-RPC portunu bağımsız eşleştirir.
3. StartCascade + SendUserCascadeMessage + SmartFocusConversation ile sıfır dokunuşlu,
   başlıklı ve içi dolu olarak hedef pencerede canlı sohbet başlatır ve ekrana odaklar.
4. Var olan sohbetleri haksız yere hayalet ilan edip silme döngüsüne girmez.
"""
import http.server
import json
import subprocess
import sys
import os
import glob
import sqlite3
import re
import time
import urllib.parse
import urllib.request
import ssl
import threading

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
LOG_FILE = os.path.join(BASE_DIR, 'dispatcher.log')
WORKSPACES_CONFIG = os.path.join(BASE_DIR, 'workspaces.json')
LS_ENV_FILE = os.path.join(BASE_DIR, 'ls_env.json')
LS_ADDRESS_FILE = os.path.join(BASE_DIR, 'ls_address.txt')
CSRF_TOKEN_FILE = os.path.join(BASE_DIR, 'csrf_token.txt')

NO_WINDOW_FLAG = subprocess.CREATE_NO_WINDOW if sys.platform == 'win32' else 0

project_active_state = {}
state_lock = threading.Lock()

IGNORED_AGENT_EVENTS = {
    'task.completed',
    'task.created',
    'task.status_changed',
    'task.deleted'
}

def run_silent(cmd, **kwargs):
    """Tüm alt süreçleri siyah CMD penceresi açmadan sessizce çalıştırır."""
    if sys.platform == 'win32':
        kwargs['creationflags'] = kwargs.get('creationflags', 0) | NO_WINDOW_FLAG
    if kwargs.get('text') and 'encoding' not in kwargs:
        kwargs['encoding'] = 'utf-8'
        kwargs['errors'] = 'replace'
    return subprocess.run(cmd, **kwargs)

def watchdog_loop():
    print("🛡️ [WATCHDOG] Otonom Kurtarma ve Kuyruk Denetim Motoru Aktif (Sessiz Mod)!")
    time.sleep(10)
    app_root = os.path.dirname(BASE_DIR)

    while True:
        try:
            insp_cmd = ['php', '-r', "require 'System/System.php'; Helper::runWatchdogInspection();"]
            run_silent(insp_cmd, capture_output=True, text=True, cwd=app_root, timeout=10)
        except Exception:
            pass
        time.sleep(20)


class Logger:
    def __init__(self, filepath):
        self.terminal = sys.stdout
        try:
            self.logfile = open(filepath, 'a', encoding='utf-8')
        except Exception:
            self.logfile = None

    def write(self, message):
        if self.terminal:
            try:
                self.terminal.write(message)
            except Exception:
                pass
        if self.logfile:
            try:
                self.logfile.write(message)
                self.logfile.flush()
            except Exception:
                pass

    def flush(self):
        if self.terminal:
            try:
                self.terminal.flush()
            except Exception:
                pass
        if self.logfile:
            try:
                self.logfile.flush()
            except Exception:
                pass

logger = Logger(LOG_FILE)
sys.stdout = logger
sys.stderr = logger

PORT = 4000
AGENTAPI_EXE = r"c:\Users\DELL\AppData\Local\Programs\Antigravity IDE\resources\app\extensions\antigravity\bin\language_server_windows_x64.exe"
ANTIGRAVITY_EXE = r"C:\Users\DELL\AppData\Local\Programs\Antigravity IDE\Antigravity IDE.exe"
ANTIGRAVITY_CLI = r"C:\Users\DELL\AppData\Local\Programs\Antigravity IDE\bin\antigravity-ide.cmd"
ANTIGRAVITY_CONV_DIR = r"C:\Users\DELL\.gemini\antigravity-ide\conversations"

SSL_CTX = ssl._create_unverified_context()

def get_language_server_processes():
    ps_cmd = 'Get-CimInstance Win32_Process -Filter "name = \'language_server_windows_x64.exe\'" | Select-Object ProcessId, CommandLine | ConvertTo-Json'
    try:
        res = run_silent(['powershell', '-NoProfile', '-Command', ps_cmd], capture_output=True, text=True, timeout=12)
        if res.returncode == 0 and res.stdout.strip():
            data = json.loads(res.stdout)
            if isinstance(data, dict):
                return [data]
            elif isinstance(data, list):
                return data
    except Exception as e:
        print(f"⚠️ LS süreçleri alınırken hata/zaman aşımı: {e}")
    return []

def get_workspace_folder(workspace_path):
    if not workspace_path:
        return ""
    norm = workspace_path.replace('\\', '/').rstrip('/').lower()
    return os.path.basename(norm)

def is_workspace_match(cmd, workspace_path):
    if not cmd or not workspace_path:
        return False
    folder = get_workspace_folder(workspace_path)
    cmd_lower = cmd.replace('\\', '/').lower()
    return f"_{folder}" in cmd_lower or f"/{folder}" in cmd_lower

def find_workspace_ls_process(workspace_path):
    if not workspace_path:
        return None
    for pr in get_language_server_processes():
        cmd = pr.get('CommandLine') or ''
        if is_workspace_match(cmd, workspace_path):
            return pr
    return None

def ensure_workspace_window_open(workspace_path):
    """
    Hedef projenin klasörü için Antigravity IDE penceresi açık değilse,
    yeni pencereyi başlatır ve Language Server'ın ayağa kalkmasını bekler.
    """
    if not workspace_path or not os.path.isdir(workspace_path):
        return None

    proc = find_workspace_ls_process(workspace_path)
    if proc:
        return proc

    folder = get_workspace_folder(workspace_path)
    print(f"🚀 [OTOMATİK IDE PENCERESİ BAŞLATILIYOR] [{folder}] için Antigravity penceresi açılıyor...")

    if os.path.exists(ANTIGRAVITY_CLI):
        run_silent(['cmd', '/c', 'call', ANTIGRAVITY_CLI, '-n', workspace_path], timeout=15)
    elif os.path.exists(ANTIGRAVITY_EXE):
        subprocess.Popen([ANTIGRAVITY_EXE, '-n', workspace_path], creationflags=NO_WINDOW_FLAG)

    for i in range(20):
        time.sleep(1)
        proc = find_workspace_ls_process(workspace_path)
        if proc:
            print(f"✅ [IDE PENCERESİ HAZIR] [{folder}] Language Server ayağa kalktı! PID: {proc.get('ProcessId')} ({i+1} sn)")
            time.sleep(2)
            return proc

    print(f"❌ [HATA] [{folder}] için Antigravity IDE penceresi 20 saniye içinde açılamadı.")
    return None

def get_workspace_rpc_env(workspace_path):
    """
    Hedef workspace'in açık penceresine ait Language Server'ın
    Connect-RPC HTTPS portunu ve CSRF token'ını tespit eder.
    """
    proc = ensure_workspace_window_open(workspace_path)
    if not proc:
        # Son çare açık olan herhangi bir LS sürecini dene
        procs = get_language_server_processes()
        proc = procs[0] if procs else None
    if not proc:
        return None, None

    pid = str(proc.get('ProcessId', ''))
    cmd = proc.get('CommandLine') or ''
    token_m = re.search(r'--csrf_token\s+([a-zA-Z0-9\-]+)', cmd)
    token = token_m.group(1) if token_m else ""

    netstat_res = run_silent(['netstat', '-ano'], capture_output=True, text=True, timeout=5)
    netstat = netstat_res.stdout or ''

    candidate_ports = []
    for line in netstat.splitlines():
        if 'LISTENING' in line and line.strip().endswith(pid):
            m = re.search(r'127\.0\.0\.1:(\d+)', line)
            if m and m.group(1) not in candidate_ports:
                candidate_ports.append(m.group(1))

    for port in candidate_ports:
        url = f"https://127.0.0.1:{port}/exa.language_server_pb.LanguageServerService/Heartbeat"
        req = urllib.request.Request(
            url,
            data=b'{}',
            headers={
                "Content-Type": "application/json",
                "X-Codeium-Csrf-Token": token
            },
            method="POST"
        )
        try:
            with urllib.request.urlopen(req, context=SSL_CTX, timeout=2) as resp:
                if resp.status == 200:
                    return port, token
        except Exception:
            continue

    return None, None

CASCADE_DEFAULT_CONFIG = {
    "planner_config": {
        "requested_model": {
            "model": "MODEL_PLACEHOLDER_M318"
        },
        "plan_model": "MODEL_PLACEHOLDER_M318"
    }
}

def start_cascade_conversation(workspace_path, prompt, title=""):
    """
    Connect-RPC üzerinden yeni bir cascade oturumu başlatır,
    görev metnini (User Turn) doğrudan cascade içine ekler
    ve SmartFocusConversation ile pencere ekranında aktif sekmeye getirir.
    """
    rpc_port, token = get_workspace_rpc_env(workspace_path)
    if not rpc_port or not token:
        print(f"❌ [CONNECT-RPC] [{get_workspace_folder(workspace_path)}] için RPC port/token bulunamadı!")
        return None

    norm_path = workspace_path.replace('\\', '/').rstrip('/')
    ws_uri = f"file:///{norm_path}" if not norm_path.startswith('/') else f"file://{norm_path}"

    payload = {
        "workspace_uris": [ws_uri],
        "source": "CORTEX_TRAJECTORY_SOURCE_CASCADE_CLIENT"
    }
    body = json.dumps(payload).encode('utf-8')

    url_start = f"https://127.0.0.1:{rpc_port}/exa.language_server_pb.LanguageServerService/StartCascade"
    req_start = urllib.request.Request(
        url_start,
        data=body,
        headers={
            "Content-Type": "application/json",
            "X-Codeium-Csrf-Token": token
        },
        method="POST"
    )

    try:
        with urllib.request.urlopen(req_start, context=SSL_CTX, timeout=6) as resp:
            if resp.status == 200:
                data = json.loads(resp.read().decode('utf-8'))
                cid = data.get("cascadeId") or data.get("cascade_id")
                if cid:
                    print(f"🎉 [CONNECT-RPC BAŞARILI] StartCascade ile yeni sohbet başlatıldı! Port: {rpc_port}, ID: {cid}")
                    time.sleep(0.5)

                    # 1. Kullanıcı mesaj turn'ünü (User Turn) model konfigürasyonuyla birlikte ekle (Zero-Touch Executor)
                    try:
                        url_msg = f"https://127.0.0.1:{rpc_port}/exa.language_server_pb.LanguageServerService/SendUserCascadeMessage"
                        msg_payload = {
                            "cascade_id": cid,
                            "cascadeId": cid,
                            "items": [{"text": prompt}],
                            "cascade_config": CASCADE_DEFAULT_CONFIG
                        }
                        req_msg = urllib.request.Request(
                            url_msg,
                            data=json.dumps(msg_payload).encode('utf-8'),
                            headers={"Content-Type": "application/json", "X-Codeium-Csrf-Token": token},
                            method="POST"
                        )
                        with urllib.request.urlopen(req_msg, context=SSL_CTX, timeout=5) as mresp:
                            if mresp.status == 200:
                                print(f"📝 [KULLANICI TURU EKLENDİ] SendUserCascadeMessage başarılı!")
                    except Exception as e_msg:
                        print(f"⚠️ SendUserCascadeMessage notu: {e_msg}")

                    time.sleep(0.2)
                    # 2. SmartFocusConversation ile arayüzde o sekmeyi öne getir ve ekrana yansıt
                    try:
                        url_focus = f"https://127.0.0.1:{rpc_port}/exa.language_server_pb.LanguageServerService/SmartFocusConversation"
                        focus_payload = {"cascade_id": cid, "cascadeId": cid}
                        req_focus = urllib.request.Request(
                            url_focus,
                            data=json.dumps(focus_payload).encode('utf-8'),
                            headers={"Content-Type": "application/json", "X-Codeium-Csrf-Token": token},
                            method="POST"
                        )
                        with urllib.request.urlopen(req_focus, context=SSL_CTX, timeout=4) as fresp:
                            if fresp.status == 200:
                                print(f"🎯 [EKRANA YANSITILDI] SmartFocusConversation ile sekme arayüzde açıldı!")
                    except Exception as e_focus:
                        print(f"⚠️ SmartFocusConversation notu: {e_focus}")

                    return cid
    except Exception as e:
        print(f"❌ [CONNECT-RPC HATASI] StartCascade başarısız: {e}")

    return None

def send_message_to_existing_cascade(workspace_path, cascade_id, message_text):
    """
    Halihazırda var olan açık bir cascade oturumuna yeni mesaj ekler ve arayüzde odaklar.
    """
    rpc_port, token = get_workspace_rpc_env(workspace_path)
    if not rpc_port or not token:
        return False

    url_msg = f"https://127.0.0.1:{rpc_port}/exa.language_server_pb.LanguageServerService/SendUserCascadeMessage"
    msg_payload = {
        "cascade_id": cascade_id,
        "cascadeId": cascade_id,
        "items": [{"text": message_text}],
        "cascade_config": CASCADE_DEFAULT_CONFIG
    }
    req_msg = urllib.request.Request(
        url_msg,
        data=json.dumps(msg_payload).encode('utf-8'),
        headers={"Content-Type": "application/json", "X-Codeium-Csrf-Token": token},
        method="POST"
    )

    try:
        with urllib.request.urlopen(req_msg, context=SSL_CTX, timeout=5) as resp:
            if resp.status == 200:
                print(f"✅ [MESAJ İLETİLDİ] Var olan sohbet güncellendi: {cascade_id}")
                # Ekranda odakla
                try:
                    url_focus = f"https://127.0.0.1:{rpc_port}/exa.language_server_pb.LanguageServerService/SmartFocusConversation"
                    req_focus = urllib.request.Request(
                        url_focus,
                        data=json.dumps({"cascade_id": cascade_id, "cascadeId": cascade_id}).encode('utf-8'),
                        headers={"Content-Type": "application/json", "X-Codeium-Csrf-Token": token},
                        method="POST"
                    )
                    urllib.request.urlopen(req_focus, context=SSL_CTX, timeout=3)
                except Exception:
                    pass
                return True
    except Exception as e:
        print(f"⚠️ Var olan sohbete mesaj gönderilemedi: {e}")

    return False

def load_workspaces_map():
    mapping = {
        "01": ["c:/wamp64/www/taskmanager"],
        "TKMDEV-01": ["c:/wamp64/www/ufkayolculuk"],
        "TKMDEV-02": ["c:/wamp64/www/kitap-okuma", "c:/wamp64/www/ufkayolculuk-kitap-okuma"],
        "TKMDEV-03": ["c:/wamp64/www/benimcarsim"],
        "TKMDEV-04": ["c:/wamp64/www/benimcarsim-depo"],
        "NKLYCM": ["d:/MobilProjeler/nakliyecim"],
        "NETTAKIP": ["d:/MobilProjeler/sonuctakip", "d:/MobilProjeler/nettakip"],
        "NEWJOBS": ["c:/wamp64/www/mvcproje"]
    }
    if os.path.exists(WORKSPACES_CONFIG):
        try:
            with open(WORKSPACES_CONFIG, 'r', encoding='utf-8') as f:
                user_map = json.load(f)
                mapping.update(user_map)
        except Exception:
            pass
    return mapping

def get_project_primary_dir(project_code, workspace_path=""):
    if workspace_path and os.path.isdir(workspace_path):
        return workspace_path
    ws_map = load_workspaces_map()
    if project_code in ws_map:
        for p in ws_map[project_code]:
            if os.path.isdir(p):
                return p
    # Veritabanından workspace_path sorgula
    try:
        root_dir = os.path.dirname(BASE_DIR)
        php_code = (
            f"require_once 'Model/DataLayer.php'; "
            f"$db = new Database(); "
            f"$row = $db->row_query(\"SELECT workspace_path FROM projects WHERE project_code = '{project_code}' LIMIT 1\"); "
            f"if ($row && !empty($row->workspace_path)) echo trim($row->workspace_path);"
        )
        res = run_silent(['php', '-r', php_code], cwd=root_dir, capture_output=True, timeout=5)
        out = (res.stdout or '').strip()
        if out and os.path.isdir(out):
            return out
    except Exception:
        pass
    return os.path.dirname(BASE_DIR)

def get_active_context_window(normalized_targets):
    try:
        vscdb_path = os.path.expandvars(r'%APPDATA%\Antigravity IDE\User\globalStorage\state.vscdb')
        if not os.path.exists(vscdb_path):
            return None

        con = sqlite3.connect(f'file:{vscdb_path}?mode=ro', uri=True)
        cur = con.cursor()
        cur.execute("SELECT value FROM ItemTable WHERE key = 'erennyuksell.ag-multi-account-switchboard'")
        row = cur.fetchone()
        con.close()

        if not row or not row[0]:
            return None

        val = row[0].decode('utf-8', errors='ignore') if isinstance(row[0], bytes) else row[0]
        data = json.loads(val)
        last_win = data.get('ag.lastContextWindow', {})
        active_cid = last_win.get('conversationId')
        if not active_cid:
            return None

        conv_db = os.path.join(ANTIGRAVITY_CONV_DIR, f"{active_cid}.db")
        if not os.path.exists(conv_db):
            return None

        con_conv = sqlite3.connect(f'file:{conv_db}?mode=ro', uri=True)
        cur_conv = con_conv.cursor()
        cur_conv.execute("SELECT data FROM trajectory_metadata_blob WHERE id='main'")
        blob = cur_conv.fetchone()
        con_conv.close()

        if blob and blob[0]:
            decoded = urllib.parse.unquote(blob[0].decode('utf-8', errors='ignore')).replace('\\', '/').lower()
            for target in normalized_targets:
                pattern = rf"(?:^|[^\w\-\.]){re.escape(target)}(?:[/?#\"\'\s,\x00-\x1f\\]|$)"
                if re.search(pattern, decoded):
                    return active_cid
    except Exception:
        pass
    return None

def discover_latest_conversation(project_code, project_title="", workspace_path=""):
    workspaces_map = load_workspaces_map()
    target_paths = []
    if workspace_path:
        target_paths.append(workspace_path)
    if project_code in workspaces_map:
        target_paths.extend(workspaces_map[project_code])

    normalized_targets = list(dict.fromkeys([p.replace('\\', '/').lower().rstrip('/') for p in target_paths if p]))
    normalized_targets.sort(key=len, reverse=True)

    if not normalized_targets or not os.path.exists(ANTIGRAVITY_CONV_DIR):
        return None, None

    # 1. ÖNCELİK: Canlı Kullanıcı Odağı (Active Context Window)
    active_cid = get_active_context_window(normalized_targets)
    if active_cid:
        return active_cid, f"file:///{normalized_targets[0]}"

    # 2. İKİNCİ AŞAMA: Bu projeye ait son 4 saat içinde işlem görmüş geçerli bir .db oturumu
    db_files = glob.glob(os.path.join(ANTIGRAVITY_CONV_DIR, '*.db'))
    db_files.sort(key=os.path.getmtime, reverse=True)

    now = time.time()
    for db_path in db_files:
        if (now - os.path.getmtime(db_path)) > 14400:
            continue

        cid = os.path.splitext(os.path.basename(db_path))[0]
        try:
            con = sqlite3.connect(f'file:{db_path}?mode=ro', uri=True)
            cur = con.cursor()
            cur.execute("SELECT data FROM trajectory_metadata_blob WHERE id='main'")
            row = cur.fetchone()
            con.close()
            if row and row[0]:
                decoded_text = urllib.parse.unquote(row[0].decode('utf-8', errors='ignore')).replace('\\', '/').lower()
                for target in normalized_targets:
                    pattern = rf"(?:^|[^\w\-\.]){re.escape(target)}(?:[/?#\"\'\s,\x00-\x1f\\]|$)"
                    if re.search(pattern, decoded_text):
                        return cid, f"file:///{target}"
        except Exception:
            continue

    return None, None

def sync_conversation_id_to_db(project_code, conv_id):
    try:
        root_dir = os.path.dirname(BASE_DIR)
        php_code = (
            f"require_once 'Model/DataLayer.php'; "
            f"$db = new Database(); "
            f"$stmt = $db->connect->prepare('UPDATE projects SET agent_conversation_id = :cid WHERE project_code = :pcode'); "
            f"$stmt->execute([':cid' => '{conv_id}', ':pcode' => '{project_code}']);"
        )
        run_silent(['php', '-r', php_code], cwd=root_dir, capture_output=True, timeout=5)
    except Exception:
        pass

def process_webhook_async(payload, header_conv_id):
    event = payload.get('event', 'unknown')
    project_id = payload.get('project_id')
    project_code = str(payload.get('project_code', ''))
    project_title = payload.get('project_title', '')
    incoming_conv_id = payload.get('agent_conversation_id') or header_conv_id
    data = payload.get('data', {})
    if not isinstance(data, dict):
        data = {}

    task_code = str(data.get('task_code', ''))
    task_id = data.get('task_id')
    api_base_url = (payload.get('api_base_url') or 'http://localhost/taskmanager').rstrip('/')
    api_token = str(payload.get('api_token') or '').strip()

    if event == 'task.submitted':
        with state_lock:
            project_active_state.pop(project_code, None)
        print(f"✅ [GÖREV TESLİM EDİLDİ] [{project_code}] {task_code} -> Ajanın yanıtını bitirmesi için 4 sn bekleniyor...")
        time.sleep(4)
        if project_id:
            app_root = os.path.dirname(BASE_DIR)
            fin_id = int(task_id) if task_id else 'null'
            next_cmd = ['php', '-r', f"require 'System/System.php'; Helper::dispatchNextAiTaskIfAvailable({int(project_id)}, {fin_id});"]
            run_silent(next_cmd, capture_output=True, text=True, cwd=app_root, timeout=10)
        return

    if event in IGNORED_AGENT_EVENTS:
        return

    workspace_path = payload.get('workspace_path') or data.get('workspace_path', '')
    agent_rules = (payload.get('agent_rules') or data.get('agent_rules') or '').strip()
    project_dir = get_project_primary_dir(project_code, workspace_path)

    # 1. HEDEF SOHBET TESPİTİ (GHOST SİLME DÖNGÜSÜ ENGELLENDİ)
    target_conv_id = None
    if incoming_conv_id:
        db_check = os.path.join(ANTIGRAVITY_CONV_DIR, f"{incoming_conv_id}.db")
        if os.path.exists(db_check):
            # Veritabanında kayıtlı olan sohbet diskte mevcut, ASLA silme!
            target_conv_id = incoming_conv_id

    if not target_conv_id:
        discovered_cid, _ = discover_latest_conversation(project_code, project_title, workspace_path)
        if discovered_cid:
            target_conv_id = discovered_cid

    # Sadece ve sadece incoming_conv_id diskte gerçekten YOKSA temizle
    if incoming_conv_id and not target_conv_id:
        print(f"🧹 [ESKİ/HAYALET SOHBET TEMİZLENDİ] {incoming_conv_id} diskte bulunamadı, yeni pencerede sohbet açılacak.")
        sync_conversation_id_to_db(project_code, "")

    is_resumed = bool(data.get('resumed', False))

    with state_lock:
        prev_state = project_active_state.get(project_code)
        if prev_state and prev_state.get('task_code') == task_code and event in ('task.started', 'task.in_progress'):
            prev_cid = prev_state.get('conv_id')
            elapsed = time.time() - prev_state.get('dispatched_at', 0)

            if prev_cid and prev_cid == target_conv_id and elapsed < 180:
                return
            elif prev_cid and target_conv_id and prev_cid != target_conv_id:
                print(f"🔄 [HESAP/SOHBET DEĞİŞİMİ] {task_code} -> Yeni Pencere: {target_conv_id}")
                is_resumed = True
            elif elapsed >= 180:
                print(f"⏰ [HATIRLATMA] {task_code} görevi {int(elapsed)} saniyedir teslim edilmedi, tekrar hatırlatılıyor...")
                is_resumed = True

    print(f"\n=======================================================")
    print(f"🔔 [GÖREV TETİKLENİYOR] -> Olay: {event} | Proje: [{project_code}] {project_title} | Görev: {task_code}")

    task_title = data.get('title', '').replace('🔄 [RESUMING TASK] ', '').replace('⚠️ [REVISION REQUEST] ', '')
    priority = data.get('priority', 'normal')

    rules_block = ""
    if agent_rules:
        rules_block = f"\n\n---\n📌 **PROJECT RULES ([{project_code}] {project_title}):**\n{agent_rules}\n"

    if event in ('task.started', 'task.in_progress', 'task.revision_requested', 'task.modification_requested'):
        is_rev = event in ('task.revision_requested', 'task.modification_requested')
        if is_rev:
            title_header = f"⚠️ [REVISION] {task_code}: {task_title}"
            banner = f"⚠️ **[{project_code}] REVISION TASK MOVED TO IN PROGRESS!**\n🔴 **Requested Changes:** {data.get('revision_reason', 'Revision requested.')}\n\n"
        elif is_resumed:
            title_header = f"🔄 [RESUMED] {task_code}: {task_title}"
            banner = f"🔄 **[SESSION HANDOFF / RESUME DIRECTIVE]**\nThis task is currently IN PROGRESS and awaiting completion.\n\n"
        else:
            title_header = f"🚀 [IN PROGRESS] {task_code}: {task_title}"
            banner = f"🚀 **TaskManager Task Marked as 'In Progress'!**\n\n"

        desc = data.get('description', '').strip() or '*(No description provided)*'
        desc = re.sub(r'---\s*⚡\s*\*\*AUTONOMOUS COMPLETION INSTRUCTION:.*$', '', desc, flags=re.DOTALL).strip()

        checklists = data.get('checklists', [])
        checklist_md = ""
        if checklists:
            checklist_md = "\n\n### ✅ Checklist:\n"
            for item in checklists:
                box = "[x]" if item.get('is_completed') else "[ ]"
                text = item.get('title') or item.get('item_text', '')
                checklist_md += f"- {box} {text}\n"

        message_text = (
            f"{banner}"
            f"📌 **Project:** [{project_code}] {project_title} (`{project_dir}`)\n"
            f"📋 **Task Code:** `{task_code}`\n"
            f"🏷️ **Title:** {task_title}\n"
            f"⚡ **Priority:** {priority}\n\n"
            f"### 📝 Task Description & Requirements:\n{desc}"
            f"{checklist_md}"
            f"{rules_block}\n\n"
            f"---\n"
            f"🛑 **STRICT OPERATIONAL GUARDRAILS:**\n"
            f"1. **SINGLE ACTIVE TASK FOCUS:** Work EXCLUSIVELY on `{task_code}` in project `[{project_code}]`. NEVER jump to older or other tasks!\n"
            f"2. **ZERO ABANDONMENT & AUTONOMOUS QA:** Complete 100% of implementation and live automated verification before responding to the user. The user is NOT a QA tester.\n"
            f"3. **SUBMIT AND STOP (USE OFFICIAL REST API - NEVER SEARCH FOR TASKMANAGER FILES OR DB):** When completed and verified, execute the following REST API request in your terminal to move `{task_code}` to `submitted` (REVIEW), then **END YOUR TURN** (The next queued task will be automatically dispatched in 4 seconds):\n\n"
            f"```powershell\n"
            f"$headers = @{{ 'Authorization' = 'Bearer {api_token}'; 'Content-Type' = 'application/json; charset=utf-8' }}\n"
            f"$body = @{{ notes = '1. What Was Done (Neler Yapıldı): ... 2. Where to Test (Nereden Test Edilir): ... 3. How to Test (Nasıl Test Edilir): ... 4. Test Evidence (Test Kanıtı): ...' }} | ConvertTo-Json -Compress\n"
            f"Invoke-RestMethod -Uri '{api_base_url}/api/tasks/{task_code}/workflow/submit' -Method Post -Headers $headers -Body ([System.Text.Encoding]::UTF8.GetBytes($body))\n"
            f"```"
        )
    elif event == 'project.webhook_test':
        title_header = f"Webhook Test Ping: [{project_code}]"
        message_text = f"✅ **TaskManager Webhook Test Ping Successful!**\n- **Project:** [{project_code}] {project_title}\n- **Target Conversation:** {target_conv_id or 'New Conversation'}"
    else:
        return

    # 2. GÖNDERİM AKIŞI:
    success = False

    # A. Hedef sohbet varsa önce ona iletmeyi dene
    if target_conv_id:
        success = send_message_to_existing_cascade(project_dir, target_conv_id, message_text)
        if not success:
            print(f"⚠️ [MEVCUT SOHBETE İLETİLEMEDİ] ID: {target_conv_id} yanıt vermedi, yeni Connect-RPC oturumu açılıyor...")

    # B. Hedef sohbet yoksa veya eskisine iletilemediyse: Connect-RPC StartCascade ile YENİ AÇ!
    if not success:
        new_cid = start_cascade_conversation(project_dir, message_text, title=title_header)
        if new_cid:
            target_conv_id = new_cid
            sync_conversation_id_to_db(project_code, new_cid)
            success = True

    if success and target_conv_id:
        print(f"✅ Gönderim Başarılı! Hedef Sohbet: {target_conv_id}")
        if task_code:
            with state_lock:
                project_active_state[project_code] = {
                    'task_code': task_code,
                    'conv_id': target_conv_id,
                    'dispatched_at': time.time()
                }
    else:
        print(f"❌ [HATA] [{project_code}] için görev iletilemedi!")


class WebhookHandler(http.server.BaseHTTPRequestHandler):
    def do_POST(self):
        if self.path != '/webhook':
            self.send_response(404)
            self.end_headers()
            return

        content_length = int(self.headers.get('Content-Length', 0))
        post_data = self.rfile.read(content_length)

        try:
            payload = json.loads(post_data.decode('utf-8'))
        except Exception as e:
            self.send_response(400)
            self.end_headers()
            self.wfile.write(json.dumps({"error": str(e)}).encode())
            return

        header_conv_id = self.headers.get('X-TaskManager-Conversation-ID')

        self.send_response(200)
        self.send_header('Content-Type', 'application/json')
        self.end_headers()
        self.wfile.write(json.dumps({"status": "queued"}).encode())

        t = threading.Thread(target=process_webhook_async, args=(payload, header_conv_id), daemon=True)
        t.start()

    def log_message(self, format, *args):
        return

def run_server():
    if '--probe' in sys.argv:
        ws = None
        for i, a in enumerate(sys.argv):
            if a == '--probe' and i + 1 < len(sys.argv) and not sys.argv[i+1].startswith('-'):
                ws = sys.argv[i+1]
        port, tok = get_workspace_rpc_env(ws)
        addr = f"127.0.0.1:{port}" if port else ""
        print(json.dumps({"address": addr, "csrf_token": tok or ""}))
        return

    watchdog_thread = threading.Thread(target=watchdog_loop, daemon=True)
    watchdog_thread.start()

    server_address = ('', PORT)
    httpd = http.server.HTTPServer(server_address, WebhookHandler)
    print("=======================================================")
    print("🤖 Antigravity Multi-Agent Dispatcher v6.0 (Connect-RPC Multi-Window) Başlatıldı!")
    print(f"📡 Dinleme Adresi : http://localhost:{PORT}/webhook")
    print("=======================================================")
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        print("\nServis durduruldu.")

if __name__ == '__main__':
    run_server()