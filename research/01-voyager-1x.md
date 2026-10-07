# Voyager 1.x (thedevdojo/voyager) — Tam Sistem Araştırması

Kaynak: `https://github.com/thedevdojo/voyager` (branch `1.8`, `cb56948`, 2024-10-14 — 2026-10-03'te yeniden doğrulandı; ilk sürüm `1.x` branch'inden, `a95fd26` / 2022-01-15, okunmuştu)
İnceleme: repodan indirildi (`/tmp/opencode/voyager1`), kaynak kod okunarak çıkarıldı.
Tarih: 2026-09-29

> ## Yeniden doğrulama — 2026-10-03
> `thedevdojo/voyager` yeniden klonlandı (`1.8`, `cb56948`) ve bu dosyadaki sayılar/iddialar kaynakla karşılaştırıldı.
>
> - **Kaynak hâlâ geçerli:** 24 formfield `.php` (22 somut handler), 23 field view, 12 model, 5 policy, 24 event, 4 komut, `Controller.php` 324 satır (`insertUpdateData` L45, `validateBread` L195, `getContentBasedOnType` L258), `VoyagerBreadController` 357 satır; BREAD kaydında transaction yok, builder'da var (`DataType.php:88`); `Menu::display()` 30 gün cache ve `Auth::user()->can('browse', $item)` süzmesi (`Menu.php:56,149`); kayıtta `checkbox` istisnası (`Controller.php:62`).
> - **Düzeltilen hatalar:** media controller 8 action (9 değil), settings controller 7 metod (8 değil), config 11 grup (8 değil), dil sayısı 34 (31 değil), field view 23 (§16'daki 24 hatalıydı), `VoyagerDummyServiceProvider` dummy seeder/içerik yayınlar, **`tcg/voyager-*` plugin paketleri yok** (Packagist 404) — bkz. `03-voyager-plugin-sistemi.md`.
> - **Sürüm bilgisi:** repo varsayılan branch'i `1.7`; son branch `1.8` (Laravel 11, PHP `^8.2|^8.3`, `doctrine/dbal` kaldırıldı); son tag `v1.8.0`; `1.x` branch'i 2022-01'de kalmış. Doküman sitesi `1.x` bölümü **1.5**'i anlatır (repo `docs/` klasörü 1.6'ya kadar güncel), yani kod dokümandan ileride.
> - **1.8'in `1.x`'e göre kaynak farkları (21 dosya):** toplu silme artık kayıt başına `delete()` + `BreadDataDeleted` olayı çalıştırır (eski kod `$data->destroy($ids)` ile toplu siliyor ve olayı tek kez, `$data` değişkeni o sırada mesaj dizisine dönüştüğü için kayıt yerine **mesaj dizisiyle** tetikliyordu); `action()` var olmayan sınıfta açık hata fırlatır; `FileDeleted` event'i artık `$path`'i gerçekten saklar (eski kodda `$this->path;` atama yoktu); `BasePolicy::checkPermission` dataType bulunamazsa istisna fırlatır; medya listeleme Flysystem 3'e uyarlandı; ilişki seçeneklerine sıralama (`sort`) eklendi; modeller `HasFactory` kullanıyor.
> - **Doküman sitesi:** `llms.txt` sürümleri 1.0–1.6 ve `1.x` (38 sayfa); `1.x` bölümü yerel kopyayla birebir aynı, tek fark yeni `introduction.md`.

> Kapsam notu: Bu dosya BREAD'ı da içerir; ayrıca Media Manager, Settings, Plugin,
> Roles/Permissions, Database Manager, Compass, Menu, Widgets, i18n, Events,
> Console komutları ve FormField sistemlerinin tamamı incelendi.

---

## 1. Genel mimari

| Katman | Konum | Not |
|---|---|---|
| FormField handler'ları | `src/FormFields/` — 24 `.php` | 22 somut handler + `AbstractHandler` + `HandlerInterface`; her biri `AbstractHandler` extends, `protected $codename` + `createContent()` |
| Field view'ları | `resources/views/formfields/` — 23 view | Seçenek desteği **view'ların içinde**, handler'da değil. 23. view `relationship.blade.php` — handler'ı yok, ilişki alanları tarafından kullanılır |
| BREAD CRUD | `src/Http/Controllers/Controller.php` → `insertUpdateData()` | 324 satır, tek merkezi kaydetme |
| BREAD builder | `src/Http/Controllers/VoyagerBreadController.php` | 357 satır, `browse_bread` authorize |
| Şema sezgisi | `src/Database/Schema/SchemaManager.php` | 1.x branch'inde Doctrine DBAL; **1.8'de** `doctrine/dbal` `composer.json`'dan çıktı, Laravel şema builder'ı (`getTables`, `getForeignKeys`) kullanılıyor, `listTableNames()` için DBAL yalnızca yedek |
| Modeller | `src/Models/` (12 adet) | `DataType`, `DataRow`, `Setting`, `Menu`, `MenuItem`, `Permission`, `Role`, `Translation`, `Category`, `Post`, `Page`, `User` |
| Politikalar | `src/Policies/` (5 adet) | `BasePolicy` + model bazlı |
| Widget'lar | `src/Widgets/` (`BaseDimmer` + 3) | Arrilot widgets |
| Event'ler | `src/Events/` (24 adet) | BREAD/media/table/menu lifecycle |
| Komutlar | `src/Commands/` (4 adet) | Install, Admin, Controllers, MakeModel |
| Config | `publishable/config/voyager.php` | 11 grup + birkaç düz anahtar (bkz. §14) |

### Kritik mimari kararı
Handler'lar **son derece ince**:

```php
class TextHandler extends AbstractHandler
{
    protected $codename = 'text';
    public function createContent($row, $dataType, $dataTypeContent, $options)
    {
        return view('voyager::formfields.text', [...]);
    }
}
```

Yani mantık view'da, PHP tarafında değil. Bu, tardis'in `FormfieldManager` +
`resources/views/formfields/` yapısına benzer ama **tardis'te bu yol bypass
ediliyor** (aşağıda "tardis bulguları" bölümüne bak).

---

## 2. FormField sistemi — 22 somut tip

Dizin toplamı 24 `.php` dosyası: `AbstractHandler` (base), `HandlerInterface`
(sözleşme), `After/` ve şu **22 somut tip**:

```
Checkbox  CodeEditor  Color  Coordinates  Date  File  Hidden  Image
MarkdownEditor  MediaPicker  MultipleCheckbox  MultipleImages  Number
Password  RadioBtn  RichTextBox  SelectDropdown  SelectMultiple
TextArea  Text  Time  Timestamp
```

### 2.1 Voyager 1.x'e özgü tip detayları

| Tip | Voyager davranışı | Not |
|---|---|---|
| `Color` | `<input type="color">` + önizleme | **tardis'te yok** |
| `Coordinates` | `details->onChange`, `showAutocomplete`, `showLatLng`; Google Maps | **tardis'te yok**; `config('voyager.googlemaps')` kullanır |
| `Hidden` | gizli input | **tardis'te yok** |
| `RichTextBox` | WYSIWYG (Summernote/TinyMCE) | **tardis'te yok** (tardis'te `code_editor` + `markdown` var) |
| `MultipleCheckbox` | options → çoklu checkbox | tardis'te `checkbox` tek kutu |
| `SelectMultiple` | çoklu seçim | tardis `select` tek seçim |
| `Timestamp` | tarih+saat | tardis'te `datetime` |
| `Image` / `MultipleImages` / `MediaPicker` | ayrı tipler + `Resizable`/`Spatial` trait'leri | tardis'te tek generic `file` |
| `SelectDropdown` | **üç kaynaklı** option üretimi (aşağıda) | tardis'te sadece statik array |

### 2.2 SelectDropdown'ın üç kaynaklı option modeli (ÇOK ÖNEMLİ)

`resources/views/formfields/select_dropdown.blade.php`:

1. **Statik** — `$options->options` sözlüğü, `<optgroup label="Custom">` içinde
2. **Model list metodu** — `Str::camel($row->field) . 'List'` metodu modelde varsa çağrılır
3. **İlişki** — `->{camelField}()->getRelated()` ile ilişkili model; `options->relationship->where` verilmişse filtrelenir

Ayrıca:
- `_empty_` anahtarı `''` değerine dönüştürülür (boş seçim)
- `default` değeri yalnızca kayıt yoksa uygulanır
- `old()` ile validation hatasından sonra seçim korunur
- `(string)$selected_value == (string)$key` ile gevşek karşılaştırma (tip farkını tolere eder)

Bu, tardis'te eksik olan en değerli özellik: **options sabit array değil, ilişkiden
türetilebilen bir yapı**.

### 2.3 Handler → view → `details` sözleşmesi

Alan seçenekleri DataRow'un `details` JSON'unda tutulur. `required` ayrı bir
sütundur (`DataRow->required`), geri kalan her şey `details` içinde:

```php
$dataRow->required  = !empty($requestData['field_required_'.$field]);
$dataRow->type      = $requestData['field_input_type_'.$field];
$dataRow->details   = json_decode($requestData['field_details_'.$field]);
$dataRow->order     = intval($requestData['field_order_'.$field]);
```

---

## 3. BREAD — kaydetme akışı (tardis için referans)

### 3.1 `Controller::insertUpdateData()` — 324 satırlık merkezî metod

Sıralama, tardis'teki "transaction yok" bulgusunu doğruluyor: **Voyager'da da
transaction yok**, ilişkiler `save()` sonrası ayrı `sync()` edilir.

```php
// 1) Translations
$translations = is_bread_translatable($data) ? $data->prepareTranslations($request) : [];

// 2) Sadece tanımlı satırlar üzerinde dön (kullanılmayan alanları atla)
$request->attributes->add(['breadRows' => $rows->pluck('field')->toArray()]);

// 3) Alan bazlı döngü
foreach ($rows as $row) {
    // checkbox istisnası: işaretlenmemiş checkbox hiç gönderilmez
    if (!$request->hasFile($row->field) && !$request->has($row->field) && $row->type !== 'checkbox') {
        continue;   // belongsToMany hariç
    }
    $content = $this->getContentBasedOnType($request, $slug, $row, $row->details);
    $data->{$row->field} = $content;
}

$data->save();
$data->saveTranslations($translations);   // çeviriler kayıt SONRASI

// 4) belongsToMany — toplu, save() sonrası
foreach ($multi_select as $sync_data) {
    $data->belongsToMany(...)->sync($sync_data['content']);
}
```

**Kayıtta kritik davranışlar:**

- **Boş dosya = mevcut dosyayı koru.** `is_null($content)` durumunda:
  - `image`, `multiple_images`, `file` → `$content = $data->{$row->field}` (mevcut korunur)
  - `file` → korunacak değer yoksa `json_encode([])`
  - `password` → mevcut korunur (boş göndermek şifreyi silmez)
- **`belongsTo` otomatik atlanır** — FK zaten model ilişkisinden gelir
- **checkbox özel** — request'te yoksa "silinmiş" sayılmaz, mevcut değer korunur
- **çoklu dosya birleştirme** — `ex_images` (mevcut) + yeni upload `array_merge` edilir
- **`belongsToMany` içeriği `sync()` ile** — pivot key'leri `details`'ten okunur
  (`pivot_table`, `foreign_pivot_key`, `related_pivot_key`, `parent_key`)
- **media_picker klasör yeniden adlandırma** — `session` içindeki `uuid`, kayıt id ile değiştirilir

### 3.2 Validation — tam kural seti (tardis'in düşürdüğü kısım)

`Controller.php:197-255` — kurallar **tümüyle korunur**, `required` dışında da:

```php
$rules = [];
foreach ($rows as $field) {
    $fieldRules = ...;                       // DataRow->details->validation
    // dizi ise dizi olarak, değilse '|' ile böl
    $rules[$fieldName] = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);

    // action bazlı kurallar (add / edit ayrı kural seti!)
    $action_rules = $field->details->validation->edit->rule;   // edit sırasında
    $rules[$fieldName] = array_merge($rules[$fieldName], ...);
    $action_rules = $field->details->validation->add->rule;    // add sırasında
    $rules[$fieldName] = array_merge($rules[$fieldName], ...);

    // alan adına özel işleme
    foreach ($rules[$fieldName] as &$fieldRule) { ... }
}
return Validator::make($data, $rules, $messages, $customAttributes);
```

**Kritik tasarım:** `details->validation->add->rule` ve `details->validation->edit->rule`
**ayrı kural setleri**. Aynı alan create'de `required`, edit'te `sometimes` olabilir.

**tardis'te testle kanıtlanan hata:** `unique`, `email`, `max` kurallarının
**hiçbiri** uygulanmıyor; duplicate slug sessizce kaydediliyor.

### 3.3 `DataType::updateDataType()` — BREAD builder kaydetme

- `DB::beginTransaction()` / `commit()` / `rollBack()` — **builder'da VAR**
- `generate_permissions` ve `server_side` eksikse `0`'a çevrilir
- Her alan için 5 ayrı görünürlük bayrağı: `browse/read/edit/add/delete`
- `required` ayrı kolon, `details` JSON, `order` int
- **Temizlik:** `$this->rows()->whereNotIn('field', $fields)->delete()` — silinmiş
  alanların DataRow'ları temizlenir (kodda `TODO`: alan adı değişikliği
  yakalanamıyor, kullanıcı uyarılmalı)
- `generate_permissions` ise `Permission::generateFor($this->name)` çağrılır
- İlişkiler `getRelationships()` ile ayrıca işlenir ve `details` JSON'una merge edilir

**tardis karşılaştırması:** tardis'in BREAD'i JSON dosyası üzerinde çalışıyor
(veritabanı tablosu yok), dolayısıyla `generate_permissions` senkronizasyonu ve
`whereNotIn` temizliği gibi mekanizmaların karşılığı yok.

### 3.4 İlişki yönetimi — `getRelationships()`

Desteklenen tipler: `hasOne`, `hasMany`, `belongsTo`, `belongsToMany`
(+ `taggable` bayrağı).

`details` içine merge edilen anahtarlar:
```
model, table, type, column, key, label, pivot_table, pivot, taggable
```
`hasOne`/`hasMany` için `relationship_column_*`, `belongsTo` için
`relationship_column_belongs_to_*` ayrı anahtardan okunur.

### 3.5 Sıralama (ordering)

- `DataType` üzerinde: `order_column`, `order_display_column`, `order_direction`,
  `default_search_key` — hepsi accessor/mutator ile tanımlı
- `DataRow::isCurrentSortField($orderBy)` — aktif sütun kontrolü
- `DataRow::sortByUrl($orderBy, $sortOrder)` — sıralama bağlantısı üretir
- `DataRow::rowBefore()` — satır sırası (BREAD builder'da sürükle-bırak)

---

## 4. İzin (Permissions / Policies) — gerçek uygulama

Bu, tardis'in **en kritik eksikliği**: tardis izin üretiyor ama kontrol etmiyor.

### 4.1 Üretim
```php
Permission::generateFor($table_name)   // 5 izin
  browse_{table}  read_{table}  edit_{table}  add_{table}  delete_{table}
Permission::removeFrom($table_name)   // toplu silme
```
`Role` ↔ `Permission` ve `Role` ↔ `User` `belongsToMany`.

### 4.2 Tüketim — iki katmanlı
1. **Controller seviyesi:** her BREAD aksiyonunda `$this->authorize('browse_bread')`
   (`VoyagerBreadController.php` 6 noktada, `VoyagerMediaController` upload/crop'ta
   `browse_media`)
2. **Model seviyesi:** `BasePolicy` — `__call` magic ile her izin adı yakalanır,
   `$user->hasPermission($action.'_'.$dataType->name)` kontrolü yapılır
   - `delete()` — soft-delete kontrolü ile
   - `restore()` — silinmişse `delete` izniyle

**tardis farkı:** `Permission::forBread()` var, `hasPermission()` tüketimi yok.

---

## 5. Media Manager

`src/Http/Controllers/VoyagerMediaController.php` — 8 public action (+ `__construct`):

| Metot | İşlev |
|---|---|
| `index()` | panel girişi |
| `files()` | **AJAX dosya listeleme** (klasör ağacı) |
| `new_folder()` | klasör oluşturma |
| `delete()` | dosya/klasör silme |
| `move()` | taşıma |
| `rename()` | yeniden adlandırma |
| `upload()` | dosya yükleme |
| `crop()` | görsel kırpma |

### 5.1 Upload davranışları
- `$this->authorize('browse_media')` — yetki kontrolü
- **MIME doğrulama:** `config('voyager.media.allowed_mimetypes')`, `*` ise kapalı
- **Çakışma çözümü:** `while (file exists) $name = get_file_name($name)`
- **Şablonlu dosya adı:**
  - `{uid}` → `Auth::user()->getKey()`
  - `{date:FORMAT}` → `Carbon::now()->format(...)`
  - `{random:N}` → `Str::random(N)`
- `Storage::disk($this->filesystem)` — disk yapılandırması

### 5.2 Crop
- `createMode=true` → `cropped_{time}` ekli **yeni** dosya
- `createMode=false` → **orijinalin üzerine yaz** (intervention/image `Image::make()->crop()`)

### 5.3 Media ile BREAD entegrasyonu
- `ImageHandler`, `MultipleImagesHandler`, `MediaPickerHandler` ayrı tipler
- `media_picker` sonrası **session'daki uuid klasör adı kayıt id ile değiştirilir**
  (`:uuid` → kayıt id) — tutarlı medya klasörü için
- Trait'ler: `Resizable` (boyut varyantları), `Spatial` (en/boy)

**tardis farkı:** tardis'te medya yönetimi yok; `file` alanı diske yazıp yol
döndürüyor. Medya paneli, klasör ağacı, kırpma, MIME politikası eksik.

---

## 6. Settings sistemi

`VoyagerSettingsController` — 7 metod: `index`, `store`, `update`, `delete`,
`move_up`, `move_down`, `delete_value`.

### 6.1 `settings` tablosu şeması
```php
id, key(unique), display_name, value(text, nullable), details(json, nullable),
type, order(default 1), group(nullable)
```
- `timestamps = false`
- `$guarded = []`
- `dispatchesEvents`: `updating` → `SettingUpdated` event

### 6.2 Tasarım deseni
- Ayar = **key/value satırı**, `group` ile sekmelere ayrılır
- `order` + `move_up`/`move_down` ile **elle sıralama**
- `type` alanı ile render tipi belirlenir → Ayar tipleri BREAD ile aynı evrende
- `details` ile alan bazlı seçenekler (tıpkı DataRow gibi)
- `value` nullable (2018'de nullable yapıldı)

**tardis farkı:** tardis'te settings sistemi yok (doğrulanacak — ayrı kontrol edilmeli).

---

## 7. Plugin sistemi

**1.x çekirdeğinde plugin dosyası yok** (`find -iname "*plugin*"` boş). Plugin mimarisi:

- `composer.json` `extra.laravel.providers` → otomatik keşif (paket tabanlı)
- ~~Resmi plugin'ler ayrı paketler: `tcg/voyager-*-plugin`~~ — **yanlıştı**: bu paket adları Packagist'te yok (2026-10-03). Bkz. `03-voyager-plugin-sistemi.md` §1.3
- `VoyagerEventServiceProvider` — event dinleyicileri
- `VoyagerDummyServiceProvider` — dummy seeder'lar ve dummy içerik (`dummy_seeders`, `dummy_content` publish tag'leri)
- `FormFieldsRegistered` event'i → **üçüncü parti formfield handler'ı bu event ile kaydedilir**
- `VoyagerDummyServiceProvider` içinde `Voyager::formFields()` benzeri kayıt

Eski (doğrulanamayan) plugin listesi — **kaynakta veya dokümanda geçmiyor**, yalnızca `larapack/voyager-hooks` gerçek bir pakettir ve 1.5'te kaldırıldı: `voyager-hooks`, `voyager-mail`, `voyager-notification`, `voyager-file-manager`,
`voyager-json-editor`, `voyager-hello-dolly` (görsel/örnek plugin'ler).

Detaylı liste ve yaşam döngüsü için ayrı araştırma dosyası gerekli (aşağıda link).

---

## 8. Database Manager

`VoyagerDatabaseController` — 9 metod:

| Metot | İşlev |
|---|---|
| `index()` | tablo listesi |
| `create()` / `store()` | tablo oluşturma |
| `edit()` / `update()` | tablo düzenleme |
| `destroy()` | tablo silme |
| `show()` | tablo içeriği |
| `cleanOldAndCreateNew()` | rename sırasında veri taşıma |
| `reorder_column()` | **sütun sıralaması** |

`config('voyager.database.tables.hidden')` ile gizli tablolar:
`migrations, data_rows, data_types, menu_items, password_resets, permission_role, personal_access_tokens, settings` (`personal_access_tokens` 1.8'de eklendi)

**tardis farkı:** tardis'te database manager yok.

---

## 9. Menu sistemi

`src/Models/Menu.php` + `src/Models/MenuItem.php` + `src/Http/Controllers/VoyagerMenuController.php`

### 9.1 `MenuItem` modeli

```php
class MenuItem extends Model
{
    use Translatable;
    protected $translatorMethods = ['link' => 'translatorLink'];
    protected $table = 'menu_items';
    protected $guarded = [];
    protected $translatable = ['title'];
```

- **`title` çevrilebilir**, `link` ise **çeviriye bağlı** (`translatorLink`) —
  yani menü etiketi dile göre değişir, hedefi çeviri kaydından gelir.
- `children()` → `hasMany(MenuItem, 'parent_id')->with('children')` → **öz-yinelemeli
  (self-referential) ağaç**, sınırsız derinlik.
- `highestOrderMenuItem($parent)` → sıralama için en yüksek `order` bulunur.

### 9.2 Üç link tipi — `prepareLink()`

```php
protected function prepareLink($absolute, $route, $parameters, $url)
{
    if (is_string($parameters))  $parameters = json_decode($parameters, true);
    elseif (is_object($parameters)) $parameters = json_decode(json_encode($parameters), true);

    if (!is_null($route)) {
        if (!Route::has($route)) { return '#'; }     // ← olmayan route güvenli #'a düşer
        return route($route, $parameters, $absolute);
    }
    return $absolute ? url($url) : $url;
}
```

1. **`route`** — Laravel adlı route + JSON `parameters`; route yoksa **`#`**
2. **`url`** — mutlak (`url()`) veya göreli
3. `parameters` mutatörü string/array/object → **her zaman diziye normalize eder**

### 9.3 `Menu::display()` — önbellek

```php
$menu = \Cache::remember('voyager_menu_'.$menuName, Carbon::now()->addDays(30),
    fn() => ...->with(['parent_items.children' => ...]));
```

- Anahtar: `voyager_menu_{name}`, **30 gün** önbellek
- `MenuItem::boot()`: `created` / `saved` / `deleted` → `$model->menu->removeMenuFromCache()`
- `removeMenuFromCache()` → `\Cache::forget('voyager_menu_'.$this->name)`
- Yani **menü ağacı önbellekli**, her yazımda geçersiz kılınıyor.

### 9.4 ⭐ `processItems()` — aktif durum + **izin bazlı sürme**

```php
// 1) Aktif durum çözümü
if ($item->href == url()->current())                      $item->active = true;   // tam eşleşme
elseif (Str::startsWith(url()->current(), Str::finish($item->href,'/'))) $item->active = true; // alt yol
if (($item->href == url('') || $item->href == route('voyager.dashboard')) && $item->children->count() > 0)
    $item->active = false;                                // dashboard hariç tut

// 2) Çocuklardan aktiflik yayılımı (yukarı doğru)
if ($item->children->count() > 0) {
    $item->setRelation('children', static::processItems($item->children));
    if (!$item->children->where('active', true)->isEmpty()) $item->active = true;
}

// 3) ⭐ İZİN BAZLI SÜRME
$items = $items->filter(fn($item) =>
    !$item->children->isEmpty() || Auth::user()->can('browse', $item)   // ← yetkisiz menü gizlenir
)->filter(fn($item) => !($item->url == '' && $item->route == '' && $item->children->count() == 0));
```

**En değerli çıkarım:** Voyager, **menü öğelerini kullanıcının BREAD iznine göre
otomatik gizliyor**. `Auth::user()->can('browse', $item)` → `BasePolicy::__call` →
`checkPermission()`. Böylece yetkisiz kullanıcı panelde göremediği BREAD'e
tıklayamıyor çünkü **bağlantı hiç render edilmiyor**.

→ **tardis'te bu tamamen yok:** izin üretiliyor ama ne menü gizleme ne de
aksiyon kontrolü var. Bu, `05-tardis-bulgulari.md` §P0-2'nin "keşif" ayağıdır —
`BasePolicy` zaten tüketiliyordu ama **BREAD sayfalarında** tüketilmiyor.

### 9.5 `VoyagerMenuController` — menü builder

| Metot | İşlev |
|---|---|
| `builder($id)` | Menü builder arayüzü |
| `add_item(Request)` | Öğe ekle |
| `update_item(Request)` | Öğe güncelle |
| `order_item(Request)` | **Sürükle-bırak sıralama** |
| `delete_menu($menu, $id)` | Menü sil |

`config('voyager.bread.add_menu_item')` → BREAD eklendiğinde menü öğesi **otomatik**
oluşur (`default_menu: admin`). Yani izin üretimi ile menü üretimi **aynı yerde**
tetiklenir — tardis'te bu eşleşme yok (izin `TardisAuthorizationPlugin`'te üretiliyor,
menü üretimi hiç yok).

Event'ler: `MenuDisplay`, `Routing`, `RoutingAdmin`, `RoutingAdminAfter`, `RoutingAfter`.

---

## 10. Compass (dashboard)

`VoyagerCompassController`; `src/Actions/`, `src/Alert/Components/`
(`AbstractComponent`, `ButtonComponent`, `TextComponent`, `TitleComponent`),
`AlertsMessages` trait'i. `Voyager::` facade + `Alert` bileşen sistemi.
Widget'lar: `BaseDimmer` (Arrilot `AbstractWidget`), `PostDimmer`, `PageDimmer`, `UserDimmer`.
`config('voyager.dashboard')` ile panel ayarları.

---

## 11. i18n / Translator

- `src/Translator.php` + `src/Translator/Collection.php`
- `src/Helpers/helpersi18n.php`, `helperTranslations.php`
- `publishable/lang/` — **34 dil** (1.8: al, am, ar, az, bg, ca, cs, de, el, en, es, fa, fi, fr, gl, id, it, ja, km, ku, mm, my, nl, pl, pt, pt_br, ro, ru, sv, tr, uk, vi, zh_CN, zh_TW; `az`, `km`, `my` sonradan eklendi)
- `config('voyager.multilingual')`: `enabled`, `default`, `locales[]`
- BREAD alanlarının `display_name`'i çevirilebilir
  (`is_bread_translatable($dataRow)`, `prepareTranslationsFromArray`)
- **Çeviri disiplini:** `required`, `unique`, `email`, `max` dahil **tüm kurallar**
  `Voyager` ana çeviri dosyasından gelir, hard-coded İngilizce mesaj yok

**tardis farkı:** tardis'te alan bazlı çeviri var (bizim bulduğumuz `translatable`
alanı), ama Voyager'ın `display_name` çevirisi ve ayrı add/edit kural seti
disiplini yok.

---

## 12. Event kataloğu (24 adet)

```
BREAD yaşam döngüsü:
  BreadAdded, BreadUpdated, BreadChanged, BreadDeleted
  BreadDataAdded, BreadDataUpdated, BreadDataChanged, BreadDataDeleted, BreadDataRestored
Medya:
  MediaFileAdded, FileDeleted, BreadImagesDeleted
Tablo:
  TableAdded, TableUpdated, TableChanged, TableDeleted
Menü / routing:
  MenuDisplay, Routing, RoutingAdmin, RoutingAdminAfter, RoutingAfter
FormField:
  FormFieldsRegistered     ← üçüncü parti handler kaydı
Ayar:
  SettingUpdated
UI:
  AlertsCollection
```

---

## 13. Console komutları

| Komut | Sınıf | İşlev |
|---|---|---|
| `voyager:install` | `InstallCommand` | kurulum, migration, publish, admin oluşturma |
| `voyager:admin` | `AdminCommand` | admin kullanıcı oluşturma/yükseltme |
| `voyager:controllers` | `ControllersCommand` | controller üretimi |
| `voyager:make:model` | `MakeModelCommand` | BREAD ile eşleşen model üretimi |

---

## 14. Config bölümleri (11 grup)

```
user          → VoyagerUser trait, model
controllers   → controller override noktaları
models        → DataType, DataRow, Menu, Setting, Permission, Role, Category...
storage       → medya disk'i, thumbnail ayarları
database      → hidden tablolar, autoload_migrations
multilingual  → enabled, default, locales[]
dashboard     → panel widget'ları
bread         → add_menu_item, default_menu, add_permission, default_role
googlemaps    → Coordinates field için API key, merkez, zoom
settings      → ayar sayfası seçenekleri
media         → allowed_mimetypes (yorum satırı; varsayılan "*")
```

Ek düz anahtarlar: `hidden_files`, `primary_color`, `show_dev_tips`, `additional_css`,
`additional_js`, `compass_in_production`.

---

## 15. tardis karşlaştırması — Voyager 1.x'ten alınacak dersler

| Konu | Voyager 1.x | tardis durumu | Öncelik |
|---|---|---|---|
| **Validation kuralları** | Tümü korunur, `add`/`edit` ayrı kural seti | `required` dışı **hepsi düşüyor** (testle kanıtlandı) | **P0** |
| **İzin kontrolü** | `authorize()` + `BasePolicy` her yerde | Üretiliyor, **hiç kontrol edilmiyor** | **P0** |
| **Boş dosya koruması** | image/file/password mevcut değeri korur | `file` → `''` yazıyor (bilerek, ama edit'te farklı davranış) | P1 |
| **Checkbox yokluk semantiği** | `type !== 'checkbox'` istisnası | Yok → işaretlenmemiş checkbox kaybolabilir | **P0** |
| **Field handler/view ayrımı** | Handler ince, mantık view'da | Tersine: `Formfield::render()` **hiç çağrılmıyor** | P1 |
| **Options kaynağı** | statik + `{field}List()` + ilişki + `where` | Sadece statik array | P1 |
| **Transaction** | Builder'da VAR (`beginTransaction`/`rollBack`) | CRUD'da **YOK** | P1 |
| **Eksik alan silme** | `whereNotIn('field', $fields)->delete()` | Yok | P2 |
| **Media Manager** | Tam panel + crop + MIME politikası + `{uid}`/`{date}`/`{random}` şablonları | **Yok** | P2 |
| **Settings** | key/value + group + order + event | **Yok** | P2 |
| **Database Manager** | tablo/sütun yönetimi | **Yok** | P3 |
| **i18n** | 34 dil, kural mesajları çevrilir | Kısmi | P2 |
| **Field tipleri** | 24 tip (color, coordinates, rich_text_box, hidden, multi-select eksikleri) | 19 tip | P2 |

---

## 15b. Güncel Tardis durumu (2026-10-03)

§15'teki tablo 2026-09-29 anlık görüntüsüdür. O günden beri:

| Konu | Şimdi |
|---|---|
| Validation kuralları | ✅ `FieldValidationRules` (liste olarak, `\|` içeren regex dahil) |
| İzin kontrolü | 🟡 Sayfalar `BreadAuthorization` ile kontrol eder; plugin yoksa **fail-open** (`docs/notes.md` B9) |
| Transaction | ✅ create/edit `DB::transaction` içinde |
| Media Manager | ✅ var (`MediaManager`, media-browser); yükleme uzantı listesiyle sınırlı |
| Settings | ✅ var (`SettingsManager`, JSON, group/validation) |
| Database Manager | ✅ var (`/admin/database`) |
| Field tipleri | 19 tip (değişmedi); Color/Coordinates/Hidden/RichTextBox hâlâ yok |
| Options kaynağı | static options + ilişki (`searchOptions`); `{field}List()` benzeri yok |

---

## 16. Kaynak dosya haritası (Voyager 1.x)

```
src/FormFields/*.php                      24 field handler
resources/views/formfields/*.blade.php    23 field view
src/Http/Controllers/Controller.php       insertUpdateData + validation (L45-255)
src/Http/Controllers/VoyagerBreadController.php   BREAD builder + authorize
src/Http/Controllers/VoyagerMediaController.php   Media Manager
src/Http/Controllers/VoyagerSettingsController.php
src/Http/Controllers/VoyagerDatabaseController.php
src/Models/DataType.php                   updateDataType, getRelationships, fields
src/Models/DataRow.php                     sortByUrl, isCurrentSortField, rowBefore
src/Models/Permission.php                 generateFor/removeFrom
src/Models/Setting.php
src/Policies/BasePolicy.php               __call + checkPermission
src/Database/Schema/SchemaManager.php     şema keşfi
src/Commands/*.php                        4 komut
src/Events/*.php                          24 event
src/Widgets/BaseDimmer.php
publishable/config/voyager.php            8 config bölümü
publishable/lang/                         34 dil
docs/                                     yerel doküman (bread/, core-concepts/, ...)
```

---

*Sonraki dosyalar: `02-voyager-2x.md`, `03-voyager-plugin-sistemi.md`,
`04-resmi-dokumanlar.md`, `05-tardis-bulgulari-ve-fix-oncelikleri.md`*
