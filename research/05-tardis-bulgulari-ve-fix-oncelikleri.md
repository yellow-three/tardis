# Tardis BREAD Bulguları ve Düzeltme Öncelikleri

> **Durum notu (2026-10-03).** Bu dosya 2026-09-29 denetiminin anlık görüntüsüdür; aşağıdaki bulguların çoğu o günden beri kapandı. Güncel açık işler için `docs/notes.md` ve `docs/backlog.md`'ye bak; bu dosyayı geçmiş kanıt/gerekçe olarak oku.
>
> | Bulgu | Şimdiki durum |
> |---|---|
> | P0 validation kuralları düşüyor | ✅ Çözüldü — `FieldValidationRules` (+ `|` içeren regex düzeltmesi), `BreadValidationTest` |
> | P0 izin kontrolü yok | 🟡 Sayfalar `BreadAuthorization` ile kontrol ediyor ve property'ler kilitli; **ama varsayılan kurulumda plugin yok → fail-open** (`notes.md` B9/B10) |
> | P0 translatable → NULL | ✅ Çözüldü — `TranslatableFormfieldTest` |
> | P1 BelongsToMany kapsamsız `sync()` | ✅ Çözüldü — `resolvableIds()`; `searchOptions()` filtresizliği açık (B4) |
> | P1 HasMany filtreleme | ↩️ Bulgu geri çekildi (`notes.md`) |
> | P1 `FormfieldManager::field()` boş | ⏳ Açık (B2) |
> | P1 checkbox options | ✅ Çözüldü — view `options` döngüsü |
> | P2 create transaction | ✅ Çözüldü — `DB::transaction` (`8bb20a3`) |
> | P2 disabled/readonly/help/width | ✅ Çözüldü — formfield view'ları bayrakları işliyor |
> | P3 ekosistem (settings, widget, plugin, media, DB yöneticisi) | ✅ Eklendi (Voyager'a göre farklar `docs/voyager-tam-arsastirma.md`'de) |

Tarih: 2026-09-29
Kapsam: `packages/tardis` — BREAD create/edit/index/read/manage akışları, 19 form field tipi,
validation, ilişkiler, izinler, seçenekler, render katmanı.

**Durum:** Bulgular kanıtlandı (çalıştırılan probe + kaynak okuma).
Düzeltmeler henüz **yazılmadı** — aşağıdaki her madde için önerilen çözüm ve test planı var.

---

## ÖZET

| Öncelik | Bulgu | Etki |
|---|---|---|
| **P0** | Field `validation` kuralları (`unique`, `email`, `max`, `min`) sessizce düşüyor | Bozuk veri, güvenlik açığı |
| **P0** | BREAD aksiyonlarında **hiç izin kontrolü yok** | Yetkisiz yazma/silme |
| **P0** | `translatable: true` alanlar sessizce `NULL` kaydediliyor | Veri kaybı |
| **P1** | `BelongsToManyField::stored()` istemci ID'lerine `sync()` — kapsam kontrolü yok | IDOR (yetkisiz kayıt bağlama) |
| **P1** | `HasManyField` ilişki satırlarını filtrelemeden create/update | Veri bozulması |
| **P1** | `FormfieldManager::field()` hep `[]` döner; `render()`/`viewData()` ölü | Mimari borç, uzantı yolu kapalı |
| **P1** | `checkbox` options yoksa tek kutu render ediyor | Bozuk UI |
| **P2** | Create ilişki `stored()` işlemleri transaction dışında | Kısmi kayıt |
| **P2** | `disabled`/`readonly`/`help`/`width`/`default`/`attributes` canlı Blade'de yok | Eksik özellik |
| **P3** | Ayar sistemi, widget, plugin altyapısı yok | Eksik ekosistem |

**Test durumu:** 365 passed (888 assertions), `php -l` ve `git diff --check` temiz.
Bu turda bulunan P0'lar **henüz teste bağlanmadı**.

---

## P0-1 — Field validation kuralları sessizce düşüyor

**Dosya:** `resources/views/pages/bread/create/create.php:107-132`

```php
$rules = [];
foreach ($fields as $name => $field) {
    $fieldRules = (array) ($field['validation'] ?? []);
    // ↓ SADECE 'required' honored ediliyor
    $rules['form.'.$name] = in_array('required', $fieldRules, true) ? 'required' : 'nullable';
    if (...) { $rules['form.'.$name] .= '|file'; }
    if (...) { $rules['form.'.$name] .= '|mimes:'.implode(',', (array) $field['mimes']); }
    if (...) { $rules['form.'.$name] .= '|max:'.(int) $field['max_size']; }
}
```

Field tanımındaki `validation` dizisi okunuyor, ancak yalnız `required` değeri
değerlendiriliyor. `unique`, `email`, `min`, `max`, `confirmed`, `regex` gibi
kurallar **hiçbir şekilde** Laravel validator'a aktarılmıyor.

`mimes` ve `max_size` ise `validation` dizisinden değil, **ayrı option
alanlarından** yeniden türetiliyor — yani `validation: ['max:5']` yazmak
sanıldığında hiçbir şey olmuyor.

**Kanıt (çalıştırılan probe):**
```
unique ihlali reddedildi mi   → false
email kuralı reddetti mi      → false
duplicate kayıttan sonra kayıt sayısı → 2     (1 olmalıydı)
max:5 sınırı                  → uygulanmıyor
```

Gerçek `posts.json` içinde `slug: ["required","unique"]` tanımlı —
**bu koruma tamamen etkisiz.**

**Etki:** Duplicate slug, geçersiz e-posta, taşmış sayısal değer kaydedilebilir.

**Önerilen çözüm:**
`validation` dizisini doğrudan Laravel kural formatına çevir, `required`
dışındakileri de uygula. Nokta sözdizimi (`.field:rule`) destekle.
`max_size`/`mimes` türetmesini `validation` ile birleştir, çakışmayı engelle.

**Test:** `tests/Feature/BreadPagesTest.php` içine —
`unique` ihlali 422 dönmeli; `email` geçersizse 422; `max` aşılırsa 422.

---

## P0-2 — BREAD aksiyonlarında izin kontrolü yok

**Dosyalar:** `resources/views/pages/bread/{create,edit,index,read,manage}/*.php`

Doğrulama sonucu — bu dizinde `forBread`, `authorize`, `can(` desenlerinin
**hiçbir eşleşmesi yok**:

```bash
grep -rn "forBread\|authorize\|can(" resources/views/pages/bread/   →  (boş)
```

`Permission::forBread()` yalnızca **kayıt** tarafında çağrılıyor:

```
src/Auth/TardisAuthorizationPlugin.php:44  →  Permission::forBread($slug);
```

Yani izinler **üretiliyor** (kayıt anında) ama **tüketilmiyor** (hiçbir yerde
kontrol edilmiyor). `TardisAuthorizationPlugin` isimli bir sınıf olmasına
rağmen gerçek bir yetkilendirme kararı vermiyor.

**Etki:** Giriş yapmış herhangi bir kullanıcı, kendisine tanımlanmamış bir
BREAD'in create/edit/delete işlemlerini gerçekleştirebilir. Bu en ciddi
bulgudur.

**Önerilen çözüm:**
Her BREAD aksiyonunun başlangıcında `Permission::forBread` üretmiş izin
anahtarlarına karşı kontrol (`browse`, `read`, `add`, `edit`, `delete`)
koy. İzin yoksa 403 döndür. `BasePolicy` yerine gerçek politika uygula.

**Test:** İzni olmayan kullanıcıyla create/edit/delete → 403; yetkili → 2xx.

---

## P0-3 — `translatable: true` alanlar `NULL` kaydediliyor

**Dosya:** `resources/views/pages/bread/create/create.php` (saving bölümü)

Translatable alan için beklenen: `{ "tr": "...", "en": "..." }`.
Gerçekleşen: `"Merhaba Dunya"` gönderildiğinde alan **`NULL`** oluyor.

**Kanıt:** Geçici probe ile doğrulandı (`/tmp/opencode/prove_translatable.php`).

**Etki:** Sessiz veri kaybı — kullanıcı veri girdi, sistem boş kaydetti,
hata mesajı yok.

**Önerilen çözüm:** Kaydetme öncesi alan tipini kontrol et. Translatable
alan `array` bekliyorsa ve düz string geliyorsa, aktif locale'ye sar:
`[$locale => $value]`. Ayrıca create blade'inde alan adı
`field[tr]` biçiminde `name` attr'ı taşımalı.

**Test:** Translatable alan create → kayıt sonrası JSON çözümlenir ve
`{"tr":"Merhaba Dunya"}` eşit olur.

---

## P1-1 — `BelongsToManyField::stored()` kapsam kontrolsuz `sync()`

**Dosya:** `src/Formfields/Types/BelongsToManyField.php`

`stored()` metodu, istemciden gelen ilişkili model **ID listesini** doğrudan
`sync()` ediyor. Kullanıcının yetkili olmadığı kayıtların ID'sini
göndermesi halinde ilişki kurulabilir.

**Etki:** IDOR benzeri yetki aşımı — saldırgan tahmin edilen bir ID ile
kaydı kendi ilişkili nesnesine bağlayabilir.

**Önerilen çözüm:** `sync()` öncesi ID listesini yetkili/scope edilmiş bir
sorguyla doğrula; yalnızca geçerli olanları `sync()` et, kalanını
reddet veya logla. Field seviyesine `->where()`/`->visible()` desteği ekle.

**Test:** Yetkisiz ID gönderildiğinde ilişki kurulmamalı (403 veya ID yok sayılmalı).

---

## P1-2 — `HasManyField` ilişki satırlarını filtrelemeden yazıyor

**Dosya:** `src/Formfields/Types/HasManyField.php`

Create/update sırasında ilişkili satırlar **filtrelenmeden** create/update
ediliyor. `select` tipi alanlar (ör. `checkbox`, `select`) doğru değer
set'ini belirlemeli, ancak tüm gelen satırlar işleniyor.

**Etki:** İstemcinin göndermediği/alakasız satırların verisi bozulabilir;
kısmi güncelleme beklenmedik yere yayılır.

**Önerilen çözüm:** İşlenmeden önce gelen satırları mevcut kayıtlarla eşleştir;
yalnız eşleşenleri güncelle, yeni olanları ekle, **silme kararını ayrı bir
`deleted_ids` kanalına taşı**.

**Test:** Kısmi güncelleme → yalnız gönderilen satır değişir.

---

## P1-3 — Render mimarisi ölü, `field()` hep boş dönüyor

**Dosyalar:**
- `src/Manager/FormfieldManager.php:69-71` → `field()` her zaman `[]` döner
- `src/Formfields/Formfield.php:174` → `viewData()` tanımlı
- `src/Formfields/Formfield.php:193` → `abstract public function render()`

**Kanıt:**
```bash
grep -rn "viewData(" resources/views/pages/bread/ src/  →  sadece tanımlar (Types/*)
grep -rn "->render("  resources/views/pages/bread/ src/  →  (boş)
```

`viewData()` **yalnız sınıflarda tanımlı**, hiçbir yerden çağrılmıyor.
`render()` abstract olarak tanımlı ama **hiç çağrılmıyor**.
Sonuç: 19 field sınıfının `render()`/`viewData()` implementasyonları ve
`formfields.*` view'ları **ölü kod**.

**Etki:** Üçüncü parti field ekleme yolu kapalı — yeni bir field tipi
`create.blade.php`/`edit.blade.php` içine elle eklenmek zorunda. Bu, her
field için iki yerde kopya kod demek.

**Önerilen çözüm (Voyager 2 mantığı):**
Handler'ı ince bırak, gerçek mantığı field sınıfına taşı:

```php
// FormfieldManager
public function field(string $name, mixed $value = null): array   // artık dolu dönmeli
{
    return $this->handlers[$this->type($name)]->handle($name, $value, $this->bread);
}
```

Blade tarafında tek noktadan render:
```blade
{!! $field->render() !!}
```

**Test:** `FormfieldManager::field()` artık boş dönmemeli; her field tipi
create/edit blade'lerinden render edilebilir olmalı.

---

## P1-4 — `checkbox` options yoksa tek kutu render ediyor

**Dosya:** ilgili blade (create/edit)

`checkbox` tipi, `options` tanımlı değilse **tek bir checkbox** basıyor.
Kullanıcı "evet/hayır" değil, tek bir onay kutusu görüyor.

**Etki:** Çoklu seçim gereken yerde tek kutu → veri kaybı.

**Önerilen çözüm:** `options` boşsa `checkbox` render edilmesin veya
create/edit sırasında hata verilsin (`options` zorunlu olsun).
Çoklu seçim ayrı bir `checkboxes` tipi olarak tanımlansın (Voyager 1.x'te
`Multiple Checkbox` ayrı tiptir).

---

## P2-1 — Create ilişki `stored()` işlemleri transaction dışında

**Dosya:** `resources/views/pages/bread/create/create.php`

Voyager 1.x, ilişki `stored()` işlemlerini (pivot yazma vb.) bir
`DB::transaction` içinde çalıştırır. Tardis'te bu işlemler ana kayıt
yazıldıktan **sonra, transaction dışında** çalışıyor.

**Etki:** İlişki yazımı başarısız olursa ana kayıt yine de kalır →
**kısmi/ tutarsız kayıt**.

**Önerilen çözüm:** Ana kayıt + tüm ilişki `stored()` çağrılarını tek
`DB::transaction()` içine al; hata halinde `DB::rollBack()`.

**Test:** `stored()` hata fırlatınca ana kayıt da geri alınmalı.

---

## P2-2 — Eksik field option'ları canlı Blade'de yok

Aşağıdaki option'lar tanımlanabiliyor ama create/edit blade'lerinde
**kullanılmıyor**:

| Option | Durum |
|---|---|
| `disabled` | Yok — alan hep aktif |
| `readonly` | Yok — alan hep düzenlenebilir |
| `help` | Yok — yardım metni gösterilmiyor |
| `width` | Yok — sütun genişliği yok |
| `default` | Yok — varsayılan değer uygulanmıyor |
| `attributes` | Yok — ek HTML attr enjekte edilmiyor |

**Etki:** BREAD builder'da ayarlanan bu seçenekler sessizce işlevsiz.

**Önerilen çözüm:** Blade'de `field()` çıktısını tek bir partial'a taşı;
orada `disabled`/`readonly`/`help`/`width`/`default`/`attributes` uygula.

---

## P3 — Eksik ekosistem parçaları (Voyager karşılaştırması)

| Özellik | Voyager | Tardis | Not |
|---|---|---|---|
| Ayar sistemi (`settings.json`, group, validation) | ✅ | ❌ | `provideSettings` şeması örnek alınabilir |
| Widget sistemi + `->permission()` | ✅ | ❌ | İzinle panel öğesi gizleme |
| Plugin (Provider/Filter kontratları) | ✅ | ❌ | Uzun vadeli |
| Theme plugin | ✅ | ❌ | — |
| BREAD action bazlı accessor | ✅ | ❌ | `getNameBrowseAttribute()` örneği |
| Satır aksiyon butonları (`getPolicy()`) | ✅ | ❌ | Aksiyon bazlı izin örneği |
| Veritabanı yöneticisi | ✅ | ❌ | — |
| Medya yöneticisi | ✅ | ❌ | Mevcut `ImageField` sınırlı |
| Çoklu checkbox / çoklu select ayrı tip | ✅ | ❌ | `checkbox` tek seçim |

---

## Düzeltme Sırası (önerilen)

```
1. P0-2  izin kontrolü        → en yüksek güvenlik etkisi
2. P0-1  validation kuralları → veri bütünlüğü
3. P0-3  translatable kayıp   → veri kaybı
4. P1-1  BelongsToMany kapsam → IDOR
5. P1-2  HasMany filtreleme   → veri bozulması
6. P1-3  render/field()      → mimari borç (testler yeşil kalırsa)
7. P1-4  checkbox options
8. P2-1  transaction
9. P2-2  eksik option'lar
10. P3   ekosistem
```

**Not:** 1-5 **davranış değiştirir** — mevcut testlerin bir kısmı
(kontrollü/izinli erişim varsayıyor) güncellenmeli. 6-9 **görsel/işlevsel**
etki daha az riskli ama daha çok test gerektirir.

**Yöntem notu (kurallar):** Düzeltme sırasında `as any`, `@ts-ignore`
benzeri bastırmalar kullanılmamalı; refactor ile bugfix aynı commit'te
birleştirilmemeli (önce test, sonra düzeltme).
