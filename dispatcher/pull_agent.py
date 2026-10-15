"""
TaskManager Live Server -> Local Antigravity Agent Pull Client (v1.0)

Bu servis, TaskManager canlı bir sunucuda (örn: https://siteniz.com) barındırıldığında
ve yerel bilgisayarınızda Cloudflare Tunnel kurmak istemediğinizde kullanılır.
Canlı sunucudaki REST API'yi periyodik olarak yoklar ve "Çalışılıyor" durumuna alınan
görevleri otomatik olarak yerel Antigravity pencerenize fırlatır.

Kullanım:
    python pull_agent.py --url https://siteniz.com/api/v1 --token tm_admin_talha_keskin_token_2026 --project 01
"""
import argparse
import time
import json
import urllib.request
import urllib.error
import sys
import os
import subprocess

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
sys.path.append(BASE_DIR)
from dispatcher import discover_latest_conversation, AGENTAPI_EXE, get_antigravity_ls_env, sync_conversation_id_to_db

def poll_live_tasks(api_url, token, project_code, interval=10):
    api_url = api_url.rstrip('/')
    headers = {
        "Authorization": f"Bearer {token}",
        "Content-Type": "application/json",
        "User-Agent": "TaskManager-Pull-Agent/3.0"
    }

    processed_tasks = set()
    print("=======================================================")
    print("🚀 TaskManager Canlı Sunucu Pull Ajanı (v3.0) Başlatıldı!")
    print(f"🌐 Hedef API       : {api_url}")
    print(f"📁 Proje Kodu      : {project_code}")
    print(f"⏱️ Yoklama Aralığı : Her {interval} saniyede bir")
    print("🔒 CSRF & Otomatik Keşif: Aktif")
    print("=======================================================")

    while True:
        try:
            url = f"{api_url}/projects/{project_code}/tasks?status=in_progress"
            req = urllib.request.Request(url, headers=headers, method='GET')
            try:
                with urllib.request.urlopen(req, timeout=10) as response:
                    status_code = response.getcode()
                    body = response.read().decode('utf-8')
            except urllib.error.HTTPError as http_err:
                status_code = http_err.code
                body = http_err.read().decode('utf-8')
            except Exception as conn_err:
                status_code = 0
                body = str(conn_err)

            if status_code == 200:
                resp_json = json.loads(body)
                data = resp_json.get('data', [])
                for task in data:
                    task_code = task.get('task_code')
                    if task_code and task_code not in processed_tasks:
                        print(f"🎯 Yeni 'Çalışılıyor' Görevi Tespit Edildi: [{task_code}] {task.get('title')}")
                        
                        # 1. Yerel açık Antigravity penceresini ve port/CSRF token ikilisini bul
                        ls_address, csrf_token = get_antigravity_ls_env()
                        cid, uri = discover_latest_conversation(project_code, task.get('title', ''), workspace_path=task.get('workspace_path', ''))
                        
                        title_header = f"🚀 [LIVE SERVER] {task_code}: {task.get('title')}"
                        desc = task.get('description', '') or '*(No description provided)*'
                        agent_rules = (task.get('agent_rules') or '').strip()
                        rules_block = ""
                        if agent_rules:
                            rules_block = (
                                f"\n\n---\n"
                                f"📌 **PROJECT RULES ([{project_code}]):**\n"
                                f"{agent_rules}\n"
                            )
                        msg = (
                            f"🚀 **Task Pulled from Live Server!**\n\n"
                            f"📌 **Project:** [{project_code}]\n"
                            f"📋 **Task Code:** {task_code}\n"
                            f"🏷️ **Title:** {task.get('title')}\n"
                            f"⚡ **Priority:** {task.get('priority', 'normal')}\n\n"
                            f"### 📝 Task Description & Requirements:\n{desc}"
                            f"{rules_block}\n\n"
                            f"---\n"
                            f"🛑 **STRICT OPERATIONAL GUARDRAILS:**\n"
                            f"1. **SINGLE ACTIVE TASK FOCUS:** Work EXCLUSIVELY on `{task_code}`. Do not jump to other tasks.\n"
                            f"2. **AUTONOMOUS QA:** Verify all changes with autonomous tests before submitting."
                        )

                        env = os.environ.copy()
                        if ls_address:
                            env["ANTIGRAVITY_LS_ADDRESS"] = ls_address
                        if csrf_token:
                            env["ANTIGRAVITY_CSRF_TOKEN"] = csrf_token

                        if cid:
                            cmd = [AGENTAPI_EXE, "agentapi", "send-message", f"--title={title_header}", cid, msg]
                        else:
                            cmd = [AGENTAPI_EXE, "agentapi", "new-conversation", f"--title={title_header}", msg]

                        run_res = subprocess.run(cmd, env=env, capture_output=True, text=True, timeout=10)
                        
                        # Trajectory not found fallback
                        combined_err = ((run_res.stdout or '') + ' ' + (run_res.stderr or '')).lower()
                        if run_res.returncode != 0 and 'trajectory not found' in combined_err:
                            fallback_cmd = [AGENTAPI_EXE, "agentapi", "new-conversation", f"--title={title_header}", msg]
                            run_res = subprocess.run(fallback_cmd, env=env, capture_output=True, text=True, timeout=10)

                        if run_res.returncode == 0:
                            print(f"✅ Antigravity penceresine ({cid or 'YENİ SOHBET'}) başarıyla iletildi!")
                            processed_tasks.add(task_code)
                        else:
                            print(f"⚠️ İletim başarısız: {run_res.stderr.strip() if run_res.stderr else run_res.stdout.strip()}")
            else:
                print(f"⚠️ API Yanıt Kodu: {status_code}")
        except Exception as e:
            print(f"⚠️ Hata: {str(e)}")

        time.sleep(interval)

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description="TaskManager Live Pull Agent v3.0")
    parser.add_argument("--url", default="http://localhost/taskmanager/api/v1", help="API Base URL")
    parser.add_argument("--token", default="tm_admin_talha_keskin_token_2026", help="Admin Bearer Token")
    parser.add_argument("--project", default="01", help="Project Code")
    parser.add_argument("--interval", type=int, default=10, help="Poll interval in seconds")
    args = parser.parse_args()

    poll_live_tasks(args.url, args.token, args.project, args.interval)
