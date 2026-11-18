# RuangServis — Sistem Antrean dan Perbaikan Laptop

Aplikasi Laravel untuk mengelola reservasi guest/member, penerimaan unit, diagnosis, persetujuan estimasi, progres teknisi, pembayaran, dan ulasan pelanggan.

## Menjalankan di Laragon (Windows)

### Persiapan pertama kali

1. Pasang dan buka aplikasi Laragon agar PHP dan Composer Laragon dapat digunakan. Dari menu Laragon, pilih PHP versi **8.3 atau lebih baru**. Untuk panduan ini, **tidak perlu menekan tombol Start All**: perintah `php artisan serve` nanti menjalankan server web sendiri, sedangkan SQLite tidak memerlukan MySQL.
2. Letakkan atau clone proyek ke `C:\laragon\www\laptop-service`.
3. Buka **Terminal** dari menu Laragon agar PHP dan Composer Laragon tersedia, lalu pindah ke folder proyek:

   ```powershell
   cd C:\laragon\www\laptop-service
   ```

4. Pasang dependency PHP dan buat file konfigurasi lokal:

   ```powershell
   composer install
   if (-not (Test-Path .env)) { Copy-Item .env.example .env }
   ```

5. Buka `.env` dengan editor teks. Pastikan SQLite digunakan dan isi kredensial admin dengan email serta kata sandi kuat milik Anda:

   ```dotenv
   DB_CONNECTION=sqlite
   ADMIN_EMAIL=admin@contoh.test
   ADMIN_PASSWORD="GantiDenganKataSandiUnikYangKuat!"
   ```

   Jangan gunakan contoh kredensial ini untuk situs publik. Jangan bagikan atau commit file `.env`.

6. Pastikan file database SQLite tersedia, buat kunci aplikasi, lalu jalankan migrasi dan seeder:

   ```powershell
   if (-not (Test-Path database\database.sqlite)) { New-Item database\database.sqlite -ItemType File | Out-Null }
   php artisan key:generate
   php artisan migrate --seed
   ```

   Seeder membuat atau memperbarui akun admin dari `ADMIN_EMAIL` dan `ADMIN_PASSWORD`. Jika keduanya belum diisi, akun admin tidak dibuat. Jika menjalankan seeder lagi, kata sandi admin akan mengikuti nilai terbaru di `.env`.

### Menjalankan aplikasi

Setelah persiapan pertama selesai, jalankan perintah berikut dari Terminal Laragon di folder proyek:

```powershell
php artisan serve
```

Biarkan jendela terminal tetap terbuka, lalu buka **http://127.0.0.1:8000** di browser. Untuk menghentikan server, tekan `Ctrl+C`.

CSS aplikasi sudah tersedia di `public/css`, sehingga Node.js dan proses build frontend tidak diperlukan untuk menjalankan aplikasi.

### Masuk sebagai admin

1. Buka **http://127.0.0.1:8000/masuk**.
2. Masuk menggunakan `ADMIN_EMAIL` dan `ADMIN_PASSWORD` yang diatur di `.env`.
3. Setelah berhasil, akun admin diarahkan ke panel operasional.

### Membuat akun teknisi

Pendaftaran dari halaman publik hanya membuat akun member. Buat akun teknisi melalui Laravel Tinker:

1. Buka jendela Terminal Laragon baru dan masuk ke folder proyek.
2. Jalankan:

   ```powershell
   php artisan tinker
   ```

3. Tempel perintah berikut dengan nama, email, nomor telepon, dan kata sandi unik teknisi:

   ```php
   \App\Models\User::create([
       'name' => 'Nama Teknisi',
       'email' => 'teknisi@contoh.test',
       'phone' => '081234567890',
       'role' => 'technician',
       'password' => 'GantiDenganKataSandiUnikYangKuat!',
   ]);
   ```

   Model akan mengenkripsi kata sandi saat menyimpan. Gunakan email yang belum terdaftar.

4. Ketik `exit` untuk keluar dari Tinker. Teknisi dapat masuk melalui **http://127.0.0.1:8000/masuk** menggunakan email dan kata sandi yang dibuat.

Untuk menjalankan aplikasi kembali di hari berikutnya, buka Laragon, buka Terminal pada folder proyek, lalu jalankan `php artisan serve`. Jangan jalankan `migrate:fresh` pada database yang berisi data karena perintah itu menghapus seluruh tabel dan datanya.

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
