# OpenCode gece görev prompt'u — TARDIS tüm kalan fazlar

Kullanıcı uyurken, soru sormadan çalışacak ajan için prompt. Genel çalışma kuralları ve mimari özeti için bkz. `docs/OPENCODE_PROMPT.md`; kalan işlerin güncel listesi `docs/backlog.md`. İş bittikçe buradaki sıra güncellenmeli.

````text
Sen TARDIS projesinde GECE BOYUNCA, kullanıcı uyurken, TEK BAŞINA çalışıyorsun. Kullanıcıya SORU SORMA, onay bekleme, durup cevap bekleme. Belirsiz bir durumda en güvenli/muhafazakâr seçeneği seç, nedenini not defterine yaz ve devam et. Kullanıcı sabah notlara bakıp seninle birlikte gözden geçirecek.

Proje: Laravel admin paketi (`yellow-three/tardis`, Laravel 13, Livewire 4, DaisyUI 5, Pest, Pint). Dizin: /home/abdurrahman/Projects/yellow-three/tardis

## 0. Not defteri (en önemli kural)
Repo DIŞINDA, şu dosyaya yaz: /home/abdurrahman/Projects/yellow-three/tardis-night-notes.md (yoksa oluştur; repo içine koyma, commit'leme). Her faz bitiminde ve her önemli kararda EKLE (üzerine yazma). Şunları yaz:
- Başladığın/bitirdiğin faz, branch adı, PR linki, commit SHA, test sayısı.
- Verdiğin her varsayım/karar ve gerekçesi ("X belirsizdi, Y'yi seçtim çünkü …").
- Atladığın, yarım bıraktığın veya geri aldığın işler ve nedeni (hata çıktısıyla).
- Gördüğün ama kapsam dışı bıraktığın hatalar/riskler.
- Sabah kullanıcıdan istediğin kararlar (numaralı liste).
Dosyanın başında "KULLANICIYA SORULACAKLAR" bölümü olsun; sabah en üstte okunsun. Gece sonunda dosyanın en üstüne 10 satırı geçmeyen bir özet yaz.

## 1. Önce oku (kod yazmadan)
1. `.claude/AGENTS.md` — Livewire kuralları: yalnızca SFC/MFC anonim sınıflı bileşenler (`new class extends Component`), `class X extends Component` YASAK, sınıf `}; ?>` ile biter, `render()` yok, sayfalarda `#[Layout]`, alt bileşenlerde yok, `#[Title]` çeviri anahtarı taşır.
2. `~/.claude/CLAUDE.md`, `~/.git-rules/AGENTS.md` — git kuralları.
3. `docs/backlog.md`, `docs/OPENCODE_PROMPT.md` (çalışma kuralları + mimari özeti), `RELEASE_NOTES.md`, `UPGRADE.md`, `docs/constraints.md`, `docs/JS.md`, `docs/PLUGIN_GUIDE.md`, `research/06`, `research/07`.

## 2. Git kuralları — GECE İÇİN ÖZEL
- `master`'a ve `feat/modern-admin-redesign`'e DOĞRUDAN commit YOK. Hiçbir PR'ı MERGE ETME (kullanıcı uyuyor, onay veremez).
- Çalışmayı YIĞIN (stack) olarak yap: ilk faz branch'ini `feat/modern-admin-redesign`'den aç, sonraki her faz branch'ini bir ÖNCEKİ faz branch'inden aç. Her PR'ın `--base`'i bir önceki faz branch'i (ilkinin base'i `feat/modern-admin-redesign`). PR açıklamasına "Stack: #önceki → bu" yaz.
- Conventional Commits, "neden"i anlat. `.env`, `vendor/`, `node_modules/`, build çıktısı (`dist/`), sır asla commit edilmez. `--no-verify` YASAK. `git push --force`, `git reset --hard` (kendi commit'lerin dışında), `git checkout -- <dosya>` (commitlenmemiş işi siler!), branch/dosya silme YASAK.
- Başka birinin değişikliğine dokunma; sadece kendi işini commit'le.
- Yalnızca testler yeşilken commit at. Her commit öncesi: `vendor/bin/pint` ve `vendor/bin/pest --compact`. CSS/JS değiştiyse `npm run build` (ama `dist/` commit'lenmez).

## 3. Kalite kuralları
- Önce test (kırmızı → yeşil). Yeni ekran: ability + `boot()` yetki kapısı (`app(BreadAuthorization::class)->authorizeAbility(...)`), menü gizleme, `AdminScreenAuthorizationTest` veri setine ekleme, `lang/en` + `lang/tr`, yeni yetki `Abilities` + `admin()` listesine.
- `tests/Unit/NoHardcodedTextTest.php` görünür sabit metni, `tests/Unit/LangFilesTest.php` EN/TR eşitliğini yakalar.
- Girdi doğrulama: istekten gelen hiçbir değer SQL kolonuna, dosya yoluna, sınıf/bileşen adına, HTML'e, `Artisan::call` parametresine doğrulanmadan gitmez (örnek: `src/Bread/BreadQuery.php`, `src/Http/Controllers/AssetController.php`).
- Dosyalar <800 satır, fonksiyonlar <50 satır, nesneleri mutasyona uğratma, hataları yutma.
- Her faz sonunda `docs/backlog.md` satırı, `RELEASE_NOTES.md`, gerekiyorsa `UPGRADE.md` ve `README.md` güncellenir. Eskimiş doküman bırakma (README'de kaldırılmış bir özelliği anlatan bölüm bulursan düzelt).

## 4. Geçmişte yaşanan hatalar — tekrar etme
Testler yeşil olması işin çalıştığını KANITLAMAZ. Her yeni davranışı gerçekten çalıştırıp doğrula.
- `Artisan::call($cmd, ['--flag'])` seçeneği YOK SAYAR; seçenekler dizi ANAHTARI olmalı (`['--flag' => true]`).
- Test SQLite'ta geçer, MySQL katı modda patlar: sayısal kolona (ör. `activity_logs.model_id`, unsignedBigInteger) metin yazma. Şemaya bak.
- Kaldırdığın bir mekanizmayı (ör. `themes-manifest.json`) kullanan başka yer (doctor, README, test) kalmasın; testin elle ürettiği bir dosyaya bel bağlama, gerçek kurulumda üretilir mi diye bak.
- Blade'de `<tag ... @if ($x->y) ... >` içinde `->` NoHardcodedText taramasını bozar; değişkene al veya `{!! !!}` ile ifadeyi çıkar. PHP yorumunda `?>` yazma. SFC dosyasında aynı `use` satırını iki kez ekleme.
- Livewire'da önceki doğrulama hatası kendiliğinden silinmez: ilgili eylemde `$this->resetErrorBag()`.
- `sed -i`/toplu replace sonrası diff'e bak; `git checkout <dosya>` commitlenmemiş işini siler. Python ile düzenlerken `str.index(...)` ilk eşleşmeyi alır; iç içe bloklarda yanlış yeri keser.
- Tam suite'te sıra bağımlı davranış çıkabilir (başka testin bıraktığı global middleware vb.). Bir test izole geçip suite'te kalıyorsa, nedenini araştır; çözemezsen testi izole et ve nota yaz.

## 5. Sıra (her biri ayrı branch + PR, yığın halinde)
Gece boyunca sırayla ilerle. Bir faz için makul çaba (aynı hatada 3 deneme) sonrası tıkandıysan: son yeşil commit'e dön (kendi commit'inden `git revert`, hard reset değil), sorunu nota yaz, SIRADAKİ faza geç.

**A. Faz 7 — Çok dilli içerik (R28)** — `feat/phase-7-translated-content`
Formda alan başına dil sekmeleri (şu an alt alta giriş), aktif dil bilgisi, doğrulama modu ("tüm diller zorunlu / yalnızca aktif dil", BREAD tanımında ayar), listede aktif dilin değeri, BREAD etiketleri (`name`, `name_plural`, alan etiketleri) ve menü başlıkları çevrilebilir. JSON kolon biçimi korunur (`Tardis\Classes\Translation`).

**B. Faz 5 — Medya (R26)** — `feat/phase-5-media`
`media_picker` alanı (registry, builder grubu, view, lang), kütüphaneden seçim, thumbnail + kırpma, dosya adı şablonu `{uid}/{date:Y/m}/{random:8}` (yalnızca beyaz liste token'lar), tek yükleme doğrulaması (`FileField` da aynı yol), `Filter\Media` sözleşmesi.

**C. Faz 3b — Layout ve builder UX (R32)** — `feat/phase-3b-layouts`
BREAD tanımında `list` ve `view` layout'ları, builder'da sürükle-bırak + 6'lık genişlik ızgarası (Alpine; klavye alternatifi şart), yan çekmecede alan seçenekleri, builder liste ekranında layout sayıları + backup, `legend`/bölüm başlığı. Mevcut JSON tanımlar geriye çalışmalı.

**D. Faz 2 kalanı (R23)** — `feat/phase-2-rest`
V2 lifecycle (`browse/read/edit/add/store/update($old)/stored/updated`), `addAfterFormField`, add/edit ayrı kural seti, çevrilebilir doğrulama mesajı, dizi elemanı doğrulaması, kalan tipler (Coordinates, RichText, Repeater, SimpleArray, çoklu select).

**E. Faz 3 kalanı (R24)** — `feat/phase-3-rest`
Sütun filtresi, adlandırılmış filtre/scope, ilişki kolonları (eager load + N+1 uyarısı), browse accessor'ları, aksiyon bazlı layout.

**F. Küçükler** — `feat/small-leftovers`
- Faz 4: `Filter\Widgets/Layouts/Media` sözleşmeleri, plugin bağımlılık gösterimi.
- Faz 6: menü ve dashboard sürükle-bırak sıralama, iç içe menü, Settings'te varsayılan tema seçiminin özel temalardan beslenmesi.
- Faz 1b: marka logosu/favicon/sidebar arka planı ayarları, dropdown bileşeni.
- R33: `config('tardis.models')` model haritası.
- R15: `docs/notes.md` B1, B2, B4, B5'i güncel kodda yeniden doğrula; geçerliyse düzelt, değilse backlog'da kapat.
- Güvenlik: sistem komut çalıştırıcısı ve log görüntüleyici için bir kez daha karşı-gözle inceleme (yetki, doğrulama, log).

## 6. Bitirme
Her faz için: `vendor/bin/pest --compact` tamamen yeşil, `vendor/bin/pint` temiz, yetki + lang + test eksiksiz, doküman güncel, PR açık (yığın base'iyle), not defteri güncel. Tüm fazlar bitince (veya vakit/yetenek bitince) en üstteki özeti yaz ve DUR. Hiçbir şeyi merge etme, master'a dokunma, kullanıcıya soru sorma.
````
