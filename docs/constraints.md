# Kalıcı Kısıtlar — docs/constraints.md

Bu dosya **kalıcı** (silinmeyen) kısıtları ve API/davranış gerçeklerini tutar. "done" denemeyen, evergreen bilgidir. Buradaki maddeler kod/API değiştikçe **güncellenir**, aksi halde **silinmez**.

> `docs/notes.md`'deki aktif bulguların aksine, bu dosyadaki maddeler çözülmüş olmaz — kalıcı referanstır.

---

## BREAD — alan tipleri `FormfieldManager` kayıt defterinden doğrulanır

BREAD tanımındaki her `type`, `FormfieldManager::assertRegistered()` ile kontrol edilir (JSON kaynak hem okurken hem yazarken). Kayıt defteri tek liste: yerleşik 19 tip + `registerType()` ile eklenenler; `image`, `email`, `simple_array` gibi dedektör adları `normalize()` ile kayıtlı tiplere eşlenir. Eski `FieldType` enum'u kaldırıldı.

**Sonuç**: Yeni bir alan tipi yalnızca `registerType()` ile eklenir ve BREAD tanımlarında hemen kullanılabilir — iki liste elle eşit tutulmaz. **Ancak** create/edit sayfaları tipi hâlâ satır içi `@if` zinciriyle çizer; özel tipin `render()` view'ı kullanılmaz (Faz 2, `docs/notes.md` → B16). `tests/Unit/FormfieldRegistryTest.php` her kayıtlı tipin örneklenebilir bir `Formfield` ve var olan bir view'a sahip olmasını pinler.

---

## BREAD — Çalışma zamanı kaynağı JSON; `config/bread` yalnızca içe aktarma

`BreadManager` tek kaynağa bağlı: `JsonBreadSource`. `config/bread/*.php` yalnızca `Bread\Legacy\LegacyConfigReader` ile **salt okunur** okunur; kullanan iki yer var: `tardis:bread:migrate` (config/bread → JSON) ve yönetim ekranındaki taşıma uyarısı. Hiçbir çalışma zamanı yolu config'ten BREAD okumaz ve hiçbir şey config'e yazmaz.

**Sonuç**: BREAD tanımı eklerken/değiştirirken yalnızca JSON'a yaz (builder, `tardis:make-bread` veya `BreadManager::save()`).

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

## Tema sistemi — CSS variable tabanlı, `Alpine.store` ile

Tema, `CSS variable` anahtar-tokası üzerinden çalışıyor (`66c4ed7`). `ThemePlugin` yalnızca `getTheme()` (özel özellik → değer) sağlar; `AssetManager` adları/değerleri doğrulayıp `:root{…}` kuralını kendisi yazar, tema keyfi CSS enjekte edemez (`getStyles()` kaldırıldı).

**Sonuç**: Tema plugin'i CSS metni değil değişken verir; değerler yalnızca renk/uzunluk/sayı karakterleri taşıyabilir.

**Tema çözümlemesinin tek kaynağı `<x-tardis::theme-boot />`**: `<head>`'de ilk paint'ten önce çalışan blocking script ile Alpine store aynı temayı çözmek zorunda — store boot'ta `data-theme`'i yeniden yazdığı için iki taraf ayrışırsa flash geri gelir. Bu mantık `resources/views/components/theme-boot.blade.php` içinde yaşar ve admin/auth layout'ları `<x-tardis::theme-boot />` çağırarak devralır. **Yeni bir layout eklerken blocking script'i kopyalama; bileşeni çağır.** Aynı şekilde localStorage anahtar adları (`tardis-theme-mode`, `tardis-theme-light`, `tardis-theme-dark`) store ile birebir aynı kalmalı. `ThemeFoucGuardTest` bu eşleşmeyi, bileşenin `@tardisStyles`'tan önce geldiğini ve gerçekten render edilebildiğini pinler.

**Sonuç**: tema çözümlemesini layout'a kopyalamak, B7'deki "iki kaynak ayrışır" hatasının aynısını üretir — bu yüzden tekrar eden çözümleme yasak, manifest okuma da `AssetManager::availableThemes()` üzerinden yapılır.

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

`TardisAuthorizationPlugin` `TardisServiceProvider::boot()` içinde kaydedilir ve etkinleştirilir (`tardis.authorization.enabled`, varsayılan `true`; boot'ta okunur çünkü host config'i `register()` sırasında henüz final değildir). `AdminMiddleware` kimlik doğrulamadan sonra `access admin` ability'sini ister; rolü olmayan kullanıcı 403 alır. İlk yönetici `tardis:admin` ile oluşturulur.

Plugin **yoksa** (`enabled=false` ve host da kendi plugin'ini kaydetmediyse) `BreadAuthorization` hâlâ fail-open'dır: giriş yapmış herkes girer. Bu yüzden `enabled=false` yalnızca başka bir koruma varken kullanılmalı.

**Sonuç**: Yeni bir ekran eklerken `boot()` içinde `app(BreadAuthorization::class)->authorizeAbility(Abilities::X)` çağır (Livewire her istekte `boot()`'u çalıştırır, `mount()` yalnızca ilk render'da) ve `Abilities` + `PermissionSeeder`'a ability'yi ekle; menü öğesine `->permission(...)` ver. Testler `tardis.authorization.enabled=false` ile çalışır (`tests/TestCase.php`); yetki testleri kendi plugin'ini kaydeder.

---

## Plugin'ler — `enable()` kullanıcı eylemidir, boot'ta `enableByDefault()`

`PluginManager::enable()` saklanan "disabled" kaydını siler ve cache'e yazar. Bir service provider'dan çağrılırsa her istekte Plugins sayfasındaki devre dışı bırakmayı geri alır. Boot-time varsayılanı `enableByDefault()` verir: kullanıcı kapattıysa dokunmaz, cache'e yazmaz.

**Sonuç**: Provider'larda ve `tardis:make-plugin` stub'larında `register()` + `enableByDefault()` kullan; `enable()` yalnızca Plugins sayfasındaki düğmeye ait.

Açık/kapalı durumu `storage/tardis/plugins.json` içinde tutulur (cache değil — `cache:clear` bir deploy'da tüm kapatmaları geri alırdı). `AuthenticationPlugin` ve `AuthorizationPlugin` uygulayan plugin'ler **kilitlidir**: `disable()` `LogicException` fırlatır, sayfa "Required" gösterir, dosyada "disabled" yazsa bile yok sayılır.

---

## BREAD slug'ı dosya adıdır

`JsonBreadSource` slug'ı doğrudan dosya adına çevirir (`{slug}.json`, `{slug}.backup.*.json`). Slug yalnızca `[A-Za-z0-9_-]` olabilir ve harf/rakamla başlar; `find()`/`has()` geçersiz slug için `null`/`false` döner, yazan metotlar (`save`, `delete`, `rollback`) `InvalidArgumentException` fırlatır.

**Sonuç**: Slug'ı kullanıcı girdisinden dosya yoluna taşıyan yeni bir kaynak/komut yazarken `JsonBreadSource::isValidSlug()` kullan; kendi regex'ini yazma. Builder'ın `[a-z0-9-]+` kuralı bunun alt kümesidir.

---

## Medya — yükleme uzantı listesiyle sınırlı

Medya ekranı yüklemeleri `tardis-media.allowed_mimes` uzantılarıyla (`extensions:` + `mimes:`) ve `max_file_size` ile sınırlar; liste boşsa kural uygulanmaz. Disk genelde `public` olduğundan `.php`/`.html` yüklemek web kökünden sunulan/çalıştırılan dosya demektir. Varsayılan listede `svg` var ve script taşıyabilir.

**Sonuç**: Medya yükleyen yeni bir yüzey eklerken aynı listeyi kullan. `MediaManager::upload()` kendisi doğrulama yapmaz — çağıran doğrulamalıdır.

---

## Manager'lar container singleton'ıdır

`MenuManager`, `WidgetManager`, `SettingsManager`, `FormfieldManager` ve `PluginManager` container'da tek instance'tır ve `Tardis::menu()` vb. aynı nesneyi döndürür. Eskiden facade `new` ile ayrı bir kopya üretiyordu: facade üzerinden kaydedilen menü öğesi, alan tipi veya plugin sayfalara/middleware'e hiç ulaşmıyordu (ve header ikinci, boş bir `MenuManager` çözüp kullanıcı menüsünü boş gösteriyordu).

**Sonuç**: Yeni bir manager eklerken `TardisServiceProvider::register()` içinde `singleton` yap ve `Tardis` sınıfında `app(Class::class)` ile çöz — `new` kullanma. `tests/Unit/TardisTest.php` eşitliği pinler.

---

## Admin kabuğu host rotalarına bağımlı olmamalı

Header/sidebar yalnızca `tardis.*` rotalarını varsayar. Host'un `profile.edit` rotası varsa Profile bağlantısı eklenir, yoksa eklenmez; çıkış `tardis.logout`'tur. Eskiden boş bir host (Breeze/Fortify'sız) her sayfada `Route [profile.edit] not defined` ile 500 veriyordu.

**Sonuç**: Layout/header'a `route('...')` eklerken rotanın paketin kendisinde olduğundan emin ol; host rotası gerekiyorsa `Route::has()` ile koru. `tests/Feature/AdminShellTest.php` her sabit sayfayı tam doküman olarak çıplak bir host'ta render eder.

