# 🧪 TASKMANAGER MANUEL KULLANICI TEST SENARYOLARI KILAVUZU
**Tarih:** 18 Ekim 2026  
**Hedef Kitle:** İnsan Test Uzmanı, Ürün Yöneticisi, Proje Yöneticisi veya Son Kullanıcı  
**Sistem Giriş Bilgileri:**
- **URL:** `http://localhost/taskmanager/`
- **Yönetici Girişi:** `iletisimtktalha@gmail.com`
- **Şifre:** `145236`

---

## 📌 GENEL TEST TALİMATLARI
Bu doküman, TaskManager platformundaki **24 bağımsız HMVC modülünün her birini** bir insan kullanıcının adım adım nasıl test edeceğini (hangi URL'ye gideceğini, hangi butona tıklayacağını, formlara ne yazacağını ve ekranda ne görmesi gerektiğini) detaylandırır.

---

## 1. 🪄 AiArchitect (AI Görev Mimarı & Prompt Mühendisliği)
- **Nereden Test Edilir:** `http://localhost/taskmanager/board?project_id=1`
- **Adım Adım Test Senaryosu:**
  1. Kanban Panosu üst araç çubuğundaki mor-pembe gradyanlı **"🪄 AI Mimar"** butonuna tıklayın.
  2. Açılan modal pencerede (AiArchitect Modalı) sol taraftaki metin kutusuna şu brifi yazın:  
     `"E-Ticaret sepeti ve Stripe ödeme entegrasyonu yapılacak."`
  3. Proje açılır kutusundan `[01] Task Manager` projesini seçin.
  4. **"🪄 Brifi Parçala & Görev Ağacı Üret"** butonuna tıklayın.
  5. 2-3 saniye sonra ekranda otomatik olarak Epikler (Ana Görevler) ve altındaki kartlar belirecektir.
  6. Bir kartı sürükleyip başka bir kartın altına bırakın (Drag & Drop ağaç testi).
  7. Sağ alttaki **"✅ Ağacı Onayla ve Panoya Ekle"** butonuna tıklayın.
- **Beklenen Sonuç:** Modal kapanmalı, Kanban panosunda yeni görevler belirmeli ve başarı toast bildirimi görünmelidir.

---

## 2. 📌 Board (Kanban Panosu & İş Akışı)
- **Nereden Test Edilir:** `http://localhost/taskmanager/board?project_id=1`
- **Adım Adım Test Senaryosu:**
  1. Sayfayı açın; 5 sütunun (`YAPILACAK İŞLER`, `ÇALIŞILIYOR`, `REVİZYON İSTENDİ`, `İNCELEMEDE`, `TAMAMLANDI`) listelendiğini görün.
  2. `YAPILACAK İŞLER` sütunundaki herhangi bir görevi farenizle basılı tutarak `ÇALIŞILIYOR` sütununa sürükleyip bırakın.
  3. Herhangi bir kartın üzerine tıklayın; sağdan **Görev Detay Çekmecesi (Drawer)** açılmalıdır.
  4. Çekmecedeki yorum alanına `"Test yorumudur"` yazıp **"Gönder"** butonuna tıklayın.
  5. Sağ üstteki **"🛠️ Araçlar ▼"** butonuna tıklayın; açılır menüden **"AI Standup Özeti"** seçeneğini seçin.
  6. Açılan robot ikonlu AI Standup modalında son 24 saatin özetinin geldiğini doğrulayın ve **"Kapat"** butonuna basın.
- **Beklenen Sonuç:** Sürüklenen kart yeni sütununda kalmalı, sayfa yenilense dahi durumu korunmalı; detay çekmecesi ve standup modalı hatasız açılmalıdır.

---

## 3. 👥 Team (Ekip, Rol & İzinler - RBAC)
- **Nereden Test Edilir:** `http://localhost/taskmanager/team`
- **Adım Adım Test Senaryosu:**
  1. Ekip Yönetimi sayfasına gidin.
  2. Sağ üstteki **"➕ Yeni Üye / Müşteri Ekle"** butonuna tıklayın.
  3. Açılan modalda şu bilgileri doldurun:
     - **Ad Soyad:** `Can Demir`
     - **E-Posta:** `candemir@example.com`
     - **Şifre:** `123456`
     - **Rol:** `user` (veya `client`)
     - **Unvan:** `Backend Geliştirici`
  4. **"Kaydet ve Ekle"** butonuna tıklayın.
  5. Ekip listesinde yeni üyenin kartının belirdiğini görün.
  6. Eklenen kartın üzerindeki **"Sil (Çöp Kutusu)"** butonuna tıklayın ve onaylayın.
- **Beklenen Sonuç:** Yeni üye veritabanına eklenmeli, arayüzde kartı hemen görünmeli, silindiğinde listeden kalkmalıdır.

---

## 4. 📁 Projects (Proje & Çalışma Alanları)
- **Nereden Test Edilir:** `http://localhost/taskmanager/projects`
- **Adım Adım Test Senaryosu:**
  1. Sol menüdeki **"Projeler"** linkine veya doğrudan URL'ye gidin.
  2. Sağ üstteki **"📁 Yeni Proje Ekle"** butonuna tıklayın.
  3. Formda:
     - **Proje Kodu:** `PRJTEST`
     - **Proje Başlığı:** `Test Otomasyon Projesi`
     - **Renk:** Yeşil veya Mavi seçin.
  4. **"Proje Oluştur"** butonuna tıklayın.
  5. Listede projenin kartının oluştuğunu ve sol menüdeki proje listesine eklendiğini görün.
- **Beklenen Sonuç:** Yeni proje başarıyla kaydedilmeli, detayına tıklandığında ilgili projenin Kanban panosuna yönlendirmelidir.

---

## 5. 📅 Calendar (İnteraktif Takvim & Gantt Çizelgesi)
- **Nereden Test Edilir:** `http://localhost/taskmanager/calendar`
- **Adım Adım Test Senaryosu:**
  1. Takvim sayfasına gidin.
  2. Ay görünümünde teslim tarihi olan görevlerin renkli rozetler olarak günlerin içine yerleştiğini inceleyin.
  3. Üstteki **"Gantt Çizelgesi"** veya **"Haftalık Görünüm"** sekmesine tıklayın.
  4. Bir görevin üzerine tıklayın; detay modalının veya çekmecesinin açıldığını doğrulayın.
- **Beklenen Sonuç:** Görevler takvim hücrelerine düzgün dağılmalı, ay değiştirme butonları (`< Önceki`, `Sonraki >`) sorunsuz çalışmalıdır.

---

## 6. 📄 Docs (Dökümantasyon Motoru)
- **Nereden Test Edilir:** `http://localhost/taskmanager/docs`
- **Adım Adım Test Senaryosu:**
  1. Dökümanlar sayfasına gidin.
  2. Sol paneldeki döküman ağacından bir dökümana tıklayın (örn: `API Kılavuzu` veya `Kurulum`).
  3. Sağ tarafta Markdown içeriğinin temiz bir HTML olarak render edildiğini görün.
  4. Üstteki **"➕ Yeni Döküman"** butonuna tıklayın.
  5. Başlık ve Markdown formatında metin girip **"Kaydet"** butonuna basın.
- **Beklenen Sonuç:** Yeni döküman sol listeye eklenmeli ve tıklandığında anında okunabilmelidir.

---

## 7. 💬 Chat (Ekip İçi Canlı Sohbet)
- **Nereden Test Edilir:** `http://localhost/taskmanager/chat`
- **Adım Adım Test Senaryosu:**
  1. Canlı Sohbet sayfasına gidin.
  2. Sol taraftaki listeden herhangi bir ekip üyesine (örn: `Ahmet Yılmaz`) tıklayın.
  3. Alt kısımdaki mesaj yazma alanına `"Merhaba, bu bir manuel test mesajıdır."` yazın.
  4. **"Gönder"** butonuna (veya Enter tuşuna) basın.
  5. Mesaj balonunun sağ tarafta belirdiğini ve saat bilgisinin düştüğünü görün.
- **Beklenen Sonuç:** Mesajlaşma ekranında kayma olmamalı, mesaj anında ekranda gösterilmelidir.

---

## 8. 🧪 ApiTester (REST API Test Laboratuvarı & Dokümantasyon)
- **Nereden Test Edilir:**
  - Canlı Test Laboratuvarı: `http://localhost/taskmanager/api-test`
  - Dokümantasyon Sayfası: `http://localhost/taskmanager/api-docs`
- **Adım Adım Test Senaryosu:**
  1. `http://localhost/taskmanager/api-test` adresini açın.
  2. Sol menüden **"⚡ Sistem & Sağlık -> API Sağlık & Durum (GET /health)"** endpoint'ine tıklayın.
  3. Sağ üstteki **"🚀 İsteği Gönder (Send Request)"** butonuna tıklayın.
  4. Alt taraftaki JSON yanıt kutusunda `HTTP 200 OK` ve `"status": "success"` döndüğünü görün.
  5. `http://localhost/taskmanager/api-docs` adresine gidin ve **"Token Kopyala"** butonuna tıklayın; `"Token panoya kopyalandı"` toast'ını doğrulayın.
- **Beklenen Sonuç:** Swagger/Postman tarzı konsol sıfır hatayla canlı API çağrısı yapmalı ve JSON yanıtı renklendirerek göstermelidir.

---

## 9. 🐙 GithubDevops (GitHub DevOps & Surgical Rollback)
- **Nereden Test Edilir:** `http://localhost/taskmanager/board?project_id=1`
- **Adım Adım Test Senaryosu:**
  1. Kanban panosunda üstteki **"🛠️ Araçlar ▼"** butonuna tıklayın.
  2. Menüden **"🐙 GitHub Ayarları (DevOps)"** seçeneğini seçin.
  3. Açılan modalda:
     - **GitHub Repo URL:** `https://github.com/BayKeskin/taskmanager`
     - **Branch:** `main`
     - **Personal Access Token:** Test amaçlı token veya mevcut token
  4. **"Kaydet"** butonuna tıklayın.
  5. **"🛠️ Araçlar ▼"** altından **"GitHub Sync"** butonuna basın ve bildirim mesajını gözlemleyin.
- **Beklenen Sonuç:** GitHub bağlantı bilgileri kaydedilmeli, senkronizasyon tetiklendiğinde durum mesajı dönmelidir.

---

## 10. 🚀 LiveDeploy (Canlı Dağıtım & Delta FTP Deployment)
- **Nereden Test Edilir:** `http://localhost/taskmanager/board?project_id=1`
- **Adım Adım Test Senaryosu:**
  1. Kanban panosundaki **"🛠️ Araçlar ▼"** açılır menüsünden **"🚀 Canlı Dağıtım (Deploy Wizard)"** seçeneğini seçin.
  2. Açılan Dağıtım Sihirbazı modalında projenin dağıtım geçmişini ve ayarlarını inceleyin.
  3. FTP/cPanel/SSH bağlantı testini başlatmak için **"Bağlantıyı Test Et"** veya **"Dağıtım Simülasyonu (Dry Run)"** butonuna basın.
- **Beklenen Sonuç:** Dağıtım modalı açılmalı, dosya farklarını (delta) listeleyebilmelidir.

---

## 11. 🧩 Mcp (Antigravity MCP Pazarı & Yönetimi)
- **Nereden Test Edilir:** `http://localhost/taskmanager/board?project_id=1`
- **Adım Adım Test Senaryosu:**
  1. Kanban panosu **"🛠️ Araçlar ▼"** menüsünden **"🧩 MCP Tools (Pazar)"** seçeneğine tıklayın.
  2. Açılan MCP Pazarı modalında kategorileri (`Design`, `Database`, `DevOps`, `Payment`) sekmelerine tıklayarak filtreleyin.
  3. Herhangi bir MCP aracının (örn: `GitHub MCP` veya `MySQL MCP`) yanındaki açma/kapama (Toggle) switch'ine tıklayın.
  4. Açılan ayar penceresinde API Key değerini kontrol edip **"Kaydet"** butonuna basın.
- **Beklenen Sonuç:** MCP aracı başarıyla aktif/pasif yapılmalı ve yeşil toast bildirimi ekrana gelmelidir.

---

## 12. 📊 Reports (Raporlar, SLA & Performans Analitiği)
- **Nereden Test Edilir:** `http://localhost/taskmanager/reports`
- **Adım Adım Test Senaryosu:**
  1. Sol menüden **"Raporlar"** sayfasına gidin.
  2. Üstteki KPI kartlarını inceleyin: Tamamlanan Görev Sayısı, Geciken Görevler, SLA Başarı Oranı.
  3. Ekip bazlı görev dağılım grafiklerini ve proje bazlı ilerleme barlarını kontrol edin.
  4. Tarih filtresinden `Son 30 Gün` seçip **"Filtrele"** butonuna basın.
- **Beklenen Sonuç:** Grafikler ve oranlar seçilen tarih aralığına göre güncellenmeli, tabloda sıfır PHP hatası olmalıdır.

---

## 13. 💰 Billing (Müşteri Hakediş & İlerleme Raporları)
- **Nereden Test Edilir:** `http://localhost/taskmanager/board?project_id=1`
- **Adım Adım Test Senaryosu:**
  1. Kanban panosundaki **"🛠️ Araçlar ▼"** menüsünü açın.
  2. **"📄 PDF Hakediş Raporu"** seçeneğine tıklayın.
  3. Yeni sekmede açılan veya indirilen faturaya/hakedişe hazır PDF raporunu inceleyin.
  4. Raporda tamamlanan görevlerin, harcanan sürelerin ve hakediş tutarlarının düzgün basıldığını görün.
- **Beklenen Sonuç:** Temiz formatlı, şirket logolu PDF hakediş dökümü oluşturulmalıdır.

---

## 14. 📊 ExcelExport (Excel & CSV Rapor Dışa Aktarıcısı)
- **Nereden Test Edilir:** `http://localhost/taskmanager/board?project_id=1`
- **Adım Adım Test Senaryosu:**
  1. Kanban panosu **"🛠️ Araçlar ▼"** menüsünden **"📊 Excel'e Aktar"** butonuna tıklayın.
  2. Tarayıcının `.xlsx` veya `.csv` dosyasını indirdiğini doğrulayın.
  3. İndirilen dosyayı Microsoft Excel veya metin düzenleyici ile açın; görev başlıkları, durumları ve atanan kişilerin doğru sütunlarda yer aldığını kontrol edin.
- **Beklenen Sonuç:** Dosya bozulmadan inmeli ve Türkçe karakterleri (`ç, ş, ğ, ü, ö, ı`) düzgün göstermelidir.

---

## 15. ⚙️ Settings (Sistem Ayarları & Yapılandırma)
- **Nereden Test Edilir:** `http://localhost/taskmanager/settings`
- **Adım Adım Test Senaryosu:**
  1. Sol menüdeki **"Ayarlar"** sayfasına gidin.
  2. **"Genel Ayarlar"** sekmesinde Şirket Adı, Varsayılan Sistem Dili (`TR`/`EN`/`DE`) alanlarını görün.
  3. **"Personel İzinleri"** kutucuklarından *"Personel AI Görevi Açabilsin"* seçeneğini işaretleyin.
  4. **"Değişiklikleri Kaydet"** butonuna basın.
  5. Sayfayı yenileyin (`F5`); seçtiğiniz ayarların korunduğunu doğrulayın.
- **Beklenen Sonuç:** Ayarlar veritabanına kaydedilmeli ve yeşil başarı mesajı görüntülenmelidir.

---

## 16. 🔴 VisualPins (Görsel Hata İğneleme & Geri Bildirim)
- **Nereden Test Edilir:** `http://localhost/taskmanager/board?project_id=1`
- **Adım Adım Test Senaryosu:**
  1. Ekranın sağ alt köşesinde bulunan kırmızı renkli **"📌 Hata İğnele"** yüzen butonuna tıklayın.
  2. İmlecin iğne (crosshair) şekline dönüştüğünü görün.
  3. Sayfanın herhangi bir öğesine (örn: bir buton veya başlık) tıklayın.
  4. Açılan pinleme kutucuğuna açıklama yazın: `"Bu buton mobilde hizalanmalı"` ve ekran görüntüsü almayı onaylayın.
  5. Gönder'e basın; sayfaya numaralı bir kırmızı pin yerleştiğini görün.
- **Beklenen Sonuç:** Pin tıkladığınız koordinatta sabitlenmeli ve tıklanınca notu gösterebilmelidir.

---

## 17. 🤖 Dispatcher (Otonom Ajan Köprüsü & Kuyruk Yöneticisi)
- **Nereden Test Edilir:** `http://localhost/taskmanager/board?project_id=1`
- **Adım Adım Test Senaryosu:**
  1. Yeni bir görev oluştururken etiket alanına `#ai` veya `#otonom` etiketini ekleyin.
  2. Görevi `YAPILACAK İŞLER` sütununa kaydedin.
  3. `http://localhost/taskmanager/api/dispatcher/status` adresine tarayıcıdan gidin.
  4. JSON çıktısında kuyruktaki ajana atanmış görevin listelendiğini doğrulayın.
- **Beklenen Sonuç:** Sistem `#ai` etiketli görevi otonom kuyruğa almalıdır.

---

## 18. 📡 SSE (Server-Sent Events Canlı Yayın Akışı)
- **Nereden Test Edilir:** İki farklı tarayıcı penceresinde `http://localhost/taskmanager/board?project_id=1` açarak.
- **Adım Adım Test Senaryosu:**
  1. Sol ekranda Chrome, sağ ekranda Firefox (veya Gizli Sekme) ile aynı panoyu açın.
  2. Sol ekrandaki bir görevi `ÇALIŞILIYOR` sütunundan `TAMAMLANDI` sütununa sürükleyin.
  3. Sağ ekrana bakın (hiçbir sayfayı yenilemeden).
- **Beklenen Sonuç:** Sağ ekrandaki görev kartı sayfa yenilenmeksizin canlı olarak `TAMAMLANDI` sütununa otomatik hareket etmeli ve anlık toast bildirimi görünmelidir.

---

## 19. 🌐 RestApi (Enterprise RESTful API Servisi)
- **Nereden Test Edilir:** Tarayıcı veya Postman üzerinden:
  `http://localhost/taskmanager/api/projects`
- **Adım Adım Test Senaryosu:**
  1. Tarayıcıda `http://localhost/taskmanager/api/projects` adresine gidin.
  2. Eğer token girilmediyse `401 Unauthorized` mesajı döndüğünü görün.
  3. Token ile çağırmak için: `http://localhost/taskmanager/api-test` arayüzünü kullanın veya istek başlığına `Authorization: Bearer tm_admin_talha_keskin_token_2026` ekleyin.
- **Beklenen Sonuç:** Standart JSON formatında proje listesi başarıyla listelenmelidir.

---

## 20. 📬 Mailer (E-Posta & SMTP Bildirimleri)
- **Nereden Test Edilir:** `http://localhost/taskmanager/settings`
- **Adım Adım Test Senaryosu:**
  1. Ayarlar sayfasında **"E-Posta & SMTP"** sekmesine geçin.
  2. SMTP Sunucu, Port (587), Kullanıcı ve Şifre alanlarının dolu olduğunu görün.
  3. **"Test E-Postası Gönder"** butonuna tıklayın ve kendi e-posta adresinizi girin.
- **Beklenen Sonuç:** Ekranda `"✅ Test e-postası başarıyla iletildi"` bildirimi çıkmalıdır.

---

## 21. 📱 Telegram (Telegram Bot & Mobil Bildirimler)
- **Nereden Test Edilir:** `http://localhost/taskmanager/settings`
- **Adım Adım Test Senaryosu:**
  1. Ayarlar sayfasında **"Telegram Bildirimleri"** bölümünü açın.
  2. Bot Token ve Chat ID alanlarını inceleyin.
  3. **"Telegram Test Mesajı Gönder"** butonuna basın.
- **Beklenen Sonuç:** Telegram kanalınıza veya botunuza `"🤖 TaskManager Test Bildirimi Başarılı"` mesajı düşmelidir.

---

## 22. 🛡️ Watchdog (Otonom Hata Kurtarma & Kuyruk İzleyici)
- **Nereden Test Edilir:** `http://localhost/taskmanager/api/watchdog/health` (veya Cron simülasyonu)
- **Adım Adım Test Senaryosu:**
  1. Tarayıcıdan `http://localhost/taskmanager/api/watchdog/health` URL'sine gidin.
  2. JSON yanıtında kuyrukta kilitli kalan (stale) görev olup olmadığını ve sistem belleği durumunu okuyun.
- **Beklenen Sonuç:** `"status": "healthy"`, `"stale_tasks_recovered": 0` şeklinde sistem sağlığı raporu dönmelidir.

---

## 23. 📦 Storage (Güvenli Dosya Depolama & Ek Yönetimi)
- **Nereden Test Edilir:** `http://localhost/taskmanager/board?project_id=1`
- **Adım Adım Test Senaryosu:**
  1. Bir görev kartına tıklayıp sağdaki detay çekmecesini açın.
  2. Çekmecedeki **"Dosya / Ek Yükle"** alanına bilgisayarınızdan bir görsel (`.png` veya `.jpg`) veya PDF dosyası sürükleyip bırakın.
  3. Yüklenen dosyanın listede belirdiğini görün ve üzerine tıklayarak indirin/görüntüleyin.
  4. Dosyanın yanındaki **"Sil"** butonuna tıklayarak eki temizleyin.
- **Beklenen Sonuç:** Dosya `Public/uploads/` güvenli dizinine yüklenmeli, kart üzerinde ataş (attachment) sayısı 1 artmalıdır.

---

## 24. 🔍 SmokeTest (Otonom Smoke Test Motoru)
- **Nereden Test Edilir:** `http://localhost/taskmanager/settings` (veya CLI: `php tests/audit_all_modules.php`)
- **Adım Adım Test Senaryosu:**
  1. Ayarlar veya Araçlar menüsünden **"Sistem Sağlık Taraması (Smoke Test)"** butonuna tıklayın.
  2. Sayfada bir ilerleme çubuğunun çalıştığını ve sırasıyla Veritabanı, Modüller, Dil Dosyaları ve API rotalarının yeşil tik (`✔`) aldığını izleyin.
- **Beklenen Sonuç:** 24/24 modülün "Aktif ve Sağlıklı" olduğu teyit edilmelidir.

---

## 🏆 TEST TAMAMLAMA KRİTERİ (CHECKLIST)
Tüm testleri tamamladıktan sonra aşağıdaki kontrol listesini onaylayın:
- [ ] Hiçbir sayfada beyaz ekran (`Blank Screen`) ile karşılaşılmadı.
- [ ] Hiçbir buton tıklamaya yanıtsız kalmadı.
- [ ] Tarayıcı geliştirici konsolunda (`F12 -> Console`) kırmızı JavaScript veya 404/500 ağ hatası oluşmadı.
- [ ] `?lang=tr`, `?lang=en` ve `?lang=de` geçişlerinde dil kusursuz değişti.
