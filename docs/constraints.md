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

## BREAD — Tanım kaynağı iki yollu, ikisi de destekleniyor

`JsonBreadSource` ve `ConfigBreadSource` birlikte yaşar. `ConfigBreadSource` bir dizinden, slug başına bir dosya okuyor **ve** tanım yazabiliyor (`write` metodu).

**Sonuç**: "BREAD tanımları JSON mı PHP config mi?" sorusunun tek bir cevabı yok — ikisi de geçerli. `config/bread/` dizini henüz oluşturulmadığı için pratikte tanımlar JSON'da yaşıyor; migration planı `docs/backlog.md`'de.

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

Tema, `CSS variable` anahtar-tokası üzerinden çalışıyor (`66c4ed7`). `ThemePlugin::getStyles()` hâlâ var ve `AssetManager:78` tarafından kullanılıyor — bu yüzden tema stilleri hem manifest hem inline `<style>` olarak üretiliyor.

**Sonuç**: `ThemePlugin::getStyles()` kaldırılırsa `AssetManager`'ın inline style üretimi de kaldırılmalı; iki yol ikisini birden besliyor.

---

## Admin mimarisi — Livewire 4, page-first

Admin şablonları Livewire 4 page-first mimarisine taşındı (`7e854ab`): SFC değil MFC, sayfa bazlı Livewire bileşenleri. Admin sayfaları DaisyUI 5 ile yeniden yazıldı (`49d2454`, 21 şablon).

**Sonuç**: Yeni admin sayfası yazarken MFC + DaisyUI 5 deseni izleniyor; eski SFC veya ham HTML deseni tutarsız olur.