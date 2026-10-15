# 📍 Localhost Projelerinden TaskManager'a Eksik ve Hata İletme Kılavuzu
> **Görsel Pinleme (BugHerd Alternatifi) & Otomatik Görev Açma Rehberi**

Bu kılavuz, localhost üzerinde koşan herhangi bir web projesinden (örneğin **Ufka Yolculuk**, **Kitap Okuma**, **Benim Çarşım** veya **NewJobs**) TaskManager'a bulunan eksikleri, görsel hataları ve revizyonları **fotoğraflı, koordinatlı ve otomatik** olarak nasıl ileteceğinizi adım adım açıklar.

---

## 🌟 1. Genel Mantık ve Çalışma Prensibi

TaskManager, harici ücretli servisler (BugHerd, Marker.io, Pastel gibi) yerine **%100 yerli ve ücretsiz** çalışan `taskmanager-pin.js` istemci modülüne sahiptir.

### Sistem Nasıl İşler?
1. Ziyaret ettiğiniz projenin sağ alt köşesinde mor bir **`📍 Hata Bildir`** butonu yer alır.
2. Butona basıp ekrandaki herhangi bir hatalı öğeye (buton, menü, yazı, form alanı vb.) tıkladığınız anda:
   - Tarayıcı anında sayfanın **yüksek çözünürlüklü ekran görüntüsünü** çeker.
   - Tıkladığınız tam piksel noktasına sivri uçlu mor bir **📍 Teardrop Pin** damgalar.
   - Tıklanan öğenin tam **CSS Seçici Yolunu** (örn: `header > nav > a.btn-login`), ekran çözünürlüğünü ve sayfa linkini otomatik toplar.
3. Açılan şık pencerede hatanın başlığını ve açıklamasını yazıp **"TaskManager'a Gönder"** dediğiniz anda:
   - Görev anında TaskManager'daki ilgili projenin (örn: Ufka Yolculuk) panosunda **YAPILACAKLAR** sütununa düşer.
   - Ekran görüntüsü dosya olarak göreve bağlanır.
   - TaskManager'ın AI Ajanı (Antigravity) tanımlıysa otomatik haberdar olur ve eksik üzerinde çalışmaya başlar!

---

## 🚀 2. Ufka Yolculuk Projesine Nasıl Eklenir? (3 Farklı Yöntem)

İhtiyacınıza ve çalışma şeklinize göre aşağıdaki **3 pratik yöntemden birini** kullanabilirsiniz:

---

### 🔹 YÖNTEM 1: Proje Koduna 1 Satır Script Eklemek (En Kalıcı & Rahat Yol)

Ufka Yolculuk (`c:/wamp64/www/ufkayolculuk`) projenizin ana layout footer şablonuna veya `public/index.php` sayfasına `</body>` kapanış etiketinden hemen önce tek satır script ekleyin:

```html
<!-- TaskManager Görsel Pin & Hata Bildirici (Ufka Yolculuk İçin) -->
<script src="http://localhost/taskmanager/Public/js/taskmanager-pin.js" data-project="TKMDEV-01" async></script>
```

#### 🛡️ Sadece Localhostta Çalıştırma (Canlıya Çıkmama Koruması):
Eğer projenizi canlı sunucuya deploy ediyorsanız ve bu butonun canlıda görünmesini istemiyorsanız, PHP ortam kontrolü içine alabilirsiniz:

```php
<?php if (ENVIRONMENT === 'development' || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost'])): ?>
    <!-- Yalnızca yerel geliştirme ortamında çalışır -->
    <script src="http://localhost/taskmanager/Public/js/taskmanager-pin.js" data-project="TKMDEV-01" async></script>
<?php endif; ?>
```

---

### 🔹 YÖNTEM 2: Sıfır Kod — Tarayıcı Yer İmi (Bookmarklet - Kod Değiştirmeden Anında Kullanım)

Eğer Ufka Yolculuk projesinin kodlarına **hiç dokunmak istemiyorsanız**, tarayıcınızın (Chrome, Edge, Firefox vb.) yer imleri çubuğuna bir buton ekleyerek sayfayı gezerken tek tıkla pinleme aracını sayfaya yükleyebilirsiniz!

#### 📌 30 Saniyede Kurulum:
1. Tarayıcınızın **Yer İmleri Çubuğunda** boş bir yere sağ tıklayın ve **"Sayfa ekle..."** (veya *Yer İmi Ekle*) seçeneğine basın.
2. **Ad (Name):** `📍 TM: Ufka Yolculuk Eksik Bildir`
3. **URL (Adres):** Aşağıdaki tek satırlık kodu kopyalayıp adres kutusuna yapıştırın ve **Kaydet**'e tıklayın:

```javascript
javascript:(function(){var s=document.createElement('script');s.src='http://localhost/taskmanager/Public/js/taskmanager-pin.js';s.setAttribute('data-project','TKMDEV-01');document.body.appendChild(s);})();
```

#### 🎯 Nasıl Kullanılır?
1. Tarayıcınızda Ufka Yolculuk projesini açın (`http://localhost/ufkayolculuk/public/`).
2. Yer imlerindeki **`📍 TM: Ufka Yolculuk Eksik Bildir`** butonuna tıklayın.
3. Ekranın sağ alt köşesinde mor **`📍 Hata Bildir`** butonu hemen belirecektir!

---

### 🔹 YÖNTEM 3: PHP Scripti veya cURL / API ile Eksik Göndermek (Programatik Yol)

Eğer bir eksikliği veya test sonucunu doğrudan PHP kodundan veya komut satırından TaskManager'a göndermek isterseniz REST API uç noktasını kullanabilirsiniz:

```php
<?php
// Ufka Yolculuk'tan TaskManager'a doğrudan görev/eksik iletme fonksiyonu
function sendTaskToTaskManager($title, $description, $priority = 'medium') {
    $apiUrl = 'http://localhost/taskmanager/api/v1/visual-feedback';
    
    $payload = json_encode([
        'project_code' => 'TKMDEV-01', // Ufka Yolculuk Proje Kodu
        'title'        => $title,
        'description'  => $description,
        'priority'     => $priority, // low, medium, high, urgent
        'url'          => 'http://localhost/ufkayolculuk/public/',
        'selector'     => '#manual-report',
        'x'            => 0,
        'y'            => 0,
        'meta'         => [
            'reporter'   => 'PHP Backend / Test Senaryosu',
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// Örnek Kullanım:
// sendTaskToTaskManager('Kayıt Formu İlçe Seçimi Boş Geliyor', 'API 500 dönüyor, kontrol edilmeli.', 'high');
```

---

## 📋 3. Adım Adım Eksik / Hata Bildirme Süreci (Nasıl Yapılır?)

1. **Sayfayı Açın:** Ufka Yolculuk veya test ettiğiniz projeyi açın (örn: `http://localhost/ufkayolculuk/public/`).
2. **Pin Modunu Başlatın:** Sağ alttaki mor **`📍 Hata Bildir`** butonuna tıklayın.
   - Sayfanın üst kısmında turuncu renkli *"🎯 Pinleme Modu Aktif: Hatalı veya revizyon gereken öğeye tıklayın (Çıkmak için ESC)"* bildirim çubuğu açılır.
3. **Eksik Olan Yere Tıklayın:**
   - Fareyi sayfa üzerinde gezdirdiğinizde incelenen öğeler mor kesik çizgili çerçeveyle vurgulanır.
   - Hatalı veya revizyon gereken butonun, başlığın, görselin veya form elemanının tam üzerine tıklayın.
4. **Otomatik Ekran Görüntüsü & Pin Damgası:**
   - Tıkladığınız anda sayfanın tam ekran görüntüsü çekilir.
   - Tıkladığınız noktaya sivri uçlu mor bir **📍 Pin** damgalanır.
   - Açılan bildirim penceresinde çekilen damgalı ekran görüntüsünün küçük resim önizlemesi (thumbnail) yer alır.
5. **Bilgileri Doldurun:**
   - **Hedef Öğe:** Otomatik tespit edilir (örn: `button#btn-register` veya `.navbar > .nav-link:nth-child(2)`).
   - **Görev Başlığı:** Hatanın kısa özeti (örn: *"Giriş Yap butonuna basınca modal açılmıyor"*).
   - **Açıklama:** Detaylı eksik veya revizyon tarifi.
   - **Öncelik:** Normal / Yüksek / Acil.
6. **"TaskManager'a Gönder" Butonuna Basın:**
   - Ekranda yeşil onay bildirimi çıkar ve pin başarıyla TaskManager'a aktarılır.

---

## ⚡ 4. Gönderildikten Sonra TaskManager'da Ne Olur?

1. **Otomatik Görev Oluşturulur:** TaskManager bildirimi alır ve `TKMDEV-01-XXX` formatında yeni bir görev açar.
2. **Doğru Panoya Düşer:** `data-project="TKMDEV-01"` sayesinde görev doğrudan **Ufka Yolculuk Web** projesinin **YAPILACAKLAR** sütununa yerleşir.
3. **Ekran Görüntüsü İlişkilendirilir:** Çekilen resim `Public/uploads/pins/` klasörüne JPEG formatında kaydedilir ve hem görevin ek dosyalarına (`task_attachments`) eklenir hem de açıklama metninde gösterilir.
4. **Zengin Teknik Rapor İçeriği:** Görevin detayında geliştirici ve AI ajanın hatayı tek seferde çözebilmesi için şu bilgiler otomatik yazılır:
   - 🖼️ **Damgalı Tam Ekran Görüntüsü** (Pinin tam koordinatı işaretli)
   - 🌐 **Sayfa URL'si**
   - 🎯 **CSS Selektör Yolu** (Tam DOM Hiyerarşisi)
   - 📍 **Tıklanan Piksel Koordinatı** (`X: 768, Y: 645`)
   - 🖥️ **Ekran Çözünürlüğü ve Viewport** (örn: `1920x1080`)
   - 💻 **Tarayıcı ve İşletim Sistemi Bilgisi**
5. **Canlı Kanban Güncellemesi:** TaskManager panosu o anda açıksa sayfa yenilenmeden görev ekranda anlık olarak belirir (SSE / Server-Sent Events).
6. **Yapay Zeka (AI) Webhook Bildirimi:** Proje için tanımlanmış AI Webhook (`http://localhost:4000/webhook`) tetiklenir ve otonom yazılım ajanı görevi çözmek üzere otomatik devreye girer.

---

## 🗂️ 5. Sistemde Kayıtlı Projeler ve `data-project` Kodları

Diğer localhost projeleriniz için widget'ı kullanırken `data-project` parametresine ilgili proje kodunu yazmanız yeterlidir:

| Proje Adı | Proje Kodu (`data-project`) | Çalışma Alanı (Workspace) | Kanban Pano Adresi |
| :--- | :--- | :--- | :--- |
| **Ufka Yolculuk Web** | `TKMDEV-01` | `c:/wamp64/www/ufkayolculuk` | `http://localhost/taskmanager/board?project_id=3` |
| **Ufka Yolculuk Kitap Okuma** | `TKMDEV-02` | `c:/wamp64/www/kitap-okuma` | `http://localhost/taskmanager/board?project_id=4` |
| **Task Manager** | `01` | `c:/wamp64/www/taskmanager` | `http://localhost/taskmanager/board?project_id=1` |
| **Benim Çarşım E-Ticaret** | `TKMDEV-03` | `c:/wamp64/www/benimcarsim` | `http://localhost/taskmanager/board?project_id=6` |
| **Benim Çarşım Depo** | `TKMDEV-04` | `c:/wamp64/www/benimcarsim-depo` | `http://localhost/taskmanager/board?project_id=7` |
| **NewJobs Kariyer** | `NEWJOBS` | `c:/wamp64/www/mvcproje` | `http://localhost/taskmanager/board?project_id=10` |
| **Nakliyecim Mobil** | `NKLYCM` | `d:/MobilProjeler/nakliyecim` | `http://localhost/taskmanager/board?project_id=8` |
| **NetTakip Mobil** | `NETTAKIP` | `d:/MobilProjeler/sonuctakip` | `http://localhost/taskmanager/board?project_id=9` |

---

### 💡 Kitap Okuma Projesi İçin Örnek Bookmarklet
Eğer **Kitap Okuma** projesinde eksik bildirmek isterseniz tarayıcı yer imi olarak şu kodu kullanabilirsiniz:
```javascript
javascript:(function(){var s=document.createElement('script');s.src='http://localhost/taskmanager/Public/js/taskmanager-pin.js';s.setAttribute('data-project','TKMDEV-02');document.body.appendChild(s);})();
```

---

## 🔒 6. CORS ve Ağ Ayarları Hakkında Not

TaskManager REST API motoru (`Controller/api/index.php`), yerleşik olarak `Access-Control-Allow-Origin: *` başlığı ile çalışır.

Bu sayede:
- Ufka Yolculuk farklı bir sanal konakta (`http://localufkayolculuk.com/`),
- Farklı bir portta (`http://localhost:8080/`),
- Veya standart WAMP alt dizininde (`http://localhost/ufkayolculuk/`) çalışsa bile,
tarayıcının güvenlik (CORS) engeline takılmadan tüm görsel pin bildirimleri resimleriyle birlikte sorunsuz iletilir.
