# ExcelExport (Excel & CSV Görev Dışa Aktarımı) Onarım Takip Listesi (ONARIM_TAKIP.md)

## Tespit Edilen Durum ve İyileştirme Maddeleri

- [x] 1. [HMVC Modül Yapısı & İzolasyon] ExcelExport modülü `Modules/ExcelExport/` altında `module.json`, `init.php`, `Controllers/`, `Models/`, `Lang/` ve `Assets/` bileşenleriyle tam izole HMVC standardına kavuşturuldu.
- [x] 2. [UTF-8 BOM ve Veri Dışa Aktarım Bütünlüğü] Eski sistemdeki `TaskModel::getTasksForExport` mantığı `ExcelExportModel::getTasksForExport` katmanına aktarıldı; Excel için `\xEF\xBB\xBF` UTF-8 BOM byte sırası ve noktalı virgül (`;`) ayracı ile Türkçe/Almanca karakterlerin Excel'de kusursuz açılması garantilendi.
- [x] 3. [Kapsamlı Rapor Alanları] Görev Kodu, Görev Başlığı, Mevcut Durum, Öncelik, Atanan Kişi, Oluşturan Kişi, Başlangıç Tarihi, Termin Tarihi, Alt Görev Yüzdesel İlerlemesi (% Progress) ve Oluşturulma Zamanı kolonları eksiksiz üretildi.
- [x] 4. [Çok Dilli Başlıklar (TR/EN/DE)] İndirilen CSV/Excel raporunun kolon başlıkları, durum isimleri ve öncelik etiketleri kullanıcının aktif dil tercihine (`?lang=tr`, `?lang=en`, `?lang=de`) göre dinamik olarak yerelleştirildi.
- [x] 5. [Statik Varlıklar ve AssetManager Kuyruğu] `Assets/css/excel_export.css` ve `Assets/js/excel_export.js` oluşturularak `init.php` üzerinden `AssetManager::enqueueCss/Js` ile kuyruğa alındı.
- [x] 6. [Rota ve Erişim Doğrulaması] `GET /taskmanager/kanban/export-excel?project_id=1` uç noktası doğrudan ve Kanban Araçlar menüsünden test edilerek canlı akışla CSV dosyasının başarıyla indirildiği doğrulandı.
- [x] 7. [Mimari Denetim Kilidi] `php tests/audit_module.php ExcelExport` çalıştırılarak 0 kural ihlali (`[PASS]`) tescillendi.
