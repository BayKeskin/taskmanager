# Docs (Dökümanlar) Modülü Onarım Takip Listesi (ONARIM_TAKIP.md)

## Tespit Edilen Eksikler ve İyileştirme Maddeleri

- [x] 1. [Görsel/CSS] Eski sistemdeki zengin Quill editör tipografi kuralları (`.ql-editor h1`, `h2`, `h3`, `h4`, `p`, ardışık boş satır temizleme kuralları, `strong`, `code`, `pre`, `blockquote`, `ul`, `ol`) `Modules/Docs/Assets/css/docs.css` içine taşınmamış.
- [x] 2. [Görsel/CSS] Eski sistemdeki gelişmiş tablo stilleri (`.ql-editor table`, `tr`, `td`, `th`, ilk satır başlık vurgusu, zebra satır renkleri ve yatay scroll desteği) `docs.css` dosyasında eksik.
- [x] 3. [Görsel/CSS] Açık tema (Light Theme) desteği kuralları (`[data-theme="light"] .cu-doc-paper`, `#rawSourceTextarea`, `.ql-code-block`, `.ql-editor blockquote`) `docs.css` içinde eksik.
- [x] 4. [İşlev/JS] Mobil görünümde dökümanlar çekmecesini açan `.cu-mobile-docs-toggle` butonunun click event listener'ı ve mobil toggle davranışı eksik.
- [x] 5. [Görsel/CSS] Sol döküman listesinde (`cu-doc-item-title`) döküman başlıklarının metin taşma (`text-overflow: ellipsis`) ve esneklik davranışı için kapsayıcı stillerinin tam uyumu.
