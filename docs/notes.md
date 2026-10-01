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
| B1 | **`FieldType` enum'u özel alan tiplerini reddediyor.** Hem JSON hem config kaynağı her alanın `type` değerini kapalı enum'a karşı doğruluyor; enum dışındaki her değer `InvalidArgumentException` alıyor. `FormfieldManager::registerType()` bir uzantı noktası gibi görünüyor ama **hiçbir BREAD sayfasında kullanılamıyor** — host kendi özel alan tipini BREAD'e ekleyemiyor. Enum'un docblock'u iki kayıt defterini (`FieldType` case'leri ↔ `FormfieldManager::$registeredTypes`) elle eşit tutmayı şart koşuyor, sessizce ayrışabilir. Kırılma değil ama dokümante edilmiş uzantı noktasının erişilemez olması. Karar: enum kaldırılmalı ya da tanıma yolu genişletilmeli. | `src/Bread/FieldType.php`, `src/Bread/Sources/JsonBreadSource.php:314`, `src/Bread/Sources/ConfigBreadSource.php:122` |

## Orta (MEDIUM) — karar bekleniyor

| # | Bulgu | Kod ref |
|---|-------|---------|
| B2 | **`FormfieldManager::field()` ölü stub** — `[]` döndürüyor, üretimde çağrılmıyor. Public API yüzeyinde olduğu için sessizce silmek BC kırılması. | `src/Manager/FormfieldManager.php` |
| B3 | **Kullanılmayan bağımlılık** — `require` içinde `spatie/laravel-permission: ^6.0` duruyor, native seeder sonrası `src/` kullanmıyor. Kaldırmak BC kararı (host bunu doğrudan kullanıyor olabilir). | `composer.json` |

## Düşük (LOW) — bilinen, kod değişikliği zorunlu değil

| # | Bulgu | Kod ref |
|---|-------|---------|
| B4 | **`searchOptions()` filtresiz** — ilişki seçicide tüm kayıtlar listeleniyor. Yalnızca görüntüleme; yazma yolu güvenli. | `BelongsToManyField::searchOptions()` |
| B5 | **Authorization plugin yoksa fail-open** — kasıtlı, `MenuItem::isVisible()` ile aynı davranış ve kodda belgeli. Değiştirilirse Menü ile tutarsızlaşır. | `src/Auth/BreadAuthorization.php` |
| B6 | **Auth layout'ı tema tercihini yok sayıyor** — `auth.blade.php:2` statik `data-theme="dark"` hardcode ve layout'ta tema/Alpine referansı yok. Admin'de light seçen kullanıcı giriş/logout sonrası her zaman dark görüyor; o sayfalarda toggle da bulunmadığı için geri de çeviremiyor. FOUC değil (tutarlı dark), ama admin ile tutarsız. `fce40c0`'daki blocking script'in auth layout kopyası + store eklenmesi çözer. | `resources/views/layouts/auth.blade.php:2` |

*Çözülünce ilgili satır silinir.*

---

# GÜNCELLEME (2026-10-01) — Geri çekilen bulgu (tekrar raporlanmasın)

`HasManyField` için "ilişki satırlarını filtrelemiyor / cross-parent hijack var" iddiası **yanlıştı, geri çekildi.** `$existing` yalnızca o ana kayda bağlı satırları içeriyor, eşleşmeyenlerde `id` düşürülüyor. Cross-parent hijack yok; dosyaya dokunulmadı.

---

# Kabul edilen kısıt (kod değişikliği gerektirmiyor)

- **Dosya yüklemesi transaction dışında kalıyor.** `FileField::transform()` dosyayı satır yazılmadan **önce** diske taşıyor. Transaction eklemek yeni bir trade-off yaratmıyor, sadece DB atomikliğini düzeltiyor — rollback'in geri alamayacağı bir dosyayı geri almaya çalışmıyor. Mevcut orphan-file riski değişmedi.