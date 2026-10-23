r"""
TaskManager -> Antigravity Central Multi-Agent Dispatcher (v4.5 - 3 Ajanlı Rol Algılama: Coder / QA / Danışman)
Hiçbir CMD/PowerShell penceresi açmadan arka planda %100 sessiz çalışır.
Proxy veya yabancı eklenti içermez.
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
APP_ROOT = os.path.abspath(os.path.join(BASE_DIR, '..', '..', '..'))
if not os.path.exists(os.path.join(APP_ROOT, 'Core', 'System.php')):
    possible_root = os.path.dirname(BASE_DIR)
    if os.path.exists(os.path.join(possible_root, 'Core', 'System.php')):
        APP_ROOT = possible_root

PORT = 4000
LOG_FILE = os.path.join(BASE_DIR, 'dispatcher.log')
WORKSPACES_CONFIG = os.path.join(BASE_DIR, 'workspaces.json')
LS_ENV_FILE = os.path.join(BASE_DIR, 'ls_env.json')
LS_ADDRESS_FILE = os.path.join(BASE_DIR, 'ls_address.txt')
CSRF_TOKEN_FILE = os.path.join(BASE_DIR, 'csrf_token.txt')

USER_HOME = os.path.expanduser('~')
LOCAL_APP_DATA = os.environ.get('LOCALAPPDATA', os.path.join(USER_HOME, 'AppData', 'Local'))

AGENTAPI_EXE = os.path.join(
    LOCAL_APP_DATA,
    'Programs',
    'Antigravity IDE',
    'resources',
    'app',
    'extensions',
    'antigravity',
    'bin',
    'language_server_windows_x64.exe'
)
ANTIGRAVITY_CONV_DIR = os.path.join(USER_HOME, '.gemini', 'antigravity-ide', 'conversations')
ANTIGRAVITY_BRAIN_DIR = os.path.join(USER_HOME, '.gemini', 'antigravity-ide', 'brain')

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
    if sys.platform == 'win32':
        kwargs['creationflags'] = kwargs.get('creationflags', 0) | NO_WINDOW_FLAG
    return subprocess.run(cmd, **kwargs)

def watchdog_loop():
    print("🛡️ [WATCHDOG] Otonom Kurtarma ve Kuyruk Denetim Motoru Aktif (Sessiz Mod)!")
    time.sleep(10)
    app_root = APP_ROOT

    while True:
        try:
            insp_cmd = ['php', '-r', "require 'Core/System.php'; \\Modules\\Watchdog\\Services\\WatchdogService::runInspection();"]
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

def get_all_ls_instances():
    instances = []
    try:
        ps_cmd = 'Get-CimInstance Win32_Process -Filter "name = \'language_server_windows_x64.exe\'" | Select-Object ProcessId, CommandLine | ConvertTo-Json'
        ps_res = run_silent(['powershell', '-NoProfile', '-WindowStyle', 'Hidden', '-Command', ps_cmd], capture_output=True, text=True, timeout=10)
        if ps_res.returncode == 0 and ps_res.stdout.strip():
            procs = json.loads(ps_res.stdout)
            if isinstance(procs, dict):
                procs = [procs]

            netstat_res = run_silent(['netstat', '-ano'], capture_output=True, text=True, timeout=8)
            netstat = netstat_res.stdout or ''
            for proc in procs:
                pid = str(proc.get('ProcessId', ''))
                cmd = proc.get('CommandLine') or ''
                token_m = re.search(r'--csrf_token\s+([a-zA-Z0-9\-]+)', cmd)
                token = token_m.group(1) if token_m else None

                candidate_ports = []
                for line in netstat.splitlines():
                    if 'LISTENING' in line and line.strip().endswith(pid):
                        m = re.search(r':(\d+)\s+', line)
                        if m and m.group(1) not in candidate_ports:
                            candidate_ports.append(m.group(1))

                instances.append({
                    'pid': pid,
                    'cmd': cmd,
                    'token': token,
                    'ports': candidate_ports
                })
    except Exception as e:
        print(f"⚠️ LS Port tespiti hatası: {e}")
    return instances

def find_active_ls_for_conversation(cid):
    if not cid:
        return None, None
    instances = get_all_ls_instances()
    for inst in instances:
        token = inst['token']
        for port in inst['ports']:
            addr = f"127.0.0.1:{port}"
            if is_conversation_active(cid, addr, token):
                return addr, token
    return None, None

def get_antigravity_ls_env(force_refresh=False):
    global cached_ls_address, cached_csrf_token

    if not force_refresh and cached_ls_address and verify_ls_env(cached_ls_address, cached_csrf_token):
        return cached_ls_address, cached_csrf_token

    instances = get_all_ls_instances()
    for inst in instances:
        token = inst['token']
        for port in inst['ports']:
            addr = f"127.0.0.1:{port}"
            if verify_ls_env(addr, token):
                _save_ls_env(addr, token)
                return addr, token

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

_ws_cache = {"data": {}, "expires_at": 0}

def load_workspaces_map():
    now = time.time()
    if _ws_cache["data"] and now < _ws_cache["expires_at"]:
        return _ws_cache["data"]

    mapping = {}
    try:
        app_root = APP_ROOT
        php_code = 'require "Core/System.php"; $db = new Database(); $rows = $db->connect->query("SELECT project_code, workspace_path FROM projects WHERE workspace_path IS NOT NULL AND workspace_path != \'\'")->fetchAll(PDO::FETCH_KEY_PAIR); echo json_encode($rows);'
        res = run_silent(['php', '-r', php_code], cwd=app_root, capture_output=True, text=True, timeout=5)
        if res.returncode == 0 and res.stdout.strip():
            db_projects = json.loads(res.stdout.strip())
            for pcode, ppath in db_projects.items():
                if ppath:
                    mapping[str(pcode)] = [str(ppath)]
    except Exception:
        pass

    if os.path.exists(WORKSPACES_CONFIG):
        try:
            with open(WORKSPACES_CONFIG, 'r', encoding='utf-8') as f:
                user_map = json.load(f)
                if isinstance(user_map, dict):
                    for k, v in user_map.items():
                        k_str = str(k)
                        if isinstance(v, list):
                            if k_str in mapping:
                                mapping[k_str] = list(dict.fromkeys(mapping[k_str] + v))
                            else:
                                mapping[k_str] = v
                        elif isinstance(v, str):
                            if k_str in mapping:
                                if v not in mapping[k_str]:
                                    mapping[k_str].append(v)
                            else:
                                mapping[k_str] = [v]
        except Exception:
            pass

    _ws_cache["data"] = mapping
    _ws_cache["expires_at"] = now + 15
    return mapping

def get_project_primary_dir(project_code, workspace_path=""):
    if workspace_path and os.path.isdir(workspace_path):
        return workspace_path
    ws_map = load_workspaces_map()
    if project_code in ws_map:
        for p in ws_map[project_code]:
            if os.path.isdir(p):
                return p
    return APP_ROOT

def get_conversation_role(cid):
    """
    Sohbetin ilk kullanıcı mesajını inceleyerek rolünü tespit eder:
    - 'danışman' / 'danisman' / 'advisor' -> 'advisor' (KESİNLİKLE GÖREV ATILMAZ!)
    - 'coder' / 'koder' / 'developer' -> 'dev'
    - 'qa' / 'tester' / 'test uzmanı' -> 'qa'
    """
    if not cid:
        return None
    cid = str(cid).strip()
    transcript_file = os.path.join(ANTIGRAVITY_BRAIN_DIR, cid, '.system_generated', 'logs', 'transcript.jsonl')
    if not os.path.exists(transcript_file):
        return None

    try:
        with open(transcript_file, 'r', encoding='utf-8', errors='replace') as f:
            for _ in range(20):
                line = f.readline()
                if not line:
                    break
                if '"source":"USER_EXPLICIT"' in line:
                    data = json.loads(line)
                    raw_content = str(data.get('content', '')).lower()
                    clean_content = raw_content.split('<additional_metadata>')[0].replace('<user_request>', '').replace('</user_request>', '').strip()

                    if 'mandatory task interrupt' in clean_content or 'mandatory qa verification' in clean_content:
                        continue

                    if re.search(r'\b(danışman|danisman|danışmanısın|danismanisin|advisor|consultant)\b', clean_content):
                        return 'advisor'
                    if re.search(r'\b(coder|coderisin|koder|developer|geliştirici|gelistirici)\b', clean_content):
                        return 'dev'
                    if re.search(r'\b(qa|tester|test uzmanı|test uzmani|test uzmanısın|test uzmanisin)\b', clean_content):
                        return 'qa'
    except Exception:
        pass
    return None

def discover_latest_conversation(project_code, project_title="", workspace_path="", target_role="dev"):
    workspaces_map = load_workspaces_map()
    target_paths = []
    if workspace_path:
        target_paths.append(workspace_path)
    if project_code in workspaces_map:
        target_paths.extend(workspaces_map[project_code])

    normalized_targets = list(dict.fromkeys([p.replace('\\', '/').lower().rstrip('/') for p in target_paths if p]))
    normalized_targets.sort(key=len, reverse=True)

    if not os.path.exists(ANTIGRAVITY_CONV_DIR):
        return None, None, None, None

    db_files = glob.glob(os.path.join(ANTIGRAVITY_CONV_DIR, '*.db'))
    db_files.sort(key=os.path.getmtime, reverse=True)

    matched_candidates = []

    for db_path in db_files[:40]:
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
                        role = get_conversation_role(cid)
                        if role == 'advisor':
                            continue
                        addr, tok = find_active_ls_for_conversation(cid)
                        matched_candidates.append({
                            'cid': cid,
                            'uri': f"file:///{target}",
                            'addr': addr,
                            'tok': tok,
                            'role': role,
                            'is_active_ls': bool(addr)
                        })
                        break
        except Exception:
            continue

    if not matched_candidates:
        return None, None, None, None

    for cand in matched_candidates:
        if cand['role'] == target_role and cand['is_active_ls']:
            print(f"🎯 [AKTİF ROL EŞLEŞMESİ] Proje: {project_code} | Rol: {target_role.upper()} -> CID: {cand['cid']}")
            return cand['cid'], cand['uri'], cand['addr'], cand['tok']

    for cand in matched_candidates:
        if cand['role'] == target_role:
            print(f"🎯 [ROL EŞLEŞMESİ] Proje: {project_code} | Rol: {target_role.upper()} -> CID: {cand['cid']}")
            return cand['cid'], cand['uri'], cand['addr'], cand['tok']

    print(f"⚠️ [ROL BULUNAMADI] Proje [{project_code}] için ilk mesajında '{target_role}' yazan açık bir pencere bulunamadı!")
    return None, None, None, None

def sync_conversation_id_to_db(project_code, conv_id, role='dev'):
    try:
        conv_id = str(conv_id).strip()
        project_code = str(project_code).strip()
        root_dir = APP_ROOT
        if role == 'qa':
            update_sql = "UPDATE projects SET qa_conversation_id = :cid WHERE project_code = :pcode"
            exec_params = f"[':cid' => '{conv_id}', ':pcode' => '{project_code}']"
        else:
            update_sql = "UPDATE projects SET dev_conversation_id = :cid1, agent_conversation_id = :cid2 WHERE project_code = :pcode"
            exec_params = f"[':cid1' => '{conv_id}', ':cid2' => '{conv_id}', ':pcode' => '{project_code}']"

        php_code = (
            f"require_once 'Core/System.php'; "
            f"$db = new Database(); "
            f"$stmt = $db->connect->prepare('{update_sql}'); "
            f"$stmt->execute({exec_params});"
        )
        run_silent(['php', '-r', php_code], cwd=root_dir, capture_output=True, timeout=5)
    except Exception:
        pass

def process_webhook_async(payload, header_conv_id):
    event = payload.get('event', 'unknown')
    project_id = payload.get('project_id')
    project_code = str(payload.get('project_code', '')).strip()
    project_title = payload.get('project_title', '')

    dev_conv_id = payload.get('dev_conversation_id') or payload.get('agent_conversation_id') or header_conv_id
    dev_conv_id = str(dev_conv_id).strip() if dev_conv_id else None
    qa_conv_id = payload.get('qa_conversation_id')
    qa_conv_id = str(qa_conv_id).strip() if qa_conv_id else None

    qa_test_instructions = (payload.get('qa_test_instructions') or '').strip()
    qa_global_instructions = (payload.get('qa_global_instructions') or '').strip()

    data = payload.get('data', {})
    if not isinstance(data, dict):
        data = {}

    task_code = str(data.get('task_code', '')).strip()
    task_title = str(data.get('title', '')).strip()
    task_id = data.get('task_id')
    api_base_url = (payload.get('api_base_url') or 'http://localhost/taskmanager').rstrip('/')
    api_token = str(payload.get('api_token') or '').strip()
    if not api_token:
        try:
            token_php = "require_once 'Core/System.php'; $cfg = \\Modules\\RestApi\\Services\\RestApiService::getDynamicApiConfig(); echo $cfg['api_token'] ?? '';"
            tok_res = run_silent(['php', '-r', token_php], cwd=APP_ROOT, capture_output=True, text=True, timeout=3)
            if tok_res.returncode == 0 and tok_res.stdout.strip():
                api_token = tok_res.stdout.strip()
        except Exception:
            pass

    workspace_path = payload.get('workspace_path') or data.get('workspace_path', '')
    agent_rules = (payload.get('agent_rules') or data.get('agent_rules') or '').strip()
    project_dir = get_project_primary_dir(project_code, workspace_path)

    if event == 'task.requeued':
        with state_lock:
            prev_info = project_active_state.pop(project_code, None)

        disc_dev, _, d_addr, d_tok = discover_latest_conversation(project_code, project_title, workspace_path, target_role='dev')
        target_conv = disc_dev or (prev_info.get('conv_id') if prev_info else None)
        if target_conv and get_conversation_role(target_conv) != 'advisor':
            ls_addr, csrf_tok = find_active_ls_for_conversation(target_conv)
            if not ls_addr:
                ls_addr, csrf_tok = get_antigravity_ls_env()
            stop_msg = (
                f"🛑 **[GÖREV İPTAL EDİLDİ / GERİ ÇEKİLDİ — ÇALIŞMAYI DERHAL DURDURUN]**\n\n"
                f"Görev: **{task_code}** ({task_title})\n"
                f"Kullanıcı görevi 'In Progress' sütunundan geri çekti.\n"
                f"Lütfen mevcut işlemleri durdurun ve yeni görev atanana kadar BEKLEMEDE (IDLE) kalın."
            )
            env = os.environ.copy()
            if ls_addr:
                env["ANTIGRAVITY_LS_ADDRESS"] = ls_addr
            if csrf_tok:
                env["ANTIGRAVITY_CSRF_TOKEN"] = csrf_tok
            stop_cmd = [AGENTAPI_EXE, "agentapi", "send-message", f"--title=[DURDUR] {task_code}", target_conv, stop_msg]
            run_silent(stop_cmd, env=env, cwd=project_dir, capture_output=True, text=True, timeout=10)
        return

    if event in IGNORED_AGENT_EVENTS:
        return

    current_role = 'qa' if event == 'task.submitted' else 'dev'

    discovered_cid, matched_uri, disc_addr, disc_tok = discover_latest_conversation(
        project_code, project_title, workspace_path, target_role=current_role
    )

    if event == 'task.submitted':
        with state_lock:
            project_active_state.pop(project_code, None)

        valid_qa_cid = discovered_cid
        if not valid_qa_cid and qa_conv_id and get_conversation_role(qa_conv_id) == 'qa':
            valid_qa_cid = qa_conv_id

        if not valid_qa_cid:
            print(f"✅ [GÖREV TESLİM EDİLDİ - QA PENCERESİ AÇIK DEĞİL] [{project_code}] {task_code} -> 4 sn sonra sıradaki göreve geçiliyor...")
            time.sleep(4)
            if project_id:
                fin_id = int(task_id) if task_id else 'null'
                next_cmd = ['php', '-r', f"require 'Core/System.php'; \\Modules\\Board\\Services\\TaskLifecycleService::dispatchNextAiTaskIfAvailable({int(project_id)}, {fin_id});"]
                run_silent(next_cmd, capture_output=True, text=True, cwd=APP_ROOT, timeout=10)
            return

    target_conv_id = discovered_cid
    ls_address = disc_addr
    csrf_token = disc_tok

    if not target_conv_id:
        fallback_id = qa_conv_id if current_role == 'qa' else dev_conv_id
        if fallback_id and get_conversation_role(fallback_id) == current_role:
            target_conv_id = fallback_id

    if not target_conv_id:
        print(f"❌ [HEDEF PENCERE YOK] [{project_code}] '{current_role.upper()}' rolüne sahip açık bir pencere bulunamadı! Görev Danışman'a gitmemesi için durduruldu.")
        return

    sync_conversation_id_to_db(project_code, target_conv_id, role=current_role)

    if not ls_address:
        addr, tok = find_active_ls_for_conversation(target_conv_id)
        if addr:
            ls_address, csrf_token = addr, tok

    if not ls_address:
        ls_address, csrf_token = get_antigravity_ls_env()

    is_resumed = bool(data.get('resumed', False))

    with state_lock:
        prev_state = project_active_state.get(project_code)
        if prev_state and prev_state.get('task_code') == task_code and event in ('task.started', 'task.in_progress'):
            prev_cid = prev_state.get('conv_id')
            elapsed = time.time() - prev_state.get('dispatched_at', 0)

            if prev_cid and prev_cid == target_conv_id and elapsed < 180:
                return
            elif prev_cid and target_conv_id and prev_cid != target_conv_id:
                is_resumed = True
            elif elapsed >= 180:
                is_resumed = True

    print(f"\n=======================================================")
    print(f"🔔 [GÖREV TETİKLENİYOR] -> Olay: {event} | Rol: {current_role.upper()} | Hedef CID: {target_conv_id} | Görev: {task_code}")

    clean_title = task_title.replace('🔄 [RESUMING TASK] ', '').replace('⚠️ [REVISION REQUEST] ', '')
    priority = data.get('priority', 'normal')

    rules_block = ""
    if agent_rules:
        rules_block = f"\n\n---\n📌 **PROJECT RULES ([{project_code}] {project_title}):**\n{agent_rules}\n"

    if event in ('task.started', 'task.in_progress', 'task.revision_requested', 'task.modification_requested'):
        is_rev = event in ('task.revision_requested', 'task.modification_requested')
        interrupt_header = (
            f"🚨 **[MANDATORY TASK INTERRUPT — STOP ALL PREVIOUS TOPICS!]**\n"
            f"> **DIRECTIVE:** Drop any previous conversation in this window immediately. Your ONLY active objective now is to execute and deliver task **{task_code}** below:\n\n"
        )
        if is_rev:
            title_header = f"⚠️ [REVISION] {task_code}: {clean_title}"
            banner = f"{interrupt_header}⚠️ **[{project_code}] REVISION TASK MOVED TO IN PROGRESS!**\n🔴 **Requested Changes:** {data.get('revision_reason', 'Revision requested.')}\n\n"
        elif is_resumed:
            title_header = f"🔄 [RESUMED] {task_code}: {clean_title}"
            banner = f"{interrupt_header}🔄 **[SESSION HANDOFF / RESUME DIRECTIVE]**\nThis task is currently IN PROGRESS and awaiting completion.\n\n"
        else:
            title_header = f"🚀 [IN PROGRESS] {task_code}: {clean_title}"
            banner = f"{interrupt_header}🚀 **TaskManager Task Marked as 'In Progress'!**\n\n"

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

        attachments = data.get('attachments', [])
        attachments_md = ""
        if attachments:
            attachments_md = (
                "\n\n### 📎 Task Attachments & Reference Designs:\n"
                "> **MANDATORY ASSET INSPECTION:** Carefully inspect all attached visual designs and reference documents before writing any code.\n"
            )
            for att in attachments:
                fname = att.get('file_name') or os.path.basename(att.get('file_path', 'attachment'))
                abs_p = (att.get('abs_path') or att.get('file_path', '')).replace('\\', '/')
                furi = att.get('file_uri') or f"file:///{abs_p.lstrip('/')}"
                ftype = att.get('file_type', '').lower()
                is_img = ftype.startswith('image/') or any(fname.lower().endswith(ext) for ext in ['.png', '.jpg', '.jpeg', '.webp', '.gif', '.svg'])
                icon = "🖼️" if is_img else "📄"
                hint = "*(Reference Image: inspect using `view_file`)*" if is_img else "*(Attached Document: view using `view_file`)*"
                attachments_md += f"- {icon} [{fname}]({furi}) {hint}\n"

        message_text = (
            f"{banner}"
            f"📌 **Project:** [{project_code}] {project_title} (`{project_dir}`)\n"
            f"📋 **Task Code:** `{task_code}`\n"
            f"🏷️ **Title:** {clean_title}\n"
            f"⚡ **Priority:** {priority}\n\n"
            f"### 📝 Task Description & Requirements:\n{desc}"
            f"{checklist_md}"
            f"{attachments_md}"
            f"{rules_block}\n\n"
            f"---\n"
            f"🛑 **STRICT OPERATIONAL GUARDRAILS:**\n"
            f"1. **SINGLE ACTIVE TASK FOCUS:** Work EXCLUSIVELY on `{task_code}` in project `[{project_code}]`. NEVER jump to older or other tasks!\n"
            f"2. **ZERO ABANDONMENT & AUTONOMOUS QA:** Complete 100% of implementation and live automated verification before responding to the user. The user is NOT a QA tester.\n"
            f"3. **SUBMIT AND STOP (USE OFFICIAL REST API):** When completed and verified, execute the following REST API request in your terminal to move `{task_code}` to `submitted` (REVIEW), then **END YOUR TURN**:\n\n"
            f"```powershell\n"
            f"[Console]::OutputEncoding = [System.Text.Encoding]::UTF8; $OutputEncoding = [System.Text.Encoding]::UTF8;\n"
            f"$headers = @{{ 'Authorization' = 'Bearer {api_token}'; 'Content-Type' = 'application/json; charset=utf-8' }}\n"
            f"$body = @{{ notes = '1. What Was Done: ... 2. Where to Test: ... 3. How to Test: ... 4. Test Evidence: ...' }} | ConvertTo-Json -Compress\n"
            f"Invoke-RestMethod -Uri '{api_base_url}/api/tasks/{task_code}/workflow/submit' -Method Post -Headers $headers -Body ([System.Text.Encoding]::UTF8.GetBytes($body))\n"
            f"```"
        )
    elif event == 'task.submitted':
        submission_notes = data.get('submission_notes', '').strip() or '*(No submission notes provided)*'
        title_header = f"🔍 [QA AUDIT REQUIRED] {task_code}: {clean_title}"

        task_desc = (data.get('description') or '').strip()
        task_desc_clean = re.sub(r'---\s*⚡\s*\*\*AUTONOMOUS COMPLETION INSTRUCTION:.*$', '', task_desc, flags=re.DOTALL).strip() or '*(No description provided)*'

        custom_qa_section = ""
        if qa_test_instructions:
            custom_qa_section += f"\n\n### 🛠️ PROJECT-SPECIFIC QA TEST INSTRUCTIONS:\n{qa_test_instructions}\n"
        if qa_global_instructions:
            custom_qa_section += f"\n\n### 🌐 GLOBAL QA TEST PROTOCOL:\n{qa_global_instructions}\n"

        message_text = (
            f"🚨 **[MANDATORY QA VERIFICATION DIRECTIVE — NO CODE MODIFICATION ALLOWED!]**\n"
            f"> **ROLE:** You are the Lead QA & Autonomous Testing Inspector. You CANNOT write feature code. Your ONLY objective is to rigorously test, verify, and audit task **{task_code}** delivered by the Developer Agent.\n\n"
            f"### 📋 DELIVERED TASK CONTEXT:\n"
            f"- **Project:** [{project_code}] {project_title} (`{project_dir}`)\n"
            f"- **Task Code:** `{task_code}` | **Title:** {clean_title}\n"
            f"- **Original Task Requirements:**\n{task_desc_clean}\n"
            f"- **Developer Submission Notes:**\n{submission_notes}\n\n"
            f"### 🔍 MANDATORY STEP-BY-STEP TESTING PROTOCOL:\n"
            f"1. **LIVE BROWSER FUNCTIONAL TEST:** Open target URL in the live browser. Verify forms, buttons, and UI layout.\n"
            f"2. **NETWORK & CONSOLE AUDIT:** Check Developer Console (0 JS errors, 0 404s) and Network payload (NO Xdebug/Fatal errors even on HTTP 200).\n"
            f"3. **CLI GATEKEEPER AUDIT:** Execute `php taskmanager-audit/audit.php ALL` (if TaskManager project) and verify 0 Violations.\n"
            f"{custom_qa_section}\n"
            f"---\n\n"
            f"### ⚖️ FINAL VERDICT COMMANDS (CHOOSE EXACTLY ONE):\n\n"
            f"**OPTION A — IF ALL TESTS PASS WITHOUT ANY FLAW (APPROVE & CLOSE):**\n"
            f"```powershell\n"
            f"[Console]::OutputEncoding = [System.Text.Encoding]::UTF8; $OutputEncoding = [System.Text.Encoding]::UTF8;\n"
            f"$headers = @{{ 'Authorization' = 'Bearer {api_token}'; 'Content-Type' = 'application/json; charset=utf-8' }}\n"
            f"$body = @{{ action = 'review'; decision = 'approved'; feedback = 'All live browser UI, Console, Network, and Audit tests passed with zero defects.' }} | ConvertTo-Json -Compress\n"
            f"Invoke-RestMethod -Uri '{api_base_url}/api/tasks/{task_code}/workflow' -Method Post -Headers $headers -Body ([System.Text.Encoding]::UTF8.GetBytes($body))\n"
            f"```\n\n"
            f"**OPTION B — IF ANY BUG, CSS GLITCH, JS ERROR OR AUDIT FAIL IS FOUND (REJECT & REQUEST REVISION):**\n"
            f"```powershell\n"
            f"[Console]::OutputEncoding = [System.Text.Encoding]::UTF8; $OutputEncoding = [System.Text.Encoding]::UTF8;\n"
            f"$headers = @{{ 'Authorization' = 'Bearer {api_token}'; 'Content-Type' = 'application/json; charset=utf-8' }}\n"
            f"$body = @{{ action = 'review'; decision = 'rejected'; feedback = '[QA DEFECT REPORT]\n1. Defect: ...\nPlease fix and re-submit.' }} | ConvertTo-Json -Compress\n"
            f"Invoke-RestMethod -Uri '{api_base_url}/api/tasks/{task_code}/workflow' -Method Post -Headers $headers -Body ([System.Text.Encoding]::UTF8.GetBytes($body))\n"
            f"```"
        )
    elif event == 'project.webhook_test':
        title_header = f"Webhook Test Ping: [{project_code}]"
        message_text = f"✅ **TaskManager Webhook Test Ping Successful!**\n- **Project:** [{project_code}] {project_title}\n- **Target Conversation ({current_role.upper()}):** {target_conv_id}"
    else:
        return

    # ── ADIM 3: Pre-Flight Akıllı Kota Kontrolü (<= %10 ise otomatik geçiş) ──
    try:
        from account_manager import AccountManager
        acct_mgr = AccountManager()
        active_email = acct_mgr.get_active_email()
        reports = acct_mgr.inspect_accounts_health(target_model_family='gemini-3.8')
        curr_report = next((r for r in reports if r['email'].lower() == (active_email or '').lower()), None)
        curr_pct = curr_report['target_model_pct'] if curr_report else 100
        print(f"📊 [PRE-FLIGHT KOTA KONTROLÜ] Aktif: {active_email} (Kota: %{curr_pct})")

        if curr_pct <= 10:
            best_next = acct_mgr.find_best_next_account(exclude_email=active_email, target_model='gemini-3.8')
            if best_next and best_next['target_model_pct'] > curr_pct:
                print(f"⚡ [PRE-FLIGHT OTO-GEÇİŞ] Kota düşük (%{curr_pct} <= 10). {best_next['email']} (%{best_next['target_model_pct']}) hesabına geçiliyor...")
                acct_mgr.switch_active_account(best_next)
                time.sleep(1.0)
                # Adresleri tazele
                fresh_addr, fresh_token = get_antigravity_ls_env(force_refresh=True)
                if fresh_addr:
                    ls_address = fresh_addr
                    csrf_token = fresh_token
    except Exception as e_pre:
        print(f"[!] Pre-flight kota kontrolü hatası: {e_pre}")

    env = os.environ.copy()
    if ls_address:
        env["ANTIGRAVITY_LS_ADDRESS"] = ls_address
    if csrf_token:
        env["ANTIGRAVITY_CSRF_TOKEN"] = csrf_token

    cmd = [AGENTAPI_EXE, "agentapi", "send-message", f"--title={title_header}", target_conv_id, message_text]

    try:
        result = run_silent(cmd, env=env, cwd=project_dir, capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=12)

        # ── 429 / Quota Exhaustion Failover Kontrolü ──
        out_err = ((result.stdout or '') + ' ' + (result.stderr or '')).lower()
        if result.returncode != 0 and any(k in out_err for k in ['429', 'quota', 'resourceexhausted', 'rate_limit', 'exceeded']):
            print(f"⚠️ [KOTA AŞIMI TESPİT EDİLDİ - 429] Çıktı: {out_err[:200]}")
            try:
                from account_manager import AccountManager
                acct_mgr = AccountManager()
                active_email = acct_mgr.get_active_email()
                best_next = acct_mgr.find_best_next_account(exclude_email=active_email, target_model='gemini-3.8')
                if best_next:
                    print(f"🔄 [429 OTO-FAILOVER] Yedek hesaba geçiliyor: {best_next['email']} (%{best_next['target_model_pct']})")
                    acct_mgr.switch_active_account(best_next)
                    time.sleep(1.0)
                    fresh_addr, fresh_token = get_antigravity_ls_env(force_refresh=True)
                    if fresh_addr:
                        env["ANTIGRAVITY_LS_ADDRESS"] = fresh_addr
                        if fresh_token:
                            env["ANTIGRAVITY_CSRF_TOKEN"] = fresh_token
                    result = run_silent(cmd, env=env, cwd=project_dir, capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=12)
            except Exception as e_failover:
                print(f"[!] 429 failover switch hatası: {e_failover}")

        if result.returncode != 0:
            fresh_addr, fresh_token = get_antigravity_ls_env(force_refresh=True)
            if fresh_addr:
                env["ANTIGRAVITY_LS_ADDRESS"] = fresh_addr
                if fresh_token:
                    env["ANTIGRAVITY_CSRF_TOKEN"] = fresh_token
                result = run_silent(cmd, env=env, cwd=project_dir, capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=12)

        print(f"✅ Gönderim Sonucu ({target_conv_id} - {current_role.upper()}): ExitCode {result.returncode}")
        if result.returncode == 0 and task_code and current_role == 'dev':
            with state_lock:
                project_active_state[project_code] = {
                    'task_code': task_code,
                    'conv_id': target_conv_id,
                    'dispatched_at': time.time()
                }
    except Exception as err:
        print(f"❌ Gönderim Hatası: {str(err)}")


class WebhookHandler(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        if self.path in ('/restart', '/shutdown'):
            self.send_response(200)
            self.send_header('Content-Type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps({"status": "shutting_down"}).encode())
            def _exit_later():
                time.sleep(0.5)
                os._exit(0)
            threading.Thread(target=_exit_later, daemon=True).start()
            return

        self.send_response(200)
        self.send_header('Content-Type', 'text/plain')
        self.end_headers()
        self.wfile.write(b"TaskManager Dispatcher OK")

    def do_POST(self):
        if self.path in ('/restart', '/shutdown'):
            self.send_response(200)
            self.send_header('Content-Type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps({"status": "shutting_down"}).encode())
            def _exit_later():
                time.sleep(0.5)
                os._exit(0)
            threading.Thread(target=_exit_later, daemon=True).start()
            return

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
    print("🤖 Antigravity 3-Agent Dispatcher v4.5 (Coder / QA / Advisor) Başlatıldı!")
    print(f"📡 Dinleme Adresi : http://localhost:{PORT}/webhook")
    print("=======================================================")
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        print("\nServis durduruldu.")

if __name__ == '__main__':
    run_server()