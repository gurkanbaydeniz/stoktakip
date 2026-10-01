# StokTakip — Stok & SKT Takip API'si

Kahve zincirleri ve küçük işletmeler için **stok, SKT (Son Kullanma Tarihi) ve irsaliye takibi** yapan REST API.
iOS, Android ve Web istemcilerine hizmet verecek şekilde tasarlanmıştır.

- **Backend:** Laravel 13 (PHP 8.5) REST API
- **Kimlik doğrulama:** Laravel Sanctum (Bearer token)
- **Veritabanı:** Geliştirmede SQLite, üretimde PostgreSQL (Supabase / Render uyumlu)

## Roller

| Rol | Yetkiler |
|---|---|
| `admin` (İşletme Sahibi) | Şirket adını düzenleme, çalışan hesabı açma/silme, tüm stok işlemleri |
| `staff` (Çalışan) | Stok okuma ve stok düşme; kullanıcı/şirket yönetimi yok |

## Veri Modeli

```
Company 1─* User (role: admin|staff)
Company 1─* Product 1─* Batch (SKT'li parti) 1─* StockMovement
Company 1─* StockMovement (in|out|adjustment, işaretli quantity)
Company 1─* Attachment (irsaliye fotoğrafı; StokAI/OCR alanları hazır)
```

Önemli tasarım kararları:

- **Mevcut stok = `SUM(stock_movements.quantity)`**; hareket tablosu değişmez denetim kaydıdır.
- **Parti (batch) kalanı** = o partiye bağlı hareketlerin toplamı → FIFO tüketim ve SKT takibi yapılabilir.
- **`attachments` tablosu** `ocr_status` / `ocr_payload` alanlarıyla baştan eklendi; ilerideki **StokAI** modülü (irsaliye fotoğrafından OCR ile okuma) bu tabloyu işleyecek.

## Kurulum

Gereksinim: PHP >= 8.3, Composer.

```bash
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
touch database/database.sqlite # Windows: type nul > database\database.sqlite
php artisan migrate
php artisan serve             # http://127.0.0.1:8000
```

Testleri çalıştırmak için (bellek-içi SQLite kullanılır, kurulum gerekmez):

```bash
php artisan test
```

## API Uçları (v1)

Tüm uçlar `Accept: application/json` bekler. Korunan uçlarda `Authorization: Bearer <token>` gönderin.

| Metot | Yol | Yetki | Açıklama |
|---|---|---|---|
| POST | `/api/v1/auth/register` | – | İşletme sahibi kaydı (şirketi oluşturur, admin rolü verir) |
| POST | `/api/v1/auth/login` | – | E-posta **veya** kullanıcı adı ile giriş (10 deneme/dk limiti) |
| GET | `/api/v1/auth/me` | token | Oturum sahibi + şirket bilgisi |
| POST | `/api/v1/auth/logout` | token | Geçerli token'ı geçersiz kılar |
| PUT | `/api/v1/company` | admin | Şirket/dükkan adını günceller |
| GET | `/api/v1/company/staff` | admin | Çalışanları listeler |
| POST | `/api/v1/company/staff` | admin | Çalışan hesabı açar (staff rolü) |
| PUT | `/api/v1/company/staff/{id}` | admin | Çalışan adı/kullanıcı adı/şifresini günceller |
| DELETE | `/api/v1/company/staff/{id}` | admin | Çalışan hesabını siler (kendini silemez) |
| GET | `/api/v1/products` | token | Ürünler + güncel stok. Filtreler: `?search=`, `?low_stock=1` |
| POST | `/api/v1/products` | admin | Yeni ürün (ad, barkod, birim, kritik stok sınırı) |
| GET | `/api/v1/products/{id}` | token | Ürün detayı + SKT sıralı partileri |
| PUT | `/api/v1/products/{id}` | admin | Ürün güncelle |
| DELETE | `/api/v1/products/{id}` | admin | Sil (yalnızca hareketi olmayan ürünler — denetim izi) |
| GET | `/api/v1/batches` | token | Partiler. Filtreler: `?product_id=`, `?filter=expiring\|expired`, `?days=` |
| POST | `/api/v1/batches` | admin | **Stok girişi**: SKT'li parti + "in" hareketi (irsaliye no opsiyonel) |
| GET | `/api/v1/batches/{id}` | token | Parti detayı (kalan miktar, SKT'ye kalan gün) |
| GET | `/api/v1/stock-movements` | token | Hareket geçmişi. Filtreler: `?product_id=`, `?type=` |
| POST | `/api/v1/stock-movements` | token | **Stok çıkışı** (`type: out`, FIFO — SKT'si en yakın partiden) veya **sayım düzeltmesi** (`type: adjustment`, admin) |
| GET | `/api/v1/alerts` | token | Uyarılar: `expiring_batches`, `expired_batches`, `low_stock_products`. `?days=` (varsayılan 30) |
| GET | `/api/v1/attachments` | token | Ek listesi. Filtreler: `?batch_id=`, `?kind=waybill\|product_photo\|other`, `?ocr_status=` |
| POST | `/api/v1/attachments` | admin | **Dosya yükleme** (multipart `file`; jpg/png/webp/heic/pdf, maks 10 MB). `kind`, `batch_id` opsiyonel |
| GET | `/api/v1/attachments/{id}` | token | Ek meta bilgisi |
| GET | `/api/v1/attachments/{id}/download` | token | Kimlik doğrulamalı indirme (`?inline=1` ile görüntüleme) |
| DELETE | `/api/v1/attachments/{id}` | admin | Eki ve dosyayı siler |

### Stok kuralları

- Her stok değişikliği **değişmez bir harekete** yazılır; mevcut stok = hareketlerin toplamı.
- Çıkışlar **FIFO** yapılır: SKT'si en yakın parti önce tüketilir (SKT'siz partiler en son).
- Yetersiz stokta çıkış `422` döner; stok asla negatife düşmez.
- Partiler değişmezdir — sayım farkları `adjustment` hareketiyle düzeltilir.

### Örnekler

```bash
# Kayıt (işletme sahibi)
curl -X POST http://127.0.0.1:8000/api/v1/auth/register \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"company_name":"Mola Kahve","name":"Gürkan","email":"gurkan@molakahve.com","username":"gurkan","password":"Gizli1234"}'

# Giriş (e-posta veya kullanıcı adı)
curl -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"login":"gurkan","password":"Gizli1234"}'

# Token ile profil
curl http://127.0.0.1:8000/api/v1/auth/me \
  -H "Accept: application/json" -H "Authorization: Bearer <token>"

# Admin çalışan açar
curl -X POST http://127.0.0.1:8000/api/v1/company/staff \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -H "Authorization: Bearer <admin-token>" \
  -d '{"name":"Barista Ali","email":"ali@molakahve.com","password":"Calisan123"}'
```

## Deployment

### Render (önerilen backend barındırma)

Kök dizindeki `render.yaml` blueprint'i kullanılabilir: PHP servisi + PostgreSQL,
`composer install` sonrası `php artisan migrate --force` çalıştırır.

### Supabase (PostgreSQL)

`.env` içine Supabase bağlantı bilgilerini girin:

```env
DB_CONNECTION=pgsql
DB_HOST=aws-0-<region>.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=<supabase-password>
```

### Vercel

Statik Web arayüzü (Vue/React) Vercel'e, bu API Render'a kurulur;
mobil uygulamalar doğrudan Render URL'sini kullanır.

## Yol Haritası

- [x] Auth: kayıt/giriş/çıkış + rol sistemi (admin/staff)
- [x] Ürün CRUD + barkod + kritik stok sınırı (`/api/v1/products`)
- [x] Parti girişi ve FIFO stok çıkışı (`/api/v1/batches`, `/api/v1/stock-movements`)
- [x] Yaklaşan SKT & kritik stok uyarı ucu (`/api/v1/alerts`)
- [x] İrsaliye fotoğrafı yükleme (`/api/v1/attachments`, multipart; mobil kamera ve web için)
- [ ] Push/eposta bildirimleri (alerts ucundan türetilecek zamanlanmış görev)
- [ ] **StokAI**: `attachments.ocr_*` alanları üzerinden irsaliye OCR okuma
