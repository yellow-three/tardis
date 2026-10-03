# Voyager 1.7 ve 2.x — Görünüm, Blade/Vue Sayfaları ve Arayüz Notları

Tarih: 2026-10-03
Kaynak: yerel kopyalar `research/voyager-1.7/` (Bootstrap 3 + jQuery + Vue 2 bileşenleri, 75 Blade view) ve `research/voyager-2.x/` (Vue 3 + Inertia + Tailwind, 128 asset dosyası, yalnızca 2 Blade dosyası). Her ikisi de `.gitignore`'da; bu dosya kod değil **tasarım notudur** — hiçbir şey birebir kopyalanmayacak, "en iyisini al, geliştir".

> Bu çalışma `docs/backlog.md` → *Voyager parity planı*'nın görünüm tarafıdır. Kod/davranış karşılaştırması: `01`–`03`.

---

## 1. İki yaklaşımın özeti

| | **Voyager 1.7** | **Voyager 2.x** | **Tardis (şimdi)** |
|---|---|---|---|
| Teknoloji | Blade + Bootstrap 3 + jQuery + DataTables + select2 + toastr + TinyMCE + Ace; menü ve media için Vue 2 | Vue 3 SPA + Inertia + Tailwind; sunucu yalnızca JSON/props verir (`app.blade.php`, `login.blade.php`) | Blade + Livewire 4 + Alpine + DaisyUI 5 |
| Sayfa üretimi | Sunucu render; **tek büyük view'da tip başına `@if/@elseif` zinciri** (browse 395 satır) | İstemci render; **her formfield kendi Vue bileşeni** (`Formfield.vue` + `Builder.vue`), aynı bileşen `action` prop'u ile browse/read/edit/add/query | Sayfa başına Livewire MFC; formfield başına blade (`resources/views/formfields/*`) |
| Tema | Sass + `primary_color` config'i ile 4 CSS kuralı; koyu mod yok | CSS değişkenleri (`CSS_VARS.md`: accent/bg/card/dropdown/input… her biri `-dark` eşli), `system/dark/light` + cookie | DaisyUI temaları + Vite manifest + FOUC koruması |
| İkon | Özel ikon fontu `voyager-*` | Heroicons (`Icon.vue`) | Heroicons (Blade icons) |
| Yön/i18n | RTL desteği (ayrı css), 34 dil | RTL (`_rtl.scss`), `LocalePicker` her kartta | EN/TR karışık, RTL yok |

**Ortak ders:** Voyager 1'in zayıflığı görünümün mantığı taşımasıdır (tip zinciri, satır içi script, ikon fontu, jQuery); Voyager 2'nin zayıflığı JS'e tamamen bağımlı SPA ve dondurulmuş depo olmasıdır. Tardis'in Livewire/Blade yolu ikisinin arasında doğru yer: **sunucu render + küçük Alpine parçaları**.

---

## 2. Voyager 1.7 — sayfa sayfa notlar

### 2.1 Kabuk (`master.blade.php`, `dashboard/navbar|sidebar`)
- Tek layout: `@yield('page_title'|'page_header'|'content'|'css'|'javascript')`. Sayfa başlığı **`page_header` section'ında `<h1 class="page-title">` + sağda eylem butonları** (Add New, Bulk Delete, Order, soft-delete toggle, mass-action'lar, dil seçici) — bu kalıp her sayfada aynı.
- Üst `navbar` (hamburger + breadcrumb + kullanıcı menüsü) ve sol `side-menu`: marka + **kullanıcı kartı (avatar, ad, e-posta, "Profile" butonu, arka plan görseli)** + `admin-menu` Vue bileşeni (özyinelemeli, açılır alt menü, öğe başına renk/ikon).
- Sidebar durumu `localStorage['voyager.stickySidebar']` ile hatırlanır; ilk boyamadan önce satır içi script ile uygulanır (flash önleme — Tardis'in `theme-boot`'u aynı fikir).
- Ayarlardan gelen markalama: `admin.title`, `admin.description`, `admin.icon_image` (favicon+logo), `admin.loader` (yükleme görseli), `admin.bg_image`; `primary_color` config'i; `additional_css`/`additional_js` config'i.
- Bildirimler: `toastr` + `alerts.blade` (`Session('alerts')`); `#voyager-loader` tam sayfa yükleme katmanı.

### 2.2 BREAD Browse (`bread/browse.blade.php`)
- İki mod: **istemci tarafı DataTables** (varsayılan) ve **`server_side`** (arama formu: *alan seçimi + `contains/equals` + metin*, sütun başlığından sıralama, `paginate` + "Showing x to y of z").
- Toplu seçim sütunu (`select_all`) + **toplu silme modalı**; tek silme modalı; `onclick` yerine veri nitelikli butonlar.
- Satır aksiyonları **Action sınıflarından** gelir (`bread/partials/actions`): politika (`getPolicy`) + `shouldActionDisplayOnRow` + özel nitelikler; `massAction` metodu olanlar üst çubukta toplu aksiyon olur. Varsayılanlar: View/Edit/Delete(+Restore).
- Soft delete: üstte **aç/kapa anahtarı** (`showSoftDeleted`).
- Hücre render'ı: **200+ satırlık `@if($row->type == …)` zinciri** (image, relationship, select_multiple, checkbox → on/off etiketi, color → renkli rozet, text → 200 karaktere kırp, file → indirme bağlantıları, media_picker → ilk 3 görsel + "n more", date → format). Satır başına `details->view_browse|view` ile özel view'a delege edilebilir.
- **Almak:** aksiyon/politika modeli, toplu seçim, tip başına hücre davranışı (renk rozeti, on/off etiketi, kırpma, "+n daha"), soft-delete anahtarı. **Almamak:** tip zinciri (V2'deki gibi alan sınıfına/kendi bileşenine taşınmalı), DataTables/jQuery.

### 2.3 BREAD Edit/Add ve Read
- Tek form, **`panel panel-bordered`** içinde; her alan `form-group col-md-{width ?? 12}` (**12 sütunlu ızgara genişliği**), `legend` satırı ile bölümleme (metin/hizalama/arka plan rengi), `display` seçenekleri, hata mesajı alan altında (`help-block`), üstte toplu hata kutusu.
- Alan çözümü: `view_add|view_edit|view` (alan başına özel view) → `relationship` → `Voyager::formField()`; ardından **`afterFormFields` kancası** (alanın altına eklenti içeriği).
- Çok dillilik: her alan etiketinin yanında gizli girdiler + üstte **dil seçici (EN/TR… radyo düğme grubu)**; tüm alanlar seçilen dile göre gösterilir.
- Read sayfası: aynı düzen, başlıkta Edit / Delete (veya Restore) / Return to list butonları, alanlar tipine göre salt okunur gösterilir.
- Sıralama sayfası (`order.blade`): sürükle-bırak liste (`order_column` + `order_display_column`).
- **Almak:** genişlik ızgarası, `legend`/bölüm başlığı, alan altı hata, alan sonrası kanca, salt-okunur Read düzeni, sıralama sayfası. 

### 2.4 Settings (`settings/index.blade.php`, 521 satır)
- **Grup başına sekme** (ikonlu `nav-tabs`), her ayar bir `panel`: başlıkta anahtar rozeti `group.key`, sağda sırala (yukarı/aşağı), sil, kopyala; ayar tipine göre input (BREAD formfield'leriyle aynı tipler: text, image, file, select, checkbox…).
- Üstte "nasıl kullanılır" kutusu: `setting('group.key')` (geliştirici ipucu, config ile kapatılır).
- Altta **yeni ayar** formu (anahtar, ad, tip, grup). `setting_tab` ile son sekmeyi hatırlar.
- **Almak:** kullanım ipucu + kopyala, tip seçerek ekleme. (Tardis zaten gruplu, arama/İçe-dışa aktarma/doğrulama ile daha ileri.)

### 2.5 Menu builder (`menus/builder.blade.php`)
- `dd` (nestable) sürükle-bırak ağaç + **ekle/düzenle modalı** (başlık [çevrilebilir], URL **veya** route + parametre JSON, hedef, ikon sınıfı, renk), sil modalı, bilgi kutusu ("sürükleyip bırakın").
- Menü adı bazlı çoklu menü (`admin`, ayrı site menüleri).
- **Almak:** nestable ağaç + modal; route/URL ikili; renk/ikon. **Geliştirmek:** JSON bindirme (karar verildi), yerleşik öğeleri gizle/yeniden adlandır, öğe başına izin.

### 2.6 Media manager (`media/manager.blade.php`, 966 satır Vue şablonu)
- Araç çubuğu: Upload, Add folder, Refresh, **Move**, **Delete**, **Crop** (yalnız tek görsel seçiliyken); ilerleme çubuğu; **breadcrumb**, sürükle-bırak yükleme, ızgara/liste, dosya tipine göre ikon (image/video/audio/zip/folder), sağ panel **dosya önizleme/ayrıntısı** (görsel/video/ses önizlemesi, ad, boyut).
- **`media_picker` alan biçimi:** BREAD formunda aynı bileşen gömülü (`hidden_element`, "open/close" ile açılır), seçilen dosyalar sürüklenebilir liste.
- **Almak:** media_picker gömme, move, crop, ayrıntı paneli, breadcrumb (Tardis'te breadcrumb/filtre var; move/crop/picker yok).

### 2.7 BREAD builder (`tools/bread/edit-add.blade.php`, 657 satır)
- Üç katlanabilir `panel`: **BREAD bilgisi** (tablo, tekil/çoğul ad, slug, ikon, model, controller, policy, *generate_permissions*, *server_side*, order/sıralama sütunları, varsayılan arama anahtarı, scope, açıklama) → **alan satırları** (her sütun için *browse/read/edit/add/delete* onay kutuları, tip seçimi, görünen ad, **`details` JSON editörü (Ace)**, sürükle tutamağı) → **ilişkiler** (yeni ilişki modalı).
- **Almak:** "tek tablo satırı = bir alan" yoğun görünüm, 5 görünürlük bayrağı yan yana, sürükle ile sıra, katlanabilir bölümler. **Geliştirmek:** ham JSON editörü yerine tip başına seçenek formu (V2'nin `Builder.vue` bileşenleri gibi).

### 2.8 Database manager, Compass, Roles, Users, Login
- **Database:** tablo listesi (ad + *Browse BREAD / Edit BREAD / Add BREAD / View / Edit / Delete* düğmeleri), **tablo düzenleyici Vue bileşenleri** (sütun satırları, tip seçici, yardımcı düğmeler); silme için onay modalı ve "BREAD'i de siler" uyarısı.
- **Compass:** sekmeler *Resources / Commands / Logs* (kaynak bağlantıları, artisan komut çalıştırıcı, log görüntüleyici). (Tardis'te yok; log ve komut yüzeyi güvenlik riski taşır — **almak istenmeyebilir**, aşağıda soru.)
- **Roles:** `edit-add` — ad/ayırt edici ad + **izin ağacı: tablo adına göre gruplanmış onay kutuları, grup onay kutusu + "Select all / Select none"**.
- **Users:** `edit-add` — ad, e-posta, parola (düzenlemede boş = değişmez ipucu), avatar, **varsayılan rol + ek roller**, kullanıcı locale'i.
- **Login:** markalı kart, e-posta/parola/"remember me", gönderirken düğmede dönen ikon, hata kutusu (`alert-red`).
- **Dashboard:** `dimmers` — widget kutuları (Arrilot widgets): ikon, sayı, başlık, buton; `config('voyager.dashboard.widgets')`.

---

## 3. Voyager 2.x — bileşen bileşen notlar

### 3.1 Kabuk (`Voyager.vue`, `Sidebar.vue`, `Navbar.vue`)
- **Sayfa yükleme çubuğu** (üstte ince, indeterminate), tarayıcı sekme başlığı `page_title - suffix`.
- **Sidebar:** masaüstünde daraltılabilir (`CollapseX` geçişi), mobilde **çekmece** (arka plan karartma + kapatma düğmesi), **alt çubukta koyu mod anahtarı (3 durumlu: system/dark/light) + avatar**, durum **cookie** ile saklanır, `MenuWrapper/MenuItem` ile iç içe menü.
- **Navbar:** daraltma düğmesi + **genel arama** (`Layout/Search.vue`: modal; sonuçlar BREAD başına kartlarda gruplanır, "+n daha" bağlantısı) + kullanıcı **dropdown** (ad, "profili görüntüle", `store.user.items`, ayırıcılar).
- **Bildirim bileşeni** (`Notifications.vue` + `notify.ts`): konum ayarlı, renkli, zaman aşımlı, **onay + özel düğmeler döndüren** (örn. "Dev server yok — Devre dışı bırak"); `axios` interceptor'ı hata mesajını otomatik bildirime çevirir (422 hariç).
- Tüm sayfalar `Card` (başlık + ikon + **`actions` slotu**) içinde; sağ üstte arama kutusu, "Reload", toplu aksiyonlar, `LocalePicker`.

### 3.2 BREAD Browse (`Bread/Browse.vue`, 519 satır)
- Kart başlığında **genel arama**, **soft-delete üç durumlu seçici (show / hide / only)**, **Reload** (dönen ikon), aksiyonlar, dil seçici.
- **Adlandırılmış filtre rozetleri** (`layout.options.filters`: ad, renk, ikon; tıklayınca uygulanır/kalkar) — Builder'da "filters" ile (sütun + operatör `=, !=, >, <, like` + değer **veya model scope**) tanımlanır.
- Tablo: **sütun başlığından sıralama** (yön oku), **sütun altı satır içi arama kutuları** (`searchable` alanlar; hangi tipin hangi arama bileşenini kullandığını formfield belirler), **sıralama sütunu** (yukarı/aşağı okları, `uses_ordering`), toplu seçim (checkbox/radio — `multiple`), **ilişki hücresi:** ilk 3 değer + "+n daha", `link_to` ile ilgili BREAD'e bağlantı.
- Altta: sonuç açıklaması ("x–y / z"), **"Tüm filtreleri temizle"**, **sayfa başına seçici** (10/25/50/100, sonuç sayısına göre), `Pagination`.
- **`fromRelationship` modu:** aynı Browse bileşeni bir ilişki alanında **seçici** olarak gömülür (seç/çoklu seç) — liste bileşeninin yeniden kullanımı.
- **Almak:** tüm yukarıdakiler — özellikle adlandırılmış filtreler, sütun içi arama, üç durumlu soft delete, sayfa başına seçici, "filtreleri temizle", ilişki hücresi, yeniden kullanılabilir liste. `link_to` zaten planlı.

### 3.3 BREAD Edit/Add (`Bread/EditAdd.vue`) ve Read
- **Altı sütunlu genişlik ızgarası** (`w-1/6 … w-full`, `md:` ile responsive), her alan kendi `Card`'ında (başlık gösterilebilir/gizlenebilir), **alan başına `Alert` ile hata listesi** (animasyonlu açılma), `LocalePicker`, geri bağlantısı (`prevUrl`).
- Aynı formfield bileşeni `action` (`browse/read/edit/add/query`) alır → **tek bileşen, beş bağlam**. Repeater alanı aynı `EditAdd`'i iç içe kullanır (`fromRepeater`).
- **Almak:** tek formfield / çok bağlam fikri (Faz 2 lifecycle ile aynı), genişlik ızgarası, alan başına inline hata, repeater içinde iç içe form.

### 3.4 Layout kavramı (V2'nin en değerli fikri)
- Bir BREAD'in **birden fazla adlandırılmış layout'u** olur: `type: list` (liste) ve `type: view` (form/okuma). `Builder/Browse.vue` her BREAD için "n list, n view" gösterir.
- **View layout builder** (`Builder/View.vue`): **sürükle-bırak** alan kartları (tutamak), kartta **− / + ile genişlik (1/6 adım)**, **SlideIn** yan çekmecede alan seçenekleri (sütun, başlık, etiket, doğrulama kuralları [kural + çevrilebilir mesaj], sınıflar, `translatable`).
- **List layout builder** (`Builder/List.vue`): sütunlar (hesaplanmış/computed dahil), `searchable`, `orderable`, varsayılan sıralama, `link_to`, **filtreler** (operatör, değer/scope), aksiyonlar.
- Aksiyon bazlı layout: create, edit ve read **farklı layout** kullanabilir.
- **Almak:** layout kavramı (şu an tek `layout` bloğu var), sürükle-bırak + genişlik ızgarası, yan çekmece seçenekleri. **Not:** bu, JSON tanım şemasını genişletir (BREAD tek kaynak kararıyla uyumlu).

### 3.5 Builder (BREAD yönetimi) (`Builder/List|Browse`)
- Tablo listesi: tablo, slug, tekil/çoğul ad, model, **list sayısı, view sayısı**; satırda **Browse, Backup (+ yedekten geri yükle dropdown'ı), Edit, Delete**. Tardis'te yedek/rollback var; **liste/layout sayısı** ve "Backup" düğmesi yok.

### 3.6 Settings (`Settings.vue`)
- Kart başlığında arama, **Kaydet**, **"Ayar ekle" dropdown'ı (formfield tipine göre 2 sütun)** + "daha fazla formfield → Plugins ekranı" bağlantısı, "Grup ekle".
- Gruplar **rozet** olarak (`Grup (n)`), tıklayınca süzer; doğrulama hata uyarıları; **seçili ayar için kullanım bilgisi** (`Voyager::setting('key')`, çift tıkla kopyala).

### 3.7 Plugins (`Plugins.vue`, 582 satır) — Tardis'in plugin ekranının hedefi
- Arama + **yalnız etkin / yalnız pasif** filtresi, **tür**, sürüm, **etkinleştir/devre dışı (onaylı)**, **tercihler (preferences) paneli**, **tema önizleme**, README, depo/web sitesi, **güncelleme denetimi** ("check for updates / newest version"), **yükleme süresi**, "kayıtlı ama kurulu değil" temizleme, hata durumları (`error_loading_plugins`).

### 3.8 Media (`UI/MediaManager.vue`, 792 satır)
- Klasörler, çoklu seçim, yükleme (hata mesajları), "klasör var" kontrolü, yol kopyalama, **küçük resimler (thumbnails)**; aynı bileşen `MediaPicker` alanında ve ayrı sayfada kullanılır; `Filter\Media` plugin'i listeyi süzer.

### 3.9 Tema ve tasarım dili
- **Tasarım jetonları CSS değişkenleri:** accent (`bg/border/text/hover/focus/active`), `bg`, `card`, `code`, `description`, `dropdown`, `input`, scrollbar… hepsi `-dark` eşli; Tailwind `theme()` ile üretilir; `_accent.scss` tek kaynaktan vurgu rengini değiştirir.
- Mikro etkileşimler: `Fade`, `Collapse`, `SlideLeft` geçişleri, tooltip direktifi, tıklama-dışı kapatma, ikon dönme (reload), `lazy-load`.
- UI bileşen seti: `Alert, Badge, Card, Collapsible, ColorPicker, DateTime(Range), Draggable, Dropdown, IconPicker, JsonEditor, KeyValueForm, LanguageInput, LocalePicker, MarkdownView, MediaManager, Modal, Notifications, Pagination, SlideIn, Slider, Slug, TagInput, Toggle`.
- **Almak:** `Card + actions slotu`, `Badge` filtre/grup rozeti, `SlideIn` çekmece, `IconPicker`, `ColorPicker`, `KeyValueForm`, `JsonEditor`, 3 durumlu tema anahtarı + kalıcı sidebar durumu, yükleme çubuğu, bildirim sistemi (onay düğmeli).

---

## 4. Tardis'in bugünkü durumu (karşılaştırma için)

- **Var:** DaisyUI 5 kabuğu (daraltılabilir sidebar, header + genel arama, tema anahtarı, `page-header` bileşeni), BREAD index (arama + 15'li sayfalama; **sıralama/filtre/toplu işlem/soft-delete anahtarı yok**), create/edit/read sayfaları (tek `form` dizisi; **genişlik ızgarası yok**), BREAD builder (adımlı sihirbaz; en az *Configure* ve *Review* adımları var), Settings (grup + arama + içe/dışa aktarma), Media browser (breadcrumb, filtre, seçim, zip), Plugins (açık/kapalı), Roles/Permissions (ayrı sayfalar), Database Explorer, Users (rol ata), Activity log.
- **Eksik/zayıf (görünüm tarafı):** kullanıcı kartı + marka ayarları (başlık/logo/favicon/loader), toast/bildirim sistemi (şimdi `session('message')` alert), sayfa yükleme çubuğu, adlandırılmış filtreler, sütun içi arama, sıralama, toplu aksiyon çubuğu, üç durumlu soft-delete, sayfa başına seçici, ilişki hücresi, izin ağacı UI'ı (gruplu + tümünü seç), media picker/move/crop, nestable menü builder, dashboard widget kartları (yok), plugin ayrıntı/tercih/önizleme, çok dilli form sekmeleri/seçici, genişlik ızgarası + `legend`/bölüm başlığı, sürükle-bırak builder, TR/EN karışık aria metinleri.

---

## 5. Karar/İlke notları (plana girenler)

1. **Sunucu render + Alpine/Livewire (kullanıcı onayladı):** V2'nin SPA'sı alınmaz — bkz. `07` §8. Etkileşim ihtiyacı (sürükle-bırak, nestable) küçük Alpine eklentisiyle çözülür.
2. **Bileşen kütüphanesi:** `Card(actions)`, `Badge`, `SlideIn`, `Modal`, `Dropdown`, `Notifications`, `Pagination`, `IconPicker`, `ColorPicker`, `Toggle` — Blade anonim bileşenleri (`x-tardis::…`) + DaisyUI karşılıkları. Sayfalar çıplak HTML yerine bunları kullanır.
3. **Tek formfield, çok bağlam:** her formfield browse/read/edit/add/query için tek sözleşme sunar (V2) ama render'ı Blade view'dan yapar; V1'in tip zinciri geri gelmez.
4. **Layout kavramı:** BREAD tanımı `list` ve `view` layout'ları taşır (en az: browse/read/edit/add ayrı). JSON şeması genişler; geriye dönük okuma için dönüştürücü yazılır (2.0.0'da serbest kırma kararı).
5. **Tasarım jetonları:** DaisyUI tema değişkenleri korunur; V2'nin accent değişkeni fikri DaisyUI `--color-primary` ile karşılanır (ayrı jeton sistemi kurulmaz). Marka ayarları (başlık, logo, favicon, yükleme görseli) **Settings** grubu `appearance` olur.
6. **Yetki:** her yeni ekran ability + `boot()` kapısı; liste aksiyonları Action politikasıyla (V1) çalışır.
7. **Compass benzeri araçlar (kullanıcı kararı):** kısıtlı sürüm Faz 8'e alındı — bkz. `07` §8.

---

## 6. Faz planına etkisi (özet; ayrıntı `docs/backlog.md`)

| Faz | Eklenen/değişen görünüm işi |
|---|---|
| 1 Panel i18n | + RTL desteği değerlendirmesi; aria/başlık metinleri anahtarlara |
| **1b (yeni) Tasarım sistemi** | `Card(actions)`, Badge, SlideIn, Modal, Dropdown, bildirim sistemi (toast + onaylı), sayfa yükleme çubuğu, sidebar kullanıcı kartı + kalıcı durum, 3 durumlu tema anahtarı, marka ayarları |
| 2 Alan sistemi | + tek sözleşme/çok bağlam (`browse/read/edit/add/query`), genişlik ızgarası, `legend`/bölüm, alan altı hata, repeater içinde iç içe form |
| 3 BREAD liste | + adlandırılmış filtre rozetleri (operatör + scope), sütun içi arama, sıralama, üç durumlu soft-delete, sayfa başına, "filtreleri temizle", ilişki hücresi + `link_to`, yeniden kullanılabilir liste (ilişki seçici), sıralama sayfası |
| **3b (yeni) Layout + Builder UX** | `list`/`view` layout'ları, sürükle-bırak + genişlik, yan çekmece seçenekleri, builder tablo listesi (layout sayıları + Backup) |
| 4 Plugin | + plugin ekranı: arama, etkin/pasif filtre, sürüm/tür, preferences paneli, tema önizleme, güncelleme denetimi (isteğe bağlı) |
| 5 Media | + picker, move, crop, ayrıntı paneli, thumbnail |
| 6 Menü + widget | + nestable menü ağacı, dashboard widget kartları (genişlik sınıfları), roller için gruplu izin ağacı |

---

## 7. Netleşen kararlar (2026-10-03)

| Konu | Karar |
|---|---|
| Layout modeli | Çoklu adlandırılmış layout (V2): `list` + `view`, aksiyon bazlı form layout'u |
| Marka | Settings `appearance` grubu; görseller media'dan |
| Avatar | Baş harf + isteğe bağlı `tardis.user.avatar_column`; Gravatar yok |
| Dashboard | Varsayılan kartlar (BREAD sayıları, son aktivite, plugin widget'ları) + `dashboard.json` düzeni |
| Compass benzeri araçlar | Kısıtlı sürüm alınır (log görüntüleyici, izin listeli komut çalıştırıcı, sistem sayfası) — Faz 8 |
| SPA | Hayır, Livewire + Alpine kalır |
