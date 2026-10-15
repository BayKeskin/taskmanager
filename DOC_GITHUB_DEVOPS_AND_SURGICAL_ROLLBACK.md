# 🐙 GitHub DevOps, Güvenli Otomatik Push, Şema Farkı & Cerrahi Rollback Kılavuzu

> **Doküman Kodu:** DOC-DEVOPS-01  
> **İlgili Proje:** [01] Task Manager & Tüm Entegre Projeler  
> **Sürüm:** v1.0 (Enterprise Otomasyon)  
> **Tarih:** Ekim 2026  

---

## 📌 1. Genel Bakış ve Sistemin Amacı

TaskManager AI DevOps motoru; yapay zeka ajanlarının (Antigravity, Claude, Codex vb.) yürüttüğü yazılım geliştirme süreçlerini **tam otonom, güvenli ve geri döndürülebilir** hale getirmek için geliştirilmiştir.

### Temel Hedefler:
1. **Sıfır Manuel Git İşlemi:** Kullanıcının GitHub sitesine gidip manuel repo açma, token girme veya terminalde `git push` yazma ihtiyacını tamamen ortadan kaldırmak.
2. **Güvenli Kimlik Doğrulama:** GitHub Personal Access Token (PAT) bilgisini asla diskteki `.git/config` veya URL içine yazmadan, her push işleminde anlık bellek üzerinden (`http.extraheader`) iletmek.
3. **Mevcut Projeleri Koruma:** Zaten bir GitHub reposuna bağlı olan projeleri (`git remote get-url origin`) bozmadan tanımak; yalnızca reposu olmayan projeler için otomatik **Private Repository** açmak.
4. **Çift Yönlü Rollback (Kod + Veritabanı):** Ajanın yaptığı iş beğenilmediğinde tek tıkla hem Git commit'ini geri almak hem de MySQL'de açtığı tablo ve sütunları temizlemek.
5. **AI Cerrah Modu (AI Surgeon):** Geri alınmak istenen görevden sonra başka görevler yapıldıysa ve doğrudan `git revert` çakışma (conflict) üretiyorsa, yapay zekayı cerrahi olarak görevlendirip diğer görevlere dokunmadan sadece o görevi cımbızla sistemden ayıklatmak.

---

## 🔑 2. GitHub Bağlantısı ve Güvenli Token Mimarisi

### 2.1 Bağlantı Adımları
1. TaskManager Kanban panosunda üst barda yer alan **`🐙 GitHub Bağla`** butonuna tıklanır.
2. Açılan modalda:
   - **GitHub Kullanıcı Adı:** `BayKeskin` (veya ilgili organizasyon adı)
   - **Personal Access Token (PAT):** `repo` yetkisine sahip `ghp_...` token yapıştırılır.
3. **"Kaydet ve Bağla"** dendiğinde bilgiler `settings` tablosunda saklanır ve üst buton otomatik olarak **`🐙 [KullanıcıAdı]`** haline gelir.

### 2.2 Güvenlik Mimarisi (`http.extraheader`)
Geleneksel sistemlerde token `https://username:token@github.com/...` şeklinde remote URL içine kaydedilir. Bu durum `.git/config` dosyasının çalınması veya paylaşılması halinde büyük bir güvenlik açığı doğurur.

TaskManager'da:
- Remote URL tertemiz kalır: `https://github.com/BayKeskin/proje.git`
- Push ve Fetch komutları yürütülürken token dinamik olarak Base64 encode edilir ve tek seferlik parametreyle gönderilir:
  ```bash
  git -c http.extraheader="AUTHORIZATION: basic [BASE64_TOKEN]" push origin [BRANCH]
  ```
- Komut bittiği an token bellekten silinir; diskte hiçbir iz bırakmaz.

---

## 🚀 3. Otomatik Repo Açma & Proje Eşitleme (GitHub Sync)

Bir proje seçildiğinde üst barda **`🚀 GitHub Sync`** butonu görüntülenir. Bu butona basıldığında `Helper::initAndSyncProjectGithubRepo($projectId)` fonksiyonu şu aşamaları yürütür:

```
[Kullanıcı 'GitHub Sync' Butonuna Basar]
                   │
                   ▼
       Projeye ait .git var mı?
        ├── [EVET] ──► git remote get-url origin kontrol edilir.
        │              └── Uzak repo varsa MEVCUT REPO KORUNUR, veritabanına bağlanır.
        │
        └── [HAYIR] ─► GitHub REST API (POST /user/repos) tetiklenir.
                       ├── Otomatik Private (Gizli) Repository açılır.
                       ├── .gitignore yoksa otomatik oluşturulur (.env, /vendor/, *.sql).
                       ├── git init & git config kimlikleri atanır.
                       └── İlk commit atılıp GitHub'a anında pushlanır.
```

---

## ⚡ 4. Görev Tesliminde Otomatik Push & Dinamik Branch Tespiti

Ajan görevi tamamlayıp `Helper::submitTaskDirectly()` çağırdığında sistem arka planda şu işlemleri otomatik icra eder:

1. **Dinamik Branch Keşfi:** Sabit `main` varsayımı yapılmaz. `git rev-parse --abbrev-ref HEAD` çalıştırılarak projenin aktif branch'i (`main`, `master`, `dev` vb.) dinamik olarak tespit edilir.
2. **Otomatik Commit & Push:** Ajan push yapmayı unutsa dahi PHP backend'i diski tarar, `git add -A && git commit -m "feat(TSK-xxx): [Başlık]"` oluşturur ve güvenli token ile GitHub'a pushlar.
3. **Mühürleme:** Üretilen resmi Commit Hash (Örn: `2fdaf05...`) `task_submissions.commit_hash` sütununa kaydedilir.
4. **Bağlantı Üretimi:** GitHub commit diff linki (`https://github.com/owner/repo/commit/hash`) görev çekmecesine doğrudan bağlanır.

---

## 🗄️ 5. 5 KB Şema Özeti & `.migrations/{task_code}_down.sql` Doktrini

Ajanların veritabanında yaptığı değişiklikleri geri alabilmek için sıfır disk maliyetli çift katmanlı bir diff sistemi uygulanır:

1. **Görev Başlarken (Pre-Task Snapshot):**
   - Görev `in_progress` durumuna geçtiğinde MySQL `information_schema.COLUMNS` taranarak sadece tablo ve sütun adları JSON formatında (~5 KB) `snap_task_{id}.json` dosyasına alınır.
2. **Ajanın Geri Alma SQL'i Doktrini:**
   - Ajan veri tabanında `INSERT`, `CREATE TABLE` veya `ALTER TABLE` yaparsa, projenin `.migrations/{task_code}_down.sql` dosyasına ters SQL komutlarını yazar.
3. **Görev Tesliminde (Post-Task Diff & 0-Byte Temizlik):**
   - Teslimat anında önceki 5 KB'lık özet ile güncel şema kıyaslanır. Yeni tablo açılmışsa `DROP TABLE`, yeni sütun eklenmişse `ALTER TABLE DROP COLUMN` SQL'i otomatik üretilir.
   - Ajanın yazdığı `.migrations/{task_code}_down.sql` içeriği ile birleştirilerek `task_submissions.rollback_sql` alanına kaydedilir.
   - **0-Byte Kuralı:** Geçici `snap_task_{id}.json` dosyası derhal diskten silinir (`@unlink`), sunucuda yer kaplamaz.

---

## ⏪ 6. Cerrahi Geri Alma (Rollback Engine) & AI Cerrah (AI Surgeon)

Görev çekmecesindeki **`⏪ Değişiklikleri Geri Al (Rollback)`** butonuna basıldığında iki farklı akış devreye girer:

### Senaryo 1: Çakışmasız Temiz Geri Alma (Safe Rollback)
1. Motor önce kuru bir test yapar: `git revert --no-commit [HASH]`.
2. Eğer sonraki görevlerle hiçbir çakışma yoksa:
   - Git revert commit'i oluşturulur ve GitHub'a pushlanır.
   - `task_submissions.rollback_sql` içindeki MySQL komutları çalıştırılarak veritabanı eski haline döndürülür.
   - Teslimat kaydı `is_reverted = 1` olarak işaretlenir ve çekmecede **`⏪ Geri Alındı`** rozeti belirir.

### Senaryo 2: Bağımlılık & Çakışma Durumu (AI Cerrah Devrede)
Eğer o görevden sonra yapılan başka görevler aynı dosya ve satırlara dokunmuşsa doğrudan `git revert` hata verir.

Bu durumda:
1. Sistem çalışma ağacını anında ilk haline geri çeker (`git revert --abort || git reset --hard HEAD`). Kodlar asla bozulmaz.
2. Ekrana şu onay penceresi gelir:
   > *"Bağımlılık / Çakışma Tespit Edildi! Bu görevden sonra gelen görevler bu kodların üstüne inşa edilmiş. 🤖 AI Cerrah Modunu (AI Surgeon) devreye alıp çakışmayı otomatik analiz edip çözerek geri alma işlemini tamamlamak ister misiniz?"*
3. **Onay Verilirse:** Görev revizyona (`modification_requested`) çekilir ve yapay zeka ajanına şu kesin cerrahi talimat gönderilir:
   > *"DİKKAT: Bu görevden SONRA yapılan diğer görevlerin kodlarına ve özelliklerine KESİNLİKLE ZARAR VERMEDEN, cımbızla sadece bu görevin eklediği kodları ve veritabanı tablolarını sistemden temizle, canlı test et ve teslim et!"*

---

## 🎛️ 7. Kullanıcı Arayüzü Butonları Referansı

| Arayüz Elemanı | Konum | Görev ve İşlev |
| :--- | :--- | :--- |
| **`🐙 GitHub Bağla`** | Kanban Üst Filtre Barı | GitHub kullanıcı adı ve PAT token yapılandırma modalını açar. |
| **`🚀 GitHub Sync`** | Kanban Üst Filtre Barı | Aktif projeyi kontrol eder, reposu yoksa private repo açar ve pushlar. |
| **`#hash Rozeti`** | Görev Çekmecesi (Teslimat) | Bu görevi oluşturan son Git commit kodunu gösterir. |
| **`🔗 GitHub'da İncele (Diff)`** | Görev Çekmecesi (Teslimat) | Görevde değişen tüm satırları GitHub üzerinde satır satır renkli açar. |
| **`🗄️ Veritabanı Etkisi`** | Görev Çekmecesi (Teslimat) | Görevin MySQL'de açtığı tablo veya sütunların özetini listeler. |
| **`⏪ Değişiklikleri Geri Al`** | Görev Çekmecesi (Teslimat) | Kodu ve veritabanını diğer görevleri bozmadan cerrahi olarak geri alır. |

---

## ❓ 8. Sıkça Sorulan Sorular

**S: Projemde zaten bir GitHub reposu vardı, bu sistem benim repomu bozar mı?**  
*C: Kesinlikle hayır. `Helper::initAndSyncProjectGithubRepo` fonksiyonu ilk adımda `git remote get-url origin` kontrolü yapar. Eğer projede önceden tanımlı bir repo varsa ona dokunmaz, sadece TaskManager ile ilişkilendirir.*

**S: Personal Access Token (PAT) süresi dolarsa ne olur?**  
*C: Üst bardaki `🐙 GitHub Bağla` butonuna tıklayıp yeni token'ınızı yapıştırmanız ve "Kaydet" demeniz yeterlidir. Tüm projeler anında yeni token ile çalışmaya devam eder.*

**S: Geri aldığım bir görevi tekrar açabilir miyim?**  
*C: Evet. Geri alınan bir görev tamamlandıysa bile yöneticiler tarafından tekrar düzenlenebilir veya yeni bir görev açılarak süreç baştan başlatılabilir.*
