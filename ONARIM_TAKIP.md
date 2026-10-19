# Calendar (Takvim & Gantt Şeması) Modülü Onarım Takip Listesi (ONARIM_TAKIP.md)

## Tespit Edilen Durum ve İyileştirme Maddeleri

- [x] 1. [Görsel/CSS] Gantt Şeması görünümü (`?tab=gantt`) zaman çizelgesi sütunları (günler, tarihler, bugün vurgusu) ve görev çubukları karanlık temada tam hizalı.
- [x] 2. [İşlev/JS] Gantt ölçek değiştirici (`scale=week` ve `scale=month`) ile zaman aralığı haftalık ve aylık görünümler arasında akıcı şekilde geçiş yapıyor.
- [x] 3. [Görsel/CSS] Standart Takvim görünümü (`?tab=calendar`) haftanın 7 günü (Pazartesi'den Pazar'a) aylık grid yapısında ve gün numaraları doğru sıralamayla render ediliyor.
- [x] 4. [Görsel/CSS] Görevler teslim tarihlerine (`due_date`) göre takvim hücrelerinde renkli durum/öncelik etiketleriyle listeleniyor.
- [x] 5. [İşlev/JS] Takvim navigasyon butonları (`Bugün`, `◀ Önceki Ay`, `Sonraki Ay ▶`) dinamik `baseUrl()` linkleriyle ayları kesintisiz değiştiriyor.
- [x] 6. [Taşınabilirlik/Portability] Tüm görünüm sekmeleri ve filtre butonları `baseUrl()` mimarisine geçirilerek her alt dizinde ve ana domainde çalışacak şekilde standardize edildi.
