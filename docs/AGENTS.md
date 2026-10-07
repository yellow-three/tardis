# AGENTS.md — TARDIS çalışma rehberi

Bu dosya **bu depodaki dokümantasyon yapısının nasıl kullanılacağını** anlatır. İçerik değil, yönlendirme içerir: bir şey yazacaksan nereye yazacağını buradan bulursun.

Kural tek cümlede: **her bilgi tek bir yerde yaşar, yaşam döngüsü yere göre değişir.** Aynı maddeyi iki dosyaya yazma.

---

## Ne yazacaksın? — yönlendirme tablosu

| Yazacağın şey | Dosya | Yaşam döngüsü |
|---|---|---|
| Aktif bulgu, açık iş, karar bekleyen yön | `docs/notes.md` | **Çözülünce satırı SİL** |
| Uzun ömürlü roadmap kalemi (`R*`) | `docs/backlog.md` | **Satırı silme, durum + commit güncelle** |
| Kalıcı kısıt, API/davranış gerçeği, "bunu yapma" kuralı | `docs/constraints.md` | **Asla "çözüldü" işaretleme, maddeyi güncelle** |
| Geri dönüşü olmayan işlem + rollback/backup bilgisi | `docs/operations.md` | **Yedekler silinene kadar koru** |

Kararsızsan: bu bir *sonraki adım* mı, yoksa *her zaman doğru olan bir şey* mi? Sonraki adım → `notes.md`. Her zaman doğru → `constraints.md`.

---

## Dört dosyanın yaşam döngüsü

### `docs/notes.md` — defter, arşiv değil

Yalnızca **aktif** bulguları tutar. Bir bulgu çözüldüğünde ilgili satırı buradan sil.

- `"✅ Çözüldü"` yazma. Arşiv git geçmişidir; defter yalnızca açık maddeleri taşır.
- Çözülen bulgunun kalıcı izi `git log`'dur. Geçmiş ayrıntıyı notlara taşıma.
- Bölümler önem sırasına göre: yüksek / orta (MEDIUM) / düşük (LOW). Yeni bulgu doğru bölüme girer.
- Tablo biçimi: `| # | Bulgu | Kod ref |`

### `docs/backlog.md` — roadmap, kalıcı

Uzun ömürlü işlerin haritası. Madde çözülünce **silinmez**; durum ve commit ile güncellenir.

- Kimlikler `R1…R19` (`R` = roadmap sırası). `B*` kimlikleriyle karıştırılmaz.
- Öncelik ölçeği: **P0** kırıcı/güvenlik/bloke edici · **P1** önemli eksik veya kullanıcı görür · **P2** iyileştirme/teknik borç/araç.
- İki tablo: `### Tamamlandı` ve `### Açık`. Çözülen madde Tamamlandı'ya taşınır.
- Sütunlar: `ID | Öncelik | İş | Durum | Commit | Yönlendirme`
- Durum dili: `✅ Çözüldü` · `🔄 Devam ediyor` · `⏸️ Karar bekleniyor` · `⏳ Açık` · `⏸️ Ertelenmiş`
- `## Current State` başlığındaki "Son güncelleme: `<tarih>`" satırını her dokunduğunda güncelle.
- **Yönlendirme sütunu** aktif bulguya işaret eder (`docs/notes.md` → B7). Roadmap özeti tutar, detayı notes taşır.

### `docs/constraints.md` — kalıcı gerçekler

"Done" olmayan, evergreen bilgi. Buradaki maddeler **çözülmez**; kod değiştikçe güncellenir.

- Her bölüm bir konu başlığı + kısa açıklama + **`**Sonuç**:`** satırı. Sonuç satırı, o kuralın pratikte neyi değiştirdiğini söyler.
- Kod referansı verirken **satır numarasını doğrula** (aşağıya bak).
- Yeni bir kısıt buraya düşer: "Bunu yapma" / "Bunu yapmadan önce şunu bil" tipi bir gerçek bulursan.

### `docs/operations.md` — geri dönüşsüz işlem kayıtları

Geri alınamayan işlemlerin rollback ve yedek bilgisi.

- Bir şeyi geri aldıysan, yedeği aldıysan, tabloları düşürdüysen: buraya yaz.
- Yedekler **silinene kadar** maddeyi koru — silme.

---

## Bağlam dosyaları (kanonik değil, referans)

- `docs/voyager-tam-arsastirma.md` — tam BREAD + Voyager araştırması
- `docs/voyager-karsilastirma.md` — Voyager 1.x/2.x karşılaştırması
- `.omo/plans/*.md` — iş planları ve karar taslakları
- `docs/EXAMPLE_BREAD.md`, `docs/PLUGIN_GUIDE.md`, `docs/LIVEWIRE_PAGE_ORGANIZATION.md`, `docs/DEMO_FLOW.md` — kullanım/şablon referansı

---

## Sert kapılar

Bunlar karar bekleyen yönlerdir; karar verilmeden **tarafında hiçbir iş yapma**:

- **B7 / R11 — BREAD tanım kaynağı yönü.** Kod JSON'da, plan `config/bread/*.php` istiyor. Yön seçilmeden `config/bread/`'e yeni tanım yazan, JSON'u config'e taşıyan veya `ConfigBreadSource`'ı okuyan bir yol ekleyen iş yapma. → `docs/notes.md` → B7
- **B1 — `FieldType` enum yönü.** Enum kaldırılacak mı, tanıma yolu mu genişleyecek karar bekliyor; iki yön de BC etkisi taşıyor. → `docs/notes.md` → B1
- **B8 / R10(a) — `ThemePlugin::getStyles()`.** Host plugin'leri bu metodu uyguluyor; kaldırmak BC kırılması. Seçenekler notes'ta. `getStyles()` giderse `AssetManager`'ın inline `<style>` üretimi de birlikte gitmeli. → `docs/notes.md` → B8
- **B5 — fail-open authorization.** Kasıtlı; `MenuItem::isVisible()` ile aynı davranış. Değiştirirsen Menü ile tutarsızlaşır. Test yazarak bu davranışı **onaylamaya** çalışma — karar B5'e ait. → `docs/notes.md` → B5
- **R12, R11'e bağlı.** BREAD tanımları JSON'dan config'e taşınırsa R12'nin kalan işleri yeni kayda göre yazılmalı.

Bir kısıtı "daha temiz görünüyor" diye sessizce ihlal etme; önce ilgili maddeye çözüm yaz.

---

## Komutlar

```bash
composer test              # vendor/bin/pest
composer test-coverage     # vendor/bin/pest --coverage
composer lint              # ./vendor/bin/pint --test
composer lint-fix          # ./vendor/bin/pint
```

Testler `orchestra/testbench` üzerinde koşar; gerçek uygulama/DB gerektirmez. `composer.lock` **gitignore'dadır** — bu bir kütüphanedir, lock dosyasını repoya sokma.

Doğrulama kapısı: `./vendor/bin/pest` yeşil + `./vendor/bin/pint --test` temiz + `git diff --check` sessiz olmadan işi tamamlanmış sayılmaz.

---

## Test yazarken

- **Guard yazan bir test, guard'ın kaldırılınca gerçekten kırıldığını göstermelidir.** Koruduğun kodu geçici olarak bozup testin kırıldığını gör, sonra geri al. Bu, testin yanından geçmekten çok daha değerlidir — `docs/notes.md`'de korunan bir davranışın testsiz kalması, en az davranışın kendisi kadar riskli bir kusurdur.
- **Kırılan davranışı testle sabitle.** Değiştirdiğin bir şeyin etkisini görmek için `git diff`i okumak yetmez; testi çalıştır.
- **Test gerçek hatayı beklesin.** Sarmalayıcı bekleyen bir test yanlış sebeple kırmızı görünebilir. BREAD sayfası testlerinde Livewire istisyalarının `ViewException` içine sarmalandığını unutma; Pest `toThrow()`'un ikinci argümanı sınıf değil **mesaj alt metni** bekler. Ayrıntı: `docs/constraints.md` → Test bölümleri.
- Bir davranışı **onaylamak** için test yazıp kararı kendinde kilitleme (B5'te olduğu gibi).

---

## Doküman referanslarını doğrula

`docs/` içindeki `dosya.php:123` referansları okuyucunun doğrulama adımıdır; **yanlış satır numarası, numara olmamasından kötüdür** — okuyucuyu yanlış fonksiyona gönderir ve dosyadaki diğer tüm referanslara güveni aşındırır.

Kod eklediğinde/taşıdığında **etkilenen referansları güncelle**. Bir bulguyu dokümana yazarken yazdığın satır numarasını `grep -n` ile doğrula.

---

## Git

- Conventional Commits: `feat:` · `fix:` · `refactor:` · `test:` · `docs:` · `build:` · `chore:`
- Commit mesajı **ne** yaptığını değil **neden** yaptığını açıklasın; bir satırda gerekçe yeter.
- `main`'e doğrudan commit/push yok; görev başına `feature/…` / `fix/…` / `refactor/…` branch'i.
- Yalnızca **kendi** değişikliklerini commit'le; başkasının çalışmasına dokunma.
- Push/merge otomatik değil — kullanıcıya bırak.
- Bittiğinde kullanıcıya **neyin değiştiğini, nedenini ve neyin yapılmadığını** anlatan bir rapor ver (karar bekleyenler açıkça listelensin).

---

## Depo haritası

```
src/
  Auth/          TardisAuthorizationPlugin — TARDIS'in kendi Permission/Role yetkileri
                 BreadAuthorization — plugin yokken fail-open yol (kasıtlı, B5)
  Bread/         BREAD tanım modeli ve kaynaklar (JSON + PHP config)
  Commands/      artisan komutları
  Contracts/     Plugin arayüzleri (ThemePlugin, AuthorizationPlugin, …)
  Formfields/    Alan tipleri (19 tip)
  Manager/       AssetManager, FormfieldManager, PluginManager, …
  Models/        Permission, Role, Media, ActivityLog
  Policies/      BasePolicy — host'un extend edip Gate'e kaydettiği yetki taban sınıfı
tests/
  Unit/          Saf mantık, DB yok
  Feature/       Livewire sayfaları, BREAD akışları, yetkilendirme
  Integration/   Tema manifest gibi uçtan uca
  Fixtures/      Test verisi (JSON BREAD tanımları)
docs/            Bu rehber + yukarıdaki dört yaşam döngüsü dosyası
.omo/plans/      İş planları
```

Host entegrasyonu **paketin içine değil, host'a** aittir: TARDIS kendi tablolarını `tardis_*` ön ekiyle açar, host kendi tablosunu kendi yönetir. Ayrıntı: `docs/constraints.md` → Permissions.