# Kalıcı Operasyonel Kayıtlar — docs/operations.md

Bu dosya **kalıcı** operasyonel / safety kayıtlarını tutar: geri dönüşü olmayan (irreversible) işlemlerin rollback ve yedek bilgileri. Buradaki madde, ilgili yedekler silinene kadar **korunur** (silinmez).

---

## Sidebar shell özelliği geri alındı — `4a02b2e`

Gerçek sıra (git log ile doğrulandı): `700e43a feat(admin): make the sidebar shell functional` merge edildi → `1effb91 fix(admin): hide the menu search field in the collapsed drawer` daralan drawer'da menü arama alanını gizledi → `4a02b2e` **yalnızca `700e43a`'yı** geri aldı.

Yani `1effb91` revert'ten saç kurtuldu ve hâlâ yürürlükte. `4a02b2e`'nin dokunduğu dosyalar:

| Dosya | Etki |
|---|---|
| `resources/views/components/admin-sidebar.blade.php` | 152 satır geri alındı |
| `resources/views/partials/menu-item.blade.php` | 60 satır geri alındı |
| `tests/Feature/AdminSidebarTest.php` | **112 satır silindi** (dosya artık yok — doğrulandı) |
| `resources/css/app.css` | 14 satır geri alındı |

Revert'ten sonra `04c5160 feat(admin): list BREAD resources in the sidebar` sidebar'a yeni iş ekledi. Yani sidebar bugün "özelliğin geri alınmamış hâli + BREAD listesi + drawer arama düzeltmesi" olarak duruyor.

### Rollback (özelliği geri getirmek istersen)

```bash
git revert 4a02b2e
```

> Dikkat: `AdminSidebarTest.php` silinmişti; `git revert 4a02b2e` onu geri getirir. `1effb91` revert'ten saç kurtulduğu için `admin-sidebar.blade.php` üzerinde **çakışma çözümü gerekebilir** — drawer düzeltmesini korumak istiyorsan revert ederken o satırları bırak. `1effb91`'i geri alma: ayrı bir düzeltme, geri alınan özelliğin parçası değil.

---

## Permission model tabloları `tardis_*` olarak hizalandı — `2f78327`

`Permission` ve `Role` modellerine `$table` override'ı eklendi (`tardis_permissions`, `tardis_roles`). Migration (`2026_06_28_000001_create_permission_tables.php`) **zaten** bu adlarla tablo açıyor (`tardis_permissions`, `tardis_roles`, `tardis_permission_role`, `tardis_role_user`) — yani değişiklik model'leri migration'a hizaladı, yeni tablo **yaratmadı**. Migration çalıştırılmadı, şema değişmedi.

### Neden operasyonel kayıt

Eski modeller Spatie'nin `permissions` / `roles` tablolarına yazıyordu. Spatie kurulu olduğu için bu tablolar mevcuttu ve veri oraya gidiyordu; plugin ise `tardis_*` okuyordu → iki taraf hiç karşılaşmıyordu. Düzeltmeden sonra okuma/yazma `tardis_*`'e taşındı.

**Sonuç: Spatie tablolarına daha önce yazılmış veri artık ölü.** Yeni kurulumda bu tablolar boş olduğu için sorun yok; **yükseltme yapan mevcut hostlarda** veri `permissions`/`roles`'te kalmış olabilir.

### Yükseltme öncesi kontrol (host'ta çalıştır)

```sql
-- Ölü veri var mı?
SELECT COUNT(*) AS spatie_permissions FROM permissions;
SELECT COUNT(*) AS spatie_roles FROM roles;
SELECT COUNT(*) AS tardis_permissions FROM tardis_permissions;
SELECT COUNT(*) AS tardis_roles FROM tardis_roles;
```

`spatie_permissions > 0` ve `tardis_permissions = 0` ise veri taşınmalı. Taşıma kararı host'a ait — bu dosya taşımaz, yalnızca kaydeder.

### İlgili madde

`composer.json` `require` içinde `spatie/laravel-permission: ^6.0` hâlâ duruyor (`src/` artık kullanmıyor). Kaldırılırsa host DB'lerindeki Spatie tabloları **silinmez** — dependency kaldırmak tabloyu düşürmek değildir. Karar: `docs/notes.md` → B3.

---

## `research/` çıktısı gitignore değil — kazara commit riski

`.gitignore` yalnızca `/.omo` satırını içeriyor (satır 25, doğrulandı). `research/` **ignore edilmiyor** ve şu üç yol bilinçli olarak untracked bırakıldı:

| Yol | İçerik | Risk |
|---|---|---|
| `research/voyager-1x-docs/` | Voyager 1.x doküman klonu + **VitePress build çıktısı** | Yüzlerce build dosyası |
| `research/voyager-2x-docs/` | Voyager 2.x doküman klonu + **VitePress build çıktısı** | Yüzlerce build dosyası |
| `docs/voyager-karsilastirma.md` | Eski, daha geniş kapsamlı Voyager raporu | Superseded |

**Risk**: `git add research/` gibi geniş bir komut biri tarafından çalıştırılırsa yüzlerce build dosyası repoya girer.

### Koruma (karar bekleniyor)

```gitignore
/research/voyager-1x-docs/
/research/voyager-2x-docs/
```

Uygulanmadı — kullanıcı kararı bekliyor (`docs/notes.md` → B4). `.omo/` zaten ignore olduğu için session artefaktları (138 `run-continuation/*.json` + `boulder.json`) bu riskin dışında.

---

## Push / merge durumu

Bu dal (`feat/modern-admin-redesign`) **push edilmedi ve merge edilmedi**. Tüm iş branch üzerinde duruyor; PR gözden geçirme ve merge kullanıcı kararı.