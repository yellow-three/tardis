# Aktif Notlar — docs/notes.md

Bu dosya **yalnızca aktif/açık** bulguları, denemeleri ve açık işleri tutar. **Kalıcı arşiv değildir.**

**Kural: Bir bulgu/iş tamamlandığında ilgili satırı/bölümü bu dosyadan SİL.** "✅ Çözüldü" olarak bırakma — arşiv git geçmişidir, defter yalnızca aktif maddeleri taşır. Kalıcı (silinmeyen) bilgi için bkz. `docs/voyager-tam-arsistirma.md` ve `research/05-tardis-bulgulari-ve-fix-oncelikleri.md`; Voyager karşılaştırması için `docs/voyager-karsilastirma.md`.

**Kapsam:** BREAD paket denetimiyle ilgili açık işler. Tamamlanan düzeltmeler `feat/modern-admin-redesign` branch'inde 7 commit'te duruyor — geçmiş ayrıntı burada tutulmaz.

---

# GÜNCELLEME (2026-10-01) — Create/edit atomikliği düzeltildi; `registerType()` erişilemezliği AÇIK (karar bekleniyor)

`create()` ile ana satır, sütunlarla ilişkilerden **önce** ve aralarında transaction olmadan yazılıyordu; döngüde bir ilişki patlarsa kullanıcı başarısız istek görüyor ama kayıt yarı yazılmış halde commit ediliyordu. `DB::transaction()` ile sarıldı (`8bb20a3`) — testler kaydın **ve** ilişkilerin birlikte geri alındığını doğruluyor (`tests/Feature/BreadCreateTransactionTest.php`, 2 test).

Bu turdaki asıl yeni bulgu, `registerType()` API'sinin **fiilen erişilemez** olması:

| # | Bulgu | Kod ref |
|---|-------|---------|
| B1 | **`FieldType` enum'u özel alan tiplerini reddediyor.** Hem JSON hem config kaynağı her alanın `type` değerini kapalı enum'a karşı doğruluyor; enum dışındaki her değer `InvalidArgumentException` alıyor. `FormfieldManager::registerType()` bir uzantı noktası gibi görünüyor ama **hiçbir BREAD sayfasında kullanılamıyor** — host kendi özel alan tipini BREAD'e ekleyemiyor. Enum'un docblock'u iki kayıt defterini (`FieldType` case'leri ↔ `FormfieldManager::$registeredTypes`) elle eşit tutmayı şart koşuyor, **ayrışma artık testle engelli** (`tests/Unit/FieldTypeTest.php`: enum case'i → renderer, renderer → enum case'i, renderer → instantiable `Formfield`; kayma enjekte edilip testin gerçekten kırıldığı doğrulandı). Kırılma değil ama dokümante edilmiş uzantı noktasının erişilemez olması. **Kalan karar yalnızca uzantı noktasının yönü**: enum kaldırılmalı mı, tanıma yolu genişletilmeli mi. | `src/Bread/FieldType.php`, `src/Bread/Sources/JsonBreadSource.php:314`, `src/Bread/Sources/ConfigBreadSource.php:122` |
| B7 | **Plan ile kod ters yönde: BREAD tanım kaynağı hangisi?** `.omo/plans/bread-php-config.md` (Karar A, 29 Mayıs 2026) tek kaynağın `config/bread/*.php` olmasını, `BreadManager`'ın `ConfigBreadSource`'a bağlanmasını (plan Adım 2) ve `JsonBreadSource`'un **silinmesini** (Adım 4) şart koşuyor. Kod bunun tersini yapıyor: `BreadManager` yalnızca `JsonBreadSource`'a bağlı (`src/Bread/BreadManager.php:13`), `tardis:make-bread` JSON üretiyor (`src/Commands/TardisMakeBreadCommand.php:48`), `tardis:bread:migrate` config/bread → JSON yönünde çalışıyor ve kaynağı `$legacy` diye adlandırıyor (`src/Commands/TardisBreadMigrateCommand.php:19`), yönetim ekranı `config/bread` doluysa "legacy" uyarısı veriyor (`resources/views/pages/bread/manage/manage.php:19`). Planın *silme* adımlarının bir kısmı uygulanmış (`DatabaseBreadSource`, `JsonBreadRepository`, `BreadRepositoryInterface`, `DataType`, `DataRow` yok; `src/Tardis.php` import'u düzeltilmiş), sonra ana hedef tersine çevrilmiş. Bu dosyanın ilgili satırları tek bir squashed commit'te (`bd783e7`) olduğu için **yönün kim/ne zaman tersine çevirdiği git geçmişinden belirlenemiyor** — kasıtlı bir karar mı yoksa yarım kalmış bir uygulama mı belli değil. İki seçenek: (a) JSON'u resmî kaynak onayla → `ConfigBreadSource` + `tardis:bread:migrate` kaldırılır ya da legacy olarak belgelenir, R11 kapanır; (b) Karar A'yı geri getir → `BreadManager` ve `tardis:make-bread` config'e döner, JSON legacy'ye çevrilir. **Kod yönü seçilmeden `config/bread`'e yeni tanım yazan hiçbir iş yapılmamalı.** | `src/Bread/BreadManager.php:13`, `src/Commands/TardisBreadMigrateCommand.php:19`, `.omo/plans/bread-php-config.md` |

## Orta (MEDIUM) — karar bekleniyor

| # | Bulgu | Kod ref |
|---|-------|---------|
| B2 | **`FormfieldManager::field()` ölü stub** — `[]` döndürüyor, üretimde çağrılmıyor. Public API yüzeyinde olduğu için sessizce silmek BC kırılması. | `src/Manager/FormfieldManager.php` |
| B3 | **Kullanılmayan bağımlılık** — `require` içinde `spatie/laravel-permission: ^6.0` duruyor, ancak **derleme zamanı bağımlılığı yok**: paketin tamamındaki tek Spatie referansı `TardisAuthorizationPlugin.php:39`'daki bir yorum, tek gerçek kullanım ise `method_exists($user, 'hasPermissionTo')` ile **runtime duck-typing** — hiçbir `use Spatie\...` import'u veya tip referansı yok. Spatie kurulu değilken de paket çalışır, `can()` kendi yetki kontrolüne düşer; bağımlılık yalnızca host'un kendi kullanımı için bir kolaylık. Kaldırmak yine de BC kararı: TARDIS'e transitif güvenip kendi `composer.json`'unda yazmayan host'un autoload'u kırılır. Seçenekler: (a) `require`'da kalır — yorum zaten gerekçeyi belgeliyor, host'a yardımcı olmaya devam eder; (b) `suggest` + `require-dev`'e taşınır — Composer'ın opsiyonel entegrasyon için doğru yeri, aynı transitif BC riski nedeniyle yine karar gerektirir. **Karar ölçütü: host'un transitive kuruluma güvenmesi gerçek mi (a), değilse (b).** | `composer.json:38`, `src/Auth/TardisAuthorizationPlugin.php:39-43` |
| B8 | **`ThemePlugin::getStyles()` zorunlu contract** — interface metodu `getStyles(): string`; tema plugin'i CSS üretmek istemese bile uygulamak zorunda. Tek üretim çağrısı `AssetManager.php:110` (inline `<style>` bloğu), testte de bir fake uyguluyor. Host'un kendi tema plugin'leri bu metodu uyguladığı için kaldırmak BC kırılması. Seçenekler: (a) dokümana yazılı kalır, kod değişmez; (b) dönüş tipi `?string` yapılır — mevcut `: string` uygulamalar sorunsuz karşılanır, plugin'ler CSS üretmekle yükümlü olmaktan çıkar, `AssetManager` null'ı atlar; (c) interface'ten tamamen çıkarılır ve inline `<style>` bloğu silinir (breaking). **R10(a) bu kararı bekliyor; R10(b) ayrı ve tamamlandı.** | `src/Contracts/Plugins/ThemePlugin.php`, `src/Manager/AssetManager.php:110` |

## Düşük (LOW) — bilinen, kod değişikliği zorunlu değil

| # | Bulgu | Kod ref |
|---|-------|---------|
| B4 | **`searchOptions()` filtresiz** — ilişki seçicide tüm kayıtlar listeleniyor. Yalnızca görüntüleme; yazma yolu güvenli. | `BelongsToManyField::searchOptions()` |
| B5 | **Authorization plugin yoksa fail-open** — kasıtlı, `MenuItem::isVisible()` ile aynı davranış ve kodda belgeli. Değiştirilirse Menü ile tutarsızlaşır. | `src/Auth/BreadAuthorization.php` |

*Çözülünce ilgili satır silinir.*

---

# GÜNCELLEME (2026-10-01) — Geri çekilen bulgu (tekrar raporlanmasın)

`HasManyField` için "ilişki satırlarını filtrelemiyor / cross-parent hijack var" iddiası **yanlıştı, geri çekildi.** `$existing` yalnızca o ana kayda bağlı satırları içeriyor, eşleşmeyenlerde `id` düşürülüyor. Cross-parent hijack yok; dosyaya dokunulmadı.

---

# Kabul edilen kısıt (kod değişikliği gerektirmiyor)

- **Dosya yüklemesi transaction dışında kalıyor.** `FileField::transform()` dosyayı satır yazılmadan **önce** diske taşıyor. Transaction eklemek yeni bir trade-off yaratmıyor, sadece DB atomikliğini düzeltiyor — rollback'in geri alamayacağı bir dosyayı geri almaya çalışmıyor. Mevcut orphan-file riski değişmedi.