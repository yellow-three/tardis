# Kalıcı Kısıtlar — docs/constraints.md

Bu dosya **kalıcı** (silinmeyen) kısıtları ve API/davranış gerçeklerini tutar. "done" denemeyen, evergreen bilgidir. Buradaki maddeler kod/API değiştikçe **güncellenir**, aksi halde **silinmez**.

> `docs/notes.md`'deki aktif bulguların aksine, bu dosyadaki maddeler çözülmüş olmaz — kalıcı referanstır.

---

## BREAD — `FieldType` enum'u özel alan tiplerini kapatıyor

BREAD tanımındaki her `type` değeri, kaynak ne olursa olsun kapalı bir enum'a karşı doğrulanır. Enum'un dışındaki her değer `InvalidArgumentException` alır:

| Tanım kaynağı | Doğrulama yeri | Sonuç |
|---|---|---|
| JSON tanım | `src/Bread/Sources/JsonBreadSource.php:314` | `FieldType::fromValue()` → enum dışı değer patlar |
| PHP config tanım | `src/Bread/Sources/ConfigBreadSource.php:122` | Aynı kapı |

**Sonuç**: `FormfieldManager::registerType()` bir uzantı noktası gibi görünüyor ama **hiçbir BREAD sayfasında kullanılamıyor** — host kendi özel alan tipini ekleyemiyor. Enum'un kendi docblock'u iki kayıt defterini (`FieldType` case'leri ↔ `FormfieldManager::$registeredTypes`) elle eşit tutmayı şart koşuyor; bu iki liste sessizce ayrışabilir.

Yeni bir alan tipi eklerken önce `FieldType`'a, sonra manager'a eklemek gerekiyor — tek taraflı ekleme çalışmıyor.

---

## BREAD — Çalışma zamanı kaynağı JSON; `config/bread` legacy okuma yolu

`BreadManager` tek bir kaynağa bağlı: `JsonBreadSource` (`src/Bread/BreadManager.php:13`). Tanım okuyan tüm yüzeyler (`find`/`all`/`save`/`backups`/`rollback`) bu kaynağa gider, `config/bread/`'e dokunmaz.

`ConfigBreadSource` hâlâ kayıtlı ve yazabiliyor (`save()`, `src/Bread/Sources/ConfigBreadSource.php:74`), ama **okuyan hiçbir çalışma zamanı yolu yok**. Yalnızca şu üç yer tutuyor:

| Kullanım | Yer | Yön |
|---|---|---|
| `tardis:make-bread` tanım üretir | `src/Commands/TardisMakeBreadCommand.php:48` | JSON yazar |
| `tardis:bread:migrate` | `src/Commands/TardisBreadMigrateCommand.php:19` | config/bread → JSON ("legacy" diye adlandırılır) |
| Yönetim ekranı uyarısı | `resources/views/pages/bread/manage/manage.php:19` | config/bread doluysa migrasyon uyarısı gösterir |

**Sonuç**: Pratikte BREAD tanımları JSON'da yaşıyor ve `config/bread` tek yönlü bir **legacy** kaynaktır — içeriği JSON'a taşınır, tersi yazılmaz. İki kaynak aynı `FieldType::fromValue()` kapısından geçtiği için alan tipi doğrulaması her iki yolda da aynıdır.

Bu yön `.omo/plans/bread-php-config.md` (Karar A) ile **çelişiyor**: plan config/bread'i tek kaynak ister, `BreadManager`'ın `ConfigBreadSource`'a bağlanmasını ister. Plan kısmen uygulanmış (`DatabaseBreadSource`, `JsonBreadRepository`, `DataType`/`DataRow` silinmiş), ardından ana hedef tersine çevrilmiş. Yön `.omo/plans/` göz ardı edilerek belirlenir; açık karar `docs/notes.md` → B7.

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

Tema, `CSS variable` anahtar-tokası üzerinden çalışıyor (`66c4ed7`). `ThemePlugin::getStyles()` hâlâ var ve `AssetManager.php:148` tarafından kullanılıyor — bu yüzden tema stilleri hem manifest hem inline `<style>` olarak üretiliyor.

**Sonuç**: `ThemePlugin::getStyles()` kaldırılırsa `AssetManager`'ın inline style üretimi de kaldırılmalı; iki yol ikisini birden besliyor.

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

