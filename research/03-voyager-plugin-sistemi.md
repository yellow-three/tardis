# Voyager Plugin Sistemi — Her İki Sürüm (Tam Araştırma)

Kaynaklar:
- `https://voyager-admin.github.io/voyager/plugins/` (resmi site)
- `https://github.com/voyager-admin/voyager/docs/plugins/` (yerel docs, 12 dosya)
- `https://github.com/thedevdojo/voyager` (1.x çekirdek)
- `https://github.com/voyager-admin/voyager` (2.x çekirdek)

Tarih: 2026-09-29

---

## BÖLÜM 1 — Voyager 1.x Plugin Sistemi

### 1.1 Çekirdekte plugin dosyası YOK

`find -iname "*plugin*"` → boş. Voyager 1.x çekirdeğinde plugin kodu bulunmuyor.
Plugin mimarisi **dolaylı** olarak şu mekanizmalarla kuruluyor:

| Mekanizma | Konum | Rolü |
|---|---|---|
| `composer.json` → `extra.laravel.providers` | paket metadata | **Otomatik keşif** (Laravel package discovery) |
| `VoyagerEventServiceProvider` | `src/Providers/` | Event dinleyicileri |
| `VoyagerDummyServiceProvider` | `src/Providers/` | Publishable asset'ler |
| `FormFieldsRegistered` event | `src/Events/` | **Üçüncü parti formfield kaydı** |
| `composer` konsol komutu | — | `voyager:composer require` |

### 1.2 Üçüncü parti formfield ekleme (1.x)

`FormFieldsRegistered` event'i, çekirdeğin formfield listesini genişletmek için
tetiklenir. Resmi örnek: `voyager-json-editor` plugin'i bir `TextareaHandler`
kopyası üretip event ile kaydeder.

**1.x plugin kurulum akışı:**
```
composer require tcg/voyager-<isim>-plugin
php artisan vendor:publish   (gerekirse)
→ FormFieldsRegistered event'i ile field tipi listeye girer
```

### 1.3 1.x Resmi plugin'ler

| Plugin | Paket | İşlev |
|---|---|---|
| Hooks | `tcg/voyager-hooks` | **Genel olay kancaları** — herhangi bir noktaya kod ekleme |
| Mail | `tcg/voyager-mail` | Toplu e-posta gönderimi (queue, attachment, view) |
| Notification | `tcg/voyager-notification` | Site içi bildirim sistemi + Vue bileşeni |
| File Manager | `tcg/voyager-file-manager` | Medya yöneticisini **ayrı panel** olarak sunma |
| JSON Editor | `tcg/voyager-json-editor` | JSON editörü formfield'i |
| Dummy | `tcg/voyager-dummy` | Geliştirme şablonu |

**Önemli:** `tcg/voyager-hooks` — 1.x'te **en genel** eklenti noktası.
Laravel'in olay sistemi üzerinden BREAD/media/menu/table herhangi bir noktaya
müdahale edebilir. Bu, 1.x'in "plugin sistemi" dediğimiz şeyin fiili olarak
`FormFieldsRegistered` + `Hooks` + Laravel event'leri üçlüsü olduğu anlamına gelir.

### 1.4 1.x'te olmayanlar

- Plugin yönetim paneli (**yok**)
- Plugin açık/kapalı anahtarı (**yok**)
- Plugin ayarları arayüzü (**yok**)
- Plugin sürüm takibi (**yok**)
- Resmi "Search Plugins" kataloğu (**yok**)

→ 1.x'te plugin = **Composer paketi + Laravel event/hook**. Yönetim katmanı
doğrudan Laravel'e bırakılmıştır.

---

## BÖLÜM 2 — Voyager 2.x Plugin Sistemi (yapılandırılmış)

2.x, plugin'i **first-class** bir kavram haline getiriyor: yönetim paneli,
açık/kapalı durumu, ayarlar, sürüm takibi ve **16 adet kontrat**.

### 2.1 Plugin tipleri (her biri özel yetkiler)

| Tip | Kontrat | Yetki |
|---|---|---|
| **Authentication** | `AuthenticationPlugin` | Farklı giriş yöntemleri (OAuth, sosyal medya, 2FA) |
| **Authorization** | `AuthorizationPlugin` | Yetkilendirme mantığı; üçüncü parti paket entegrasyonu (örn. `spatie/permissions`) |
| **Formfield** | `FormfieldPlugin` | **BREAD builder'a yeni formfield** (WYSIWYG vb.) |
| **Generic** | `GenericPlugin` | Diğer hiçbirine uymayan |
| **Theme** | `ThemePlugin` | Görünüm/artan tema değiştirme, `Preview` ile önizleme |

### 2.2 Plugin kaydı — Service Provider

```php
<?php
namespace My\Plugin;

use Illuminate\Support\ServiceProvider;
use Voyager\Admin\Manager\Plugins as PluginManager;

class MyPluginServiceProvider extends ServiceProvider
{
    public function boot(PluginManager $pluginmanager)
    {
        $pluginmanager->addPlugin(\My\Plugin\MyPlugin::class);
    }
}
```

> "One package can provide multiple plugins... All plugins can be enabled/disabled
> **independently**. Make sure they don't depend on each other!"

**Tasarım kararı:** Bir paket çok plugin sağlayabilir ve her plugin
**bağımsız** açılıp kapatılabilir. Bağımlılık kurulmamalı.

### 2.3 Yönetim paneli

`PluginsController` + `PluginsCommand` (CLI) ile tam yönetim:

- **Search Plugins** — katalogda ara, kurulum komutunu kopyala
- **Enable / Disable** — `storage/voyager/plugins.json` dosyasında saklanır
- **Settings** — her plugin ayar paneli açar (Vue bileşeni)
- **Theme Preview** — tema geçici olarak önizlenir, sayfa yenilenince kaybolur

**Plugin kimliği ve sürümü:**
```php
$plugin->identifier = $plugin->repository.'@'.class_basename($plugin);
$plugin->enabled    = array_key_exists($plugin->identifier, $this->enabled_plugins);
$plugin->version    = InstalledVersions::getPrettyVersion($plugin->repository) ?? '';
$plugin->stats      = [];
```

→ Sürüm bilgisi **Composer `InstalledVersions`'tan** okunur; plugin'ın kendi
sürüm dosyası yoktur. Tek doğruluk kaynağı Composer lock dosyasıdır.

### 2.4 Provider kontratları (plugin'in **eklediği** şeyler)

| Kontrat | Metot | Sağladığı |
|---|---|---|
| `Provider/MenuItems` | `provideMenuItems()` | Menü öğeleri |
| `Provider/Widgets` | `provideWidgets(): Collection` | Panel widget'ları |
| `Provider/Settings` | `provideSettings(): array` | Ayar alanları |
| `Provider/SettingsComponent` | `getSettingsComponent(): string` | Ayar modal'ında gösterilecek Vue bileşeni |
| `Provider/FrontendRoutes` | `provideFrontendRoutes()` | **Giriş gerektirmeyen** rotalar |
| `Provider/ProtectedRoutes` | `provideProtectedRoutes()` | **Voyager girişi gerektiren** rotalar |
| `Provider/JS` | `provideJS(): string` | JavaScript kodu |
| `Provider/CSS` | `provideCSS(): string` | CSS kodu |

#### Widget API (fluent)
```php
(new Widget('component-name', 'title'))
    ->icon('academic-cap')       // başlık yanında ikon
    ->width(6)                   // 3-12 arası genişlik
    ->parameters(['key'=>'value'])  // bileşene geçirilecek parametreler
    ->permission('perm_key')     // **izne göre göster/gizle**
```

→ `->permission()` widget seviyesinde yetkilendirme. Bu, tardis'te eksik olan
"paneldeki her öğeyi izne göre gizleme" yeteneğinin karşılığı.

#### Plugin ayarları — tam şema
```php
public function provideSettings(): array
{
    return [[
        'type'         => 'text',
        'group'        => 'My group',
        'name'         => 'My setting',
        'key'          => 'my_setting',
        'value'        => 'Value',
        'translatable' => false,
        'info'         => 'This is a setting provided by a plugin',
        'options'      => [],
        'validation'   => [],      // ← **doğrulama kuralları ayar seviyesinde**
    ]];
}
```

→ Ayar girdisi **BREAD field şemasıyla aynı** (`type`, `key`, `options`,
`validation`, `translatable`). Yani ayarlar da field gibi değerlendiriliyor ve
**validation** taşıyabiliyor. Ayar değerleri `settings.json`'a **kullanıcı
"Save" dediğinde** yazılır, okurken **her zaman default dönmeli**.

#### Asset'ler — "publish etme" derdi yok
```php
class VoyagerDocs implements GenericPlugin, CSS, JS
{
    public function provideCSS(): string { return file_get_contents('.../asset.css'); }
    public function provideJS(): string { return file_get_contents('.../asset.js'); }
}
```
> "Directly providing your assets allows you to simply develop your plugin and
> releasing it - **without the need to re-publish files with any change**.
> Don't worry - Voyager takes care of **caching** your assets."

**Tasarım kararı:** Asset'ler dosya olarak publish edilmez, **string döner**,
Voyager önbelleğe alır. Geliştirme-üretim ayrımı yok.
`FormfieldPlugin` **otomatik olarak JS kontratını implement eder.**

#### Rotalar — iki ayrı güvenlik seviyesi
```php
class MyPlugin implements ProtectedRoutes
{
    public function provideProtectedRoutes(): void
    {
        Route::get('/my-page', fn() =>
            Inertia::render('component-to-render', ['foo'=>'bar'])
                ->withViewData('title', 'My page')
        )->name('my-page');
    }
}
```
- `provideProtectedRoutes()` → **yalnız Voyager'a giriş yapmışlar**
- `provideFrontendRoutes()` → herkese açık
- Inertia kullanılır (master view içinde Vue render), ama Blade de dönebilir

### 2.5 Filter kontratları (plugin'in **süzdüğü** şeyler)

| Kontrat | Metot imzası | Süzülen |
|---|---|---|
| `Filter/Layouts` | `filterLayouts(Bread $bread, string $action, Collection $layouts): Collection` | BREAD layout'ları (`$action` = browse/read/edit/add) |
| `Filter/MenuItems` | `filterMenuItems(Collection $items, bool $mainMenu = true): Collection` | Menü öğeleri |
| `Filter/Widgets` | `filterWidgets(Collection $widgets): Collection` | Panel widget'ları |
| `Filter/Media` | (medya süzme) | Medya listesi |

```php
class MyPlugin implements GenericPlugin, MenuItemFilter
{
    public function filterMenuItems(Collection $items, $mainMenu = true): Collection
    {
        // $mainMenu true → ana menü, false → kullanıcı menüsü
        return $items->filter(fn($item) => /* koşul */ true);
    }
}
```

#### ⭐ EN ÖNEMLİ TASARIM KARARI: Provider / Filter ayrımı

Bu ayrım Voyager 2.x'in plugin mimarisinin çekirdeğidir:

| | Provider | Filter |
|---|---|---|
| Yön | Sistem geneline **ekler** | Mevcut şeyleri **süzer** |
| Etki alanı | Yalnız kendi katkısı | **Tüm sistemi** etkiler |
| Entegrasyon riski | Düşük | Yüksek (birbirini bozabilir) |
| Örnek | kendi menü öğeni | **tüm** menü öğelerini gizleme |

→ Plugin'lerin birbirini bozmasını engelleyen kasıtlı mimari kısıt.
`MenuItemFilter` alan **ana menü ve kullanıcı menüsünü ayırt eder**;
`LayoutFilter` ise **eyleme göre** (`browse`/`read`/`edit`/`add`) farklı
layout'ları süzebilir.

### 2.6 Bileşen (Vue) kaydı

```javascript
import Component from './Component.vue';
voyager.component('my-component', Component);
```
`SettingsComponent` kontratı ile ayarlar modal'ında gösterilecek bileşenin
**adı** (`getSettingsComponent(): string`) döner.

### 2.7 Best practices

> **"Don't load things in the constructor"**
> "Your plugin class is loaded when calling `addPlugin(...)` **even when it's
> disabled**. To prevent long loading times and unnecessary memory usage, load
> data only when needed (in route definitions, for example)."

→ **Kritik uyarı:** Plugin sınıfı **kapalıyken bile** yükleniyor. Bu yüzden
constructor'da I/O, veritabanı sorgusu veya dosya okuması yapılmamalı; veri
yalnızca gerçekten ihtiyaç duyulduğunda (ör. route tanımında) alınmalı.

---

## BÖLÜM 3 — Taraf Karşılaştırması

| Özellik | 1.x | 2.x |
|---|---|---|
| Plugin yönetim paneli | ❌ | ✅ (Search/Enable/Disable/Settings) |
| Açık/kapalı anahtarı | ❌ | ✅ (`storage/voyager/plugins.json`) |
| Plugin sürümü | ❌ | ✅ (Composer `InstalledVersions`) |
| Plugin ayar arayüzü | ❌ | ✅ (Vue bileşeni) |
| Plugin tipleri | ❌ (tek tip) | ✅ 5 tip (Auth/Authorization/Formfield/Generic/Theme) |
| Formfield ekleme | `FormFieldsRegistered` event | `FormfieldPlugin` kontratı (+ otomatik JS) |
| Route ekleme | Laravel paketi olarak | `ProtectedRoutes` / `FrontendRoutes` |
| Asset sağlama | `vendor:publish` | `provideJS()`/`provideCSS()` string + önbellek |
| Ayar sağlama | ❌ | ✅ `provideSettings()` (validation'lı) |
| Widget sağlama | Arrilot widget paketi | `provideWidgets()` fluent API + `->permission()` |
| **Menü/Widget/Layout/Media sürme** | ❌ | ✅ 4 Filter kontratı |
| Tema desteği | ❌ | ✅ `ThemePlugin` + Preview |
| Kapalıyken yüklenme | — | ⚠️ Evet → constructor'da yük yapma |
| Hook sistemi | `tcg/voyager-hooks` paketi | Kontrat tabanlı Provider/Filter |

---

## BÖLÜM 4 — tardis'e Çıkarımlar

| Konu | Öneri | Öncelik |
|---|---|---|
| **Plugin yok** | Provider/Filter ayrımlı kontrat sistemi kur | P3 (uzun vade) |
| **Formfield kaydı** | `FormfieldManager::addFormfield()` registry + `getFormfield($type)` (Voyager 2 mantığı) | **P1** |
| **Ölü render mimarisi** | Handler ince olsun, mantık field sınıfında (Voyager 2) ya da view'da (Voyager 1) — **biri** | **P1** |
| **Widget/API yok** | Panel öğelerine `->permission()` benzeri izin bağlama | P2 |
| **Ayar sistemi yok** | `settings.json` + group + validation'lı ayar şeması | P2 |
| **Katalog yok** | "Search Plugins" benzeri katalog + CLI kurulum | P3 |
| **Performans uyarısı** | Plugin/field sınıfları tembel yüklenmeli (constructor'da I/O yasak) | P2 |

---

## BÖLÜM 5 — Kaynak Dosyalar

```
research/voyager-2x-docs/plugin-manager.md          plugin türleri + yönetim
research/voyager-2x-docs/plugins/index.md           geliştirme ortamı + kayıt
research/voyager-2x-docs/plugins/best-practices.md  constructor uyarısı
research/voyager-2x-docs/plugins/filter.md           4 Filter kontratı
research/voyager-2x-docs/plugins/components.md       Vue bileşen kaydı
research/voyager-2x-docs/plugins/assets.md           provideJS / provideCSS
research/voyager-2x-docs/plugins/routes.md           Protected / Frontend routes
research/voyager-2x-docs/plugins/settings.md         provideSettings şeması
research/voyager-2x-docs/plugins/widgets.md          Widget fluent API
research/voyager-2x-docs/plugins/pages.md            özel sayfalar
research/voyager-2x-docs/plugins/menu-items.md       menü öğesi ekleme
research/voyager-2x-docs/plugins/preferences.md      plugin tercihleri
research/voyager-2x-docs/plugins/language.md         plugin i18n

/tmp/opencode/voyager2/src/Contracts/Plugins/        17 kontrat dosyası
/tmp/opencode/voyager2/src/Manager/Plugins.php        plugin registry + kimlik
/tmp/opencode/voyager2/src/Plugins/AuthenticationPlugin.php
```
