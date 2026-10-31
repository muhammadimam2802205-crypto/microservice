# UTS Microservices — Order Service

Repositori ini berisi implementasi **order-service** (Laravel 13 / PHP 8.4) sebagai bagian dari
sistem microservices e-commerce bersama product-service, cart-service, dan review-service.

Fokus pengerjaan UTS ada pada **order-service**: checkout dari keranjang, detail pesanan,
daftar pesanan per user (dengan filter status), dan pembayaran pesanan. Service lain dipakai
apa adanya sesuai kontrak yang sudah ada dan **tidak diubah perilakunya**.

---

## 1. Arsitektur

### 1.1 Gambaran sistem

```
                          ┌──────────────────────┐
   Client (curl/Postman)  │   order-service      │  :3003  (Laravel 13 / PHP 8.4)
   ──────────────────────▶│                      │
                          │  Controller ─▶ Service ─▶ Client HTTP
                          └───────┬──────────┬───┴──────────┬─────────────┐
                                  │          │              │             │
                     GET/DELETE   │          │ GET          │             │
                     /carts/{id}  │          │ /products/{id}             │
                                  ▼          ▼              ▼             ▼
                        ┌──────────────┐  ┌────────────────┐  ┌──────────────────┐
                        │ cart-service │  │ product-service│  │   order-db       │
                        │   :3001      │  │    :3000       │  │  MySQL 8 (:3307) │
                        │  Laravel     │  │  Node/Express  │  └──────────────────┘
                        └──────┬───────┘  └───────┬────────┘
                               │                  │
                        ┌──────▼───────┐  ┌───────▼────────┐
                        │   cart-db    │  │  product-db    │
                        │ Postgres 16  │  │   MySQL 8      │
                        └──────────────┘  └────────────────┘

                        ┌──────────────────┐       ┌──────────────┐
                        │  review-service  │──────▶│  review-db   │
                        │   :3002 Flask    │       │  MongoDB 7   │
                        └────────┬─────────┘       └──────────────┘
                                 │ GET /products/{id}
                                 ▼
                          product-service :3000
```

**4 service + 4 database**, semuanya dijalankan oleh satu `docker-compose.yml` di root:

| Service | Port host | Teknologi | Database |
|---|---|---|---|
| `product-services` | 3000 | Node.js / Express | `product-db` — MySQL 8 (3306) |
| `cart-service` | 3001 | Laravel | `cart-db` — PostgreSQL 16 (5432) |
| `review-service` | 3002 | Python / Flask | `review-db` — MongoDB 7 (27017) |
| `order-service` | **3003** | Laravel / PHP 8.4 | `order-db` — MySQL 8 (host **3307**) |

### 1.2 Lapisan order-service

```
routes/api.php
  └── app/Http/Controllers/OrderController.php   (HTTP: status code, message, errorResponse)
        ├── app/Http/Requests/*                  (validasi; ApiFormRequest → 422 JSON)
        ├── app/Services/OrderService.php        (logika bisnis, transaksi, eager loading)
        │     ├── app/Models/Order.php           (hasMany items)
        │     ├── app/Models/OrderItem.php       (belongsTo order)
        │     ├── app/Clients/ProductServiceClient.php
        │     └── app/Clients/CartServiceClient.php
        └── app/Exceptions/*                     (exception kasus bisnis & kegagalan service)
```

Aturan yang dijaga: **controller tanpa query/aturan bisnis**, **service tanpa HTTP**,
client HTTP terisolasi di `app/Clients/`.

### 1.3 Alur checkout (`POST /orders`)

1. `StoreOrderRequest` memvalidasi `user_id`.
2. `OrderService::checkout()` mengambil keranjang dari cart-service (`GET /carts/{userId}`).
3. Keranjang kosong → `409`.
4. Untuk setiap item, ambil produk (`GET /products/{id}`):
   - produk tidak ada → `409` (bukan 503);
   - stok kurang → `409`;
   - service tidak bisa dihubungi / 5xx → `503`.
5. Hitung total dan siapkan **snapshot** (`product_name`, `unit_price`, `quantity`, `subtotal`).
6. `DB::transaction` menyimpan `orders` + `order_items` sekaligus (gagal satu → rollback).
7. Best effort: `DELETE /carts/{userId}`. Bila gagal hanya dicatat `Log::warning`; response tetap `201`.
8. Response `201` berisi pesanan lengkap dengan item.

---

## 2. Menjalankan Sistem

### 2.1 Prasyarat

- Docker Desktop (dengan `docker compose` v2) — **satu-satunya prasyarat** untuk menjalankan
  seluruh sistem.
- Opsional untuk pengembangan lokal tanpa Docker: PHP 8.4 + Composer, Node.js 22, Python 3.

### 2.2 Menjalankan seluruh sistem

```bash
git clone https://github.com/muhammadimam2802205-crypto/microservice.git
cd mi-pdsku

# Build image dan jalankan 8 container (4 service + 4 database)
docker compose up --build
```

Tidak perlu menyalin `.env` atau mengisi `APP_KEY` secara manual: seluruh environment variable
penting sudah diisi di `docker-compose.yml` (termasuk `APP_KEY` demo untuk cart-service dan
order-service).

**Migrasi berjalan otomatis.** Image order-service menjalankan `php artisan migrate --force`
dengan retry terbatas (maksimal 20 percobaan × 3 detik) sebelum `php artisan serve`.
Database product diinisialisasi otomatis dari `database/init.sql` (mount ke
`/docker-entrypoint-initdb.d`).

### 2.3 URL tiap service

| Service | URL | Cek cepat |
|---|---|---|
| order-service | http://localhost:3003 | `curl http://localhost:3003/health` |
| product-service | http://localhost:3000 | `curl http://localhost:3000/healt` (perhatikan ejaan `healt` milik service asli) |
| cart-service | http://localhost:3001 | `curl http://localhost:3001/carts/1` |
| review-service | http://localhost:3002 | `curl http://localhost:3002/health` |

> **Catatan penting:** `order-service` didaftarkan dengan `apiPrefix: ''`, sehingga endpoint
> **tanpa** awalan `/api`. Gunakan `http://localhost:3003/orders`, bukan `/api/orders`.

### 2.4 Melihat status & log

```bash
docker compose ps
docker compose logs -f order-service
```

### 2.5 Reset data

```bash
# Hentikan semua container dan hapus volume database (data kembali bersih)
docker compose down -v

# Nyalakan lagi dari nol
docker compose up --build
```

`docker compose down -v` menghapus **seluruh** data: pesanan, keranjang, produk (akan di-seed
ulang dari `database/init.sql` saat product-db dibuat ulang), dan review.

---

## 3. Endpoint

### 3.1 Ringkasan endpoint order-service

| Method | Endpoint | Fungsi | Sukses | Gagal |
|---|---|---|---|---|
| GET | `/health` | cek service hidup | 200 | — |
| POST | `/orders` | checkout dari isi keranjang | 201 | 422, 409, 503, 500 |
| GET | `/orders/{id}` | detail pesanan + item | 200 | 404, 500 |
| GET | `/orders/user/{userId}` | daftar pesanan user (terbaru di atas) + filter opsional `?status=` | 200 | 422, 500 |
| PATCH | `/orders/{id}/pay` | ubah status `PENDING` → `PAID` | 200 | 404, 409, 500 |

Ketentuan umum:

- Struktur response: `message`, `data`, dan `errors` (khusus 422).
- `unit_price`, `subtotal`, `total_amount` dikirim sebagai **angka**.
- `data.items[]` hanya memuat 5 field: `product_id`, `product_name`, `unit_price`, `quantity`, `subtotal`.
- Id non-angka di path → **404 JSON** (route memakai `->whereNumber()`).
- Detail teknis pada 500/503 hanya muncul bila `APP_DEBUG=true`.

### 3.2 Contoh request & response

#### `GET /health`

```bash
curl http://localhost:3003/health
```

```json
200 { "status": "ok", "service": "order-service" }
```

#### `POST /orders` — sukses

Prasyarat: keranjang user 7 berisi produk 1 (qty 2) dan produk 2 (qty 1).

```bash
curl -X POST http://localhost:3003/orders \
  -H "Content-Type: application/json" \
  -d '{"user_id": 7}'
```

```json
201
{
  "message": "Pesanan berhasil dibuat",
  "data": {
    "id": 1,
    "user_id": 7,
    "status": "PENDING",
    "total_amount": 1850000,
    "items": [
      { "product_id": 1, "product_name": "Keyboard Mekanik", "unit_price": 750000, "quantity": 2, "subtotal": 1500000 },
      { "product_id": 2, "product_name": "Mouse Wireless", "unit_price": 350000, "quantity": 1, "subtotal": 350000 }
    ],
    "created_at": "2026-10-30T10:00:00.000000Z",
    "updated_at": "2026-10-30T10:00:00.000000Z"
  }
}
```

#### Kegagalan `POST /orders` yang diuji

**a. Body tidak valid → 422**

```bash
curl -X POST http://localhost:3003/orders -H "Content-Type: application/json" -d '{}'
```

```json
422
{
  "message": "Data yang dikirim tidak valid",
  "errors": { "user_id": ["Field user_id wajib diisi"] }
}
```

`{"user_id": "abc"}` atau `{"user_id": 0}` juga 422 (`errors.user_id`).

**b. Keranjang kosong → 409**

```bash
curl -X POST http://localhost:3003/orders -H "Content-Type: application/json" -d '{"user_id": 7}'
```

```json
409 { "message": "Keranjang kosong, tidak ada yang bisa di-checkout" }
```

**c. Stok tidak cukup → 409**

Keranjang user 7 berisi produk 4 (`Monitor Gaming 24 Inch`, stok 8) dengan quantity 11.

```json
409 { "message": "Stok Monitor Gaming 24 Inch tidak mencukupi: diminta 11, tersedia 8" }
```

**d. Produk sudah tidak ada → 409**

Produk id 2 dihapus dari product-service, tetapi keranjang user 7 masih memuat id 2.

```json
409 { "message": "Produk Mouse Wireless sudah tidak tersedia" }
```

**e. Service pendukung mati → 503**

```bash
docker compose stop product-services
curl -X POST http://localhost:3003/orders -H "Content-Type: application/json" -d '{"user_id": 7}'
```

```json
503 { "message": "Product service tidak dapat dihubungi, coba lagi nanti" }
```

Sama untuk `docker compose stop cart-service` → 503 dengan pesan cart service.
Detail teknis ditambahkan pada body bila `APP_DEBUG=true`, dan penyebabnya tercatat di
`docker compose logs order-service`.

#### `GET /orders/{id}`

```bash
curl http://localhost:3003/orders/1
```

```json
200
{
  "message": "Berhasil mengambil pesanan",
  "data": {
    "id": 1, "user_id": 7, "status": "PENDING", "total_amount": 1850000,
    "items": [ { "product_id": 1, "product_name": "Keyboard Mekanik", "unit_price": 750000, "quantity": 2, "subtotal": 1500000 } ],
    "created_at": "...", "updated_at": "..."
  }
}
```

- `GET /orders/99` → `404 { "message": "Pesanan dengan id 99 tidak ditemukan" }`
- `GET /orders/abc` → `404 { "message": "Endpoint atau data tidak ditemukan" }` (bukan HTML/500)

#### `GET /orders/user/{userId}`

```bash
curl http://localhost:3003/orders/user/7
```

```json
200
{
  "message": "Berhasil mengambil daftar pesanan",
  "data": [
    { "id": 2, "user_id": 7, "status": "PAID",    "total_amount": 1850000, "items": [ ... ], "created_at": "...", "updated_at": "..." },
    { "id": 1, "user_id": 7, "status": "PENDING", "total_amount": 150000,  "items": [ ... ], "created_at": "...", "updated_at": "..." }
  ]
}
```

- Urutan **terbaru di atas** (`created_at desc`, lalu `id desc`).
- User tanpa pesanan → `200 { "message": "...", "data": [] }` (bukan 404).
- Filter status:

```bash
curl "http://localhost:3003/orders/user/7?status=PENDING"
curl "http://localhost:3003/orders/user/7?status=PAID"
curl "http://localhost:3003/orders/user/7?status=SHIPPED"
```

```json
422
{
  "message": "Data yang dikirim tidak valid",
  "errors": { "status": ["Status harus PENDING atau PAID"] }
}
```

#### `PATCH /orders/{id}/pay`

```bash
curl -X PATCH http://localhost:3003/orders/1/pay
```

```json
200
{
  "message": "Pesanan berhasil dibayar",
  "data": { "id": 1, "user_id": 7, "status": "PAID", "total_amount": 150000, "items": [ ... ], "created_at": "...", "updated_at": "..." }
}
```

- Membayar pesanan yang sudah `PAID` → `409 { "message": "Pesanan berstatus PAID, hanya pesanan PENDING yang dapat dibayar" }`
- Id tidak ada → `404 { "message": "Pesanan dengan id 99 tidak ditemukan" }`
- Id non-angka → `404 { "message": "Endpoint atau data tidak ditemukan" }`

### 3.3 Endpoint service lain yang dipakai order-service

Hanya dibaca, tidak diubah:

| Service | Endpoint | Dipakai untuk |
|---|---|---|
| product-service | `GET /products/{id}` | nama, harga (`"750000.00"` → float), stok |
| cart-service | `GET /carts/{userId}` | daftar `items[].product_id` + `items[].quantity` |
| cart-service | `DELETE /carts/{userId}` | mengosongkan keranjang setelah checkout (best effort) |

Ringkas endpoint lain milik service tersebut (tidak dipakai order-service):
product-service `GET/POST/PUT/DELETE /products`, cart-service `POST /carts`,
`PUT /carts/items/{id}`, `DELETE /carts/items/{id}`.

---

## 4. Environment Variable

### 4.1 order-service (`order-service/.env.example`)

| Variabel | Contoh | Keterangan |
|---|---|---|
| `APP_NAME` | `order-service` | |
| `APP_ENV` | `local` | |
| `APP_KEY` | *(kosong di example)* | diisi kunci demo lewat compose |
| `APP_DEBUG` | `true` | `false` saat produksi → detail teknis disembunyikan |
| `APP_URL` | `http://localhost:3003` | |
| `LOG_CHANNEL` | `stack` (`stderr` di compose) | |
| `LOG_LEVEL` | `debug` | |
| `DB_CONNECTION` | `mysql` | wajib MySQL |
| `DB_HOST` | `127.0.0.1` (`order-db` di compose) | |
| `DB_PORT` | `3306` | port host dipetakan ke **3307** |
| `DB_DATABASE` | `orderdb` | |
| `DB_USERNAME` | `appuser` | |
| `DB_PASSWORD` | `apppassword` | kredensial demo, bukan rahasia produksi |
| `PRODUCT_SERVICE_BASE_URL` | `http://localhost:3000` (`http://product-services:3000` di compose) | |
| `PRODUCT_SERVICE_TIMEOUT` | `5` | detik, di-clamp maksimal 5 |
| `CART_SERVICE_BASE_URL` | `http://localhost:3001` (`http://cart-service:3001` di compose) | |
| `CART_SERVICE_TIMEOUT` | `5` | detik, di-clamp maksimal 5 |

### 4.2 Variabel penting di `docker-compose.yml`

| Service | Variabel kunci |
|---|---|
| `order-service` | `APP_ENV=local`, `APP_DEBUG=true`, `APP_KEY=<demo base64>`, `LOG_CHANNEL=stderr`, `LOG_LEVEL=debug`, `DB_HOST=order-db`, `DB_DATABASE=orderdb`, `PRODUCT_SERVICE_BASE_URL=http://product-services:3000`, `CART_SERVICE_BASE_URL=http://cart-service:3001` |
| `cart-service` | `APP_KEY=<demo base64>`, `APP_ENV=local`, `APP_DEBUG=true`, `LOG_CHANNEL=stderr`, `DB_HOST=cart-db` (pgsql), `PRODUCT_SERVICE_BASE_URL=http://product-services:3000` |
| `review-service` | `HOST=0.0.0.0`, `PORT=3002`, `MONGO_URI=mongodb://appuser:apppassword@review-db:27017/?authSource=admin`, `PRODUCT_SERVICE_URL=http://product-services:3000` |
| `product-services` | `DB_HOST=product-db`, `DB_NAME=productdb`, `DB_USER=appuser`, `DB_PASSWORD=apppassword`, `PORT=3000` |
| Database | `MYSQL_ROOT_PASSWORD`, `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`, `POSTGRES_*`, `MONGO_INITDB_ROOT_*` (kredensial demo) |

---

## 5. Keputusan Desain & Keterbatasan

### 5.1 Keputusan desain

1. **Snapshot** nama & harga produk disimpan di `order_items`. Perubahan harga produk tidak
   memengaruhi pesanan lama.
2. **Transaksi database**: `DB::transaction` menyimpan `orders` + `order_items` sekaligus;
   bila satu gagal, semuanya di-rollback.
3. **Cart clear best effort**: pesanan yang sudah tersimpan tidak dibatalkan bila pengosongan
   keranjang gagal; kegagalan dicatat sebagai `Log::warning` dan response tetap `201`.
4. **Stok hanya diperiksa, tidak dikurangi** (di luar lingkup UTS).
5. **Timeout 5 detik, tanpa retry otomatis** pada panggilan antar service — retry berisiko
   menggandakan checkout.
6. **Pemetaan error eksplisit** lewat exception khusus: 404/409/503/500 terpisah; detail
   teknis hanya saat `APP_DEBUG=true`.
7. **Client HTTP di `app/Clients/`** agar lapisan "client" terpisah dari service.
8. **Eager loading `with('items')`** pada `find`, `listForUser`, dan `pay` untuk menghindari N+1.
9. **Angka pada response**: `unit_price`/`subtotal`/`total_amount` diserialisasi sebagai angka
   walau tersimpan sebagai `decimal`.
10. **Tanpa automated test** (keputusan pengerjaan); verifikasi lewat checklist manual dan demo.

### 5.2 Keterbatasan yang disadari

- **Tanpa idempotency key** pada `POST /orders`: request ganda dapat membuat dua pesanan.
- **Tidak ada pengurangan stok** → kemungkinan oversell pada request bersamaan.
- **Tanpa outbox/message broker**: pengosongan keranjang yang gagal tidak diulang otomatis;
  keranjang bisa berisi ulang.
- **Tanpa retry** antar service: gangguan sesaat langsung menjadi `503`.
- **Tanpa autentikasi/otorisasi**: `user_id` dipercaya dari body/path (di luar lingkup UTS).
- **Tanpa pagination** pada daftar pesanan user.
- **Tanpa automated test**; regresi dijaga manual.

### 5.3 Deviasi sadar dari contoh soal

- **Data demo memakai `database/init.sql` apa adanya.** Empat produk pertama yang dipakai
  dalam skenario pengujian:

  | id | nama | harga | stok |
  |---|---|---|---|
  | 1 | Keyboard Mekanik | 750000.00 | 10 |
  | 2 | Mouse Wireless | 350000.00 | 10 |
  | 3 | Headset Gaming | 800000.00 | 15 |
  | 4 | Monitor Gaming 24 Inch | 2100000.00 | 8 |

  File juga memuat produk demo tambahan (id 5–10). Kasus "stok kurang" didemokan dengan
  **id 4, quantity 11** (stok 8).
- **Format timestamp** mengikuti default Laravel (ISO-8601 UTC, mis.
  `2026-10-30T10:00:00.000000Z`); nilai pada soal hanya ilustrasi.
- **Folder `car-service` di-rename menjadi `cart-service`** agar sesuai struktur repo pada soal;
  isi dan kontraknya tidak diubah.
- **Tanpa awalan `/api`** pada route order-service (`apiPrefix: ''`), konsisten dengan
  cart-service dan contoh soal.

---

## 6. Struktur Repo

```
mi-pdsku/
├── docker-compose.yml          # orkestrasi 4 service + 4 database
├── database/
│   └── init.sql                # skema + data awal product-db
├── product-service/            # Node.js/Express  — :3000
│   ├── Dockerfile
│   ├── app.js, server.js
│   ├── routes/, controllers/, models/, config/
├── cart-service/               # Laravel           — :3001
│   ├── Dockerfile, docker-compose.yml
│   ├── app/, routes/, database/
├── review-service/             # Flask             — :3002
│   ├── Dockerfile, docker-compose.yml, requirements.txt
│   ├── run.py, app/
├── order-service/              # Laravel           — :3003  (fokus UTS)
│   ├── Dockerfile, .dockerignore
│   ├── app/
│   │   ├── Clients/            # ProductServiceClient, CartServiceClient
│   │   ├── Exceptions/         # exception bisnis & kegagalan service
│   │   ├── Http/Controllers/   # OrderController
│   │   ├── Http/Requests/      # ApiFormRequest, StoreOrderRequest, ListOrdersRequest
│   │   ├── Models/             # Order, OrderItem
│   │   └── Services/           # OrderService
│   ├── routes/api.php
│   ├── database/migrations/
│   └── bootstrap/app.php       # apiPrefix '', handler 404 JSON
└── uts/
    ├── plan.md                 # rencana pengerjaan
    ├── tasks.md                # daftar task + status commit
    └── Soal_UTS_Microservices_Order_Service.docx
```

---

## 7. Ringkasan Riwayat Pengerjaan

Pengerjaan dibagi menjadi task berurutan, **satu task = satu commit = satu push**
(lihat `uts/tasks.md` untuk SHA tiap commit):

| Task | Isi |
|---|---|
| T01–T04 | Scaffold Laravel, konfigurasi env/DB, migration, model & relasi |
| T05–T07 | Exception, client HTTP, Form Request |
| T08–T10 | `GET /health`, logika checkout, `POST /orders` |
| T11–T12 | `GET /orders/{id}`, `GET /orders/user/{userId}` |
| T13 | Filter `?status=PENDING\|PAID` (422 bila tidak valid) |
| T14 | `PATCH /orders/{id}/pay` |
| T15 | Dockerfile order-service (PHP 8.4 + pdo_mysql) |
| T16 | Rename `car-service` → `cart-service` |
| T17 | `docker-compose.yml` gabungan |
| T18 | README root (dokumen ini) |
| T19–T22 | Verifikasi manual, uji clone baru, video demo, pengumpulan |
