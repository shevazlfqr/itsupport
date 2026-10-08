# Panduan Aplikasi IT Support Helpdesk

Dokumen ini menjelaskan perilaku aplikasi dari route, konfigurasi, controller, service, model, Blade, dan service worker yang ada di repository. Ini bukan klaim bahwa seluruh skenario production atau seluruh perangkat sudah diuji.

## Ringkasan

IT Support Helpdesk adalah portal internal untuk menerima permintaan bantuan, mengatur antrean teknisi, mencatat percakapan dan aktivitas, serta mengelola akun. Branch utama repository adalah `master`.

| Bagian | Implementasi |
| --- | --- |
| Backend | PHP `^8.3`, Laravel `^13.17` (dependency lokal saat dokumentasi dibuat: 13.31.0) |
| Tampilan | Blade, CSS, JavaScript; Chart.js lokal untuk grafik |
| Database | MySQL/MariaDB yang dikonfigurasi melalui `.env` |
| Authentication | Session Laravel, form login/logout, CSRF |
| Front controller | `public/index.php`; route Laravel di `routes/web.php` |
| Frontend build | Tidak ada Vite/SPA build; asset berada langsung di `public/assets/` |
| PWA | Manifest dan service worker online-first; push notification belum tersedia |

## Login dan role

Tidak ada akun atau password bawaan di source control. Akun Admin pertama harus disediakan melalui proses provisioning yang dikendalikan; setelah itu Admin dapat membuat akun kantor dari menu pengelolaan pengguna. Akun baru meminta nama, email unik, role, jabatan, departemen, dan password minimal 8 karakter yang memuat huruf besar, huruf kecil, angka, serta karakter khusus. Login menormalkan email ke lowercase, hanya menerima akun aktif, membatasi lima kegagalan per kombinasi email dan IP selama 15 menit, lalu meregenerasi session setelah berhasil. Logout mengakhiri session dan membuat ulang token CSRF.

| Role | Akses aplikasi |
| --- | --- |
| `employee` | Membuat tiket, melihat tiket miliknya, berkomentar, dan menutup atau membuka kembali tiket berstatus selesai. |
| `it` | Melihat antrean semua tiket, mengambil tiket yang belum ditugaskan, dan memperbarui tiket yang menjadi tanggung jawabnya. Dapat memakai filter tiket milik sendiri. |
| `admin` | Melihat serta mengelola semua tiket, mengatur assignee/status, melihat analitik dan ekspor, serta membuat/mengubah/mengaktifkan akun. Admin tidak dapat menurunkan role atau menonaktifkan akunnya sendiri melalui alur yang tersedia. |

Pembatasan dilakukan di route middleware dan request/controller, bukan hanya dengan menyembunyikan tombol. Detail tiket membatasi employee pada tiket miliknya; role IT dan Admin dapat melihat tiket lain.

## Alur tiket dan aturan operasional

### Data utama

Migration helpdesk menambahkan tabel `tickets`, `comments`, `activities`, `attachments`, dan `ticket_reads`, dengan relasi ke tabel `users`. Tiket menyimpan subject, description, category, priority, status, requester, assignee opsional, catatan resolusi, waktu dibuat/diubah, dan deadline SLA. `ticket_reads` memakai pasangan ticket/user sebagai primary key untuk waktu baca terakhir.

Kategori tersedia: Perangkat, Software, Jaringan, Akun & Akses, Email, Lainnya. Prioritas dan target SLA awal dihitung ketika tiket dibuat:

| Prioritas | Target SLA |
| --- | ---: |
| Urgent | 4 jam |
| Tinggi | 8 jam |
| Sedang | 24 jam |
| Rendah | 72 jam |

SLA adalah deadline yang dihitung aplikasi dari waktu pembuatan; bukan jaminan bahwa penyelesaian akan terjadi sebelum deadline.

Status dan transisi yang diizinkan berada di `config/helpdesk.php`:

| Status sekarang | Status berikut yang diizinkan |
| --- | --- |
| Baru | Baru, Ditugaskan, Dibatalkan |
| Ditugaskan | Ditugaskan, Sedang dikerjakan, Dibatalkan, Baru |
| Sedang dikerjakan | Sedang dikerjakan, Selesai, Dibatalkan |
| Selesai | Selesai, Ditutup, Sedang dikerjakan |
| Ditutup | Ditutup |
| Dibatalkan | Dibatalkan |

Saat tiket dibuat, aplikasi menulis aktivitas pembuatan. Perubahan status, assignment, komentar, dan keputusan requester dicatat sebagai aktivitas. Komentar dan pembaruan tiket berjalan dalam transaksi database.

### Lampiran

Form tiket menerima maksimal 5 gambar dengan ukuran maksimal 10 MB per file: JPG/JPEG, PNG, WebP, HEIC, atau HEIF. Saat ini file disimpan pada disk Laravel `public` di `storage/app/public/uploads` dan biasanya dipublikasikan melalui `php artisan storage:link`.

**Catatan keamanan deployment:** URL pada public disk dapat diakses langsung tanpa pemeriksaan authorization Laravel jika seseorang mengetahui URL file. Jika lampiran berpotensi memuat informasi internal atau rahasia, ubah penyimpanan ke private disk dan sajikan melalui route download yang mengotorisasi akses ke tiket. Dokumentasi ini mencatat risiko yang ada; arsitektur penyimpanan belum diubah oleh panduan ini.

## Route penting

Route kanonis didefinisikan di `routes/web.php` dan memakai session web Laravel.

| Method dan path | Fungsi | Pembatasan |
| --- | --- | --- |
| `GET /login`, `POST /login` | Form dan proses login | Login aktif/rate-limited |
| `POST /logout` | Logout | Session terautentikasi |
| `GET /` | Dashboard dan daftar tiket | Login |
| `GET /tickets/create`, `POST /tickets` | Form dan pembuatan tiket | Login |
| `GET /tickets/{ticket}` | Detail tiket | Login dan pemeriksaan akses requester/role |
| `POST /tickets/{ticket}/comments` | Komentar | Login dan pemeriksaan akses tiket |
| `POST /tickets/{ticket}/claim` | Ambil tiket | Role IT dan tiket belum ditugaskan |
| `PATCH /tickets/{ticket}` | Update status/assignee | Admin, atau IT yang ditugaskan |
| `POST /tickets/{ticket}/resolution` | Konfirmasi selesai / buka kembali | Requester dan tiket selesai |
| `GET /tickets-export` | Ekspor CSV | Role IT/Admin |
| `GET /users`, `POST /users`, `PATCH /users/{user}`, `POST /users/{user}/toggle` | Kelola akun | Admin |
| `GET /api/updates` | Polling aktivitas baru untuk pengguna | Login; response dibatasi oleh akses tiket pengguna |
| `GET|POST /index.php`, `POST /` | Kompatibilitas front-controller/action lama | Login/otorisasi aksi tetap berlaku |

Endpoint `/api/updates` adalah endpoint polling internal yang memakai autentikasi web, bukan API publik/token API terpisah. Route `/index.php` dipertahankan untuk kompatibilitas link/form lama; fitur baru sebaiknya memakai named route Laravel.

Dashboard ticket mendukung pencarian pada subject, ID, deskripsi, dan requester; filter status, prioritas, kategori, rentang tanggal, dan filter milik sendiri bagi IT. Ekspor CSV tersedia bagi IT/Admin. Analitik tersedia bagi IT/Admin dengan rentang 7, 30, atau 90 hari. Polling aktivitas berjalan tiap 8 detik selama halaman terlihat dan browser online; perubahan tidak didorong melalui Web Push.

## Struktur kode

```text
app/
├── Http/Controllers/       # Login, dashboard, tiket, user, kompatibilitas
├── Http/Middleware/        # Role, kompatibilitas CSRF, security headers
├── Http/Requests/          # Validasi dan authorization per aksi
├── Models/                 # User, Ticket, Comment, Activity, Attachment, TicketRead
├── Services/               # TicketService, UserService, DashboardViewData
└── Support/legacy.php      # Adapter array untuk kontrak view/front-controller lama
bootstrap/                  # Bootstrap Laravel dan registrasi middleware
config/helpdesk.php         # Role, status, transisi, kategori, SLA, batas upload
database/migrations/        # Schema Laravel dan migration helpdesk guarded
public/
├── assets/                 # CSS/JS aplikasi dan Chart.js lokal
├── icons/                  # Icon manifest SVG
├── manifest.webmanifest    # Nama, scope, warna, display, icon PWA
├── sw.js                   # Cache static dan fallback navigasi offline
└── offline.html            # Halaman fallback offline
resources/views/
├── auth/login.blade.php    # Login
├── layouts/app.blade.php   # Layout aplikasi
└── dashboard/partials/     # Sidebar/topbar dan section dashboard
routes/web.php              # Route aplikasi dan kompatibilitas
```

Controller memproses request, FormRequest memvalidasi dan mengotorisasi input, service mengelola perubahan bisnis dalam transaksi, Eloquent memetakan data, lalu Blade merender halaman. `DashboardViewData` menggabungkan hasil query ke struktur data tampilan; `app/Support/legacy.php` menjaga kompatibilitas beberapa kontrak tampilan PHP native. Asset frontend tidak membutuhkan tahap compile.

## PWA, cache, dan konektivitas

### Instalasi

Manifest bernama `IT Support Helpdesk` dengan nama pendek `IT Support`, `start_url` dan `scope` `/`, `display: standalone`, warna tema `#1046AE`, warna latar `#EAF0F9`, icon SVG ukuran 192/512, dan icon maskable. Di Android, browser Chromium yang mendukung installability dapat menawarkan install prompt; aplikasi juga menyediakan menu untuk memulai pemasangan manual. Di iOS, pemasangan dilakukan lewat Share → Add to Home Screen di Safari. Ketersediaan dan label prompt ditentukan browser/OS; HTTPS diperlukan di deployment (localhost dikecualikan untuk development).

Session Laravel tetap menjadi authentication saat aplikasi dibuka dari Home Screen. `localStorage` hanya dipakai untuk preferensi tema dan penanda bahwa prompt install sudah ditutup; password dan response ticket tidak disimpan di sana atau di Cache Storage. PWA tidak mengantrekan pembuatan tiket ketika offline. Penolakan prompt install tidak memutus akses website.

### Kebijakan service worker

`public/sw.js` memakai cache bernama berversi (`itsupport-static-v14` saat dokumentasi ini dibuat):

- Cache-first untuk file statis same-origin di `/assets/`, `/icons/`, manifest, dan `offline.html`; bila tidak ada di cache, worker meminta ke server lalu menyimpan response sukses.
- Navigasi HTML mengambil response jaringan dengan `no-store`; hanya jika request gagal, worker menyajikan `offline.html`.
- Request selain GET/same-origin tidak diintersepsi. Halaman login/dashboard/tiket, route user, dan API tidak dimasukkan ke cache.
- Saat worker baru aktif, cache versi lama dengan prefix aplikasi dihapus. Tombol update meminta worker menunggu untuk aktif lalu halaman dimuat ulang.

Cache statis dapat menampilkan file lama sebelum versi cache dinaikkan. Ketika mengganti nama/isi asset yang perlu dipastikan serentak, naikkan konstanta `CACHE_VERSION` di `public/sw.js`, deploy asset dan worker bersama, lalu verifikasi flow update di browser target. Uji di Chrome DevTools → Application → Service Workers dan Cache Storage; jangan memasukkan response privat sebagai jalan pintas offline.

Status jaringan membantu memberi tahu konektivitas, bukan bukti bahwa server telah menyimpan perubahan. Jika offline atau server gagal, proses submit tidak boleh dianggap berhasil. Push notification belum menjadi bagian implementasi; tahap itu memerlukan persetujuan permission, penyimpanan subscription, sumber event ticket, dan worker pengirim yang terpisah.

## Keamanan dan batas yang diketahui

- Gunakan authentication session Laravel, proteksi CSRF, FormRequest/controller authorization, dan middleware role. Jangan mengandalkan tampilan tombol sebagai kontrol akses.
- Di production, middleware security mengarahkan request ke HTTPS dan mengirim header seperti CSP, `X-Frame-Options`, `X-Content-Type-Options`, Referrer Policy, Permissions Policy, serta HSTS pada request HTTPS.
- CSP saat ini mengizinkan inline script/style (`unsafe-inline`) karena implementasi UI memakainya. Jika kebijakan diperketat, pindahkan inline code atau gunakan nonce/hash dan uji semua halaman.
- **Lampiran berada di public disk** sebagaimana dijelaskan di bagian Lampiran. Ini batas paling penting untuk deployment yang menaruh informasi sensitif di screenshot.
- Jangan commit `.env`, password, session secret, atau data database. Set `APP_DEBUG=false` dan rotasi kredensial jika pernah terekspos.
- Service worker tidak menyimpan response terautentikasi. Namun cache browser umum dan kebijakan akses file server tetap perlu diperiksa pada deployment.

## Menyiapkan environment dan backup

1. Siapkan PHP 8.3+, extension `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `xml`, Composer 2.x, serta MySQL/MariaDB.
2. Konfigurasi `.env` lokal/production (`APP_URL`, koneksi database, session, mail bila dipakai). Jangan gunakan kredensial development untuk production.
3. Ambil backup database dan upload sebelum menerapkan migration pada salinan schema/data yang belum dikenal. Migration helpdesk membuat tabel yang belum ada dan hanya menambah `sla_deadline` jika kolom itu belum ada; migration standar Laravel lain juga berlaku sesuai tabel `migrations`.
4. Jalankan `composer install`, `php artisan migrate`, dan `php artisan storage:link` sesuai environment. Periksa log Laravel dan database setelah migrasi.
5. Arahkan document root web server ke direktori `public`, aktifkan HTTPS, set `APP_ENV=production`/`APP_DEBUG=false`, dan beri hak tulis minimum yang diperlukan pada `storage` dan `bootstrap/cache`.
6. Backup database **dan** lampiran pada disk upload. Simpan backup di luar document root dan uji prosedur pemulihannya.
7. Sebelum membuka akses kantor, tinjau kebijakan akses lampiran dan jalankan pemeriksaan browser pada Chrome/Edge desktop serta Android Chrome/iPhone Safari. Preview yang ada hanya satu screenshot login desktop; bukan bukti seluruh checklist perangkat sudah lulus.

## Pemeriksaan lokal

Perintah berikut tersedia untuk inspeksi route, kompilasi view, dan test suite:

```powershell
php artisan route:list
php artisan view:cache
php artisan test
```

`view:cache` menghasilkan cache Blade di `bootstrap/cache`; bersihkan dengan `php artisan view:clear` bila perlu. Jangan menjalankan seeder, `migrate:fresh`, atau reset database kantor sebagai langkah troubleshooting tanpa backup dan otorisasi eksplisit.

## Artefak dokumentasi

- [Preview login desktop](previews/login-desktop.png) diambil dari halaman Laravel lokal pada viewport desktop 1440 × 1000.
- [README](../README.md) berisi setup ringkas.
- [DESIGN.md](../DESIGN.md) berisi arah visual dan kontrak antarmuka.
