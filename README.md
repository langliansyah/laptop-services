# RuangServis — Sistem Antrean dan Perbaikan Laptop

Aplikasi Laravel untuk mengelola reservasi guest/member, penerimaan unit, diagnosis, persetujuan estimasi, progres teknisi, pembayaran, dan ulasan pelanggan.

## Menjalankan di Laragon (Windows)

### Setup pertama kali

1. Pasang Laragon dan pilih PHP versi **8.3 atau lebih baru** dari menu Laragon. Letakkan atau clone proyek ke `C:\laragon\www\laptop-service`.
2. Buka Laragon dan klik **Start All** agar layanan MySQL menyala. Apache tidak dipakai pada panduan ini; web dijalankan dengan `php artisan serve`.
3. Buat database kosong bernama `laptop_service` di HeidiSQL atau phpMyAdmin. Jangan buat tabel secara manual; perintah migrasi akan membuat tabel aplikasi.
4. Buka **Terminal** dari Laragon, masuk ke folder proyek, lalu pasang dependency dan buat `.env`:

   ```powershell
   cd C:\laragon\www\laptop-service
   composer install
   if (-not (Test-Path .env)) { Copy-Item .env.example .env }
   ```

5. Edit `.env`. Isi koneksi MySQL sesuai Laragon dan tentukan kredensial admin:

   ```dotenv
   APP_NAME=RuangServis
   APP_URL=http://127.0.0.1:8000

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=laptop_service
   DB_USERNAME=root
   DB_PASSWORD=

   ADMIN_EMAIL=admin@contoh.test
   ADMIN_PASSWORD="GantiDenganKataSandiUnikYangKuat!"
   ```

   Laragon biasanya memakai `root` tanpa kata sandi secara lokal; jika konfigurasi MySQL berbeda, sesuaikan `DB_USERNAME` dan `DB_PASSWORD`. Jangan gunakan kredensial contoh untuk situs publik, dan jangan bagikan atau commit `.env`.

6. Di Terminal Laragon yang sama, jalankan:

   ```powershell
   php artisan key:generate
   php artisan migrate --seed
   ```

   `migrate` membuat tabel aplikasi, sedangkan `--seed` membuat akun admin berdasarkan `ADMIN_EMAIL` dan `ADMIN_PASSWORD`. Jika hanya menjalankan `php artisan migrate`, akun admin **tidak** dibuat; untuk membuatnya sesudahnya jalankan `php artisan db:seed`.

### Menjalankan dan masuk

Di Terminal Laragon, dari folder proyek, jalankan:

```powershell
php artisan serve
```

Biarkan terminal tetap terbuka dan buka **http://127.0.0.1:8000**. Halaman masuk admin tersedia di **http://127.0.0.1:8000/masuk**. CSS sudah tersedia di `public/css`; Node.js tidak diperlukan.

Untuk penggunaan berikutnya, buka Laragon dan klik **Start All**, lalu jalankan `php artisan serve` dari folder proyek. Jangan jalankan `migrate:fresh` pada database berisi data karena perintah itu menghapus seluruh tabel dan datanya.

### Membuat akun teknisi

Pendaftaran publik hanya membuat akun member. Untuk membuat teknisi, buka Terminal Laragon kedua, masuk ke folder proyek, lalu jalankan `php artisan tinker`. Di prompt Tinker, buat akun dengan email dan kata sandi unik:

```php
\App\Models\User::create([
    'name' => 'Nama Teknisi',
    'email' => 'teknisi@contoh.test',
    'phone' => '081234567890',
    'role' => 'technician',
    'password' => 'GantiDenganKataSandiUnikYangKuat!',
]);
```

Kata sandi akan di-hash oleh aplikasi. Ketik `exit` untuk keluar; teknisi masuk melalui **http://127.0.0.1:8000/masuk**.

## Alur aplikasi

1. Pelanggan mengisi reservasi tanpa login dan menerima kode antrean `ANT-...`.
2. Admin masuk dan memverifikasi nomor seri ketika unit fisik diterima. Sistem menerbitkan service ID `SRV-...` dan status berubah menjadi `diagnosing`.
3. Admin/teknisi menyimpan diagnosis, biaya, dan estimasi hari kerja. Pelanggan membuka halaman pelacakan memakai kode antrean atau service ID bersama nomor WhatsApp.
4. Perbaikan baru bisa dimulai setelah pelanggan menyetujui estimasi. Jika ditolak, status menjadi `cancelled`.
5. Teknisi memperbarui progres dan menandai unit siap diambil. Admin mengonfirmasi metode pembayaran saat serah terima untuk menyelesaikan servis.
6. Pelanggan dapat mencetak bukti penerimaan/nota dan memberi rating setelah servis selesai. Member dapat mendaftar kapan saja; reservasi baru saat login masuk ke riwayat akunnya.

## Status servis

`waiting_queue` → `diagnosing` → `waiting_approval` → `in_progress` → `ready_for_pickup` → `completed`

Pelanggan dapat mengubah `waiting_approval` menjadi `cancelled`. Perubahan lain dibatasi oleh state machine pada `App\Enums\ServiceStatus`.

## Akun dan akses

- Reservasi, pelacakan, persetujuan biaya, nota, dan feedback dapat digunakan guest.
- Pelacakan guest diverifikasi menggunakan nomor referensi dan nomor WhatsApp; akses lanjutan dibatasi ke sesi browser yang telah memverifikasi pasangan tersebut.
- Member hanya melihat riwayat servis yang dibuat saat masuk akun. Reservasi guest lama tidak otomatis diklaim oleh akun.
- Role `admin` mengelola penerimaan unit dan pembayaran. Role `technician` menangani diagnosis dan progres. Keduanya wajib login.
- Pendaftaran publik selalu membuat role `member`; role staf hanya ditetapkan secara administratif.
- Cara membuat akun teknisi dijelaskan langkah demi langkah pada bagian [Membuat akun teknisi](#membuat-akun-teknisi).

## Pengujian

```powershell
php artisan test
```
