# Voyager 2.x (voyager-admin/voyager) — Tam Sistem Araştırması

Kaynak: `https://github.com/voyager-admin/voyager` (branch `2.x`)
İnceleme: repodan indirildi (`/tmp/opencode/voyager2`), kaynak kod + yerel `docs/` okunarak çıkarıldı.
Tarih: 2026-09-29

> Kapsam notu: BREAD dahil tüm sistemler — Formfields, Manager, Plugins, Policies,
> Rules, Media, Settings, Menu, Layouts, Widgets, Commands, Events.

---

## 1. Voyager 1.x'ten en temel mimari fark

| Konu | Voyager 1.x | Voyager 2.x |
|---|---|---|
| Field tanımı | `Handler` sınıfı + Blade view | **Tek PHP sınıfı** (`Formfields\Text` vb.), view ayrı |
| Field lifecycle | `createContent()` tek nokta | **8 aşamalı** `browse/read/edit/update/updated/add/store/stored` |
| BREAD depolama | MySQL (`data_types` + `data_rows` tabloları) | **JSON dosyaları** + `storage/voyager/breads/` |
| Şema kaynağı | Doctrine DBAL ile canlı | **Model Reflection** (`getModelRelationships`, `getModelScopes`, `getModelComputedProperties`) |
| Field kaydı | `FormFieldsRegistered` event | `addFormfield(class)` + `getFormfields()` registry |
| Frontend | Server-rendered Blade | **Vue + Tailwind** (webpack, `tailwind.config.js`, `jsconfig.json`) |
| Validity | PHP 7.3+, Laravel 6/7/8 | PHP 8+ (`string\|array\|null` union, `mixed`, `: void`) |

**Not:** Voyager 2.x, tardis'in **JSON tabanlı BREAD** yaklaşımına çok daha
yakın. tardis'in mimari seçimi (veritabanı tablosu yerine JSON) Voyager 2 ile
örtüşüyor; fark, lifecycle derinliğinde.

---

## 2. `Formfield` taban sınıfı — 8 aşamalı lifecycle

`src/Classes/Formfield.php` — `JsonSerializable` implementasyonu.

```php
class Formfield implements \JsonSerializable
{
    protected array $dontStore = [
        'notTranslatable', 'notAsSetting', 'notInLists', 'notInViews',
        'browseArray', 'noColumns', 'noComputedProps', 'noRelationships',
        'noRelationshipProps', 'noRelationshipPivots', 'uuid',
    ];

    public mixed $options;
    public Column $column;
    public ?int $tab = null;              // sekme (tab) gruplama
    public ?string $link_to = null;
    public bool $translatable = false;
    protected ?Bread $bread = null;
}
```

### 2.1 Lifecycle metotları

| Metot | Ne zaman | Girdi | Not |
|---|---|---|---|
| `browse($value)` | index/liste | değer | **HTML temizleme burada** |
| `read($value)` | detay sayfası | değer | |
| `edit($value)` | edit formu | değer | |
| `update($model, $value, $old)` | update **öncesi** | değer + **eski değer** | `$old` kritik |
| `updated($model, $value)` | update **sonrası** | model | void |
| `add()` | create formu | — | varsayılan değer |
| `store($value)` | store **öncesi** | değer | |
| `stored($model, $value)` | store **sonrası** | model | void |

**tardis karşılaştırması:** tardis'te yalnızca `transform()` ve `stored()` var.
- `browse`/`read`/`edit` ayrımı **yok** → index'te HTML temizleme yapılmıyor
- `update` **öncesi/sonrası ayrımı yok** → eski değere erişilemiyor
- `add()` default mantığı `Formfield` içinde değil, inline Blade'de

### 2.2 Örnek: `Text` — browse temizleme dahili

```php
class Text extends Formfield
{
    public function type(): string { return 'text'; }

    public function browse(mixed $input): mixed {
        return Str::limit(strip_tags($input), $this->options->display_length ?? 150);
    }

    public function add(): mixed {
        return $this->options->default_value ?? '';
    }
}
```

→ `strip_tags` + uzunluk kırpma **alan sınıfının kendisinde**. tardis'te bu
koruma `index.php`'de global olarak yapılıyor ve alan bazlı `display_length`
seçeneği yok.

### 2.3 `dontStore` bayrakları (işlev anlamları)

| Bayrak | Anlamı |
|---|---|
| `notTranslatable` | Bu alan çevirilemez |
| `notAsSetting` | Ayar olarak kullanılamaz |
| `notInLists` | Listede görünmez |
| `notInViews` | View'larda görünmez |
| `browseArray` | Browse çıktısı dizi olmalı |
| `noColumns` | Sütun eşlemesi yok |
| `noComputedProps` | Computed property'ler yok sayılır |
| `noRelationships` | İlişki yok sayılır |
| `noRelationshipProps` | İlişki özellikleri yok sayılır |
| `noRelationshipPivots` | Pivot yok sayılır |
| `uuid` | Geçici kimlik (iç kullanım) |

→ Bu bayraklar tardis'te **hiç yok**. Özellikle `notTranslatable`,
Voyager'ın `translatable: true` alanlarda ihtiyaç duyduğu korumanın ta kendisi
(tardis'in P0 veri kaybı hatasının doğrudan çözümü).

---

## 3. Field tipleri (16 sınıf)

```
Checkbox   DateTime   DynamicInput   MediaPicker   Number   Password
Radio      Relationship  Repeater    Select        SimpleArray
Slider     Slug       Tags          Text          Toggle
```

`src/Formfields/Types/` klasörü **yok** — tipler düz sınıflar.

### 3.1 Lifecycle override matrisi (hangi tip hangi aşamayı özelleştiriyor)

| Tip | Özelleştirilen aşamalar | Yorum |
|---|---|---|
| `Checkbox` | add, browse, read, edit, store, update | Tam yaşam döngüsü |
| `DateTime` | add, browse, read, edit, store, update | Tam |
| `Repeater` | add, browse, read, edit, store, update | Tam (çoklu satır) |
| `Select` | add, browse, edit, store, update | okuma yok |
| `SimpleArray` | add, browse, edit, store, update | |
| `Tags` | add, browse, edit, store, update | |
| `MediaPicker` | add, browse, read, edit, store, update | Tam |
| `DynamicInput` | add, browse, edit, store, update | Bağımlı alan (dynamic) |
| `Relationship` | add, **stored**, edit, **updated** | **İlişki kaydı store/update SONRASI** |
| `Password` | edit, update, store | **add yok** — yalnız düzenlemede |
| `Number` | add | |
| `Radio` | add | |
| `Text` | browse, add | |
| `Slider` | — | Saf view, mantık yok |
| `Slug` | — | Saf view |
| `Toggle` | — | Saf view |

**Kritik gözlem:** `Relationship` tipi `stored()` ve `updated()` ile ilişkiyi
**kayıt tamamlandıktan sonra** yazar. Bu, tardis'in ilişki kaydını ana kayıttan
sonra yaptığı davranışla aynı — ama Voyager bunu **field sınıfının sorumluluğu**
yapmış, merkezî controller koduna gömmek yerine. `Password`'ün `add` override'ı
olmayışı da "yeni kayıtta şifre boş bırakılabilir" semantiğini temiz kodla
ifade ediyor.

### 3.2 Voyager 1.x'e göre eksik tip karşılaştırması

| Tip | 1.x | 2.x | tardis |
|---|---|---|---|
| `Color` | ✅ | ❌ | ❌ |
| `Coordinates` | ✅ | ❌ | ❌ |
| `Hidden` | ✅ | ❌ | ❌ |
| `RichTextBox` | ✅ | ❌ | ❌ |
| `MultipleCheckbox` | ✅ | `Checkbox` (çoklu) | ❌ |
| `SelectMultiple` | ✅ | `Select` (çoklu) | ❌ |
| `Timestamp` | ✅ | `DateTime` | ✅ `datetime` |
| `Image`/`MultipleImages` | ✅ | `MediaPicker` | `file` |
| — | — | **`Repeater`** | `has_many` |
| — | — | **`DynamicInput`** | ❌ |
| — | — | **`SimpleArray`** | ❌ |
| — | — | **`Relationship`** | `belongs_to` |
| — | — | **`Toggle`** | `checkbox` |

---

## 4. BREAD Manager — JSON tabanlı depolama

`src/Manager/Breads.php` — 30+ metot. Kritik olanlar:

| Metot | İşlev |
|---|---|
| `getBreads()` | Tüm BREAD'ları yükle |
| `storeBread($bread)` | **JSON'a kaydet** |
| `getBackups()` / `rollbackBread()` / `backupBread()` | **Yedekleme + geri alma** |
| `clearBreads()` | Önbellek temizle |
| `createBread($table)` | Tablodan BREAD türet (Reflection ile) |
| `addFormfield($class)` | **Field sınıfı kaydet** |
| `getFormfields()` / `getFormfield($type)` | Field registry |
| `getModelReflectionClass($model)` | Modeli yansıt |
| `getModelScopes($reflection)` | Model scope'ları (query scope'ları!) |
| `getModelComputedProperties($reflection)` | **Accessor'ları otomatik keşfet** |
| `getModelRelationships($reflection, $model)` | **İlişkileri otomatik keşfet** |
| `addAction()` / `manipulateActions()` | BREAD aksiyonlarını programatik manipüle |
| `getLayoutForAction()` / `getLayoutsForAction()` | Layout yönetimi |

**tardis için çıkarımlar:**
- **Reflection tabanlı otomatik keşif**: `getModelRelationships`,
  `getModelComputedProperties`, `getModelScopes` → BREAD oluştururken ilişileri
  ve accessor'ları otomatik listeler. tardis bunu manuel JSON yazımına bırakıyor.
- **Yedekleme/geri alma** (`backupBread`, `getBackups`, `rollbackBread`) → BREAD
  JSON'u yanlış kaydedildiğinde kurtarma. tardis'te **yok**.
- **Action manipülasyonu** (`manipulateActions(callable)`) → plugin/hook'ların
  aksiyon eklemesine izin verir.

---

## 5. Validation — Voyager 2.x (tardis P0 hatasının tam çözümü)

Kaynak: `docs/bread/validation.md` + `docs/formfields/`

### 5.1 Field bazlı kural + **çevrilebilir mesaj**
> "open the options for a formfield and look for the `Validation` section.
> Click the `+` button to add a rule and fill in the `Rule` and the `Message`
> which will be displayed when this rule fails. **The message field is
> translatable** to display translated error messages to users."

→ Kural **ve mesaj** çifti saklanır; mesaj çeviri tablosuna girer.

### 5.2 Çok dilli doğrulama seçeneği
Layout seviyesinde iki mod:
- **Validate all locales** — tüm diller zorunlu
- **Validate current locale** — sadece aktif dil; diğerleri **ignored**

`Voyager::setLocales(['de', 'en'])` ile kullanıcıya göre dinamik dil.

### 5.3 Dizi elemanı doğrulama

```php
// 1 boyutlu dizi
['name' => 'admin', 'email' => 'foo@bar.baz']
// → .name:required , .email:email   (BAŞTA nokta = 1-D)

// çok boyutlu dizi (kullanıcı listesi)
[['name'=>...],['name'=>...]]
// → docs'ta çok boyutlu sözdizimi tanımlı
```

**tardis için çıkarım:** `has_many` ilişki satırlarının her biri doğrulanabilir
olmalı — şu an tardis'te `has_many` iç satırları **hiç doğrulanmıyor**.

---

## 6. Plugin sistemi (Voyager 2.x — tam kontrat tabanlı)

`src/Manager/Plugins.php` + `src/Contracts/Plugins/`

### 6.1 Plugin kimliği ve yönetimi
```php
$plugin->identifier  = $plugin->repository.'@'.class_basename($plugin);  // ör: "vendor/Plugin"
$plugin->version     = InstalledVersions::getPrettyVersion($plugin->repository);  // Composer'dan
$plugin->enabled     = array_key_exists($plugin->identifier, $this->enabled_plugins);
$plugin->preferences = /* anonim sınıf, ayar yönetimi */
$plugin->stats       = [];
```
- Ayar dosyası: `storage/voyager/plugins.json`
- **Composer `InstalledVersions`** ile sürüm okunur — plugin sürümü merkezî değil
- Plugin **açık/kapalı** durumu dosyada tutulur, kod yüklenmeden yönetilir

### 6.2 Plugin kontratları

**Temel:**
| Kontrat | Rolü |
|---|---|
| `GenericPlugin` | **Tüm plugin'lerin extends ettiği taban** |
| `AuthenticationPlugin` | Kimlik doğrulama davranışı |
| `AuthorizationPlugin` | Yetkilendirme davranışı |
| `FormfieldPlugin` | **Özel formfield ekleme** |
| `ThemePlugin` | Tema |

**Provider (plugin'in sunduğu özellikler):**
| Kontrat | Sağladığı şey |
|---|---|
| `Features/Provider/MenuItems` | Menü öğeleri |
| `Features/Provider/Widgets` | Panel widget'ları |
| `Features/Provider/Settings` | Ayar alanları |
| `Features/Provider/SettingsComponent` | Ayar bileşeni (Vue) |
| `Features/Provider/CSS` | CSS dosyaları |
| `Features/Provider/JS` | JS dosyaları |
| `Features/Provider/FrontendRoutes` | Ön yüz rotaları |
| `Features/Provider/ProtectedRoutes` | **Yetki gerektiren rotalar** |

**Filter (plugin'in diğerlerini etkilemesi):**
| Kontrat | Etkilediği |
|---|---|
| `Features/Filter/MenuItems` | Tüm menü öğelerini süzme |
| `Features/Filter/Widgets` | Tüm widget'ları süzme |
| `Features/Filter/Layouts` | Tüm layout'ları süzme |
| `Features/Filter/Media` | Medya listesini süzme |

→ **Filter/Provider ayrımı** çok önemli bir tasarım kararı: bir plugin ya
sistem geneline bir şey **ekler** (Provider) ya da mevcut şeyleri **süzme**
(Filter) yetkisine sahip. Bu, plugin'lerin birbirini bozmasını engeller.

**Auth plugin gerçek implementasyonu:** `src/Plugins/AuthenticationPlugin.php`

### 6.3 Plugin komutu
`src/Commands/PluginsCommand.php` — CLI üzerinden plugin yönetimi.

---

## 7. Policies — varsayılan **izinli** (opt-out)

```php
// src/Policies/BasePolicy.php
public function __call(string $name, array $arguments): bool
{
    return true;    // ← varsayılan olarak İZİN VER
}
```

**Dikkat çekici tasarım kararı:** Voyager 2.x, tanımlanmamış bir yetki kontrolünde
**izin verir** (opt-out / permissive). Voyager 1.x ise `BasePolicy`'de
`$user->hasPermission($action.'_'.$dataType->name)` ile **kontrol eder** (opt-in).

→ Her iki yaklaşımın riski farklı:
- **1.x (opt-in):** kontrol unutulursa yetkisiz erişim → güvenli varsayılan
- **2.x (opt-out):** plugin/özel kod yolu korunur ama kontrol unutulursa **açık**

tardis için: 1.x modeli (opt-in) daha güvenli. tardis şu an ikisinin de **hiçbirini**
yapmıyor — izin üretiliyor, tüketilmiyor.

`docs/authorization.md` ve kök dizindeki `authorization.md` ayrıntılı.

---

## 8. Rules

`src/Rules/ClassExists.php` — formfield seçeneklerinde model referansı doğrulama
`src/Rules/DefaultLocale.php` — varsayılan locale doğrulama

→ **Yapılandırılmış kurallar** (`class-exists`, `default-locale`) BREAD JSON'unda
seçenek olarak saklanıp form gönderiminde doğrulanır. Model referansı hatalıysa
kullanıcı formu gönderdiğinde değil, **yapılandırırken** hata alır.

---

## 9. Settings Manager

`src/Manager/Settings.php` — 13 metot:

| Metot | İşlev |
|---|---|
| `get()` | Tüm ayarlar |
| `set($key, $value, $locale, $save)` | Ayar yaz (**locale destekli!**) |
| `merge($settings)` | Toplu yazma |
| `setting($key, $default, $translate)` | Tek ayar okuma |
| `exists($group, $key)` | Varlık kontrolü |
| `save()` | Dosyaya yaz |
| `getSettingsByKey($key)` | Anahtara göre filtrele |
| `load()` / `unload()` | Önbellek yönetimi |

→ Voyager 1.x'in `settings` tablosundan farklı olarak Voyager 2.x ayarları
**dosyada** tutar ve **`$locale` parametresi** ile çok dilli ayar yazımı
destekler. `notAsSetting` bayrağı hangi field'ların ayar olarak kullanılabileceğini
belirler.

---

## 10. Menu Manager

`src/Manager/Menu.php` — `addItems()`, `getItems(PluginManager, $userMenu)`,
`getUnfilteredItems()`

→ Menü öğeleri **plugin manager üzerinden** toplanır: `MenuItems` (Provider)
pluginlerin menü eklemesine, `MenuItems` (Filter) pluginlerin menüyü süzmeye
izin verir. `userMenu=true` ayrı bir kullanıcı menüsü döndürür.

---

## 11. Media Manager (2.x)

`src/Http/Controllers/MediaController.php` + `src/Classes/Media.php`
Doküman: `docs/media-manager.md`
Field: `MediaPicker.php` — tam lifecycle (add, browse, read, edit, store, update)
Plugin entegrasyonu: `Contracts/Plugins/Features/Filter/Media.php` — **medya
listesini plugin süzebilir** (örn. kullanıcı bazlı medya izolasyonu).

---

## 12. Command'lar

| Komut | İşlev |
|---|---|
| `InstallCommand` | Kurulum |
| `DevCommand` | Geliştirme yardımcıları |
| `ModelCommand` | Model üretimi |
| `PluginsCommand` | **Plugin yönetimi** |

---

## 13. Layout & View sistemi

`src/Classes/Layout.php`, `Classes/Column.php`, `Classes/Widget.php`
Dokümanlar: `docs/bread/layouts.md`, `docs/bread/views.md`, `docs/bread/lists.md`

Field özelliği: `?int $tab` → **layout sekmeleri** (Create / Edit / Read / Browse
ayrı ayrı sekmelere bölünebiliyor).

Aksiyon bazlı layout:
```php
getLayoutForAction(BreadClass $bread, string $action): Layout
getLayoutsForAction(BreadClass $bread, string $action): Collection
```

→ **Aksiyon bazlı layout**: create formu, edit formu ve read sayfası **farklı
layout** kullanabilir. tardis'te create/edit blade'leri neredeyse kopyalanmış
durumda; bu yapı tek yapı taşıyıcısıyla çözüyor.

---

## 14. BREAD özellikleri (docs/bread/)

| Doküman | Kapsam |
|---|---|
| `index.md` | BREAD genel bakış |
| `validation.md` | Field bazlı kurallar, çevrilebilir mesaj, locale/array doğrulama |
| `relationships.md` | İlişki tanımları |
| `multilanguage.md` | Çok dillilik |
| `manipulate-data.md` | Veri dönüşümü |
| `actions.md` | BREAD aksiyonları |
| `layouts.md` | Layout sistemi |
| `lists.md` | Liste/index görünümü |
| `views.md` | View katmanı |

Plugin: `docs/plugin-manager.md`, `docs/plugins/`
Diğer: `docs/media-manager.md`, `docs/settings.md`, `docs/overriding/`,
`docs/formfields/` (17 dosya), `docs/getting-started/`, `docs/contributing/`

**Ek dokümanlar:** `authorization.md` (kök), `TODO.md`, `CSS_VARS.md`

---

## 15. tardis karşılaştırması — Voyager 2.x'ten alınacak dersler

| Konu | Voyager 2.x | tardis durumu | Öncelik |
|---|---|---|---|
| **Field lifecycle** | 8 aşama, `update($model,$value,$old)` | Sadece `transform()` + `stored()` | **P0** |
| **Browse temizleme** | `browse()` içinde `strip_tags` + limit | index'te global, alan bazlı seçenek yok | P1 |
| **Validation** | Kural + **çevrilebilir mesaj** | Tümü düşüyor | **P0** |
| **Çok dilli doğrulama** | all locales / current locale | Yok | P1 |
| **Dizi elemanı doğrulama** | `.field:rule` nokta sözdizimi | `has_many` satırları doğrulanmıyor | **P0** |
| **`notTranslatable` bayrağı** | Alan seviyesinde koruma | **YOK → P0 veri kaybı** | **P0** |
| **Reflection ile ilişki keşfi** | Otomatik | Manuel JSON | P2 |
| **BREAD yedekleme/rollback** | Var | Yok | P2 |
| **Aksiyon bazlı layout** | Ayrı layout'lar | Kopya blade'ler | P1 |
| **Plugin Provider/Filter ayrımı** | Net ayrım | Yok | P3 |
| **Plugin sürümü** | Composer `InstalledVersions` | Yok | P3 |
| **Ayar locale desteği** | `set($key,$value,$locale)` | Yok | P2 |
| **Yapılandırılmış kurallar** | `ClassExists`, `DefaultLocale` | Yok | P2 |
| **Model scope keşfi** | `getModelScopes()` | Yok | P2 |

---

## 16. Kaynak dosya haritası (Voyager 2.x)

```
src/Classes/Formfield.php        8 aşamalı lifecycle taban sınıf
src/Classes/Bread.php            BREAD tanımı
src/Classes/Column.php           sütun eşlemesi
src/Classes/Layout.php           layout tanımı
src/Classes/Media.php            medya tanımı
src/Classes/Widget.php           widget tanımı
src/Classes/Action.php           aksiyon tanımı
src/Classes/DynamicInput.php     dinamik girdi
src/Formfields/*.php             16 field sınıfı
src/Manager/Breads.php           BREAD yönetimi + Reflection keşfi
src/Manager/Plugins.php          plugin registry
src/Manager/Settings.php         ayar yönetimi (locale destekli)
src/Manager/Menu.php             menü yönetimi
src/Policies/BasePolicy.php      __call → true (opt-out)
src/Rules/ClassExists.php        model referansı doğrulama
src/Rules/DefaultLocale.php      locale doğrulama
src/Contracts/Plugins/*          17 plugin kontratı
src/Plugins/AuthenticationPlugin.php
src/Http/Controllers/            Auth, Bread, BreadBuilder, Media, Plugins, Settings
src/Commands/                    Install, Dev, Model, Plugins
docs/                            yerel doküman (bread/, formfields/, plugins/, ...)
authorization.md, TODO.md, CSS_VARS.md
```

---

*Sonraki dosyalar: `03-voyager-plugin-sistemi.md`, `04-resmi-dokumanlar.md`,
`05-tardis-bulgulari-ve-fix-oncelikleri.md`, `00-ozet.md`*
