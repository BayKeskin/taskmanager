# GithubDevops Modülü Onarım Takip Listesi (ONARIM_TAKIP.md)

## Tespit Edilen Görsel ve İşlevsel Eksikler

- [x] 1. [Görsel/CSS] GitHub Ayarları Modalında (`#modalGithubSettings`) koyu tema uyumsuzluğu ve donuk tasarım; input alanları, odaklanma efektleri (`:focus`), başlık ve butonlar ClickUp karanlık tema tokenları (`var(--bg-card)`, `var(--brand-primary)`, `var(--border-subtle)`) ile uyumlu değil, kutu gölgeleri ve degrade vurguları eksik.
- [x] 2. [İşlev/Veri] GitHub Ayarları Modalı açıldığında veritabanında (`settings` tablosunda) kayıtlı `github_username` ve `github_token` değerleri modal inputlarına (`#ghUsernameInput`, `#ghTokenInput`) otomatik doldurulmuyor (pre-fill eksik).
- [x] 3. [İşlev/JS] Modaldaki şifre gizleme/gösterme (göz ikonu / toggle password visibility) özelliği eksik; Personal Access Token (PAT) girilirken veya kontrol edilirken kullanıcı tokenını göremiyor.
- [x] 4. [Görsel/Etkileşim] Görev Detay Çekmecesi (`task_drawer.php`) içindeki "Cerrahi Rollback" (`rollbackTask`) butonu ve Git Commit/Diff bağlantısı için stil sınıfları ad-hoc inline stiller yerine `Modules/GithubDevops/Assets/css/githubdevops.css` içine taşınmalı ve modern rozet görünümüne kavuşturulmalı.
- [x] 5. [HMVC & Denetim Kilidi] Yapılan tüm görsel ve işlevsel onarımların ardından `php tests/audit_module.php GithubDevops` çalıştırılarak 0 kural ihlali (`[PASS]`) doğrulanmalı ve canlı tarayıcıda test kanıtı sunulmalı.
