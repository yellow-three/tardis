# OpenCode görev prompt'u — TARDIS kalan işler

Bu dosya, OpenCode'a (veya başka bir kod ajanına) yapıştırılacak görev prompt'udur. Kalan işlerin güncel listesi `docs/backlog.md`'dedir; iş bittikçe orayı ve bu dosyadaki "Yapılacaklar"ı güncelleyin.

````text
Sen TARDIS projesinde çalışıyorsun: Laravel admin paketi (`yellow-three/tardis`, Composer paketi, Laravel 13, Livewire 4, DaisyUI 5, Pest, Pint). Proje dizini: /home/abdurrahman/Projects/yellow-three/tardis

## Önce oku (kod yazmadan)
1. `.claude/AGENTS.md` — Livewire kuralları. Yalnızca SFC/MFC anonim sınıflı bileşenler; `class X extends Component` YASAK; sınıf `}; ?>` ile biter; `render()` yok; sayfalarda `#[Layout]`, alt bileşenlerde yok; `#[Title]` çeviri anahtarı taşır.
2. `~/.claude/CLAUDE.md` ve `~/.git-rules/AGENTS.md` — git kuralları.
3. `docs/backlog.md` — yol haritası. R22–R33 satırlarındaki durumlar güncel. Kalan işler oradan.
4. `RELEASE_NOTES.md`, `UPGRADE.md`, `docs/JS.md`, `docs/PLUGIN_GUIDE.md`, `research/06` ve `research/07` (Voyager karşılaştırma notları).

## Git ve çalışma kuralları (zorunlu)
- `master`'a ve `feat/modern-admin-redesign`'e doğrudan commit YOK. Entegrasyon branch'i `feat/modern-admin-redesign`. Her faz için ondan yeni branch aç (`feat/phase-8-install`, `feat/phase-7-translated-content` ...), commit'le, push'la, `gh pr create --base feat/modern-admin-redesign` ile PR aç.
- Commit mesajları Conventional Commits, "neden"i anlatsın. `.env`, `vendor/`, `node_modules/`, build çıktısı, sır asla commit edilmez. Hook'u `--no-verify` ile geçme.
- PR'ı kullanıcı onayı olmadan MERGE ETME. Merge kararı kullanıcıya ait; her PR için ayrıca sor.
- Başkasının (kullanıcının) çalışma ağacındaki değişikliklere dokunma; yalnızca kendi değişikliklerini commit'le.
- Testler yeşil değilken commit atma. Her commit öncesi: `vendor/bin/pint` ve `vendor/bin/pest --compact`. CSS/JS değiştiysen `npm run build`.
- Test yazma, uygulamadan ÖNCE (kırmızı → yeşil). Yeni ekran: ability + `boot()` yetki kapısı (`app(BreadAuthorization::class)->authorizeAbility(...)`), menü gizleme, `AdminScreenAuthorizationTest` veri setine ekleme, `lang/en` + `lang/tr` anahtarları. `tests/Unit/NoHardcodedTextTest.php` görünür sabit metni yakalar; `tests/Unit/LangFilesTest.php` EN/TR eşitliğini ve placeholder'ları denetler.
- Girdi doğrulaması sınırda yapılır: istekten gelen hiçbir değer SQL kolonuna, dosya yoluna, sınıf/bileşen adına ya da HTML'e doğrulanmadan gitmez. Örnek yaklaşım: `src/Bread/BreadQuery.php` (kolon adları tanımdan gelir), `src/Http/Controllers/AssetController.php` (yalnızca kayıtlı hash).
- Sabitler: `docs/AGENTS.md`, `docs/constraints.md`.
- Dosyalar küçük kalsın (<800 satır), fonksiyonlar <50 satır, nesneleri mutasyona uğratma, hataları yutma.
- Her faz sonunda `docs/backlog.md` satırını, `RELEASE_NOTES.md` ve gerekiyorsa `UPGRADE.md` / `README.md`'yi güncelle.

## Mevcut mimari (kısa)
- Singleton yöneticiler: Menu, Widget, Settings, Formfield, Plugin, Theme, Action. `Tardis::addAction/replaceAction/manipulateActions`, `Tardis::addCss/addJs`.
- BREAD: JSON tanımlar (`storage`), `BreadDefinition`, `BreadQuery` (liste), `BreadSaver` (kaydetme), alanlar `Formfield::render()` view'ından `x-tardis::form-field` ile çizilir; tip seçenekleri `Formfield::$configurable`/`configure()`; alan varlıkları `Formfield::assets()`.
- Yetki: `Abilities` sabitleri, `BreadAuthorization`. `PermissionSeeder` `Abilities::admin()` listesinden üretir.
- Menü/dashboard/tema kullanıcı katmanları `storage/tardis/*.json` dosyalarında (`menus.json`, `dashboard.json`, `themes.json`, `preferences.json`), atomik yazım.
- Panel metinleri `lang/en` + `lang/tr`, `tardis::` ad alanı.

## Yapılacaklar — bu sırayla, her biri ayrı branch + PR
**1. Faz 8 — Kurulum ve sistem araçları (R29)**
- `tardis:install`: migrate + permission seed + ilk admin (`tardis:admin` mantığını yeniden kullan) + asset publish; idempotent; etkileşimsiz mod (`--no-interaction`, `--email`).
- `tardis:doctor`: ortam kontrolleri (PHP/Laravel sürümü, storage yazılabilirliği, `tardis_*` tabloları, yayınlanmış asset'in güncelliği, route cache uyarısı, authorization plugin durumu, BREAD tanımlarının model/tablo sağlığı). Çıktı hem konsol hem `--json`.
- Panelde "Sistem" sayfası (doctor sonucu, ability `view system`).
- Salt okunur log görüntüleyici (`view logs`): yalnızca `storage/logs` içindeki `*.log`, dosya adı beyaz liste/regex ile doğrulanır, son N satır, büyük dosyada kuyruktan okuma.
- İzin listeli komut çalıştırıcı (`run commands`): config'te açıkça listelenen artisan komutları, `local` ortam dışında varsayılan KAPALI, argümanlar doğrulanır, her çalıştırma activity log'a yazılır. Keyfi komut çalıştırma yok.
- İsteğe bağlı demo veri seeder'ı. Plugin iskeleti güncel kalsın.

**2. Faz 7 — Çok dilli içerik (R28)**
- Formda alan başına dil sekmeleri (şu an locale başına alt alta giriş var), aktif dil bilgisi, "tüm diller zorunlu / yalnızca aktif dil" doğrulama modu (BREAD tanımında ayar), listede aktif dilin değeri, BREAD etiketleri (`name`, `name_plural`, alan etiketleri) ve menü başlıkları çevrilebilir. JSON kolon biçimi korunur (`Tardis\Classes\Translation`).

**3. Faz 5 — Medya entegrasyonu (R26)**
- `media_picker` alan tipi (registry'ye ekle, builder grubu, view, lang), medya kütüphanesinden seçim, thumbnail + kırpma, dosya adı şablonu `{uid}/{date:Y/m}/{random:8}` (yalnızca beyaz listedeki token'lar), tek yükleme doğrulaması (BREAD `FileField` da aynı yolu kullanır), isteğe bağlı `Filter\Media` sözleşmesi.

**4. Faz 3b — Layout ve builder arayüzü (R32)**
- BREAD tanımında `list` ve `view` layout'ları, builder'da sürükle-bırak + 6'lık genişlik ızgarası (Alpine ile; erişilebilir klavye alternatifi bırak), yan çekmecede alan seçenekleri, builder tablo listesinde layout sayıları + backup, `legend`/bölüm başlığı. Mevcut JSON tanımlar geriye çalışmaya devam etmeli.

**5. Faz 2 kalanı (R23)**
- V2 lifecycle (`browse/read/edit/add/store/update($old)/stored/updated`) alan kancaları, `addAfterFormField`, add/edit için ayrı kural seti, çevrilebilir doğrulama mesajı, dizi elemanı doğrulaması, kalan tipler (Coordinates, RichText, Repeater, SimpleArray, çoklu select).

**6. Faz 3 kalanı (R24)**
- Sütun filtresi, adlandırılmış filtre/scope, ilişki kolonları (eager load + N+1 uyarısı), browse accessor'ları, aksiyon bazlı layout.

**7. Küçükler**
- Faz 4: `Filter\Widgets/Layouts/Media` sözleşmeleri, plugin bağımlılık gösterimi.
- Faz 6: menü ve dashboard için sürükle-bırak sıralama, iç içe menü, Settings'te varsayılan tema seçiminin özel temalardan beslenmesi.
- Faz 1b: marka logosu/favicon/sidebar arka planı ayarları, dropdown bileşeni.
- R33: `config('tardis.models')` model haritası.
- R15: BREAD'in açık 4 bulgusunu (`docs/notes.md` B1, B2, B4, B5) güncel kodda yeniden doğrula; hâlâ geçerliyse düzelt, değilse backlog'da kapat.
- README ve durum dosyalarını (`PROJECT_STATUS.md` vb.) son fazlara göre güncelle.

## Bitirme ölçütü (her faz için)
`vendor/bin/pest --compact` tamamen yeşil, `vendor/bin/pint` temiz, yeni ekranlar yetki kapısı + lang + test içeriyor, backlog/RELEASE_NOTES güncel, PR açık. PR açıklamasına "ne", "kapsam dışı", "test planı" yaz. Merge'i kullanıcıya bırak. Bir faz belirsizse tahmin yürütme, soru sor.
````
