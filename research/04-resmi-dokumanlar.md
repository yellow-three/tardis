# Resmi Dokümanlar — Tam Sayfa Dizini ve Özetler

İndirilen sayfalar:
- **Voyager 1.x:** `voyager-1x-docs/` — **37 sayfa** (tümü indirildi)
  Kaynak: `https://voyager-docs.devdojo.com/1.x/`
- **Voyager 2.x:** `voyager-2x-docs/` — **55 dosya** (repodaki `docs/` klasörü)
  Kaynak: `https://github.com/voyager-admin/voyager/docs` = `https://voyager-admin.github.io/voyager/`
  (Site ile repo birebir aynı — 52 içerik sayfası, doğrulandı)

> Her sayfa `.md` uzantısıyla doğrudan markdown olarak çekilebiliyor.
> Ayrıca `https://voyager-docs.devdojo.com/llms.txt` tam indeks dosyası var.

---

## BÖLÜM A — Voyager 1.x Dokümanları (37 sayfa)

### A.1 Getting Started (5 sayfa)

| Dosya | Özet |
|---|---|
| `getting-started-what-is-voyager.md` | Voyager'ın tanımı, BREAD kavramı, Laravel gereksinimleri |
| `getting-started-prerequisites.md` | PHP/Laravel/MySQL gereksinimleri (en kısa sayfa) |
| `getting-started-installation.md` | `composer require tcg/voyager`, `php artisan voyager:install`, admin oluşturma |
| `getting-started-upgrading.md` | Sürüm yükseltme adımları, migration uyarıları |
| `getting-started-configurations.md` | **En uzun sayfa (1066 kelime)** — `config/voyager.php` bölüm bölüm, `publishable/config` çoğaltma, **model/tablo değiştirme** |

### A.2 BREAD (12 sayfa)

| Dosya | Özet |
|---|---|
| `bread-introduction.md` | BREAD = **B**rowse/**R**ead/**E**dit/**A**dd/**D**elete. BREAD oluşturma sihirbazı, `generate_permissions`, `server_side` |
| `bread-relationships.md` | `hasOne`/`hasMany`/`belongsTo`/`belongsToMany` kurulumu, pivot tablo ayarları |
| `bread-introduction-1.md` | **Formfield genel yapısı** — BREAD builder'daki alan seçenekleri, `details` içeriği |
| `bread-introduction-1-checkbox.md` | Checkbox / **Multiple Checkbox** / **Radio** — seçenek listesi, ayrı tipler |
| `bread-introduction-1-dropdown.md` | **Dropdown (select)** — options kaynağı, ilişkiden seçenek üretme |
| `bread-introduction-1-date-time.md` | **Date & Time (timestamp)** — tek tip, tarih+saat |
| `bread-introduction-1-number.md` | Number — `min`/`max`/`step` |
| `bread-introduction-1-images.md` | **Images** — image vs multiple_images, resize, media_picker ilişkisi |
| `bread-introduction-1-media-picker.md` | **En uzun field sayfası (872 kelime)** — Media Picker kurulumu, klasör yapısı, `{uuid}` davranışı |
| `bread-introduction-1-coordinates.md` | Coordinates — Google Maps, `showAutocomplete`, `showLatLng` |
| `bread-introduction-1-tinymce.md` | TinyMCE (rich text) — kurulum, TinyMCE 5, config |

### A.3 Core Concepts (8 sayfa)

| Dosya | Özet |
|---|---|
| `core-concepts-routing.md` | Admin rotaları, `voyager.` öneki, **route override** |
| `core-concepts-media-manager.md` | Medya yöneticisi panosu, klasör ağacı, dosya işlemleri |
| `core-concepts-menus-and-menu-builder.md` | Menü sistemi, menü öğesi tipleri, **icon seçici**, sıralama |
| `core-concepts-database-manager.md` | **Veritabanı yöneticisi** — tablo oluşturma/düzenleme/silme, sütun sıralama |
| `core-concepts-settings.md` | Ayar sistemi, **gruplar**, `setting('site.title')` helper'ı |
| `core-concepts-compass.md` | Ana panel (compass), widget'lar |
| `core-concepts-roles-and-permissions.md` | **En kapsamlı sistem sayfası (721 kelime)** — Role/Permission, BREAD izinleri, **Laravel Policy uyumu**, `hasPermission` |
| `core-concepts-multilanguage.md` | Çok dillilik (646 kelime) — BREAD alan çevirileri, `multilingual` config |
| `core-concepts-helper-methods.md` | `Voyager::` facade yardımcıları, `setting()`, `trans()` |

### A.4 Customization (9 sayfa)

| Dosya | Özet |
|---|---|
| `customization-adding-custom-formfields.md` | **Üçüncü parti field ekleme** — `AbstractHandler` extends, `$codename`, `createContent()`, view, **service provider kaydı** |
| `customization-bread-accessors.md` | **BREAD action bazlı accessor** — `getNameBrowseAttribute()`, `getNameReadAttribute()`, `getNameEditAttribute()`, `getNameAddAttribute()` |
| `customization-action-buttons.md` | **Satır aksiyon butonları** — `AbstractAction` extends, `getTitle/getIcon/getPolicy/getAttributes/getDefaultRoute` |
| `customization-custom-realtionship-attributes.md` | İlişkide ek özellik gösterme — accessor + `$additional_attributes = ['full_name']` |
| `customization-overriding-files.md` | **455 kelime** — view/controller override, `vendor:publish`, `resources/views/vendor/voyager/` |
| `customization-overriding-routes.md` | Route override (kısa) |
| `customization-additional-css-js.md` | `additional_css` / `additional_js` config |
| `customization-enabling-soft-delete.md` | Soft delete desteği, BREAD'de aktif etme |
| `customization-coordinates.md` | Coordinates detayları (formfields sayfasının genişletilmiş hâli) |
| `customization-custom-guard.md` | **Özel auth guard** — `guard` config, `VoyagerUser` trait |

### A.5 Troubleshooting (2 sayfa)

| Dosya | Özet |
|---|---|
| `troubleshooting-using-https.md` | HTTPS + Voyager asset yükleme sorunları |
| `troubleshooting-missing-required-parameter.md` | Eksik parametre hatası çözümü |

---

## BÖLÜM B — Voyager 2.x Dokümanları (55 dosya)

### B.1 BREAD (9 sayfa)

| Dosya | Özet |
|---|---|
| `bread/index.md` | BREAD genel bakış |
| `bread/validation.md` | **⭐ Field bazlı validation, çevrilebilir mesaj, locale modu, dizi elemanı doğrulama** |
| `bread/relationships.md` | İlişki tanımları |
| `bread/multilanguage.md` | Çok dillilik |
| `bread/manipulate-data.md` | **Veri dönüşümü — `browse/read/edit/update/updated/store/stored` lifecycle** |
| `bread/actions.md` | BREAD aksiyonları |
| `bread/layouts.md` | **Layout sistemi, tab gruplama** |
| `bread/lists.md` | Liste/index görünümü |
| `bread/views.md` | View katmanı |

### B.2 Formfields (17 sayfa)

`index.md`, `text.md`, `password.md`, `number.md`, `slider.md`, `slug.md`,
`tags.md`, `select.md`, `radios.md`, `checkboxes.md`, `toggle.md`,
`datetime.md`, `simple-array.md`, `dynamic-input.md`, `repeater.md`,
`relationship.md`, `media-picker.md`

### B.3 Plugin (12 sayfa)

`plugin-manager.md`, `plugins/index.md`, `plugins/best-practices.md`,
`plugins/filter.md`, `plugins/components.md`, `plugins/assets.md`,
`plugins/routes.md`, `plugins/settings.md`, `plugins/widgets.md`,
`plugins/pages.md`, `plugins/menu-items.md`, `plugins/preferences.md`,
`plugins/language.md`

→ Tamamı `03-voyager-plugin-sistemi.md` içinde sentezlendi.

### B.4 Diğer

| Dosya | Özet |
|---|---|
| `index.md` | Ana giriş |
| `media-manager.md` | Medya yöneticisi |
| `settings.md` | Ayar sistemi |
| `plugin-manager.md` | Plugin yönetimi |
| `javascript.md` | **Genel JS API'si** — `voyager.component()`, event'ler |
| `overriding/formfields.md` | **Field override** mekanizması |
| `overriding/icons.md` | İkon override |
| `getting-started/*` (4) | installation, prerequisites, tips-and-tricks, what-is-voyager |
| `contributing/*` (5) | Katkı rehberi: assets, css, documentation, environment, index |
| `de/index.md` | Almanca çeviri |

---

## BÖLÜM C — Dört Kaynağın Kapsam Özeti

| Kaynak | Kapsam | Durum |
|---|---|---|
| `github.com/thedevdojo/voyager` | 1.x **kaynak kodu** — 24 handler, controller, model, policy | ✅ Tam indirildi, okundu |
| `voyager-docs.devdojo.com` | 1.x **37 doküman sayfası** | ✅ Tam indirildi |
| `github.com/voyager-admin/voyager` | 2.x **kaynak kodu** + 55 doküman dosyası | ✅ Tam indirildi, okundu |
| `voyager-admin.github.io/voyager/` | 2.x site — 52 içerik sayfası | ✅ Repo ile aynı doğrulandı, repo docs kopyalandı |

---

## BÖLÜM D — Dokümanlardan Çıkarılan En Değerli 10 Tespit

1. **Field bazlı validation + çevrilebilir mesaj** (2.x `bread/validation.md`)
   → tardis'te tüm kurallar düşüyor (P0)
2. **BREAD action bazlı accessor** (1.x `customization/bread-accessors.md`)
   → `getNameBrowseAttribute()` / `getNameReadAttribute()` — tardis'te yok
3. **Satır aksiyon butonları** (1.x `customization/action-buttons.md`)
   → `getPolicy()` ile **aksiyon bazlı izin** — tardis'te izin kontrolü yok
4. **Üçüncü parti formfield** (1.x `customization/adding-custom-formfields.md`)
   → `AbstractHandler` + `$codename` + service provider kaydı
5. **Select seçenekleri 3 kaynaktan** (1.x `bread-introduction-1-dropdown.md`)
   → statik options, `{field}List()` metodu, ilişki + where filtresi
6. **Çoklu checkbox / çoklu select ayrı tipler** (1.x)
   → tardis'te `checkbox` tek kutu, `select` tek seçim
7. **Dizi elemanı doğrulama** (2.x)
   → `.field:rule` nokta sözdizimi; `has_many` satırları doğrulanabilir
8. **Layout tab gruplama + aksiyon bazlı layout** (2.x `bread/layouts.md`)
   → create/edit/read ayrı layout; tardis'te kopyalanmış blade'ler
9. **Çok dilli doğrulama modu** (2.x)
   → "validate all locales" / "validate current locale"
10. **Filter kontratları ile menü/widget sürme** (2.x `plugins/filter.md`)
    → izne göre panel öğesi gizleme; tardis'te yok
