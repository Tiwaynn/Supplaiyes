# SUPPLAIYES

Platform pengelolaan dan distribusi surplus makanan. PHP native (PDO), MySQL, Tailwind CSS (via CDN), JavaScript (AJAX polling).

## Fitur
- Dua role: **Admin** dan **User/Penerima** (admin hanya dibuat lewat `setup.php`, bukan registrasi publik).
- Admin: dashboard statistik, inventaris (tambah/edit/arsipkan/tandai terdistribusi/tarik dari distribusi), reservasi (konfirmasi pengambilan/batalkan), riwayat perubahan, laporan.
- User: katalog dengan pencarian, filter kategori/harga/gratis-berbayar, urutan terbaru atau batas distribusi terdekat, badge "Prioritas Distribusi", reservasi dengan kode unik, riwayat dan pembatalan.
- Reservasi atomik: transaksi + `SELECT ... FOR UPDATE`, jadi tidak ada pemesanan ganda.
- Inventaris realtime: katalog dan tabel admin di-refresh via AJAX polling (default 5 detik).
- Keamanan: `password_hash`, PDO prepared statements, CSRF, pembatasan role, regenerasi session, escape output (XSS), validasi di backend.
- Soft delete (arsip) dan log lengkap (`food_status_logs`, `reservation_logs`) dengan waktu + pelaku.
- Tanpa upload foto: kartu makanan memakai ikon kategori.

## Instalasi (XAMPP)
1. Salin folder `supplaiyes` ke `C:\xampp\htdocs\`.
2. Jalankan **Apache** dan **MySQL** di XAMPP Control Panel.
3. Buka `http://localhost/phpmyadmin` → tab **Import** → pilih `database.sql` → **Go**.
4. Cek `config/config.php` (default XAMPP: user `root`, password kosong, `base_url` = `/supplaiyes`). Kalau nama folder berbeda, ubah `base_url`.
5. Buka `http://localhost/supplaiyes/setup.php` → **Jalankan setup**. Ini membuat akun admin, user demo, dan 8 makanan contoh. Setelah itu hapus `setup.php`.
6. Buka `http://localhost/supplaiyes/` dan login.

Akun demo (dari setup):
- Admin: `admin@supplaiyes.test` / `Admin#12345`
- User: `budi@supplaiyes.test` / `User#12345`

Ganti password admin lewat menu Profil setelah login.

Catatan: Tailwind dimuat dari CDN, jadi butuh internet saat membuka halaman.

## Cara menguji
1. Login admin → Inventaris → **Tambah Makanan** (status keamanan "Layak Distribusi").
2. Buka jendela lain, login user → makanan muncul di Beranda (maks. ~5 detik).
3. Reservasi 2 porsi → stok turun, kode `SPL-XXXXXX` muncul. Lihat di Reservasi Saya.
4. Admin → Reservasi → **Konfirmasi Pengambilan** (masuk laporan sebagai terdistribusi) atau batalkan.
5. Admin → **Tarik dari Distribusi** → makanan hilang dari katalog user, reservasi aktif dibatalkan.
6. Makanan dengan "Karantina" tidak tampil di user. Ubah ke "Layak" lewat Edit.
7. Reservasi yang tidak diambil sampai batas waktu (default 120 menit) otomatis kedaluwarsa dan stok dikembalikan bila makanan masih layak dan batas distribusi belum lewat.

## Struktur
```
config/       konfigurasi (dilindungi .htaccess)
includes/     bootstrap, db, helpers, logika inventaris, layout (dilindungi .htaccess)
api/          endpoint JSON/HTML untuk polling dan reservasi
admin/        halaman admin
assets/app.js JavaScript (polling, modal reservasi)
database.sql  skema MySQL
setup.php     instalasi awal (hapus setelah dipakai)
```
