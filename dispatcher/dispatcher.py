"""
TaskManager -> Antigravity Central Multi-Agent Dispatcher (v4.2 - Sıfır Pencere / No-Window Modu)
Hiçbir CMD/PowerShell penceresi açmadan arka planda %100 sessiz çalışır.
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
import threading

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
LOG_FILE = os.path.join(BASE_DIR, 'dispatcher.log')
WORKSPACES_CONFIG = os.path.join(BASE_DIR, 'workspaces.json')
LS_ENV_FILE = os.path.join(BASE_DIR, 'ls_env.json')
LS_ADDRESS_FILE = os.path.join(BASE_DIR, 'ls_address.txt')
CSRF_TOKEN_FILE = os.path.join(BASE_DIR, 'csrf_token.txt')

# WINDOWS'TA SİYAH CMD EKRANI ÇIKMASINI VE 0xc0000142 HATASINI ENGELLEYEN BAYRAK:
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
ANTIGRAVITY_CONV_DIR = r"C:\Users\DELL\.gemini\antigravity-ide\conversations"

cached_ls_address = None
cached_csrf_token = None

def _save_ls_env(addr, token):
    global cached_ls_address, cached_csrf_token
    cached_ls_address = addr
    cached_csrf_token = token
    try:
        with open(LS_ENV_FILE, 'w', encoding='utf-8') as f:
            json.dump({"address": addr, "csrf_token": token or "", "updated_at": int(time.time())}, f, indent=2)
        with open(LS_ADDRESS_FILE, 'w', encoding='utf-8') as f:
            f.write(addr)
        if token:
            with open(CSRF_TOKEN_FILE, 'w', encoding='utf-8') as f:
                f.write(token)
    except Exception:
        pass

def verify_ls_env(addr, token=None):
    if not addr or not os.path.exists(AGENTAPI_EXE):
        return False
    test_env = os.environ.copy()
    test_env["ANTIGRAVITY_LS_ADDRESS"] = addr
    if token:
        test_env["ANTIGRAVITY_CSRF_TOKEN"] = token
    try:
        res = run_silent(
            [AGENTAPI_EXE, "agentapi", "get-conversation-metadata", "__probe__"],
            env=test_env, capture_output=True, text=True, timeout=2
        )
        out = ((res.stdout or '') + " " + (res.stderr or '')).lower()
        if res.returncode == 0 or "trajectory not found" in out or "conversationmetadata" in out:
            if "unavailable" not in out and "connection error" not in out and "invalid csrf" not in out:
                return True
    except Exception:
        pass
    return False

def get_antigravity_ls_env(force_refresh=False):
    global cached_ls_address, cached_csrf_token

    if not force_refresh and cached_ls_address and verify_ls_env(cached_ls_address, cached_csrf_token):
        return cached_ls_address, cached_csrf_token

    if not force_refresh and os.path.exists(LS_ENV_FILE):
        try:
            with open(LS_ENV_FILE, 'r', encoding='utf-8') as f:
                f_data = json.load(f)
                if f_data.get('address') and verify_ls_env(f_data.get('address'), f_data.get('csrf_token')):
                    cached_ls_address = f_data.get('address')
                    cached_csrf_token = f_data.get('csrf_token')
                    return cached_ls_address, cached_csrf_token
        except Exception:
            pass

    try:
        ps_cmd = 'Get-CimInstance Win32_Process -Filter "name = \'language_server_windows_x64.exe\'" | Select-Object ProcessId, CommandLine | ConvertTo-Json'
        ps_res = run_silent(['powershell', '-NoProfile', '-WindowStyle', 'Hidden', '-Command', ps_cmd], capture_output=True, text=True, timeout=5)
        if ps_res.returncode == 0 and ps_res.stdout.strip():
            procs = json.loads(ps_res.stdout)
            if isinstance(procs, dict):
                procs = [procs]

            netstat_res = run_silent(['netstat', '-ano'], capture_output=True, text=True, timeout=5)
            netstat = netstat_res.stdout or ''
            for proc in procs:
                pid = str(proc.get('ProcessId', ''))
                cmd = proc.get('CommandLine') or ''
                token_m = re.search(r'--csrf_token\s+([a-zA-Z0-9\-]+)', cmd)
                token = token_m.group(1) if token_m else None

                candidate_ports = []
                for line in netstat.splitlines():
                    if 'LISTENING' in line and line.strip().endswith(pid):
                        m = re.search(r'127\.0\.0\.1:(\d+)', line)
                        if m and m.group(1) not in candidate_ports:
                            candidate_ports.append(m.group(1))

                for port in candidate_ports:
                    addr = f"127.0.0.1:{port}"
                    if verify_ls_env(addr, token):
                        _save_ls_env(addr, token)
                        return addr, token
    except Exception as e:
        print(f"⚠️ LS Port tespiti hatası: {e}")

    return None, None

def is_conversation_active(cid, ls_address, csrf_token=None):
    if not cid or not ls_address or not os.path.exists(AGENTAPI_EXE):
        return False
    env = os.environ.copy()
    env["ANTIGRAVITY_LS_ADDRESS"] = ls_address
    if csrf_token:
        env["ANTIGRAVITY_CSRF_TOKEN"] = csrf_token
    try:
        res = run_silent(
            [AGENTAPI_EXE, "agentapi", "get-conversation-metadata", cid],
            env=env, capture_output=True, text=True, timeout=3
        )
        return (res.returncode == 0)
    except Exception:
        return False

def load_workspaces_map():
    mapping = {
        "01": ["c:/wamp64/www/taskmanager"],
        "TKMDEV-01": ["c:/wamp64/www/ufkayolculuk"],
        "TKMDEV-02": ["c:/wamp64/www/kitap-okuma", "c:/wamp64/www/ufkayolculuk-kitap-okuma"],
        "TKMDEV-03": ["c:/wamp64/www/benimcarsim"],
        "TKMDEV-04": ["c:/wamp64/www/benimcarsim-depo"],
        "NKLYCM": ["d:/MobilProjeler/nakliyecim"],
        "NETTAKIP": ["d:/MobilProjeler/sonuctakip", "d:/MobilProjeler/nettakip"]
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
    return os.path.dirname(BASE_DIR)

def discover_latest_conversation(project_code, project_title="", workspace_path=""):
    ls_address, csrf_token = get_antigravity_ls_env()
    workspaces_map = load_workspaces_map()
    target_paths = []
    if workspace_path:
        target_paths.append(workspace_path)
    if project_code in workspaces_map:
        target_paths.extend(workspaces_map[project_code])

    normalized_targets = list(dict.fromkeys([p.replace('\\', '/').lower().rstrip('/') for p in target_paths if p]))
    normalized_targets.sort(key=len, reverse=True)

    if not os.path.exists(ANTIGRAVITY_CONV_DIR):
        return None, None

    db_files = glob.glob(os.path.join(ANTIGRAVITY_CONV_DIR, '*.db'))
    db_files.sort(key=os.path.getmtime, reverse=True)

    for db_path in db_files:
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
                        if ls_address and not is_conversation_active(cid, ls_address, csrf_token):
                            continue
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
            f"$stmt =$db->connect->prepare('UPDATE projects SET agent_conversation_id = :cid WHERE project_code = :pcode'); "
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
    is_watchdog_check = bool(data.get('watchdog_check', False))

    # 1. GÖREV TESLİM EDİLDİĞİNDE: Kilidi temizle, 4 saniye bekle (ajan cevabını tamamlasın), sonra sıradaki görevi başlat!
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

    ls_address, csrf_token = get_antigravity_ls_env()
    discovered_cid, matched_uri = discover_latest_conversation(project_code, project_title, workspace_path)

    if incoming_conv_id and ls_address and not is_conversation_active(incoming_conv_id, ls_address, csrf_token):
        incoming_conv_id = None

    target_conv_id = discovered_cid or incoming_conv_id
    is_resumed = bool(data.get('resumed', False))

    # 2. ANTI-SPAM & HESAP/SOHBET DEĞİŞİM KONTROLÜ
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

    if discovered_cid:
        sync_conversation_id_to_db(project_code, discovered_cid)

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
            f"3. **SUBMIT AND STOP:** When completed and verified, execute the following command in your terminal to move the task to `submitted` (REVIEW) and summarize your delivery notes in the task's language, then **END YOUR TURN** (The next queued task will be automatically dispatched in 4 seconds):\n\n"
            f"```powershell\n"
            f"php -r \"require 'c:/wamp64/www/taskmanager/System/System.php'; Helper::submitTaskDirectly('{task_code}', '1. What Was Done (Neler Yapıldı): ... 2. Where to Test (Nereden Test Edilir): ... 3. How to Test (Nasıl Test Edilir): ... 4. Test Evidence (Test Kanıtı): ...');\"\n"
            f"```"
        )
    elif event == 'project.webhook_test':
        title_header = f"Webhook Test Ping: [{project_code}]"
        message_text = f"✅ **TaskManager Webhook Test Ping Successful!**\n- **Project:** [{project_code}] {project_title}\n- **Target Conversation:** {target_conv_id or 'New Conversation'}"
    else:
        return

    env = os.environ.copy()
    if ls_address:
        env["ANTIGRAVITY_LS_ADDRESS"] = ls_address
    if csrf_token:
        env["ANTIGRAVITY_CSRF_TOKEN"] = csrf_token

    if target_conv_id:
        cmd = [AGENTAPI_EXE, "agentapi", "send-message", f"--title={title_header}", target_conv_id, message_text]
    else:
        cmd = [AGENTAPI_EXE, "agentapi", "new-conversation", f"--title={title_header}", message_text]

    try:
        result = run_silent(cmd, env=env, cwd=project_dir, capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=12)

        if result.returncode != 0:
            fresh_addr, fresh_token = get_antigravity_ls_env(force_refresh=True)
            if fresh_addr:
                env["ANTIGRAVITY_LS_ADDRESS"] = fresh_addr
                if fresh_token:
                    env["ANTIGRAVITY_CSRF_TOKEN"] = fresh_token
                result = run_silent(cmd, env=env, cwd=project_dir, capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=12)

        combined_err = ((result.stdout or '') + ' ' + (result.stderr or '')).lower()
        if result.returncode != 0 and ('trajectory not found' in combined_err or not target_conv_id):
            fallback_cmd = [AGENTAPI_EXE, "agentapi", "new-conversation", f"--title={title_header}", message_text]
            result = run_silent(fallback_cmd, env=env, cwd=project_dir, capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=12)

        if result.returncode == 0 and result.stdout:
            try:
                out_json = json.loads(result.stdout)
                new_cid = out_json.get('response', {}).get('newConversation', {}).get('conversationId')
                if new_cid:
                    target_conv_id = new_cid
                    sync_conversation_id_to_db(project_code, new_cid)
            except Exception:
                pass

        print(f"✅ Gönderim Sonucu ({target_conv_id}): ExitCode {result.returncode}")
        if result.returncode == 0 and task_code:
            with state_lock:
                project_active_state[project_code] = {
                    'task_code': task_code,
                    'conv_id': target_conv_id,
                    'dispatched_at': time.time()
                }
    except Exception as err:
        print(f"❌ Gönderim Hatası: {str(err)}")


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
        addr, tok = get_antigravity_ls_env(force_refresh=True)
        print(json.dumps({"address": addr, "csrf_token": tok or ""}))
        return

    watchdog_thread = threading.Thread(target=watchdog_loop, daemon=True)
    watchdog_thread.start()

    server_address = ('', PORT)
    httpd = http.server.HTTPServer(server_address, WebhookHandler)
    print("=======================================================")
    print("🤖 Antigravity Multi-Agent Dispatcher v4.2 (Sessiz Mod) Başlatıldı!")
    print(f"📡 Dinleme Adresi : http://localhost:{PORT}/webhook")
    print("=======================================================")
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        print("\nServis durduruldu.")

if __name__ == '__main__':
    run_server()