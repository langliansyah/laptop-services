# RuangServis — Sistem Antrean dan Perbaikan Laptop

Aplikasi Laravel untuk mengelola reservasi guest/member, penerimaan unit, diagnosis, persetujuan estimasi, progres teknisi, pembayaran, dan ulasan pelanggan.

## Persyaratan

- PHP 8.3+
- Composer
- SQLite (konfigurasi bawaan) atau database Laravel lain
- Node.js tidak diperlukan untuk menjalankan UI; stylesheet aplikasi tersedia langsung dari `public/css`.

## Menjalankan aplikasi

```powershell
composer install
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate
```

Pastikan `DB_CONNECTION=sqlite` dan file `database/database.sqlite` tersedia. Lalu, siapkan kredensial admin **unik** di `.env`:

```dotenv
ADMIN_EMAIL=admin@domain-anda.test
ADMIN_PASSWORD=ganti-dengan-kata-sandi-kuat
```

Jalankan skema dan buat akun admin:

```powershell
php artisan migrate --seed
php artisan serve
```

Buka `http://127.0.0.1:8000`. Admin masuk melalui menu **Masuk**; role admin dibuat dari nilai `ADMIN_EMAIL` dan `ADMIN_PASSWORD` saat seeding. Teknisi tambahan dapat dibuat dari Laravel Tinker dengan role `technician`. Jangan menggunakan kata sandi demo atau menampilkan kredensial staf ke pelanggan.

Untuk SQLite pada pengaturan awal, file database sudah disertakan. Jika belum ada:

```powershell
New-Item database\database.sqlite -ItemType File
```

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
- Admin dapat membuat akun staf tambahan melalui Tinker: `\App\Models\User::create(['name' => 'Teknisi', 'email' => 'teknisi@domain-anda.test', 'phone' => '081234567890', 'role' => 'technician', 'password' => 'kata-sandi-kuat']);`.

## Pengujian

```powershell
php artisan test
```
