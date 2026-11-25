# Backlog — Öncelikli İş Birikimi (docs/backlog.md)

Uzun ömürlü, dalga dalga ilerleyen işlerin **roadmap** takibi. Kaynak: `.omo/plans/` ve `.omo/drafts/`. `docs/notes.md`'den farkı: **kalıcı** dosyadır — buradaki madde çözülünce satırı silinmez, **commit ile güncellenir** ve tamamlandığında işaretlenir (roadmap'tir, defter değil). Aktif bulgu detayı `docs/notes.md`'de yaşar; bu dosya ona işaret eder.

## Öncelik ölçeği

- **P0** — Kırıcı / güvenlik / bloke edici (üretim öncesi şart)
- **P1** — Önemli eksik veya hata (kısa vade, kullanıcı görür)
- **P2** — İyileştirme / teknik borç / araç (orta vade)

## Current State

**Bu bölüm projenin durumu için tek kaynaktır.** `PROJECT_STATUS.md` yalnızca mimari özetidir ve buraya bağlanır; `RELEASE_NOTES.md` yayınlanan değişiklikleri anlatır, durum tutmaz. Son doğrulama: 2026-11-18 — `composer test` → 1119 passed, `composer lint` temiz; önceki: 2026-11-17 — 1021 passed (2758 assertions), `composer lint` temiz. **Faz 9 (R34), Faz 10 (R35) ve Faz 10b'nin ilk parçası (R56) master'da** (PR #36–#39). Kalan parçalar `docs/notes.md`'de satırlarında. **Faz 10c (R57) hata dilimi** (`fix/phase-10c-core-api`): istek başına varlık yazımı, servis sağlayıcıda istek başı iş yok, facade/Tardis sınıfı, alt sağlayıcılar birleşti, kurulumda `view:clear`, kararlı direktif anahtarı. Hata dilimi master'da (PR #40). **Livewire yapısı** (`refactor/phase-10c-livewire-structure`): ayrı bileşen kökü, `index/` dizinleri, `tardis-views` yalnızca geçersiz kılınabilir view'lar, `wire:key`, `#[Computed]`, `navigate: true`, `PanelRoutes` ile tek rota grubu, plugin rotaları BREAD'den önce, builder 800 satırın altında. **Yeniden adlandırma** (`refactor/phase-10c-renaming`, adlar 2026-11-18'de onaylandı): `TardisManager`, `Auth\SessionAuthenticationPlugin` / `RoleAuthorizationPlugin` (`tardis-authentication`), `Classes/` ve `Manager/` alan klasörlerine taşındı, komutlar `tardis:make:*` ve `tardis:bread:import-legacy`, rotalar `tardis.builder.*` / `tardis.bread.{index,create,show,edit}` / liste ekranları `.index`; eski sınıf ve komut adları 0.3'e kadar çalışır. Son dilim (`refactor/phase-10c-config-root`): `tardis.media.*` / `tardis.icons.*` tek config kökü ve tek `tardis-config` yayın etiketi, `tardis_activity_logs` tablo adı, her yerde `Tardis` yazımı. Bununla R57 kapandı (PR #40–#43). **Faz 11** başladı: R36 ilişki alanları (`feat/phase-11-relations`): `belongs_to`, `has_one`, builder'ın ilişkiler sekmesi forma alan ekliyor, tanımdaki `relationships` anahtarı kaldırıldı. R37 görsel alanı (`feat/phase-11-image-field`): `image` tipi (`resize`, `quality`, `thumbnails`), medya ekranında küçük resim (`thumbnail_size` artık okunuyor), GD ile, yeni bağımlılık yok. R38 (`feat/phase-11-frontend-helpers`): site menüleri (ada göre, iç içe, `storage/tardis/site-menus.json`), `setting()` / `menu()` / `<x-tardis::site-menu>`. Sürükle-bırak yerine yukarı/aşağı + girinti/çıkıntı düğmeleri; sürükle-bırak R27 kuyruğunda kaldı. R39 (`feat/phase-11-users`): Kullanıcılar ekranında hesap oluşturma, ad/e-posta/parola düzenleme ve silme (`UserAccounts`, `PrivilegeGuard::mayManageUser`), paket içi profil sayfası (`/admin/profile`), `tardis.users.columns` (B41). Plandaki "users BREAD'i" yerine mevcut ekran genişletildi: `users` adı zaten ayrılmış ve rol ataması ayrıcalık korumalarından geçmeli. R40 (`feat/phase-11-ordering`): sıralama sayfası (`/admin/{slug}/order`, `order_display_column`, sürükle-bırak + oklar), alan `help`/`description`/`placeholder`/`default` builder'da, `timestamp` takma adı. **Faz 11 (R36–R40) bununla tamamlandı**; sıradaki Faz 12 (R41 `tardis:import:voyager`). **R41** (`feat/phase-12-voyager-import`): `Tardis\Import\Voyager` (alan, BREAD, izin eşleyicileri + içe aktarıcı), `--dry-run`/`--only`/`--connection`/`--force`, eşlenemeyenler raporda. **R42** (`feat/phase-12-demo-content`): `tardis:install --demo` / `tardis:demo [--remove]`, `tardis_demo_*` tabloları ve paket içi modeller. **R43** (`docs/phase-12-guides`): `docs/CUSTOMIZATION.md` (örnekleri `CustomizationGuideTest` ile çalışıyor), `docs/MIGRATING_FROM_VOYAGER.md`, README konumlandırması. **R44** (`feat/phase-12-definition-health`): `DefinitionHealth`, `tardis:doctor` uyarısı ve builder inceleme adımı uyarısı; yedek/geri alma arayüzü zaten BREAD yönetim sayfasında (her tanımın *Yedekler* menüsü). **Faz 12 (R41–R44) tamamlandı**. **Faz 13 / R45** ilk dilim (`chore/phase-13-quality-gate`): `LICENSE` (MIT), `SECURITY.md` (GitHub özel güvenlik bildirimi), `CHANGELOG.md`, `CONTRIBUTING.md`, `.gitattributes` export-ignore, CI cache anahtarı `composer.json`, PHPStan/Larastan seviye 5 (temiz, baseline yok) + CI işi, MySQL 8.4 / PostgreSQL 17 test işleri (yeşil; `users` yabancı anahtarları kaldırıldı, PostgreSQL'de aramalar `ilike`). Veritabanı ve statik analiz işleri 2026-11-19'da branch protection'da zorunlu yapıldı (10 kontrol). İkinci dilim (`ci/phase-13-dependency-matrix`): en düşük bağımlılıklarla test işi ve Laravel 13 / Livewire 4'ün yayınlanmamış dallarıyla izinli-başarısız iş. Laravel 14 (2026-11-19 isteği): henüz dal olarak yok (`master` Packagist'te `13.0.x-dev`, `pest-plugin-laravel` en çok `^13.0`); `master` + Testbench `12.x-dev` ile izinli-başarısız iş eklendi, `composer.json`'a `^14.0` Laravel 14 betası çıkıp bu iş yeşilken eklenecek. Üçüncü dilim (`test/phase-13-browser`): `pest-plugin-browser` + Playwright ile giriş ve BREAD akışları, CI'da `browser tests` işi. Kalan: `--prefer-lowest` ve sonraki sürümler için izinli-başarısız iş, tarayıcı testleri.

Son güncelleme: 2026-11-17 (proje incelemesi; 1.0 yol haritası R34–R45, R56–R57 ve 1.x farkları R46–R55 eklendi, R22/R31/R32 durumları düzeltildi). ID'ler `R` = roadmap sırası; `notes.md` bulgularıyla (`B*`) karışmaz.

### Tamamlandı

| ID | Öncelik | İş | Durum | Commit | Yönlendirme |
|----|---------|----|-------|--------|-------------|
| R1 | P1 | Tema sistemi — CSS variable tabanlı anahtar-toka | ✅ Çözüldü | `66c4ed7` | `docs/constraints.md` → Tema sistemi |
| R2 | P1 | Menü refactor — heroicons + alias config + bölüm/hiyerarşi | ✅ Çözüldü | `914bcd3`, `22e346c`, `83456f9`, `0fd7fe6` | — |
| R3 | P1 | Media browser — Filament V4 iki sütunlu düzen | ✅ Çözüldü | `f7d4c44`, `6f077d6`, `e447334` | — |
| R4 | P2 | Settings — arama, kopyalama, Ctrl+S, validasyon dalgaları | ✅ Çözüldü | `5ac4d74`, `c574820`, `db49ebd` | — |
| R5 | P2 | Blade asset direktifleri (`AssetManager`) | ✅ Çözüldü | `ed8d7f0` | — |
| R6 | P2 | Asset symlink kurulumu | ✅ Çözüldü | (plan frontmatter: `status: completed`) | — |
| R7 | P2 | Dual-mode asset pipeline — Vite dev + prebuilt manifest | ✅ Çözüldü | `ed8d7f0` + `AssetManager`/`ThemeManager` manifest | `docs/constraints.md` → Tema sistemi |
| R8 | P1 | Tema paint öncesi uygulanmıyor (FOUC) — blocking `<script>` + manifest çözümlemesi `</head>` öncesine alındı | ✅ Çözüldü | `fce40c0` | `docs/constraints.md` → Tema sistemi |
| R17 | P0 | **Yetkilendirme kapatıldı.** `TardisAuthorizationPlugin` varsayılan etkin, `access admin` kapısı, `tardis:admin`, tüm sabit ekranlar ability ile korunuyor ve menüde gizleniyor, BREAD kaydı izinleri üretiyor, plugin durumu dosyada + auth plugin'leri kilitli, Users ekranı, Database Explorer sistem tabloları, ek CSS/JS config'i | ✅ Çözüldü | `b17143c`, `b846632`, `30131ff`, `bae9688`, `331202c` | `RELEASE_NOTES.md` |
| R20 | P1 | **Yönetici kabuğu çıplak host'ta 500 veriyordu + manager'lar singleton değildi.** | ✅ Çözüldü | `a87de6a` | `docs/constraints.md` → Manager'lar |
| R10 | P2 | Asset iyileştirmeleri: content-hash cache busting + `ThemePlugin::getStyles()` kaldırıldı (tema artık doğrulanmış CSS değişkenleri) | ✅ Çözüldü | Faz 0 | `docs/constraints.md` → Tema sistemi |
| R11 | P2 | BREAD tanım kaynağı: **JSON tek kaynak**; `config/bread` salt okunur içe aktarma (`LegacyConfigReader`) | ✅ Çözüldü | Faz 0 | `docs/constraints.md` → BREAD |
| R18 | P2 | Auth/policy/config tutarsızlıkları: login `AuthenticationPlugin.attempt()` üzerinden, `BasePolicy` `BreadAuthorization`'dan, ölü config anahtarları kaldırıldı, plugin rotaları wildcard'a yenilmiyor | ✅ Çözüldü | Faz 0 | `RELEASE_NOTES.md` |
| R21 | P0 | **Faz 0 — ilk sürüm temizliği:** `FieldType` → registry, `config/bread` salt okunur, `getStyles()` kalktı, `field()` stub'ı kalktı, BREAD rotaları tanımdan üretilir (`component`/`policy`/`scope`, rezerve slug'lar), BREAD olayları + listener'lar + çalışan activity log, `tardis.page` olayı, `Asset` + `Tardis::addCss/addJs`, `tardis:make-plugin` stub düzeltmeleri, sürüm `0.1.0` + `UPGRADE.md`. **Ertelenenler:** `addAfterFormField` → R23 (B16'ya bağlı), `addAction/replaceAction` → R24, model haritası → R33 | ✅ Çözüldü | `feat/phase-0-cleanup` | `UPGRADE.md` |
| R28 | P2 | **Faz 7 — Çok dilli içerik.** Form içinde locale sekmeleri, 'tüm diller / aktif dil' doğrulama modu, listede aktif dil, BREAD etiketleri + menü başlıkları çevrilebilir (JSON kolon biçimi korunur) | ✅ Çözüldü | `feat/phase-7-translated-content` | `RELEASE_NOTES.md` |
| R26 | P2 | **Faz 5 — Media entegrasyonu.** BREAD `media_picker` alanı, thumbnail + kırpma, `{uid}/{date:…}/{random:n}` dosya adı şablonu, tek yükleme doğrulaması (BREAD `FileField` dahil), media filter plugin'i | ✅ Çözüldü | `feat/phase-5-media` | `RELEASE_NOTES.md` |
| R24 | P1 | **Faz 3 — BREAD liste.** `addAction/replaceAction/manipulateActions` kayıt API'si, `BreadQuery` servisi (V2 `Browsable` parçaları: arama, sütun filtresi, adlandırılmış filtre/scope, sıralama, soft-delete, eager load, `warnings[]`), BREAD başına `scope`, `Action` sınıflarının UI'ya bağlanması (satır + toplu), sunucu taraflı sırala/filtre/ara/sayfa boyutu, ilişki kolonları (eager load), soft-delete geri yükleme/kalıcı silme, aksiyon bazlı layout, browse accessor'ları. **Kalan dördü (`feat/phase-3-rest`): sütun-içi arama, adlandırılmış filtre rozetleri, ilişki kolonları, browse accessor'ları.** `BreadDefinition::getField()` düz-liste `fields` şeklini de çözecek şekilde düzeltildi (adlandırılmış filtrelerin `columnExists()`'te sessizce atlanmasının kök nedeni). | ✅ Çözüldü | `93f52f7` (`feat/phase-3-rest`) | PR #24 |

### Açık

| ID | Öncelik | İş | Durum | Commit | Yönlendirme |
|----|---------|----|-------|--------|-------------|
| R9 | P1 | **Voyager → Tardis özellik transferi.** BREAD audit turu bu planın parçası; 6 P0/P1 düzeltmesi landı. Eski bulgular (B1–B16) kapandı (bkz. R15); kalan parite işleri **R36–R44**'e taşındı. | ➡️ R36–R44'e taşındı | `e0d00ac`…`8bb20a3` | `docs/notes.md` + `.omo/plans/voyager-transfer.md` |
| R12 | P2 | **Voyager II kalan özellikler** — `.omo/plans/voyager-ii-features.md` yerine aşağıdaki **Voyager parity planı** (R21–R30) geçerli. | ➡️ R21–R30'a taşındı | — | Voyager parity planı |
| R13 | P2 | **Laravel Vite plugin entegrasyonu.** Plan hâlâ `status: draft`. | ⏳ Taslak | — | `.omo/plans/laravel-vite-plugin.md` |
| R14 | P2 | **Graphify MCP'yi OpenCode'a bağla.** Doğrulandı: `~/.config/opencode/opencode.json` içinde `graphify` **yok**. Yalnızca araç/observability, ürün yüzeyi yok. | ⏳ Açık | — | `.omo/plans/graphify-mcp-setup.md` |
| R15 | P1 | **BREAD açık bulguları.** Hepsi kapandı: B1/B2 (Faz 0), B16 (alanlar `Formfield::render()` view'ından çizilir, Faz 2), B4 (`BelongsToManyField::searchOptions()` artık ilgili BREAD'in `scope`'unu ve `browse` yetkisini uygular; seçili id'ler de aynı sorgudan geçer), B5 kasıtlı fail-open olarak `docs/notes.md` → *Kabul edilen kısıt*'a taşındı. | ✅ Çözüldü | `fix/relation-picker-scope` | `docs/notes.md` |
| R16 | P2 | **`Alpine.store('theme')` → `Alpine.data('theme')` + `$persist` geçişi.** R8 ile birlikte planlanmıştı ama FOUC'yu etkilemiyor ve planın gövdesi `availableThemes` / `lightThemes` / `darkThemes` getter'larını düşürüyor — `pages/settings/settings.blade.php` bunları `$store.theme.availableThemes` üzerinden okuyor, gövde aynen uygulansaydı settings sayfası kırılırdı. Ayrıca plan store'un `resources/js/app.js` içinde olduğunu varsayıyor; oysa layout'a inline gömülü. Gerçek gerekçe: listener cleanup (`wire:navigate` sırasında birikme). | ⏸️ Ertelenmiş | — | `.omo/plans/theme-fix-wire-navigate.md` (Todo 2-7) |
| R19 | P2 | **`ThemeManager` boot-time I/O.** `TardisServiceProvider::register()` içinde dosya/ağ okuması yapılıyor, hatalar yalnızca loglanıyor. | ➡️ R31'e taşındı (tema motoru yeniden yazılırken ortadan kalkar) | — | Tema, CSS ve JS mimarisi |
| R22 | P1 | **Faz 1 — Panel i18n.** Tüm sabit metinler `__('tardis::…')`, `lang/en` (varsayılan) + `lang/tr`, locale seçici, TR/EN karışık aria etiketlerinin temizliği | ✅ Birleşti; kalan sabit metinler `docs/notes.md` B39 (R35) | PR #11 (`feat/phase-1-i18n-design-system`) | `RELEASE_NOTES.md` → Faz 1 / 1b |
| R23 | P1 | **Faz 2 — Alan sistemi.** **B16:** create/edit'in satır içi tip zincirini `Formfield::render()` view'larına taşımak (özel tip sonunda gerçekten çizilir), `addAfterFormField` kancası, `BreadSaver` servisi (create/edit kopya kodu kalkar), `BreadDefinition`/layout üzerinde `visibleFor/searchable/orderable/relationships`, `FormfieldPlugin` + registry, V2 lifecycle (`browse/read/edit/add/store/update($old)/stored/updated`), add/edit ayrı kural seti, çevrilebilir doğrulama mesajı, dizi elemanı doğrulama, eksik tipler (Color, Coordinates, Hidden, RichText, çoklu checkbox/select, Repeater, SimpleArray, MediaPicker) | 🔄 Kısmen: B16 (alanlar `Formfield::render()` view'ından çizilir), `BreadSaver`, `Formfield::configure()`, Color + Hidden tipleri bitti; lifecycle, `addAfterFormField`, add/edit kural seti, çevrilebilir mesaj, kalan tipler açık | — | Faz 2 |
| R25 | P1 | **Faz 4 — Plugin genişletilebilirliği.** **Plugin CSS/JS mimarisi (aşağıdaki bölüm):** `Asset` değer nesnesi, hash'li önbellekli sunum rotası, bildirimli kapsam, formfield varlık çekme modeli, `window.Tardis` JS API'si, `--with-assets` iskelesi, CSP nonce, `Routes` contract'ı + yetkili/ön yüz rota ayrımı, plugin ayar ekranı + preferences, `Filter\Widgets/Layouts/Media`, `tardis:plugins` komutu, plugin sürüm/bağımlılık gösterimi | 🔄 Büyük ölçüde bitti: `Asset::file()` + hash'li sunum, kapsam süzmesi, formfield varlıkları (`Formfield::assets()`), CSP nonce, `--with-assets`, plugin ayar bileşeni (`SettingsComponent`), `Provider\Routes` bağlandı, `docs/JS.md`, `tardis:plugins`; açık: `Filter\Widgets/Layouts/Media`, plugin bağımlılık gösterimi | — | Faz 4 |
| R27 | P2 | **Faz 6 — Menü builder + Dashboard widget'ları + görünüm.** `storage/tardis/menus.json` bindirmesi, nestable sürükle-bırak, gizle/yeniden adlandır/özel link, varsayılan dashboard kartları + `storage/tardis/dashboard.json` düzeni + widget izinleri, rol sayfasında gruplu izin ağacı, tema editörü (appearance) + özel CSS ayarı (`manage appearance`; özel JS arayüzden **verilmez**, yalnızca config dosyasıyla) | 🔄 Büyük ölçüde bitti: `menus.json` bindirmesi + Menü oluşturucu (gizle/yeniden adlandır/bölüm/sıra/özel link; yukarı-aşağı, sürükle-bırak değil), widget'a dayalı dashboard + `dashboard.json` (gizle/sırala/genişlik, `manage dashboard`), rol sayfasında gruplu izin ağacı, tema editörü (`manage appearance`), özel CSS ayarı bitti; sürükle-bırak, iç içe menü, widget izinlerinin rol ekranında gösterimi, Settings'te özel temaların varsayılan seçimine bağlanması açık | — | Faz 6 |
| R29 | P2 | **Faz 8 — Kurulum, DX ve sistem araçları.** `tardis:install` (migrate + seed + ilk admin + asset publish), isteğe bağlı demo veri, `tardis:doctor` + panelde **Sistem** sayfası, **salt okunur log görüntüleyici** (`view logs`) ve **izin listeli komut çalıştırıcı** (`run commands`, `local` dışında kapalı, activity log'a yazar), güncellenmiş plugin iskeleti, `UPGRADE.md`, kapsamlı örnek | ✅ Çözüldü: `tardis:install` + `tardis:doctor` (provider'a kayıtlı, JSON çıktı, deploy kapısı olarak non-zero exit), üç sistem ekranı (`view system` / `view logs` / `run commands` ayrı yetkilerle kardeş menü girdisi), log okuyucu (ad kalıbı + bayt tavanı, seek ile tail), izin listeli komut çalıştırıcı (varsayılan kapalı, ortam + izin listesi, activity log), isteğe bağlı demo veri (`DemoSeeder`, ortam korumalı), güncellenmiş plugin iskeleti (`stubs/plugin`), `UPGRADE.md`, kapsamlı örnek ve ilgili testler | `feat/phase-8-install` | `docs/constraints.md` → Test (görünür metin tarayıcısı) |
| R33 | P2 | **Model haritası.** `config('tardis.models')` ile host'un `Role`, `Permission`, `Media`, `ActivityLog` modellerini değiştirebilmesi (V1 `Voyager::useModel`); şu an sınıflar sabit | ✓ Tamamlandı | — | `research/07` §5 |
| R30 | P1 | **Kalite kapısı (her faz).** Önce test, tam sayfa istek testi, `boot()` yetki kapısı, `lang` anahtarı, `docs/` güncellemesi, faz başına yığılı PR | 🔄 Sürekli | — | Voyager parity planı → Kurallar |
| R31 | P1 | **Faz 1b — Tasarım sistemi (ortak UI bileşenleri).** `x-tardis::card` (actions slotu), badge, slide-in çekmece, modal, dropdown, toast/bildirim (onay düğmeli), sayfa yükleme çubuğu, sidebar kullanıcı kartı (baş harf avatarı + `avatar_column`) + kalıcı durum, 3 durumlu tema anahtarı, marka ayarları, **tema/CSS/JS mimarisi (aşağıdaki bölüm): `dist/assets/app.js`, `Alpine.data('theme')`, runtime tema motoru, sunucuda çözülen `data-theme`, global + kullanıcı tema tercihi, DaisyUI tema listesinin daraltılması** (`appearance` grubu: başlık/logo/favicon/yükleme görseli), RTL değerlendirmesi | ✅ Birleşti (bileşenler, `app.js` + `window.Tardis`, runtime tema motoru); RTL değerlendirmesi yapılmadı → R54 | PR #11, PR #16 | `RELEASE_NOTES.md` → Faz 1 / 1b, Faz 6 |
| R32 | P1 | **Faz 3b — Layout + Builder UX.** BREAD tanımı `list`/`view` layout'ları, sürükle-bırak + 6'lık genişlik ızgarası, yan çekmecede alan seçenekleri, builder tablo listesi (layout sayıları + Backup), `legend`/bölüm başlığı | ✅ Birleşti (sürükle-bırak, genişlik, çekmece, `legend`); yedek/geri alma arayüzü doğrulanmadı → R44 | PR #22 (`feat/phase-3b-layouts`) | `research/06` §3.4 |

## Bağımlılık sırası notu

R10 artık tek bir karara indirgendi: (b) content-hash cache busting tamamlandı ve koda girdi, (a) ise `ThemePlugin::getStyles()` public contract'ı üzerinde bir BC kararı bekliyor (`docs/notes.md` → B8) — `getStyles()` giderse `AssetManager`'ın inline `<style>` üretimi de gitmeli. R11, R12'nin tanım kaynağını etkiliyor — tanımlar JSON'dan config'e taşınırken R12'nin kalan işleri yeni kayda göre yazılmalı.

## Voyager parity planı (2026-10-03)

Amaç: Voyager 1'in olgun BREAD/menü/medya/kurulum deneyimini ve Voyager 2'nin mimarisini (JSON tanım, alan lifecycle'ı, Provider/Filter plugin'leri) Tardis'in Livewire 4 + DaisyUI yığınında birleştirmek. Kaynak karşılaştırma: `research/01-voyager-1x.md`, `research/02-voyager-2x.md`, `research/03-voyager-plugin-sistemi.md`.

### Alınan kararlar

| Konu | Karar |
|---|---|
| Kapsam | Hepsi: alan sistemi, BREAD liste, plugin, çok dillilik, media, menü builder, dashboard widget'ları, kurulum, CSS/JS/tema yönetimi, izinler |
| BREAD tanım kaynağı | **JSON tek kaynak**; `config/bread` yalnızca legacy içe aktarma, sonra kaldırılır |
| Alan genişletme | **Registry + `FormfieldPlugin`**; `FieldType` enum'u kalkar |
| Geriye uyumluluk | **Serbest kır** (redesign yayınlanmadı); ilk sürüm `0.1.0` ön sürümü, `1.0.0` daha sonra; `UPGRADE.md` ile |
| Çeviri verisi | **JSON kolon** (V2 biçimi, mevcut `translatable` alanlarla aynı) |
| Menü depolama | **JSON dosyası** (`storage/tardis/menus.json`), kod tanımlı öğelerin üstüne bindirme |
| Panel dili | **`lang/` dosyaları, EN varsayılan + TR** |
| Yetkilendirme | Zaten kapatıldı (R17): her yeni ekran ability + `boot()` kapısı + menü gizleme ile gelir |
| SPA | **Hayır**: Livewire + Alpine kalır (`wire:navigate` ile sayfa geçişi); etkileşim ihtiyaçları Alpine ile |
| Compass benzeri araçlar | **Alınır, kısıtlı:** salt okunur log görüntüleyici (`view logs`), izin listeli komut çalıştırıcı (`run commands`; `local` dışında varsayılan kapalı; her çalıştırma activity log'a), sistem sayfası (`tardis:doctor` sonucu) — Faz 8 |
| BREAD rotaları | **Tanımdan üretilir, `/{slug}` wildcard'ı kalkar**; tanımda `component`, `policy`, `scope` alanları (Faz 0) |
| Mimari | **`BreadQuery` + `BreadSaver` servisleri** (sayfalar ince kalır) ve **BREAD olayları + listener'lar** (izin, menü, cache; plugin'ler de dinler) |
| Özel JS / tema listesi / tercih deposu | **Onaylandı:** özel JS arayüzden verilmez (yalnız config/dosya); DaisyUI `themes: all` yerine kısa liste; kullanıcı tercihi `storage/tardis/preferences.json` (host şemasına dokunulmaz) |
| Plugin varlıkları | **Tardis sunar**: hash'li URL + `immutable` önbellek (inline yok, publish yok); **bildirimli kapsam** (`scope`, rota/ability) + formfield varlıkları **çekme modeli**; **`window.Tardis` sürümlü JS API'si**; `tardis:make-plugin --with-assets` (R25/R21) |
| Tema kaynağı | **Runtime CSS değişkenleri**: host/plugin temaları `storage/tardis/themes.json` + Settings `appearance` + `ThemePlugin::getTheme()`; yerleşik temalar Vite ile paketlenir (R31) |
| Tema kapsamı | **Global varsayılan + kullanıcı başına** (sunucuda saklanır, `data-theme` sunucuda çözülür) (R31) |
| Tailwind | **Önceden derlenmiş CSS + geniş safelist + `tardis.assets.css`**; host Node build'i gerekmez (R31) |
| JavaScript | **Küçük `app.js` paketi** (`Alpine.data`/store bileşenleri); layout'taki gömülü betik kalkar, FOUC betiği hariç (R31) |
| Layout modeli | **Çoklu adlandırılmış layout** (V2): BREAD başına `list` ve `view` layout'ları, aksiyon bazlı form layout'u, seçilebilir liste görünümleri (R32) |
| Marka | **Settings `appearance` grubu** (başlık, logo, favicon, yükleme görseli, sidebar arka planı); görseller media'dan seçilir; config yalnızca varsayılan (R31) |
| Avatar | **Baş harf avatarı** + isteğe bağlı `tardis.user.avatar_column`; dış servis çağrısı yok (R31) |
| Dashboard | **Varsayılan kartlar** (izinli BREAD'ler için kayıt sayısı, son aktivite, plugin widget'ları) + `storage/tardis/dashboard.json` düzeni; her widget `->permission()` taşır (R27) |

### Faz sırası ve bağımlılıklar

Her faz bir öncekinin üstüne yığılmış ayrı PR'dır; sıra bağımlılığa göre (önce temel, sonra onu kullananlar).

| Faz | Teslimat | Neden bu sırada | Boyut |
|---|---|---|---|
| **0 — ilk sürüm temizliği** (R21) | Registry'ye geçiş, `ConfigBreadSource` kaldırma, `getStyles()` kaldırma, `field()` stub'ı, ölü config (B13), `BasePolicy` (B11), login → plugin (B12), `Routes` contract (B14), `UPGRADE.md` | Sonraki fazların hepsi bu API yüzeyine yazılır; BC bir kez kırılır | L |
| **1 — Panel i18n** (R22) | `lang/en`, `lang/tr`, `__()` her yerde, locale seçici | Her sonraki ekran çeviri anahtarıyla yazılmalı; sonradan taşımak ucuz değil | M |
| **1b — Tasarım sistemi** (R31) | Ortak Blade bileşenleri (Card/Badge/SlideIn/Modal/Dropdown/Toast), yükleme çubuğu, kullanıcı kartı, tema anahtarı, marka ayarları | Sonraki tüm ekranlar bu bileşenlerle yazılır; i18n anahtarlarıyla birlikte gelir | M |
| **2 — Alan sistemi** (R23) | `FormfieldPlugin`, lifecycle, add/edit kuralları, çevrilebilir mesajlar, eksik tipler | BREAD sayfaları, liste ve media picker buna dayanır | L |
| **3 — BREAD liste** (R24) | Aksiyonlar (satır/toplu), sunucu taraflı sırala/sütun-içi arama/**adlandırılmış filtre rozetleri**, sayfa başına, "filtreleri temizle", ilişki hücresi (+n daha, `link_to`), üç durumlu soft-delete, yeniden kullanılabilir liste (ilişki seçici), sıralama sayfası | Aksiyon + izin yapısını kullanır; alan `browse()` lifecycle'ı gerekir | L |
| **3b — Layout + Builder UX** (R32) | `list`/`view` layout'ları, sürükle-bırak builder, genişlik ızgarası, yan çekmece, builder liste ekranı | Liste (Faz 3) ve alan sözleşmesi (Faz 2) üstüne; BREAD şemasını genişletir | L |
| **4 — Plugin** (R25) | Routes, ayar ekranı, preferences, Filter contract'ları, `tardis:plugins` | Faz 0'daki Routes bağlantısının üstüne; Faz 6 widget filtreleri buna dayanır | M |
| **5 — Media** (R26) | `media_picker`, thumbnail/kırpma, ad şablonu, ortak yükleme doğrulaması | `media_picker` bir formfield (Faz 2), media filter bir plugin contract'ı (Faz 4) | M |
| **6 — Menü builder + widget + görünüm** (R27) | `menus.json` bindirme + sürükle-bırak, widget izinleri/yerleşim, varsayılan tema, özel CSS/JS | Menü başlıkları ve widget etiketleri i18n (Faz 1) gerektirir; widget filtreleri Faz 4 | L |
| **7 — Çok dilli içerik** (R28) | Locale sekmeleri, doğrulama modu, listede aktif dil, çevrilebilir etiketler | Alan lifecycle'ı (Faz 2) + liste (Faz 3) + menü (Faz 6) | M |
| **8 — Kurulum/DX** (R29) | `tardis:install`, `tardis:doctor`, demo veri, rehberler | Önceki fazların hepsini tek komutta toplar; en sonda | S |

### Görünüm notları (2026-10-03)

Voyager 1.7 ve 2.x kaynakları sayfa/bileşen düzeyinde incelendi: `research/06-voyager-gorunum-ve-blade-notlari.md`. Plana giren ilkeler: sunucu render + Alpine/Livewire kalır (SPA yok); tek formfield sözleşmesi çok bağlamda (browse/read/edit/add/query) çalışır, V1'in tip zinciri geri gelmez; BREAD tanımı `list`/`view` layout'ları taşır; ortak bileşen kütüphanesi (Card+actions, Badge, SlideIn, Modal, Toast); marka ayarları `appearance` grubunda; Compass benzeri araçlar kısıtlı sürümle plana alındı (Faz 8), SPA geçişi yok (`research/07` §8).

### Mimari ve kod notları (2026-10-03)

Klasör yapısı, servis sağlayıcı akışı, BREAD veri modeli, liste/kaydetme mantığı ve genişletme noktaları karşılaştırıldı: `research/07-voyager-mimari-ve-kod-notlari.md`. Plana giren yapısal kararlar: BREAD rotaları tanımdan üretilir (wildcard kalkar); BREAD tanımı `component`/`policy`/`scope` taşır; sorgu ve kaydetme `BreadQuery`/`BreadSaver` servislerine taşınır; yan etkiler BREAD olayları + listener'lar olur; genişletme API'si (`addAfterFormField`, `addAction/replaceAction/manipulateActions`, `addCss/addJs`, model haritası, `tardis.page` olayı).

## Tema, CSS ve JS mimarisi (2026-10-03)

> **Tarihsel plan (Faz 1b/4 öncesi).** Aşağıdaki "Bugünkü durum" o günü anlatır ve artık geçerli değildir. Güncel davranış: `docs/constraints.md` → *Tema sistemi* ve *Varlıklar*; güncel öneri: bu dosyanın sonundaki *Tema, CSS/JS ve Vite: hedef yapı (2026-11-17)*.

### Bugünkü durum (koddan)

- **Tek derleme çıktısı:** `vite build` yalnızca `dist/assets/app.css` üretir (196 KB, sabit ad + `?v=<md5>`); `resources/js/app.js` boş (tek yorum). Alpine tema store'u `layouts/admin.blade.php`'de **satır içi**, FOUC betiği `components/theme-boot`'ta.
- **Tema motoru yarım:** CSS `themes: all` ile DaisyUI'nin ~35 temasını paketliyor ama `themes-manifest.json` yalnızca `tardis-light`/`tardis-dark`'ı listeliyor — seçici sadece ikisini sunuyor, kalan temalar ölü ağırlık. Tema eklemek `@plugin "daisyui/theme"` yazıp **paketi yeniden build etmeyi** gerektiriyor; host bunu yapamaz.
- **Tercih yalnızca tarayıcıda:** `localStorage` (`tardis-theme-mode|light|dark`); sunucu bilmiyor, bu yüzden `<html data-theme="dark">` sabit varsayılan, ilk boyamada betikle düzeltiliyor. Giriş sayfası ve cihaz değişimi aynı tercihi taşımıyor.
- **Contract'lar:** `ThemePlugin::getTheme()` (değişken geçersiz kılma) hiçbir yerde kullanılmıyor; `getStyles()` inline `<style>` olarak yazılıyor (B8). `ThemeManager` `register()` içinde dosya/ağ okuyor (R19).
- **Plugin/host sayfaları:** paket CSS'i yalnızca Tardis view'larını taradığı için dışarıdan gelen yeni Tailwind sınıfları derlenmiş CSS'te yok.
- **Voyager karşılaştırması:** V1 yalnızca `primary_color` config'i + `additional_css/js` + Settings'ten logo/loader; koyu mod yok. V2 CSS değişkenleri (`CSS_VARS.md`, her biri `-dark` eşli) + `ThemePlugin` + plugin CSS/JS provider'ları + `Voyager::addCss/addJs`.

### Hedef yapı

| Katman | Nerede | Ne yapar |
|---|---|---|
| **1. Paket derlemesi** | `resources/css/app.css`, `resources/js/app.js` → `dist/assets/{app.css,app.js}` | Tailwind 4 + DaisyUI 5; **yalnızca yerleşik temalar** (`tardis-light/dark` + seçilmiş kısa liste); ortak bileşen sınıfları (`tardis-*`); JS: `Alpine.data/stores` (tema, toast, çekmece, tooltip, sürükle-bırak). Host Node build'i gerekmez. |
| **2. Runtime temalar** | `storage/tardis/themes.json` + `ThemePlugin::getTheme()` + Settings `appearance` | Host/plugin temaları (ad, `light|dark`, ~12 DaisyUI rengi) **veri**dir; `@tardisStyles` bunları `[data-theme="ad"]{--color-primary:…;color-scheme:…}` olarak `<style>` yazar (build yok). Değerler doğrulanır (oklch/hex/hsl deseni, ad `[a-z0-9-]`) — CSS enjeksiyonu yok. Yerleşik + runtime temalar tek `ThemeManager` listesinden gelir; manifest yalnızca yerleşik metadata. |
| **3. Tercih çözümü** | Sunucu | Sıra: **kullanıcı tercihi** (`storage/tardis/preferences.json`, kullanıcı id'sine göre; mod + light + dark tema) → **global varsayılan** (Settings `appearance.mode/theme_light/theme_dark`) → yerleşik. Sunucu `<html data-theme>`'i **doğrudan yazar** (ilk boyamada JS gerekmez); yalnız `system` modu küçük bir betikle `prefers-color-scheme`e bakar. `localStorage` yalnızca misafir/giriş sayfası ve önbellek. Tercih `Alpine.data('theme')` üzerinden sunucuya yazılır (R16: listener temizliği çözülür). |
| **4. Genişletme** | `tardis.assets.css|js` (yapıldı), plugin `CSS`/`JS` provider'ları, `Tardis::addCss()/addJs()` (Faz 0) | Dosya tabanlı ek varlıklar paket varlıklarından sonra yüklenir (yalnız http(s) ve `/…`). Arayüzden **özel CSS** (Settings `appearance.custom_css`, `manage appearance`, `</style>` kaçışı) verilebilir; **özel JS arayüzden verilmez** — yönetici oturumunda her sayfada çalışan kod olur, yalnızca config/dosya ile eklenir. |
| **5. Tasarım jetonları** | DaisyUI `--color-*` + `:root{--tardis-*}` | Ayrı jeton sistemi kurulmaz (V2'nin accent/card/input listesi DaisyUI değişkenleriyle karşılanır); paket yalnızca yazı tipi, yarıçap, sidebar genişliği, yoğunluk için `--tardis-*` ekler. Marka (başlık, logo, favicon, yükleme görseli, sidebar arka planı) `appearance` ayarlarından. |
| **6. JS yapısı** | `resources/js/app.js` + `resources/js/components/*.js` | Tek giriş dosyası `alpine:init` altında bileşenleri kaydeder (`Alpine.data('theme'|'toast'|'slideIn'…)`); Livewire 4'ün paketlediği Alpine kullanılır (ek yükleme yok), `sort`/`persist`/`collapse`/`anchor` eklentileri açıkça kullanılır. Layout'ta gömülü betik **kalmaz** (yalnızca FOUC/`system` betiği ve gerekirse nonce'lu); böylece CSP için `unsafe-inline` gerekmez. |
| **7. Yayınlama/DX** | `vendor:publish --tag=tardis-assets`, `tardis:install`, `tardis:doctor` | Dağıtılan `dist/` paketle birlikte gelir; doctor yayınlanmış paketin `dist` özetinin paket sürümüyle eşleşmediğini (bayat varlık) ve manifest/tema dosyasının okunamadığını bildirir; geliştirme `npm run dev` + `resources/hot` (var). |

### Tema motoru kuralları

1. `ThemeManager` `register()`'da I/O yapmaz; tembel çözülür ve sonuç tek istekte önbelleğe alınır (R19 biter).
2. `ThemePlugin::getStyles()` kalkar (B8c, serbest kırma); yerine yalnızca `getTheme(): array` (değişkenler) kalır.
3. Tema doğrulaması tek yerde (`Theme::fromArray`): ad deseni, `colorScheme ∈ {light,dark}`, renk değerleri izinli desenle; geçersiz tema loglanıp atlanır (sayfa kırılmaz).
4. FOUC koruması test altında kalır: sunucu tarafı çözümü ve `system` betiği için `ThemeFoucGuardTest` yeniden yazılır.
5. DaisyUI yerleşik tema listesi `themes: all` yerine seçilmiş kısa listeyle sınırlanır (paket boyutu ve "seçilemeyen tema" tutarsızlığı biter); hangi temaların gönderileceği Faz 1b'de netleşir.

### Faz dağılımı

- **Faz 0 (R21):** `ThemePlugin::getStyles()` kaldırılır, `Tardis::addCss()/addJs()`.
- **Faz 1b (R31):** `app.js` paketi + `Alpine.data('theme')`, runtime tema motoru (`themes.json`, `ThemePlugin::getTheme`), sunucuda çözülen `data-theme`, global + kullanıcı tercihi, tema listesi daraltma, marka ayarları.
- **Faz 6 (R27):** tema editörü (renk seçicili), özel CSS ayarı, önizleme.
- **Faz 8 (R29):** `tardis:doctor` varlık/tema denetimleri.

## Plugin CSS/JS mimarisi (2026-10-03)

> **Tarihsel plan (Faz 1b/4 öncesi).** Aşağıdaki "Bugünkü durum" o günü anlatır ve artık geçerli değildir. Güncel davranış: `docs/constraints.md` → *Tema sistemi* ve *Varlıklar*; güncel öneri: bu dosyanın sonundaki *Tema, CSS/JS ve Vite: hedef yapı (2026-11-17)*.

### Bugünkü durum

`Provider\CSS::provideCSS(): string` ve `Provider\JS::provideJS(): string` düz metin döndürür; `AssetManager` bunları **her istekte** `<style>`/`<script>` olarak sayfaya gömer (önbellek yok, dosya yok, CSP'de `unsafe-inline` gerekir). Plugin JS'i Alpine ve Livewire'dan **önce** yazılır; Alpine'e bağlanmak için `alpine:init`'i beklemek gerekir ve bu belgelenmemiştir. Sayfaya göre yükleme yoktur: etkin plugin'in varlıkları her sayfadadır. `tardis:make-plugin` stub'ı `composer.json`'da olmayan `tardis/core` paketini ister. V2'de de varlıklar düz metin döner ama içerik önbelleklenir ve plugin yazarı publish yapmaz (`research/voyager-2x-docs/plugins/assets.md`).

### Hedef model

| Konu | Karar |
|---|---|
| **Bildirim** | `Tardis\Assets\Asset` değer nesnesi: `Asset::file($path)` (plugin paketi içindeki dosya), `Asset::inline($text)` (isteğe bağlı, nonce'lu), `Asset::url($url)` (http(s)/kök-göreli; `integrity` desteği). Seçenekler: `scope` (`admin`\|`auth`\|`both`), `routes` (rota adı deseni), `ability`, `module`, `defer`, `position` (`head`\|`body`). `provideCSS()/provideJS()` hem `string` (eski) hem `Asset\|array<Asset>` döndürebilir; `Tardis::addCss()/addJs()` aynı nesneyi alır. |
| **Sunum** | Tardis içeriği **hash'ler** ve `/admin/_assets/{plugin}/{hash}.css\|js` rotasından `Cache-Control: public, max-age=31536000, immutable` + `ETag` ile sunar. Rota yalnızca **kayıtlı** (o isteğin kayıt tablosundaki) varlıkları hash ile çözer; yol parametresi dosya sistemine gitmez (keyfi dosya okuma yok). Hash içerik + plugin sürümünden türer: güncelleme URL'yi değiştirir, publish/`artisan` adımı gerekmez. Rota kimlik doğrulamasız (`web`) çalışır — varlıklar kod'dur, veri değil; **plugin yazarlarına belgeye "varlığa sır koyma"** uyarısı yazılır. Pasif plugin'in varlıkları yazılmaz (zaten `enabledWith`). |
| **Kapsam** | Varlık `scope` ve isteğe bağlı rota deseni/`ability` ile **bildirilir**; eşleşmeyen sayfada yazılmaz. **Formfield plugin'leri** varlıklarını alan sınıfında tanımlar (`Formfield::assets(): array`) — yalnızca o alanın bulunduğu sayfada **çekilir** (V2'de formfield plugin'i JS sözleşmesini otomatik uygular; burada sayfa başına). `wire:navigate` geçişlerinde yeni sayfanın varlıkları `data-navigate-track` ile izlenir; kayıt tablosu değişince Livewire tam yenileme yapar. |
| **Yükleme sırası** | (1) çekirdek `app.css`/`app.js` → (2) tema `<style>` → (3) plugin CSS → (4) plugin JS (`defer`) → (5) host `tardis.assets.*`. Plugin CSS çekirdekten sonra gelir; **`@layer tardis.plugins`** kullanması şablonda önerilir (çekirdek bileşen sınıflarını yanlışlıkla ezmemek için). |
| **JS API** | `window.Tardis` (sürümlü, `Tardis.version`): `component(ad, tanım)` (= `Alpine.data`), `on('ready'\|'navigate'\|'theme-changed', fn)`, `toast(mesaj, seçenekler)`, `theme` (okuma), `csrf()`. Plugin Alpine'in iç yapısına değil bu yüzeye bağlanır; yüzey `docs/JS.md`'de belgelenir, kırıcı değişiklik major sürümle. |
| **CSP** | Satır içi içerik yalnızca `Asset::inline` ile ve nonce'lu; nonce kaynağı Laravel'in Vite nonce'u (varsa) veya `tardis.csp.nonce` config'i. Çekirdek betikleri dosyadır (FOUC betiği hariç, o da nonce'lu). `Asset::url` için `integrity`/`crossorigin` yazılır. |
| **Stil sınırı** | Paket CSS'i önceden derlenmiştir (Tailwind yalnızca Tardis view'larını tarar): plugin yazarı DaisyUI bileşenlerini ve `tardis-*` yardımcı sınıflarını kullanır, gerisi için kendi CSS'ini `Asset` ile getirir. Plugin sınıfları için `tp-{plugin}-` ön eki önerilir. |
| **Tema eklentileri** | `ThemePlugin` yalnızca `getTheme(): array` (CSS değişkenleri) sağlar; `getStyles()` kalktı (B8c). |
| **Plugin ayar ekranı** | `Provider\SettingsComponent` karşılığı: Plugins sayfasında "Settings" düğmesi plugin'in adını verdiği **Livewire bileşenini** modalda açar (V2'deki Vue bileşeninin karşılığı). |
| **İskele** | `tardis:make-plugin --with-assets`: `resources/css/plugin.css`, `resources/js/plugin.js`, plugin'e özel `vite.config.js` (çıktı `dist/`), `Asset::file()` ile bağlanmış ServiceProvider, `@layer tardis.plugins` ve sınıf ön eki kuralı. `--with-assets` olmadan stub boş css/js bırakmaz. Stub `composer.json`'u `yellow-three/tardis` ister. |
| **Önbellek** | Kayıt tablosu (ad → dosya, hash) istek başına bir kez çözülür, `storage/framework/cache`'e yazılır; `tardis:assets:clear` ve plugin etkinleştir/devre dışı bunu temizler. Dosya değişimi hash'i değiştirir. |
| **Test** | Rota: doğru `Content-Type`, `immutable`, bilinmeyen hash 404, `..` ve mutlak yol reddi, pasif plugin 404; kapsam: sayfa/rota/ability eşleşmesi; sıra: çekirdek → tema → plugin; formfield varlığı yalnızca alanın sayfasında; stub'tan üretilen plugin `composer validate` ve PHP sözdizimi testi. |

### Faz dağılımı

- **Faz 0 (R21):** stub `composer.json` düzeltmesi, `Tardis::addCss()/addJs()` ve `Asset` değer nesnesinin ilk hâli (eski string sözleşmesi geriye çalışmaz — serbest kırma).
- **Faz 2 (R23):** `Formfield::assets()`; formfield plugin'leri varlıklarını alan sınıfında bildirir.
- **Faz 4 (R25):** hash'li sunum rotası, kapsam/ability süzmesi, `window.Tardis` API'si, CSP nonce, `--with-assets`, plugin ayar bileşeni, `docs/JS.md`.
- **Faz 8 (R29):** `tardis:doctor` plugin varlık denetimi (olmayan dosya, bozuk hash tablosu).

### Voyager'dan neyin alındığı

| Alan | Voyager 1'den | Voyager 2'den |
|---|---|---|
| Alanlar | Eksik tipler (Color, Coordinates, Hidden, RichText, çoklu seçim), `{field}List()` benzeri dinamik seçenekler | Lifecycle, `FormfieldPlugin`, Repeater/SimpleArray/DynamicInput, çevrilebilir kural mesajı, dizi elemanı doğrulama |
| BREAD | Satır/toplu aksiyonlar, accessor'lar, ilişki kolonları, soft-delete | JSON tanım + yedek/rollback (var), aksiyon bazlı layout, `manipulateActions` |
| Menü | UI'dan menü builder, izne göre gizleme (yapıldı) | Provider/Filter ile plugin menüsü (var) |
| Media | Picker, kırpma, ad şablonu | Media filter plugin'i |
| Plugin | — | Provider/Filter, Routes (Protected/Frontend), ayar bileşeni, preferences, dosya tabanlı durum (yapıldı), `plugins` komutu |
| Kurulum | `voyager:install` + dummy veri, `voyager:admin` (`tardis:admin` yapıldı) | — |
| Çok dillilik | Panel dilleri (34), `display_name` çevirisi | Locale tabanlı ayar yazma, doğrulama modları |
| Güvenlik | Policy + menü süzme (yapıldı) | — (V2'de BREAD yetkisi yok; Tardis zaten önde) |

### Kurallar (her faz için)

1. **Önce test:** regresyon/özellik testi yazılır, kırmızı görülür, sonra uygulanır.
2. **Tam sayfa istek testi** her yeni ekran için (`AdminShellTest` kalıbı) — Livewire bileşen testi layout'u render etmez.
3. **Yetki:** yeni ekran `Abilities` + `PermissionSeeder` + `boot()` kapısı + menü `->permission()`.
4. **i18n:** Faz 1'den sonra çıplak metin yok; `lang/en` + `lang/tr` birlikte.
5. **Dokümantasyon:** README/constraints/notes/backlog aynı PR'da güncellenir; çözülen bulgu `notes.md`'den silinir.
6. **PR:** faz başına yığılı PR; merge'ü kullanıcı onaylar.

### Kapsam dışı

- Vue/Inertia SPA arayüzü (V2'nin ön yüzü): **karar verildi, Livewire + Alpine kalır.**
- Doctrine DBAL (V1); şema keşfi Laravel şema builder ile.
- ~~Kullanıcı parolası/profil ekranı (host'a ait).~~ 2026-11-17: karar geri alındı, bkz. R39.
- Çeviri tablosu (V1 `translations`); çeviriler JSON kolonda.

## 1.0 yol haritası (2026-11-17) — kabul edildi

Karar (2026-11-17): proje incelemesindeki öneri sırası kabul edildi. Hedef, `thedevdojo/voyager` (V1) ve `voyager-admin/voyager` (V2) gibi bir sistem; konumlandırma **"Voyager'ın bakımı süren, güncel Laravel'de çalışan halefi"**. Bulgu ayrıntıları `docs/notes.md` → *GÜNCELLEME (2026-11-17)* (B17–B47, B52–B58); bu bölüm onları fazlara bağlar. Önceki *Kurallar (her faz için)* aynen geçerli: önce test, faz başına ayrı PR, merge'ü kullanıcı onaylar. `1.0.0` etiketi yalnızca kullanıcının açık onayıyla atılır; "1.0 kapısı" bir ölçüt listesidir, sürüm kararı değil.

### Faz sırası

| Faz | ID | Öncelik | Teslimat | Kapattığı bulgular | Boyut |
|---|---|---|---|---|---|
| **9 — Güvenlik ve bozuk akışlar** ✅ | R34 | P0 | Parola sıfırlama akışı (rota adı, `confirmed`, hata gösterimi, kullanıcı sayımı, davranış testleri); medya yeniden adlandırmada uzantı denetimi (denetim `MediaManager` içine); Database Explorer'da kayıpsız sütun düzenleme + üretimde salt okunur bayrağı; yetkisiz kullanıcı için çıkış; BREAD `model` → Eloquent doğrulaması; süper yönetici atama/silme korumaları; `generateModel` üzerine yazma koruması; edit/read snapshot'ında yalnızca tanımlı alanlar; global aramada `scope`; Spatie köprüsü; BREAD sayfalarında `boot()` yetkisi; panel middleware'inin Livewire güncelleme isteklerinde kalıcı olması; `/admin` kökü ve misafir yönlendirmesi; `ReservedSlugs`'ın rotalardan üretilmesi | B17, B18, B19, B20, B22, B23, B24, B25, B26, B31, B32, B68, B69, B70 | L |
| **10 — Sağlamlık** | R35 | P1 | İstek başına yetki önbelleği; ZIP indirme (rota + temizlik) ya da düğmenin kaldırılması; `BreadQuery` query log; UUID/ULID anahtarlar; `AuthenticationPlugin` sözleşmesinin bağlanması/budanması + tek guard; medya `LIKE` kaçışı ve liste performansı; durum dosyalarında kilit; `JsonBreadSource` istek içi önbellek; `BreadRemoved` temizliği; activity log düzeltmeleri; i18n artıkları; gizli tablo kontrolü; Users sayfalaması; kararlı sıralama; `strict_types` | B21, B27, B28, B29, B30, B34–B43 | L |
| **10b — Tema ve varlık mimarisi** | R56 | P1 | Tek tema kavramı: `ThemePlugin::themes()` (plugin bir tema kaynağı olur, `getTheme()` kalkar); yerleşik temalar yalnızca PHP verisinde; üretilen `themes.css` ve çekirdek `app.css`/`app.js` hash'li rotadan; biçim jetonları (`shape`) + editör; açık `@layer` sırası; Vite'ta geliştirme/üretim aynı davranış, açık `@source`; plugin iskeleti için `@reference` girişi; `tardis-boot` veri bloğu. Üç parça halinde: (a) kavramlar + tek tema kaynağı, (b) varlık sunumu + Vite, (c) biçim jetonları | B52–B58 | L |
| **10c — Çekirdek API ve isimlendirme** | R57 | P1 | Önce hatalar (davranış, isim değişmez): `scoped()` bağlamalar, koşulsuz komut kaydı, `register()`'da çözümleme yok, ön ayar kurulumu, facade docblock'u + testi, `Tardis` önbellek alanları, sürüm kaynağı, alt sağlayıcıların birleştirilmesi. Sonra tek yeniden adlandırma PR'ı: kök sınıf, auth plugin sınıfları, `Classes/` ve `Manager/` taşıması, komut ve rota adları, config kökü, marka yazımı; `class_alias` + komut alias'ı bir minör sürüm, `UPGRADE.md` eski → yeni tablosu, `arch()` testi. Livewire yapısı: ayrı bileşen kökü, düz MFC dizinleri, `wire:key`, `#[Computed]`, kararlı direktif anahtarı, tek rota grubu tanımı, güncelleme sonrası `view:clear` | B59–B67, B71–B76 | L |
| **11 — Voyager parite çekirdeği** | R36 | P1 | **İlişki alanları:** aranabilir `belongs_to`, `has_one`; tanımdaki `relationships` anahtarı ya forma bağlanır ya kaldırılır (bugün builder yazıyor, create/edit okumuyor) | — | M |
| | R37 | P1 | **Görsel alanı:** `image` gerçek tip (`resize`, `quality`, `thumbnails[]`), `thumbnail_size` config'inin bağlanması, kırpma; işleme isteğe bağlı bağımlılık | B33 | M |
| | R38 | P1 | **Ön yüz yardımcıları + çoklu menü:** `setting()` / `menu()` helper'ları, ada göre çoklu menü (`admin` biri), iç içe + sürükle-bırak (R27 kuyruğu) | — | M |
| | R39 | P1 | **Kullanıcı yönetimi:** kurulumda isteğe bağlı `users` BREAD'i (parola + rol), paket içi profil sayfası. *"Kapsam dışı → kullanıcı parolası/profil ekranı" kararı bu maddeyle geri alındı.* | B41 | M |
| | R40 | P2 | **Sıralama sayfası** (`orderColumn`, sürükle-bırak) + eksik alan seçenekleri (`timestamp` takma adı, açıklama/varsayılan) | — | S |
| **12 — Geçiş ve ilk izlenim** | R41 | P1 | **`tardis:import:voyager`:** V1 `data_types`/`data_rows` → BREAD JSON, `menus`/`menu_items` → menü, `settings` → ayarlar, `roles`/`permissions` → `tardis_*`; `--dry-run` ve eşlenemeyen alan raporu | — | L |
| | R42 | P1 | **Örnek içerik:** posts/pages/categories BREAD'leri + model/migration stub'ı + menü (`tardis:install --demo`) | — | S |
| | R43 | P1 | **Belgeler:** README konumlandırması (Voyager halefi), özelleştirme rehberi (aksiyon, özel alan, accessor, view/rota override, özel guard, soft delete), Voyager'dan geçiş rehberi | — | M |
| | R44 | P2 | **Tanım sağlığı:** `tardis:doctor`'da BREAD alanı ↔ tablo sütunu denetimi, builder'da sütunu kalmamış alan uyarısı, builder'da yedek/geri alma arayüzü | — | S |
| **13 — 1.0 kapısı** | R45 | P0 | `LICENSE`, `SECURITY.md` + private vulnerability reporting, `.gitattributes`, `CHANGELOG.md`; PHPStan/Larastan; MySQL + PostgreSQL CI matrisi; `--prefer-lowest` ve sonraki Laravel/Livewire için izinli-başarısız iş; kritik akışlar için tarayıcı testi; CI cache anahtarı | B44–B47 | M |

Sıra gerekçesi: 9 ve 10 mevcut yüzeyi güvenli ve doğru hale getirir (yeni özellik bunların üstüne yazılır); 10b ve 10c kırıcı değişikliklerdir ve parite özellikleri, rehberler ve içe aktarma komutu yazılmadan önce yapılır ki onlar eski adı ve eski tema sözleşmesini taşımasın (R37 görsel alanı ve R43 rehberleri yeni yapının üstüne yazılır); 11 Voyager kullanıcısının ilk gün aradığı parçalardır ve 12'deki içe aktarma komutu bunlara muhtaçtır (`belongs_to` ve `image` olmadan V1 tanımları eşlenemez); 13 en sonda, çünkü CI/paketleme ölçütleri önceki fazların kodunu da kapsamalı.

### Bizim eksiklerimiz (özet)

Ayrıntı ve kod referansı için parantezdeki kimliğe bak.

| Alan | Eksik | Nerede |
|---|---|---|
| Çalışmayan akışlar | Parola sıfırlama; medya ZIP indirme; yetkisiz kullanıcının çıkışı | B17, B27, B20 |
| Güvenlik | Yeniden adlandırmayla uzantı atlatma; rastgele sınıfı BREAD modeli yapma; süper yöneticiliğe yükselme ve rol silerek kilitlenme; tanımsız sütunların tarayıcıya gitmesi; aramada `scope`'un atlanması; mevcut modelin ezilmesi | B18, B24, B25, B22, B23, B26 |
| Veri güvenliği | Database Explorer'ın sütun tipini tahminle yeniden yazması; üretim kapısı ve şema denetim kaydı yok | B19 |
| Performans | Her yetki sorusu ayrı sorgu (bir istekte 66); tanımların her istekte diskten okunması; medya listesinde dosya başına 3 depolama çağrısı | B21, B36, B34 |
| Uyumluluk | UUID/ULID anahtarlar; PostgreSQL'de sayısal sütun araması; çok sunuculu dağıtımda dosya tabanlı durum; Spatie kullanan host | B29, B42, B35, B31 |
| Voyager paritesi | `belongs_to`/`has_one` alanı; gerçek görsel alanı + thumbnail; `setting()`/`menu()`; çoklu menü; kullanıcı CRUD + profil; sıralama sayfası; örnek içerik; özelleştirme rehberi | R36–R43 |
| Ölü/yarım yüzey | `AuthenticationPlugin`'in 7 metodu; tanımdaki `relationships`; `thumbnail_size` config'i; `BreadRemoved` olayı | B30, R36, B33, B37 |
| Proje hijyeni | `LICENSE`, `SECURITY.md`, `.gitattributes` yok; statik analiz yok; CI yalnızca SQLite; tarayıcı testi yok; 72 dosyada `strict_types` yok | B44–B47, B43 |

### Voyager'da olmayan, bizim yapmamız iyi olacaklar (1.x farkları)

"V1" ve "V2" sütunları yerel kaynak kopyalarına (`research/voyager-1.7`, `research/voyager-2.x`) göre; ❌ = yok, ◐ = kısmi. Bunlar 1.0 kapsamında **değil**: Faz 9–13 bitmeden başlanmaz (Voyager 2'yi bitiremeyen şey kapsamın sürekli genişlemesiydi).

| ID | Özellik | V1 | V2 | Neden değerli | Boyut |
|---|---|---|---|---|---|
| R46 | **Dışa/içe aktarma** (CSV/XLSX), "tüm eşleşenleri seç" ile toplu aksiyon (✅ 2026-11-25: CSV dışa/içe aktarma ve tüm eşleşenler; XLSX isteğe bağlı `openspout/openspout` ile) | ❌ | ❌ | Yönetim panelinden en sık istenen iş; liste filtreleri ve yetki zaten hazır | M |
| R47 | **Revizyon geçmişi + geri alma** ve eşzamanlı düzenleme uyarısı (✅ 2026-11-22) | ❌ | ❌ | Activity log `old_values`'u zaten tutuyor; üstüne diff görünümü ve "başkası değiştirdi" kontrolü | M |
| R48 | **Kayıt ve alan düzeyinde yetki** (✅ 2026-11-22: `owner_column` + `manage`, alan `ability`) | ❌ (tablo düzeyi) | ❌ (BREAD yetkisi yok) | `AuthorizationPlugin::can()` `$model` alıyor ama kullanılmıyor; "yalnızca kendi kayıtları" en yaygın kural | M |
| R49 | **2FA (TOTP), oturum listesi, kullanıcı kılığına girme** (✅ 2026-11-22: TOTP 2FA, oturum listesi, kılığa girme) | ❌ | ❌ | Paket kendi girişini taşıyor; Voyager'ın güvenlik itibarına karşı en görünür fark | M |
| R50 | **Tam denetim kaydı:** giriş/çıkış, rol ve izin değişikliği, plugin, ayar, şema; IP + user-agent; saklama ve `tardis:activity:prune` (✅ 2026-11-25: ayar, izin, medya, BREAD tanımı eklendi; tür filtresi, `retention_days` + prune komutu) | ❌ | ❌ | Bugün yalnızca BREAD kayıtları ve komut çalıştırıcı yazılıyor (B38) | S |
| R51 | **Durum deposu sürücüsü** (dosya varsayılan + veritabanı) (✅ 2026-11-25: `tardis.state.driver`, `tardis_state`, `tardis:state:copy`; B35 kapandı) | — (V1 her şeyi DB'de tutar) | ❌ (dosya) | Çok sunuculu/efemeral dağıtım; BREAD tanımlarının deploy ile taşınması (B35) | M |
| R52 | **Migration üreten Database Explorer** (✅ 2026-11-25: `tardis.database.migrations.mode` = migrate/write/off, geri alınabilir `down()`) | ❌ (doğrudan şema) | ❌ (ekran yok) | Ortamlar arası şema sapmasını bitirir; V1'in en çok şikâyet alan ekranının düzgün hali | M |
| R53 | **BREAD tanımından JSON API** (aynı tanım, aynı yetki) (✅ 2026-11-25: `tardis.api`, `/api/tardis/{slug}` CRUD + tanım listesi) | ❌ | ❌ | Panel + headless kullanım; tanım, doğrulama ve `scope` hazır | L |
| R54 | **Panel deneyimi:** komut paleti (Ctrl+K), bildirim merkezi, grafik widget sözleşmesi, ek diller + RTL (✅ 2026-11-25: palet, `tardis_notifications` + `tardis` kanalı, `ChartWidget` SVG, de/fr/es/ar + RTL) | ❌ | ◐ (global arama, bildirim) | Günlük kullanım hızı; `Locales::name()` de/fr/es/ar'ı zaten tanıyor | M |
| R55 | **Kayıtlı liste görünümleri**, satır içi düzenleme, kopyala aksiyonu, medya kullanım takibi + SVG temizleme (✅ 2026-11-25) | ❌ | ❌ | Küçük ama sık kullanılan kolaylıklar | M |

Zaten önde olduğumuz ve korunması gerekenler (Voyager'da yok ya da zayıf): ability + `scope` + policy ile BREAD yetkisi, activity log, koyu mod ve tema editörü, `tardis:doctor` ve sistem ekranları, CSP nonce ve hash'li plugin varlıkları, çeviride fallback rozetleri, 918 testlik paket. R34 bu üstünlüğün ön koşulu: açık güvenlik bulguları varken "Voyager'dan daha güvenli" iddiası yapılamaz.

### Tavsiyeler

| Konu | Tavsiye |
|---|---|
| Kapsam disiplini | 1.0 = Faz 9–13 (10b ve 10c dahil). Yeni fikir geldiğinde R46+ listesine yazılır, faza sokulmaz. V2 yerel kopyasındaki `TODO.md` bunun tersinin nereye vardığını gösteriyor. |
| "Büyük ölçüde bitti" fazlar | R23, R25, R27 hâlâ yarım durumda duruyor ve PR'ları birleşmiş. Faz 10'a başlamadan önce üçünün açık kuyruğu tek tek doğrulanıp ya ilgili R maddesine taşınmalı ya kapatılmalı; bu incelemede R22/R31/R32 böyle düzeltildi. |
| Her bulgu için regresyon testi | B17, B18, B20, B21 geçici testlerle yeniden üretildi ve testler silindi. Düzeltmeye bu testleri kalıcı yazarak başlamak (önce kırmızı) *Kurallar* madde 1'in doğrudan uygulaması. |
| Güvenlik denetimi tek yerde | Uzantı listesi, `scope`, model doğrulaması gibi kurallar çağırana bırakılınca unutuluyor (B18, B23, B24 aynı kalıp). Kural servisin içine konmalı (`MediaManager`, `BreadDefinition::query()`, tanım doğrulaması). |
| Doküman tek kaynak | **Yapıldı (2026-11-17):** durumun tek kaynağı bu dosyanın *Current State* bölümü; `PROJECT_STATUS.md` mimari özetine indirildi. Kalan: `docs/OPENCODE_*PROMPT.md` dosyaları tamamlanmış fazları anlatıyor; arşivlenmeli ya da silinmeli. |
| Veritabanı çeşitliliği | CI yalnızca SQLite. `LIKE`, JSON kolon, `->change()` gibi yerler sürücüye duyarlı; MySQL ve PostgreSQL matrisi R45'e bırakılmadan Faz 9 ile birlikte açılırsa B19 ve B42 düzeltmeleri gerçek sürücüde doğrulanır. |
| İçe aktarma komutu için veri | Arama sonuçlarında Packagist'te `yellow-three/voyager` adlı bir fork görünüyor (doğrulanmadı). Size aitse, R41 için gerçek bir V1 veritabanı dökümü test verisi olarak en değerli girdi olur. |
| Demo ve tanıtım | R42 bitince herkese açık bir demo kurulumu ve README'ye ekran görüntüleri; Voyager'ın yayılmasında "5 dakikada dolu panel" belirleyiciydi. |
| Sürüm ritmi | 0.x'te her faz bir minör sürüm (`0.2` = Faz 9, …) ve `UPGRADE.md` girdisi; böylece erken kullanıcılar güvenlik düzeltmelerini 1.0'ı beklemeden alır. |

## Tema, CSS/JS ve Vite: hedef yapı (2026-11-17) — kabul edildi (R56)

Bugünkü davranışın tarifi `docs/constraints.md` → *Tema sistemi* ve *Varlıklar*; sorunlar `docs/notes.md` B52–B58. Karar (2026-11-17): kabul edildi, **R56 / Faz 10b**. R37 görsel alanı ve R43 özelleştirme rehberi bu yapının üstüne yazılır.

### Karışıklığın kaynağı

Bugün "tema" kelimesi dört ayrı mekanizma için kullanılıyor ve bunlar birbirini eziyor:

| Mekanizma | Ne | Kim değiştirir | Sorun |
|---|---|---|---|
| DaisyUI teması (yerleşik) | `[data-theme]` paleti, derlenmiş CSS'te | Paket | Değerler PHP'de de kopyalı (B53) |
| DaisyUI teması (özel) | `[data-theme]` paleti, `themes.json` → satır içi `<style>` | Yönetici (Themes ekranı) | Yalnızca renk (B54); her sayfada satır içi |
| `ThemePlugin::getTheme()` | `:root{…}` değişken geçersiz kılma | Plugin | Tema değil; tüm temaları eziyor (B52) |
| Özel CSS / ek varlıklar | `appearance.custom_css`, `tardis.assets.css`, plugin `CSS` provider'ı | Yönetici / host / plugin | Sırası belgeli ama katman (`@layer`) bildirilmemiş |

### Önerilen kavramlar (her biri tek iş yapar)

| Kavram | Tanım | Kaynağı | Sayfaya nasıl gelir |
|---|---|---|---|
| **Tema** | Seçilebilir DaisyUI paleti: ad, şema, renkler **ve biçim** (`radius`, `size`, `border`, `depth`, `noise`) | Yerleşik (PHP verisi) + özel (`themes.json`) + **plugin** (`ThemePlugin::themes(): array` — tam tema tanımları döndürür, seçicide "plugin" rozetiyle görünür) | Hepsi tek `ThemeManager` listesinden, tek üretilmiş `themes.css` olarak |
| **Marka** | Temadan bağımsız kimlik: başlık, logo, favicon, yazı tipi (`--tardis-font-sans`), sidebar genişliği | Settings `appearance` | `:root{--tardis-*}`; yalnızca `--tardis-*` adları |
| **Ek CSS** | Tema dışı stil: plugin bileşen CSS'i, host stil dosyası, yönetici özel CSS'i | `Provider\CSS`, `Tardis::addCss()`, `tardis.assets.css`, `appearance.custom_css` | Dosya → hash'li rota; satır içi → nonce'lu |

`ThemePlugin::getTheme()` kalkar (serbest kırma, `UPGRADE.md`): bir plugin palet sunmak istiyorsa `themes()` ile tema verir; tek tük değişken ezmek istiyorsa bunu açıkça `Provider\CSS` ile yapar ve ne yaptığı adından belli olur. Böylece "DaisyUI teması" ile "tema plugin'i" aynı şey olur: plugin yalnızca bir tema **kaynağıdır**.

### Önerilen yapı

| Katman | Öneri | Kapattığı |
|---|---|---|
| **Tema verisi tek yerde** | Yerleşik temalar yalnızca `BuiltinThemes.php`'de kalır; `app.css`'ten `@plugin "daisyui/theme"` blokları çıkar. `ThemeManager::css()` yerleşik + özel + plugin temalarının hepsini üretir; varsayılan tema ek olarak `:root` seçicisiyle yazılır (JS'siz ve `data-theme`'siz sayfa da doğru boyanır). | B53 |
| **`themes.css` hash'li rotadan** | Üretilen tema CSS'i satır içi `<style>` yerine `/{prefix}/_assets/{hash}.css` ile sunulur (hash = içerik; `themes.json` ya da plugin listesi değişince URL değişir). Sayfa başına tekrar eden satır içi blok ve onun CSP nonce ihtiyacı kalkar. | B53, B58 |
| **Biçim jetonları** | `Theme` şemasına isteğe bağlı `shape` alanı: izinli anahtarlar DaisyUI 5'in tema değişkenleri, değerler uzunluk/sayı deseninden geçer (`Theme::isSafeColor()` gibi tek doğrulayıcı). Tema editörüne yarıçap/boyut/kenarlık alanları (yapıldı 2026-11-22: kaydırıcı yerine uzunluk alanları, varsayılan yer tutucu olarak). | B54 |
| **Katman sırası açık** | `app.css`'in başında tek `@layer` bildirimi (Tailwind + DaisyUI katmanları, ardından `tardis`, `plugins`); plugin iskeleti CSS'ini `@layer plugins` içine yazar; yönetici özel CSS'i ve host dosyaları katmansız, en sonda (bilerek en güçlü). Sıra bir testle pinlenir. | B52'nin tekrarı |
| **Çekirdek varlıklar da hash'li rotadan** | `app.css` / `app.js` varsayılan olarak `resources/compiled`'dan aynı `AssetController` ile sunulur: yayınlama adımı, bayat kopya ve istek başına `md5_file()` kalkar. Statik sunum isteyen host için `tardis.assets.publish = true` (bugünkü davranış) seçenek olarak kalır; `tardis:doctor` yalnızca o modda bayatlık denetler. | B55 |
| **Vite: tek davranış** | İki ayrı config kalır (IIFE gerekçesi geçerli). Geliştirmede çekirdek betik de klasik olarak yüklenir (`vite build --watch` çıktısı) ya da betik Alpine başlangıç sırasına bağımlı olmaktan çıkarılır; CSS için `@vite/client` eklenir. Hot dosyasına gerçek sunucu adresi (`server.origin`/https) yazılır. | B56 |
| **Tailwind kaynakları açık** | `@import "tailwindcss" source(none);` + `@source "../views";` (ve varsa `src/` içindeki sınıf üreten PHP). Derleme yalnızca view'lara bağlı olur; CI'daki "bundle güncel mi" denetimi kararlı hale gelir. | B57 |
| **Plugin/host için Tailwind** | Paket, tema değişkenlerini ve `tardis-*` yardımcılarını taşıyan küçük bir giriş dosyası sunar; plugin iskeleti kendi CSS'inde `@reference` ile buna bağlanır ve yalnızca **kendi** sınıflarını derler (çekirdek CSS kopyalanmaz). Kendi derlemesini yapan host için `@source "vendor/yellow-three/tardis/resources/views"` satırı belgelenir. İskelet `vite` sürümü paketle hizalanır. | B58 |
| **JS** | `resources/js/app.js` modüllere bölünür (`theme.js`, `toasts.js`, `builder-layout.js`, `api.js`) ve yine tek IIFE'ye paketlenir. Sunucu verisi `<script type="application/json" id="tardis-boot">` veri bloğuyla verilir (çalıştırılabilir değil → nonce gerekmez); yalnızca `system` modunun tek satırlık betiği satır içi kalır. Genel yüzey `window.Tardis` olarak kalır (`docs/JS.md`). | B58 |

### Sayfada beklenen sıra (hedef)

1. `<html data-theme="…">` — sunucu yazar (değişmiyor)
2. `tardis-boot` veri bloğu + yalnızca `system` modunda engelleyici tek satır
3. `app.css` (hash'li) → `themes.css` (hash'li) → plugin CSS (`@layer plugins`) → marka değişkenleri → host `tardis.assets.css` → yönetici özel CSS'i
4. `app.js` (hash'li, klasik) → plugin JS (`defer`) → host `tardis.assets.js` → `@livewireScripts`

### Riskler ve açık sorular

| Konu | Not |
|---|---|
| DaisyUI'nin temayı kendi derlemesinden beklemesi | Yerleşik temaları CSS'ten çıkarmak `themes: false` ile zaten denenmiş yolun devamı (özel temalar bugün böyle çalışıyor); yine de `prefersdark`/`:root` varsayılanı ve `color-scheme` için tarayıcıda görsel doğrulama gerekir. Önce bir tema ile dene. |
| Hash'li rotadan çekirdek varlık | PHP üzerinden sunum statik dosyadan yavaştır; `immutable` önbellek ilk istekten sonrasını kapatır. Yoğun sitelerde `publish = true` önerilir. |
| `ThemePlugin` sözleşme kırılması | Bugün paket içinde gerçek uygulayıcısı yok (yalnızca test fixture'ı); kırma maliyeti düşük. |
| Sıra | R56'yı parçalamak mümkün: (a) kavramlar + `ThemePlugin::themes()` + tek tema kaynağı, (b) varlık sunumu + Vite, (c) biçim jetonları + editör. (a) ve (b) birbirinden bağımsız. |

## Çekirdek API ve isimlendirme (2026-11-17) — kabul edildi (R57)

İncelenen dosyalar: `src/TardisServiceProvider.php`, `src/Facades/Tardis.php`, `src/Tardis.php`. Hatalar `docs/notes.md` B59–B67; bu bölüm yapı ve isim kararlarıdır. Karar (2026-11-17): kabul edildi, **R57 / Faz 10c**. Tablolardaki yeni adlar öneridir; kesin adlar yeniden adlandırma PR'ında tek tek onaylanır. **Zamanlama önemli:** isim değişiklikleri kırıcıdır ve `0.x`'te ucuzdur; R41 (Voyager içe aktarma) ve R43 (rehberler) yazılmadan, yani Faz 11'den önce yapılmalı, sonrasında her rehber ve plugin iskeleti eski adı taşır.

### Servis sağlayıcı

| Konu | Bugün | Öneri |
|---|---|---|
| Bağlama ömrü | Tüm manager'lar `singleton` | İstek durumu ya da dosyadan okunan durum taşıyanlar `scoped()` (`AssetManager`, `ThemeManager`, `SettingsManager`, `MenuManager`, `PluginManager`, `UserPreferences`, `ThemePreference`); kayıt defterleri (`FormfieldManager`, `ActionManager`, `WidgetManager`) `singleton` kalır. Plugin **kaydı** (sınıf listesi) ile plugin **durumu** (açık/kapalı dosyası) ayrılırsa kayıt singleton, durum scoped olabilir. (B59) |
| `register()` / `boot()` ayrımı | `register()` içinde `PluginManager` çözülüyor, plugin örnekleniyor | `register()` yalnızca bağlama yapar; varsayılan kimlik doğrulama ve yetkilendirme plugin'leri birlikte `boot()`'ta kaydolur. (B61) |
| Sağlayıcı sayısı | Üç sağlayıcı, ikisi yinelenen iş yapıyor | Tek `TardisServiceProvider`; `tardis-media` config'i de buradan birleştirilir. (B66) |
| Metot adları | `registerAliases()` alias kaydetmiyor (facade kökü + `LegacyConfigReader` bağlıyor); `registerPluginServiceProviders()` plugin değil paketin kendi alt sağlayıcılarını kaydediyor; `registerDefaultAuth()` ile `registerDefaultAuthorization()` bir bakışta ayırt edilmiyor | `registerBindings()`, (kalkar), `registerDefaultAuthenticationPlugin()` / `registerDefaultAuthorizationPlugin()` |
| `boot()` düzeni | İki Blade direktifi ve anonim bileşen yolu satır içi, gerisi metotlarda | `registerBladeDirectives()` ve `registerBladeComponents()`; `boot()` yalnızca çağrı listesi olur |
| Komutlar | Yalnızca konsolda | Koşulsuz `$this->commands([...])`. (B60) |
| İlk kurulum | Her web isteğinde ön ayar kontrolü | `tardis:install` + `SettingsManager` tembel yüklemesi. (B62) |
| Config birleştirme | `mergeConfigFrom` yüzeysel birleştirir: eski bir `config/tardis.php` yayınlamış host, sonradan eklenen iç içe anahtarları (`system.commands`, `translation.tabs`) alamaz | Okuyan kod varsayılanı kendisi veriyor (çoğu yerde öyle); `tardis:doctor`'a "yayınlanmış config'te eksik anahtar" denetimi |
| Yayın etiketleri | `tardis-config`, `tardis-icons-config`, `tardis-media-config`, `tardis-views`, `tardis-migrations`, `tardis-assets`, `tardis-lang` | Tek kalıp: `tardis-config` üç config dosyasını birlikte yayınlasın (ya da `tardis-config-icons` / `tardis-config-media`) |

### Facade ve `Tardis` sınıfı

| Konu | Bugün | Öneri |
|---|---|---|
| İki `Tardis` sınıfı | `Tardis\Tardis` (kök) ve `Tardis\Facades\Tardis` (facade) + global `Tardis` alias'ı. Sayfalar facade'ı, `theme-boot` kök sınıfın statik `version()`'ını kullanıyor; `use` satırına bakmadan hangisi olduğu anlaşılmıyor | Kök sınıf `Tardis\TardisManager` (ya da `Tardis\Panel`), facade `Tardis\Facades\Tardis` olarak kalır; container anahtarı sınıf adı, `'tardis'` onun alias'ı |
| Önbellek alanları | Altı `?Manager` alanı | Kaldır; her erişimci doğrudan `app(X::class)` döndürsün. (B64) |
| Erişimci kapsamı | `plugins()`, `menu()`, `widgets()`, `settings()`, `formfields()`, `bread()` var; tema, varlık, aksiyon, medya için yok | Eksikleri ekle: `themes()`, `assets()`, `actions()`, `media()`, `preferences()`. Böylece her manager aynı yoldan ulaşılır |
| Kısayol metotları | `addCss`, `addJs`, `addAction`, `replaceAction`, `manipulateActions` — yalnızca iki manager için kısayol var | İkisinden biri: ya kısayollar kalkar (`Tardis::assets()->addCss()`, `Tardis::actions()->add()`), ya her kayıt işi için kısayol olur (`addMenuItem`, `addWidget`, `registerFormfield`, `registerPlugin`). Öneri: Voyager'dan gelenlerin tanıdığı kısayolları tut (`addCss`, `addJs`, `addAction`), eksik olanları tamamla; `manipulateActions` → `modifyActions` |
| Çoğul/tekil | `menu()` tekil, diğerleri çoğul; `bread()` | `menus()` (R38 çoklu menü ile anlam kazanır), `breads()` |
| Sürüm | Elle yazılı sabit, statik metot | `InstalledVersions` + sabit yedek. (B65) |
| Docblock | Yanlış namespace, eksik metotlar | Düzelt ve bir testle pinle: facade docblock'undaki her `@method` kök sınıfta var mı, dönüş tipi gerçek sınıf mı. (B63) |

### Paket genelinde isimlendirme

| Konu | Bugün | Öneri |
|---|---|---|
| Aynı kısa ada sahip sınıflar | `Tardis\Plugins\AuthenticationPlugin` (somut) ile `Tardis\Contracts\Plugins\AuthenticationPlugin` (sözleşme); yetkilendirmenin somutu başka yerde ve başka kalıpta: `Tardis\Auth\TardisAuthorizationPlugin` | İkisi de `Tardis\Auth` altında, rolünü söyleyen adla: `SessionAuthenticationPlugin`, `RoleAuthorizationPlugin`. `src/Plugins/` klasörü kalkar |
| Plugin anahtarı ile `name()` | Anahtar `tardis-auth`, `name()` `'TARDIS Default Auth'`; diğeri anahtar `tardis-authorization`, `name()` `'tardis-authorization'` | Anahtar = `name()` = kebab-case kimlik (`tardis-authentication`, `tardis-authorization`); görünen ad ayrı bir `label()` ve çevrilebilir |
| `Classes/` klasörü | `DynamicInput`, `MenuItem`, `UserMenuItem`, `Setting`, `Translation`, `Widget` — ortak noktaları yok | Alanına taşı: `Menu\MenuItem`, `Menu\UserMenuItem`, `Settings\Setting`, `Dashboard\Widget`, `Formfields\DynamicInput`, `Support\Translation` |
| Manager'ların yeri | `Manager\*` (tekil klasör adı), ama `Bread\BreadManager`; tema hem `Manager\ThemeManager` hem `Theme\*` | Alan klasörü kazanır: `Theme\ThemeManager`, `Menu\MenuManager`, `Media\MediaManager`, `Settings\SettingsManager`, `Plugins\PluginManager`, `Assets\AssetManager`. `Manager/` klasörü kalkar; `BreadManager` zaten bu düzende |
| Sınıf adında tekrar eden önek | `Tardis\Commands\TardisInstallCommand`, `Tardis\TardisMediaServiceProvider` | Namespace zaten `Tardis`: `Commands\InstallCommand`, `Commands\MakeBreadCommand` |
| Komut adları | `tardis:make-bread`, `tardis:make-model`, `tardis:make-plugin` ama `tardis:bread:migrate`, `tardis:bread:export` | Tek kalıp, Laravel'inki: `tardis:make:bread`, `tardis:make:model`, `tardis:make:plugin`, `tardis:bread:import-legacy` (bugünkü `bread:migrate` veritabanı migration'ı sanılıyor), `tardis:bread:export`, ileride `tardis:import:voyager` |
| Rota adları | `tardis.bread.edit` = builder'da tanım düzenleme, `tardis.bread.edit.item` = kayıt düzenleme; `tardis.media` ve `tardis.media.browse` aynı ekran; `tardis.permissions`, `tardis.roles` `.index`'siz, diğerleri `.index`'li | Tanım için `tardis.builder.*`, kayıt için `tardis.bread.{index,create,show,edit}`; tüm liste ekranları `.index` |
| Marka yazımı | Kodda ve metinlerde `TARDIS` (31 yer) ve `Tardis` (12 yer) karışık | Tek yazım (`Tardis`); `lang/` ve README'de düzelt |
| Config kökleri | `tardis.*`, ayrıca `tardis-media.*` ve `tardis-icons.*` | Tek kök: `tardis.media.*`, `tardis.icons.*` (dosyalar ayrı kalabilir: `config/tardis/media.php` yerine `mergeConfigFrom(..., 'tardis.media')`) |
| Tablo adları | `tardis_roles`, `tardis_permissions`, `tardis_media` ama `activity_logs` | `tardis_activity_logs` (B38) |
| `strict_types` | 72 dosyada yok | Hepsinde olsun; Pint kuralıyla zorla (`declare_strict_types`). (B43) |

### Rotalar, direktifler ve Livewire 4 yapısı

| Konu | Bugün | Öneri |
|---|---|---|
| Panel middleware'i | Yalnızca ilk sayfa isteğinde çalışır; Livewire güncellemeleri atlar (B68) | `Livewire::addPersistentMiddleware([AdminMiddleware::class])`; her bileşende ayrıca `boot()` kapısı (B32). İkisi birlikte: middleware oturumu ve `access admin`'i, `boot()` ekranın kendi ability'sini korur |
| Rota grubu | Ön ek, ad ve middleware listesi üç dosyada elle yazılı | Tek yerde tanım: `Tardis\Http\PanelRoutes::group(fn () => …)` (ya da `Route::tardis()` makrosu); `tardis.admin.middleware`, `guard`, `domain` config'i buradan okunur (R49 "panel yapılandırması"nın altyapısı) |
| Kök ve misafir | `/admin` 404; girişli kullanıcı login formunu görür | `/admin` → dashboard'a yönlendirme; login/forgot/reset rotalarına misafir kontrolü (girişliyse dashboard'a) |
| Rezerve adlar | Elle tutulan liste, dört ad eksik (B70) | Liste kayıtlı `tardis.*` rotalarının ilk yol parçasından üretilir; elle liste yalnızca rota olmayan adlar için kalır |
| Çıkış | Closure, plugin'i atlıyor, `tardis.admin` grubunda | `LogoutController` → `AuthenticationPlugin::logout()`; kimlik doğrulama yeterli, `access admin` gerekmez (B20) |
| Blade direktifleri | Tam sınıf adı derlenmiş view'a gömülü; iki ayrı direktif; sıra layout'a emanet | Direktif kararlı bir container anahtarı çağırır (`app('tardis.assets')`); layout'lar için `<x-tardis::head />` ve `<x-tardis::scripts />` bileşenleri tema önyüklemesini, stilleri, `@livewireStyles`/`@livewireScripts`'i doğru sırada tek yerden yazar — yeni layout ekleyen sırayı bilmek zorunda kalmaz |
| Anonim bileşenler | `anonymousComponentPath` + `loadViewsFrom` aynı etiketi çözüyor | Yalnızca `loadViewsFrom` (yayınlanan `vendor/tardis/components` geçerli olur); davranış bir testle pinlenir |
| Livewire kökü | Tüm `resources/views` | `resources/views/livewire/` (sayfalar ve çocuk bileşenler burada, adlar aynı kalır); layout, bileşen, formfield, widget view'ları Livewire ad alanının dışında |
| Dizin düzeni | MFC dizini içinde MFC dizini; çocuk bileşen `pages/` altında | Her MFC kendi yaprak dizininde: `pages/database/index/`, `pages/database/create/`, `pages/database/edit/`, `pages/system/index/`…; çocuk bileşenler `livewire/components/` altında (`tardis::components.media-picker`) |
| Sayfa yayını | `tardis-views` Livewire sayfalarını geçersiz kılmıyor (B67) | Sayfa değiştirmenin desteklenen yolu BREAD tanımındaki `components` ve (eklenirse) sabit ekranlar için `tardis.pages` config haritası; `tardis-views` yalnızca layout + anonim bileşenleri yayınlar |
| Derleme önbelleği | Paket güncellemesinde bayat kalabilir (B71) | `tardis:install` ve yeni bir `tardis:upgrade` komutu `view:clear` çalıştırır; `composer.json` `post-autoload-dump` önerisi `UPGRADE.md`'de; doctor denetimi |
| Bileşen yazım kuralları | `wire:key` yok, eski computed getter'lar, tam sayfa yönlendirme | `.claude/AGENTS.md` ve `docs/LIVEWIRE_PAGE_ORGANIZATION.md`'ye üç kural: döngüde `wire:key` zorunlu, türetilmiş veri `#[Computed]`, panel içi yönlendirme `navigate: true`. İlk ikisi view tarayan bir testle zorlanır (`NoHardcodedTextTest` kalıbı) |

### Nasıl uygulanır

1. **Önce hatalar** (B59–B67): davranış düzeltmesi, isim değişmez; ayrı PR.
2. **Sonra tek bir "yeniden adlandırma" PR'ı**: sınıf taşıma, komut ve rota adları, config kökü. Eski sınıf adları için `class_alias` ve eski komut adları için `setAliases()` bir minör sürüm boyunca tutulur; `UPGRADE.md`'ye eski → yeni tablosu yazılır.
3. Taşımayı bir mimari testiyle koru (Pest `arch()`): `Tardis\Classes` ve `Tardis\Manager` namespace'leri boş kalır, `Contracts` dışında hiçbir somut sınıf sözleşmeyle aynı kısa adı taşımaz.

## Kaynaklar — Voyager dış durum bilgisi (tek tek doğrulanmadı)

- SonarSource, "The Tainted Voyage: Uncovering Voyager's Vulnerabilities": <https://www.sonarsource.com/blog/the-tainted-voyage-uncovering-voyagers-vulnerabilities> — CVE-2024-55417 (medya yüklemesinde dosya tipi denetiminin atlatılması) ve CVE-2024-55416 (Compass'ta yansıtılan XSS); R34'teki B18'in neden P0 olduğunun gerekçesi.
- CVE-2024-55417 kaydı: <https://www.wiz.io/vulnerability-database/cve/cve-2024-55417>
- `voyager-admin/voyager` paket durumu (etiketli 2.x sürümü görünmüyor): <https://laraplugins.io/plugins/voyager-admin/voyager>
