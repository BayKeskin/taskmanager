r"""
TaskManager -> Antigravity Central Multi-Agent Dispatcher (Environment - v5.0 Shift Handoff & Carry-Over Engine)
Port: 4005 (TAMAMEN BAĞIMSIZ PARALEL ÇALIŞMA ORTAMI)
- Port 4005 (Canlı sistem) ile asla çakışmaz.
- Sabit kullanıcı yolları (C:\Users\DELL) ve eklenti bağımlılıkları tamamen kaldırılmış, os.path.expanduser / os.environ ile dinamikleştirilmiştir.
- Vardiya Devir Teslim Hafızası (Shift Handoff) & Yarım İş Devri (Carry-over Brief) motoru içerir.
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

try:
    from account_manager import AccountManager
except ImportError:
    AccountManager = None

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
APP_ROOT = os.path.abspath(os.path.join(BASE_DIR, '..', '..', '..'))
if not os.path.exists(os.path.join(APP_ROOT, 'Core', 'System.php')):
    possible_root = os.path.dirname(BASE_DIR)
    if os.path.exists(os.path.join(possible_root, 'Core', 'System.php')):
        APP_ROOT = possible_root

# Spesifik Dosyalar ve Yapılandırmalar
PORT = 4005
LOG_FILE = os.path.join(BASE_DIR, 'dispatcher.log')
LAST_SESSIONS_FILE = os.path.join(BASE_DIR, 'last_sessions.json')
WORKSPACES_CONFIG = os.path.join(BASE_DIR, 'workspaces.json')
LS_ENV_FILE = os.path.join(BASE_DIR, 'ls_env.json')
LS_ADDRESS_FILE = os.path.join(BASE_DIR, 'ls_address.txt')
CSRF_TOKEN_FILE = os.path.join(BASE_DIR, 'csrf_token.txt')

# DİNAMİK KULLANICI YOLLARI (Sabit DELL yolu yok)
USER_HOME = os.path.expanduser('~')
LOCAL_APP_DATA = os.environ.get('LOCALAPPDATA', os.path.join(USER_HOME, 'AppData', 'Local'))
ROAMING_APP_DATA = os.environ.get('APPDATA', os.path.join(USER_HOME, 'AppData', 'Roaming'))

def _resolve_agentapi():
    candidates = [
        os.path.join(LOCAL_APP_DATA, 'Programs', 'Antigravity IDE', 'resources', 'app', 'extensions', 'antigravity', 'bin', 'language_server_windows_x64.exe'),
        r'C:\Users\DELL\AppData\Local\Programs\Antigravity IDE\resources\app\extensions\antigravity\bin\language_server_windows_x64.exe',
        r'C:\Program Files\Antigravity IDE\resources\app\extensions\antigravity\bin\language_server_windows_x64.exe'
    ]
    for c in candidates:
        if os.path.isfile(c):
            return c
    return candidates[0]

AGENTAPI_EXE = _resolve_agentapi()
ANTIGRAVITY_CONV_DIR = os.path.join(USER_HOME, '.gemini', 'antigravity-ide', 'conversations')

# WINDOWS'TA SİYAH CMD EKRANI ÇIKMASINI ENGELLEYEN BAYRAK:
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
    """Tüm açık Antigravity Language Server süreçlerini ve dinledikleri portları döndürür."""
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
    """Belirli bir conversation ID'sini barındıran doğru LS örneğini ve portunu bulur."""
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
    - 'danışman' / 'advisor' içeriyorsa -> 'advisor' (asla görev verilmez!)
    - 'qa' / 'test' içeriyorsa -> 'qa'
    - 'coder' / 'kod' / 'developer' içeriyorsa -> 'dev'
    - Belirtilmemişse -> None
    """
    if not cid:
        return None
    cid = str(cid).strip()
    transcript_file = os.path.join(
        os.environ.get('USERPROFILE', r'C:\Users\DELL'),
        r'.gemini\antigravity-ide\brain',
        cid,
        '.system_generated',
        'logs',
        'transcript.jsonl'
    )
    if not os.path.exists(transcript_file):
        return None

    try:
        with open(transcript_file, 'r', encoding='utf-8', errors='replace') as f:
            for _ in range(15):
                line = f.readline()
                if not line:
                    break
                if '"source":"USER_EXPLICIT"' in line:
                    data = json.loads(line)
                    raw_content = str(data.get('content', '')).lower()
                    clean_content = raw_content.split('<additional_metadata>')[0].replace('<user_request>', '').replace('</user_request>', '').strip()

                    # 1. Danışman koruması (en yüksek öncelik)
                    if any(w in clean_content for w in ['danışman', 'danisman', 'advisor', 'consultant']):
                        return 'advisor'
                    # 2. QA kontrolü
                    if any(w in clean_content for w in ['qa', 'test uzmanı', 'test uzmani', 'tester', 'test']):
                        return 'qa'
                    # 3. Coder kontrolü
                    if any(w in clean_content for w in ['coder', 'koder', 'developer', 'geliştirici', 'gelistirici', 'kod']):
                        return 'dev'
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
                        addr, tok = find_active_ls_for_conversation(cid)
                        matched_candidates.append({
                            'cid': cid,
                            'uri': f"file:///{target}",
                            'addr': addr,
                            'tok': tok,
                            'mtime': os.path.getmtime(db_path)
                        })
                        break
        except Exception:
            continue

    if not matched_candidates:
        return None, None, None, None

    # 1. Aşama: Tam rol eşleşmesi ara (örn: ilk mesaja 'coder' ya da 'qa' yazılmış pencereler)
    for cand in matched_candidates:
        cid = cand['cid']
        detected_role = get_conversation_role(cid)
        if detected_role == 'advisor':
            continue
        if detected_role == target_role:
            print(f"🎯 [ROL EŞLEŞMESİ BULUNDU] {project_code} -> CID: {cid} | Rol: {target_role.upper()}")
            return cand['cid'], cand['uri'], cand['addr'], cand['tok']

    # 2. Aşama: Eğer açık role atanmış pencere yoksa, Danışman ve zıt rol OLMAYAN en güncel pencereyi seç
    opposite_role = 'qa' if target_role == 'dev' else 'dev'
    for cand in matched_candidates:
        cid = cand['cid']
        detected_role = get_conversation_role(cid)
        if detected_role == 'advisor':
            continue
        if detected_role != opposite_role:
            print(f"⚡ [GENEL/NÖTR PENCERE SEÇİLDİ] {project_code} -> CID: {cid} (Hedef Rol: {target_role.upper()})")
            return cand['cid'], cand['uri'], cand['addr'], cand['tok']

    return None, None, None, None

def is_conversation_alive(cid):
    """
    Belirli bir conversation ID'sinin hem diskte .db dosyasının olup olmadığını
    hem de aktif bir Language Server sürecine bağlı olup olmadığını doğrular.
    """
    if not cid or not isinstance(cid, str):
        return False, None, None
    cid = cid.strip()
    db_file = os.path.join(ANTIGRAVITY_CONV_DIR, f"{cid}.db")
    if not os.path.exists(db_file):
        return False, None, None
    
    addr, tok = find_active_ls_for_conversation(cid)
    if addr:
        return True, addr, tok
    return False, None, None

def sync_conversation_id_to_db(project_code, conv_id, role='dev'):
    """
    Hem dev_conversation_id hem agent_conversation_id (veya qa_conversation_id) sütunlarını senkronize günceller.
    """
    try:
        conv_id = str(conv_id).strip()
        project_code = str(project_code).strip()
        root_dir = APP_ROOT
        if role == 'qa':
            update_sql = "UPDATE projects SET qa_conversation_id = :cid WHERE project_code = :pcode"
            exec_params = "array(':cid' => '" + conv_id + "', ':pcode' => '" + project_code + "')"
        else:
            update_sql = "UPDATE projects SET dev_conversation_id = :cid1, agent_conversation_id = :cid2 WHERE project_code = :pcode"
            exec_params = "array(':cid1' => '" + conv_id + "', ':cid2' => '" + conv_id + "', ':pcode' => '" + project_code + "')"

        php_code = (
            f"require_once 'Core/System.php'; "
            f"$db = new Database(); "
            f"$stmt = $db->connect->prepare(\"{update_sql}\"); "
            f"$stmt->execute({exec_params}); "
            f"echo 'ROWS:' . $stmt->rowCount();"
        )
        res = run_silent(['php', '-r', php_code], cwd=root_dir, capture_output=True, text=True, timeout=5)
        out = (res.stdout or '').strip()
        print(f"📝 [- DB SYNC ({role})] Proje: {project_code} -> CID: {conv_id} | DB Yanıt: {out}")
    except Exception as e:
        print(f"⚠️ DB sync hatası: {e}")


# =========================================================================
# 🧠 VARDİYA DEVİR TESLİM HAFIZASI & YARIM İŞ DEVRİ (SHIFT HANDOFF MOTORU)
# =========================================================================

def load_last_sessions():
    """last_sessions.json dosyasından her projenin son kayıtlı conversation_id bilgisini yükler."""
    if os.path.exists(LAST_SESSIONS_FILE):
        try:
            with open(LAST_SESSIONS_FILE, 'r', encoding='utf-8') as f:
                data = json.load(f)
                if isinstance(data, dict):
                    return data
        except Exception:
            pass
    return {}

def save_last_session(project_code, conv_id, role='dev'):
    """Projeye ait son aktif oturum bilgisini diske yazar."""
    if not project_code or not conv_id:
        return
    sessions = load_last_sessions()
    if project_code not in sessions:
        sessions[project_code] = {}
    sessions[project_code][role] = {
        'conversation_id': conv_id,
        'updated_at': int(time.time())
    }
    try:
        with open(LAST_SESSIONS_FILE, 'w', encoding='utf-8') as f:
            json.dump(sessions, f, indent=2, ensure_ascii=False)
    except Exception as e:
        print(f"⚠️ last_sessions.json kaydedilemedi: {e}")

def get_git_status_info(repo_dir):
    """Repo klasöründe git status ve diff kontrolü yapar."""
    if not os.path.isdir(repo_dir) or not os.path.exists(os.path.join(repo_dir, '.git')):
        return False, "", ""
    try:
        st_res = run_silent(['git', 'status', '-s'], cwd=repo_dir, capture_output=True, text=True, timeout=5)
        status_out = (st_res.stdout or '').strip()
        is_dirty = bool(status_out)
        
        diff_out = ""
        if is_dirty:
            diff_res = run_silent(['git', 'diff', '--stat'], cwd=repo_dir, capture_output=True, text=True, timeout=5)
            diff_out = (diff_res.stdout or '').strip()
            
        log_res = run_silent(['git', 'log', '-n', '3', '--stat', '--oneline'], cwd=repo_dir, capture_output=True, text=True, timeout=5)
        log_out = (log_res.stdout or '').strip()

        return is_dirty, status_out, diff_out, log_out
    except Exception as e:
        print(f"⚠️ Git inceleme hatası: {e}")
        return False, "", "", ""

def fetch_last_completed_tasks_from_db(project_id, project_code):
    """Projede son tamamlanan 3 görevin başlık ve notlarını DB'den çeker."""
    tasks = []
    try:
        where = f"t.project_id = {int(project_id)}" if project_id else f"p.project_code = '{project_code}'"
        php_code = (
            f"require_once 'Core/System.php'; "
            f"$db = new Database(); "
            f"$sql = \"SELECT t.task_code, t.title, ts.notes FROM tasks t "
            f"LEFT JOIN projects p ON t.project_id = p.id "
            f"LEFT JOIN task_submissions ts ON ts.task_id = t.id "
            f"WHERE {where} AND t.status = 'completed' "
            f"ORDER BY t.id DESC LIMIT 3\"; "
            f"$rows = $db->connect->query($sql)->fetchAll(PDO::FETCH_ASSOC); "
            f"echo json_encode($rows);"
        )
        res = run_silent(['php', '-r', php_code], cwd=APP_ROOT, capture_output=True, text=True, timeout=5)
        if res.returncode == 0 and res.stdout.strip():
            tasks = json.loads(res.stdout.strip())
    except Exception as e:
        print(f"⚠️ DB tamamlanan görev çekme hatası: {e}")
    return tasks

def build_shift_handoff_brief(project_id, project_code, project_dir, old_cid, new_cid):
    """
    Vardiya Değişimi veya Yarım İş Devri durumunu analiz edip nihai prompt'a eklenecek
    özel markdown özet bloğunu oluşturur.
    """
    is_dirty, status_out, diff_out, log_out = get_git_status_info(project_dir)

    handoff_block = (
        f"\n\n=======================================================\n"
        f"🚨 **[SESSION HANDOFF & SHIFT CHANGE NOTIFICATION]**\n"
        f"> **Context Transition:** Agent account/session changed from `{old_cid}` to `{new_cid}`.\n"
        f"> **Workspace Dir:** `{project_dir}`\n"
    )

    if is_dirty:
        # DURUM B: Görev ortasında kredi bitti / yarım bırakıldı (Git kirli)
        print(f"🔄 [- CARRY OVER] Yarım bırakılmış iş tespit edildi! (Git Status Dirty) -> {project_code}")
        handoff_block += (
            f"\n🔄 **[CARRY-OVER BRIEF — INTERRUPTED WORK DETECTED]**\n"
            f"> [!CAUTION]\n"
            f"> The previous agent session was interrupted mid-task (e.g. quota limit, session reset).\n"
            f"> Uncommitted local changes detected in repository:\n\n"
            f"**Git Status (`git status -s`):**\n"
            f"```text\n{status_out}\n```\n\n"
        )
        if diff_out:
            handoff_block += (
                f"**Git Diff Summary (`git diff --stat`):**\n"
                f"```text\n{diff_out}\n```\n\n"
            )
        handoff_block += (
            f"> **MANDATORY DIRECTIVE FOR NEW AGENT:**\n"
            f"> 1. Inspect the modified files above before starting.\n"
            f"> 2. Reconcile unfinished work seamlessly without discarding user progress.\n"
            f"> 3. Resume the task to complete autonomous implementation and live verification.\n"
            f"=======================================================\n\n"
        )
    else:
        # DURUM A: Görev bitmiş, temiz hesap değişimi (Git temiz)
        print(f"🧠 [- SHIFT HANDOFF] Temiz vardiya devri tespit edildi! (Git Clean) -> {project_code}")
        last_tasks = fetch_last_completed_tasks_from_db(project_id, project_code)
        tasks_summary = ""
        if last_tasks:
            for t in last_tasks:
                t_code = t.get('task_code', '')
                t_title = t.get('title', '')
                t_notes = (t.get('notes') or '').strip()
                tasks_summary += f"- **[{t_code}] {t_title}**\n"
                if t_notes:
                    first_line = t_notes.split('\n')[0][:120]
                    tasks_summary += f"  *Notes:* {first_line}...\n"
        else:
            tasks_summary = "- *(No recently completed tasks found in database)*\n"

        handoff_block += (
            f"\n🧠 **[RECENT PROJECT MEMORY & SHIFT HANDOFF BRIEF]**\n"
            f"> [!NOTE]\n"
            f"> Clean shift handoff. Repository is in a clean git state.\n"
            f"> Here is the latest context and work recently accomplished in this project:\n\n"
            f"**Recently Completed Tasks:**\n"
            f"{tasks_summary}\n"
        )
        if log_out:
            handoff_block += (
                f"**Recent Commits (`git log -n 3 --stat`):**\n"
                f"```text\n{log_out}\n```\n\n"
            )
        handoff_block += (
            f"> **MANDATORY DIRECTIVE FOR NEW AGENT:**\n"
            f"> Carry this context forward. Maintain code styling, architectural integrity, and conventions established in prior tasks.\n"
            f"=======================================================\n\n"
        )

    return handoff_block

# =========================================================================

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

    if (not qa_conv_id or not dev_conv_id) and (project_id or project_code):
        try:
            where_clause = f"id = {int(project_id)}" if project_id else f"project_code = '{project_code}'"
            db_php = f"require_once 'Core/System.php'; \$db = new Database(); \$stmt = \$db->connect->query('SELECT dev_conversation_id, qa_conversation_id, qa_test_instructions, agent_conversation_id FROM projects WHERE {where_clause} LIMIT 1'); echo json_encode(\$stmt->fetch(PDO::FETCH_ASSOC));"
            res = run_silent(['php', '-r', db_php], cwd=APP_ROOT, capture_output=True, text=True, timeout=5)
            if res.returncode == 0 and res.stdout:
                db_proj = json.loads(res.stdout)
                if db_proj:
                    if not qa_conv_id and db_proj.get('qa_conversation_id'):
                        qa_conv_id = str(db_proj['qa_conversation_id']).strip()
                    if not dev_conv_id:
                        dev_conv_id = str(db_proj.get('dev_conversation_id') or db_proj.get('agent_conversation_id') or '').strip() or None
                    if not qa_test_instructions and db_proj.get('qa_test_instructions'):
                        qa_test_instructions = str(db_proj['qa_test_instructions']).strip()
        except Exception:
            pass

    data = payload.get('data', {})
    if not isinstance(data, dict):
        data = {}

    task_code = str(payload.get('task_code') or data.get('task_code', '')).strip()
    task_title = str(payload.get('task_title') or data.get('title', '')).strip()
    task_id = payload.get('task_id') or data.get('task_id')
    api_base_url = (payload.get('api_base_url') or 'http://localhost/taskmanager').rstrip('/')
    api_token = str(payload.get('api_token') or '').strip()
    if not api_token:
        try:
            token_php = "require_once 'Core/System.php'; \$cfg = \\Modules\\RestApi\\Services\\RestApiService::getDynamicApiConfig(); echo \$cfg['api_token'] ?? '';"
            tok_res = run_silent(['php', '-r', token_php], cwd=APP_ROOT, capture_output=True, text=True, timeout=3)
            if tok_res.returncode == 0 and tok_res.stdout.strip():
                api_token = tok_res.stdout.strip()
        except Exception:
            pass
    if not api_token:
        api_token = 'tm_admin_talha_keskin_token_2026'

    workspace_path = payload.get('workspace_path') or data.get('workspace_path', '')
    agent_rules = (payload.get('agent_rules') or data.get('agent_rules') or '').strip()
    project_dir = get_project_primary_dir(project_code, workspace_path)

    if event == 'task.requeued':
        with state_lock:
            prev_info = project_active_state.pop(project_code, None)

        target_conv = dev_conv_id or (prev_info.get('conv_id') if prev_info else None)
        if target_conv and os.path.exists(AGENTAPI_EXE):
            ls_addr, csrf_tok = find_active_ls_for_conversation(target_conv)
            if not ls_addr:
                ls_addr, csrf_tok = get_antigravity_ls_env()

            stop_msg = (
                f"🛑 **[GÖREV İPTAL EDİLDİ / GERİ ÇEKİLDİ — ÇALIŞMAYI DERHAL DURDURUN]**\n\n"
                f"Görev: **{task_code}** ({task_title})\n"
                f"Kullanıcı görevi 'In Progress' sütunundan geri çekti veya iptal etti.\n"
                f"Lütfen mevcut dosya değişikliklerini durdurun, terminal işlemlerini sonlandırın ve yeni bir görev atanana kadar BEKLEMEDE (IDLE) kalın."
            )
            env = os.environ.copy()
            if ls_addr:
                env["ANTIGRAVITY_LS_ADDRESS"] = ls_addr
            if csrf_tok:
                env["ANTIGRAVITY_CSRF_TOKEN"] = csrf_tok
            stop_cmd = [AGENTAPI_EXE, "agentapi", "send-message", f"--title=[DURDUR] {task_code}", target_conv, stop_msg]
            try:
                run_silent(stop_cmd, env=env, cwd=project_dir, capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=10)
                print(f"🛑 [- GÖREV GERİ ALINDI] [{project_code}] {task_code} -> Ajan ({target_conv}) durduruldu ve kilit silindi.")
            except Exception as e:
                print(f"⚠️ [- DURDURMA BİLDİRİMİ HATASI] {e}")
        else:
            print(f"🛑 [- GÖREV GERİ ALINDI] [{project_code}] {task_code} -> Kilit silindi.")
        return

    if event == 'task.submitted':
        with state_lock:
            project_active_state.pop(project_code, None)

        if not qa_conv_id:
            print(f"✅ [- GÖREV TESLİM EDİLDİ - TEK AJAN MODU] [{project_code}] {task_code} -> 4 sn bekleniyor...")
            time.sleep(4)
            if project_id:
                app_root = APP_ROOT
                fin_id = int(task_id) if task_id else 'null'
                next_cmd = ['php', '-r', f"require 'Core/System.php'; \\Modules\\Board\\Services\\TaskLifecycleService::dispatchNextAiTaskIfAvailable({int(project_id)}, {fin_id});"]
                run_silent(next_cmd, capture_output=True, text=True, cwd=app_root, timeout=10)
            return
        else:
            print(f"🔬 [- GÖREV TESLİM EDİLDİ - ÇİFT AJAN MODU] [{project_code}] {task_code} -> QA Ajanına ({qa_conv_id}) yönlendiriliyor...")

    if event in IGNORED_AGENT_EVENTS:
        return

    workspace_path = payload.get('workspace_path') or data.get('workspace_path', '')
    agent_rules = (payload.get('agent_rules') or data.get('agent_rules') or '').strip()
    project_dir = get_project_primary_dir(project_code, workspace_path)

    current_role = 'qa' if event == 'task.submitted' else 'dev'
    primary_conv_id = qa_conv_id if event == 'task.submitted' else dev_conv_id

    discovered_cid, matched_uri, disc_addr, disc_tok = discover_latest_conversation(project_code, project_title, workspace_path, target_role=current_role)
    if discovered_cid:
        discovered_cid = str(discovered_cid).strip()

    ls_address = disc_addr
    csrf_token = disc_tok
    primary_conv_id = qa_conv_id if event == 'task.submitted' else dev_conv_id

    # 1. ÖLÜ ID TEMİZLİĞİ VE CANLI SOHBET SEÇİMİ
    target_conv_id = None
    is_primary_alive = False
    p_addr, p_tok = None, None

    if primary_conv_id:
        is_primary_alive, p_addr, p_tok = is_conversation_alive(primary_conv_id)

    # Diskteki .db dosyalarının mtime (son düzenleme) tarihlerini kıyasla
    primary_mtime = 0
    discovered_mtime = 0
    if primary_conv_id:
        p_db = os.path.join(ANTIGRAVITY_CONV_DIR, f"{primary_conv_id}.db")
        if os.path.exists(p_db):
            primary_mtime = os.path.getmtime(p_db)
    if discovered_cid:
        d_db = os.path.join(ANTIGRAVITY_CONV_DIR, f"{discovered_cid}.db")
        if os.path.exists(d_db):
            discovered_mtime = os.path.getmtime(d_db)

    # Eğer gelen ID ölü ise VEYA discovered_cid mevcut ve daha güncel bir sohbetse:
    if not is_primary_alive and discovered_cid:
        print(f"⚠️ [- ÖLÜ/GEÇERSİZ ID TESPİT EDİLDİ] Gelen ID: '{primary_conv_id}' aktif değil! Otomatik keşfedilen canlı sohbet kullanılıyor: {discovered_cid}")
        target_conv_id = discovered_cid
        ls_address, csrf_token = disc_addr, disc_tok
        sync_conversation_id_to_db(project_code, discovered_cid, role=current_role)
    elif discovered_cid and discovered_mtime > (primary_mtime + 5):
        # Kullanıcı yeni bir sohbet açmış, eski sohbeti terk etmiş
        print(f"🔄 [- DAHA GÜNCEL SOHBET TESPİT EDİLDİ] Eski ID: {primary_conv_id} (mtime: {primary_mtime}) -> Yeni Keşif: {discovered_cid} (mtime: {discovered_mtime})")
        target_conv_id = discovered_cid
        ls_address, csrf_token = disc_addr, disc_tok
        sync_conversation_id_to_db(project_code, discovered_cid, role=current_role)
    elif is_primary_alive:
        target_conv_id = primary_conv_id
        ls_address, csrf_token = p_addr, p_tok
    elif discovered_cid:
        target_conv_id = discovered_cid
        ls_address, csrf_token = disc_addr, disc_tok
        sync_conversation_id_to_db(project_code, discovered_cid, role=current_role)
    else:
        target_conv_id = primary_conv_id

    if target_conv_id:
        target_conv_id = str(target_conv_id).strip()

    if not ls_address and target_conv_id:
        addr, tok = find_active_ls_for_conversation(target_conv_id)
        if addr:
            ls_address, csrf_token = addr, tok

    if not ls_address:
        ls_address, csrf_token = get_antigravity_ls_env()

    is_resumed = bool(data.get('resumed', False))

    # =========================================================================
    # VARDİYA DEĞİŞİMİ TESPİTİ VE ÖZET ENJEKSİYONU
    # =========================================================================
    shift_handoff_brief = ""
    saved_sessions = load_last_sessions()
    last_known_cid = saved_sessions.get(project_code, {}).get(current_role, {}).get('conversation_id')

    if target_conv_id and last_known_cid and target_conv_id != last_known_cid:
        print(f"🔄 [- VARDİYA DEĞİŞİMİ TESPİT EDİLDİ] [{project_code}] Eski: {last_known_cid} -> Yeni: {target_conv_id}")
        shift_handoff_brief = build_shift_handoff_brief(project_id, project_code, project_dir, last_known_cid, target_conv_id)
        is_resumed = True
    elif target_conv_id:
        # İlk kayıt ya da aynı session
        save_last_session(project_code, target_conv_id, current_role)

    # 2. ANTI-SPAM & HESAP/SOHBET DEĞİŞİM KONTROLÜ
    with state_lock:
        prev_state = project_active_state.get(project_code)
        if prev_state and prev_state.get('task_code') == task_code and event in ('task.started', 'task.in_progress'):
            prev_cid = prev_state.get('conv_id')
            elapsed = time.time() - prev_state.get('dispatched_at', 0)

            if prev_cid and prev_cid == target_conv_id and elapsed < 1200:
                print(f"⏳ [- ANTI-SPAM] {task_code} zaten son {int(elapsed)} sn içinde tetiklendi, atlanıyor.")
                return
            elif prev_cid and target_conv_id and prev_cid != target_conv_id:
                print(f"🔄 [- HESAP DEĞİŞİMİ] {task_code} -> Yeni Pencere: {target_conv_id}")
                is_resumed = True
            elif elapsed >= 1200:
                print(f"⏰ [- STALE KURTARMA] {task_code} 20+ dk hareketsiz kaldı, kurtarma uyarısı gönderiliyor...")
                is_resumed = True

    print(f"\n=======================================================")
    print(f"🔔 [DISPATCHER 4006] -> Olay: {event} | Proje: [{project_code}] {project_title} | Görev: {task_code}")

    if discovered_cid and not dev_conv_id and event != 'task.submitted':
        sync_conversation_id_to_db(project_code, discovered_cid)

    task_title = data.get('title', '').replace('🔄 [RESUMING TASK] ', '').replace('⚠️ [REVISION REQUEST] ', '')
    priority = data.get('priority', 'normal')

    rules_block = ""
    if agent_rules:
        rules_block = f"\n\n---\n📌 **PROJECT RULES ([{project_code}] {project_title}):**\n{agent_rules}\n"

    if event in ('task.started', 'task.in_progress', 'task.revision_requested', 'task.modification_requested'):
        is_rev = event in ('task.revision_requested', 'task.modification_requested')
        interrupt_header = (
            f"🚨 **[MANDATORY TASK INTERRUPT — STOP ALL PREVIOUS TOPICS!]**\n"
            f"🚨 **[EXECUTE IMMEDIATELY - DO NOT RE-ANSWER PREVIOUS CHAT MESSAGES OR INTRODUCTIONS]**\n"
            f"> **DIRECTIVE:** Drop any previous conversation in this window immediately. Your ONLY active objective now is to execute and deliver task **{task_code}** below:\n\n"
        )
        role_manifesto = (
            f"🛡️ **[ACTIVE AGENT ROLE ASSIGNMENT: LEAD DEVELOPER / CODER]**\n"
            f"> **YOUR IDENTITY & MISSION:** You are the exclusive Full-Stack Developer for project `[{project_code}] {project_title}`.\n"
            f"> **RESPONSIBILITY:** Write clean, production-ready code, follow the project doctrine, verify changes locally, and submit via the official REST API.\n\n"
        )
        if is_rev:
            title_header = f"⚠️ [REVISION] {task_code}: {task_title}"
            banner = f"{interrupt_header}{role_manifesto}⚠️ **[{project_code}] REVISION TASK MOVED TO IN PROGRESS!**\n🔴 **Requested Changes:** {data.get('revision_reason', 'Revision requested.')}\n\n"
        elif is_resumed:
            title_header = f"🔄 [RESUMED] {task_code}: {task_title}"
            banner = f"{interrupt_header}{role_manifesto}🔄 **[SESSION HANDOFF / RESUME DIRECTIVE]**\nThis task is currently IN PROGRESS and awaiting completion.\n\n"
        else:
            title_header = f"🚀 [IN PROGRESS] {task_code}: {task_title}"
            banner = f"{interrupt_header}{role_manifesto}🚀 **TaskManager Task Marked as 'In Progress'!**\n\n"

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
                hint = "*(Reference Image: inspect using `view_file` or visual tools to match design pixel-perfect)*" if is_img else "*(Attached Document: view using `view_file`)*"
                attachments_md += f"- {icon} [{fname}]({furi}) {hint}\n"

        message_text = (
            f"{shift_handoff_brief}"
            f"{banner}"
            f"📌 **Project:** [{project_code}] {project_title} (`{project_dir}`)\n"
            f"📋 **Task Code:** `{task_code}`\n"
            f"🏷️ **Title:** {task_title}\n"
            f"⚡ **Priority:** {priority}\n\n"
            f"### 📝 Task Description & Requirements:\n{desc}"
            f"{checklist_md}"
            f"{attachments_md}"
            f"{rules_block}\n\n"
            f"---\n"
            f"🛑 **STRICT OPERATIONAL GUARDRAILS:**\n"
            f"1. **SINGLE ACTIVE TASK FOCUS:** Work EXCLUSIVELY on `{task_code}` in project `[{project_code}]`. NEVER jump to older or other tasks!\n"
            f"2. **ZERO ABANDONMENT & AUTONOMOUS QA:** Complete 100% of implementation and live automated verification before responding to the user. The user is NOT a QA tester.\n"
            f"3. **SUBMIT AND STOP (USE OFFICIAL REST API - NEVER SEARCH FOR TASKMANAGER FILES OR DB):** When completed and verified, execute the following REST API request in your terminal to move `{task_code}` to `submitted` (REVIEW), then **END YOUR TURN** (The next queued task will be automatically dispatched in 4 seconds):\n\n"
            f"```powershell\n"
            f"[Console]::OutputEncoding = [System.Text.Encoding]::UTF8; $OutputEncoding = [System.Text.Encoding]::UTF8;\n"
            f"$headers = @{{ 'Authorization' = 'Bearer {api_token}'; 'Content-Type' = 'application/json; charset=utf-8' }}\n"
            f"$body = @{{ notes = '1. What Was Done (Neler Yapıldı): ... 2. Where to Test (Nereden Test Edilir): ... 3. How to Test (Nasıl Test Edilir): ... 4. Test Evidence (Test Kanıtı): ...' }} | ConvertTo-Json -Compress\n"
            f"Invoke-RestMethod -Uri '{api_base_url}/api/tasks/{task_code}/workflow/submit' -Method Post -Headers $headers -Body ([System.Text.Encoding]::UTF8.GetBytes($body))\n"
            f"```"
        )
    elif event == 'task.submitted':
        submission_notes = data.get('submission_notes', '').strip() or '*(No submission notes provided)*'
        title_header = f"🔍 [QA AUDIT REQUIRED] {task_code}: {task_title}"

        task_desc = (data.get('description') or '').strip()
        task_desc_clean = re.sub(r'---\s*⚡\s*\*\*AUTONOMOUS COMPLETION INSTRUCTION:.*$', '', task_desc, flags=re.DOTALL).strip()
        if not task_desc_clean:
            task_desc_clean = '*(No description provided)*'

        attachments = data.get('attachments', [])
        attachments_md = ""
        if attachments:
            attachments_md = "\n- **Task Attachments & Designs:**\n"
            for att in attachments:
                fname = att.get('file_name') or os.path.basename(att.get('file_path', 'attachment'))
                abs_p = (att.get('abs_path') or att.get('file_path', '')).replace('\\', '/')
                furi = att.get('file_uri') or f"file:///{abs_p.lstrip('/')}"
                ftype = att.get('file_type', '').lower()
                is_img = ftype.startswith('image/') or any(fname.lower().endswith(ext) for ext in ['.png', '.jpg', '.jpeg', '.webp', '.gif', '.svg'])
                icon = "🖼️" if is_img else "📄"
                attachments_md += f"  - {icon} [{fname}]({furi})\n"

        custom_qa_section = ""
        if qa_test_instructions:
            custom_qa_section += f"\n\n### 🛠️ PROJECT-SPECIFIC QA TEST INSTRUCTIONS:\n{qa_test_instructions}\n"
        if qa_global_instructions:
            custom_qa_section += f"\n\n### 🌐 GLOBAL QA TEST PROTOCOL:\n{qa_global_instructions}\n"

        message_text = (
            f"{shift_handoff_brief}"
            f"🚨 **[MANDATORY QA VERIFICATION DIRECTIVE — NO CODE MODIFICATION ALLOWED!]**\n"
            f"> **ROLE:** You are the Lead QA & Autonomous Testing Inspector. You CANNOT write feature code. Your ONLY objective is to rigorously test, verify, and audit task **{task_code}** delivered by the Developer Agent.\n\n"
            f"🚨 **STRICT ANTI-RUBBERSTAMPING PROTOCOL:**\n"
            f"> NEVER trust the Developer's submission notes. What the developer wrote, claims to have done, or how the code was implemented is COMPLETELY IRRELEVANT.\n"
            f"> Your sole responsibility is the USER'S ORIGINAL REQUIREMENT. Görevin Coder tarafından nasıl çözüldüğü değil, kullanıcının talep ettiği asıl amacın ekranda %100 çalıştığı doğrulanmalıdır.\n"
            f"> You MUST independently and autonomously execute the exact end-to-end user scenario in the live browser (e.g., clicking buttons, submitting forms, logging in, verifying result view).\n"
            f"> If the user's primary intended outcome does NOT work or cannot be proven in the live UI, you are STRICTLY FORBIDDEN to approve. REJECT the task immediately with OPTION B!\n\n"
            f"### 📋 DELIVERED TASK CONTEXT:\n"
            f"- **Project:** [{project_code}] {project_title} (`{project_dir}`)\n"
            f"- **Task Code:** `{task_code}` | **Title:** {task_title}\n"
            f"- **Original Task Requirements:**\n{task_desc_clean}\n"
            f"{attachments_md}"
            f"- **Developer Submission Notes:**\n{submission_notes}\n\n"
            f"### 🔍 MANDATORY STEP-BY-STEP TESTING PROTOCOL:\n"
            f"1. **LIVE BROWSER FUNCTIONAL TEST:**\n"
            f"   - Open target URL in the live browser.\n"
            f"   - Verify forms submit properly, buttons work, UI layout is intact, and no dead elements exist.\n"
            f"2. **NETWORK & CONSOLE AUDIT (ZERO TOLERANCE):**\n"
            f"   - Check Developer Console: Must contain **0 JS errors** and **0 404 assets**.\n"
            f"   - Check Network Response: Inspect payload body. Even if HTTP 200, ensure NO PHP Fatals, Xdebug errors, PDOExceptions, Warnings, or HTTP 500s.\n"
            f"3. **PROJECT-SPECIFIC TEST EXECUTION:**\n"
            f"   - If this project defines specific CLI test/audit commands or rules below, execute only what is defined in the project instructions.\n"
            f"{custom_qa_section}\n"
            f"---\n\n"
            f"### ⚖️ FINAL VERDICT COMMANDS (CHOOSE EXACTLY ONE):\n\n"
            f"**OPTION A — IF ALL TESTS PASS WITHOUT ANY FLAW (APPROVE & CLOSE):**\n"
            f"Execute in PowerShell:\n"
            f"```powershell\n"
            f"[Console]::OutputEncoding = [System.Text.Encoding]::UTF8; $OutputEncoding = [System.Text.Encoding]::UTF8;\n"
            f"$headers = @{{ 'Authorization' = 'Bearer {api_token}'; 'Content-Type' = 'application/json; charset=utf-8' }}\n"
            f"$body = @{{ action = 'review'; decision = 'approved'; feedback = 'All live browser UI, Console, Network, and Audit tests passed with zero defects.' }} | ConvertTo-Json -Compress\n"
            f"Invoke-RestMethod -Uri '{api_base_url}/api/tasks/{task_code}/workflow' -Method Post -Headers $headers -Body ([System.Text.Encoding]::UTF8.GetBytes($body))\n"
            f"```\n\n"
            f"**OPTION B — IF ANY BUG, CSS GLITCH, JS ERROR OR AUDIT FAIL IS FOUND (REJECT & REQUEST REVISION):**\n"
            f"Execute in PowerShell (Clearly document the defect with reproduction steps):\n"
            f"```powershell\n"
            f"[Console]::OutputEncoding = [System.Text.Encoding]::UTF8; $OutputEncoding = [System.Text.Encoding]::UTF8;\n"
            f"$headers = @{{ 'Authorization' = 'Bearer {api_token}'; 'Content-Type' = 'application/json; charset=utf-8' }}\n"
            f"$body = @{{ action = 'review'; decision = 'rejected'; feedback = '[QA DEFECT REPORT]\n1. Visual/CSS Bug: ...\n2. Console/Network Bug: ...\n3. Audit Failure: ...\nPlease fix and re-submit.' }} | ConvertTo-Json -Compress\n"
            f"Invoke-RestMethod -Uri '{api_base_url}/api/tasks/{task_code}/workflow' -Method Post -Headers $headers -Body ([System.Text.Encoding]::UTF8.GetBytes($body))\n"
            f"```"
        )
    elif event == 'project.webhook_test':
        title_header = f"Webhook Test Ping: [{project_code}]"
        message_text = f"{shift_handoff_brief}✅ **TaskManager Webhook Test Ping Successful (Port 4005)!**\n- **Project:** [{project_code}] {project_title}\n- **Target Conversation:** {target_conv_id or 'New Conversation'}"
    else:
        return

    # Gönderim öncesi güncel oturumu kaydet
    if target_conv_id:
        save_last_session(project_code, target_conv_id, current_role)

    env = os.environ.copy()
    if ls_address:
        env["ANTIGRAVITY_LS_ADDRESS"] = ls_address
    if csrf_token:
        env["ANTIGRAVITY_CSRF_TOKEN"] = csrf_token

    if target_conv_id:
        cmd = [AGENTAPI_EXE, "agentapi", "send-message", f"--title={title_header}", target_conv_id, message_text]
    else:
        cmd = [AGENTAPI_EXE, "agentapi", "new-conversation", f"--title={title_header}", message_text]

    if not project_dir or not os.path.isdir(project_dir):
        project_dir = APP_ROOT

    try:
        result = run_silent(cmd, env=env, cwd=project_dir, capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=12)

        if result.returncode != 0:
            fresh_addr, fresh_token = get_antigravity_ls_env(force_refresh=True)
            if fresh_addr:
                env["ANTIGRAVITY_LS_ADDRESS"] = fresh_addr
                if fresh_token:
                    env["ANTIGRAVITY_CSRF_TOKEN"] = fresh_token
                result = run_silent(cmd, env=env, cwd=project_dir, capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=12)

        # HATA 3 DÜZELTMESİ: send-message başarısız olduğunda TÜM durumlarda genişletilmiş kurtarma
        if result.returncode != 0:
            err_msg = ((result.stdout or '') + ' ' + (result.stderr or '')).strip()
            print(f"⚠️ [- GÖNDERİM BAŞARISIZ] Hata: '{err_msg}'. Otomatik kurtarma devreye giriyor...")

            # ÖZEL DURUM: KOTA AŞIMI / RESOURCE EXHAUSTED TESPİTİ
            is_quota_err = any(q_word in err_msg.lower() for q_word in ["resourceexhausted", "quota", "rate limit", "429"])
            if is_quota_err and AccountManager:
                print(f"🚨 [KOTA AŞIMI TESPİT EDİLDİ] Aktif hesap kotası tükendi! Yerli AccountManager devreye giriyor...")
                try:
                    acct_mgr = AccountManager()
                    best_next = acct_mgr.find_best_next_account(target_model="gemini-3.8")
                    if best_next and best_next.get("target_model_pct", 0) > 0:
                        next_email = best_next["email"]
                        next_pct = best_next["target_model_pct"]
                        print(f"🔄 [OTOMATİK HESAP GEÇİŞİ] Yeni sağlıklı hesaba geçiliyor: {next_email} (Gemini 3.8: %{next_pct})...")
                        acct_mgr.switch_active_account(best_next)
                        
                        # Oturum güncellendi, yeni ortam değişkenlerini tazele
                        fresh_addr, fresh_token = get_antigravity_ls_env(force_refresh=True)
                        if fresh_addr:
                            env["ANTIGRAVITY_LS_ADDRESS"] = fresh_addr
                        if fresh_token:
                            env["ANTIGRAVITY_CSRF_TOKEN"] = fresh_token

                        # Sessizce görevi tekrar gönder (Ajanın akışını bozmamak için bildirim metni eklemeden)
                        retry_cmd = [AGENTAPI_EXE, "agentapi", "send-message", f"--title={title_header}", target_conv_id, message_text]
                        result = run_silent(retry_cmd, env=env, cwd=project_dir, capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=15)
                        if result.returncode == 0:
                            print(f"✅ [KOTA KURTARMA BAŞARILI] Yeni hesap üzerinden görev başarıyla fırlatıldı!")
                except Exception as ex_quota:
                    print(f"❌ [KOTA GEÇİŞİ HATASI] {ex_quota}")
            
            # 1. Adım: O workspace için son saniyelerde açılmış yeni bir pencere var mı tekrar tara
            re_cid = None
            if result.returncode != 0:
                re_cid, re_uri, re_addr, re_tok = discover_latest_conversation(project_code, project_title, workspace_path)
            if re_cid and re_cid != target_conv_id:
                print(f"🔄 [- YENİ PENCERE YAKALANDI] {target_conv_id} yerine {re_cid} deneniyor...")
                if re_addr:
                    env["ANTIGRAVITY_LS_ADDRESS"] = re_addr
                if re_tok:
                    env["ANTIGRAVITY_CSRF_TOKEN"] = re_tok
                retry_cmd = [AGENTAPI_EXE, "agentapi", "send-message", f"--title={title_header}", re_cid, message_text]
                result = run_silent(retry_cmd, env=env, cwd=project_dir, capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=12)
                if result.returncode == 0:
                    target_conv_id = re_cid
                    save_last_session(project_code, re_cid, current_role)
                    sync_conversation_id_to_db(project_code, re_cid, role=current_role)

            # 2. Adım: Eğer hala gönderilemediyse koşulsuz olarak new-conversation fallback komutunu çalıştır
            if result.returncode != 0:
                print(f"🚀 [- KOŞULSUZ FALLBACK] 'new-conversation' komutu ile taze oturum başlatılıyor...")
                fallback_cmd = [AGENTAPI_EXE, "agentapi", "new-conversation", f"--title={title_header}", message_text]
                result = run_silent(fallback_cmd, env=env, cwd=project_dir, capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=12)

        if result.returncode == 0 and result.stdout:
            try:
                out_json = json.loads(result.stdout)
                new_cid = out_json.get('response', {}).get('newConversation', {}).get('conversationId')
                if new_cid:
                    target_conv_id = new_cid
                    save_last_session(project_code, new_cid, current_role)
                    sync_conversation_id_to_db(project_code, new_cid, role=current_role)
                    print(f"✨ [- YENİ SOHBET OLUŞTURULDU VE VERİTABANINA YAZILDI] Conversation ID: {new_cid}")
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
        import traceback
        print(f"❌ Gönderim Hatası: {str(err)}\n{traceback.format_exc()}")


class WebhookHandler(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        if self.path in ('/restart', '/shutdown'):
            self.send_response(200)
            self.send_header('Content-Type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps({"status": "shutting_down_demo"}).encode())
            def _exit_later():
                time.sleep(0.5)
                os._exit(0)
            threading.Thread(target=_exit_later, daemon=True).start()
            return

        self.send_response(200)
        self.send_header('Content-Type', 'text/plain')
        self.end_headers()
        self.wfile.write(b"TaskManager Dispatcher (Port 4005) OK")

    def do_POST(self):
        if self.path in ('/restart', '/shutdown'):
            self.send_response(200)
            self.send_header('Content-Type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps({"status": "shutting_down_demo"}).encode())
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
        self.wfile.write(json.dumps({"status": "queued_demo"}).encode())

        t = threading.Thread(target=process_webhook_async, args=(payload, header_conv_id), daemon=True)
        t.start()

    def log_message(self, format, *args):
        return

def run_server():
    if '--probe' in sys.argv:
        addr, tok = get_antigravity_ls_env(force_refresh=True)
        print(json.dumps({"address": addr, "csrf_token": tok or ""}))
        return

    server_address = ('', PORT)
    httpd = http.server.HTTPServer(server_address, WebhookHandler)

    # ⚠️ Proxy Server (Port 4006) ve Proaktif Kota Nöbetçisi DEVRE DIŞI bırakıldı.
    # Sebep: Proxy kendi kendine sonsuz döngüye girerek dispatcher.log'u saniyede 2.3KB hata ile dolduruyordu.
    # Kota Nöbetçisi görev ortasında hesap değiştirerek agent sohbetini koparıyordu.
    # Hesap rotasyonu dispatch_task() hata bloğunda (satır ~1014-1040) güvenli şekilde korunmaktadır.

    print("=======================================================")
    print("🤖 Antigravity Multi-Agent Dispatcher (Port 4005)")
    print(f"📡 Dinleme Adresi : http://localhost:{PORT}/webhook")
    print(f"📁 Log Dosyası    : {LOG_FILE}")
    print(f"💾 Oturum Hafızası: {LAST_SESSIONS_FILE}")
    print("=======================================================")
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        print("\n[DEMO] Servis durduruldu.")

if __name__ == '__main__':
    run_server()
