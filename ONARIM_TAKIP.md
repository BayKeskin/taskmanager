# Billing (Hakediş & İş Teslim Raporu) Modülü Onarım Takip Listesi (ONARIM_TAKIP.md)

## Tespit Edilen Durum ve İyileştirme Maddeleri

- [x] 1. [Görsel/CSS] `/billing-report?project_id=1` şablonu, kurumsal TaskManager Enterprise antetli kağıt düzeninde (header, marka logosu, proje bilgileri, teslim tarihi) temiz render ediliyor.
- [x] 2. [Görsel/CSS] 4'lü KPI kartı (Tamamlanan Görev, Harcanan Efor, Tahmini Efor, Toplam Hakediş) doğru sayısal veriler ve renk kodlamalarıyla hizalı.
- [x] 3. [Görsel/CSS] Görev döküm tablosu, ara toplamlar, KDV/genel toplam ve alt imza blokları (Teslim Eden, Teslim Alan) hizalı ve taşma yapmıyor.
- [x] 4. [İşlev/JS] Üst eylem çubuğundaki `⬅ Raporlara Dön` ve `🖨️ Yazdır / PDF İndir` (`btnBillingPrint` & `window.print()`) butonları hatasız çalışıyor.
- [x] 5. [Görsel/CSS] `@media print` kuralları ile yazdırma esnasında üst aksiyon çubuğu gizleniyor, kenar boşlukları sıfırlanıyor ve A4 formatında temiz çıktı veriliyor.
- [x] 6. [Taşınabilirlik/Portability] `billing_report.php` ve `board.js` içindeki stil ve geri dönüş linkleri `baseUrl()` ile dinamikleştirilerek her dizinde bağımsız çalışabilir kılındı.
