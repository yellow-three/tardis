# Aktif Notlar — docs/notes.md

Bu dosya **yalnızca aktif/açık** bulguları, denemeleri ve açık işleri tutar. **Kalıcı arşiv değildir.**

**Kural: Bir bulgu/iş tamamlandığında ilgili satırı/bölümü bu dosyadan SİL.** "✅ Çözüldü" olarak bırakma — arşiv git geçmişidir, defter yalnızca aktif maddeleri taşır. Kalıcı (silinmeyen) bilgi için bkz. `docs/voyager-tam-arsastirma.md` ve `research/05-tardis-bulgulari-ve-fix-oncelikleri.md`; Voyager karşılaştırması için `docs/voyager-karsilastirma.md`.

**Kapsam:** paketin açık bulguları. Kapananlar `RELEASE_NOTES.md` ve git geçmişinde; plan ve sıra `docs/backlog.md`'de.

---

# GÜNCELLEME (2026-11-17) — Proje incelemesi: açık bulgular

Kapsam: `src/`, `routes/`, `resources/views/pages`, migration'lar, CI ve dokümanlar okundu. Başlangıç durumu: `composer test` → 918 passed (2369 assertions), `composer lint` temiz. **Doğrulama** sütunu: *test* = geçici bir Pest testiyle yeniden üretildi (test sonra silindi), *kod* = yalnızca kod okumasıyla tespit edildi, çalıştırılarak doğrulanmadı. Hiçbir bulgu için kod değişikliği yapılmadı.

**Plan bağlantısı (2026-11-17, kabul edildi):** düzeltme sırası `docs/backlog.md` → *1.0 yol haritası*. Bu incelemenin bulguları kapandı (2026-11-22): R34 (Faz 9), R35 (Faz 10), R37, R39, R45 (Faz 13, B47 tarayıcı testleriyle PR #55), R56 (Faz 10b), R57 (Faz 10c) ve açık bulgular dilimi (PR #56–#61: B29, B34, B37, B38, B55, B56, B58, B59, B72, B75). Ayrıntı `RELEASE_NOTES.md` ve git geçmişinde. **Açık kalanlar:** B35'in çok sunuculu kısmı (1.x, R51) ve tema editöründe `shape` (köşe yuvarlaklığı, kenarlık) düzenleme. Her düzeltme, bulguyu yeniden üreten kalıcı bir testle başlar; kapanan satır bu dosyadan silinir.

## Yüksek (HIGH)

| # | Bulgu | Kod ref | Doğrulama |
|---|-------|---------|-----------|

## Orta (MEDIUM)

| # | Bulgu | Kod ref | Doğrulama |
|---|-------|---------|-----------|

## Düşük (LOW)

| # | Bulgu | Kod ref |
|---|-------|---------|
| B35 | **Durum dosyaları: kalan kısım.** (Bütün durum dosyaları — eklentiler, tercihler, menü bindirmesi, dashboard, temalar, ayarlar, ayar denetim kaydı — kilit altında ve değişiklik bazında yazılıyor; uzun süreli süreçler dosya imzasıyla değişikliği görüyor.) Açık: `storage/` çok sunuculu/efemeral dağıtımlarda paylaşılmıyor, BREAD tanımları deploy ile taşınmıyor (R51). | `storage/tardis/*` |

## Depo / paketleme / CI

| # | Bulgu |
|---|-------|

## Tema, CSS/JS ve Vite (2026-11-17 incelemesi)

Hepsi kod okumasıyla tespit edildi; hedef yapı ve gerekçeler `docs/backlog.md` → *Tema, CSS/JS ve Vite: hedef yapı* (R56, kabul edildi).

| # | Bulgu | Kod ref |
|---|-------|---------|

## Servis sağlayıcı, facade ve `Tardis` sınıfı (2026-11-17 incelemesi)

Hepsi kod okumasıyla tespit edildi; isimlendirme ve yapı önerileri `docs/backlog.md` → *Çekirdek API ve isimlendirme* (R57, kabul edildi).

| # | Bulgu | Kod ref |
|---|-------|---------|

## Rotalar, Blade direktifleri ve Livewire 4 yapısı (2026-11-17 incelemesi)

Livewire sürümü: 4.4.7 (`composer.lock`). *test* = geçici Pest testiyle yeniden üretildi; *kod* = kod okuması (Livewire için `vendor/livewire/livewire/src`). Plan: B68–B70 → R34, B71–B76 → R57.

| # | Bulgu | Kod ref | Doğrulama |
|---|-------|---------|-----------|

---

# GÜNCELLEME (2026-10-01) — Geri çekilen bulgu (tekrar raporlanmasın)

`HasManyField` için "ilişki satırlarını filtrelemiyor / cross-parent hijack var" iddiası **yanlıştı, geri çekildi.** `$existing` yalnızca o ana kayda bağlı satırları içeriyor, eşleşmeyenlerde `id` düşürülüyor. Cross-parent hijack yok; dosyaya dokunulmadı.

---

# Kabul edilen kısıt (kod değişikliği gerektirmiyor)

- **Authorization plugin yoksa panel fail-open** (eski B5). Kasıtlı: `BreadAuthorization::allowsAbility()` ve `MenuItem::isVisible()` aynı kuralı izler; `tardis.authorization.enabled` varsayılan olarak `true` olduğu için bu yalnızca host'un yetkilendirmeyi bilerek kapattığı kurulumlarda geçerli. Değiştirilirse menü ile tutarsızlaşır.

- **Dosya yüklemesi transaction dışında kalıyor.** `FileField::transform()` dosyayı satır yazılmadan **önce** diske taşıyor. Transaction eklemek yeni bir trade-off yaratmıyor, sadece DB atomikliğini düzeltiyor — rollback'in geri alamayacağı bir dosyayı geri almaya çalışmıyor. Mevcut orphan-file riski değişmedi.