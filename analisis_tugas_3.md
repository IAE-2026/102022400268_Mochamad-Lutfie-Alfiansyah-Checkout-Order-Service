# Analisis Tugas 3 Integrasi Aplikasi Enterprise
# Nama : Mochamad Lutfie Alfiansyah
# NIM : 102022400268
# Kelas : SI4809

# Analisis Endpoint penting: api/v1/orders

Transaksi yang dipilih sebagai fokus implementasi adalah proses pembuatan order dari checkout yang telah memiliki pembayaran dengan status terkonfirmasi melalui endpoint `POST /api/v1/orders`.

Proses ini dipilih karena memiliki peran penting dalam alur bisnis aplikasi. Pada tahap ini, status transaksi berubah dari checkout menjadi order yang bersifat final. Selain itu, proses tersebut melibatkan beberapa komponen sistem yang saling terhubung, seperti validasi pembayaran, pengurangan stok produk, pencatatan audit, serta pengiriman event ke sistem lain.

Beberapa alasan yang menjadikan transaksi ini sebagai transaksi kritis antara lain:

- Menjadi titik perubahan utama dari data checkout menjadi data order.
- Hanya dapat dilakukan apabila pembayaran telah berstatus `confirmed` atau `paid`.
- Mengurangi jumlah stok produk melalui integrasi dengan Product Stock Service.
- Membuat data permanen pada tabel `orders` dan `order_items`.
- Menghubungkan data pembayaran dengan order melalui kolom `order_id` pada tabel `payments`.
- Mengubah status checkout menjadi `converted_to_order`.
- Mencatat aktivitas transaksi ke layanan Legacy SOAP Audit sebagai bentuk kebutuhan audit dan pelacakan transaksi.
- Mengirimkan event `checkout.order.created` melalui RabbitMQ agar dapat diproses oleh layanan atau departemen lain yang membutuhkan informasi tersebut.

Berdasarkan sequence diagram yang telah dibuat, proses dimulai ketika pengguna mengirimkan permintaan pembuatan order melalui API Gateway dengan menyertakan JWT yang diperoleh dari layanan SSO. API Gateway kemudian melakukan validasi token ke Cloud Dosen SSO. Setelah token dinyatakan valid, informasi klaim pengguna diteruskan ke middleware autentikasi dan role lokal untuk menentukan hak akses pengguna berdasarkan data yang tersimpan pada database.

Apabila token tidak valid atau pengguna tidak memiliki role yang sesuai, sistem akan menghentikan proses dan mengembalikan respons `403 Forbidden`. Namun apabila token valid dan pengguna memiliki hak akses yang diperlukan, request diteruskan ke Checkout Order Service dengan membawa informasi `checkout_id` dan `payment_id`.

Selanjutnya Checkout Order Service melakukan validasi payload yang diterima, kemudian mengambil data checkout, item checkout, dan informasi pembayaran dari database. Data tersebut digunakan untuk memastikan bahwa seluruh syarat bisnis telah terpenuhi sebelum order dibuat.

Tahap berikutnya adalah pemeriksaan status pembayaran. Jika pembayaran belum berstatus `confirmed` atau `paid`, sistem akan mengembalikan respons `409 Checkout requires confirmed payment`. Selain itu, sistem juga memeriksa apakah order untuk checkout yang sama sudah pernah dibuat sebelumnya. Jika order telah tersedia, sistem mengembalikan respons `409 Order already exists` sehingga tidak terjadi duplikasi data order.

Apabila seluruh validasi berhasil, Checkout Order Service mulai menjalankan proses utama pembuatan order. Untuk setiap item yang terdapat pada checkout, sistem mengirimkan permintaan ke Product Stock Service untuk mengurangi stok produk sesuai jumlah yang dibeli. Setelah stok berhasil diperbarui, sistem membuat data order baru dengan status `paid` dan menyalin seluruh data item dari checkout ke tabel `order_items`.

Selanjutnya sistem memperbarui data pembayaran dengan menambahkan referensi `order_id`, kemudian mengubah status checkout menjadi `converted_to_order`. Setelah seluruh data berhasil disimpan, sistem memperoleh informasi seperti `order_id` dan `invoice_number` yang akan digunakan pada proses berikutnya.

Sebagai bagian dari kebutuhan audit, Checkout Order Service mengirimkan data transaksi ke Legacy SOAP Audit Dosen dalam format XML. Layanan audit kemudian mengembalikan `ReceiptNumber` yang digunakan sebagai bukti bahwa transaksi telah tercatat pada sistem audit. Nomor tersebut kemudian disimpan pada data order di database.

Setelah proses audit selesai, sistem mempublikasikan event `checkout.order.created` ke RabbitMQ Dosen dalam format JSON. Event tersebut akan diteruskan ke exchange pusat dan didistribusikan kepada department subscriber yang membutuhkan informasi terkait order baru. Dengan mekanisme ini, sistem lain dapat merespons perubahan data tanpa harus melakukan komunikasi langsung dengan Checkout Order Service.

Apabila seluruh tahapan berhasil dijalankan tanpa kesalahan, Checkout Order Service mengembalikan respons `201 Order Created` kepada API Gateway. Gateway kemudian meneruskan respons tersebut kepada client sebagai tanda bahwa order berhasil dibuat dan seluruh proses transaksi telah selesai dijalankan.

## Skema Role Lokal

Dalam implementasi sistem, setiap pengguna memiliki hak akses yang berbeda sesuai perannya masing-masing.

- **Customer** memiliki hak untuk membuat checkout, melakukan pembayaran, dan melanjutkan proses pembuatan order setelah pembayaran berhasil dikonfirmasi.
- **Finance** bertanggung jawab melakukan konfirmasi pembayaran.
- **System** menjalankan proses pembuatan order secara otomatis setelah seluruh persyaratan transaksi terpenuhi.
- **Admin** dapat melihat data order serta melakukan perubahan status order sesuai kebutuhan operasional.

## Catatan Implementasi

Implementasi Tugas 3 pada Checkout Order Service difokuskan pada endpoint `POST /api/v1/orders` sebagai transaksi utama yang merepresentasikan proses bisnis paling penting dalam sistem.

Autentikasi dan otorisasi dilakukan melalui middleware `sso.role`. Middleware ini bertugas membaca Bearer JWT, melakukan validasi token menggunakan skema RS256 dan JWKS yang disediakan oleh layanan SSO, kemudian memetakan pengguna ke role lokal yang tersimpan pada tabel `users`, `roles`, dan `role_user`.

Akses terhadap endpoint pembuatan order dibatasi hanya untuk role tertentu sesuai kebutuhan sistem. Pembatasan ini bertujuan untuk memastikan bahwa hanya pengguna yang memiliki kewenangan yang dapat menjalankan proses tersebut.

Integrasi dengan layanan audit diimplementasikan melalui `LegacyAuditClient`. Komponen ini bertugas mengubah data order ke dalam format XML sesuai spesifikasi layanan SOAP dan mengirimkannya ke server audit. Nomor bukti audit yang diterima akan disimpan pada kolom `audit_receipt_number`.

Sementara itu, publikasi event dilakukan oleh `OrderEventPublisher`. Komponen ini mengirimkan event dalam format JSON ke layanan pusat agar dapat diteruskan ke exchange RabbitMQ yang digunakan oleh berbagai layanan dalam ekosistem aplikasi.

Seluruh integrasi eksternal dirancang menggunakan konfigurasi yang dapat diaktifkan atau dinonaktifkan melalui file `.env`. Dengan pendekatan ini, proses pengembangan dan pengujian lokal tetap dapat dilakukan tanpa harus selalu terhubung ke server eksternal.