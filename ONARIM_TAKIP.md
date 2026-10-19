# Board (Kanban & Liste Görünümü) Onarım Takip Listesi (ONARIM_TAKIP.md)

## Tespit Edilen Durum ve İyileştirme Maddeleri

- [x] 1. [Liste Görünümü & Eski Sistem Eşleştirmesi] Eski sistemdeki (`old/View/index/index.php`) tam özellikli liste görünümü (`cu-group`, `cu-task-list`, `cu-task-row`) yeni HMVC Board modülüne entegre edildi.
- [x] 2. [Durum Akordeonları & Sayaçlar] Görevler durumlarına göre gruplandı (`assigned`, `accepted`, `in_progress`, `modification_requested`, `submitted`, `completed`) ve durum rozetleri, sayaçlar ve dinamik filtreleme tam desteklendi.
- [x] 3. [İç İçe Alt Görev Ağacı (Nested Subtasks Tree)] Ana görevlerin altında yer alan alt görev ağacı (`cu-subtasks-tree`) açma/kapatma (toggle `▶/▼`), hızlı satır içi alt görev oluşturma (`quickSubtaskInput`), silme (`deleteSubtask`) ve detay çekmecesi açma işlevleri eksiksiz bağlandı.
- [x] 4. [Görünüm Değiştirici (View Switcher)] Üst başlık çubuğuna (`cu-view-header`) Kanban Panosu (`📌 Kanban`) ve Liste Görünümü (`📋 Liste`) arasında kesintisiz geçiş sağlayan şık görünüm değiştirici sekmeler eklendi (aktif proje ve filtre parametrelerini koruyarak).
- [x] 5. [HMVC & Model Ayrımı] `TaskModel::getSubtaskMap()` metodu eklenerek alt görevler veritabanı seviyesinde temiz bir şekilde çekildi; `BoardController` veri hazırlığını üstlenip `board.php` şablonuna aktardı.
- [x] 6. [Üç Dilli Sözlük Desteği] TR, EN ve DE dillerinde tüm liste grubu başlıkları, aksiyon butonları ve alt görev etiketleri senkronize edildi.
- [x] 7. [Sıfır İhlal & Güvenlik] `php tests/audit_module.php Board` çalıştırılarak 0 kural ihlali (`[PASS]`) doğrulandı.
