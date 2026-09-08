# Helpdesk IT Support

Portal ticketing internal berbasis PHP native dan MySQL. Karyawan membuat serta memantau tiket, sedangkan Tim IT menangani tiket dan Admin mengelola operasional serta akun pengguna.

## Kebutuhan sistem

- Apache dan MySQL/MariaDB aktif melalui Laragon, XAMPP, atau stack lokal lain
- PHP 7.4 atau lebih baru
- Ekstensi PHP `PDO` dan `pdo_mysql`
- Browser modern

## Menjalankan aplikasi

1. Letakkan proyek di document root stack lokal. Contoh:
   - Laragon: `C:\laragon\www\itsupport`
   - XAMPP: `C:\xampp\htdocs\itsupport`
2. Salin `.env.example` menjadi `.env`, lalu sesuaikan koneksi database lokal.
3. Jalankan Apache dan MySQL/MariaDB.
4. Buat database dari `database/schema.sql`. File ini adalah baseline untuk
   instalasi baru:

```powershell
Get-Content .\database\schema.sql | & '<mysql-bin>\mysql.exe' -u root -p
```

Untuk database lama yang dibuat sebelum kolom SLA masuk ke schema, jalankan
`database/migration_sla.sql` satu kali. Migration tersebut aman jika kolom dan
index SLA sudah ada.

5. Buka `http://localhost/itsupport/`.

Konfigurasi koneksi berada di `app/config.php`. Konfigurasi lokal saat ini:

| Konfigurasi | Nilai |
|---|---|
| Host | `127.0.0.1` |
| Port | `3306` |
| Database | `itsupport` |
| User | `root` |
| Password | Kosong |

## Akun admin lokal

Kredensial admin tidak disimpan di repository. Gunakan password yang dibuat saat
provisioning database lokal dan simpan di password manager atau secret store
lokal. Jangan menaruh password di README, source code, screenshot, atau commit.

Tidak tersedia registrasi publik. Admin menambahkan akun Karyawan, Tim IT, atau
Admin melalui menu **Kelola User**.

## Fitur utama

### Ticketing

- Membuat dan memantau tiket bantuan IT
- Kategori, prioritas, deskripsi, dan lampiran
- Penugasan oleh Admin atau diambil langsung oleh Tim IT
- Percakapan dan riwayat aktivitas dengan tanggal serta jam
- Catatan penyelesaian sebelum tiket dinyatakan selesai
- Filter tiket dan filter **Ticket Saya** untuk teknisi

Status tiket yang digunakan:

1. Baru
2. Ditugaskan
3. Sedang dikerjakan
4. Selesai
5. Ditutup
6. Dibatalkan

Status final seperti **Ditutup** dan **Dibatalkan** bersifat read-only. Tiket **Selesai** menunggu konfirmasi requester untuk ditutup atau dibuka kembali.

### Kelola pengguna

Admin dapat:

- menambahkan akun satu per satu;
- mengubah nama, email, role, jabatan, dan divisi;
- mereset password;
- mengaktifkan atau menonaktifkan akun;
- memfilter akun berdasarkan status aktif dan nonaktif.

Akun dinonaktifkan tanpa dihapus permanen agar histori tiket tetap konsisten. Admin tidak dapat menonaktifkan atau menurunkan role akunnya sendiri.

### Lampiran

- Format: JPG, PNG, WebP, HEIC, dan HEIF
- Maksimal 5 file per tiket
- Maksimal 10 MB per file
- Preview gambar sebelum tiket dikirim
- File HEIC/HEIF ditampilkan sebagai file jika browser tidak mendukung preview

### Dashboard dan notifikasi

- Ringkasan distribusi status tiket
- Klasifikasi masalah berdasarkan kategori
- Distribusi beban kerja teknisi
- Aktivitas terbaru
- Notifikasi komentar dan perubahan tiket
- Popup hasil tambah, edit, dan perubahan data pengguna
- Light mode dan dark mode
- Layout responsif untuk desktop dan mobile

## Struktur proyek

```text
itsupport/
├── app/
│   ├── Http/
│   │   ├── actions.php       # Handler form dan aturan bisnis
│   │   └── updates_api.php   # Endpoint polling notifikasi
│   ├── Storage/
│   │   └── mysql_store.php   # Repository PDO MySQL
│   ├── Support/
│   │   ├── helpers.php       # Helper umum, CSRF, dan otorisasi
│   │   ├── csv_export.php     # Export ticket ke CSV
│   │   ├── rate_limiter.php   # Pembatasan percobaan login
│   │   └── uploads.php        # Validasi dan penyimpanan lampiran
│   ├── bootstrap.php          # Inisialisasi aplikasi dan session
│   ├── config.php             # Konfigurasi database dan domain
│   └── env.php                # Loader environment lokal
├── assets/
│   ├── analytics.js           # Inisialisasi chart analytics
│   ├── app.js                 # Seluruh interaksi antarmuka
│   ├── ascii-field.svg        # Motif ASCII statis
│   ├── styles.css             # Stylesheet aplikasi
│   └── vendor/
│       └── chart.umd.min.js   # Chart.js vendored, versi dan lisensi di header
├── database/
│   ├── schema.sql             # Baseline schema untuk instalasi baru
│   └── migration_sla.sql      # Migration historis yang idempotent
├── storage/
│   ├── sessions/              # Session PHP runtime, tidak dilacak Git
│   └── rate_limits/           # State rate limiter, tidak dilacak Git
├── uploads/                   # Lampiran tiket runtime, tidak dilacak Git
├── anti-slop/                 # Audit dan catatan desain, bukan runtime app
├── index.php                  # Front controller dan template
├── DESIGN.md                  # Arah visual dan kontrak UI
└── README.md                  # Setup dan dokumentasi proyek
```

## Penyimpanan data

Seluruh data aplikasi disimpan di MySQL:

- `users`
- `tickets`
- `comments`
- `activities`
- `attachments`
- `ticket_reads`

Runtime tidak menggunakan JSON. Operasi perubahan data dijalankan melalui PDO dan transaksi database.

## Keamanan

- Password disimpan menggunakan `password_hash()`.
- Form perubahan data dilindungi CSRF token.
- Hak akses diperiksa kembali di backend.
- Akun nonaktif tidak dapat login.
- Upload divalidasi berdasarkan format, MIME type, jumlah, dan ukuran.

Untuk penggunaan produksi:

- ganti seluruh password awal;
- gunakan akun MySQL khusus dengan password kuat;
- pindahkan kredensial database ke environment variables;
- aktifkan HTTPS;
- nonaktifkan tampilan error PHP;
- lakukan backup database dan folder `uploads` secara berkala;
- integrasikan autentikasi kantor atau SSO jika diperlukan.
