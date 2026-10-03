# Aktif Notlar — docs/notes.md

Bu dosya **yalnızca aktif/açık** bulguları, denemeleri ve açık işleri tutar. **Kalıcı arşiv değildir.**

**Kural: Bir bulgu/iş tamamlandığında ilgili satırı/bölümü bu dosyadan SİL.** "✅ Çözüldü" olarak bırakma — arşiv git geçmişidir, defter yalnızca aktif maddeleri taşır. Kalıcı (silinmeyen) bilgi için bkz. `docs/voyager-tam-arsastirma.md` ve `research/05-tardis-bulgulari-ve-fix-oncelikleri.md`; Voyager karşılaştırması için `docs/voyager-karsilastirma.md`.

**Kapsam:** BREAD paket denetimiyle ilgili açık işler. Tamamlanan düzeltmeler `feat/modern-admin-redesign` branch'inde 7 commit'te duruyor — geçmiş ayrıntı burada tutulmaz.

---

# GÜNCELLEME (2026-10-03) — Kod denetimi: düzeltilenler commit'te, açık kalanlar burada

> Çözülenler `RELEASE_NOTES.md`'de (varsayılan yetkilendirme, ekran yetkileri, settings import; Faz 0: B1, B2, B7, B8, B11, B12, B13, B14). Bu dosyada yalnızca açık kalanlar var.

Bu turda bulunan ve düzeltilen hatalar (kilitli Livewire property'leri, search yetkisi, medya yükleme listesi, login throttle, doğrulama kurallarında `|`, slug path traversal, `make-plugin` stub'ları) `RELEASE_NOTES.md` ve `git log`'da. Aşağıdakiler **karar veya kapsam gerektirdiği için açık**:

| # | Bulgu | Kod ref |
|---|-------|---------|

# GÜNCELLEME (2026-10-01) — Create/edit atomikliği düzeltildi; `registerType()` erişilemezliği AÇIK (karar bekleniyor)

`create()` ile ana satır, sütunlarla ilişkilerden **önce** ve aralarında transaction olmadan yazılıyordu; döngüde bir ilişki patlarsa kullanıcı başarısız istek görüyor ama kayıt yarı yazılmış halde commit ediliyordu. `DB::transaction()` ile sarıldı (`8bb20a3`) — testler kaydın **ve** ilişkilerin birlikte geri alındığını doğruluyor (`tests/Feature/BreadCreateTransactionTest.php`, 2 test).

Bu turdaki asıl yeni bulgu, `registerType()` API'sinin **fiilen erişilemez** olması:

| # | Bulgu | Kod ref |
|---|-------|---------|

## Orta (MEDIUM) — karar bekleniyor

| # | Bulgu | Kod ref |
|---|-------|---------|


## Düşük (LOW) — bilinen, kod değişikliği zorunlu değil

| # | Bulgu | Kod ref |
|---|-------|---------|
| B16 | **Create/edit sayfaları alan tipini satır içi `@if` zinciriyle çiziyor; `resources/views/formfields/*.blade.php` ve `Formfield::render()/viewData()` kullanılmıyor.** Registry artık özel tipleri doğruluyor ama `create.blade.php`/`edit.blade.php`'nin `@else` dalı bilinmeyen tipi düz metin kutusu olarak çiziyor — host'un kaydettiği alan sınıfının `render()` view'ı hiçbir yerde kullanılmaz. Faz 2'de (tek alan sözleşmesi, çok bağlam) çözülür; `addAfterFormField` kancası da buna bağlı. | `resources/views/pages/bread/{create,edit}/*.blade.php`, `src/Formfields/Formfield.php` |
| B4 | **`searchOptions()` filtresiz** — ilişki seçicide tüm kayıtlar listeleniyor. Yalnızca görüntüleme; yazma yolu güvenli. | `BelongsToManyField::searchOptions()` |
| B5 | **Authorization plugin yoksa fail-open** — kasıtlı, `MenuItem::isVisible()` ile aynı davranış ve kodda belgeli. Değiştirilirse Menü ile tutarsızlaşır. | `src/Auth/BreadAuthorization.php` |

*Çözülünce ilgili satır silinir.*

---

# GÜNCELLEME (2026-10-01) — Geri çekilen bulgu (tekrar raporlanmasın)

`HasManyField` için "ilişki satırlarını filtrelemiyor / cross-parent hijack var" iddiası **yanlıştı, geri çekildi.** `$existing` yalnızca o ana kayda bağlı satırları içeriyor, eşleşmeyenlerde `id` düşürülüyor. Cross-parent hijack yok; dosyaya dokunulmadı.

---

# Kabul edilen kısıt (kod değişikliği gerektirmiyor)

- **Dosya yüklemesi transaction dışında kalıyor.** `FileField::transform()` dosyayı satır yazılmadan **önce** diske taşıyor. Transaction eklemek yeni bir trade-off yaratmıyor, sadece DB atomikliğini düzeltiyor — rollback'in geri alamayacağı bir dosyayı geri almaya çalışmıyor. Mevcut orphan-file riski değişmedi.