# Helpdesk IT Support

Portal ticketing internal berbasis PHP native dan MySQL. Karyawan membuat serta memantau tiket, sedangkan Tim IT menangani tiket dan Admin mengelola operasional serta akun pengguna.

## Kebutuhan sistem

- XAMPP dengan Apache dan MySQL/MariaDB aktif
- PHP 7.4 atau lebih baru
- Ekstensi PHP `PDO` dan `pdo_mysql`
- Browser modern

## Menjalankan aplikasi

1. Letakkan proyek di `C:\xampp\htdocs\itsupport`.
2. Jalankan Apache dan MySQL melalui XAMPP Control Panel.
3. Buat schema database:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -u root -e 'SOURCE C:/xampp/htdocs/itsupport/database/schema.sql'
```

4. Buka `http://localhost/itsupport/`.

Konfigurasi koneksi berada di `app/config.php`. Konfigurasi lokal saat ini:

| Konfigurasi | Nilai |
|---|---|
| Host | `127.0.0.1` |
| Port | `3306` |
| Database | `itsupport` |
| User | `root` |
| Password | Kosong |

## Akun Admin saat ini

| Nama | Email | Password | Role |
|---|---|---|---|
| Administrator | `admin@gmail.com` | `admin123` | Admin |
| Sheva Zulfiqaar | `shevazulfiqaar@gmail.com` | `sheva123` | Admin |

Tidak tersedia registrasi publik. Admin menambahkan akun Karyawan, Tim IT, atau Admin melalui menu **Kelola User**.

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
│   │   └── uploads.php       # Validasi dan penyimpanan lampiran
│   ├── bootstrap.php         # Inisialisasi aplikasi dan session
│   └── config.php            # Konfigurasi database dan domain
├── assets/
│   ├── app.js                # Seluruh interaksi antarmuka
│   └── styles.css            # Seluruh stylesheet aplikasi
├── database/
│   └── schema.sql            # Struktur tabel dan foreign key
├── storage/
│   └── sessions/             # Session PHP runtime
├── uploads/                  # Lampiran tiket runtime
├── index.php                 # Front controller dan template
└── README.md
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
