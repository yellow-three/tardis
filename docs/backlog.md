# Backlog — Öncelikli İş Birikimi (docs/backlog.md)

Uzun ömürlü, dalga dalga ilerleyen işlerin **roadmap** takibi. Kaynak: `.omo/plans/` ve `.omo/drafts/`. `docs/notes.md`'den farkı: **kalıcı** dosyadır — buradaki madde çözülünce satırı silinmez, **commit ile güncellenir** ve tamamlandığında işaretlenir (roadmap'tir, defter değil). Aktif bulgu detayı `docs/notes.md`'de yaşar; bu dosya ona işaret eder.

## Öncelik ölçeği

- **P0** — Kırıcı / güvenlik / bloke edici (üretim öncesi şart)
- **P1** — Önemli eksik veya hata (kısa vade, kullanıcı görür)
- **P2** — İyileştirme / teknik borç / araç (orta vade)

## Current State

Son güncelleme: 2026-10-01. ID'ler `R` = roadmap sırası; `notes.md` bulgularıyla (`B*`) karışmaz.

### Tamamlandı

| ID | Öncelik | İş | Durum | Commit | Yönlendirme |
|----|---------|----|-------|--------|-------------|
| R1 | P1 | Tema sistemi — CSS variable tabanlı anahtar-toka | ✅ Çözüldü | `66c4ed7` | `docs/constraints.md` → Tema sistemi |
| R2 | P1 | Menü refactor — heroicons + alias config + bölüm/hiyerarşi | ✅ Çözüldü | `914bcd3`, `22e346c`, `83456f9`, `0fd7fe6` | — |
| R3 | P1 | Media browser — Filament V4 iki sütunlu düzen | ✅ Çözüldü | `f7d4c44`, `6f077d6`, `e447334` | — |
| R4 | P2 | Settings — arama, kopyalama, Ctrl+S, validasyon dalgaları | ✅ Çözüldü | `5ac4d74`, `c574820`, `db49ebd` | — |
| R5 | P2 | Blade asset direktifleri (`AssetManager`) | ✅ Çözüldü | `ed8d7f0` | — |
| R6 | P2 | Asset symlink kurulumu | ✅ Çözüldü | (plan frontmatter: `status: completed`) | — |
| R7 | P2 | Dual-mode asset pipeline — Vite dev + prebuilt manifest | ✅ Çözüldü | `ed8d7f0` + `AssetManager`/`ThemeManager` manifest | `docs/constraints.md` → Tema sistemi |
| R8 | P1 | Tema paint öncesi uygulanmıyor (FOUC) — blocking `<script>` + manifest çözümlemesi `</head>` öncesine alındı | ✅ Çözüldü | `fce40c0` | `docs/constraints.md` → Tema sistemi |

### Açık

| ID | Öncelik | İş | Durum | Commit | Yönlendirme |
|----|---------|----|-------|--------|-------------|
| R9 | P1 | **Voyager → Tardis özellik transferi.** BREAD audit turu bu planın parçası; 6 P0/P1 düzeltmesi landı. Kalan açık bulgular B1-B5. | 🔄 Devam ediyor | `e0d00ac`…`8bb20a3` | `docs/notes.md` + `.omo/plans/voyager-transfer.md` |
| R10 | P2 | **Asset iyileştirmeleri — kalan iki madde.** (a) `ThemePlugin::getStyles()` hâlâ var ve `AssetManager:78` kullanıyor; (b) content-hash ile otomatik versiyonlama `AssetManager`'da yok. | ⏳ Açık | — | `docs/constraints.md` → Tema sistemi |
| R11 | P2 | **BREAD tanım kaynağı yönü çelişkili — karar bekleniyor.** `.omo/plans/bread-php-config.md` (Karar A) tek kaynağın `config/bread/*.php` olmasını, `BreadManager`'ın `ConfigBreadSource`'a bağlanmasını ve `JsonBreadSource`'un silinmesini şart koşuyor. Kod tersini yapıyor: `BreadManager` yalnızca `JsonBreadSource`'a bağlı (`src/Bread/BreadManager.php:13`), `tardis:make-bread` JSON üretiyor (`src/Commands/TardisMakeBreadCommand.php:48`), `tardis:bread:migrate` config/bread → JSON yönünde çalışıp kaynağı `$legacy` diye adlandırıyor. `DatabaseBreadSource`/`JsonBreadRepository`/`DataType`/`DataRow` silinmiş, `Tardis.php` import'u düzeltilmiş — yani plan kısmen uygulanmış, ardından ana hedef tersine çevrilmiş. `config/bread/` yönü seçmeden ilerlenmemeli. | ⏸️ Karar bekleniyor | — | `docs/notes.md` → B7 |
| R12 | P2 | **Voyager II kalan özellikler.** Plan survey sonrası revize edildi: kod tabanı ~%85 tamam, kalan %15. | ⏳ Açık (~%85) | — | `.omo/plans/voyager-ii-features.md` |
| R13 | P2 | **Laravel Vite plugin entegrasyonu.** Plan hâlâ `status: draft`. | ⏳ Taslak | — | `.omo/plans/laravel-vite-plugin.md` |
| R14 | P2 | **Graphify MCP'yi OpenCode'a bağla.** Doğrulandı: `~/.config/opencode/opencode.json` içinde `graphify` **yok**. Yalnızca araç/observability, ürün yüzeyi yok. | ⏳ Açık | — | `.omo/plans/graphify-mcp-setup.md` |
| R15 | P1 | **BREAD açık bulguları (5 madde).** `registerType()` erişilemezliği, ölü `field()` stub, kullanılmayan Spatie bağımlılığı, `searchOptions()` filtresiz, fail-open authorization. | ⏳ Açık | — | `docs/notes.md` → B1-B5 |
| R16 | P2 | **`Alpine.store('theme')` → `Alpine.data('theme')` + `$persist` geçişi.** R8 ile birlikte planlanmıştı ama FOUC'yu etkilemiyor ve planın gövdesi `availableThemes` / `lightThemes` / `darkThemes` getter'larını düşürüyor — `settings.blade.php:455-463` bunları `$store.theme.availableThemes` üzerinden okuyor, gövde aynen uygulansaydı settings sayfası kırılırdı. Ayrıca plan store'un `resources/js/app.js` içinde olduğunu varsayıyor; oysa layout'a inline gömülü. Gerçek gerekçe: listener cleanup (`wire:navigate` sırasında birikme). | ⏸️ Ertelenmiş | — | `.omo/plans/theme-fix-wire-navigate.md` (Todo 2-7) |

## Bağımlılık sırası notu

R10, `docs/constraints.md` → Tema sistemi'nde tarif edilen iki yolun tek taraflı kaldırılmasını gerektiriyor: `getStyles()` giderse `AssetManager`'ın inline `<style>` üretimi de gitmeli. R11, R12'nin tanım kaynağını etkiliyor — tanımlar JSON'dan config'e taşınırken R12'nin kalan işleri yeni kayda göre yazılmalı.