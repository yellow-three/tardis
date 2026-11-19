# Kalıcı Kısıtlar — docs/constraints.md

Bu dosya **kalıcı** (silinmeyen) kısıtları ve API/davranış gerçeklerini tutar. "done" denemeyen, evergreen bilgidir. Buradaki maddeler kod/API değiştikçe **güncellenir**, aksi halde **silinmez**.

> `docs/notes.md`'deki aktif bulguların aksine, bu dosyadaki maddeler çözülmüş olmaz — kalıcı referanstır.

---

## BREAD — alan tipleri `FormfieldManager` kayıt defterinden doğrulanır

BREAD tanımındaki her `type`, `FormfieldManager::assertRegistered()` ile kontrol edilir (JSON kaynak hem okurken hem yazarken). Kayıt defteri tek liste: yerleşik 27 tip + `registerType()` ile eklenenler; `image`, `email`, `simple_array` gibi dedektör adları `normalize()` ile kayıtlı tiplere eşlenir. Eski `FieldType` enum'u kaldırıldı.

**Sonuç**: Yeni bir alan tipi yalnızca `registerType()` ile eklenir ve BREAD tanımlarında hemen kullanılabilir — iki liste elle eşit tutulmaz. Create/edit sayfaları alanı `Formfield::render()` view'ından çizer, bu yüzden özel tip formda da görünür. `tests/Unit/FormfieldRegistryTest.php` her kayıtlı tipin örneklenebilir bir `Formfield` ve var olan bir view'a sahip olmasını pinler.

---

## BREAD — Çalışma zamanı kaynağı JSON; `config/bread` yalnızca içe aktarma

`BreadManager` tek kaynağa bağlı: `JsonBreadSource`. `config/bread/*.php` yalnızca `Bread\Legacy\LegacyConfigReader` ile **salt okunur** okunur; kullanan iki yer var: `tardis:bread:import-legacy` (config/bread → JSON) ve yönetim ekranındaki taşıma uyarısı. Hiçbir çalışma zamanı yolu config'ten BREAD okumaz ve hiçbir şey config'e yazmaz.

**Sonuç**: BREAD tanımı eklerken/değiştirirken yalnızca JSON'a yaz (builder, `tardis:make:bread` veya `BreadManager::save()`).

---

## BREAD — rotalar tanımdan üretilir, wildcard yoktur

`Tardis\Http\BreadRoutes::define()` (`routes/admin.php`'in sonunda) her tanım için browse/add/read/edit rotalarını, **var olan slug'larla kısıtlanmış** biçimde kaydeder; adlar herkes için aynıdır (`tardis.bread.index` + `slug`), slug listesi boşken bile kayıtlıdır. Tanım `components` ile bir aksiyonun Livewire bileşenini değiştirebilir: o slug için önce bir "literal" rota (`tardis.bread.index.{slug}`) kaydedilir. `ReservedSlugs` (settings, users, bread, media, database, login…) kaydedilemez ve yönlendirilmez. Liste rota yüklenirken okunur: `route:cache` varsa sonradan oluşturulan BREAD için önbellek yenilenmelidir.

**Sonuç**: Plugin rotaları artık `/{slug}/{id}` tarafından yutulmaz. Test içinde tanım kaydettikten sonra gerçek rotaları görmek için `reloadAdminRoutes()` (tests/Pest.php) çağır. Yeni sabit bir yönetim sayfası eklerken adını `ReservedSlugs`'a ekle.

---

## BREAD — `policy` ve `scope` tanımın parçasıdır

`policy` ability'lerin kurulduğu kelimeyi değiştirir (`browse {policy}`); sayfalar, sidebar, izin üretimi ve `BasePolicy` hepsi `BreadDefinition::permissionKey()` / `BreadAuthorization::keyFor()` üzerinden gider. `scope` model scope adıdır ve `BreadDefinition::query()` ile **listeye ve her kayıt aramasına** uygulanır (read/edit/delete); kapsam dışı bir kayıt id tahminiyle açılamaz.

**Sonuç**: BREAD kaydı bulurken `Model::findOrFail()` değil `BreadDefinition::query()->findOrFail()` kullan.

---

## BREAD — yan etkiler olaylarla, listener'larla

`BreadManager::save()` `BreadSaved`, `delete()` `BreadRemoved` fırlatır; create/edit/delete sayfaları `BreadRecordCreated/Updated/Deleted` fırlatır. İzin üretimi (`ProvisionBreadPermissions`) ve activity log (`LogBreadActivity`) ordinary listener'lardır; log `tardis.activity_log.enabled/log_events` ile yönetilir ve parola/gizli alanları yazmaz. `AdminMiddleware` yetkili her sayfada `tardis.page` olayını yayınlar.

**Sonuç**: Yeni bir yan etki için yöneticinin içine kod ekleme, olayı dinle.

---

## Test — Livewire istisnaları `ViewException` içine sarmalıyor

Livewire 4 testte, handler'dan fırlatılan istisnayı olduğu gibi taşımıyor; `ViewException` içine sarıyor. Bu yüzden `expect(fn() => ...)->toThrow(ModelNotFoundException::class)` deseni BREAD testlerinde **başarısız oluyor** — gerçek hata sarmalayıcının altında.

**Sonuç**: BREAD sayfası testlerinde ya sarmalanmış istisya tipini (`ViewException`) beklenmeli ya da Livewire'in kendi hata assertion'ı kullanılmalı. Test yazarken hangi assertion'ın gerçekten *hatanın* kaynağından geldiğini doğrula — sarmalayıcı bekleyen bir test, yanlış sebeple kırmızı görünebilir.

---

## Test — Pest `toThrow()` sınıf değil mesaj alt metni bekliyor

`expect(...)->toThrow(X::class, 'mesaj')` çağrısında ikinci argüman istisna **sınıfı** değil, sınıfın **constructor'ına geçirilen mesajın alt metni** olarak yorumlanır. Yanlış anlaşılırsa test, beklenmedik bir eşleşme arar.

**Sonuç**: Sınıf eşleşmesi için tek argüman yeter; mesaj eşleşmesi istiyorsan ayrıca doğrula.

---

## Permissions — Tablolar `tardis_*` ön ekli, plugin bunları okuyor

Authorization plugin'i yetenekleri `tardis_permissions` / `tardis_roles` üzerinden çözüyor. Migration (`2026_06_28_000001_create_permission_tables.php`) zaten bu adlarla tablo açıyor: `tardis_permissions`, `tardis_roles`, `tardis_permission_role`, `tardis_role_user`.

**Sonuç**: `Permission` ve `Role` modellerinde `$table` bu adlara sabitlenmek zorunda (aksi halde modeller `permissions`/`roles` tablosuna yazar, plugin `tardis_*` okur ve ikisi hiç karşılaşmaz). Yeni bir permission/role modeli eklerken `$table` override'ını unutma.

---

## Tema sistemi — tema bir DaisyUI paletidir, sunucuda çözülür

"Tema" bu pakette tek bir şey demektir: bir DaisyUI `data-theme` paleti (ad, `light|dark` şeması, `--color-*` değerleri ve DaisyUI 5'in biçim değişkenleri `--radius-*`, `--size-*`, `--border`, `--depth`, `--noise`). Üç kaynağı vardır ve hepsi `ThemeManager::all()` listesinde birleşir; CSS'e yalnızca `ThemeManager::css()` çevirir:

| Kaynak | Nerede tanımlı | Not |
|---|---|---|
| Yerleşik (`tardis-light`, `tardis-dark`) | `src/Theme/BuiltinThemes.php` (tek yer) | İlk açık yerleşik tema `:where(:root)` ile `data-theme`'siz sayfanın da temasıdır |
| Plugin | `ThemePlugin::themes()` (themes.json girdisiyle aynı biçim) | Seçicide görünür; yerleşik bir temanın adını alamaz |
| Özel | `storage/tardis/themes.json` (Themes ekranı, `manage appearance`) | |

`resources/css/app.css` tema tanımlamaz (`@plugin "daisyui" { themes: false; }`); temalar `@tardisStyles` içinde `<style id="tardis-themes">` olarak yazılır. `Theme::fromArray()` tek giriş kapısıdır: ad `[a-z0-9-]`, şema `light|dark`, renk `Theme::isSafeColor()`, biçim değeri `Theme::isSafeLength()`; bilinmeyen anahtar atılır, geçersiz değer temayı reddeder. Bir temanın vermediği biçim değişkeni DaisyUI'nin açık tema varsayılanını alır (`Theme::SHAPE`) — bunlar yazılmazsa bileşenlerin köşe ve kenarlıkları geçersiz değer alır.

**Seçim sunucuda çözülür.** `ThemePreference::resolve()` sırası: kullanıcının kaydı (`storage/tardis/preferences.json`) → Settings `appearance.mode|theme_light|theme_dark` → `tardis-light` / `tardis-dark`. Layout `<html data-theme>`'i doğrudan yazar; `localStorage` kullanılmaz. `<x-tardis::theme-boot />` çekirdek betiğe veriyi (`window.__TARDIS__`) verir ve yalnızca `system` modunda `prefers-color-scheme`'e bakan tek satırlık engelleyici betiği ekler. Tarayıcıdaki değişiklik `Alpine.store('theme')` → `POST tardis.preferences.theme` ile kaydedilir.

**Sonuç**: Yerleşik bir temayı değiştirirken yalnızca `BuiltinThemes.php`'yi değiştir. Bir plugin palet sunmak istiyorsa `ThemePlugin::themes()` kullanır; tek tük değişken ezmek (tema dışı stil) `Provider\CSS` ile yapılır. Yeni bir layout eklerken `data-theme`'i `ThemePreference::resolve()` sonucundan yaz ve `<x-tardis::theme-boot />`'u `@tardisStyles`'tan önce çağır; tema listesini her yerde `ThemeManager`'dan oku.

---

## Varlıklar — çekirdek yayınlanır, plugin varlıkları hash'li rotadan sunulur

`npm run build` iki Vite derlemesi çalıştırır: `vite.config.js` (`resources/css/app.css` → `resources/compiled/assets/app.css`, Tailwind 4 + DaisyUI 5) ve `vite.js.config.js` (`resources/js/app.js` → `app.js`, klasik IIFE; Livewire Alpine'i başlatmadan önce çalışması gerektiği için modül değil). `resources/compiled` depoya **commit edilir** ve CI derleme çıktısının commit'tekiyle aynı olduğunu denetler; host'ta Node gerekmez.

| Varlık | Nasıl sunulur | Önbellek |
|---|---|---|
| Çekirdek `app.css` / `app.js` | `vendor:publish --tag=tardis-assets` ile `public/vendor/tardis/assets` altına kopyalanır | Sabit dosya adı + `?v=<md5>`; yayınlanan kopya paket güncellenince bayatlar (`tardis:doctor` bildirir) |
| Plugin / host dosyası (`Asset::file()`) | `/{prefix}/_assets/{hash}.css|js` rotası (`AssetController`), yalnızca kayıtlı varlıklar | İçerik hash'i + `immutable`; yayınlama gerekmez |
| Satır içi (`Asset::inlineCss/Js`, temalar, `appearance.custom_css`, `window.__TARDIS__`) | Sayfaya yazılır | Yok; CSP için nonce (`tardis.csp.nonce` ya da Vite nonce'u) |

`@tardisStyles` sırası: çekirdek `app.css` → tüm temalar → `appearance.custom_css` → `Tardis::addCss()` → plugin CSS → `tardis.assets.css`. `@tardisScripts` sırası: çekirdek `app.js` → `Tardis::addJs()` → plugin JS → `tardis.assets.js`; ardından `@livewireScripts`.

Geliştirme modu: `npm run dev` `resources/hot` dosyasını yazar (`vite-plugins/hot-file.js`); dosya varken `AssetManager` çekirdek varlıkları Vite sunucusundan yükler. Laravel'in `Vite` facade'ı ve manifest'i kullanılmaz.

**Sonuç**: Çekirdek CSS/JS'i değiştirdikten sonra `npm run build` çalıştır ve `resources/compiled`'ı aynı commit'e koy. Plugin varlığı için `public/`'e dosya kopyalama; `Asset::file()` kullan. Paket CSS'i yalnızca paketin kendi view'larındaki Tailwind sınıflarını içerir: plugin/host sayfası DaisyUI bileşen sınıflarını kullanır, gerisini kendi CSS'iyle getirir.

---

## Admin mimarisi — Livewire 4, page-first

Admin şablonları Livewire 4 page-first mimarisine taşındı (`7e854ab`): sayfa bazlı Livewire bileşenleri, yalnızca SFC ve MFC (class-based yok). Küçük sayfalar SFC, büyükler (BREAD sayfaları, builder, database, media-browser, settings) MFC. Admin sayfaları DaisyUI 5 ile yeniden yazıldı (`49d2454`, 21 şablon).

**Sonuç**: Yeni admin sayfası yazarken SFC/MFC + DaisyUI 5 deseni izleniyor; sayfa ~800 satırı geçerse MFC'ye böl (`livewire:convert --mfc`). Eski class-based veya ham HTML deseni tutarsız olur. Kurallar: `docs/LIVEWIRE_PAGE_ORGANIZATION.md`.

---

## Livewire — public property'ler istemci tarafından yazılabilir

Livewire'de her `public` property tarayıcıdan değiştirilebilir. BREAD sayfalarında `slug`, `id`, `bread` ve `record` yetkilendirmenin dayandığı değerlerdir: `mount()` yetkiyi `slug` ile kontrol eder, `delete()`/`save()` ise sonra `bread['model']`'e güvenir. Kilitli olmasalar, istemci `bread.model`'i başka bir Eloquent sınıfına çevirip yetkili bir ability ile o modelde create/edit/delete çalıştırabilirdi.

**Sonuç**: Yetkilendirmenin veya hedef model/kayıt seçiminin dayandığı her property `#[Locked]` olmalı (`tests/Feature/BreadAuthorizationTest.php` bunu index/create/read/edit için pinler). Yeni bir BREAD sayfası eklerken aynı property'leri kilitle; yalnızca gerçekten kullanıcı girdisi olan alanlar (`form`, `search`, …) yazılabilir kalır. BREAD builder'ın property'leri bilerek yazılabilir — builder zaten tanımı düzenlemek için var.

---

## Yetkilendirme — varsayılan olarak açık plugin, `access admin` kapısı

`RoleAuthorizationPlugin` `TardisServiceProvider::boot()` içinde kaydedilir ve etkinleştirilir (`tardis.authorization.enabled`, varsayılan `true`; boot'ta okunur çünkü host config'i `register()` sırasında henüz final değildir). `AdminMiddleware` kimlik doğrulamadan sonra `access admin` ability'sini ister; rolü olmayan kullanıcı 403 alır. İlk yönetici `tardis:admin` ile oluşturulur.

Plugin **yoksa** (`enabled=false` ve host da kendi plugin'ini kaydetmediyse) `BreadAuthorization` hâlâ fail-open'dır: giriş yapmış herkes girer. Bu yüzden `enabled=false` yalnızca başka bir koruma varken kullanılmalı.

**Sonuç**: Yeni bir ekran eklerken `boot()` içinde `app(BreadAuthorization::class)->authorizeAbility(Abilities::X)` çağır (Livewire her istekte `boot()`'u çalıştırır, `mount()` yalnızca ilk render'da) ve `Abilities` + `PermissionSeeder`'a ability'yi ekle; menü öğesine `->permission(...)` ver. Testler `tardis.authorization.enabled=false` ile çalışır (`tests/TestCase.php`); yetki testleri kendi plugin'ini kaydeder.

---

## Plugin'ler — `enable()` kullanıcı eylemidir, boot'ta `enableByDefault()`

`PluginManager::enable()` saklanan "disabled" kaydını siler ve cache'e yazar. Bir service provider'dan çağrılırsa her istekte Plugins sayfasındaki devre dışı bırakmayı geri alır. Boot-time varsayılanı `enableByDefault()` verir: kullanıcı kapattıysa dokunmaz, cache'e yazmaz.

**Sonuç**: Provider'larda ve `tardis:make:plugin` stub'larında `register()` + `enableByDefault()` kullan; `enable()` yalnızca Plugins sayfasındaki düğmeye ait.

Açık/kapalı durumu `storage/tardis/plugins.json` içinde tutulur (cache değil — `cache:clear` bir deploy'da tüm kapatmaları geri alırdı). `AuthenticationPlugin` ve `AuthorizationPlugin` uygulayan plugin'ler **kilitlidir**: `disable()` `LogicException` fırlatır, sayfa "Required" gösterir, dosyada "disabled" yazsa bile yok sayılır.

---

## BREAD slug'ı dosya adıdır

`JsonBreadSource` slug'ı doğrudan dosya adına çevirir (`{slug}.json`, `{slug}.backup.*.json`). Slug yalnızca `[A-Za-z0-9_-]` olabilir ve harf/rakamla başlar; `find()`/`has()` geçersiz slug için `null`/`false` döner, yazan metotlar (`save`, `delete`, `rollback`) `InvalidArgumentException` fırlatır.

**Sonuç**: Slug'ı kullanıcı girdisinden dosya yoluna taşıyan yeni bir kaynak/komut yazarken `JsonBreadSource::isValidSlug()` kullan; kendi regex'ini yazma. Builder'ın `[a-z0-9-]+` kuralı bunun alt kümesidir.

---

## Medya — yükleme uzantı listesiyle sınırlı

Medya ekranı yüklemeleri `tardis.media.allowed_mimes` uzantılarıyla (`extensions:` + `mimes:`) ve `max_file_size` ile sınırlar; liste boşsa kural uygulanmaz. Disk genelde `public` olduğundan `.php`/`.html` yüklemek web kökünden sunulan/çalıştırılan dosya demektir. `svg` varsayılan listede yok (script taşıyabilir, temizlenmez); ekleyen host bunu bilerek ekler.

Dosya önce Livewire'ın geçici yüklemesinden geçer ve onun sınırı önce uygulanır (varsayılan 12 MB, `throttle:60,1`); `max_file_size` bunu büyütmez, host `livewire.temporary_file_upload.rules`'ı da yükseltmelidir (README "Media uploads").

**Sonuç**: Medya yükleyen yeni bir yüzey eklerken aynı listeyi kullan. `MediaManager::upload()` kendisi doğrulama yapmaz — çağıran doğrulamalıdır.

---

## Manager'lar container singleton'ıdır

`MenuManager`, `WidgetManager`, `SettingsManager`, `FormfieldManager` ve `PluginManager` container'da tek instance'tır ve `Tardis::menu()` vb. aynı nesneyi döndürür. Eskiden facade `new` ile ayrı bir kopya üretiyordu: facade üzerinden kaydedilen menü öğesi, alan tipi veya plugin sayfalara/middleware'e hiç ulaşmıyordu (ve header ikinci, boş bir `MenuManager` çözüp kullanıcı menüsünü boş gösteriyordu).

**Sonuç**: Yeni bir manager eklerken `TardisServiceProvider::register()` içinde `singleton` yap ve `Tardis` sınıfında `app(Class::class)` ile çöz — `new` kullanma. `tests/Unit/TardisTest.php` eşitliği pinler.

---

## Admin kabuğu host rotalarına bağımlı olmamalı

Header/sidebar yalnızca `tardis.*` rotalarını varsayar. Host'un `profile.edit` rotası varsa Profile bağlantısı eklenir, yoksa eklenmez; çıkış `tardis.logout`'tur. Eskiden boş bir host (Breeze/Fortify'sız) her sayfada `Route [profile.edit] not defined` ile 500 veriyordu.

**Sonuç**: Layout/header'a `route('...')` eklerken rotanın paketin kendisinde olduğundan emin ol; host rotası gerekiyorsa `Route::has()` ile koru. `tests/Feature/AdminShellTest.php` her sabit sayfayı tam doküman olarak çıplak bir host'ta render eder.

---

## Test — `NoHardcodedTextTest` etiket içindeki `->` ifadesini yanlış okuyor

Görünür metin tarayıcısı HTML etiketlerini regex ile tarar ve etiket dışında kalan kısmı metin sayar. Blade'de `@disabled(! $this->canRun)` yazıldığında metindeki `>` karakteri (`->` okunun ucu) etiketi erkenden kapatır. Tarayıcı ifadeyi geçerli bir attribute değeri olarak göremez ve Blade'in gerçekte üretmediği bir metni raporlar — yani test, DOM'da olmayan bir metni "görünür" bulur.

**Sonuç**: Bir öznitelik değeri içinde (`@disabled`, `:class`, `wire:*`, `x-*`) asla `$this->` kullanma. Değeri `mount()` ve `updated*()` içinde senkronlayan düz bir public property olarak tut (`public bool $canRun = false;`) ve etikette çıplak `$canRun` yaz. Böylece tarayıcı, test ve gerçek DOM aynı değeri görür.

