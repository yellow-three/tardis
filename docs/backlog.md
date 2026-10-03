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

### Açık

| ID | Öncelik | İş | Durum | Commit | Yönlendirme |
|----|---------|----|-------|--------|-------------|
| R9 | P1 | **Voyager → Tardis özellik transferi.** BREAD audit turu bu planın parçası; 6 P0/P1 düzeltmesi landı. Kalan açık bulgular B1, B2, B4, B5. | 🔄 Devam ediyor | `e0d00ac`…`8bb20a3` | `docs/notes.md` + `.omo/plans/voyager-transfer.md` |
| R10 | P2 | **Asset iyileştirmeleri — (b) tamamlandı, (a) karar bekliyor.** (b) content-hash cache busting eklendi: `AssetManager::publishedCssVersion()` yayınlanmış bundle'ın md5 önekini `?v=` olarak ekliyor; bundle yoksa URL değişmeden kalıyor, dev mode dokunulmuyor. Vite sabit dosya adı ürettiği için (`assetFileNames: 'assets/[name][extname]'`) URL deploy'lar arasında değişmiyordu. (a) `ThemePlugin::getStyles()` hâlâ var ve `AssetManager.php:148` kullanıyor. | ⏸️ Karar bekleniyor (a) | — | `docs/notes.md` → B8 |
| R11 | P2 | **BREAD tanım kaynağı: karar verildi — JSON tek kaynak** (2026-10-03). `config/bread` yalnızca tek yönlü legacy içe aktarma (`tardis:bread:migrate`); `ConfigBreadSource` 2.0.0'da kaldırılır. | 🔄 Faz 0'da uygulanacak | — | `docs/notes.md` → B7, R21 |
| R12 | P2 | **Voyager II kalan özellikler** — `.omo/plans/voyager-ii-features.md` yerine aşağıdaki **Voyager parity planı** (R21–R30) geçerli. | ➡️ R21–R30'a taşındı | — | Voyager parity planı |
| R13 | P2 | **Laravel Vite plugin entegrasyonu.** Plan hâlâ `status: draft`. | ⏳ Taslak | — | `.omo/plans/laravel-vite-plugin.md` |
| R14 | P2 | **Graphify MCP'yi OpenCode'a bağla.** Doğrulandı: `~/.config/opencode/opencode.json` içinde `graphify` **yok**. Yalnızca araç/observability, ürün yüzeyi yok. | ⏳ Açık | — | `.omo/plans/graphify-mcp-setup.md` |
| R15 | P1 | **BREAD açık bulguları (4 madde kaldı).** (Locked property, search yetkisi, slug doğrulama ve validation `|` düzeltmeleri 2026-10-03'te kapandı.) `registerType()` erişilemezliği, ölü `field()` stub, `searchOptions()` filtresiz, fail-open authorization. Kullanılmayan Spatie bağımlılığı kaldırıldı (`bf24401` sonrası) — paket hiçbir yerde Spatie'ye derleme zamanı bağlı değildi, yalnızca `method_exists()` ile duck-typ ediyordu. | ⏳ Açık | — | `docs/notes.md` → B1, B2, B4, B5 |
| R16 | P2 | **`Alpine.store('theme')` → `Alpine.data('theme')` + `$persist` geçişi.** R8 ile birlikte planlanmıştı ama FOUC'yu etkilemiyor ve planın gövdesi `availableThemes` / `lightThemes` / `darkThemes` getter'larını düşürüyor — `pages/settings/settings.blade.php` bunları `$store.theme.availableThemes` üzerinden okuyor, gövde aynen uygulansaydı settings sayfası kırılırdı. Ayrıca plan store'un `resources/js/app.js` içinde olduğunu varsayıyor; oysa layout'a inline gömülü. Gerçek gerekçe: listener cleanup (`wire:navigate` sırasında birikme). | ⏸️ Ertelenmiş | — | `.omo/plans/theme-fix-wire-navigate.md` (Todo 2-7) |
| R18 | P2 | **Auth/policy/config tutarsızlıkları.** Login `AuthenticationPlugin`'i atlıyor (B12), `BasePolicy` izin adı slug'dan türemiyor (B11), kullanılmayan config anahtarları (B13; `ConfigTest` varlıklarını pinliyor), plugin route'ları wildcard'a yenilebilir (B14). | ⏳ Açık | — | `docs/notes.md` → B11–B15 |
| R19 | P2 | **`ThemeManager` boot-time I/O.** `TardisServiceProvider::register()` içinde dosya/ağ okuması yapılıyor, hatalar yalnızca loglanıyor. | ⏸️ Ertelenmiş | — | — |
| R21 | P0 | **Faz 0 — 2.0.0 temizliği.** `FieldType` enum'u → registry (B1), `ConfigBreadSource` + `config/bread` kaldırma (B7/R11), `ThemePlugin::getStyles()` kaldırma (B8c), `FormfieldManager::field()` stub'ı, ölü config anahtarları (B13), `BasePolicy` ability adı (B11), login'in `AuthenticationPlugin`'den geçmesi (B12), `Provider\Routes` contract'ının bağlanması (B14), sürüm `2.0.0` + `UPGRADE.md` | ⏳ Plan | — | Voyager parity planı → Faz 0 |
| R22 | P1 | **Faz 1 — Panel i18n.** Tüm sabit metinler `__('tardis::…')`, `lang/en` (varsayılan) + `lang/tr`, locale seçici, TR/EN karışık aria etiketlerinin temizliği | ⏳ Plan | — | Faz 1 |
| R23 | P1 | **Faz 2 — Alan sistemi.** `FormfieldPlugin` + registry, V2 lifecycle (`browse/read/edit/add/store/update($old)/stored/updated`), add/edit ayrı kural seti, çevrilebilir doğrulama mesajı, dizi elemanı doğrulama, eksik tipler (Color, Coordinates, Hidden, RichText, çoklu checkbox/select, Repeater, SimpleArray, MediaPicker) | ⏳ Plan | — | Faz 2 |
| R24 | P1 | **Faz 3 — BREAD liste.** `Action` sınıflarının UI'ya bağlanması (satır + toplu), sunucu taraflı sırala/filtre/ara/sayfa boyutu, ilişki kolonları (eager load), soft-delete geri yükleme/kalıcı silme, aksiyon bazlı layout, browse accessor'ları | ⏳ Plan | — | Faz 3 |
| R25 | P1 | **Faz 4 — Plugin genişletilebilirliği.** `Routes` contract'ı + yetkili/ön yüz rota ayrımı, plugin ayar ekranı + preferences, `Filter\Widgets/Layouts/Media`, `tardis:plugins` komutu, plugin sürüm/bağımlılık gösterimi | ⏳ Plan | — | Faz 4 |
| R26 | P2 | **Faz 5 — Media entegrasyonu.** BREAD `media_picker` alanı, thumbnail + kırpma, `{uid}/{date:…}/{random:n}` dosya adı şablonu, tek yükleme doğrulaması (BREAD `FileField` dahil), media filter plugin'i | ⏳ Plan | — | Faz 5 |
| R27 | P2 | **Faz 6 — Menü builder + Dashboard widget'ları + görünüm.** `storage/tardis/menus.json` bindirmesi, nestable sürükle-bırak, gizle/yeniden adlandır/özel link, varsayılan dashboard kartları + `storage/tardis/dashboard.json` düzeni + widget izinleri, rol sayfasında gruplu izin ağacı, varsayılan tema + özel CSS/JS ayarı (`manage appearance`) | ⏳ Plan | — | Faz 6 |
| R28 | P2 | **Faz 7 — Çok dilli içerik.** Form içinde locale sekmeleri, 'tüm diller / aktif dil' doğrulama modu, listede aktif dil, BREAD etiketleri + menü başlıkları çevrilebilir (JSON kolon biçimi korunur) | ⏳ Plan | — | Faz 7 |
| R29 | P2 | **Faz 8 — Kurulum ve DX.** `tardis:install` (migrate + seed + ilk admin + asset publish), isteğe bağlı demo veri, `tardis:doctor`, güncellenmiş plugin iskeleti, `UPGRADE.md`, kapsamlı örnek | ⏳ Plan | — | Faz 8 |
| R30 | P1 | **Kalite kapısı (her faz).** Önce test, tam sayfa istek testi, `boot()` yetki kapısı, `lang` anahtarı, `docs/` güncellemesi, faz başına yığılı PR | 🔄 Sürekli | — | Voyager parity planı → Kurallar |
| R31 | P1 | **Faz 1b — Tasarım sistemi (ortak UI bileşenleri).** `x-tardis::card` (actions slotu), badge, slide-in çekmece, modal, dropdown, toast/bildirim (onay düğmeli), sayfa yükleme çubuğu, sidebar kullanıcı kartı (baş harf avatarı + `avatar_column`) + kalıcı durum, 3 durumlu tema anahtarı, marka ayarları (`appearance` grubu: başlık/logo/favicon/yükleme görseli), RTL değerlendirmesi | ⏳ Plan | — | `research/06` §3.9, §5 |
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

Voyager 1.7 ve 2.x kaynakları sayfa/bileşen düzeyinde incelendi: `research/06-voyager-gorunum-ve-blade-notlari.md`. Plana giren ilkeler: sunucu render + Alpine/Livewire kalır (SPA yok); tek formfield sözleşmesi çok bağlamda (browse/read/edit/add/query) çalışır, V1'in tip zinciri geri gelmez; BREAD tanımı `list`/`view` layout'ları taşır; ortak bileşen kütüphanesi (Card+actions, Badge, SlideIn, Modal, Toast); marka ayarları `appearance` grubunda; V1 Compass'in komut çalıştırıcısı **alınmaz**.

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

- Vue/Inertia arayüzü (V2'nin ön yüzü); Tardis Livewire kalır.
- Doctrine DBAL (V1); şema keşfi Laravel şema builder ile.
- Kullanıcı parolası/profil ekranı (host'a ait).
- Çeviri tablosu (V1 `translations`); çeviriler JSON kolonda.
