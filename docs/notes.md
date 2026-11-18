# Aktif Notlar — docs/notes.md

Bu dosya **yalnızca aktif/açık** bulguları, denemeleri ve açık işleri tutar. **Kalıcı arşiv değildir.**

**Kural: Bir bulgu/iş tamamlandığında ilgili satırı/bölümü bu dosyadan SİL.** "✅ Çözüldü" olarak bırakma — arşiv git geçmişidir, defter yalnızca aktif maddeleri taşır. Kalıcı (silinmeyen) bilgi için bkz. `docs/voyager-tam-arsastirma.md` ve `research/05-tardis-bulgulari-ve-fix-oncelikleri.md`; Voyager karşılaştırması için `docs/voyager-karsilastirma.md`.

**Kapsam:** paketin açık bulguları. Kapananlar `RELEASE_NOTES.md` ve git geçmişinde; plan ve sıra `docs/backlog.md`'de.

---

# GÜNCELLEME (2026-11-17) — Proje incelemesi: açık bulgular

Kapsam: `src/`, `routes/`, `resources/views/pages`, migration'lar, CI ve dokümanlar okundu. Başlangıç durumu: `composer test` → 918 passed (2369 assertions), `composer lint` temiz. **Doğrulama** sütunu: *test* = geçici bir Pest testiyle yeniden üretildi (test sonra silindi), *kod* = yalnızca kod okumasıyla tespit edildi, çalıştırılarak doğrulanmadı. Hiçbir bulgu için kod değişikliği yapılmadı.

**Plan bağlantısı (2026-11-17, kabul edildi):** düzeltme sırası `docs/backlog.md` → *1.0 yol haritası*. **R34 (Faz 9, P0):** kapandı (2026-11-17, `fix/phase-9a-access-and-auth-flows`): B17, B18, B19, B20, B22, B23, B24, B25, B26, B31, B32, B68, B69, B70 — satırları silindi, ayrıntı `RELEASE_NOTES.md` ve git geçmişinde. **R35 (Faz 10, P1):** büyük ölçüde kapandı (PR #37 ve `fix/phase-10b-remaining`): B21, B27, B28, B30, B36, B39, B40, B43 silindi. Kalan kısımlar satırlarında: B29, B34, B35, B38, B41, B42. **R37:** B33. **R39:** B41. **R45 (Faz 13):** B44–B47. **R56 (Faz 10b):** ilk parça (`feature/phase-10b-theme-assets`): B52, B53, B54, B57 kapandı, B58 kısmen. Açık: B55 (çekirdek varlıkların hash'li rotadan sunulması), B56 (Vite geliştirme modu), B58'in kalanı, tema editöründe `shape` düzenleme. **R57 (Faz 10c):** hata düzeltmeleri dilimi (`fix/phase-10c-core-api`): B60–B66, B71 kapandı; B59, B72 kısmen. Açık: B59/B72'nin kalanı, B67, B73–B76 ve yeniden adlandırma PR'ı. Her düzeltme, bulguyu yeniden üreten kalıcı bir testle başlar; kapanan satır bu dosyadan silinir.

## Yüksek (HIGH)

| # | Bulgu | Kod ref | Doğrulama |
|---|-------|---------|-----------|

## Orta (MEDIUM)

| # | Bulgu | Kod ref | Doğrulama |
|---|-------|---------|-----------|
| B29 | **UUID/ULID: kalan kısım.** BREAD listesi, denetim kaydı ve Users sayfası string anahtarı destekliyor (Faz 10). Açık: paket migration'ları `foreignId('user_id')->constrained()` ile `users.id`'nin bigint olduğunu varsayıyor; UUID kullanıcı tablosunda `tardis_role_user`, `activity_logs.user_id`, `tardis_media.created_by` uyumsuz. | `database/migrations/*` | kod |

## Düşük (LOW)

| # | Bulgu | Kod ref |
|---|-------|---------|
| B34 | Medya: `listFiles()` dosya başına 3 depolama çağrısı yapıyor (S3'te yavaş) ve sayfalama yok; varsayılan listede `svg` var (script taşıyabilir, temizlenmiyor). (`LIKE` kaçışı Faz 10'da düzeltildi.) | `src/Manager/MediaManager.php` |
| B35 | **Durum dosyaları: kalan kısım.** `plugins.json` ve `preferences.json` artık kilit altında ve değişiklik bazında yazılıyor (`Tardis\Support\JsonFile`, Faz 10). Açık: `menus.json`, `dashboard.json`, `themes.json` ve ayarlar hâlâ tam kopya yazıyor (`JsonFile::update()`'e taşınmalı); `storage/` çok sunuculu/efemeral dağıtımlarda paylaşılmıyor, BREAD tanımları deploy ile taşınmıyor (R51). | `src/Menu/MenuOverlay.php`, `src/Dashboard/DashboardLayout.php`, `src/Manager/ThemeManager.php`, `src/Manager/SettingsManager.php` |
| B37 | ~~`BreadRemoved` olayını dinleyen yok.~~ **Kasıtlı, bulgu değil:** `src/Events/BreadRemoved.php` izinlerin ve geçmişin bilerek bırakıldığını söylüyor (aynı slug yeniden oluşturulunca roller korunur). Yetim menü bindirmesi zararsız. Satır bir sonraki temizlikte silinebilir. | `src/Events/BreadRemoved.php` |
| B38 | **Activity log: kalan kısım.** Giriş/çıkış, rol ve kullanıcı-rol değişikliği, plugin aç/kapa, IP/user-agent ve indeksler Faz 10'da eklendi. (Tablo adı Faz 10c'de `tardis_activity_logs` oldu.) Açık: geri yükleme (`RestoreAction`) loglanmıyor, kalıcı silme `deleted` olarak görünüyor; ayar değişiklikleri dışında kalan yönetici işlemleri (menü, tema, dashboard) yazılmıyor. | `src/Listeners/LogBreadActivity.php`, `src/Bread/Actions/*` |
| B42 | Arama `LIKE`'ı PostgreSQL'de sayısal sütunda hata verir (CI yalnızca SQLite). (Kararlı sıralama ve toplu aksiyon transaction'ı Faz 10'da eklendi.) | `src/Bread/BreadQuery.php` |

## Depo / paketleme / CI

| # | Bulgu |
|---|-------|
| B47 | **Tarayıcı/E2E testi yok.** (PHPStan/Larastan seviye 5 ve MySQL/PostgreSQL CI işleri Faz 13'te eklendi; veritabanı işleri ilk çalıştırmada sonuçlarına göre zorunlu kontrole alınacak.) |

## Tema, CSS/JS ve Vite (2026-11-17 incelemesi)

Hepsi kod okumasıyla tespit edildi; hedef yapı ve gerekçeler `docs/backlog.md` → *Tema, CSS/JS ve Vite: hedef yapı* (R56, kabul edildi).

| # | Bulgu | Kod ref |
|---|-------|---------|
| B55 | **Çekirdek varlıklar yayınlamaya bağlı ve bayatlıyor.** `app.css`/`app.js` `public/vendor/tardis/assets`'e kopyalanmadan panel stilsiz açılır; `composer update` sonrası yeniden yayınlanmazsa eski sürüm sunulur. Plugin varlıkları ise aynı pakette hash'li rotadan, yayınlamasız sunuluyor. Ek olarak `?v=` için her istekte iki dosyanın `md5_file()`'ı hesaplanıyor. | `src/Manager/AssetManager.php` (`publishedVersion`, `coreScript`), `src/Http/Controllers/AssetController.php` |
| B56 | **Geliştirme modu üretimden farklı çalışıyor.** `resources/hot` varken çekirdek betik `type="module"` olarak yükleniyor; üretimde ise "Livewire Alpine'i başlatmadan önce çalışmalı" gerekçesiyle klasik betik. `@vite/client` eklenmediği için CSS/JS sıcak yenilemesi yok. Hot dosyası adresi `http://localhost` sabit (https `.test` sitesinde ve farklı host'ta çalışan Vite'ta uyumsuz). | `src/Manager/AssetManager.php` (`coreScript`, `styles`), `vite-plugins/hot-file.js` |
| B58 | **Küçük artıklar.** Çalışma kopyasında eski çıktı klasörleri duruyor (`dist/`, `public/tardis-assets/themes-manifest.json`; ikisi de git dışında); tema seçimi bir istekte iki kez çözülüyor (layout + `theme-boot`); `window.__TARDIS__` çalıştırılabilir satır içi betik olarak yazılıyor (veri bloğu olsaydı CSP nonce'u gerekmezdi). (Plugin iskeletinin Vite sürümü Faz 10b'de hizalandı.) | `resources/views/layouts/*.blade.php`, `resources/views/components/theme-boot.blade.php` |

## Servis sağlayıcı, facade ve `Tardis` sınıfı (2026-11-17 incelemesi)

Hepsi kod okumasıyla tespit edildi; isimlendirme ve yapı önerileri `docs/backlog.md` → *Çekirdek API ve isimlendirme* (R57, kabul edildi).

| # | Bulgu | Kod ref |
|---|-------|---------|
| B59 | **Uzun süreli süreçte bayat okumalar.** `AssetManager`'ın "bir kez yaz" bayrağı Faz 10c'de isteğe taşındı (ikinci istekte stil/betik kayboluyordu). Açık: `ThemeManager` (tema listesi), `SettingsManager`, `MenuManager` ve `PluginManager`'ın kapalı listesi ilk okumayı süreç boyunca saklıyor; başka bir işçide yapılan değişikliği görmezler. Kayıtlar (singleton) ile okunan durum (istek başına) ayrılmalı. | `src/TardisServiceProvider.php` |

## Rotalar, Blade direktifleri ve Livewire 4 yapısı (2026-11-17 incelemesi)

Livewire sürümü: 4.4.7 (`composer.lock`). *test* = geçici Pest testiyle yeniden üretildi; *kod* = kod okuması (Livewire için `vendor/livewire/livewire/src`). Plan: B68–B70 → R34, B71–B76 → R57.

| # | Bulgu | Kod ref | Doğrulama |
|---|-------|---------|-----------|
| B72 | **Varlık sırası layout'a emanet.** (Kararlı direktif anahtarı ve tek anonim bileşen mekanizması Faz 10c'de yapıldı.) Açık: `@tardisScripts`'in `@livewireScripts`'ten önce gelmesi yalnızca layout sırasına emanet; layout'lar için `<x-tardis::head />` / `<x-tardis::scripts />` bileşenleri sırayı tek yerde tutmalı. | `resources/views/layouts/*.blade.php` |
| B75 | **BREAD sayfalarının snapshot'ı.** (`wire:key`, `#[Computed]`, `navigate: true` ve yükleme sınırı belgesi Faz 10c'de yapıldı.) Açık: BREAD sayfaları tanımın tamamını `#[Locked] array $bread` olarak her istekte snapshot'ta taşıyor; yalnızca `slug` taşınıp tanım sunucuda yeniden okunabilir (istek başına okuma `JsonBreadSource` içinde zaten önbellekte). | `resources/views/livewire/pages/bread/**` | kod |

---

# GÜNCELLEME (2026-10-01) — Geri çekilen bulgu (tekrar raporlanmasın)

`HasManyField` için "ilişki satırlarını filtrelemiyor / cross-parent hijack var" iddiası **yanlıştı, geri çekildi.** `$existing` yalnızca o ana kayda bağlı satırları içeriyor, eşleşmeyenlerde `id` düşürülüyor. Cross-parent hijack yok; dosyaya dokunulmadı.

---

# Kabul edilen kısıt (kod değişikliği gerektirmiyor)

- **Authorization plugin yoksa panel fail-open** (eski B5). Kasıtlı: `BreadAuthorization::allowsAbility()` ve `MenuItem::isVisible()` aynı kuralı izler; `tardis.authorization.enabled` varsayılan olarak `true` olduğu için bu yalnızca host'un yetkilendirmeyi bilerek kapattığı kurulumlarda geçerli. Değiştirilirse menü ile tutarsızlaşır.

- **Dosya yüklemesi transaction dışında kalıyor.** `FileField::transform()` dosyayı satır yazılmadan **önce** diske taşıyor. Transaction eklemek yeni bir trade-off yaratmıyor, sadece DB atomikliğini düzeltiyor — rollback'in geri alamayacağı bir dosyayı geri almaya çalışmıyor. Mevcut orphan-file riski değişmedi.