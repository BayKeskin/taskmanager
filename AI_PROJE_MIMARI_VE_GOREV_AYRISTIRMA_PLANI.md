# 📋 AI Proje Mimarı & Akıllı Görev Ayrıştırma Strateji Planı
**Kullanıcı Briflerinden ve Sözleşmelerden Departman Bazlı, Hiyerarşik ve Hibrit Otonom İcra Mimarisi**

> **Tarih:** 14 Ekim 2026  
> **Durum:** Ekip İncelemesi ve Mimari Karar Belgesi  
> **Kapsam:** TaskManager Core, Veritabanı Hiyerarşisi, Dispatcher, Helper, Ajax, Drag & Drop Önizleme ve Çift Modlu İcra  

---

## 1. VİZYON VE PLATFORM FELSEFESİ: "BİZ SADECE KOD MOTORU DEĞİLİZ"

TaskManager, sadece prompt verilip arkada kod yazdırılan basit bir AI kodlama aracı **değildir**.  
TaskManager; yazılımcıların, tasarımcıların, SEO uzmanlarının, pazarlamacıların, proje yöneticilerinin ve müşterilerin birlikte çalıştığı **uçtan uca bir proje, iş ve ekip yönetim platformudur**.

Bu vizyon doğrultusunda sisteme girdi olarak:
- Kimi zaman **tek bir cümlelik vizyon brifi** gelebilir: *"Bize B2B toptan satış yapan, dinamik fiyatlandırmalı ve çok dilli bir e-ticaret sitesi lazım."*
- Kimi zaman **özellikler listesi (feature list)** gelebilir: *"Sepet olacak, kupon girilecek, iyzico entegrasyonu, blog, SEO altyapısı, KVKK ve sözleşme sayfaları olacak..."*
- Kimi zaman da **20-30 sayfalık resmi bir teknik şartname, müşteri sözleşmesi veya PDF analiz dokümanı** yüklenebilir.

Sistem bu girdiyi aldığında tek bir devasa "Yazılım (Coding)" görevi açamaz. İşi **büyük fonksiyonel modüllere (Ana Görev / Epic)** ve bu modüllerin altındaki **somut adımlara (Alt Görev / Subtask)** parçalamak; her parçayı ilgili uzmanlık alanına (Yazılım, Tasarım, SEO, Hukuk, Pazarlama) dağıtmak zorundadır.

---

## 2. KULLANICININ TEMEL TEREDDÜTLERİ VE RİSK ANALİZİ

### 🔴 Tereddüt 1: "AI'ya işi parçala dedik; 5 ana başlık, altında da 25 alt görev çıkardı. Biz 'Sepet Sistemi'ne AI dediğimiz anda hepsini tek lokmada yutup sistemi çökertir mi?"
* **Korkulan Senaryo:** Kullanıcı "Sepet Kodlaması" ana görevine onay verdiğinde, ajanın sepete ekleme, adet artırma, kupon doğrulama ve kargo hesaplama gibi 5 farklı alt görevi tek bir promptta yapmaya kalkması.
* **Doğuracağı Zarar:** LLM bağlam penceresi (token) şişer, kodlar yüzeysel/eksik yazılır, test edilemez hale gelir, bir yerde hata çıkınca tüm sepet çöker ve kullanıcı araya girip parça parça test edemez.
* **Çözüm:** Ajan ana göreve değil, sadece tekil alt göreve odaklanır.

### 🔴 Tereddüt 2: "Her şeye körü körüne `#ai` etiketi yapıştırılırsa ekibin iş akışı mahvolur!"
* **Korkulan Senaryo:** Sistemin ürettiği görevlere varsayılan olarak `#ai` veya `#otonom` etiketi basması.
* **Doğuracağı Zarar:** Sözleşmede "Mesafeli Satış Sözleşmesi Metinlerinin Hazırlanması" veya "Google Merchant Center Reklam Kurulumu" gibi hukuki veya pazarlama maddeleri de vardır. Ajan avukatlık veya reklam yayını yapamaz. Ayrıca ekipte insan tasarımcı veya junior yazılımcı varken tüm işleri ajanın gasp etmesi iş takip sisteminin mantığına aykırıdır.
* **Çözüm:** Üretilen görevlerde **ASLA otomatik `#ai` etiketi olmayacaktır**. Görevler niteliklerine göre etiketlenir (`coding`, `design`, `seo`, `marketing`, `legal`). Bu işi otonom ajana mı vereceğiz, insan ekip arkadaşımıza mı atayacağız, yoksa kendimiz mi yapacağız? **Karar %100 insandadır (Human-in-the-Loop).**

### 🔴 Tereddüt 3: "Coding tek bir ana görev olamaz; modüler ayrıştırma nasıl yapılacak?"
* **Kullanıcının İstediği Çözüm:** Gerçek dünya yazılım ve proje mimarisine uygun fonksiyonel parçalama:
  - **Ana Görev 1:** Ana Sayfa Geliştirmesi ➔ *Alt Görevler: Hero slider, vitrin ürünleri listesi, bülten aboneliği*
  - **Ana Görev 2:** Sepet Modülü Geliştirmesi ➔ *Alt Görevler: Sepete ekle/çıkar servisi, adet artırma/azaltma, indirim kuponu motoru, kargo baremi*
  - **Ana Görev 3:** Yasal ve Kurumsal Sayfalar ➔ *Alt Görevler: KVKK aydınlatma metni sayfası, Mesafeli Satış Sözleşmesi onay checkbox'ı, İade şartları*
  - **Ana Görev 4:** Teknik SEO Altyapısı ➔ *Alt Görevler: Dinamik sitemap.xml, robots.txt, canonical link kurgusu, OpenGraph etiketleri*

---

## 3. YENİ GELİŞTİRME: İKİ KRİTİK İHTİYAÇ VE MİMARİ ÇÖZÜMLERİ

### 💡 İhtiyaç A: "Ben her zaman bilgisayar başında değilim; sepete onay gerekmeyebilir ama SEO veya Tasarıma mecbur onay gerekir. Bunu nasıl çözeceğiz?"

Kullanıcımızın sistemi kurma amacı: **"Bilgisayar başında olmadığımda (gece veya dışarıdayken) ajanın işleri kendi kendine yapabilmesi."**  
Ancak bazı işler (Sepet altyapısı gibi saf kodlama) ardı ardına akabilirken; bazı işler (SEO anahtar kelimeleri, müşteri arayüz tasarımları, kritik onaylar) **müşteri veya yönetici canlıda onay vermeden bir sonraki adıma geçmemelidir!**

#### 🛠️ Çözüm: Ana Görev Seviyesinde "İcra Modu (Execution Mode)"

Her Ana Göreve (Epic) kullanıcı tarafından seçilebilen basit bir çalışma modu ekliyoruz:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                 ANA GÖREV (EPIC) İCRA MODU SEÇENEĞİ                         │
├─────────────────────────────────────────────────────────────────────────────┤
│ 🚀 1. TAM OTONOM AKIŞ (Gece Modu / Unattended Chaining)                     │
│    - Örnek: Sepet Sistemi, Veritabanı Şeması, API Servisleri                │
│    - Akış: Alt Görev 1 biter -> Testi geçer -> submitted sütununa geçer ->  │
│      4 saniye sonra Alt Görev 2 OTOMATİK başlar.                            │
│    - Sonuç: Kullanıcı sabah uyandığında sepetin 4 alt görevi de bitmiştir!  │
├─────────────────────────────────────────────────────────────────────────────┤
│ 🛑 2. ONAY KAPILI AKIŞ (Gatekeeper / Step-by-Step Approval)                 │
│    - Örnek: SEO Stratejisi, Figma Tasarım Onayı, Müşteri Kabul Görevleri    │
│    - Akış: Alt Görev 1 biter -> submitted sütununda DURUR.                  │
│    - Kuyruk kilitlenir; sıradaki alt göreve ASLA otomatik geçmez!          │
│    - Ne zaman ki Yönetici veya Müşteri "Onayla (Approve)" butonuna basar,  │
│      Alt Görev 2 ANCAK O ZAMAN tetiklenir!                                 │
└─────────────────────────────────────────────────────────────────────────────┘
```

#### 💻 Kod Seviyesinde Nasıl Çalışacak? (`Helper.php` ve `ajax.php`)
1. `tasks` tablosuna (veya `tags` içine `chain_auto` ya da `chain_gated`) bir mod eklenir: `is_gated (TINYINT 0/1)`.
2. `Helper::dispatchNextAiTaskIfAvailable()` metodu sıradaki görevi çekerken:
   - Eğer bağlı olduğu ana görev `is_gated = 1` ise ve önceki alt görev henüz yönetici/müşteri tarafından `completed` (onaylandı) yapılmamışsa, sıradaki alt görevi **kuyruğa almaz, bekletir.**
   - Müşteri veya Admin `/ajax?action=approve` butonuna bastığı an, `ajax.php` otomatik olarak sıradaki onaylı alt görevi tetikler!

---

### 💡 İhtiyaç B: "Yapay zekanın bize önerdiği görev akışını Sürükle-Bırak (Drag & Drop) ile değiştirebilir miyiz?"

Kullanıcı brifi veya sözleşmeyi verdi. AI bir parçalama önerdi:
- *Ana Sayfa Kodlaması* altında 4 alt görev çıkardı.
- Kullanıcı baktı ve dedi ki: *"Bu 3 tanesi burada kalsın ama şu 4. görev (örn: İletişim Formu API'si) burada değil, 'Diğer Servisler' ana görevinin altında olmalı!"*

#### 🛠️ Çözüm: İnteraktif Önizleme Modalı (Draggable Kanban/Tree Builder)

AI ayrıştırma sonucunu doğrudan veritabanına yazmaz. Kullanıcının önüne **interaktif bir Ağaç / Kart Taşıma Modalı** açılır:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ 🪄 AI PROJE MİMARI - GÖREV AKIŞINI DÜZENLE & ONAYLA                         │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  [📂 Ana Sayfa Kodlaması] (Modül / Epic)                      [+ Alt Görev]  │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │ ⠿ [TSK-1] Hero Slider Alanı               [coding] [👤 Ayşe] [🗑️]      │  │
│  │ ⠿ [TSK-2] Vitrin Ürünleri Gridleri        [coding] [🤖 AI]   [🗑️]      │  │
│  │ ⠿ [TSK-3] E-Bülten Kayıt Kutusu           [coding] [🤖 AI]   [🗑️]      │  │
│  │ ⠿ [TSK-4] İletişim Formu AJAX Servisi ────┐                           │  │
│  └───────────────────────────────────────────┼───────────────────────────┘  │
│                                              │                              │
│                                              ▼ (SÜRÜKLE - BIRAK!)           │
│  [📂 Diğer API ve Kurumsal Servisler]        │                              │
│  ┌───────────────────────────────────────────┼───────────────────────────┐  │
│  │ ⠿ [TSK-5] Statik Sayfa Yönlendirmeleri    │                           │  │
│  │ ⠿ [TSK-4] İletişim Formu AJAX Servisi <───┘ (BURAYA BIRAKILDI!)       │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                             │
│  [➕ Yeni Ana Görev Ekle]                                                   │
│                                                                             │
│  [ İptal ]                                      [ ✅ Onayla ve Projeye İşle ]│
└─────────────────────────────────────────────────────────────────────────────┘
```

#### Bu Ekranın Kullanıcıya Sağladığı Esneklik:
1. **Kutudan Kutuya Taşıma:** Alt görevleri fareyle tutup bir ana görevden diğer ana görevin altına sürükleyip bırakabilme (HTML5 Drag & Drop / `Sortable.js`).
2. **Ana Göreve Yükseltme:** İsterse bir alt görevi tutup ana listeye bırakarak bağımsız bir Ana Görev (Epic) yapabilme.
3. **Rol ve AI Seçimi:** Her alt görevin yanındaki kutucuktan:
   - İster insan ekip arkadaşına atama (`👤 Atanan: Tasarımcı`),
   - İster otonom çalışması için `🤖 AI` checkbox'ını işaretleme,
   - İsterse tek tıkla gereksiz maddeyi silme (`🗑️`).
4. **Tek Tıkla Kayıt:** Kullanıcı son düzenlemeyi yapıp "Onayla ve Projeye İşle" dediğinde, arka planda tek bir DB Transaction çalışır:
   - Ana görevler ve altına sürüklenen alt görevler eksiksiz ilişkileriyle (`parent_task_id`, `project_id`, `tags`, `priority`) veritabanına kaydedilir.

---

## 4. ADIM ADIM GELİŞTİRME YOL HARİTASI (TEKNİK ADIMLAR)

İleride geliştirme kararı verildiğinde uygulanacak 4 net teknik adım:

### 1. Veritabanı ve Model Seviyesi
- `tasks` tablosuna `is_gated (TINYINT DEFAULT 0)` sütunu eklenir. (0: Gece otonom akar, 1: Alt görevler arasında yönetici/müşteri onayı bekler).

### 2. Helper ve Kuyruk Seviyesi (`System/Helper.php`)
- `dispatchNextAiTaskIfAvailable()`:
  - Bağlı olduğu ana görev `is_gated = 1` olan alt görevler için: Kendisinden önceki alt görev `completed` olmadan sıradaki görevi başlatmaz.
  - `is_gated = 0` olan görevler için: Alt görev `submitted` olduğu anda 4 saniye sonra sıradaki alt görevi başlatır (Gece otonom akışı).

### 3. Ajax API Katmanı (`Controller/ajax/ajax.php`)
- `decompose_brief_with_ai`: Kullanıcı metnini veya dökümanını alıp LLM üzerinden modüler JSON ağacı üreten servis.
- `bulk_create_hierarchical_tasks`: Sürükle-bırak sonrası son hali verilen ağacı veritabanına kaydeden servis.
- `approve`: Onay kapılı (`is_gated`) görevlerde onay verildiğinde sıradaki alt görevi tetikleyen entegrasyon.

### 4. Arayüz ve İnteraktif Modal (`Components/modals.php` & `Public/js/app.js`)
- Panoda "🪄 AI Proje Mimarı" butonu.
- Brif/Sözleşme yükleme adımı.
- Sürükle-bırak destekli görsel ağaç düzenleme ekranı (`Sortable.js` veya yerel drag-drop).
- Her kartta Departman Etiketi, Atanan Kişi ve `[ ] AI Çalıştırsın` kutucukları.

---

## 5. SONUÇ VE ÖZET DEĞERLENDİRME

Bu stratejiyle kullanıcının dile getirdiği tüm endişeler tam anlamıyla çözülmektedir:

1. **"Ben yokken de gece çalışsın"** ihtiyacı: `Tam Otonom Akış (is_gated=0)` ile sepetteki 4 yazılım görevinin siz uyurken sırayla bitirilmesini sağlar.
2. **"SEO ve Tasarımda mecbur onay olmak zorunda"** ihtiyacı: `Onay Kapılı Akış (is_gated=1)` ile müşteri veya siz onay vermeden bir sonraki adıma asla geçilmemesini garanti eder.
3. **"Yapay zekanın parçaladığı işleri sürükle bırak ile değiştirebileyim"** ihtiyacı: İnteraktif önizleme modalı ile tam kontrolü insana verir; AI önerir, insan düzenler ve yönetir.

Bu belge, ekiple yapılacak toplantıda teknik ve operasyonel tüm soruların yanıtını eksiksiz sunmaktadır.
