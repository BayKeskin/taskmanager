# 🚀 ÇİFT AJANLI (DEV + QA) OTONOM TEST VE DOĞRULAMA SİSTEMİ MİMARİ YOL HARİTASI

> **Doküman Türü:** Mimari Tasarım, Satır Satır Yol Haritası ve Geri Dönüşüm (Rollback) Güvencesi  
> **Tarih:** 21 Ekim 2026  
> **Temel Prensip:** "Sıfır Otomatik Port Tahmini" — Her iki ajanın (`dev_conversation_id` ve `qa_conversation_id`) kimliği veritabanında elle girilir veya ajanın ilk mesajıyla güncellenir. Sistemin geri kalanı %100 kararlı, geri alınabilir ve denetlenebilir çalışır.

---

## 📌 BÖLÜM 1: MEVCUT REVİZYON MEKANİZMASININ RÖNTGENİ (NASIL İŞLİYOR VE NEDEN BOZULDU?)

### 1.1 Kod Seviyesinde Mevcut Akış
Mevcut kod tabanımızda revizyon zinciri 4 ana dosya üzerinden yürür:
1. **Tetikleyici (`Modules/Board/Controllers/AjaxController.php` - Satır ~506):**
   - Müşteri veya Admin "Düzeltme İste" dediğinde `request_changes` case'i çalışır.
   - Durum `modification_requested` olarak güncellenir, `task_activities` tablosuna log atılır ve SSE yayını yapılır.
2. **REST API Kancası (`Modules/RestApi/Controllers/RestApiController.php` - Satır ~609):**
   - API üzerinden `POST /api/tasks/{id}/workflow` ile `action: "review"` ve `decision: "rejected"` gönderildiğinde:
   - `RestApiModel::rejectTaskSubmission` çalışır; görev durumu `modification_requested` olur.
   - Ardından `TaskLifecycleService::triggerTaskRevisionWebhook($taskId, $feedback)` çağrılır.
3. **Webhook İletici (`Modules/Board/Services/TaskLifecycleService.php` - Satır ~442):**
   - `triggerTaskRevisionWebhook` metodu görevin proje kodunu, eklerini ve revizyon gerekçesini paketler.
   - `WebhookService::triggerProjectWebhook($projectId, 'task.revision_requested', $payloadData)` ile Python Dispatcher'a (Port 4000) HTTP POST atar.
4. **Ajan Enjektörü (`Modules/Dispatcher/Bin/dispatcher.py` - Satır ~423):**
   - Python sunucusu `event == 'task.revision_requested'` veya `'task.modification_requested'` yakaladığında:
   ```python
   banner = f"🚨 **[MANDATORY TASK INTERRUPT]**\n⚠️ **[{project_code}] REVISION TASK MOVED TO IN PROGRESS!**\n🔴 **Requested Changes:** {revision_reason}\n\n"
   ```
   başlığı ile ajanın IDE penceresine prompt enjekte eder.

### 1.2 Geçmişte Neden Bozuldu?
* **Problem 1 (Tek Pencere Çıkmazı):** Dev ajanı "Ben görevi bitirdim" diyerek submit ettikten sonra, aynı pencereye anında revizyon fırlatıldığında ajan önceki mesajın kontekstinde kayboluyor ve "Ben zaten yapmıştım" diyerek döngüye giriyordu.
* **Problem 2 (Conversation ID Kayması):** Kullanıcı yeni bir sohbet açtığında veritabanındaki `agent_conversation_id` eski pencereyi gösteriyordu. Dispatcher görevi kapatılmış eski porttaki hayalet pencereye göndermeye çalıştığı için revizyon hiçbir zaman ekrana düşmüyordu.
* **Problem 3 (Geri Bildirim Format Eksikliği):** Revizyon istendiğinde ajana sadece "Hata var düzelt" deniyordu; tarayıcı konsol hatası, network yanıtı veya hangi dosyanın hatalı olduğu parametrik olarak verilmiyordu.

---

## 📌 BÖLÜM 2: GERİ DÖNÜŞÜM (ROLLBACK) VE GÜVENLİK GÜVENCESİ

Bu mimariyi uygularken **mevcut kararlı sürüme tek tıkla geri dönebilme (100% Rollback)** garantisi sağlanacaktır:
1. **Geriye Dönük Veritabanı Uyumluluğu:** `projects` tablosundaki mevcut `agent_conversation_id` sütunu **asla silinmeyecek**. Yanına opsiyonel olarak `dev_conversation_id`, `qa_conversation_id` ve `qa_test_instructions` sütunları eklenecektir. İstenirse tek bir SQL ile bu sütunlar düşürülebilir (`.migrations/dual_agent_down.sql`).
2. **Yedekli Dosya Mimarisi:** Değiştirilecek dosyaların (`dispatcher.py`, `TaskLifecycleService.php`, `RestApiController.php`, `edit_project.php`) birebir kopyası `.bak` uzantısıyla saklanacaktır.
3. **Tek Anahtarla Kapatma (Feature Flag):** Eğer sistemde çift ajan istenmezse, Proje ayarlarından `qa_conversation_id` boş bırakıldığında sistem **birebir bugünkü tek ajanlı klasik çalışma biçimine geri düşecektir (Zero-Risk Fallback).**

---

## 📌 BÖLÜM 3: ADIM ADIM SATIR VE FONKSİYON BAZINDA YOL HARİTASI

### 🔲 ADIM 1: Veritabanı Genişletmesi & Geri Alma Scripti
* **Hedef Tablo:** `projects` ve `settings`
* **Yapılacak İşlem:**
  1. `projects` tablosuna `qa_conversation_id VARCHAR(100) NULL` sütunu eklenmesi.
  2. `projects` tablosuna `qa_test_instructions TEXT NULL` (Proje bazlı özel test talimatları) eklenmesi.
  3. `settings` tablosuna `qa_global_instructions TEXT NULL` (Panelden değiştirilebilir genel Test Ajanı Talimatları) eklenmesi.
  4. `tasks` tablosuna `qa_revision_count INT DEFAULT 0` (Sonsuz döngüyü önleyen sayaç) eklenmesi.
* **Rollback Dosyası:** `.migrations/dual_agent_down.sql` oluşturulacak.

---

### 🔲 ADIM 2: Panel Arayüzü — Test Ajanı ve Özel Test Talimatları Girişi
* **Hedef Dosya 1:** [Modules/Projects/Views/modals/edit_project.php](file:///c:/wamp64/www/taskmanager/Modules/Projects/Views/modals/edit_project.php) (Satır ~90)
  - **Ne Eklenecek?**
    - 🔵 `dev_conversation_id` (Kodlama Ajanı Penceresi) input kutusu.
    - 🟣 `qa_conversation_id` (Test Ajanı Penceresi) input kutusu.
    - 📝 `qa_test_instructions` (Bu projeye özel test edilecek URL'ler, butonlar ve PowerShell test komutları alanı).
* **Hedef Dosya 2:** [Modules/AgentRules/Views/index.php](file:///c:/wamp64/www/taskmanager/Modules/AgentRules/Views/index.php) (Satır ~40)
  - **Ne Eklenecek?**
    - Tıpkı `AGENTS.md` kuralları gibi, panelde **"🛡️ QA TEST AGENT GLOBAL PROTOCOL"** sekmesi açılacak.
    - Test Ajanının neyi nasıl test edeceğini (UI, CSS, Console, Network, Audit) siz panelden metin kutusundan dilediğiniz zaman değiştirebileceksiniz.

---

### 🔲 ADIM 3: Test Ajanına Fırlatılacak İngilizce QA Talimat Şablonu
Test Ajanının penceresine (`qa_conversation_id`) düşecek olan mesaj %100 İngilizce, sert ve denetime zorlayıcı olacaktır:

```markdown
🚨 **[MANDATORY QA VERIFICATION DIRECTIVE — NO CODE MODIFICATION ALLOWED!]**
> **ROLE:** You are the Lead QA & Autonomous Testing Inspector. You CANNOT write feature code. Your ONLY objective is to rigorously test, verify, and audit task **{task_code}** delivered by the Developer Agent.

### 📋 DELIVERED TASK CONTEXT:
- **Project:** [{project_code}] {project_title} (`{workspace_path}`)
- **Task Code:** `{task_code}` | **Title:** {task_title}
- **Developer Submission Notes:** {submission_notes}

### 🔍 MANDATORY STEP-BY-STEP TESTING PROTOCOL:
1. **LIVE BROWSER TEST (UI & USABILITY):**
   - Open `{test_url}` using your browser tool.
   - Inspect visually: Verify responsiveness, CSS layouts, padding/margins, text visibility, and button clickability.
2. **NETWORK & CONSOLE AUDIT (ZERO TOLERANCE):**
   - Check Developer Console: Must contain **0 JS errors** and **0 404 assets**.
   - Check Network Response: Inspect payload body. Even if HTTP 200, ensure NO `xdebug-error`, `Fatal error`, `PDOException`, `Warning`, or `Notice`.
3. **MANDATORY GATEKEEPER CLI AUDIT:**
   - Execute in terminal: `php taskmanager-audit/audit.php {module_name}`
   - Ensure it returns `0 Violations / [PASS]`.
4. **TRI-LINGUAL (TR, EN, DE) & SECURITY CHECK:**
   - Test `?lang=tr`, `?lang=en`, and `?lang=de`. Verify no raw untranslated strings exist.
   - Verify basic input security (XSS, SQL sanitization).

---

### 🛠️ USER-CONFIGURED CUSTOM POWERSHELL TEST COMMANDS:
{custom_qa_commands_from_panel}

---

### ⚖️ FINAL VERDICT COMMANDS (CHOOSE EXACTLY ONE):

**OPTION A — IF ALL TESTS PASS WITHOUT ANY FLAW (APPROVE & CLOSE):**
Execute in PowerShell:
```powershell
Invoke-RestMethod -Uri "{api_base_url}/api/tasks/{task_id}/workflow" -Method POST -Headers @{ "Authorization" = "Bearer {api_token}"; "Content-Type" = "application/json" } -Body '{"action":"review","decision":"approved","feedback":"All live browser UI, Console, Network, and Audit tests passed with zero defects."}'
```

**OPTION B — IF ANY BUG, CSS GLITCH, JS ERROR OR AUDIT FAIL IS FOUND (REJECT & REQUEST REVISION):**
Execute in PowerShell (Clearly document the defect with screenshot and reproduction steps):
```powershell
Invoke-RestMethod -Uri "{api_base_url}/api/tasks/{task_id}/workflow" -Method POST -Headers @{ "Authorization" = "Bearer {api_token}"; "Content-Type" = "application/json" } -Body '{"action":"review","decision":"rejected","feedback":"[QA DEFECT REPORT]\n1. Visual/CSS Bug: ...\n2. Console/Network Bug: ...\n3. Audit Failure: ...\nPlease fix and re-submit."}'
```
```

---

### 🔲 ADIM 4: Python Dispatcher Akıllı Dağıtım Mantığı (`dispatcher.py`)
* **Hedef Dosya:** [Modules/Dispatcher/Bin/dispatcher.py](file:///c:/wamp64/www/taskmanager/Modules/Dispatcher/Bin/dispatcher.py) (Satır ~350 - 450)
* **Yapılacak Düzenleme:**
  1. `task.submitted` Olayı Geldiğinde:
     - Görevi sonlandırmak yerine projenin `qa_conversation_id` değerini alacak.
     - Eğer `qa_conversation_id` tanımlıysa: Yukarıdaki İngilizce QA Direktifini hazırlayıp **Test Ajanının Penceresine** enjekte edecek.
     - `dev_conversation_id` penceresini sessiz moda (idle) alacak.
  2. `task.modification_requested` Olayı Geldiğinde:
     - QA Ajanının yazdığı hata tutanağını (`feedback`) alacak.
     - `tasks.qa_revision_count` değerini kontrol edecek.
     - **Eğer `qa_revision_count >= 3` ise:**
       - Döngüyü kıracak (`Circuit Breaker`).
       - TaskManager paneline "⚠️ Maksimum Revizyon Limiti (3/3) Aşıldı - İnsan Müdahalesi Gerekiyor" uyarısı bırakacak ve duracak.
     - **Eğer limit aşılmadıysa:**
       - Hata raporunu ve ekran görüntülerini paketleyip **Kodlama Ajanının Penceresine (`dev_conversation_id`)** revizyon görevi olarak fırlatacak.

---

### 🔲 ADIM 5: Sonsuz Döngü Kalkanı & Güvenlik Kilidi (`TaskLifecycleService.php`)
* **Hedef Dosya:** [Modules/Board/Services/TaskLifecycleService.php](file:///c:/wamp64/www/taskmanager/Modules/Board/Services/TaskLifecycleService.php)
* **Yeni Metot:** `handleQaReviewDecision(int $taskId, string $decision, string $feedback)`
  - Her revizyonda `qa_revision_count` 1 artırılır.
  - Revizyon 3'e ulaştığında görev `in_review` (İnsan İncelemesinde) durumuna kilitlenir; iki ajanın sonsuz ping-pong yapması engellenir.

---

## 📋 ADIM ADIM İLERLEME TAKİP LİSTESİ (CHECKLIST)

İşlemleri yaparken bu dosyayı referans alacağız ve her adımı bitirdikçe işaretleyeceğiz:

- [ ] **Aşama 1:** Veritabanı tablolarının genişletilmesi (`qa_conversation_id`, `qa_test_instructions`, `qa_revision_count`) ve `.migrations/dual_agent_down.sql` hazırlanması.
- [ ] **Aşama 2:** Panel arayüzünde Proje Düzenleme ve AgentRules ekranlarına Test Ajanı ID ve Özel Test Komutları alanlarının eklenmesi.
- [ ] **Aşama 3:** `TaskLifecycleService.php` içine QA döngü yöneticisi ve 3 turluk sonsuz döngü kalkanının (Circuit Breaker) kodlanması.
- [ ] **Aşama 4:** `dispatcher.py` motoruna `task.submitted` -> `QA Agent` ve `task.modification_requested` -> `Dev Agent` akıllı yönlendirmesinin eklenmesi.
- [ ] **Aşama 5:** `RestApiController.php` review kancalarının QA ajanının PowerShell komutlarıyla tam uyumlu hale getirilmesi.
- [ ] **Aşama 6:** Canlı çift pencere testi: Kodlama ajanı görevi bitirip teslim edecek -> Test ajanı uyanıp tarayıcıda test edecek -> Hata bildirip revizyon döngüsünü tamamlayacak.
- [ ] **Aşama 7:** `php taskmanager-audit/audit.php ALL` ile tüm modüllerin %100 0 ihlal ile geçtiğinin onaylanması.

---

Bu planı onaylıyorsanız, ilk adımdan (`Aşama 1 - Veritabanı & Rollback Scripti`) başlayarak hiç acele etmeden, adım adım ve test ederek hayata geçirebiliriz.
