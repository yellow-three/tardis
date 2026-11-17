# Aktif Notlar — docs/notes.md

Bu dosya **yalnızca aktif/açık** bulguları, denemeleri ve açık işleri tutar. **Kalıcı arşiv değildir.**

**Kural: Bir bulgu/iş tamamlandığında ilgili satırı/bölümü bu dosyadan SİL.** "✅ Çözüldü" olarak bırakma — arşiv git geçmişidir, defter yalnızca aktif maddeleri taşır. Kalıcı (silinmeyen) bilgi için bkz. `docs/voyager-tam-arsastirma.md` ve `research/05-tardis-bulgulari-ve-fix-oncelikleri.md`; Voyager karşılaştırması için `docs/voyager-karsilastirma.md`.

**Kapsam:** paketin açık bulguları. Kapananlar `RELEASE_NOTES.md` ve git geçmişinde; plan ve sıra `docs/backlog.md`'de.

---

# GÜNCELLEME (2026-11-17) — Proje incelemesi: açık bulgular

Kapsam: `src/`, `routes/`, `resources/views/pages`, migration'lar, CI ve dokümanlar okundu. Başlangıç durumu: `composer test` → 918 passed (2369 assertions), `composer lint` temiz. **Doğrulama** sütunu: *test* = geçici bir Pest testiyle yeniden üretildi (test sonra silindi), *kod* = yalnızca kod okumasıyla tespit edildi, çalıştırılarak doğrulanmadı. Hiçbir bulgu için kod değişikliği yapılmadı.

**Plan bağlantısı (2026-11-17, kabul edildi):** düzeltme sırası `docs/backlog.md` → *1.0 yol haritası*. **R34 (Faz 9, P0):** kapandı (2026-11-17, `fix/phase-9a-access-and-auth-flows`): B17, B18, B19, B20, B22, B23, B24, B25, B26, B31, B32, B68, B69, B70 — satırları silindi, ayrıntı `RELEASE_NOTES.md` ve git geçmişinde. **R35 (Faz 10, P1):** B21, B27, B28, B29, B30, B34–B43. **R37:** B33. **R39:** B41. **R45 (Faz 13):** B44–B47. **R56 (Faz 10b):** B52–B58. **R57 (Faz 10c):** B59–B67, B71–B76. Her düzeltme, bulguyu yeniden üreten kalıcı bir testle başlar; kapanan satır bu dosyadan silinir.

## Yüksek (HIGH)

| # | Bulgu | Kod ref | Doğrulama |
|---|-------|---------|-----------|

## Orta (MEDIUM)

| # | Bulgu | Kod ref | Doğrulama |
|---|-------|---------|-----------|
| B21 | **Yetki kontrolleri istek içinde önbelleklenmiyor.** Tüm izinlere sahip (super-admin olmayan) bir kullanıcıyla dashboard isteği 70 sorgu çalıştırdı; 66'sı `tardis_role_user` üzerindeydi (her `can()` çağrısı 1–2 sorgu, sidebar her öğe için soruyor). Kullanıcının izin listesi istek başına bir kez okunmalı. | `src/Auth/TardisAuthorizationPlugin.php` (`can`, `isSuperAdmin`, `holdsTardisPermission`) | test |
| B27 | **Medya "seçilenleri indir" çalışmıyor.** `downloadSelected()` ZIP'i üretip `download-zip` olayını yayınlıyor; olayı dinleyen JS/rota yok. Yan etkiler: sunucunun mutlak dosya yolu tarayıcıya gidiyor, ZIP `storage/app/temp/media-export.zip` altında kalıyor (temizlenmiyor, eşzamanlı kullanıcılar aynı dosyayı eziyor), seçimdeki klasörler sessizce atlanıyor. | `pages/media-browser/media-browser.php:~400-412`, `MediaManager::downloadZip()` | kod (grep: tek eşleşme) |
| B28 | **`BreadQuery::listing()` global query log durumunu bozuyor.** `DB::enableQueryLog()` / `disableQueryLog()` çağırıyor: host'un (veya Debugbar/Telescope'un) açtığı log kapanıyor; log zaten açıksa önceki sorgular da "yavaş sorgu" uyarılarına sayılıyor. `DB::listen` ya da önceki durumu geri yükleme gerekir. | `src/Bread/BreadQuery.php` (`listing`) | kod |
| B29 | **UUID/ULID anahtarlı modeller desteklenmiyor.** Listede `wire:click="runAction('…', {{ $row->getKey() }})"` anahtarı tırnaksız basıyor (string anahtar JS'i bozar); `LogBreadActivity` sayısal olmayan anahtarı sessizce atlıyor; `activity_logs.model_id` `unsignedBigInteger`; Users sayfası `int $userId` bekliyor; migration'lar `constrained()` ile `users.id` bigint varsayıyor (UUID kullanıcı tablosunda migration patlar). | `pages/bread/index/index.blade.php:89,180`, `src/Listeners/LogBreadActivity.php`, `database/migrations/*`, `pages/users/users.php` | kod |
| B30 | **`AuthenticationPlugin` sözleşmesinin yarısı ölü.** `forgotPassword()`, `forgotPasswordView()`, `loginComponent()`, `nameField()`, `avatar()`, `logout()`, `guard()` paket içinde hiçbir yerden çağrılmıyor. Guard da tutarsız: `attempt()` `guard()` ('web') kullanıyor, `user()`/`handleRequest()`/`logout()` ve `TardisAuthorizationPlugin` varsayılan guard'ı. Ya bağlanmalı ya sözleşmeden çıkarılmalı. | `src/Plugins/AuthenticationPlugin.php`, `src/Contracts/Plugins/AuthenticationPlugin.php` | kod (grep: 0 çağrı) |

## Düşük (LOW)

| # | Bulgu | Kod ref |
|---|-------|---------|
| B33 | Thumbnail/kırpma kodu yok: `tardis-media.thumbnail_size` hiçbir yerde okunmuyor, `Media::thumbnail_url` orijinal dosyayı döndürüyor. `docs/backlog.md` R26 "thumbnail + kırpma ✅" diyor, `docs/TEST_CHECKLIST.md:179` "kırpma yok" diyor. | `src/Models/Media.php`, `config/tardis-media.php` |
| B34 | Medya: `deleteDirectory()` / `syncDirectoryRename()` `LIKE` desenlerinde `_` ve `%` kaçışı yok (`a_b` klasörünü silmek `axb/…` satırlarını da siler); `listFiles()` dosya başına 3 depolama çağrısı yapıyor (S3'te yavaş) ve sayfalama yok; varsayılan listede `svg` var (script taşıyabilir, temizlenmiyor). | `src/Manager/MediaManager.php` |
| B35 | Dosya tabanlı durum (`plugins.json`, `preferences.json`, `settings.json`, `menus.json`, `themes.json`, `dashboard.json`, BREAD JSON'ları) kilitsiz oku-değiştir-yaz; eşzamanlı yazımda kayıp olur. `storage/` çok sunuculu/efemeral dağıtımlarda paylaşılmaz ve genelde git dışında — BREAD tanımları deploy ile taşınmaz. | `src/Support/UserPreferences.php`, `src/Manager/PluginManager.php`, … |
| B36 | `JsonBreadSource` bellek içi önbellek tutmuyor; `BreadRoutes::define()` her istekte tüm tanım dosyalarını okuyup çözüyor (menü ve arama da ayrıca). | `src/Bread/Sources/JsonBreadSource.php`, `src/Http/BreadRoutes.php` |
| B37 | `BreadRemoved` olayını dinleyen yok: silinen BREAD'in izinleri (`browse x` …) ve menü bindirmeleri yetim kalıyor. | `src/TardisServiceProvider.php` (`registerListeners`) |
| B38 | Activity log: tablo adı ön eksiz (`activity_logs`, diğerleri `tardis_*`) ve listener'da sabit yazılı (model değiştirilebilir olduğu halde); her olayda `Schema::hasTable()` sorgusu; geri yükleme/kalıcı silme, ayar dışı yönetici işlemleri (rol, kullanıcı-rol, plugin aç/kapa, şema, giriş) yazılmıyor; IP/user-agent yok; `created_at`/`user_id` indeksi yok. | `database/migrations/2026_06_14_000003_*`, `src/Listeners/LogBreadActivity.php` |
| B39 | i18n artıkları: 12 sayfada `#[Title('…')]` sabit İngilizce (diğerleri `tardis::…` anahtarı kullanıyor). (`ManagesColumnDefinitions` mesajları Faz 9'da çevrildi.) | `pages/bread/*`, `pages/database/*`, `settings`, `media-browser`, `ui-components` |
| B40 | Database Explorer liste sayfasında `selectedTable` kilitli değil ve `loadTableData()` gizli tablo kontrolü yapmıyor; `manage database` sahibi gizli tabloların (`sessions`, `tardis_*`) sütun/satır sayısını okuyabilir. | `pages/database/database.php` |
| B41 | Users sayfası `limit(100)` ile kesiyor, sayfalama yok; `name`/`email` sütunlarını varsayıyor. | `pages/users/users.php` |
| B42 | Liste sıralamasında ikincil anahtar yok (eşit değerlerde sayfalar arası kayıt tekrar/atlama); toplu aksiyon kayıt başına ayrı sorgu ve transaction'sız (ortada `findOrFail` patlarsa yarım kalır); arama `LIKE`'ı PostgreSQL'de sayısal sütunda hata verir (CI yalnızca SQLite). | `src/Bread/BreadQuery.php`, `pages/bread/index/index.php` |
| B43 | `src/` altında 72 dosyada `declare(strict_types=1)` yok (kalanında var). | `src/Formfields/**`, `src/Manager/**`, `src/Models/**`, … |

## Depo / paketleme / CI

| # | Bulgu |
|---|-------|
| B44 | `LICENSE` dosyası yok (`composer.json` ve README "MIT" diyor). `CHANGELOG`, `SECURITY.md`, `CONTRIBUTING` da yok. |
| B45 | `.gitattributes` yok: `research/`, `docs/`, `tests/`, `.github/`, `stubs` dışı geliştirme dosyaları Composer dist arşivine giriyor (`export-ignore` gerekir). |
| B46 | `composer.lock` `.gitignore`'da ama CI cache anahtarı `hashFiles('**/composer.lock')`; anahtar hep aynı, cache hiç yenilenmiyor. |
| B47 | Statik analiz yok (PHPStan/Larastan); CI yalnızca SQLite'ta koşuyor (MySQL/PostgreSQL matrisi yok); tarayıcı/E2E testi yok. |

## Tema, CSS/JS ve Vite (2026-11-17 incelemesi)

Hepsi kod okumasıyla tespit edildi; hedef yapı ve gerekçeler `docs/backlog.md` → *Tema, CSS/JS ve Vite: hedef yapı* (R56, kabul edildi).

| # | Bulgu | Kod ref |
|---|-------|---------|
| B52 | **`ThemePlugin` seçilen temayı eziyor.** `getTheme()` değişkenleri katmansız bir `:root{…}` kuralı olarak, tema kurallarından sonra yazılıyor. Yerleşik temalar `@layer` içinde, özel temalar aynı özgüllükte ama daha önce geldiği için plugin'in `--color-*` değeri açık/koyu ve kullanıcı seçimi dahil **her temada** kazanır; `base-*` rengi veren bir plugin koyu modu bozar. Plugin "tema" adını taşıyor ama seçicide görünmüyor, şeması yok. | `src/Manager/AssetManager.php` (`styles` adım 3, `themeRule`), `src/Contracts/Plugins/ThemePlugin.php` |
| B53 | **Yerleşik temalar iki yerde tanımlı.** Aynı 24 renk değeri hem `resources/css/app.css`'te (`@plugin "daisyui/theme"`) hem `src/Theme/BuiltinThemes.php`'de; eşitliği bir test koruyor. Yerleşik ve özel temalar da farklı yoldan geliyor (derlenmiş CSS / her sayfada satır içi `<style>`). | `resources/css/app.css:6-41`, `src/Theme/BuiltinThemes.php` |
| B54 | **Tema yalnızca renk taşıyabiliyor.** `Theme::COLORS` 20 `--color-*` anahtarı kabul ediyor; DaisyUI 5 temasının biçim değişkenleri (`--radius-selector|field|box`, `--size-selector|field`, `--border`, `--depth`, `--noise`) derlenmiş CSS'te kullanılıyor ama temadan ayarlanamıyor. Köşe yarıçapı gibi bir fark için bugün tek yol `ThemePlugin` (B52) ya da özel CSS. | `src/Theme/Theme.php` |
| B55 | **Çekirdek varlıklar yayınlamaya bağlı ve bayatlıyor.** `app.css`/`app.js` `public/vendor/tardis/assets`'e kopyalanmadan panel stilsiz açılır; `composer update` sonrası yeniden yayınlanmazsa eski sürüm sunulur. Plugin varlıkları ise aynı pakette hash'li rotadan, yayınlamasız sunuluyor. Ek olarak `?v=` için her istekte iki dosyanın `md5_file()`'ı hesaplanıyor. | `src/Manager/AssetManager.php` (`publishedVersion`, `coreScript`), `src/Http/Controllers/AssetController.php` |
| B56 | **Geliştirme modu üretimden farklı çalışıyor.** `resources/hot` varken çekirdek betik `type="module"` olarak yükleniyor; üretimde ise "Livewire Alpine'i başlatmadan önce çalışmalı" gerekçesiyle klasik betik. `@vite/client` eklenmediği için CSS/JS sıcak yenilemesi yok. Hot dosyası adresi `http://localhost` sabit (https `.test` sitesinde ve farklı host'ta çalışan Vite'ta uyumsuz). | `src/Manager/AssetManager.php` (`coreScript`, `styles`), `vite-plugins/hot-file.js` |
| B57 | **Tailwind kaynak taraması örtük.** `app.css`'te `@source` yok; Tailwind 4 proje kökünü otomatik tarıyor (git'in yok saymadığı her dosya: `docs/`, `tests/`, `stubs/`). Derleme çıktısı CI'da commit'le karşılaştırıldığı için, sınıf adına benzeyen bir metin içeren doküman/test değişikliği "bundle bayat" hatası üretebilir. | `resources/css/app.css:1`, `.github/workflows/ci.yml` (build-assets) |
| B58 | **Küçük artıklar.** Plugin iskeleti `vite ^7` istiyor, paket `^8.1` kullanıyor; çalışma kopyasında eski çıktı klasörleri duruyor (`dist/`, `public/tardis-assets/themes-manifest.json`; ikisi de git dışında, eski düzenin kalıntısı); tema seçimi bir istekte iki kez çözülüyor (layout + `theme-boot`); `window.__TARDIS__` çalıştırılabilir satır içi betik olarak yazılıyor (veri bloğu olsaydı CSP nonce'u gerekmezdi). | `stubs/plugin/package.json.stub`, `resources/views/layouts/*.blade.php`, `resources/views/components/theme-boot.blade.php` |

## Servis sağlayıcı, facade ve `Tardis` sınıfı (2026-11-17 incelemesi)

Hepsi kod okumasıyla tespit edildi; isimlendirme ve yapı önerileri `docs/backlog.md` → *Çekirdek API ve isimlendirme* (R57, kabul edildi).

| # | Bulgu | Kod ref |
|---|-------|---------|
| B59 | **İstek durumu taşıyan sınıflar `singleton`.** `AssetManager` `stylesRendered` / `scriptsRendered` / `added` tutuyor: süreç yeniden kullanılırsa (Octane, kuyruk işçisinde render) ikinci istekte `@tardisStyles` boş döner ve bir önceki isteğin `Tardis::addCss()` kayıtları kalır. `ThemeManager` (tema listesi), `SettingsManager`, `MenuManager`, `PluginManager` (kapalı plugin listesi) da ilk okumayı süreç boyunca saklıyor; başka bir işçide yapılan değişikliği görmezler. Bunlar `scoped()` olmalı. **Testle doğrulandı:** aynı süreçte bir sayfa render edildikten sonra istenen ikinci sayfanın HTML'inde `app.css` ve `app.js` yok. | `src/TardisServiceProvider.php:58-81` |
| B60 | **Komutlar yalnızca konsolda kayıtlı.** `registerCommands()` `runningInConsole()` koşuluna bağlı; web isteğinde `tardis:*` komutları tanımsız. Sistem → Komut çalıştırıcı izin listesine `tardis:doctor` eklense bile çalışmaz. `$this->commands()` koşulsuz çağrılabilir (Laravel kaydı Artisan başlarken yapar). | `src/TardisServiceProvider.php:247-262` |
| B61 | **`register()` içinde çözümleme ve dosya okuma.** `registerDefaultAuth()` `register()` aşamasında `PluginManager`'ı ve auth plugin'ini örnekliyor; `PluginManager` kurucusu `storage/tardis/plugins.json`'u okuyor. Diğer sağlayıcılar henüz kaydolmadan container'dan çözümleme yapılmamalı; yetkilendirme plugin'i gibi `boot()`'a taşınmalı. | `src/TardisServiceProvider.php:138-146` |
| B62 | **Her web isteğinde kurulum kontrolü.** `loadDefaultSettings()` `boot()`'ta her istekte `file_exists()` yapıyor ve dosya yoksa bir GET isteğinde diske yazıyor. Ön ayarın yeri `tardis:install` ya da `SettingsManager`'ın tembel yüklemesi. | `src/TardisServiceProvider.php:120-136` |
| B63 | **Facade docblock'u yanlış ve eksik.** `bread()` dönüş tipi `\Tardis\Manager\BreadManager` yazıyor (gerçek sınıf `\Tardis\Bread\BreadManager`); `addCss`/`addJs` satırlarında `\$asset` kaçışlı; `addAction`, `replaceAction`, `manipulateActions` hiç yok. IDE tamamlama ve statik analiz yanlış sonuç verir. | `src/Facades/Tardis.php:10-18` |
| B64 | **`Tardis` sınıfındaki önbellek alanları gereksiz ve zararlı.** Altı `?Manager` alanı container'daki singleton'ı ikinci kez saklıyor; container'da örnek değiştirildiğinde (testte `app()->instance()`, B59 sonrası `scoped`) facade eski nesneyi döndürmeye devam eder. `BreadManager` ve `JsonBreadSource` ise container'da singleton değil: facade tek örnek, `app(BreadManager::class)` her seferinde yenisini veriyor (`docs/constraints.md` → "Manager'lar container singleton'ıdır" kuralına aykırı, B36'nın önbelleğine de engel). | `src/Tardis.php:21-61`, `src/TardisServiceProvider.php` |
| B65 | **Sürüm elle yazılı.** `Tardis::version()` sabit `'0.1.0'` döndürüyor; etiketle ayrışabilir. `Composer\InstalledVersions::getPrettyVersion('yellow-three/tardis')` (yedeği sabit) tek kaynak olur. | `src/Tardis.php:100-103` |
| B66 | **Yinelenen/boş alt sağlayıcılar.** `TardisPluginManagerServiceProvider` yalnızca view'ları ikinci kez yüklüyor (adı plugin yöneticisini çağrıştırıyor, onunla ilgisi yok); `TardisMediaServiceProvider` migration'ları ikinci kez yüklüyor. İkisi de ana sağlayıcıya katlanabilir. | `src/TardisPluginManagerServiceProvider.php`, `src/TardisMediaServiceProvider.php` |
| B67 | **`tardis-views` yayını Livewire sayfalarını etkilemiyor (Livewire kaynağından doğrulandı: `Finder` ad alanı başına tek `viewPath` tutuyor).** Yayınlanan `resources/views/vendor/tardis` Blade view'larını ve anonim bileşenleri geçersiz kılar; Livewire sayfaları ise `Livewire::addNamespace(viewPath: paket)` ile yalnızca paket dizininden çözülüyor. Host bir sayfayı yayınlayıp düzenlerse değişiklik görünmez; ya desteklenmeli (yayınlanan dizin önce aranır) ya etiket yalnızca layout/bileşenleri yayınlamalı ve bu belgelenmeli. | `src/TardisServiceProvider.php:163-169, 195-204` |

## Rotalar, Blade direktifleri ve Livewire 4 yapısı (2026-11-17 incelemesi)

Livewire sürümü: 4.4.7 (`composer.lock`). *test* = geçici Pest testiyle yeniden üretildi; *kod* = kod okuması (Livewire için `vendor/livewire/livewire/src`). Plan: B68–B70 → R34, B71–B76 → R57.

| # | Bulgu | Kod ref | Doğrulama |
|---|-------|---------|-----------|
| B71 | **Livewire derleme önbelleği dosya zamanına bakıyor.** SFC/MFC'ler `storage/framework/views/livewire` altına derlenir ve `kaynak mtime > derlenmiş mtime` ise yenilenir. Composer dist kurulumu dosyalara arşivdeki (yayın anındaki) zamanı verir; yeni sürümün dosya zamanı, eski sürümün host'ta derlendiği andan eskiyse paket güncellemesinden sonra **eski bileşen sunulmaya devam eder**. `php artisan view:clear` bunu temizler: `tardis:install`, `UPGRADE.md` ve deploy rehberi bunu adım olarak içermeli; `tardis:doctor` derlenmiş bileşenlerin paket sürümüyle uyumunu denetlemeli. | `vendor/livewire/livewire/src/Compiler/CacheManager.php:21-55` | kod |
| B72 | **Blade direktifleri ve bileşen yolu.** `@tardisStyles` / `@tardisScripts` sınıfın tam adını metin olarak derlenmiş view'a gömüyor (`\Tardis\Manager\AssetManager`): sınıf taşınırsa (R57) host'ta `view:clear` yapılmadan sayfalar kırılır — direktif `app('tardis.assets')` gibi kararlı bir anahtar üretmeli. İkisi de "bir kez" bayrağına bağlı (B59). `@tardisScripts`'in `@livewireScripts`'ten önce gelmesi zorunlu ama yalnızca layout'taki sıraya emanet. `Blade::anonymousComponentPath(…, 'tardis')` ile `loadViewsFrom(…, 'tardis')` aynı `<x-tardis::…>` etiketini iki ayrı yoldan çözüyor (view ad alanı ayrıca alt sağlayıcıda üçüncü kez yükleniyor, B66); hangisinin kazandığı ve yayınlanmış bir bileşenin geçerli olup olmadığı testle sabitlenip tek mekanizma bırakılmalı. | `src/TardisServiceProvider.php:90-106` | kod |
| B73 | **Livewire kökü tüm `resources/views`.** `Livewire::addNamespace('tardis', viewPath: resources/views)` layout'ları, anonim bileşenleri, formfield ve widget view'larını da Livewire ad alanına sokuyor; aynı dosyalar `loadViewsFrom` ile düz Blade view'ı olarak da erişilebilir (SFC dosyası `view('tardis::pages.login')` ile çizilirse sınıf bloğu çalışır). Livewire bileşenleri ayrı bir kökte durmalı (`resources/views/livewire/pages/...` → adlar `tardis::pages.*` olarak kalır). | `src/TardisServiceProvider.php:163-183` | kod |
| B74 | **SFC/MFC düzeni tutarsız.** (a) `pages/database/` ve `pages/system/` birer MFC dizini ve içlerinde başka MFC dizinleri var (`create/`, `edit/`, `commands/`, `logs/`); çalışıyor ama Livewire dizinin değişim zamanına dizin girdileriyle birlikte bakıyor ve `livewire:convert` bu iç içe düzeni beklemez. (b) `pages/media-picker` sayfa değil, form alanından çağrılan çocuk bileşen. (c) `stats-media.blade.php` hiçbir yerden çağrılmıyor; `.claude/AGENTS.md` onu örnek gösteriyor ve yolunu yanlış veriyor (`components/` altında değil, kökte). (d) `bread-builder.php` 850 satır (kural: en çok 800). (e) `forgot-password` ve `reset-password` `render()` tanımlıyor (yasak). (f) `ui-components` vitrin sayfası üretimde de açık ve ability kapısı yok. | `resources/views/pages/**`, `resources/views/stats-media.blade.php` | kod |
| B75 | **Livewire kullanım tutarsızlıkları.** `@foreach` içeren 10 view'da hiç `wire:key` yok (BREAD listesi satırları ve seçim kutuları, ayarlar, arama, log satırları): sıralama/sayfalama/filtre sonrası DOM eşleştirmesi yanlış satıra durum taşıyabilir. 36 eski tip `getXProperty()` ile 3 `#[Computed]` karışık. `$this->redirect()` 6 yerde `navigate: true` olmadan (menü `wire:navigate` kullanıyor; kaydet sonrası tam sayfa yenileniyor). BREAD sayfaları tanımın tamamını `#[Locked] array $bread` olarak her istekte snapshot'ta taşıyor (yalnızca `slug` taşınıp tanım sunucuda yeniden okunabilir). Dosya yüklemede Livewire'ın kendi sınırı (varsayılan 12 MB, `throttle:60,1`) `tardis-media.max_file_size`'tan önce uygulanır; daha büyük sınır için host'un `livewire.temporary_file_upload` ayarı gerekir ve bu belgelenmemiş. | `resources/views/pages/**` | kod |
| B76 | **Rota dosyası tekrarları.** `['web', 'tardis.locale', 'tardis.admin']` ve ön ek/ad grubu üç yerde elle yazılı (`routes/admin.php`, `BreadRoutes`, `PluginRoutes`); ek middleware, guard ya da domain eklemek üç dosyayı değiştirmeyi gerektirir. `/media` ve `/media/browse` aynı bileşene gidiyor. Çıkış bir closure ve `AuthenticationPlugin::logout()`'u atlıyor (B20, B30). Plugin rotaları BREAD rotalarından sonra kaydoluyor: bir BREAD slug'ıyla aynı ilk parçayı kullanan plugin rotası gölgede kalır. | `routes/admin.php`, `src/Http/BreadRoutes.php`, `src/Http/PluginRoutes.php` | kod |

---

# GÜNCELLEME (2026-10-01) — Geri çekilen bulgu (tekrar raporlanmasın)

`HasManyField` için "ilişki satırlarını filtrelemiyor / cross-parent hijack var" iddiası **yanlıştı, geri çekildi.** `$existing` yalnızca o ana kayda bağlı satırları içeriyor, eşleşmeyenlerde `id` düşürülüyor. Cross-parent hijack yok; dosyaya dokunulmadı.

---

# Kabul edilen kısıt (kod değişikliği gerektirmiyor)

- **Authorization plugin yoksa panel fail-open** (eski B5). Kasıtlı: `BreadAuthorization::allowsAbility()` ve `MenuItem::isVisible()` aynı kuralı izler; `tardis.authorization.enabled` varsayılan olarak `true` olduğu için bu yalnızca host'un yetkilendirmeyi bilerek kapattığı kurulumlarda geçerli. Değiştirilirse menü ile tutarsızlaşır.

- **Dosya yüklemesi transaction dışında kalıyor.** `FileField::transform()` dosyayı satır yazılmadan **önce** diske taşıyor. Transaction eklemek yeni bir trade-off yaratmıyor, sadece DB atomikliğini düzeltiyor — rollback'in geri alamayacağı bir dosyayı geri almaya çalışmıyor. Mevcut orphan-file riski değişmedi.