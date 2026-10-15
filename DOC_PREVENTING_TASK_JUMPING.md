# 🛡️ Ajanların Önceki Görevlere Atlamasını Engelleme Kılavuzu & Mimari Çözüm Raporu
> **Görev:** TSK-166 — Ajanların önceki görevlere atlaması ve asıl göreve bakmaması sorununun kökten çözümü

Bu doküman, Antigravity AI ajanlarının kullanıcı tarafından yeni bir görev verildiğinde veya sohbet üzerinden talimat iletildiğinde, o görevi yapmak yerine **3-5 önceki eski görevlere geri dönüp asıl görevi gözden kaçırması** probleminin teknik kök nedenlerini ve uygulanan kesin mimari çözümleri açıklar.

---

## 🔍 1. Kök Neden Analizi: Ajan Neden Eski Görevlere Atlıyordu?

Yapılan derinlemesine log ve kod incelemelerinde problemin **4 ana kaynaktan** tetiklendiği tespit edilmiştir:

### ❌ Kök Neden 1: `AGENTS.md` Kural 2'deki "Zorunlu Kuyruk Döngüsü"
Eski `AGENTS.md` Bölüm 2 doktrininde şu kırmızı çizgi kuralı bulunuyordu:
> *"Veritabanında 'in_progress' veya kuyrukta bekleyen görev varken, ajanın durup ara rapor yazması veya konuşması KESİNLİKLE YASAKTIR. Sadece kuyruktaki TÜM görevler %100 bittiğinde kullanıcıya yanıt verilebilir!"*

**Yol Açtığı Sorun:**
Kullanıcı sohbet penceresinden doğrudan bir talimat verdiğinde veya yeni bir iş istediğinde; ajan veritabanını tarıyor, arkada bekleyen veya eskiden yarım kalmış görevleri görüyordu. Bu kural yüzünden **"Benim kullanıcıyla konuşmam yasak, arkadaki tüm görevleri bitirmeden duramam"** yanılgısına kapılıp kullanıcının doğrudan verdiği komutu çöpe atarak 3-5 önceki görevleri yapmaya koyuluyordu.

---

### ❌ Kök Neden 2: `Helper::dispatchNextAiTaskIfAvailable` Zaman Aşımı Kontrolünün Olmaması
`System/Helper.php` dosyasındaki otonom motor, bir görev teslim edildiğinde (`submitTaskDirectly`):
1. **Aşama 1'de:** Projedeki diğer `in_progress` görevleri arıyordu.
2. **Aşama 3'te:** `assigned` sütunundaki en eski `id ASC` görevini otomatik olarak `in_progress` yapıp webhook fırlatıyordu.

**Yol Açtığı Sorun:**
- Bir görev 3 gün önce veya 2 hafta önce yarım bırakılmış olsa bile, sistem onu "yarım kalmış acil iş" zannedip diriltiyor ve ajanın ekranına `🔄 [RESUMING TASK]` olarak fırlatıyordu.
- Aşama 3 ise kullanıcının haberi olmadan `assigned` (Yapılacaklar) sütunundaki 10 gün önceki backlog görevini alıp ajanın kucağına bırakıyordu. Ajan da o eski göreve kilitleniyordu.

---

### ❌ Kök Neden 3: Veritabanında Aynı Anda Birden Fazla Görevin 'in_progress' Kalması
Kanban panosunda kullanıcı yeni bir kartı "Çalışılıyor" sütununa sürüklediğinde veya kabul ettiğinde; önceki çalışan görev için sadece bir aktivite logu yazılıyor, ancak **veritabanındaki `status` sütunu `'in_progress'` olarak bırakılıyordu.**
Bu sebeple bir projede aynı anda 3-4 görev `in_progress` olarak kalıyor, ajan hangi göreve bakacağını şaşırıp sıralamada en eski olanına atlıyordu.

---

### ❌ Kök Neden 4: Webhook Mesajındaki "Kuyruğu Kilitlememe" Baskısı
Dispatcher ve Helper mesajlarında:
> *"to prevent the system from deadlocking and allow the queue to immediately proceed to the next pending task, you MUST deliver..."*
ifadesi yer alıyordu. Bu ifade yapay zeka modelinde "hemen bir sonraki göreve geçmeliyim" algısı oluşturuyordu.

---

## 🛠️ 2. Uygulanan Kalıcı Mimari Çözümler

### ✅ Çözüm 1: `AGENTS.md` Kural 2 Baştan Yazıldı ("Kullanıcı Üstünlüğü & Tek Görev Odağı")
`AGENTS.md` dosyasındaki tehlikeli zorunlu döngü kuralı kaldırıldı ve yerine 3 maddelik koruma getirildi:
1. **Kullanıcı Direktifi Üstünlüğü (User Prompt Supremacy):** Kullanıcı aktif chat penceresinde bir talimat veya soru yazdığında, **kullanıcının mesajı her şeyden üstündür.** Ajan arka plandaki kuyruğu bahane ederek kullanıcının mesajını görmezden gelemez.
2. **Tekil Görev İzolasyonu (Single Active Task Isolation):** Ajan yalnızca ve yalnızca kendisine o an açıkça verilen göreve odaklanır. Veritabanını tarayarak kendi kendine 3-5 görev öncesine atlamak veya eski görevleri diriltmek **KESİNLİKLE YASAKTIR.**
3. **Kontrollü Teslimat & Sıra Kullanıcıda:** Görev teslim edildiğinde (`Helper::submitTaskDirectly`), ajan Definition of Done (DoD) raporunu sunup sırasını kullanıcıya devreder. Kullanıcı yeni bir görev vermedikçe veya onaylamadıkça başka göreve otonom atlayamaz.

*(Bu kural `taskmanager`, `ufkayolculuk` ve `kitap-okuma` depolarındaki tüm `AGENTS.md` dosyalarına uygulandı).*

---

### ✅ Çözüm 2: `Helper.php` Anti-Staleness & Kontrolsüz Geçiş Engeli
`System/Helper.php` içindeki `dispatchNextAiTaskIfAvailable` metoduna koruma filtreleri eklendi:
1. **30 Dakika Zaman Aşımı (Anti-Staleness):**
   ```sql
   SELECT * FROM tasks 
   WHERE project_id = ? 
     AND status = 'in_progress'
     AND updated_at >= NOW() - INTERVAL 30 MINUTE
   ```
   Yalnızca son 30 dakika içinde üzerinde çalışılmış taze işler araya girme durumunda geri çağrılabilir. Günler veya saatler öncesinden kalmış eski işler asla otomatik diriltilmez.
2. **Backlog Otomatik Geçişi Kapatıldı (Aşama 3 İptali):**
   Bir görev bittiğinde `assigned` sütunundaki eski görevlerin otomatik olarak `in_progress` yapılıp ajana zorla fırlatılması engellendi. Görev bittiğinde motor `null` döner, ajan teslimatını yapar ve kullanıcının talimatını bekler.

---

### ✅ Çözüm 3: Projede Tekil Aktif Görev Zorunluluğu (`Controller/ajax/ajax.php`)
Kanban panosunda veya API üzerinden yeni bir görev `in_progress` yapıldığında veya kabul edildiğinde:
- Aynı projedeki diğer tüm `in_progress` görevler otomatik olarak `assigned` (Yapılacaklar) sütununa geri çekilir (`UPDATE tasks SET status = 'assigned'`).
- Canlı SSE yayını ile ekrandaki diğer kartlar da güncellenir.
- Böylece veritabanında **HER ZAMAN TEK BİR AKTİF GÖREV** kalması garanti altına alınır.

---

### ✅ Çözüm 4: Ajan Direktifleri Netleştirildi (`formatAgentMessage` & `dispatcher.py`)
Yapay zekaya giden sistem talimatı şu şekilde sabitlendi:
> *"🎯 Directive: This task is actively assigned and in progress. Focus EXCLUSIVELY on this specific task: [TASK_CODE]. Do NOT jump to older or previously completed tasks. If the user in the active chat gave a direct instruction or question, prioritize addressing the user."*

---

## 🧪 3. Otonom Test & Doğrulama Kanıtları

1. **Özel Anti-Task-Jumping Test Paketi (`scratch/test_anti_task_jumping.php`):**
   - `AGENTS.md` Kural 2 kontrolü: Eski döngü kuralının silindiği ve Tek Görev Odağının eklendiği doğrulandı.
   - `formatAgentMessage`: Tekil görev ve kullanıcı önceliği direktifleri doğrulandı.
   - `dispatchNextAiTaskIfAvailable`: 10 gün önceki backlog görevine atlamadığı (`null` döndüğü) test edildi.
   - Zaman Aşımı: 2 saat önce bırakılmış `in_progress` görevinin diriltilmediği (`null` döndüğü) kanıtlandı.
   - **Sonuç:** 4/4 test başarıyla geçti.
2. **Helper Çok Dilli & Talimat Uyumluluk Testi (`scratch/test_helper_tsk132.php`):**
   - 5/5 test başarıyla geçti.
3. **Sistem Smoke Testi (`System/SmokeTest.php`):**
   - 16/16 test eksiksiz geçti.

---

## 💡 4. Kullanıcı İçin Pratik İpuçları

1. **Görev Başlatırken:** Panodan bir kartı "Çalışılıyor" sütununa sürüklediğinizde, sistem diğer eski kartları otomatik olarak "Yapılacaklar" sütununa çeker; ajan sadece o karta odaklanır.
2. **Sohbetten Talimat Verirken:** Ajanla sohbet ederken doğrudan verdiğiniz mesajlar artık veritabanı kurallarından üstündür; ajan eski görevlere kaçmadan doğrudan sorunuza yanıt verir.
3. **Görev Bittiğinde:** Ajan işi bitirdiğinde teslimat raporunu sunar ve durur. Sıradaki işi başlatmak için panodan yeni görevi sürüklemeniz veya ajana chatten "Sıradaki işe geç" demeniz yeterlidir.
