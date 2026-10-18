# 🗺️ HMVC MODÜLER DENETİM VE REFAKTÖR YOL HARİTASI (ROADMAP_HMVC_REFACTOR.md)

Bu belge, TaskManager projesindeki 24 modülün aşağıdaki 4 ana mimari kural çerçevesinde incelenmesiyle oluşturulan kapsamlı tespit ve aksiyon planıdır:

1. **Varlık İzolasyonu (CSS & JS):** View dosyalarındaki `<style>`, `<script>` blokları ve inline stiller ilgili modülün `Assets/css/` ve `Assets/js/` dizinlerine taşınacak, `AssetManager` ile yüklenecektir.
2. **Katmanlı Veri Erişimi (Model Standardı):** View ve Controller dosyalarında doğrudan veritabanı erişimi (`$db`, `global $db`, `query()`) kesinlikle olmayacak; veriler modülün kendi `Models/*Model.php` sınıfı üzerinden gelecektir. Eksik modeller üretilecektir.
3. **MVC Akışı (Controller Veri Besleme):** View dosyaları doğrudan iş mantığı çalıştırmayacak; tüm değişkenler ve veriler Controller'dan temin edilecektir.
4. **Modüler i18n Standardı:** Her modül sadece kendi `Modules/{Modul}/Lang/{tr,en,de}.php` dosyasındaki `__('ModulAdi::anahtar')` kalıbını kullanacaktır. Çekirdek `languages/` altındaki genel `app.*` anahtarlarına bağımlılık sonlandırılacaktır.

---

## 📊 GENEL DENETİM ÖZETİ (24 MODÜL)

- **Dedicated Modeli Eksik Olan Modüller (13 Modül):** `AiArchitect`, `ApiTester`, `Calendar`, `Dispatcher`, `GithubDevops`, `LiveDeploy`, `Mailer`, `RestApi`, `SSE`, `SmokeTest`, `Storage`, `Telegram`, `Watchdog`.
- **View veya Controller İçinde Doğrudan SQL / `$db` Çağıran Modüller (3 Modül):** `ApiTester` (Controller ve View), `Board` (AjaxController), `RestApi` (Controller).
- **View Dosyalarında `<style>` veya `<script>` Blokları Bulunan Modüller (9 Modül):** `ApiTester`, `Billing`, `Board`, `Calendar`, `Chat`, `Docs`, `Mcp`, `Settings`, `Team`.
- **Genel `app.*` Dil Anahtarlarını Kullanan Modüller (10 Modül):** `AiArchitect`, `Board`, `Chat`, `Docs`, `ExcelExport`, `LiveDeploy`, `Mcp`, `Projects`, `Settings`, `Team`.

---

## 🔍 MODÜL BAZINDA DETAYLI TESPİTLER VE EYLEM PLANI

---

### 1. 🤖 Modül: AiArchitect
- [ ] **1. CSS/JS İzolasyonu:** Varlıklar `aiarchitect.css` ve `aiarchitect.js` içine taşındı; View temizliği teyit edilecek.
- [ ] **2. Model:** Dedicated model eksik. `Modules/AiArchitect/Models/AiArchitectModel.php` oluşturulacak (Task ağacı, prompt geçmişi ve decompose logları için).
- [ ] **3. Controller/View:** `AiArchitectController` model üzerinden çalışacak.
- [ ] **4. Dil (i18n):** `brief_modal.php` içindeki 20 adet `__('app.ai_arch_*')` anahtarı `Modules/AiArchitect/Lang/{tr,en,de}.php` dosyasına `__('AiArchitect::*')` olarak taşınacak.

---

### 2. 🔌 Modül: ApiTester
- [ ] **1. CSS/JS İzolasyonu:**
  - `Modules/ApiTester/Views/api_docs.php` içindeki `<script>` bloğu `Modules/ApiTester/Assets/js/api_docs.js` dosyasına taşınacak.
  - `Modules/ApiTester/Views/api_test.php` içindeki `<style>` ve `<script>` blokları `Modules/ApiTester/Assets/` altına taşınacak.
- [ ] **2. Model:** Dedicated model eksik. `Modules/ApiTester/Models/ApiTesterModel.php` oluşturulacak.
- [ ] **3. Controller/View:**
  - `ApiTesterController.php` içindeki `global $db` kaldırılıp modele taşınacak.
  - `api_test.php` view dosyası içindeki doğrudan `global $db` çağrısı tamamen kaldırılacak; veriler `ApiTesterController` üzerinden aktarılacak.
- [ ] **4. Dil (i18n):** Sabit metinler `Modules/ApiTester/Lang/` dosyalarına aktarılacak (`__('ApiTester::*')`).

---

### 3. 💳 Modül: Billing
- [ ] **1. CSS/JS İzolasyonu:** `Modules/Billing/Views/billing_report.php` içindeki gömülü `<style>` bloğu `Modules/Billing/Assets/css/billing-report.css` içine taşınacak.
- [ ] **2. Model:** `BillingModel.php` mevcut.
- [ ] **3. Controller/View:** Rapor verisi Controller üzerinden akıyor, view temizliği yapılacak.
- [ ] **4. Dil (i18n):** Modül içi çeviriler `Modules/Billing/Lang/` altına toplanacak.

---

### 4. 📋 Modül: Board
- [ ] **1. CSS/JS İzolasyonu:** `Modules/Board/Views/board.php` içerisindeki `<script>` blokları `Modules/Board/Assets/js/board.js` içine taşınacak.
- [ ] **2. Model:** `TaskModel.php` mevcut.
- [ ] **3. Controller/View:**
  - `Modules/Board/Controllers/AjaxController.php` içindeki `global $db` kaldırılıp `TaskModel` ve `ProjectModel` metotlarına devredilecek.
- [ ] **4. Dil (i18n):** `board.php` ve çekmecedeki 67 adet `__('app.*')` anahtarı `Modules/Board/Lang/{tr,en,de}.php` dosyasına `__('Board::*')` olarak taşınacak.

---

### 5. 📅 Modül: Calendar
- [ ] **1. CSS/JS İzolasyonu:** `Modules/Calendar/Views/calendar.php` içindeki `<style>` ve `<script>` blokları `Modules/Calendar/Assets/{css/gantt.css, js/gantt-drag.js}` içine aktarılacak.
- [ ] **2. Model:** Dedicated model eksik. `Modules/Calendar/Models/CalendarModel.php` oluşturulacak (Tarih bazlı görev ve gantt sorguları için).
- [ ] **3. Controller/View:** `CalendarController` verileri `CalendarModel` üzerinden çekecek.
- [ ] **4. Dil (i18n):** Takvim ve Gantt terimleri `Modules/Calendar/Lang/` altına `__('Calendar::*')` olarak taşınacak.

---

### 6. 💬 Modül: Chat
- [ ] **1. CSS/JS İzolasyonu:** `Modules/Chat/Views/chat.php` içindeki `<style>` ve `<script>` blokları `Modules/Chat/Assets/` altına taşınacak.
- [ ] **2. Model:** `ChatModel.php` mevcut.
- [ ] **3. Controller/View:** MVC akışı doğrulanacak.
- [ ] **4. Dil (i18n):** `__('app.chat_*')` anahtarları `Modules/Chat/Lang/` altına `__('Chat::*')` olarak taşınacak.

---

### 7. 🤖 Modül: Dispatcher
- [ ] **1. CSS/JS İzolasyonu:** Arayüz yok (Servis modülü).
- [ ] **2. Model:** Dedicated model eksik. `Modules/Dispatcher/Models/DispatcherModel.php` oluşturulacak (Görev kuyruğu ve dispatch geçmişi için).
- [ ] **3. Controller/View:** `DispatcherController` doğrudan sorgu yerine modeli kullanacak.
- [ ] **4. Dil (i18n):** Log ve hata mesajları `Modules/Dispatcher/Lang/` altına alınacak.

---

### 8. 📄 Modül: Docs
- [ ] **1. CSS/JS İzolasyonu:** `Modules/Docs/Views/docs.php` içindeki devasa `<style>` (satır 260-610) ve `<script>` (satır 620-1150) blokları `Modules/Docs/Assets/css/docs.css` ve `Modules/Docs/Assets/js/docs.js` içine taşınacak.
- [ ] **2. Model:** `DocModel.php` mevcut.
- [ ] **3. Controller/View:** `docs.php` içindeki doğrudan veri hazırlıkları `DocController` üzerinden View'e aktarılacak.
- [ ] **4. Dil (i18n):** 62 adet `__('app.docs_*')` anahtarı `Modules/Docs/Lang/{tr,en,de}.php` altına `__('Docs::*')` olarak taşınacak.

---

### 9. 📊 Modül: ExcelExport
- [ ] **1. CSS/JS İzolasyonu:** Arayüz yok.
- [ ] **2. Model:** `ExcelExportModel.php` mevcut.
- [ ] **3. Controller/View:** Excel başlıkları kontrol edilecek.
- [ ] **4. Dil (i18n):** 12 adet `__('app.export_*')` anahtarı `Modules/ExcelExport/Lang/` altına `__('ExcelExport::*')` olarak taşınacak.

---

### 10. 🐙 Modül: GithubDevops
- [ ] **1. CSS/JS İzolasyonu:** Varlıklar `github-devops.js` içine taşındı.
- [ ] **2. Model:** Dedicated model eksik. `Modules/GithubDevops/Models/GitDevopsModel.php` oluşturulacak (Repo eşlemeleri ve cerrahi rollback audit kayıtları için).
- [ ] **3. Controller/View:** `AjaxController` modeli kullanacak.
- [ ] **4. Dil (i18n):** GitHub DevOps terimleri `Modules/GithubDevops/Lang/` altına taşınacak.

---

### 11. 🚀 Modül: LiveDeploy
- [ ] **1. CSS/JS İzolasyonu:** Varlıklar `deploy-wizard.js` ve `deploy-wizard.css` içine taşındı.
- [ ] **2. Model:** Dedicated model eksik. `Modules/LiveDeploy/Models/DeployModel.php` oluşturulacak (Hosting konfigürasyonları ve dağıtım logları için).
- [ ] **3. Controller/View:** `DeployController` modeli kullanacak.
- [ ] **4. Dil (i18n):** 21 adet `__('app.deploy_*')` anahtarı `Modules/LiveDeploy/Lang/` altına `__('LiveDeploy::*')` olarak taşınacak.

---

### 12. ✉️ Modül: Mailer
- [ ] **1. CSS/JS İzolasyonu:** View dosyası e-posta şablonudur; inline stiller e-posta istemcileri için zorunludur ancak modül assets içine de taşınabilir.
- [ ] **2. Model:** Dedicated model eksik. `Modules/Mailer/Models/MailModel.php` oluşturulacak (Giden kutusu ve SMTP logları için).
- [ ] **3. Controller/View:** `MailerController` modeli kullanacak.
- [ ] **4. Dil (i18n):** Şablon dilleri `Modules/Mailer/Lang/` altına taşınacak.

---

### 13. 🧩 Modül: Mcp
- [ ] **1. CSS/JS İzolasyonu:** `mcp_marketplace.php` içindeki `<script>` bloğu `Modules/Mcp/Assets/js/mcp.js` içine taşınacak.
- [ ] **2. Model:** `McpModel.php` mevcut.
- [ ] **3. Controller/View:** MVC veri akışı kontrol edilecek.
- [ ] **4. Dil (i18n):** 33 adet `__('app.mcp_*')` anahtarı `Modules/Mcp/Lang/` altına `__('Mcp::*')` olarak taşınacak.

---

### 14. 📁 Modül: Projects
- [ ] **1. CSS/JS İzolasyonu:** Varlıklar `projects.js` içine taşındı.
- [ ] **2. Model:** `ProjectModel.php` mevcut.
- [ ] **3. Controller/View:** Modallar `ProjectController` üzerinden beslenecek.
- [ ] **4. Dil (i18n):** 37 adet `__('app.modal_proj_*')` anahtarı `Modules/Projects/Lang/` altına `__('Projects::*')` olarak taşınacak.

---

### 15. 📈 Modül: Reports
- [ ] **1. CSS/JS İzolasyonu:** Varlıklar kontrol edilecek.
- [ ] **2. Model:** `ReportModel.php` mevcut.
- [ ] **3. Controller/View:** MVC uyumu doğrulanacak.
- [ ] **4. Dil (i18n):** Modül içi çeviriler `Modules/Reports/Lang/` altına taşınacak.

---

### 16. 🌐 Modül: RestApi
- [ ] **1. CSS/JS İzolasyonu:** Arayüz yok (JSON API).
- [ ] **2. Model:** Dedicated model eksik. `Modules/RestApi/Models/ApiModel.php` oluşturulacak (Token ve yetkilendirme doğrulamaları için).
- [ ] **3. Controller/View:** `RestApi/Controllers/index.php` içindeki doğrudan `global $db` çağrısı kaldırılıp ilgili Model sınıflarına devredilecek.
- [ ] **4. Dil (i18n):** API hata/başarı yanıtları `Modules/RestApi/Lang/` altına taşınacak.

---

### 17. ⚡ Modül: SSE
- [ ] **1. CSS/JS İzolasyonu:** İstemci betiği `Modules/SSE/Assets/js/realtime.js` olarak konumlandırıldı.
- [ ] **2. Model:** Dedicated model eksik. `Modules/SSE/Models/SseEventModel.php` oluşturulacak (Canlı olay kuyruğu ve broadcast logları için).
- [ ] **3. Controller/View:** Olay yakalayıcılar modeli kullanacak.
- [ ] **4. Dil (i18n):** SSE bağlantı mesajları `Modules/SSE/Lang/` altına taşınacak.

---

### 18. ⚙️ Modül: Settings
- [ ] **1. CSS/JS İzolasyonu:** `Modules/Settings/Views/settings.php` içindeki gömülü `<style>` ve `<script>` blokları `Modules/Settings/Assets/` altına taşınacak.
- [ ] **2. Model:** `SettingsModel.php` mevcut.
- [ ] **3. Controller/View:** View Controller üzerinden beslenecek.
- [ ] **4. Dil (i18n):** 89 adet `__('app.settings_*')` anahtarı `Modules/Settings/Lang/` altına `__('Settings::*')` olarak taşınacak.

---

### 19. 🧪 Modül: SmokeTest
- [ ] **1. CSS/JS İzolasyonu:** İlgili stiller modül assets klasörüne alınacak.
- [ ] **2. Model:** Dedicated model eksik. `Modules/SmokeTest/Models/SmokeTestModel.php` oluşturulacak (Test sonuç logları için).
- [ ] **3. Controller/View:** `SmokeTestController` modeli kullanacak.
- [ ] **4. Dil (i18n):** Test metinleri `Modules/SmokeTest/Lang/` altına taşınacak.

---

### 20. 💾 Modül: Storage
- [ ] **1. CSS/JS İzolasyonu:** Dosya listeleme/yükleme stilleri assets klasörüne alınacak.
- [ ] **2. Model:** Dedicated model eksik. `Modules/Storage/Models/StorageModel.php` oluşturulacak (Ekler ve dosya indeksleme için).
- [ ] **3. Controller/View:** `StorageController` modeli kullanacak.
- [ ] **4. Dil (i18n):** Dosya depolama metinleri `Modules/Storage/Lang/` altına taşınacak.

---

### 21. 👥 Modül: Team
- [ ] **1. CSS/JS İzolasyonu:**
  - `Modules/Team/Views/login.php` içindeki `<style>` bloğu `Modules/Team/Assets/css/login.css` dosyasına taşınacak.
  - `Modules/Team/Views/team.php` içindeki `<script>` blokları `Modules/Team/Assets/js/team.js` içine taşınacak.
- [ ] **2. Model:** `UserModel.php` mevcut.
- [ ] **3. Controller/View:** Giriş ve ekip yönetimi akışları doğrulanacak.
- [ ] **4. Dil (i18n):** 84 adet `__('app.auth_*')` ve `__('app.team_*')` anahtarı `Modules/Team/Lang/` altına `__('Team::*')` olarak taşınacak.

---

### 22. 📱 Modül: Telegram
- [ ] **1. CSS/JS İzolasyonu:** Arayüz yok (Servis modülü).
- [ ] **2. Model:** Dedicated model eksik. `Modules/Telegram/Models/TelegramModel.php` oluşturulacak (Bot webhook ve bildirim kuyruğu için).
- [ ] **3. Controller/View:** Servis katmanı modeli kullanacak.
- [ ] **4. Dil (i18n):** Bot mesaj şablonları `Modules/Telegram/Lang/` altına taşınacak.

---

### 23. 📌 Modül: VisualPins
- [ ] **1. CSS/JS İzolasyonu:** Varlıklar `visual-pins.js`, `taskmanager-pin.js`, `visual-pins.css` olarak yapılandırıldı.
- [ ] **2. Model:** `PinModel.php` mevcut.
- [ ] **3. Controller/View:** Pin kayıt ve önizleme akışları doğrulanacak.
- [ ] **4. Dil (i18n):** Görsel pinleme terimleri `Modules/VisualPins/Lang/` altına taşınacak.

---

### 24. 🛡️ Modül: Watchdog
- [ ] **1. CSS/JS İzolasyonu:** Arayüz yok (Arka plan bekçisi).
- [ ] **2. Model:** Dedicated model eksik. `Modules/Watchdog/Models/WatchdogModel.php` oluşturulacak (Sistem sağlık kontrolleri ve kurtarma logları için).
- [ ] **3. Controller/View:** Kurtarma servisleri modeli kullanacak.
- [ ] **4. Dil (i18n):** Alarm metinleri `Modules/Watchdog/Lang/` altına taşınacak.

---

## 🎯 UYGULAMA FAZLARI VE STRATEJİSİ

- **FAZ 1 (Hızlı Kazanım - Doğrudan DB Çağrılarının Temizlenmesi & Model Üretimi):**
  - Eksik olan 13 modüle `Models/*Model.php` sınıflarının eklenmesi (`BaseModel`'den türetilerek).
  - `ApiTester`, `Board/AjaxController` ve `RestApi` içindeki doğrudan SQL / `global $db` çağrılarının kaldırılıp modellere taşınması.

- **FAZ 2 (Varlık Ayrıştırması - View'lerdeki `<style>` ve `<script>` Temizliği):**
  - Başta `Docs` (500+ satır inline stil ve JS), `Settings`, `Chat`, `Calendar`, `ApiTester`, `Billing` ve `Team` olmak üzere tüm view içi stil ve scriptlerin kendi modül `Assets/` klasörlerine taşınması ve `AssetManager` ile kuyruğa alınması.

- **FAZ 3 (i18n & Dil İzolasyonu):**
  - Genel `app.*` anahtarlarını kullanan 10 modülün tüm dil metinlerinin kendi `Modules/{Modul}/Lang/{tr,en,de}.php` sözlüklerine taşınması ve View/Controller'larda `__('ModulAdi::anahtar')` formatına geçirilmesi.
  - Genel `languages/` dizininden modüllere ait olan anahtarların temizlenmesi.

- **FAZ 4 (Otonom Doğrulama & QA):**
  - Her faz sonrasında `php tests/audit_all_modules.php` ve canlı tarayıcı testleriyle sıfır hata ve sıfır regresyonun kanıtlanması.
