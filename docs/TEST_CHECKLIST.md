# Tardis 0.1.0 — test listesi

Bu liste **0.1.0 test yayını** içindir (ön sürüm: üretimde kullanmayın, API 1.0'dan önce değişebilir). Her madde bir kutucuk: **yap → beklenen sonucu gör → işaretle**. Beklenenden farklı bir şey görürseniz en sonda "Hata bildirimi" şablonuyla yazın. "Bilinen sınırlar" bölümündekiler hata değildir.

## 0. Hazırlık

- [ ] Kurulum README'deki gibi çalışıyor: `composer config repositories.tardis vcs https://github.com/yellow-three/tardis`, `composer require yellow-three/tardis:^0.1`, `php artisan tardis:install --email=siz@ornek.com`
- [ ] `php artisan tardis:doctor` hata vermeden bitiyor (uyarı olabilir; **kırmızı hata** olmamalı). `--json` çıktısı geçerli JSON.
- [ ] `tardis:install` ikinci kez çalıştırılınca hata vermiyor, çift kayıt oluşturmuyor.
- [ ] `/admin` adresi giriş sayfasına yönlendiriyor; sayfa **stilli** açılıyor (CSS ve ikonlar yüklü).
- [ ] Tarayıcı konsolunda (F12) kırmızı hata yok.

Test verisi için isteğe bağlı: `tardis.system.demo.enabled=true` yapıp yalnızca **local/testing** ortamında `Tardis\Database\Seeders\DemoSeeder` çalıştırılabilir. Gerçek veriniz olan bir ortamda çalıştırmayın.

## 1. Giriş ve oturum

- [ ] Doğru bilgilerle giriş yapılıyor, panel açılıyor.
- [ ] Yanlış parola ile giriş reddediliyor, anlaşılır mesaj var, parola alanı temizleniyor.
- [ ] Art arda çok sayıda yanlış denemeden sonra giriş sınırlanıyor (rate limit).
- [ ] Çıkış (Logout) çalışıyor; sonra geri tuşuyla panel sayfası **açılmıyor**.
- [ ] "Şifremi unuttum" ve şifre sıfırlama sayfaları açılıyor (e-posta gönderimi host'un ayarına bağlı).
- [ ] Giriş yapmadan `/admin/dashboard`, `/admin/media/1/edit`, `/admin/users` doğrudan açılınca giriş sayfasına yönleniyor.

## 2. Yetkiler (en önemli bölüm)

Hesap açma: Users ekranından (veya `php artisan tardis:admin kullanici@ornek.com --create`) iki kullanıcı hazırlayın: bir **süper admin**, bir de **rolsüz** kullanıcı. Sonra Roles ekranından kısıtlı bir rol (ör. "Editör") oluşturup sadece birkaç izin verin.

- [ ] **Rolsüz kullanıcı** giriş yapınca panel açılmıyor (403 / yetki yok).
- [ ] Kısıtlı rolde olmayan bir ekrana adres çubuğundan gitmek (ör. `/admin/settings`) **403** veriyor. Sidebar'da o ekranın bağlantısı da **görünmüyor**.
- [ ] Bir kullanıcının izni, sayfa açıkken kaldırılırsa (başka sekmeden) o sayfadaki bir sonraki işlem (kaydet, sil) **403** veriyor.
- [ ] Roller ekranında izin seçici gruplu: grup başlıkları, BREAD kaynakları, "Tümünü seç / kaldır" düğmesi çalışıyor. Kaydedince rolün izinleri kalıcı.
- [ ] Süper admin her yere girebiliyor.
- [ ] İzin tablosunda şu izinler var: `access admin`, `manage settings`, `manage plugins`, `manage users`, `manage roles`, `manage database`, `manage bread`, `view activity`, `manage menus`, `manage dashboard`, `manage appearance`, `view system`, `view logs`, `run commands`, medya izinleri (`browse/upload/rename/move/delete media`) ve her BREAD için `browse/read/edit/add/delete {slug}`.

## 3. Dashboard

- [ ] Kartlar görünüyor (Kullanıcılar, BREAD'ler, Medya, Aktivite, Son aktivite).
- [ ] Bir kartın izni olmayan kullanıcıda o kart **görünmüyor**.
- [ ] `manage dashboard` izni olanda "Özelleştir" düğmesi var: kart gizleme, sola/sağa taşıma, genişlik seçimi (3/4/6/8/12) çalışıyor; sayfayı yenileyince düzen **korunuyor**; "Varsayılanlara dön" işe yarıyor. İzni olmayanda düğme **yok**.
- [ ] Dar ekranda (telefon genişliği) kartlar alt alta diziliyor.

## 4. BREAD yönetimi (builder)

- [ ] `/admin/bread` mevcut BREAD'leri listeliyor. "Yeni" → `/admin/bread/create` builder açılıyor.
- [ ] Bir model seçince alanlar tablodan algılanıyor; alan tipi, etiket, doğrulama, `browse/read/edit/add` görünürlüğü değiştirilebiliyor.
- [ ] Geçersiz slug (boşluk, `../`, özel karakter) **reddediliyor**. Ayrılmış bir slug (`users`, `settings`, `bread`…) **reddediliyor**.
- [ ] Kaydedince BREAD sidebar'a ekleniyor ve `/admin/{slug}` çalışıyor. (Not: `route:cache` kullanıyorsanız cache'i yeniden oluşturun.)
- [ ] Her kayıtta yedek oluşuyor; yönetim ekranından eski yedeğe dönülebiliyor.
- [ ] Builder'da çevrilebilir alan açılabiliyor ve dil listesi seçilebiliyor.
- [ ] **Layout:** liste ve detay layout'ları tanımlanabiliyor; alanlar sürüklenerek ya da **klavye ile** yeniden sıralanabiliyor; genişlik (6'lık ızgara) değişiyor.
- [ ] Mevcut (eski) bir BREAD JSON dosyası, layout tanımlamadan da sorunsuz çalışıyor.

## 5. BREAD kayıtları (CRUD ve liste)

Bir BREAD üzerinde:

- [ ] **Ekle**: zorunlu alan boş bırakılınca alanın altında hata görünüyor, kayıt oluşmuyor. Geçerli verilerle kayıt oluşuyor, listeye düşüyor, "oluşturuldu" mesajı var.
- [ ] **Düzenle**: değerler doğru geliyor; değiştirip kaydedince listede güncelleniyor.
- [ ] **Oku (detay)**: tüm alanlar doğru görünüyor.
- [ ] **Sil**: onay isteniyor; silince listeden gidiyor.
- [ ] **Arama**: yalnızca "aranabilir" alanlarda arıyor; `%` ve `_` karakterleri **düz karakter** olarak aranıyor (her şeyi getirmiyor).
- [ ] **Sıralama**: başlığa tıklayınca artan, tekrar tıklayınca azalan; ok işareti doğru. Sıralanamaz işaretli sütun tıklanamıyor.
- [ ] **Sayfa boyutu** (10/15/25/50/100) değişiyor; sayfa numaraları doğru.
- [ ] **Filtreler**: sütun filtresi ve tanımlı adlandırılmış filtreler sonucu daraltıyor, temizlenince geri geliyor.
- [ ] **İlişki kolonları** (varsa) doğru değeri gösteriyor; çok kayıtlı listede "yüksek sorgu sayısı" uyarısı çıkıyorsa not edin.
- [ ] **Soft delete** (modelde varsa): "Silinenler" seçimi (gizle / dahil / yalnız silinenler), **Geri yükle**, **Kalıcı sil** (onaylı) çalışıyor.
- [ ] **Toplu işlem**: birkaç satır işaretlenince çubuk çıkıyor; toplu silme yalnızca işaretlenenleri siliyor. Yetkin olmayan satıra işlem yapılamıyor.
- [ ] **Yetki**: `browse` izni olmayan kullanıcı listeyi açamıyor; `delete` izni olmayanda sil düğmesi **yok** ve adresle zorlanınca 403.
- [ ] Başka bir kaydın id'sini adres çubuğunda değiştirip erişmeye çalışmak (ör. scope ile gizlenen kayıt) **404/403** veriyor.

## 6. Alan tipleri

Her tipi bir BREAD'de ekleyip **ekle → düzenle → oku → boş bırak → geçersiz değer** akışıyla deneyin.

| Tip | Özellikle bakılacak |
|---|---|
| text, textarea, markdown, code_editor, slug | kısa/uzun metin, Türkçe karakter, çok uzun değer |
| number, slider | min/max/step uyuyor mu, ondalık değer |
| select, select_multiple, radio, checkbox | seçenekler tanımdan geliyor, çoklu seçim kaydediliyor ve geri geliyor |
| toggle | açık/kapalı kalıcı; boş kayıtta varsayılan |
| date, datetime, time | tarayıcı seçici, kayıt ve geri okuma doğru saat/tarih |
| password | düzenlemede **boş bırakınca eski parola korunuyor**; değer asla ekrana basılmıyor |
| file | yükleme, mime kısıtı, boyut kısıtı; yanlış tür **reddediliyor** |
| tags, simple_array | ekle/çıkar, boş öğeler kaydedilmiyor; `min`/`max` sınırı uyuyor |
| color, hidden | renk seçici; hidden alan formda görünmüyor ama kaydediliyor |
| belongs_to_many | arama kutusu, seçim kaydı; ilişkili BREAD'in kapsamı dışındaki kayıtlar **listelenmiyor**; ilişkili BREAD'e `browse` izni olmayanda seçenek **boş** |
| has_many | bilgi kutusu görünüyor |
| media_picker | aşağıdaki Medya bölümü |
| rich_text | biçimlendirme (kalın, liste, bağlantı) kaydediliyor; `<script>` temizleniyor |
| repeater | satır ekle/sil/yeniden sırala, boş satır, iç alan doğrulaması |
| coordinates | enlem/boylam aralık dışı değerler reddediliyor, kayıt ve geri okuma |

- [ ] Zorunlu alan hataları alanın **altında** görünüyor, sayfanın üstünde değil (çevrilebilir alanlarda hangi dilde olduğu belli).
- [ ] Doğrulama mesajları arayüz dilinde.

## 7. Çok dilli içerik

Çevrilebilir bir alan (ör. `title`) ve `tardis.locales = ['en','tr']` ile:

- [ ] Formda dil **sekmeleri** var; sekme değişince önceki dilin yazdığı **kaybolmuyor**.
- [ ] Kaydedince her dilin değeri korunuyor; düzenlemede geri geliyor.
- [ ] **Doğrulama modu** (`tardis.translation.validation`): `all` iken `required` bir alanın **bir dili boşsa kayıt reddediliyor** ve hata veren dilin sekmesine geçiş bağlantısı çıkıyor. `active` yapınca yalnızca açık dil doğrulanıyor.
- [ ] Listede ve detayda **aktif dil** gösteriliyor; o dilde değer yoksa başka dilden alınan değerde küçük **dil rozeti** çıkıyor.
- [ ] BREAD adı/açıklaması ve alan etiketleri dil haritası (`{"en":"Posts","tr":"Yazılar"}`) olarak tanımlanınca arayüz diline göre değişiyor; eski düz metin tanımlar **bozulmuyor**.
- [ ] Menü başlıkları dil haritasıyla çalışıyor.
- [ ] Panel dilini header'dan değiştirince (EN/TR) tüm ekranlar çevriliyor; seçim **kalıcı** (çıkış-giriş sonrası da).

## 8. Medya

- [ ] Medya kitaplığı (`/admin/media`) açılıyor; yükleme, klasör oluşturma, yeniden adlandırma, taşıma, silme çalışıyor.
- [ ] Her işlem kendi iznine bağlı: yalnızca `browse media` olanda yükle/sil/yeniden adlandır **yok**; adresle zorlanınca 403.
- [ ] İzin verilmeyen dosya türü **yüklenmiyor**. Çift uzantılı ya da `.php` dosya reddediliyor.
- [ ] Klasör adında `..` veya `/` **kabul edilmiyor**.
- [ ] Medya düzenleme (`/admin/media/{id}/edit`): ad, alt metin, başlık, açıklama kaydediliyor. Silince **dosya diskten de** siliniyor. Giriş yapmadan veya izinsiz kullanıcıyla açılmıyor.
- [ ] **media_picker** alanı: "Gözat" düğmesi kitaplığı açıyor; seçilen dosya alana yazılıyor; tek/çoklu seçim (min/max) uyuyor; mime kısıtı dışındaki dosyalar seçilemiyor; "Temizle" çalışıyor; aynı sayfada iki seçici varsa **seçim yalnızca kendi alanına** yazılıyor.
- [ ] Dosya adı şablonu (`{uid}/{date:Y/m}/{random:8}` gibi) tanımlanınca dosya o yola kaydediliyor; tanımsız bir `{token}` yazılırsa **reddediliyor**.

## 9. Menü, temalar, görünüm

- [ ] **Menü oluşturucu** (`manage menus`): öğe gizleme/gösterme, yeniden adlandırma, bölüm değiştirme, yukarı/aşağı taşıma sidebar'a **anında** yansıyor. "Varsayılanlara dön" her şeyi eski haline getiriyor.
- [ ] **Özel bağlantı**: `https://…` ve `/yol` kabul ediliyor; `javascript:`, `data:`, `//x.com` ve boş başlık **reddediliyor**. "Yeni sekmede aç" çalışıyor. Yetki girilince başkaları görmüyor.
- [ ] **Temalar** (`manage appearance`): özel tema oluşturma (ad, etiket, şema, renkler), kaydedince seçicide görünüyor; **yerleşik temanın üzerine yazılamıyor** (kopyalanabiliyor); geçersiz renk (`red;}`) **reddediliyor**; özel tema silinebiliyor.
- [ ] Header'daki tema düğmesi **Açık → Koyu → Sistem** sırasıyla dönüyor; seçim yenilemede ve başka cihazda **korunuyor**. Sayfa ilk açılırken yanlış tema yanıp sönmüyor.
- [ ] Settings → görünüm: varsayılan mod/tema ve **özel CSS** kaydediliyor ve tüm sayfalara uygulanıyor. Özel CSS'e `</style><script>alert(1)</script>` yazınca **script çalışmıyor**.
- [ ] Toast bildirimleri, yükleme çubuğu, modal ve yan çekmece düzgün açılıp kapanıyor (Esc ile kapanıyor).

## 10. Settings, kullanıcılar, aktivite, arama

- [ ] **Settings**: gruplar arasında gezinme, değer kaydetme, farklı tipler (metin, sayı, seçim, toggle, renk, görsel). `manage settings` izni olmayan giremiyor.
- [ ] **Users**: kullanıcıya rol atama/kaldırma çalışıyor; kendi `super-admin` rolünü kaldırıp panelden kilitlenme riskine karşı uyarı/engel var mı not edin.
- [ ] **Aktivite günlüğü**: BREAD'de oluşturma/güncelleme/silme kayıtları düşüyor. **Parola ve gizli alanlar log'a yazılmıyor.**
- [ ] **Arama** (`/admin/search`): yetkin olmayan kaynaklar sonuçta **çıkmıyor**.

## 11. Plugin'ler

- [ ] Plugins ekranı eklentileri, türlerini, sürümlerini listeliyor.
- [ ] Etkinleştir/devre dışı bırak çalışıyor ve **yenilemeden sonra da kalıcı**. Kimlik doğrulama ve yetkilendirme plugin'leri **kapatılamıyor** (kilit simgesi).
- [ ] Ayar ekranı olan plugin'de "Ayarlar" düğmesi var, diyalog açılıyor; kapalı plugin'de yok.
- [ ] `php artisan tardis:plugins`, `tardis:plugins disable <ad>`, `tardis:plugins enable <ad>` çalışıyor.
- [ ] `php artisan tardis:make-plugin deneme --with-assets` bir plugin iskeleti üretiyor; üretilen `composer.json` geçerli.

## 12. Sistem araçları

- [ ] **Sistem** sayfası doctor sonucunu gösteriyor; her kontrolün durumu (ok/uyarı/hata) ve ipucu var. `view system` izni olmayan giremiyor.
- [ ] **Log görüntüleyici** (`view logs`): yalnızca `storage/logs` içindeki `.log` dosyaları listeleniyor; satır sayısı değişiyor; çok büyük dosyada sayfa donmuyor. Adres çubuğuyla dosya adına `../` verilince erişilemiyor.
- [ ] **Komut çalıştırıcı** (`run commands`) **varsayılan kapalı**: kapalıyken çalıştırma düğmesi pasif. Config'te açıp (`enabled=true`, `environments`, `allowlist`) izin listesine örneğin `tardis:doctor => ['--json']` ekleyince yalnızca o komut/seçenek çalışıyor; listede olmayan komut veya seçenek **reddediliyor**. Hem çalıştırılan hem **reddedilen** denemeler aktivite günlüğüne düşüyor.
- [ ] Üretim ortamında (`APP_ENV=production`) komut çalıştırıcı, config açık olsa bile **kapalı**.

## 13. Database Explorer

- [ ] Tablolar listeleniyor; `tardis_*`, `migrations`, `sessions`, `jobs` gibi sistem tabloları **gizli**.
- [ ] Yeni tablo oluşturma, tablo düzenleme (sütun ekle/değiştir), model üretme çalışıyor.
- [ ] Gizli bir tabloya adres çubuğundan (`/admin/database/migrations/edit`) gidilince erişim **yok**.
- [ ] Silme/değiştirme işlemleri onay istiyor. (Yedekli bir veritabanında deneyin.)

## 14. Tarayıcı, ekran ve erişilebilirlik

- [ ] Chrome, Firefox ve Safari'de ana akışlar (giriş, BREAD CRUD, medya) çalışıyor.
- [ ] Telefon ve tablet genişliğinde sidebar kapanıp açılıyor, tablolar yatay kaydırılabiliyor, formlar taşmıyor.
- [ ] Yalnızca **klavye** ile giriş, menü gezinmesi ve form doldurma yapılabiliyor; odak görünür.
- [ ] Koyu ve açık temada metin okunuyor (kontrast), her iki temada da tüm ekranlar bozuk görünmüyor.
- [ ] Ekran okuyucu için düğmelerde ve sıralama başlıklarında anlamlı etiket var (basit kontrol).

## 15. Güvenlik denemeleri (kendi test ortamınızda)

- [ ] Bir metin alanına `<script>alert(1)</script>` ve `"><img src=x onerror=alert(1)>` girin: liste, detay, düzenleme, menü ve aktivite günlüğünde **çalışmamalı**, düz metin görünmeli.
- [ ] Adres çubuğunda kayıt id'sini başkasınınkiyle değiştirin; yetkiniz yoksa 403/404.
- [ ] Giriş yapmadan `POST` isteği (ör. `/admin/logout`, `/admin/preferences/theme`) CSRF/yetki hatası veriyor.
- [ ] `/admin/_assets/xxxxxxxxxxxxxxxx.css` rastgele bir hash ile **404** veriyor.
- [ ] Başka bir kullanıcının tercih ettiği tema/dil bilgisini değiştirmek mümkün değil.

## Bilinen sınırlar (hata değil)

- Menü ve dashboard sıralaması yukarı/aşağı düğmeleriyle; **sürükle-bırak yok**. İç içe menü yok.
- Paket Packagist'te değil; kurulum yalnızca GitHub deposundan.
- `required` çevrilebilir alan varsayılan olarak **her dilde** dolu olmalı (`tardis.translation.validation` ile gevşetilir).
- Yeni bir BREAD oluşturunca `route:cache` kullanılıyorsa cache'in yeniden oluşturulması gerekir.
- Settings'teki varsayılan light/dark tema seçimi yalnızca yerleşik iki temayı listeliyor; özel temalar oraya bağlı değil.
- Medya kırpma (crop) yok.
- Komut çalıştırıcı bilerek kapalı gelir.

## Hata bildirimi

Her hata için şunları yazın:

```
Başlık:            (tek cümle)
Bölüm / madde:     (ör. 5. BREAD kayıtları → Soft delete)
Ortam:             tarayıcı + sürüm, ekran genişliği, APP_ENV, tema (açık/koyu), dil
Kullanıcı / rol:   (süper admin / rolsüz / "Editör" rolü …)
Adımlar:           1) … 2) … 3) …
Beklenen:          
Gerçekleşen:       
Ekran görüntüsü:   (varsa)
Konsol / log:      F12 konsol hatası veya storage/logs/laravel.log satırı
```

Güvenlik açığı olabileceğini düşündüğünüz bulguları herkese açık issue yerine doğrudan proje sahibine iletin.
