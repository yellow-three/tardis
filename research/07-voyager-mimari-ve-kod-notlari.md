# Voyager 1.7 ve 2.x — Klasör Yapısı, Kodlama Biçimi ve Mantık Notları

Tarih: 2026-10-03
Kaynak: `research/voyager-1.7/` (`src/` 4777 satırlık controller dahil ~170 PHP dosyası, 34 test dosyası) ve `research/voyager-2.x/` (`src/` ~80 PHP dosyası, 27 test dosyası). Görünüm notları: `06-voyager-gorunum-ve-blade-notlari.md`. Burada **mimari, klasör, kod desenleri ve çalışma mantığı** var — kopyalanacak bir şey yok, "en iyisini al, geliştir".

---

## 1. Klasör yapısı

| | **Voyager 1.7** (`TCG\Voyager`) | **Voyager 2.x** (`Voyager\Admin`) | **Tardis** (`Tardis`) |
|---|---|---|---|
| Üst düzey | `src/{Actions, Alert, Commands, Contracts, Database, Events, Facades, FormFields, Helpers, Http, Listeners, Models, Policies, Providers, Traits, Translator, Widgets}` | `src/{Classes, Commands, Contracts, Events, Exceptions, Facades, Formfields, Http, Manager, Plugins, Policies, Rules, Traits}` | `src/{Auth, Bread, Classes, Commands, Contracts, Database, Events, Facades, Formfields, Http, Manager, Models, Plugins, Policies, Theme}` |
| En büyük parça | `Database/` **67 dosya** (Doctrine tip eşlemeleri: Common/Mysql/Postgresql/Sqlite) — bakım yükü, 1.8'de DBAL'dan kurtulmaya çalışılmış | `Contracts/` **17 dosya** (plugin sözleşmeleri) ve `Manager/Breads.php` 537 satır | `Manager/` + `Bread/` |
| Controller | `Http/Controllers/` — **`VoyagerBaseController` 1022 satır** (tüm BREAD aksiyonları) + `Controller.php` 324 (kaydetme/doğrulama) + `ContentTypes/` (tip başına kayıt dönüştürücü) + `Traits/BreadRelationshipParser` | `BreadController` 489 + `Controller` 129; sorgu ve kaydetme **trait'lere** ayrılmış: `Traits/Bread/Browsable` (225), `Saveable` (49) | Controller yok; MFC sayfaları (`index.php`, `create.php`, `edit.php`) sorguyu ve kaydetmeyi içinde taşıyor |
| Model | **BREAD tanımı veritabanında:** `DataType` + `DataRow` (details JSON) + `Menu/MenuItem/Setting/Role/Permission/Translation…` — 12 model; modeller config ile değiştirilebilir | **Model yok:** `Classes/Bread` (JSON'dan), `Layout`, `Formfield`, `Column`, `Widget`, `MenuItem`, `Action` düz sınıflar | `Bread/BreadDefinition` (JSON), `Models/{Permission, Role, Media, ActivityLog}` |
| Kayıt/Yönetici sınıf | `Voyager.php` (360 satır, **god facade**: `formField`, `actions`, `dimmers`, `setting`, `image`, `alerts`, `translatable`, `model/useModel`, `onLoadingView`…) | `Voyager.php` 139 satır + **facade trait'leri** (`Traits/Facade/{Assets, Auth, Database, Filesystem, Localization, Messages, Routes, Widgets}`) — ilgi alanına göre bölünmüş | `Tardis.php` (62 satır) + `Manager/*` (menu, plugin, widget, settings, formfield, bread, media, asset, theme) |
| Yardımcılar | `Helpers/helpers.php` (global `setting()`, `menu()`, `voyager_asset()`…) | Facade üzerinden | Facade (`Tardis::…`); global helper yok |
| Test | 34 dosya, browser-kit testing (`LoginTest`, `RolesTest`, `MenuTest`, `PermissionTest`, `ViewEventTest`, `AlertTest`…), `Models/`, `Unit/Actions` | 27 dosya: `Unit/` (Bread, Settings, Plugins, Locale, Translation, Actions), `Feature/` (Auth, BreadManager, Dashboard, Install, Menu, PluginCommand), `Browser/` (Dusk: BreadBuilder, Assets) | 51 Pest dosyası (Feature/Unit/Integration) |

**Notlar**
- V1'in dosya sayısının çoğu şema soyutlaması (`Database/`) — Tardis Laravel şema builder'ını kullanıyor; **almamak** doğru.
- V1'de "tek dev controller" yaklaşımı bakımı zorlaştırmış (1022 satır). V2 sorguyu (`Browsable`) ve kaydetmeyi (`Saveable`) ayrı trait'lere böldü ama yine controller'a bağlı. Tardis'in bugünkü sorunu aynı yönde: **sorgu ve kaydetme mantığı Livewire sayfasının içinde** (`bread/index/index.php` `getRowsProperty`, `create/edit` içinde kopya `requiredColumns`).
- V2'de **facade trait'lerle bölünmüş** (Assets, Auth, Database, Filesystem, Localization, Messages, Routes, Widgets); V1'de tek dev sınıf. Tardis `Tardis.php`'si ince; sorumlulukları `Manager`'lara dağıtılmış (V2'ye yakın, iyi).

---

## 2. Servis sağlayıcı ve başlatma (`VoyagerServiceProvider`)

**V1.7 (365 satır):**
- `register()`: alt provider'lar (`EventServiceProvider`, `ImageServiceProvider`, `DummyServiceProvider`), singleton `voyager` ve `VoyagerGuard`, alert bileşenleri, formfield'lar (config ile genişletilebilir), config'ler, konsol komutları, publish grupları.
- `boot()`: view'lar, middleware alias (`admin.user`), çeviriler, migration yolu (config ile otomatik yükleme), **view composer** (`voyager::*` view'larına ortak veri), alert toplama olayı, `loadAuth()` (**politikalar DB'deki `DataType` satırlarından dinamik kaydedilir** — BREAD başına `policy_name`; her izin için `Gate::define`), storage symlink eksikse **otomatik uyarı + düzeltme**.
- Global yardımcılar (`Helpers/helpers.php`) `loadHelpers()` ile yüklenir.

**V2.x (474 satır):**
- `boot()`: `Gate::before` (üst düzey yetki kancası), `voyager.page` olayı (middleware'de tetiklenir; eklentiler sayfa isteğine bağlanır), **rotalar BREAD koleksiyonundan üretilir**, plugin formfield'ları yüklenir, **BREAD başına policy** kaydı, menü öğeleri (builder + her BREAD), aksiyonlar (`registerActions`, `registerBulkActions` — akıcı API: `->route(fn)->displayOnBread(fn)`).
- `register()`: `MenuManager`, `SettingManager`, `BreadManager`, `PluginManager` **singleton**.

**Tardis'e dersler**
1. **Rota üretimi BREAD başına (V1 `Route::resource($slug, …)` ve V2 `registerBreadRoutes`).** Her iki Voyager de wildcard kullanmıyor; her slug için ayrı rota grubu + rota adı (`voyager.posts.browse`) kuruyor, **rotaya BREAD nesnesini default olarak bağlıyor** (V2: `'bread' => $bread`). Tardis'in `/{slug}` wildcard'ı B14'ün (plugin rotası yenilmesi) ve "BREAD'e özel controller/bileşen" eksikliğinin kökü. → *Plana girer:* BREAD rotaları tanımdan üretilir (adlandırılmış, per-BREAD middleware ve **per-BREAD Livewire bileşeni override**), wildcard kalkar.
2. **BREAD başına `controller` ve `policy` alanı** (ikisinde de var). Tardis'te karşılığı: tanımda `component` (browse/read/edit/add için ayrı override edilebilir Livewire bileşeni) ve `policy`/ability önekini seçme.
3. **Tanımdan dinamik policy/izin kaydı** (V1 `loadAuth`, V2 `registerBreadPolicies`). Tardis'te izinler `BreadManager::save()` ile üretiliyor (yapıldı); **kayıt anında Gate tanımı** yok → Laravel `Gate`/`@can` ile uyum isteniyorsa eklenmeli.
4. **`Gate::before` kancası** (V2) — süper-admin bypass için Tardis zaten plugin içinde; Gate'e bağlamak host policy'leriyle birleşmeyi kolaylaştırır.
5. **Başlatma olayları:** `Routing`, `RoutingAdmin`, `RoutingAdminAfter`, `RoutingAfter` (V1) ve `voyager.page` (V2) — eklentiler rota/sayfa yaşam döngüsüne bağlanır. Tardis'te plugin `Routes` contract'ı var ama bağlı değil (Faz 0).
6. **Eksik symlink gibi ortam sorunlarını panelde uyarı olarak göstermek** (V1 alert sistemi) → `tardis:doctor` (Faz 8) ile birleşir.

---

## 3. BREAD'in veri modeli ve yaşam döngüsü

| | V1.7 | V2.x | Tardis |
|---|---|---|---|
| Tanım | DB: `data_types` (+ `details` JSON, `server_side`, `generate_permissions`, `order_*`, `scope`, `policy_name`, `controller`) ve `data_rows` (alan satırı: `field`, `type`, `display_name`, `required`, `browse/read/edit/add/delete`, `details` JSON, `order`) | JSON dosyası: `table`, çevrilebilir `slug/name_singular/name_plural`, `icon`, `model`, `controller`, `policy`, `scope`, `global_search_field`, `badge/color`, **`layouts[]`**, `layout_map`, `relationships[]` | JSON: `slug`, `model`, `name(s)`, `fields[]`, `layout{browse,edit,read,field_order}`, `relationships`, `order_*`, `search_key`, `soft_delete` |
| Sahip olunan fikirler | `DataType::browseRows/readRows/editRows/addRows` (aynı satır kümesinden **bağlama göre görünür alanlar**) | `Layout::searchableFormfields()`, `getFormfieldsByColumnType('relationship')` (layout alanları üzerinde sorgu yöntemleri) | `BreadDefinition` düz veri; alan listesi üzerinde yardımcı yöntem az |
| Model keşfi | `SchemaManager` (Doctrine) + `ModelReflector`-benzeri `Reflection.php` | `Breads::getModelScopes/ComputedProperties/Relationships` (Reflection) | `ModelReflector` (analyze, fields, accessors, scopes, relationships) |
| Çeviri | `Translatable` trait + `translations` tablosu | JSON kolon; `Translatable` trait'i BREAD sınıfına da uygulanmış (slug/ad çevrilebilir) | JSON kolon (alan düzeyi) |
| Yan etkiler | `BreadAdded` olayı → **listener'lar** `AddBreadMenuItem`, `AddBreadPermission` (silinince `DeleteBreadMenuItem`); `SettingUpdated` → `ClearCachedSettingValue` | `registerBreadMenuItems`, policy kaydı provider'da | `BreadManager::save()` içinde doğrudan izin üretimi (yapıldı) |

**Dersler**
- **Yan etkiler olay + listener ile** (V1: `BreadAdded/Updated/Deleted` → izin, menü, cache). Tardis'te izin üretimi `BreadManager::save()` içine gömüldü; bunun yerine **`BreadSaved/BreadDeleted` olayları** + listener'lar (izin, menü, ileride webhook/audit) hem test edilebilir hem genişletilebilir. *Plana girer (Faz 0/3b).*
- **Alan listesi üzerinde sorgu yöntemleri** (V2 `Layout`): `BreadDefinition`/layout nesnesi `visibleFor('browse')`, `searchable()`, `orderable()`, `relationships()` sunmalı; sayfalar dizi süzmesin.
- **Yeniden adlandırma/silinen alan temizliği:** V1 `updateDataType` içinde `whereNotIn('field', …)->delete()` ve transaction; Tardis JSON'da alan silinince layout'larda kalıntı kalabilir → builder kaydederken **layout referans doğrulaması**.
- **BREAD başına `scope`** (iki sürümde de): liste sorgusuna uygulanan model scope'u (ör. yalnız `published`). Tardis'te yok → liste fazına (Faz 3).

---

## 4. Liste sorgusu ve kaydetme akışı (mantık)

**V2 `Browsable` trait'i — liste sorgusunun parçaları** (Tardis'te `index.php` içindeki tek `getRowsProperty` yerine alınacak ayrım):
1. `loadSoftDeletesQuery` — `show/hide/only`
2. `globalSearchQuery` — layout'taki **searchable** alanlar üzerinde `OR` ile ara; ilişki alanı için `orWhereHas`
3. `columnSearchQuery` — sütun filtreleri; **tip başına arama davranışı** formfield'dan (`queryColumn`)
4. `applyCustomFilter` / `applyCustomScope` — **adlandırılmış filtre** (sütun+operatör+değer) ve **model scope** olarak filtre; uyarılar `warnings[]` listesine yazılır (bozuk filtre sayfayı kırmaz)
5. `orderQuery` — çevrilebilir sütun için locale'e göre JSON sıralama
6. `eagerLoadRelationships` — layout'taki ilişki alanları için `with()`
7. `transformResults` — her satırı formfield'ın `browse()` çıktısına çevirir

**V2 `Saveable`:** her formfield için `update($model,$value,$old)` / `store($value)`; çevrilebilir alanda **her locale ayrı** formfield'dan geçirilir ve JSON'a döner; kolon tipi `column` / `computed` ayrımı (computed için `setXAttribute` varsa yazılır).

**V1 `Controller::insertUpdateData` (324 satır) ve `ContentTypes/*`:** her alan tipinin **sunucu tarafı dönüştürücüsü ayrı sınıf** (`Image`, `MultipleImage`, `File`, `Password`, `Checkbox`, `SelectMultiple`, `Coordinates`, `Timestamp`, `Relationship`, `Text`) — `BaseType(request, slug, row, options)->handle()`; controller `getContentBasedOnType` ile yönlendirir. Dosya yükleme, parola hash, boş kalınca mevcut değeri koruma mantığı **tipin içinde**, controller'da değil.

**V1 `VoyagerBaseController` aksiyon seti (1022 satır):** `index`, `show`, `edit`, `update`, `create`, `store`, `destroy` (+ toplu), `restore`, `order`, `update_order`, `action` (özel aksiyon sınıfı), `relation` (select2 ajax), `remove_media`; her biri **olay** tetikler (`BreadDataAdded/Updated/Deleted/Restored`) ve `authorize()` çağırır.

**Tardis'e dersler**
1. **Sorgu servisi:** `Tardis\Bread\BreadQuery` (V2 `Browsable` parçaları: arama, filtre, sıralama, soft-delete, eager load, uyarılar) — Livewire sayfası yalnızca parametre verir. Test edilebilir, ilişki seçici (liste yeniden kullanımı) aynı servisi kullanır.
2. **Kaydetme servisi:** `Tardis\Bread\BreadSaver` (create/edit'teki kopya `requiredColumns`, dönüştürme, transaction, ilişkiler, olaylar tek yerde). Bugün iki sayfada kopya kod var.
3. **Tip başına sunucu tarafı davranış formfield sınıfında** (Tardis `transform/stored/updated` zaten bu yolda; V1'in `ContentTypes`'ı bunun sade hâli) — lifecycle genişlemesi (Faz 2) V2'yi izler.
4. **Bozuk filtre sayfayı kırmaz, uyarı döndürür** (V2 `warnings[]`; Tardis index'te yavaş sorgu uyarısı var, aynı kanalı kullanır).
5. **Aksiyon sınıfları akıcı kaydedilir** (V2 `->route(fn)->displayOnBread(fn)`); Tardis `Bread\Action` hazır, UI'a bağlanmamış (Faz 3).
6. **Toplu silme kayıt başına `delete()` + olay** (V1 1.8 düzeltmesi) — `destroy($ids)` ile olay kaybolur.

---

## 5. Genişletme noktaları

| Mekanizma | V1.7 | V2.x | Tardis |
|---|---|---|---|
| Alan ekleme | `FormFieldsRegistered` olayı, `Voyager::addFormField($handler)`, config'te `formfields` | `FormfieldPlugin` + `Breads::addFormfield`, otomatik JS/Vue kaydı | `FormfieldManager::registerType` (+ Faz 2: registry/plugin) |
| Alan sonrası içerik | `addAfterFormField` (alanın altına eklenti içeriği) | Plugin `components` | yok |
| Model değiştirme | `Voyager::useModel('Menu', $obj)` ve `config('voyager.models')` | — | yok (host `Role/Permission` modellerini değiştiremez; `auth.providers.users.model` kullanılıyor) |
| View kancası | `Voyager::onLoadingView($name, $closure)` — her view render'ında olay (veri enjekte) | `voyager.page` olayı | yok |
| Rota geçersiz kılma | `routes/voyager.php` yayınlanıp düzenlenir; `Routing*` olayları | `Provider\Routes` + `Protected/FrontendRoutes` | `Routes` contract tanımlı, **bağlı değil** |
| Controller değiştirme | BREAD başına `controller`, `config('voyager.controllers.namespace')` | BREAD başına `controller` | yok |
| Varlık (asset) ekleme | `config('voyager.additional_css/js')` | `Voyager::addJavascript/addCss` (runtime, facade trait `Assets`) + plugin provider'ları | config (yapıldı) + plugin CSS/JS provider |
| Aksiyon ekleme | `Voyager::addAction`, `replaceAction` | `addAction` + `manipulateActions(callable)` | `Action` hazır, kayıt API'si yok |
| Dashboard | `config('voyager.dashboard.widgets')` (Arrilot) | Widget fluent API + `Provider\Widgets`/`Filter\Widgets` | `WidgetManager` var, UI yok |
| Menü | DB menü + `menu()` helper'ı | Plugin `MenuItems` (Provider/Filter) | Plugin `MenuItems` + `MenuManager` |

**Alınacaklar (plana girenler):** `addAfterFormField` benzeri alan-altı kanca; model değiştirme (config `models` haritası: `Role`, `Permission`, `Media`, `ActivityLog`); view/page olayı (`tardis.page` — middleware'de dispatch); runtime `addCss/addJs`; `addAction/replaceAction/manipulateActions`; BREAD başına `component` ve `policy`.

---

## 6. Dikkat çeken kod desenleri ve tuzaklar

**İyi**
- **Olay tabanlı yan etkiler** (V1: 24 olay, 4 listener) — çekirdeği sade tutuyor.
- **Alert sistemi:** `AlertsMessages` trait'i + `Alert` bileşenleri; eksik symlink gibi sorunlar panelde otomatik uyarı olarak çıkar.
- **`Translatable` trait'i** (iki sürümde): model ve BREAD sınıfı çevrilebilir özellik tanımlar; koleksiyona `translate()` makrosu.
- **`Collection::macro('translate')`** ve **`__()` disiplini:** doğrulama kural mesajları bile çeviri dosyasından.
- **V2 `Bread::getModel()`** model sınıfı tembel çözülür ve `Exceptions/` altında açık hata sınıfları var (3 dosya).
- **V2 plugin yaşam döngüsü:** `PluginsController` ile etkinleştir/devre dışı + `PluginsCommand`; plugin tercihleri anonim sınıfla kapsüllenir.

**Kötü / kaçınılacak**
- V1: 1000 satırlık controller, view içinde tip zinciri, `Database/` soyutlaması, global helper bağımlılığı (`setting()`, `menu()`), JSON `details` sütunlarında şemasız veri.
- V2: Test altyapısı Dusk (kırılgan), SPA'ya bağımlı; `TODO.md` açık hata listesi (boş çevrilmiş slug'ın `voyager.dashboard` rotasını ezmesi gibi); BREAD aksiyonlarında yetki yok.
- İkisi de: **dinamik rota üretimi tablo yoksa sessizce yutulur** (V1 `catch (\Exception) {}`) — "migrate edilmemiş" hatası gizlenir.

---

## 7. Tardis için yapısal öneriler (plana girenler)

| # | Öneri | Kaynak fikir | Faz |
|---|---|---|---|
| 1 | BREAD rotaları tanımdan üretilir (adlandırılmış, per-BREAD middleware, wildcard kalkar); BREAD tanımında `component`, `policy`, `scope` | V1/V2 per-slug rotalar, `controller`/`policy`/`scope` | 0 |
| 2 | `BreadSaved/BreadDeleted/BreadRecordCreated/Updated/Deleted/Restored` olayları; izin + menü + cache listener'ları | V1 `BreadAdded` + listener'lar | 0 |
| 3 | `BreadQuery` servisi (V2 `Browsable` parçaları) | V2 | 3 |
| 4 | `BreadSaver` servisi (kopya kodu kaldırır) | V1 `insertUpdateData`, V2 `Saveable` | 2–3 |
| 5 | `BreadDefinition`/`Layout` üzerinde sorgu yöntemleri (`visibleFor`, `searchable`, `orderable`, `relationships`) | V2 `Layout` | 2 |
| 6 | Genişletme API'si: `addAfterFormField`, `addAction/replaceAction/manipulateActions`, `addCss/addJs`, `tardis.page` olayı, config `models` haritası | V1/V2 | 0–4 |
| 7 | `Tardis` facade'ini trait/arayüzlerle ilgi alanına bölmek (şimdi ince, sorun değil) — değişiklik gerekmez | V2 facade trait'leri | — |
| 8 | Layout referans doğrulaması (silinen alan, olmayan sütun) kaydederken | V1 `whereNotIn` temizliği | 3b |
| 9 | Tablo yokken/hatalı tanımda **açık uyarı** (sessiz yutma yok); `tardis:doctor` bunu raporlar | V1 tuzağı | 8 |
| 10 | Test stratejisi: Pest Feature + tam sayfa istek testleri (yapıldı); V2'nin Dusk'ı **alınmaz** | V1/V2 test setleri | — |

---

## 8. Kapsam kararları (kullanıcı kararlarıyla kapandı — 2026-10-03)

Önceki notta (`06` §5/§7) iki konuyu **ben tek başıma "alınmaz" diye yazmıştım**; sonradan soruldu:

| Konu | Karar |
|---|---|
| SPA geçişi | **Hayır** — Livewire + Alpine kalır (`wire:navigate`) |
| Compass benzeri araçlar | **Evet, kısıtlı:** salt okunur log görüntüleyici (`view logs`), izin listeli komut çalıştırıcı (`run commands`, `local` dışında varsayılan kapalı, activity log'a yazar), sistem sayfası (`tardis:doctor` sonucu) |
| BREAD rotaları | Tanımdan üretilir, wildcard kalkar |
| Servis + olaylar | `BreadQuery` + `BreadSaver` servisleri ve BREAD olayları + listener'lar |
