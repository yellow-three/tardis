# TARDIS BREAD DENETİMİ VE VOYAGER ARAŞTIRMASI — NİHAİ RAPOR

> **Durum notu (2026-10-03).** Bu rapor 2026-09-29 tarihli bir anlık görüntüdür. "Düzeltilmedi, planlandı" denen P0 bulguları (validation, translatable, BelongsToMany kapsamı) sonradan düzeltildi, izin kontrolü sayfalarda uygulanıyor ve artık varsayılan olarak etkin (`RELEASE_NOTES.md`); test sayısı 365'ten 476'ya çıktı. Bulgu bazında güncel durum tablosu: `research/05-tardis-bulgulari-ve-fix-oncelikleri.md` (en üstte). Açık işler: `docs/notes.md`.

Tarih: 2026-09-29
Branch: `feat/modern-admin-redesign`
Kapsam: `packages/tardis` BREAD akışları + Voyager 1.x / Voyager 2.x karşılaştırması
(plugin, media, settings, save tüm sayfalar dahil)

> ## ⭐ ANA TESLİLAT
> Birleşik nihai rapor: **`docs/voyager-tam-arsastirma.md`**
> Bu dosya (`00-ozet.md`) yalnız yönetici özeti ve okuma sırasıdır; tüm bulgular,
> Voyager 1.x/2.x sistem detayları, plugin karşılaştırması, doküman kapsamı ve
> öncelikli düzeltme listesi birleşik rapordadır.

> **Yeniden doğrulama (2026-10-03):** dört kaynak yeniden indirilip rakamlar kaynakla karşılaştırıldı; düzeltmeler ilgili dosyaların başındaki "Yeniden doğrulama" kutularında. Öne çıkanlar: (1) 1.x'in güncel çizgisi `1.8` (Laravel 11), araştırma başta eski `1.x` branch'ine dayanıyordu; (2) "resmi `tcg/voyager-*` plugin'leri" **yanlıştı** — bu paketler yok, `larapack/voyager-hooks` 1.5'te kaldırıldı; (3) 2.x deposu 2022-10'dan beri donmuş ve BREAD yetkilendirmesi orada "To be implemented"; (4) birkaç sayı düzeltildi (media 8 action, settings 7 metod, 34 dil, 27/11 manager metodu).

---

## 1. BU RAPORU NASIL OKUMALI

| # | Dosya | Ne var içinde |
|---|---|---|
| 00 | **`00-ozet.md`** (bu dosya) | Yönetici özeti, karar önerileri, okuma sırası |
| 01 | `01-voyager-1x.md` | Voyager 1.x kaynak kodu araştırması — 23 field handler, BREAD akışı, policy, media, settings |
| 02 | `02-voyager-2x.md` | Voyager 2.x kaynak kodu araştırması — 16 field sınıfı, lifecycle, plugin kontratları, BREAD JSON |
| 03 | `03-voyager-plugin-sistemi.md` | **Plugin sistemi karşılaştırması** — 5 tip, 17 kontrat, Provider/Filter ayrımı |
| 04 | `04-resmi-dokumanlar.md` | **Tüm doküman sayfalarının dizini ve özeti** (38 + 55) |
| 05 | `05-tardis-bulgulari-ve-fix-oncelikleri.md` | **Tardis bulguları — kanıt, etki, çözüm, test planı** |
| — | `voyager-1x-docs/` | 38 indirilmiş resmi 1.x doküman sayfası |
| — | `voyager-2x-docs/` | 55 kopyalanmış resmi 2.x doküman dosyası |

**Önce 05, sonra 03, sonra 01/02 oku.** 04 bir referans dizinidir;
gerektiğinde tek tek sayfalara bakmak için.

---

## 2. YÖNETİCİ ÖZETİ

### 2.1 Ne yapıldı

1. **Tardis BREAD denetimi** — 19 field tipinin create/edit/index/read/manage
   akışları, validation, ilişkiler, izinler, seçenekler, render katmanı ve
   kayıt yolu incelendi. Render/UI eksikleri düzeltildi ve test edildi
   (365 test geçiyor). Derinlemesine davranış denetiminde **3 kritik
   güvenlik/veri bütünlüğü hatası** bulundu (aşağıda).
2. **Voyager 1.x araştırması** — kaynak kod + 38 resmi doküman sayfası incelendi.
3. **Voyager 2.x araştırması** — kaynak kod + 55 doküman dosyası incelendi.
4. **Plugin sistemi** — her iki sürüm için ayrıntılı karşılaştırma yapıldı.
5. **Dört kaynağın tamamı** indirildi ve `research/` altında saklandı.

### 2.2 En kritik 3 bulgu (düzeltilmedi, planlandı)

| # | Bulgu | Neden ciddi |
|---|---|---|
| **1** | **BREAD aksiyonlarında hiç izin kontrolü yok** | Giriş yapmış her kullanıcı, kendisine tanımlanmamış BREAD'lerde create/edit/delete yapabilir. İzinler üretiliyor (`Permission::forBread`) ama **hiç tüketilmiyor**. |
| **2** | **Field `validation` kuralları sessizce düşüyor** | `unique`, `email`, `max`, `min` uygulanmıyor. Duplicate slug ve geçersiz veri kaydedilebiliyor. Gerçek `posts.json`'daki `slug: unique` koruması etkisiz. |
| **3** | **`translatable: true` alanlar `NULL` kaydediliyor** | Kullanıcı veri giriyor, sistem sessizce boş kaydediyor. Veri kaybı, hata yok. |

Ayrıca bir yetki aşımı (IDOR) riski: `BelongsToManyField` istemciden gelen
ID'ye güveniyor (`BelongsToManyField::stored()`).

→ Tam kanıt, dosya:line referansları ve test planları: **`05-tardis-bulgulari-ve-fix-oncelikleri.md`**

### 2.3 Test durumu

- **365 passed (888 assertions)**, `php -l` ve `git diff --check` temiz.
- Bu turda bulunan P0'lar **henüz teste bağlanmadı** — düzeltme öncesi
  regresyon testi yazılmalı.

### 2.4 Commit'ler (push/merge edilmedi)

```
fbe13fb  fix(bread): render every field type and stop save dying on NOT NULL columns
d7a1365  test(bread): cover every field type's control and the edit upload path
```

---

## 3. VOYAGER 1.x vs 2.x — ANA FARKLAR

| Konu | Voyager 1.x | Voyager 2.x |
|---|---|---|
| **Field sayısı** | 23 somut handler + base | 17 PHP field sınıfı (konsol tabanlı) |
| **Field felsefesi** | Handler ince, mantık **Blade view**'da | Mantık **PHP sınıfında**, lifecycle'lı |
| **Field uzantısı** | `FormFieldsRegistered` event | `FormfieldPlugin` kontratı |
| **Doğrulama** | Ayrı add/edit kuralları, tek `getValidationData` | **Field bazlı validation + çevrilebilir mesaj + dizi elemanı doğrulama** |
| **İzin** | Gerçek **Laravel Policy + authorize()** tüketimi | Taban policy, plugin tabanlı authorization |
| **İlişkiler** | Blade view, ilişki satırları düzenlenebilir | `browse/read/edit/update/updated/store/stored` lifecycle |
| **BREAD kayıt** | PHP builder | **JSON** + rollback + tek dosyada tanım |
| **CRUD** | Yok, BREAD builder + ayrı CRUD controller yok | Var |
| **Plugin** | Composer paket + event (yönetim paneli yok) | **Yönetim paneli + 5 tip + 17 kontrat** |
| **Ayrıntı** | Media Manager, Settings, Database Manager, Compass | Media, Settings, Widget, Menu |

**En değerli çıkarım:** Voyager'ın BREAD'i, field mantığını **tek bir
yere (view veya sınıf) toplama** disiplini sayesinde sağlam. Tardis'te
`render()`/`viewData()` yolu var ama **çağrılmıyor** — bu, mimari borcun
kökü ve P1-3'ün çözüm noktası.

---

## 4. ÖNERİLEN ÇALIŞMA PLANI

### Aşama 1 — Güvenlik ve veri bütünlüğü (P0)
Sıra önemli — önce izin, sonra validation, sonra translatable:

1. **BREAD izin kontrolü** — her aksiyonda browse/read/add/edit/delete kontrolü.
2. **Field validation kuralları** — `unique`/`email`/`max`/`min` uygulansın.
3. **Translatable kayıp** — alan tipine göre normalize et.
4. **BelongsToMany kapsam** — `sync()` öncesi yetki doğrula.

Her biri için önce **regresyon testi**, sonra düzeltme.

### Aşama 2 — İlişki ve mimari (P1)

5. **HasMany filtreleme** — yalnız gönderilen satırları güncelle.
6. **`FormfieldManager::field()`** — `[]` yerine gerçek çıktı; blade'de tek
   render noktası. Bu, `render()`/`viewData()` ölü kodunu canlandırır ve
   üçüncü parti field yolunu açar.

### Aşama 3 — İşlevsel (P2/P3)

7. Transaction kapsamı, eksik option'lar, checkbox options,
   çoklu seçim tipleri, ayar sistemi, widget/izin.

---

## 5. KAPSAM VE KAYNAK DOĞRULAMA DURUMU

| Kaynak | Durum |
|---|---|
| `github.com/thedevdojo/voyager` (1.x) | ✅ `1.8` @ `cb56948` (2024-10-14) yeniden okundu; ilk okuma `1.x` branch'i (2022-01) |
| `voyager-docs.devdojo.com` (1.x) | ✅ 38/38 sayfa (2026-10-03 yeniden indirildi; site 1.5'i anlatır) |
| `github.com/voyager-admin/voyager` (2.x) | ✅ `2.x` @ `47fb33b` (2022-10-21; repo o günden beri değişmedi) yeniden okundu |
| `voyager-admin.github.io/voyager/` | ✅ Site, repo docs ile aynı içerik (55 sayfa; 2026-10-03'te 285 dosya birebir doğrulandı) |

**Eksik bırakılmış hiçbir sayfa yok.** Dört kaynağın tamamı `research/`
altında dosya olarak duruyor. Doküman sayfaları `.md` uzantısıyla doğrudan
markdown olarak indirildi (`voyager-docs.devdojo.com` normal HTML'i React
RSC olarak sunuyor; `.md` ve `llms.txt` çalışıyor).

**Doğrulama notu:** 01 ve 02 kaynak notlarıdır; iddiaları kaynak kodla
tekrar teyit edilmelidir. 05'teki bulgular ise çalıştırılan probe'lar ve
grep ile kanıtlanmıştır.

---

## 6. KISITLAR VE DİKKAT EDİLMESİ GEREKENLER

- **`/home/abdurrahman/Lerd/lara` uygulamasına dokunulmadı** — kullanıcı kararı.
- Alt ajan kullanılmadı — araştırma doğrudan yapıldı (kullanıcı talebi).
- Push/merge yapılmadı; commit'ler branch üzerinde bekliyor.
- `docs/voyager-karsilastirma.md` kullanıcıya ait untracked içeriktir —
  bu raporlar onun yerine geçmez, ikisi birleştirilebilir.
