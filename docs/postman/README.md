# Postman Check - Tugas 3

Import dua file berikut ke Postman:

- `checkout-order-tugas-3.postman_collection.json`
- `checkout-order-tugas-3.postman_environment.json`

Pilih environment `Checkout Order Tugas 3 - Local`, lalu jalankan request secara berurutan.

## Persiapan Service Lokal

Pastikan `.env` sudah berisi:

```env
IAE_API_KEY=KEY-MHS-343
IAE_TEAM_ID=TEAM-01
IAE_CENTRAL_API_KEY=KEY-MHS-343
SSO_ENABLED=true
LEGACY_AUDIT_ENABLED=true
RABBITMQ_ENABLED=true
```

Lalu jalankan:

```bash
php artisan config:clear
php artisan migrate
php artisan serve --port=8002
```

Jika menggunakan Docker:

```bash
docker compose up --build
```

## Urutan Request

1. `01 - IAE Central - Get Warga Token`
   - Body: `{ "email": "warga40@ktp.iae.id", "password": "KtpDigital2026!" }`
   - Token akan otomatis disimpan ke variable `central_token`.

2. `02 - IAE Central - Direct SOAP Audit Check`
   - Mengecek endpoint SOAP dosen langsung.

3. `03 - IAE Central - Direct Message Publish Check`
   - Mengecek endpoint publish message dosen langsung.

4. `04 - Local - Health Check`
   - Mengecek service Laravel lokal aktif.

5. `05 - Local - Create Checkout`
   - Menyimpan `checkout_id`.

6. `06 - Local - Create Payment`
   - Menyimpan `payment_id`.

7. `07 - Local - Confirm Payment`
   - Mengubah payment menjadi `confirmed`.

8. `08 - Local - Create Critical Order`
   - Endpoint kritis Tugas 3.
   - Jika berhasil, response memuat `audit_receipt_number`.

## Cara Manual Jika Tidak Pakai Collection

Gunakan token dari gambar pertama sebagai Bearer token untuk request lokal.

Header untuk semua endpoint lokal:

```text
Accept: application/json
Content-Type: application/json
X-IAE-KEY: KEY-MHS-343
Authorization: Bearer <token_dari_login_sso>
```

### 1. Buat Checkout

```text
POST http://localhost:8002/api/v1/checkouts
```

Body:

```json
{
  "user_id": 1,
  "shipping_address": "Jl. Telekomunikasi No. 1, Bandung",
  "payment_method": "bank_transfer",
  "items": [
    {
      "product_id": 10,
      "quantity": 2,
      "price": 150000
    }
  ]
}
```

Ambil nilai:

```text
data.id
```

Contoh hasilnya misalnya `checkout_id = 3`.

### 2. Buat Payment

```text
POST http://localhost:8002/api/v1/payments
```

Body:

```json
{
  "checkout_id": 3,
  "payment_method": "bank_transfer"
}
```

Ganti `3` dengan `data.id` dari checkout. Ambil nilai:

```text
data.id
```

Contoh hasilnya misalnya `payment_id = 5`.

### 3. Confirm Payment

```text
POST http://localhost:8002/api/v1/payments/confirm
```

Body:

```json
{
  "payment_id": 5,
  "status": "confirmed"
}
```

Ganti `5` dengan `data.id` dari payment.

### 4. Buat Order dari Endpoint Kritis

```text
POST http://localhost:8002/api/v1/orders
```

Body:

```json
{
  "checkout_id": 3,
  "payment_id": 5
}
```

Gunakan ID asli dari response checkout dan payment, bukan selalu `1`.

## Penyebab Error pada Gambar Kedua

Response:

```json
{
  "message": "Validation failed",
  "errors": {
    "checkout_id": [
      "The selected checkout id is invalid."
    ],
    "payment_id": [
      "The selected payment id is invalid."
    ]
  }
}
```

Artinya data dengan `checkout_id: 1` dan `payment_id: 1` tidak ada di database lokal yang sedang dipakai Laravel.

Solusinya:

- Jalankan `POST /api/v1/checkouts` terlebih dahulu.
- Jalankan `POST /api/v1/payments` memakai `checkout_id` dari response checkout.
- Jalankan `POST /api/v1/payments/confirm` memakai `payment_id` dari response payment.
- Baru jalankan endpoint kritis `POST /api/v1/orders`.

## Catatan Jika 403 Role

Endpoint `POST /api/v1/orders` menerima role lokal `customer`, `system`, atau `admin` ketika `SSO_ENABLED=true`.

Token warga dari SSO biasanya tidak membawa claim `role`, sehingga aplikasi memetakannya ke role lokal default `customer`.

Untuk cek lokal sementara tanpa role blocking, set:

```env
SSO_ENABLED=false
```

Lalu jalankan:

```bash
php artisan config:clear
```

SOAP audit dan publish message tetap berjalan selama `LEGACY_AUDIT_ENABLED=true`, `RABBITMQ_ENABLED=true`, dan `IAE_CENTRAL_API_KEY=KEY-MHS-343`.
