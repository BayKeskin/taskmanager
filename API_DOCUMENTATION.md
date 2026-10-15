# ⚡ TaskManager Enterprise REST API & Yapay Zeka (AI Agent) Dokümantasyonu

> **Sürüm:** `v1.0.0`  
> **Temel URL (Base URL):** `http://localhost/taskmanager/api/v1`  
> **Protokol:** REST / JSON (UTF-8)  
> **Yetkilendirme:** Bearer Token (`Authorization: Bearer <token>`) veya `X-API-Key`  

Bu REST API, TaskManager arayüzünde bir kullanıcının veya yöneticinin yapabildiği **tüm işlemleri** (Proje yönetimi, Görev oluşturma, Kanban durum geçişleri, Çoklu çalışan atama, Kontrol listeleri (Checklist), Yorumlaşma, Teslimat onayı/reddi ve Döküman yönetimi) harici sistemlere, insan geliştiricilere ve özellikle **Yapay Zeka Ajanlarına (Antigravity Agent, Claude, GPT, vb.)** sunmak üzere tasarlanmıştır.

---

## 🎯 Yapay Zeka (AI Agent) Entegrasyon Senaryosu

### Görevlendirme Mantığı
Bir yapay zekaya veya ekibe şu talimat verildiğinde:
> *"A projesi senin alanın. Bu projede yapılacak (pending) durumundaki her görevi analiz et, kabul et, geliştir, test et ve yönetici onayına teslim et."*

Ajanın izleyeceği standart 5 adımlı otonom iş akışı şöyledir:

```mermaid
graph TD
    A[1. Projedeki Görevleri Listele] -->|GET /projects/ETC/tasks?status=pending| B[2. İlgili Görevi Seç & İşe Başla]
    B -->|POST /tasks/ETC-101/workflow/accept| C[3. Geliştirme & Checklist Tamamlama]
    C -->|PUT /tasks/ETC-101/checklists/1| D[4. Yorum & İlerleme Bildirimi]
    D -->|POST /tasks/ETC-101/comments| E[5. Yöneticiye Teslim Et]
    E -->|POST /tasks/ETC-101/workflow/submit| F[Yönetici İncelemesi / Tamamlandı]
```

---

## 🔐 Kimlik Doğrulama (Authentication & Zero-Config Architecture)

API çağrılarında kimlik doğrulama için **Bearer Token** kullanılır:

```http
Authorization: Bearer <API_BEARER_TOKEN>
```
Alternatif olarak `X-API-Key: <API_BEARER_TOKEN>` başlığı veya URL sorgu parametresi `?api_token=<API_BEARER_TOKEN>` kullanılabilir.

### 🚀 Sıfır Sabit Kod & Kur-Çalıştır (Zero-Config / White-Label) Mimarisi:
* **Dinamik Base URL:** İstek yapılan sunucunun `settings.siteurl` veya `Host` başlığı üzerinden anında çözülür. Sunucu veya alan adı değiştiğinde hiçbir kod dosyasına dokunulması gerekmez.
* **Dinamik Yönetici Tokenı:** Sistem, veritabanındaki aktif yöneticinin (`role = 'admin' AND status = 'active'`) güncel `api_token` anahtarını kullanır.
* **Otomatik Token Üretimi (Self-Healing):** Sistem yeni bir sunucuya kurulduğunda veya admin token'ı henüz oluşturulmamışsa, sistem hata vermez; ilk istekte anında `tm_live_<hash>` formatında benzersiz bir token üreterek veritabanına kaydeder.
* **Token Yenileme:** Güvenlik amacıyla token yenilendiğinde (`POST /api/auth/regenerate-token`), sonraki tüm görev bildirimlerinde ve ajan çağrılarında yeni token otomatik olarak kullanılır.

> *Not: Yeni ekip üyeleri ve projeler doğrudan API üzerinden (`POST /api/v1/users` ve `POST /api/v1/projects`) veya web arayüzünden eklenebilir.*

---

## 🌐 Çok Dilli API Desteği (Internationalization / I18n)

TaskManager REST API'si tüm durum yanıtlarını, başarı mesajlarını, doğrulama hatalarını ve sistem bildirimlerini çok dilli (**Türkçe `tr`**, **İngilizce `en`**, **Almanca `de`**) olarak sunar. İstemciler ve yapay zeka ajanları tercih ettikleri dili aşağıdaki 4 farklı yöntemle belirtebilir:

### 1. Yöntem: URL Sorgu Parametresi (`?lang=`)
İstek URL'sinin sonuna `lang`, `locale` veya `set_lang` parametresi ekleyebilirsiniz:
```http
GET /api/v1/projects?lang=en
GET /api/v1/tasks/ETC-101?lang=de
```

### 2. Yöntem: HTTP Başlıkları (Headers)
Standart `Accept-Language` başlığı veya özel `X-Language` / `X-Locale` başlığı gönderebilirsiniz:
```http
Accept-Language: en-US,en;q=0.9
# veya
X-Language: de
```

### 3. Yöntem: JSON İstek Gövdesi (Request Body)
`POST`, `PUT`, `PATCH` isteklerinde JSON gövdesine `lang` parametresi ekleyebilirsiniz:
```json
{
  "lang": "en",
  "title": "New Enterprise Task",
  "priority": "high"
}
```

### 4. Yöntem: Kullanıcı Profil Tercihi (Otomatik)
Herhangi bir dil parametresi gönderilmediğinde, Bearer Token sahibi kullanıcının profilinde kayıtlı dil tercihi (`users.language`) veya sistem varsayılan dili (`settings.default_language`) otomatik olarak geçerli olur.

### Çok Dilli Yanıt Yapısı & `locale` Alanı:
Tüm API yanıtlarında aktif dil kodu `locale` anahtarında döner:
```json
{
  "success": true,
  "message": "Projects listed.",
  "locale": "en",
  "data": [ ... ]
}
```

Hata durumunda:
```json
{
  "success": false,
  "error": "Task title is required.",
  "locale": "en"
}
```

---

## 📌 REST API Endpoint Referansı

### 1. Kimlik Doğrulama & Oturum (`/auth`)

#### 1.1 Giriş Yap ve Token Al
```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "iletisimtktalha@gmail.com",
  "password": "145236"
}
```
**Yanıt (200 OK):**
```json
{
  "success": true,
  "message": "Giriş başarılı.",
  "data": {
    "token": "tm_admin_talha_keskin_token_2026",
    "user": {
      "id": 1,
      "name": "Talha Keskin",
      "email": "iletisimtktalha@gmail.com",
      "role": "admin"
    }
  }
}
```

#### 1.2 Aktif Kullanıcı Bilgileri
```http
GET /api/v1/auth/me
Authorization: Bearer tm_admin_talha_keskin_token_2026
```

---

### 2. Projeler (Spaces) (`/projects`)

#### 2.1 Tüm Projeleri Listele
```http
GET /api/v1/projects?status=active
Authorization: Bearer tm_admin_talha_keskin_token_2026
```
**Yanıt Örneği:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "project_code": "ETC",
      "title": "E-Ticaret Platformu Yenileme (v3.0)",
      "color": "#7B68EE",
      "icon": "🛍️",
      "status": "active",
      "stats": {
        "total_tasks": 4,
        "pending": 1,
        "in_progress": 1,
        "modification_requested": 1,
        "under_review": 0,
        "completed": 1
      }
    }
  ]
}
```

#### 2.2 Projenin Görevlerini Getir (AI Agent İçin Temel Çağrı)
Belirli bir projedeki görevleri duruma, önceliğe veya atanan kişiye göre filtreler:
```http
GET /api/v1/projects/ETC/tasks?status=pending
Authorization: Bearer tm_admin_talha_keskin_token_2026
```
*Parametreler:*
- `status`: `pending` (veya `todo`), `in_progress`, `modification_requested`, `submitted`, `completed`, `all`
- `priority`: `urgent`, `high`, `normal`, `low`
- `assigned_to`: `me` veya kullanıcı ID

#### 2.3 Yeni Proje Oluştur
```http
POST /api/v1/projects
Content-Type: application/json

{
  "title": "Mobil Sadakat v2",
  "project_code": "MSD",
  "description": "Mobil kupon ve sadakat altyapısı",
  "color": "#06D6A0",
  "icon": "🎁"
}
```

---

### 3. Görevler & Kanban İş Akışları (`/tasks`)

#### 3.1 Genel Görev Arama & Filtreleme
```http
GET /api/v1/tasks?project=ETC&status=in_progress&priority=urgent
Authorization: Bearer tm_admin_talha_keskin_token_2026
```

#### 3.2 Görev Detayı (Checklistler, Alt Görevler, Yorumlar Dahil)
```http
GET /api/v1/tasks/ETC-101
Authorization: Bearer tm_admin_talha_keskin_token_2026
```

#### 3.3 Yeni Görev Oluştur
```http
POST /api/v1/tasks
Content-Type: application/json

{
  "title": "Webhook Dinleyici Servisinin Kodlanması",
  "project": "ETC",
  "description": "Ödeme sağlayıcı webhook isteklerini karşılayan endpoint.",
  "priority": "high",
  "deadline": "2026-10-20",
  "time_estimate": "6h",
  "assignee_ids": [1, 3],
  "checklists": [
    "HMAC SHA256 imza doğrulaması",
    "Idempotency anahtarı kontrolü",
    "Slack bildirim tetikleyicisi"
  ]
}
```

---

### 4. Kanban Durum ve İş Akışı Geçişleri (`/tasks/{code}/workflow`)

#### 4.1 Görevi Kabul Et & İşe Başla (`/accept`)
Görevin durumunu doğrudan **`in_progress` (Çalışılıyor)** yapar ve aktivite kaydı oluşturur:
```http
POST /api/v1/tasks/ETC-101/workflow/accept
Authorization: Bearer tm_admin_talha_keskin_token_2026
```

#### 4.2 Doğrudan Durum Değiştir (`/status`)
Kanban panosunda sürükle-bırak hareketine eşdeğerdir:
```http
POST /api/v1/tasks/ETC-101/workflow/status
Content-Type: application/json

{
  "status": "in_progress",
  "note": "İşlemlere başlandı."
}
```
*Geçerli Durumlar:* `assigned`, `in_progress`, `modification_requested`, `submitted`, `completed`

#### 4.3 Revizyon Talep Et (`/request-modification`)
Çalışan veya yönetici eksik/kusur bildiriminde bulunur:
```http
POST /api/v1/tasks/ETC-101/workflow/request-modification
Content-Type: application/json

{
  "reason": "Mobil ekranlarda buton hizalaması taşma yapıyor."
}
```

#### 4.4 Görevi Teslim Et (`/submit`)
Çalışan görevi tamamladığında inceleme için notlar ve link ile teslim eder:
```http
POST /api/v1/tasks/ETC-101/workflow/submit
Content-Type: application/json

{
  "notes": "Tüm testler ve kod incelemesi tamamlandı.",
  "attachment_url": "https://github.com/firma/proje/pull/42"
}
```

#### 4.5 Teslimatı İncele & Onayla/Reddet (`/review`) *(Yönetici)*
```http
POST /api/v1/tasks/ETC-101/workflow/review
Content-Type: application/json

{
  "decision": "approved",
  "feedback": "Test edildi, canlıya alınmaya hazır."
}
```

---

### 5. Kontrol Listeleri (Checklist) (`/tasks/{code}/checklists`)

#### 5.1 Madde Ekle
```http
POST /api/v1/tasks/ETC-101/checklists
Content-Type: application/json

{
  "title": "Birim testlerinin (PHPUnit) çalıştırılması"
}
```

#### 5.2 Maddeyi Tamamla / Güncelle
```http
PUT /api/v1/tasks/ETC-101/checklists/2
Content-Type: application/json

{
  "is_completed": 1
}
```

---

### 6. Görev İçi Yorumlar & Tartışma (`/tasks/{code}/comments`)

```http
POST /api/v1/tasks/ETC-101/comments
Content-Type: application/json

{
  "comment": "Sandbox ortamında 3D secure doğrulama senaryosu başarıyla test edildi."
}
```

---

### 7. Çoklu Çalışan Atama (`/tasks/{code}/assignees`)

```http
POST /api/v1/tasks/ETC-101/assignees
Content-Type: application/json

{
  "user_id": 2
}
```

---

### 8. Dökümanlar & Bilgi Bankası (`/docs` & `/projects/{code}/docs`)

Yapay Zeka (AI Agent) veya geliştiricilerin projeye ait sunucu bilgileri, FTP/SSH erişimleri, veritabanı şifreleri, API anahtarları veya mimari dökümantasyonları okuyabilmesi ve ekleyebilmesi için tasarlanmıştır.

> **💡 AI Agent İpucu:** `GET /api/v1/projects/{code}` çağrısı yaptığınızda veya `GET /api/v1/tasks/{code}` detayını aldığınızda projenin tüm dökümanları otomatik olarak (`docs` veya `project_docs` dizisinde) hazır gelir! Ayrı bir çağrı yapmadan doğrudan FTP ve sunucu bilgilerine erişebilirsiniz.

#### 8.1 Bir Projenin Dökümanlarını Listele
```http
GET /api/v1/projects/ETC/docs
Authorization: Bearer tm_admin_talha_keskin_token_2026
```
veya:
```http
GET /api/v1/docs?project=ETC
Authorization: Bearer tm_admin_talha_keskin_token_2026
```

#### 8.2 Projeye Örnek FTP / Sunucu Bilgisi Dökümanı Ekleme
```http
POST /api/v1/projects/ETC/docs
Content-Type: application/json
Authorization: Bearer tm_admin_talha_keskin_token_2026

{
  "title": "FTP ve Sunucu Erişim Bilgileri",
  "icon": "🔑",
  "content": "### Canlı Sunucu FTP Bilgileri\n- **Host:** ftp.eticaret.example.com\n- **Port:** 21\n- **Kullanıcı Adı:** deploy_user\n- **Şifre:** Sup3rSecr3t!2026\n- **Dizin:** /var/www/html/public\n\n### Veritabanı Bilgileri\n- **DB Host:** 127.0.0.1:3306\n- **DB Adı:** etc_prod_db\n- **DB Kullanıcı:** etc_user"
}
```

#### 8.3 Tekil Döküman Detayı / Güncelleme / Silme
```http
GET /api/v1/docs/1
PUT /api/v1/docs/1
DELETE /api/v1/docs/1
```

---

### 9. Proje Bazlı Yapay Zeka (AI Agent) Webhook Tetikleyicisi & Conversation ID

Farklı projeler için ayrı ayrı açılmış Antigravity / AI sohbet pencerelerinin **yalnızca ilgili olanının uyanması** için geliştirilmiştir.

#### 9.1 Projeye Özel Webhook & Conversation ID Tanımlama
```http
PUT /api/v1/projects/01
Content-Type: application/json
Authorization: Bearer tm_admin_talha_keskin_token_2026

{
  "webhook_url": "http://localhost:4000/webhook",
  "workspace_path": "c:/wamp64/www/taskmanager",
  "agent_conversation_id": "auto"
}
```
> **💡 İpucu:** `agent_conversation_id` alanını `auto` yapabilir veya boş bırakabilirsiniz. Sistem, projenin `workspace_path` (fiziksel klasör) yoluna bağlı **en güncel açık sohbet penceresini milisaniyeler içinde dinamik olarak keşfeder**.

#### 9.2 Görev Oluştuğunda Hedef URL'ye Gönderilen Webhook Payloadı
Yeni bir görev eklendiğinde (`task.created`) veya durum değiştiğinde hedef adrese otomatik olarak HTTP POST fırlatılır:

```http
POST /webhook HTTP/1.1
Host: localhost:4000
Content-Type: application/json
X-TaskManager-Event: task.created
X-TaskManager-Project: 01
X-TaskManager-Conversation-ID: 760ae474-fe99-431f-93a8-927b31cb72a9

{
  "event": "task.created",
  "project_id": 1,
  "project_code": "01",
  "project_title": "Task Manager",
  "agent_conversation_id": "760ae474-fe99-431f-93a8-927b31cb72a9",
  "api_base_url": "http://localhost/taskmanager",
  "api_token": "tm_admin_talha_keskin_token_2026",
  "timestamp": "2026-10-12T14:40:00+03:00",
  "data": {
    "task_id": 15,
    "task_code": "01-102",
    "title": "Webhook Olay Tetikleyicisi",
    "priority": "high",
    "status": "assigned"
  }
}
```

#### 9.3 Otonom Ajanların Kullandığı Resmi Teslimat Standardı (PowerShell)
Ajanlar görev tamamlandığında yerel TaskManager dizinini veya veritabanını aramadan doğrudan HTTP REST API üzerinden şu komutla teslimat gerçekleştirir:
```powershell
$headers = @{ 'Authorization' = 'Bearer ' + $api_token; 'Content-Type' = 'application/json; charset=utf-8' }
$body = @{ 
    notes = '1. Neler Yapıldı: ... 2. Nereden Test Edilir: ... 3. Nasıl Test Edilir: ... 4. Test Kanıtı: ...';
    attachment_url = 'https://github.com/organization/repo/commit/abc123'
} | ConvertTo-Json -Compress

Invoke-RestMethod -Uri "$api_base_url/api/tasks/$task_code/workflow/submit" -Method Post -Headers $headers -Body ([System.Text.Encoding]::UTF8.GetBytes($body))
```

#### 9.4 Webhook Test Pingi Gönder
Yapılandırılmış bağlantıyı anında test etmek için:
```http
POST /api/v1/projects/01/webhook/test
Authorization: Bearer <API_BEARER_TOKEN>
```

#### 9.5 Son Webhook Çağrı Günlükleri (Audit Logs)
```http
GET /api/v1/webhooks/logs
Authorization: Bearer tm_admin_talha_keskin_token_2026
```

---

## 🐍 Örnek Python Scripti: Yapay Zeka Ajanı Otonom Görev Yürütme

```python
import json
import urllib.request

BASE_URL = "http://localhost/taskmanager/api/v1"
TOKEN = "YOUR_API_TOKEN"  # Webhook payloadından gelen api_token değeri
HEADERS = {
    "Authorization": f"Bearer {TOKEN}",
    "Content-Type": "application/json; charset=utf-8"
}

def api_call(path, method="GET", data=None):
    url = f"{BASE_URL}{path}"
    payload = json.dumps(data).encode("utf-8") if data else None
    req = urllib.request.Request(url, data=payload, headers=HEADERS, method=method)
    with urllib.request.urlopen(req) as resp:
        return json.loads(resp.read().decode("utf-8"))

# 1. ETC Projesindeki Bekleyen Görevleri Çek
res = api_call("/projects/ETC/tasks?status=pending")
tasks = res.get("data", [])

if not tasks:
    print("Bekleyen görev bulunamadı.")
    exit()

task = tasks[0]
task_code = task["task_code"]
print(f"🎯 Üzerinde çalışılacak görev: [{task_code}] {task['title']}")

# 2. Görevi Kabul Et (in_progress durumuna al)
api_call(f"/tasks/{task_code}/workflow/accept", method="POST")
print("⚙️ Görev durumu 'Çalışılıyor (in_progress)' yapıldı.")

# 3. Yorum Ekle
api_call(f"/tasks/{task_code}/comments", method="POST", data={
    "comment": "AI Agent: İlgili modül kodlandı ve doğrulandı."
})

# 4. Varsa Kontrol Listesi Maddesini Tamamla
if task.get("checklists"):
    ch_id = task["checklists"][0]["id"]
    api_call(f"/tasks/{task_code}/checklists/{ch_id}", method="PUT", data={"is_completed": 1})
    print(f"✅ Kontrol listesi maddesi tamamlandı (ID: {ch_id}).")

# 5. Görevi Teslim Et
api_call(f"/tasks/{task_code}/workflow/submit", method="POST", data={
    "notes": "İlgili görev yapay zeka ajanı tarafından eksiksiz geliştirildi.",
    "attachment_url": "https://github.com/proje/repo/pull/99"
})
print("🚀 Görev yönetici onayına teslim edildi!")
```

---

## 🛡️ 10. Otonom Ajan İstikrarı & Canlı Sunucu (Production) Mimarisi (v3.0)

### 10.1. Antigravity IDE 2.4+ CSRF Koruması & Dinamik Port Keşfi
* Antigravity IDE her başlatıldığında `language_server_windows_x64.exe` sürecini benzersiz bir `--csrf_token <UUID>` parametresiyle çalıştırır.
* Dispatcher v3.0 ve PHP Helper, PowerShell CIM sorgusuyla çalışan language_server sürecini tespit eder, komut satırından `--csrf_token` değerini ve `netstat` ile dinlenen portu (`ANTIGRAVITY_LS_ADDRESS`) dinamik olarak keşfeder ve `dispatcher/ls_env.json` dosyasına kaydeder.
* **Canlı Probe Testi:** Terminalde aşağıdaki komutu çalıştırarak aktif port ve CSRF token ikilisini anında görebilirsiniz:
  ```powershell
  python dispatcher/dispatcher.py --probe
  ```

### 10.2. 5 Farklı Google Hesabı Arasında Limit Rotasyonu
* Antigravity IDE üzerinde hesap limiti dolduğunda diğer Google hesabınıza geçtiğinizde, açık olan pencerenin `conversation_id` değeri ve language_server oturumu **asla değişmez**.
* İletişim işletim sistemi seviyesindeki yerel CLI/IPC (`language_server.exe agentapi`) üzerinden sağlandığı için Google hesap değişimleri görev akışını **kesintiye uğratmaz**.

### 10.3. Şişen Sohbetleri Silip Yeni Pencere Açma (Otomatik Keşif & Trajectory Recovery)
* Sohbet geçmişi uzayıp token maliyeti veya yanıt gecikmesi arttığında eski sohbeti silip temiz bir pencere açabilirsiniz.
* Sistem (`Helper::discoverLatestConversation` ve `dispatcher.py`), `C:\Users\DELL\.gemini\antigravity-ide\conversations\*.db` kayıtlarını değiştirilme tarihine göre sıralayarak ilgili projenin klasörüne (`workspace_path`) bağlı **en güncel açık sohbet penceresini milisaniyeler içinde yakalar**.
* Eğer hedef sohbet silinmişse (`trajectory not found`), sistem otomatik olarak `new-conversation` komutuyla IDE içinde yeni bir sohbet başlatır ve veritabanını günceller.

### 10.4. Çift Katmanlı Kendi Kendini Onaran Mimari (Self-Healing)
1. **Katman 1 (Otomatik Bekçi):** TaskManager (PHP), webhook fırlatmadan önce `127.0.0.1:4000` portunu yoklar. Eğer servis kapalıysa, arka planda `pythonw.exe dispatcher.py` sürecini sessizce ayağa kaldırır.
2. **Katman 2 (Doğrudan Yerel Fallback):** Eğer Port 4000 herhangi bir sebeple zaman aşımına uğrarsa veya kapalıysa, PHP doğrudan `language_server_windows_x64.exe` komutunu hem `ANTIGRAVITY_LS_ADDRESS` hem `ANTIGRAVITY_CSRF_TOKEN` ortam değişkenleriyle `proc_open` üzerinden çalıştırarak görevi ajana anında teslim eder (`dispatchDirectToAntigravity`).

### 10.5. Canlı Sunucuya Taşındığında Çalıştırma Modelleri (Production)
TaskManager canlı bir sunucuya (örneğin `https://siteniz.com` veya VPS) taşındığında, yerel bilgisayarınızdaki Antigravity ajanlarını çalıştırmak için 2 profesyonel model desteklenir:

#### Model A: Cloudflare Tunnel (Canlıdan Yerele Anlık Push - Önerilen)
1. Yerel bilgisayarınızda Cloudflare tünelini başlatın:
   ```powershell
   cloudflared tunnel --url http://localhost:4000
   ```
2. Canlıdaki TaskManager'a girin: *⚙️ Proje Ayarları -> Webhook URL* alanına size verilen güvenli HTTPS adresini (örnek: `https://agent.siteniz.com/webhook`) yazın.
3. Canlı panoda bir görev *Çalışılıyor* sütununa taşındığı anda tünel üzerinden yerel port 4000'e anlık POST iletilir; Dispatcher v3.0 dinamik CSRF token ve port ile açık Antigravity pencerenizi anında uyandırır.

#### Model B: Ters Yönlü Çekiş Servisi (Pull Agent v3.0 - Sıfır Tünel / Sıfır Port / Sıfır Bağımlılık)
1. Dışarıdan içeriye port açmak veya tünel kurmak istemiyorsanız, projedeki hazır çekiş servisini başlatın:
   ```powershell
   python dispatcher/pull_agent.py --url https://siteniz.com/taskmanager/api/v1 --token tm_admin_talha_keskin_token_2026 --project 01
   ```
2. Servis Python standart kütüphanesini (`urllib`) kullanır; `pip install requests` gerektirmez.
3. Canlı sunucuyu periyodik olarak sorgular, yeni *in_progress* görevi tespit ettiğinde yerel Antigravity pencerenizi uyarır.
4. Ajan işi bitirdiğinde doğrudan canlı sunucunun REST API'sine (`POST /tasks/{code}/workflow/submit`) teslim eder.

---

## 🌐 Etkileşimli Dokümantasyon Arayüzü
Sistemin arayüzünden doğrudan API testleri yapmak ve kod parçacıklarını kopyalamak için:  
👉 **[http://localhost/taskmanager/api-docs](http://localhost/taskmanager/api-docs)** sayfasını ziyaret edebilirsiniz.

