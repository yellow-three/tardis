# Backlog — Öncelikli İş Birikimi (docs/backlog.md)

Uzun ömürlü, dalga dalga ilerleyen işlerin **roadmap** takibi. Kaynak: `.omo/plans/` ve `.omo/drafts/`. `docs/notes.md`'den farkı: **kalıcı** dosyadır — buradaki madde çözülünce satırı silinmez, **commit ile güncellenir** ve tamamlandığında işaretlenir (roadmap'tir, defter değil). Aktif bulgu detayı `docs/notes.md`'de yaşar; bu dosya ona işaret eder.

## Öncelik ölçeği

- **P0** — Kırıcı / güvenlik / bloke edici (üretim öncesi şart)
- **P1** — Önemli eksik veya hata (kısa vade, kullanıcı görür)
- **P2** — İyileştirme / teknik borç / araç (orta vade)

## Current State

Son güncelleme: 2026-10-03 (Voyager parity planı eklendi). ID'ler `R` = roadmap sırası; `notes.md` bulgularıyla (`B*`) karışmaz.

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
| R21 | P0 | **Faz 0 — 2.0.0 temizliği:** `FieldType` → registry, `config/bread` salt okunur, `getStyles()` kalktı, `field()` stub'ı kalktı, BREAD rotaları tanımdan üretilir (`component`/`policy`/`scope`, rezerve slug'lar), BREAD olayları + listener'lar + çalışan activity log, `tardis.page` olayı, `Asset` + `Tardis::addCss/addJs`, `tardis:make-plugin` stub düzeltmeleri, sürüm `2.0.0` + `UPGRADE.md`. **Ertelenenler:** `addAfterFormField` → R23 (B16'ya bağlı), `addAction/replaceAction` → R24, model haritası → R33 | ✅ Çözüldü | `feat/phase-0-cleanup` | `UPGRADE.md` |

### Açık

| ID | Öncelik | İş | Durum | Commit | Yönlendirme |
|----|---------|----|-------|--------|-------------|
| R9 | P1 | **Voyager → Tardis özellik transferi.** BREAD audit turu bu planın parçası; 6 P0/P1 düzeltmesi landı. Kalan açık bulgular B1, B2, B4, B5. | 🔄 Devam ediyor | `e0d00ac`…`8bb20a3` | `docs/notes.md` + `.omo/plans/voyager-transfer.md` |
| R12 | P2 | **Voyager II kalan özellikler** — `.omo/plans/voyager-ii-features.md` yerine aşağıdaki **Voyager parity planı** (R21–R30) geçerli. | ➡️ R21–R30'a taşındı | — | Voyager parity planı |
| R13 | P2 | **Laravel Vite plugin entegrasyonu.** Plan hâlâ `status: draft`. | ⏳ Taslak | — | `.omo/plans/laravel-vite-plugin.md` |
| R14 | P2 | **Graphify MCP'yi OpenCode'a bağla.** Doğrulandı: `~/.config/opencode/opencode.json` içinde `graphify` **yok**. Yalnızca araç/observability, ürün yüzeyi yok. | ⏳ Açık | — | `.omo/plans/graphify-mcp-setup.md` |
| R15 | P1 | **BREAD açık bulguları (4 madde kaldı).** (Locked property, search yetkisi, slug doğrulama ve validation `|` düzeltmeleri 2026-10-03'te kapandı.) `registerType()` erişilemezliği, ölü `field()` stub, `searchOptions()` filtresiz, fail-open authorization. Kullanılmayan Spatie bağımlılığı kaldırıldı (`bf24401` sonrası) — paket hiçbir yerde Spatie'ye derleme zamanı bağlı değildi, yalnızca `method_exists()` ile duck-typ ediyordu. | ⏳ Açık | — | `docs/notes.md` → B1, B2, B4, B5 |
| R16 | P2 | **`Alpine.store('theme')` → `Alpine.data('theme')` + `$persist` geçişi.** R8 ile birlikte planlanmıştı ama FOUC'yu etkilemiyor ve planın gövdesi `availableThemes` / `lightThemes` / `darkThemes` getter'larını düşürüyor — `pages/settings/settings.blade.php` bunları `$store.theme.availableThemes` üzerinden okuyor, gövde aynen uygulansaydı settings sayfası kırılırdı. Ayrıca plan store'un `resources/js/app.js` içinde olduğunu varsayıyor; oysa layout'a inline gömülü. Gerçek gerekçe: listener cleanup (`wire:navigate` sırasında birikme). | ⏸️ Ertelenmiş | — | `.omo/plans/theme-fix-wire-navigate.md` (Todo 2-7) |
| R19 | P2 | **`ThemeManager` boot-time I/O.** `TardisServiceProvider::register()` içinde dosya/ağ okuması yapılıyor, hatalar yalnızca loglanıyor. | ➡️ R31'e taşındı (tema motoru yeniden yazılırken ortadan kalkar) | — | Tema, CSS ve JS mimarisi |
| R22 | P1 | **Faz 1 — Panel i18n.** Tüm sabit metinler `__('tardis::…')`, `lang/en` (varsayılan) + `lang/tr`, locale seçici, TR/EN karışık aria etiketlerinin temizliği | ⏳ Plan | — | Faz 1 |
| R23 | P1 | **Faz 2 — Alan sistemi.** **B16:** create/edit'in satır içi tip zincirini `Formfield::render()` view'larına taşımak (özel tip sonunda gerçekten çizilir), `addAfterFormField` kancası, `BreadSaver` servisi (create/edit kopya kodu kalkar), `BreadDefinition`/layout üzerinde `visibleFor/searchable/orderable/relationships`, `FormfieldPlugin` + registry, V2 lifecycle (`browse/read/edit/add/store/update($old)/stored/updated`), add/edit ayrı kural seti, çevrilebilir doğrulama mesajı, dizi elemanı doğrulama, eksik tipler (Color, Coordinates, Hidden, RichText, çoklu checkbox/select, Repeater, SimpleArray, MediaPicker) | 🔄 Kısmen: B16 (alanlar `Formfield::render()` view'ından çizilir), `BreadSaver`, `Formfield::configure()`, Color + Hidden tipleri bitti; lifecycle, `addAfterFormField`, add/edit kural seti, çevrilebilir mesaj, kalan tipler açık | — | Faz 2 |
| R24 | P1 | **Faz 3 — BREAD liste.** `addAction/replaceAction/manipulateActions` kayıt API'si, `BreadQuery` servisi (V2 `Browsable` parçaları: arama, sütun filtresi, adlandırılmış filtre/scope, sıralama, soft-delete, eager load, `warnings[]`), BREAD başına `scope`, `Action` sınıflarının UI'ya bağlanması (satır + toplu), sunucu taraflı sırala/filtre/ara/sayfa boyutu, ilişki kolonları (eager load), soft-delete geri yükleme/kalıcı silme, aksiyon bazlı layout, browse accessor'ları | 🔄 Kısmen: `BreadQuery` (arama/sıralama/sayfa boyutu/soft-delete), `ActionManager` + `Tardis::addAction/replaceAction/manipulateActions`, satır + toplu aksiyon UI'ı, geri yükleme/kalıcı silme bitti; sütun filtresi, adlandırılmış filtre, ilişki kolonları (eager load), browse accessor'ları açık | — | Faz 3 |
| R25 | P1 | **Faz 4 — Plugin genişletilebilirliği.** **Plugin CSS/JS mimarisi (aşağıdaki bölüm):** `Asset` değer nesnesi, hash'li önbellekli sunum rotası, bildirimli kapsam, formfield varlık çekme modeli, `window.Tardis` JS API'si, `--with-assets` iskelesi, CSP nonce, `Routes` contract'ı + yetkili/ön yüz rota ayrımı, plugin ayar ekranı + preferences, `Filter\Widgets/Layouts/Media`, `tardis:plugins` komutu, plugin sürüm/bağımlılık gösterimi | 🔄 Büyük ölçüde bitti: `Asset::file()` + hash'li sunum, kapsam süzmesi, formfield varlıkları (`Formfield::assets()`), CSP nonce, `--with-assets`, plugin ayar bileşeni (`SettingsComponent`), `Provider\Routes` bağlandı, `docs/JS.md`, `tardis:plugins`; açık: `Filter\Widgets/Layouts/Media`, plugin bağımlılık gösterimi | — | Faz 4 |
| R26 | P2 | **Faz 5 — Media entegrasyonu.** BREAD `media_picker` alanı, thumbnail + kırpma, `{uid}/{date:…}/{random:n}` dosya adı şablonu, tek yükleme doğrulaması (BREAD `FileField` dahil), media filter plugin'i | ⏳ Plan | — | Faz 5 |
| R27 | P2 | **Faz 6 — Menü builder + Dashboard widget'ları + görünüm.** `storage/tardis/menus.json` bindirmesi, nestable sürükle-bırak, gizle/yeniden adlandır/özel link, varsayılan dashboard kartları + `storage/tardis/dashboard.json` düzeni + widget izinleri, rol sayfasında gruplu izin ağacı, tema editörü (appearance) + özel CSS ayarı (`manage appearance`; özel JS arayüzden **verilmez**, yalnızca config dosyasıyla) | 🔄 Büyük ölçüde bitti: `menus.json` bindirmesi + Menü oluşturucu (gizle/yeniden adlandır/bölüm/sıra/özel link; yukarı-aşağı, sürükle-bırak değil), widget'a dayalı dashboard + `dashboard.json` (gizle/sırala/genişlik, `manage dashboard`), rol sayfasında gruplu izin ağacı, tema editörü (`manage appearance`), özel CSS ayarı bitti; sürükle-bırak, iç içe menü, widget izinlerinin rol ekranında gösterimi, Settings'te özel temaların varsayılan seçimine bağlanması açık | — | Faz 6 |
| R28 | P2 | **Faz 7 — Çok dilli içerik.** Form içinde locale sekmeleri, 'tüm diller / aktif dil' doğrulama modu, listede aktif dil, BREAD etiketleri + menü başlıkları çevrilebilir (JSON kolon biçimi korunur) | ⏳ Plan | — | Faz 7 |
| R29 | P2 | **Faz 8 — Kurulum, DX ve sistem araçları.** `tardis:install` (migrate + seed + ilk admin + asset publish), isteğe bağlı demo veri, `tardis:doctor` + panelde **Sistem** sayfası, **salt okunur log görüntüleyici** (`view logs`) ve **izin listeli komut çalıştırıcı** (`run commands`, `local` dışında kapalı, activity log'a yazar), güncellenmiş plugin iskeleti, `UPGRADE.md`, kapsamlı örnek | ⏳ Plan | — | Faz 8 |
| R33 | P2 | **Model haritası.** `config('tardis.models')` ile host'un `Role`, `Permission`, `Media`, `ActivityLog` modellerini değiştirebilmesi (V1 `Voyager::useModel`); şu an sınıflar sabit | ⏳ Açık | — | `research/07` §5 |
| R30 | P1 | **Kalite kapısı (her faz).** Önce test, tam sayfa istek testi, `boot()` yetki kapısı, `lang` anahtarı, `docs/` güncellemesi, faz başına yığılı PR | 🔄 Sürekli | — | Voyager parity planı → Kurallar |
| R31 | P1 | **Faz 1b — Tasarım sistemi (ortak UI bileşenleri).** `x-tardis::card` (actions slotu), badge, slide-in çekmece, modal, dropdown, toast/bildirim (onay düğmeli), sayfa yükleme çubuğu, sidebar kullanıcı kartı (baş harf avatarı + `avatar_column`) + kalıcı durum, 3 durumlu tema anahtarı, marka ayarları, **tema/CSS/JS mimarisi (aşağıdaki bölüm): `dist/assets/app.js`, `Alpine.data('theme')`, runtime tema motoru, sunucuda çözülen `data-theme`, global + kullanıcı tema tercihi, DaisyUI tema listesinin daraltılması** (`appearance` grubu: başlık/logo/favicon/yükleme görseli), RTL değerlendirmesi | ⏳ Plan | — | `research/06` §3.9, §5 |
| R32 | P1 | **Faz 3b — Layout + Builder UX.** BREAD tanımı `list`/`view` layout'ları, sürükle-bırak + 6'lık genişlik ızgarası, yan çekmecede alan seçenekleri, builder tablo listesi (layout sayıları + Backup), `legend`/bölüm başlığı | ⏳ Plan | — | `research/06` §3.4 |

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
| Geriye uyumluluk | **Serbest kır, `2.0.0` çıkar** (redesign yayınlanmadı); `UPGRADE.md` ile |
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
| **0 — 2.0.0 temizliği** (R21) | Registry'ye geçiş, `ConfigBreadSource` kaldırma, `getStyles()` kaldırma, `field()` stub'ı, ölü config (B13), `BasePolicy` (B11), login → plugin (B12), `Routes` contract (B14), `UPGRADE.md` | Sonraki fazların hepsi bu API yüzeyine yazılır; BC bir kez kırılır | L |
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
2. `ThemePlugin::getStyles()` kalkar (B8c, 2.0.0 serbest kırma); yerine yalnızca `getTheme(): array` (değişkenler) kalır.
3. Tema doğrulaması tek yerde (`Theme::fromArray`): ad deseni, `colorScheme ∈ {light,dark}`, renk değerleri izinli desenle; geçersiz tema loglanıp atlanır (sayfa kırılmaz).
4. FOUC koruması test altında kalır: sunucu tarafı çözümü ve `system` betiği için `ThemeFoucGuardTest` yeniden yazılır.
5. DaisyUI yerleşik tema listesi `themes: all` yerine seçilmiş kısa listeyle sınırlanır (paket boyutu ve "seçilemeyen tema" tutarsızlığı biter); hangi temaların gönderileceği Faz 1b'de netleşir.

### Faz dağılımı

- **Faz 0 (R21):** `ThemePlugin::getStyles()` kaldırılır, `Tardis::addCss()/addJs()`.
- **Faz 1b (R31):** `app.js` paketi + `Alpine.data('theme')`, runtime tema motoru (`themes.json`, `ThemePlugin::getTheme`), sunucuda çözülen `data-theme`, global + kullanıcı tercihi, tema listesi daraltma, marka ayarları.
- **Faz 6 (R27):** tema editörü (renk seçicili), özel CSS ayarı, önizleme.
- **Faz 8 (R29):** `tardis:doctor` varlık/tema denetimleri.

## Plugin CSS/JS mimarisi (2026-10-03)

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

- **Faz 0 (R21):** stub `composer.json` düzeltmesi, `Tardis::addCss()/addJs()` ve `Asset` değer nesnesinin ilk hâli (eski string sözleşmesi geriye çalışmaz — 2.0.0 serbest kırma).
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
- Kullanıcı parolası/profil ekranı (host'a ait).
- Çeviri tablosu (V1 `translations`); çeviriler JSON kolonda.
