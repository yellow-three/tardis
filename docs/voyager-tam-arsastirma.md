# TARDIS BREAD DENETİMİ + VOYAGER TAM ARAŞTIRMA

> **Durum notu (2026-10-03).** Bu rapor 2026-09-29 tarihli bir anlık görüntüdür. "Düzeltilmedi, planlandı" denen P0 bulguları (validation, translatable, BelongsToMany kapsamı) sonradan düzeltildi, izin kontrolü sayfalarda uygulanıyor ama varsayılan olarak açık (`docs/notes.md` B9); test sayısı 365'ten 476'ya çıktı. Bulgu bazında güncel durum tablosu: `research/05-tardis-bulgulari-ve-fix-oncelikleri.md` (en üstte). Açık işler: `docs/notes.md`.

**Tarih:** 2026-09-29
**Branch:** `feat/modern-admin-redesign`
**Kapsam:** `packages/tardis` BREAD akışları (19 field tipi, validation, ilişki, izin,
render, kayıt) + Voyager 1.x ve Voyager 2.x'in tüm sistemleri (BREAD, FormFields,
Plugin, Media, Settings, Policies, Menu, Database Manager, Compass, Widgets, i18n,
Events, Console) + 92 resmi markdown doküman sayfası (230 asset ile 328 dosya)

> Bu dosya tek birleşik nihai rapordur. Detaylı çalışma notları `research/` altındaki
> 00–05 numaralı dosyalardadır; bu rapor onların sentezidir.
> Ayrıca `docs/voyager-karsilastirma.md` kullanıcıya ait önce bir içeriktir, korunmuştur.

---

# BÖLÜM 1 — YÖNETİCİ ÖZETİ

## 1.1 Ne yapıldı

| İş | Durum |
|---|---|
| Tardis BREAD denetimi (19 field tipi, 5 sayfa akışı) | ✅ Tamamlandı |
| Render/UI eksiklerinin düzeltilmesi + testleri | ✅ Tamamlandı — 365 test geçiyor |
| Derinlemesine davranış denetimi (validation, izin, ilişki, kayıt) | ✅ Tamamlandı — **3 kritik hata bulundu** |
| Voyager 1.x kaynak + 38 doküman sayfası | ✅ Tamamlandı |
| Voyager 2.x kaynak + 55 doküman dosyası | ✅ Tamamlandı |
| Plugin sistemi (her iki sürüm, 17 kontrat) | ✅ Tamamlandı |
| **TEK birleşik rapor (bu dosya)** | ✅ Tamamlandı |

## 1.2 En kritik 3 bulgu — düzeltilmedi, planlandı

> Tam kanıt, `dosya:line` referansları, önerilen çözüm ve test planı:
> `research/05-tardis-bulgulari-ve-fix-oncelikleri.md`

### 🔴 P0-1 — BREAD aksiyonlarında HİÇ izin kontrolü yok

`Permission::forBread()` izinleri **üretiyor** ama **hiçbir yerde tüketilmiyor**:

```bash
grep -rn "forBread\|authorize\|can(" resources/views/pages/bread/   →  (boş)
```

`forBread()` yalnız `src/Auth/TardisAuthorizationPlugin.php:44` içinden
(kayıt tarafında) çağrılıyor. `resources/views/pages/bread/{create,edit,index,read,manage}/*.php`
dosyalarının hiçbirinde `authorize` / `can` / `forBread` **yok**.

**Etki:** Giriş yapmış her kullanıcı, kendisine tanımlanmamış bir BREAD'in
create/edit/delete işlemini gerçekleştirebilir. En ciddi bulgu.

### 🔴 P0-2 — Field `validation` kuralları sessizce düşüyor

`resources/views/pages/bread/create/create.php:107-132`:

```php
$fieldRules = (array) ($field['validation'] ?? []);   // ["required","unique"] okunuyor
$rules['form.'.$name] = in_array('required', $fieldRules, true) ? 'required' : 'nullable';
// ↑ SADECE 'required' honored ediliyor
```

`unique`, `email`, `min`, `max`, `confirmed`, `regex` **hiçbir şekilde** validator'a
aktarılmıyor. `mimes`/`max_size` ise `validation` dizisinden değil, **ayrı option
alanlarından** yeniden türetiliyor.

**Kanıt (çalıştırılan probe):**
```
unique ihlali reddedildi mi   → false
email kuralı reddetti mi      → false
duplicate sonrası kayıt sayısı → 2      (1 olmalıydı)
max:5 sınırı                  → uygulanmıyor
```

Gerçek `posts.json` içinde `slug: ["required","unique"]` tanımlı — **bu koruma
tamamen etkisiz.**

### 🔴 P0-3 — `translatable: true` alanlar `NULL` kaydediliyor

Translatable alan için beklenen `{"tr":"...","en":"..."}`; gerçekleşen: düz string
gönderildiğinde alan **`NULL`** oluyor. Kullanıcı veri giriyor, sistem sessizce boş
kaydediyor, **hata mesajı yok**. Veri kaybı.

**Çözüm ipucu (Voyager 2'den):** `notTranslatable` bayrağı + dizi tipinde
normalizasyon. Düz string geliyorsa `[$locale => $value]` sarmalayıp kaydet.

### 🟠 P1 — Yetki aşımı (IDOR)

`BelongsToManyField::stored()` istemciden gelen ilişkili model **ID listesini
doğrudan `sync()` ediyor** — yetki/kapsam kontrolü yok. Tahmin edilen bir ID ile
kayıt bağlanabilir.

## 1.3 Tamamlanan ve commit edilen iş

```
fbe13fb  fix(bread): render every field type and stop save dying on NOT NULL columns
d7a1365  test(bread): cover every field type's control and the edit upload path
```

**Test durumu:** 365 passed (888 assertions) · `php -l` temiz · `git diff --check` temiz
Push/merge **yapılmadı**. Bu turda kod değişikliği yok.

---

# BÖLÜM 2 — KAPSAM VE KAYNAKLAR

| Kaynak | Kapsam | Durum |
|---|---|---|
| `github.com/thedevdojo/voyager` (branch `1.8`, `cb56948`, 2024-10-14; ilk okuma `1.x` / 2022-01) | Kaynak kodu | ✅ yeniden doğrulandı (2026-10-03) |
| `voyager-docs.devdojo.com/1.x/` | 38 doküman sayfası (1.5'i anlatır) | ✅ `research/voyager-1x-docs/` |
| `github.com/voyager-admin/voyager` (branch `2.x`, `47fb33b`, 2022-10-21) | Kaynak kodu + 55 doküman | ✅ yeniden doğrulandı (2026-10-03) |
| `voyager-admin.github.io/voyager/` | 55 sayfa | ✅ repo docs ile aynı içerik doğrulandı |

**Eksik bırakılmış sayfa yok.**

| Dizin | Markdown | Asset | Toplam |
|---|---|---|---|
| `research/voyager-1x-docs/` | 38 | 0 | 38 |
| `research/voyager-2x-docs/` | 55 | 230 (113 js, 56 png, 55 html, 2 svg, 2 json, 2 css) | 285 |
| **Markdown doküman toplamı** | **93** | — | — |
| 6 araştırma raporu | 6 | — | — |
| **`research/` geneli** | **98** | **230** | **328 (3,2 MB)** |

---

# BÖLÜM 3 — TARDIS BREAD DENETİMİ

## 3.1 Sayfa akışları

```
resources/views/pages/bread/
├── create/   create.php + create.blade.php
├── edit/     edit.php + edit.blade.php
├── index/    index.php + index.blade.php      ← orderBy:52
├── read/     read.php + read.blade.php
└── manage/   manage.php + manage.blade.php
```

## 3.2 Render mimarisi — ölü kod (P1-3)

| Kanıt | Sonuç |
|---|---|
| `grep -rn "viewData(" resources/views/pages/bread/ src/` | Yalnız **tanımlar** (`src/Formfields/Types/*`) |
| `grep -rn "->render(" resources/views/pages/bread/ src/` | **Boş** — hiç çağrılmıyor |
| `FormfieldManager.php:69-71` → `field()` | **Her zaman `[]` dönüyor** |

`Formfield::viewData()` (`Formfield.php:174`) ve `abstract render()`
(`Formfield.php:193`) tanımlı ama **çağrılmıyor**. 19 field sınıfının
implementasyonları ve `resources/views/formfields/` view'ları **ölü kod**.

**Etki:** Üçüncü parti field ekleme yolu kapalı — her yeni tip create/edit
blade'lerine **elle** eklenmek zorunda (iki yerde kopya).

**Çözüm (Voyager 2 mimarisi):** Handler ince, mantık field sınıfında;
blade'de tek render noktası: `{!! $field->render() !!}`.

## 3.3 Field tipleri — 19 tip

`src/Formfields/Types/`: text, textarea, markdown, code_editor, number, select,
radio, checkbox, tags, slug, image, file, datetime, date, time, color, slider,
has_many, belongs_to, belongs_to_many

### Voyager 1.x'e göre eksikler

| Tip | 1.x | 2.x | tardis |
|---|---|---|---|
| `color` | ✅ | ❌ | ✅ |
| `coordinates` | ✅ | ❌ | ❌ |
| `hidden` | ✅ | ❌ | ❌ |
| `rich_text_box` | ✅ | ❌ | ❌ (code_editor + markdown var) |
| `multiple_checkbox` | ✅ | `Checkbox` (çoklu) | ❌ `checkbox` **tek kutu** |
| `select_multiple` | ✅ | `Select` (çoklu) | ❌ `select` **tek seçim** |
| `timestamp` | ✅ | `DateTime` | ✅ `datetime` |
| `image`/`multiple_images`/`media_picker` | ✅ 3 ayrı tip | `MediaPicker` | tek generic `file` |
| `dynamic_input` | ❌ | ✅ | ❌ |
| `simple_array` | ❌ | ✅ | ❌ |
| `repeater` | ❌ | ✅ | `has_many` |

## 3.4 Select options — statik array yetersiz (P1)

**Voyager 1.x `select_dropdown.blade.php` üç kaynaklı option üretir:**

1. **Statik** — `$options->options` sözlüğü, `<optgroup label="Custom">` içinde
2. **Model list metodu** — `Str::camel($row->field).'List'` modelde varsa çağrılır
3. **İlişki** — `->{camelField}()->getRelated()` + `options->relationship->where` filtresi

Ayrıca: `_empty_` → `''`, `default` yalnız kayıt yoksa, `old()` ile hata sonrası
seçim korunur, `(string)$selected == (string)$key` gevşek karşılaştırma.

**tardis'te:** sadece statik array. İlişkiden seçenek üretme yok.

## 3.5 Kayıt akışı ve transaction (P2-1)

**Voyager 1.x `Controller::insertUpdateData()` (324 satır):**
- Ana kayıt `save()`, çeviriler `saveTranslations()` **sonra**
- `belongsToMany` → `sync()` **toplu, save() sonrası**
- **Boş dosya = mevcut korunur** (`image`/`file`/`password`)
- `belongsTo` otomatik atlanır (FK model ilişkisinden gelir)
- Checkbox request'te yoksa "silinmiş" sayılmaz, mevcut korunur
- Media picker `session` uuid'si kayıt id ile değiştirilir

**Voyager 1.x BREAD builder'da transaction VAR** (`beginTransaction`/`rollBack`).

**tardis:** create ilişki `stored()` işlemleri **transaction dışında** →
ilişki yazımı başarısız olursa ana kayıt kalır (kısmi kayıt).

## 3.6 Diğer bulgular

| Bulgu | Öncelik |
|---|---|
| `HasManyField` ilişki satırlarını **filtrelemeden** create/update | P1 |
| `checkbox` options yoksa **tek kutu** render ediyor | P1 |
| `disabled`/`readonly`/`help`/`width`/`default`/`attributes` canlı Blade'de **yok** | P2 |
| `index/index.php:52` → `orderBy` var ama izin kontrolü yok | P1 |
| Eksik alan kaydı temizliği yok (`whereNotIn(...)->delete()` karşılığı) | P2 |
| `has_many` satırları **hiç doğrulanmıyor** (Voyager'ın `.field:rule` sözdizimi) | P1 |

---

# BÖLÜM 4 — VOYAGER 1.x TAM SİSTEM ARAŞTIRMASI

> Detaylı notlar: `research/01-voyager-1x.md`

## 4.1 Genel mimari

| Katman | Konum | Not |
|---|---|---|
| Field handler'ları | `src/FormFields/` — 24 `.php` | 22 somut tip + `AbstractHandler` + `HandlerInterface` |
| Field view'ları | `resources/views/formfields/` — 23 view | Mantık **view'da** |
| BREAD CRUD | `Http/Controllers/Controller.php` | `insertUpdateData()` 324 satır |
| BREAD builder | `VoyagerBreadController.php` | 357 satır, `authorize` |
| Şema keşfi | `Database/Schema/SchemaManager.php` | Doctrine DBAL (1.x branch); 1.8'de Laravel şema builder'ı |
| Modeller | `src/Models/` (12) | `DataType`, `DataRow`, `Setting`, `Menu`, `MenuItem`, `Permission`, `Role`, `Translation`… |
| Politikalar | `src/Policies/` (5) | `BasePolicy` + model bazlı |
| Widget'lar | `src/Widgets/` | `BaseDimmer` + 3 (Arrilot) |
| Event'ler | `src/Events/` (24) | BREAD/media/table/menu lifecycle |
| Komutlar | `src/Commands/` (4) | install, admin, controllers, make:model |
| Config | `publishable/config/voyager.php` | 11 grup |
| i18n | `publishable/lang/` | **34 dil** |

## 4.2 22 somut field tipi

```
Checkbox  CodeEditor  Color  Coordinates  Date  File  Hidden  Image
MarkdownEditor  MediaPicker  MultipleCheckbox  MultipleImages  Number
Password  RadioBtn  RichTextBox  SelectDropdown  SelectMultiple
TextArea  Text  Time  Timestamp
```

Handler **son derece ince** — mantık view'da:

```php
class TextHandler extends AbstractHandler
{
    protected $codename = 'text';
    public function createContent($row, $dataType, $dataTypeContent, $options)
    { return view('voyager::formfields.text', [...]); }
}
```

## 4.3 Validation — tam kural seti (tardis P0-2'nin çözümü)

`Controller.php:197-255`:

```php
$rules[$fieldName] = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);

// ⭐ add / edit AYRI kural seti
$rules[$fieldName] = array_merge($rules[$fieldName], $field->details->validation->edit->rule);
$rules[$fieldName] = array_merge($rules[$fieldName], $field->details->validation->add->rule);

return Validator::make($data, $rules, $messages, $customAttributes);
```

Aynı alan create'de `required`, edit'te `sometimes` olabilir. Tüm kurallar
korunur — **tardis'in düşürdüğü tam olarak bu**.

## 4.4 ⭐ Policies — gerçek izin tüketimi (tardis P0-1'in çözümü)

`src/Policies/BasePolicy.php` — doğrulandı:

```php
public function __call($name, $arguments)          // magic: her izin adı yakalanır
{
    $user = $arguments[0]; $model = $arguments[1];
    return $this->checkPermission($user, $model, $name);
}

public function delete(User $user, $model)         // soft-delete kontrolü ile
{
    $soft_delete = $model->deleted_at && in_array(SoftDeletes::class, class_uses_recursive($model));
    return !$soft_delete && $this->checkPermission($user, $model, 'delete');
}

public function restore(User $user, $model)        // silinmişse 'delete' izniyle
{ return $model->deleted_at && $this->checkPermission($user, $model, 'delete'); }

protected function checkPermission(User $user, $model, $action)
{ /* DataType bulunur → $user->hasPermission($action.'_'.$dataType->name) */ }
```

**İki katmanlı tüketim:**
1. **Controller:** her BREAD aksiyonunda `$this->authorize('browse_bread')`
   (`VoyagerBreadController` 6 nokta; `VoyagerMediaController` upload/crop'ta `browse_media`)
2. **Model:** `BasePolicy` → `hasPermission()`

→ **tardis'te izin üretiliyor, tüketilmiyor.** `TardisAuthorizationPlugin` isimli
sınıf var ama gerçek karar vermiyor.

## 4.5 ⭐ Menu — izin bazlı menü gizleme (yeni keşif)

`Menu::processItems()` (`src/Models/Menu.php:108-160`):

```php
// Aktif durum: tam eşleşme → alt yol (admin/posts/1/edit ⇒ admin/posts) → çocuklardan yayılım
$items = $items->filter(fn($item) =>
    !$item->children->isEmpty() || Auth::user()->can('browse', $item)   // ⭐ İZİN BAZLI SÜRME
)->filter(fn($item) => !($item->url=='' && $item->route=='' && $item->children->count()==0));
```

**Menü öğeleri kullanıcının BREAD iznine göre otomatik gizlenir** — yetkisiz
kullanıcı bağlantıyı hiç görmez, tıklayamaz.

Diğer özellikler:
- `MenuItem`: `Translatable` trait, `$translatable = ['title']`, `link` çeviriye bağlı
- `children()` → öz-yinelemeli ağaç (`parent_id`)
- `prepareLink()` → 3 link tipi: `route` (yoksa `'#'`), `url` (mutlak/göreli)
- `parameters` mutatörü string/array/object → **her zaman diziye normalize**
- `Cache::remember('voyager_menu_'.name, 30 gün)`; `created/saved/deleted` → `Cache::forget`
- `VoyagerMenuController`: `builder`, `add_item`, `update_item`, `order_item`, `delete_menu`
- `config('voyager.bread.add_menu_item')` → BREAD eklenince menü öğesi **otomatik** oluşur

## 4.6 Media Manager

`VoyagerMediaController` — 9 metot: `index`, `files` (AJAX klasör ağacı),
`new_folder`, `delete`, `move`, `rename`, `upload`, `crop`

- **Yetki:** `$this->authorize('browse_media')`
- **MIME politikası:** `config('voyager.media.allowed_mimetypes')`, `*` ise kapalı
- **Çakışma çözümü:** `while (file exists) $name = get_file_name($name)`
- **Şablonlu dosya adı:** `{uid}` → user key · `{date:FORMAT}` → Carbon · `{random:N}` → `Str::random(N)`
- **Crop:** `createMode=true` → yeni dosya (`cropped_{time}`) · `false` → **orijinalin üzerine yaz**
- **BREAD entegrasyonu:** `Image`/`MultipleImages`/`MediaPicker` ayrı tipler;
  `media_picker` sonrası session uuid'si **kayıt id ile değiştirilir** (`:uuid` → id)
- **Trait'ler:** `Resizable` (boyut varyantları), `Spatial` (en/boy)
- Disk: `Storage::disk($this->filesystem)`

**tardis'te:** medya yönetimi yok; `file` alanı diske yazıp yol döndürüyor.
Medya paneli, klasör ağacı, kırpma, MIME politikası eksik.

## 4.7 Settings

`VoyagerSettingsController` — 8 metot: `index`, `store`, `update`, `delete`,
`move_up`, `move_down`, `delete_value`

```php
// settings tablosu
id, key(unique), display_name, value(text, nullable), details(json, nullable),
type, order(default 1), group(nullable)     // timestamps = false, $guarded = []
```

- Ayar = **key/value satırı**, `group` ile sekmelere ayrılır
- `order` + `move_up`/`move_down` → elle sıralama
- `type` render tipi → **ayarlar BREAD ile aynı evrende**
- `details` alan bazlı seçenekler (tıpkı `DataRow`)
- `dispatchesEvents`: `updating` → `SettingUpdated`

## 4.8 Database Manager

`VoyagerDatabaseController` — 9 metot: `index`, `create`, `store`, `edit`,
`update`, `destroy`, `show`, `cleanOldAndCreateNew` (rename'de veri taşıma),
`reorder_column` (sütun sıralama)

Gizli tablolar: `migrations, data_rows, data_types, menu_items, password_resets,
permission_role, settings`

## 4.9 Compass (dashboard) + Widgets

`VoyagerCompassController`; `src/Actions/`; `src/Alert/Components/`
(`AbstractComponent`, `ButtonComponent`, `TextComponent`, `TitleComponent`);
`AlertsMessages` trait'i; `Voyager::` facade + `Alert` bileşen sistemi.
Widget'lar: `BaseDimmer` (Arrilot `AbstractWidget`) + `PostDimmer`, `PageDimmer`, `UserDimmer`.
`config('voyager.dashboard')`.

## 4.10 i18n

- `src/Translator.php` + `Translator/Collection.php`; `Helpers/helpersi18n.php`
- `publishable/lang/` — **34 dil** (1.8; `az`, `km`, `my` sonradan eklendi)
- `config('voyager.multilingual')`: `enabled`, `default`, `locales[]`
- BREAD `display_name` çevirilebilir (`is_bread_translatable`, `prepareTranslationsFromArray`)
- **Çeviri disiplini:** `required`/`unique`/`email`/`max` dahil **tüm kural
  mesajları** çeviri dosyasından gelir — hard-coded İngilizce mesaj yok

## 4.11 Event kataloğu (24)

```
BREAD:   BreadAdded, BreadUpdated, BreadChanged, BreadDeleted,
         BreadDataAdded, BreadDataUpdated, BreadDataChanged,
         BreadDataDeleted, BreadDataRestored
Medya:   MediaFileAdded, FileDeleted, BreadImagesDeleted
Tablo:   TableAdded, TableUpdated, TableChanged, TableDeleted
Menü:    MenuDisplay, Routing, RoutingAdmin, RoutingAdminAfter, RoutingAfter
Field:   FormFieldsRegistered      ← üçüncü parti handler kaydı
Ayar:    SettingUpdated
UI:      AlertsCollection
```

## 4.12 Console komutları (4)

| Komut | İşlev |
|---|---|
| `voyager:install` | kurulum, migration, publish, admin oluşturma |
| `voyager:admin` | admin kullanıcı oluşturma/yükseltme |
| `voyager:controllers` | controller üretimi |
| `voyager:make:model` | BREAD ile eşleşen model üretimi |

---

# BÖLÜM 5 — VOYAGER 2.x TAM SİSTEM ARAŞTIRMASI

> Detaylı notlar: `research/02-voyager-2x.md`

## 5.1 1.x'ten temel mimari fark

| Konu | 1.x | 2.x |
|---|---|---|
| Field tanımı | Handler + Blade view | **Tek PHP sınıfı** |
| Field lifecycle | `createContent()` | **8 aşama** `browse/read/edit/update/updated/add/store/stored` |
| BREAD depolama | MySQL (`data_types`+`data_rows`) | **JSON** `storage/voyager/breads/` |
| Şema kaynağı | Doctrine DBAL | **Model Reflection** |
| Field kaydı | `FormFieldsRegistered` event | `addFormfield(class)` registry |
| Frontend | Server-rendered Blade | **Vue + Tailwind** |
| Validity | PHP 7.3+ | PHP 8+ (`mixed`, union, `: void`) |

→ Voyager 2.x, tardis'in **JSON tabanlı BREAD** yaklaşımına çok daha yakın.

## 5.2 ⭐ 8 aşamalı lifecycle (tardis'in en büyük eksikliği)

| Metot | Ne zaman | Girdi | Not |
|---|---|---|---|
| `browse($value)` | index | değer | **HTML temizleme burada** |
| `read($value)` | detay | değer | |
| `edit($value)` | edit formu | değer | |
| `update($model,$value,$old)` | update **öncesi** | değer + **eski değer** | `$old` kritik |
| `updated($model,$value)` | update **sonrası** | model | void |
| `add()` | create formu | — | varsayılan değer |
| `store($value)` | store **öncesi** | değer | |
| `stored($model,$value)` | store **sonrası** | model | void |

**tardis'te yalnız `transform()` + `stored()` var** → `browse`/`read`/`edit`
ayrımı yok, `update` öncesi/sonrası ayrımı yok (eski değere erişilemiyor).

Örnek — `Text`:

```php
public function browse(mixed $input): mixed {
    return Str::limit(strip_tags($input), $this->options->display_length ?? 150);
}
public function add(): mixed { return $this->options->default_value ?? ''; }
```

## 5.3 `dontStore` bayrakları — ⭐ P0-3'ün çözümü

```php
protected array $dontStore = [
    'notTranslatable', 'notAsSetting', 'notInLists', 'notInViews',
    'browseArray', 'noColumns', 'noComputedProps', 'noRelationships',
    'noRelationshipProps', 'noRelationshipPivots', 'uuid',
];
```

`notTranslatable` = "bu alan çevirilemez" koruması. Voyager'ın `translatable: true`
alanlarda ihtiyaç duyduğu **öncü kontrol** — tardis'te **hiç yok**, doğrudan
P0 veri kaybı hatasının kaynağı.

## 5.4 16 field sınıfı

```
Checkbox  DateTime  DynamicInput  MediaPicker  Number  Password
Radio  Relationship  Repeater  Select  SimpleArray  Slider
Slug  Tags  Text  Toggle
```

`src/Formfields/Types/` klasörü **yok** — tipler düz sınıflar.

Öne çıkan davranışlar:
- `Relationship` → `stored()`/`updated()` ile ilişkiyi **kayıt sonrası** yazar
- `Password` → `add` override'ı **yok** (yeni kayıtta boş bırakılabilir)
- `Slider`/`Slug`/`Toggle` → saf view, mantık yok

## 5.5 BREAD Manager — Reflection + yedekleme

`src/Manager/Breads.php` — 27 public metot. Kritik olanlar:

| Metot | İşlev |
|---|---|
| `storeBread($bread)` | JSON'a kaydet |
| `getBackups()` / `backupBread()` / `rollbackBread()` | **Yedekleme + geri alma** |
| `getModelReflectionClass($model)` | Modeli yansıt |
| `getModelRelationships($reflection,$model)` | **İlişkileri otomatik keşfet** |
| `getModelComputedProperties($reflection)` | **Accessor'ları otomatik keşfet** |
| `getModelScopes($reflection)` | **Query scope'larını keşfet** |
| `addFormfield($class)` / `getFormfield($type)` | Field registry |
| `manipulateActions(callable)` | Aksiyonları programatik değiştir |
| `getLayoutForAction($bread,$action)` | **Aksiyon bazlı layout** |

→ **Reflection tabanlı otomatik keşif** (ilişkiler, accessor'lar, scope'lar) ve
**BREAD yedekleme/rollback** tardis'te yok.

## 5.6 Validation — kurul + çevrilebilir mesaj

- Field seçeneklerinde `Validation` bölümü: kural (`Rule`) + **mesaj** (`Message`)
- **Mesaj alanı çevrilebilir** → kullanıcıya yerelleştirilmiş hata
- Layout seviyesinde iki mod: **validate all locales** / **validate current locale**
- `Voyager::setLocales(['de','en'])` ile kullanıcıya göre dinamik dil
- **Dizi elemanı doğrulama:** `.name:required` nokta sözdizimi
  → `has_many` satırlarının her biri doğrulanabilir (**tardis'te hiç doğrulanmıyor**)

## 5.7 Policies — ⭐ opt-out (varsayılan izinli)

```php
// src/Policies/BasePolicy.php
public function __call(string $name, array $arguments): bool
{
    return true;    // ⭐ tanımlanmamış kontrolde İZİN VER
}
```

**İki yaklaşımın riski farklı:**

| | Yaklaşım | Kontrol unutulursa |
|---|---|---|
| 1.x | opt-in (`hasPermission` kontrol eder) | Güvenli varsayılan |
| 2.x | opt-out (`return true`) | **Açık** |
| **tardis** | **hiçbiri** | İzin üretiliyor, tüketilmiyor |

→ **tardis için 1.x modeli (opt-in) daha güvenli.**

## 5.8 Diğer sistemler

| Sistem | Konum | Not |
|---|---|---|
| Settings | `Manager/Settings.php` (11 metot) | **Dosya tabanlı**, `set($key,$value,$locale)` **çok dilli** |
| Menu | `Manager/Menu.php` | `addItems()`, `getItems(PluginManager,$userMenu)`, `getUnfilteredItems()` |
| Media | `Http/Controllers/MediaController.php` + `Classes/Media.php` | `Filter\Media` ile plugin sürebilir |
| Rules | `Rules/ClassExists.php`, `Rules/DefaultLocale.php` | Model referansı **yapılandırma anında** doğrulanır |
| Layout | `Classes/Layout.php` + `?int $tab` | **Aksiyon bazlı** ayrı layout'lar |
| Commands | 4 | install, dev, model, plugins |

---

# BÖLÜM 6 — PLUGIN SİSTEMİ

> Detaylı notlar: `research/03-voyager-plugin-sistemi.md`

## 6.1 Voyager 1.x — plugin dosyası çekirdekte YOK

`find -iname "*plugin*"` → boş. Mimari **dolaylı**:

| Mekanizma | Rolü |
|---|---|
| `composer.json` → `extra.laravel.providers` | Otomatik keşif |
| `VoyagerEventServiceProvider` | Event dinleyicileri |
| `FormFieldsRegistered` event | **Üçüncü parti formfield kaydı** |

~~Resmi plugin'ler: `voyager-hooks`, `voyager-mail`, …~~ — **düzeltme (2026-10-03):** `tcg/voyager-*` paketleri Packagist'te yok ve kaynakta/dokümanda geçmiyor; yalnızca üçüncü parti `larapack/voyager-hooks` gerçektir ve 1.5'te kaldırıldı. Bkz. `research/03-voyager-plugin-sistemi.md` §1.3.

**Yok:** yönetim paneli, açık/kapalı anahtarı, ayar arayüzü, sürüm takibi, katalog.
→ Plugin = Composer paketi + Laravel event'leri (+ `FormFieldsRegistered`).

## 6.2 Voyager 2.x — first-class plugin sistemi

**5 tip:** Authentication · Authorization · Formfield · Generic · Theme

**Kayıt:**

```php
class MyPluginServiceProvider extends ServiceProvider
{
    public function boot(PluginManager $pluginmanager)
    { $pluginmanager->addPlugin(\My\Plugin\MyPlugin::class); }
}
```

**Yönetim:** Search Plugins · Enable/Disable (`storage/voyager/plugins.json`) ·
Settings (Vue modal) · Theme Preview. Sürüm **Composer `InstalledVersions`**'tan okunur.

### ⭐ Provider / Filter ayrımı — çekirdek tasarım kararı

| | Provider | Filter |
|---|---|---|
| Yön | Sistem geneline **ekler** | Mevcut şeyleri **süzme** |
| Etki alanı | Yalnız kendi katkısı | **Tüm sistemi** etkiler |
| Entegrasyon riski | Düşük | Yüksek |

Provider (8): `MenuItems`, `Widgets`, `Settings`, `SettingsComponent`, `CSS`, `JS`,
`FrontendRoutes`, `ProtectedRoutes`
Filter (4): `MenuItems`, `Widgets`, `Layouts`, `Media`

→ Plugin'lerin birbirini bozmasını engelleyen kasıtlı kısıt.

### Örnekler

```php
// Provider: widget ekleme (fluent API)
public function provideWidgets(): Collection {
    return collect([(new Widget('my-component','Başlık'))
        ->icon('academic-cap')->width(6)->permission('perm_key')]);
}

// Filter: tüm menü öğelerini süzme
public function filterMenuItems(Collection $items, $mainMenu = true): Collection {
    return $items->filter(fn($item) => /* koşul */ true);
}

// Provider: doğrulamalı ayar (BREAD field şemasıyla AYNI)
public function provideSettings(): array {
    return [['type'=>'text','group'=>'G','name'=>'N','key'=>'my_setting',
             'value'=>'V','translatable'=>false,'info'=>'…','options'=>[],'validation'=>[]]];
}

// Provider: asset — publish yok, string dön + Voyager önbellekler
public function provideCSS(): string { return file_get_contents('…/asset.css'); }
```

### ⚠️ Performans uyarısı (best practice)

> "Your plugin class is loaded when calling `addPlugin(...)` **even when it's
> disabled**. To prevent long loading times… load data only when needed
> (in route definitions, for example)."

→ **Constructor'da I/O yapma.**

## 6.3 Sürüm karşılaştırması

| Özellik | 1.x | 2.x |
|---|---|---|
| Yönetim paneli / açık-kapalı | ❌ | ✅ |
| Plugin sürümü | ❌ | ✅ (Composer) |
| Plugin tipleri | tek | ✅ 5 |
| Formfield ekleme | event | ✅ kontrat (+otomatik JS) |
| Asset | `vendor:publish` | ✅ string + önbellek |
| Ayar sağlama | ❌ | ✅ validation'lı |
| Widget + `->permission()` | ❌ | ✅ |
| **Menü/Widget/Layout/Media sürme** | ❌ | ✅ 4 Filter |
| Tema | ❌ | ✅ Preview |
| Hook | yok (`larapack/voyager-hooks` 1.5'te kaldırıldı) | Kontrat tabanlı |

---

# BÖLÜM 7 — DOKÜMAN KAPSAMI

**93 markdown doküman kaydedildi** (38 + 55) ve 230 asset (görsel/js/css) indirildi;
`research/` toplamı 328 dosya / 3,2 MB.
Dizin + sayfa bazlı özetler: `research/04-resmi-dokumanlar.md`

### 1.x — 38 sayfa

| Kategori | Sayfa sayısı | Öne çıkan |
|---|---|---|
| Getting Started | 5 | `configurations` (1066 kelime) |
| BREAD | 12 | `media-picker` (872), `introduction-1` (630) |
| Core Concepts | 8 | `roles-and-permissions` (721), `multilanguage` (646) |
| Customization | 10 | `overriding-files` (455) |
| Troubleshooting | 2 | — |

### 2.x — 55 dosya

BREAD (9) · Formfields (17) · Plugin (12) · Getting Started (4) ·
Contributing (5) · `media-manager`, `settings`, `plugin-manager`, `javascript`,
`overriding/` (2) · `de/index.md`

### En değerli 10 doküman çıkarımı

1. Field bazlı validation + **çevrilebilir mesaj** (2.x)
2. **BREAD action bazlı accessor** — `getNameBrowseAttribute()` (1.x)
3. **Satır aksiyon butonları** — `getPolicy()` ile aksiyon bazlı izin (1.x)
4. Üçüncü parti field — `AbstractHandler` + `$codename` + provider kaydı (1.x)
5. Select seçenekleri **3 kaynaktan**: statik + `{field}List()` + ilişki+where (1.x)
6. **Çoklu checkbox / çoklu select ayrı tipler** (1.x) — tardis'te yok
7. **Dizi elemanı doğrulama** `.field:rule` (2.x) — `has_many` satırları
8. **Aksiyon bazlı layout** (2.x) — tardis'te kopyalanmış blade'ler
9. Çok dilli doğrulama modu — all/current locale (2.x)
10. **Filter kontratları** ile menü/widget sürme (2.x)

---

# BÖLÜM 8 — ÖNCELİKLİ DÜZELTME LİSTESİ

## Sıra ve gerekçe

```
 1. P0-1  BREAD izin kontrolü        → en yüksek güvenlik etkisi
 2. P0-2  Validation kuralları       → veri bütünlüğü
 3. P0-3  Translatable kayıp         → veri kaybı
 4. P1-1  BelongsToMany kapsam       → IDOR
 5. P1-2  HasMany filtreleme         → veri bozulması
 6. P1-3  render()/field()           → mimari borç, 3. parti field yolu
 7. P1-4  checkbox options
 8. P1-5  has_many satır doğrulama
 9. P2-1  transaction kapsamı
10. P2-2  eksik option'lar
11. P3    ekosistem (ayar, widget, plugin)
```

## 1–5. Güvenlik ve veri bütünlüğü (P0 + ilk P1)

Her biri için: **önce regresyon testi, sonra düzeltme.**

| # | Düzeltme | Referans (doğrulanmış) |
|---|---|---|
| 1 | Her BREAD aksiyonunda `browse/read/add/edit/delete` kontrolü; yoksa 403 | Voyager 1.x `BasePolicy::checkPermission` + `authorize()` |
| 2 | `validation` dizisini doğrudan Laravel kurallarına çevir; `add`/`edit` ayrı kural seti | Voyager 1.x `Controller.php:197-255` |
| 3 | Translatable alanı diziye normalize et (`[$locale => $value]`) + `notTranslatable` koruması | Voyager 2.x `Formfield::dontStore` |
| 4 | `sync()` öncesi ID'leri kapsam edilmiş sorguyla doğrula | — (güvenlik disiplini) |
| 5 | Gelen satırları mevcut kayıtlarla eşleştir; yalnız eşleşeni güncelle | Voyager 1.x "yalnız tanımlı satırlar üzerinde dön" deseni |

**⚠️ 1–5 davranış değiştirir** — erişimi varsayan mevcut testlerin bir kısmı
güncellenmeli. Geri alınabilir değildir; commit'ler ayrı tutulmalı.

## 6–10. Mimari ve işlevsel

| # | Düzeltme | Not |
|---|---|---|
| 6 | `FormfieldManager::field()` gerçek çıktı dönsün; blade'de tek render noktası | `render()`/`viewData()` ölü kodunu canlandırır |
| 7 | `checkbox` options yoksa render etme / hata ver; çoklu seçim ayrı tip | Voyager 1.x `MultipleCheckbox` |
| 8 | `has_many` satırları için `.field:rule` doğrulama | Voyager 2.x |
| 9 | Ana kayıt + ilişki `stored()` → tek `DB::transaction()` | Voyager 1.x builder'da var |
| 10 | `disabled`/`readonly`/`help`/`width`/`default`/`attributes` Blade'de uygulansın | Tek partial'a taşı |

## Yöntem kuralları

- Düzeltme sırasında `as any`, `@ts-ignore` gibi bastırmalar **kullanılmaz**
- Refactor ile bugfix **aynı commit'te birleştirilmez**
- Önce **başarısız test**, sonra düzeltme (TDD)
- Her adım sonrası `lsp_diagnostics` + tam suite

---

# BÖLÜM 9 — KISITLAR

- `/home/abdurrahman/Lerd/lara` uygulamasına **dokunulmadı** (kullanıcı kararı)
- Alt ajan **kullanılmadı** (kullanıcı talebi: "arastirmayi kendin yap alt ajan kullanma")
- **Push/merge yapılmadı**; commit'ler branch üzerinde bekliyor
- `docs/voyager-karsilastirma.md` — kullanıcıya ait untracked içerik, **korundu**
- Bu rapordaki bulgular çalıştırılan probe'lar ve grep/okuma ile **kanıtlandı**;
  `research/01` ve `research/02` kaynak notlarıdır, iddiaları tekrar teyit
  edilmelidir

---

# EKLER — Dosya Haritası

## Çalışma notları (`research/`)

| Dosya | İçerik |
|---|---|
| `00-ozet.md` | Yönetici özeti, okuma sırası |
| `01-voyager-1x.md` | 1.x kaynak araştırması (22 handler, BREAD, policy, media, settings, menu, events) |
| `02-voyager-2x.md` | 2.x kaynak araştırması (16 sınıf, lifecycle, plugin, reflection) |
| `03-voyager-plugin-sistemi.md` | Plugin sistemi — 5 tip, 17 kontrat, Provider/Filter |
| `04-resmi-dokumanlar.md` | 92 markdown sayfanın dizini ve özeti |
| `05-tardis-bulgulari-ve-fix-oncelikleri.md` | Bulgu kanıtları, `dosya:line`, çözüm, test planı |
| `voyager-1x-docs/` | 38 resmi 1.x sayfası |
| `voyager-2x-docs/` | 55 resmi 2.x doküman dosyası |

## Voyager 1.x (`/tmp/opencode/voyager1`)

```
src/FormFields/*.php                    24 dosya (22 handler + 2 base)
resources/views/formfields/*.blade.php  23 view
src/Http/Controllers/Controller.php     insertUpdateData + validation
src/Http/Controllers/VoyagerBreadController.php
src/Http/Controllers/VoyagerMediaController.php
src/Http/Controllers/VoyagerSettingsController.php
src/Http/Controllers/VoyagerDatabaseController.php
src/Http/Controllers/VoyagerMenuController.php
src/Models/DataType.php                 updateDataType, getRelationships
src/Models/DataRow.php                   sortByUrl, isCurrentSortField
src/Models/Permission.php               generateFor / removeFrom
src/Models/Menu.php                     display, processItems (izin süzme)
src/Models/MenuItem.php                 link, prepareLink, children
src/Policies/BasePolicy.php             __call + checkPermission
src/Database/Schema/SchemaManager.php   şema keşfi
src/Events/*.php                        24 event
src/Commands/*.php                      4 komut
publishable/config/voyager.php          8 config bölümü
publishable/lang/                       34 dil
```

## Voyager 2.x (`/tmp/opencode/voyager2`)

```
src/Classes/Formfield.php               8 aşamalı lifecycle + dontStore
src/Classes/{Bread,Column,Layout,Media,Widget,Action,DynamicInput}.php
src/Formfields/*.php                    16 field sınıfı
src/Manager/Breads.php                  Reflection keşfi + yedekleme
src/Manager/Plugins.php                 plugin registry
src/Manager/Settings.php                ayar yönetimi (locale destekli)
src/Manager/Menu.php                    menü yönetimi
src/Policies/BasePolicy.php             __call → true (opt-out)
src/Rules/{ClassExists,DefaultLocale}.php
src/Contracts/Plugins/*                 17 plugin kontratı
src/Http/Controllers/                   Auth, Bread, BreadBuilder, Media, Plugins, Settings
src/Commands/                           Install, Dev, Model, Plugins
docs/                                   55 doküman dosyası
```
