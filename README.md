# Helpdesk IT Support

Portal ticketing internal untuk pelaporan kendala IT, penugasan teknisi, percakapan, SLA, dan pengelolaan akun.

## Stack dan kebutuhan

- PHP 8.3 atau lebih baru
- Laravel 13
- Composer 2.x
- MySQL atau MariaDB
- Ekstensi PHP `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, dan `xml`

Laravel 13 membutuhkan PHP minimal 8.3. Lihat [catatan rilis resmi Laravel](https://laravel.com/framework/docs/13.x/releases) untuk matriks dukungan versi.

## Menjalankan di Laragon

1. Pastikan Laragon memilih PHP 8.3. Pada instalasi ini executable yang tervalidasi adalah `C:\laragon\bin\php\PHP-8.3.30\php.exe`.
2. Jika `.env` belum ada, salin `.env.example` menjadi `.env`, lalu sesuaikan `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD`.
3. Pasang dependency dan siapkan aplikasi:

   ```powershell
   if (-not (Test-Path .env)) { Copy-Item .env.example .env }
   $env:Path = 'C:\laragon\bin\php\PHP-8.3.30;' + $env:Path
   composer install
   $php = 'C:\laragon\bin\php\PHP-8.3.30\php.exe'
   if (-not (Select-String -Path .env -Pattern '^APP_KEY=.+')) { & $php artisan key:generate }
   & $php artisan migrate
   & $php artisan storage:link
   ```

   Migration helpdesk bersifat guarded: tabel lama tidak dihapus dan kolom SLA ditambahkan hanya jika belum ada.
4. Arahkan DocumentRoot virtual host Laragon ke folder `public`, yaitu `C:\laragon\www\itsupport\public`.
5. Alternatif untuk development cepat:

   ```powershell
   & 'C:\laragon\bin\php\PHP-8.3.30\php.exe' artisan serve
   ```

Jika perintah `php` atau `composer` belum masuk PATH, jalankan executable PHP 8.3 yang dipilih Laragon secara eksplisit. Jangan memakai `C:\laragon\bin\php\php.ini` yang masih menunjuk ke ekstensi XAMPP; gunakan `php.ini` milik versi PHP aktif.

## Akun dan data lokal

Data pengguna lama tetap berada di database `itsupport`. Password tidak disimpan di repository. Gunakan password yang sudah dibuat saat provisioning lokal atau buat akun baru melalui menu admin.

Migration tidak menjalankan reset, seed, atau penghapusan data. Backup native hasil pemindahan tersedia di luar project bila diperlukan.

## Fitur utama

- Ticket baru dengan kategori, prioritas, deskripsi, SLA, dan lampiran JPG, PNG, WebP, HEIC, atau HEIF.
- Penugasan dan perubahan status oleh Tim IT atau Admin.
- Komentar, aktivitas, catatan penyelesaian, konfirmasi requester, dan status read-only untuk ticket final.
- Filter, export CSV, ringkasan analitik, polling notifikasi, serta light dan dark mode.
- Pengelolaan akun oleh Admin dengan validasi password, perubahan role, dan aktivasi akun.
- Layout responsif dengan skip link, fokus keyboard, label form, dan header keamanan HTTP.
- PWA installable dengan manifest, icon aplikasi, service worker, prompt install, dan halaman offline.

## PWA dan batasan offline

Project ini menggunakan PWA online-first. Browser desktop tetap mengakses URL Laravel yang sama, sedangkan Android Chrome atau browser yang mendukung installability dapat memasang `IT Support` ke Home Screen dengan mode `standalone`.

Service worker hanya menyimpan asset publik dan app shell: CSS, JavaScript, icon, manifest, serta halaman offline. Navigasi HTML, halaman login, endpoint API, route ticket, route user, upload, dan response yang mungkin berisi session atau data ticket tidak disimpan di cache. Dengan begitu cache tidak menjadi sumber data lintas akun setelah logout atau login dengan akun lain.

Saat koneksi terputus, aplikasi menampilkan status offline dan navigasi baru memakai `offline.html` jika server tidak dapat dijangkau. Pengiriman ticket tidak diantrekan secara diam-diam dan tidak akan dianggap berhasil tanpa response Laravel. Push notification belum diaktifkan karena memerlukan kontrak event backend, penyimpanan subscription, permission flow, dan worker pengiriman terpisah.

### Menjalankan dan memeriksa PWA

1. Jalankan aplikasi melalui Laragon atau `artisan serve`. Untuk installability di device, gunakan HTTPS; `localhost` tetap mendapat pengecualian untuk development.
2. Buka `/` atau `/login`, login dengan akun kantor, lalu periksa prompt `Pasang IT Support` jika browser mengirimkan `beforeinstallprompt`. Prompt otomatis hanya ditampilkan sekali; jika ditutup, pilihan install tetap tersedia lewat menu `Pasang aplikasi` di sidebar.
3. Di Chrome DevTools, buka Application lalu periksa Manifest, Service Workers, dan mode offline. Pastikan request dashboard atau ticket tidak muncul di Cache Storage.
4. Di iPhone Safari, gunakan Share lalu `Add to Home Screen`. Safari tidak menyediakan `beforeinstallprompt`, sehingga aplikasi menampilkan instruksi manual sekali dan menyediakan menu `Pasang aplikasi` untuk membukanya lagi.
5. Uji update dengan menaikkan `CACHE_VERSION` di `public/sw.js`. Worker baru menunggu persetujuan tombol `Perbarui`, kemudian halaman dimuat ulang setelah worker baru aktif.

PWA tidak menggantikan authentication Laravel. Cookie session, CSRF, guard role, dan logout tetap berjalan melalui request server biasa. Browser hanya menyimpan penanda bahwa prompt install sudah ditutup; password, token, dan response ticket tidak ditaruh di `localStorage` maupun cache privat.

## Struktur project

```text
itsupport/
|-- app/
|   |-- Http/Controllers/       # Endpoint web dan adapter kompatibilitas
|   |-- Http/Middleware/        # Role, CSRF legacy, dan security headers
|   |-- Http/Requests/          # Validasi input per use case
|   |-- Models/                 # Model Eloquent untuk tabel helpdesk
|   |-- Services/               # Transaksi ticket/user dan data dashboard
|   `-- Support/legacy.php      # Adapter schema lama untuk boundary kompatibilitas
|-- bootstrap/                  # Bootstrap aplikasi Laravel
|-- config/helpdesk.php         # Status, role, kategori, SLA, dan batas upload
|-- database/migrations/        # Migration guarded untuk schema existing
|-- public/assets/              # CSS, JavaScript, PWA helper, SVG, dan Chart.js
|-- public/icons/               # Icon manifest 192, 512, dan maskable
|-- public/manifest.webmanifest # Metadata installability
|-- public/sw.js                 # Service worker online-first
|-- public/offline.html          # Fallback saat navigasi gagal
|-- resources/views/            # Layout, halaman auth, dan partial dashboard
|   |-- layouts/app.blade.php
|   `-- dashboard/partials/     # Sidebar, topbar, flash, dan section dashboard
|-- routes/web.php              # Route canonical dan route kompatibilitas index.php
|-- storage/app/public/uploads/ # Lampiran yang dapat diakses melalui storage link
|-- DESIGN.md                   # Arah visual dan kontrak UI
`-- README.md                   # Panduan ini
```

Route `/index.php` dan POST legacy dipertahankan sementara agar bookmark atau form lama tidak langsung rusak. Feature baru sebaiknya memakai named route Laravel, FormRequest, controller, service, dan Eloquent model.

Data dashboard disiapkan oleh `App\Services\DashboardViewData`, lalu dirender melalui layout `resources/views/layouts/app.blade.php` dan partial di `resources/views/dashboard/partials/`.

Salinan sumber native sebelum migrasi dipindahkan ke `C:\laragon\www\itsupport-native-backup-20260912`, di luar project agar tidak ikut menjadi source runtime.

## Perintah pemeriksaan

```powershell
php artisan route:list
php artisan view:cache
php artisan test
```

Sebelum deploy, aktifkan HTTPS, gunakan akun database khusus dengan password kuat, matikan `APP_DEBUG`, dan jadwalkan backup database serta `storage/app/public/uploads`.
