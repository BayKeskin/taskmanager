# LiveDeploy (Canlı Dağıtım & Hosting Sihirbazı) Onarım Takip Listesi (ONARIM_TAKIP.md)

## Tespit Edilen Görsel ve İşlevsel Durum Maddeleri

- [x] 1. [Görsel/CSS] Dağıtım Sihirbazı Modalı (`#modalDeployWizard`) ClickUp koyu teması ile tam uyumlu modern gölgelendirme (`box-shadow: 0 16px 40px rgba(0,0,0,0.5), 0 0 24px rgba(0, 206, 201, 0.15)`), mikro-açılış animasyonu ve stepper geçiş efektleri ile güçlendirilmeli.
- [x] 2. [Görsel/CSS] Form girdi kutuları (`.deploy-select-main`, `.cu-form-control`), odaklanma çerçeveleri (`:focus`) ve butonlar (`#btnDeployNext`, `#btnDeployAction`, `#btnTestDeployPanel`) net kontrast ve pürüzsüz geçişlere kavuşturulmalı.
- [x] 3. [İşlev/JS] 3 Aşamalı Sihirbaz akışı (Adım 1: Barındırma & FTP ➔ Adım 2: Canlı Domain & DB & Git Diff ➔ Adım 3: Dağıtım, Sağlık Kontrolü & Cerrahi Rollback) adımları arasındaki geçişler ve modal açma/kapama fonksiyonları doğrulanmalı.
- [x] 4. [HMVC & Denetim Kilidi] Yapılan tüm görsel ve işlevsel iyileştirmelerin ardından `php tests/audit_module.php LiveDeploy` çalıştırılarak 0 kural ihlali (`[PASS]`) doğrulanmalı ve canlı tarayıcıda kanıt sunulmalı.
