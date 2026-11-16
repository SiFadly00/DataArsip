# Arsip Aman

Aplikasi sistem informasi arsip dokumen dengan halaman admin berbasis PHP.

## Struktur kode

- `index.php`: titik masuk dan kerangka halaman.
- `includes/storage.php`: sesi, penyimpanan arsip, dan fungsi enkripsi.
- `includes/controller.php`: pemrosesan formulir dan aksi dokumen.
- `views/auth.php`: tampilan penyiapan akun dan login.
- `views/dashboard.php`: tampilan pengelolaan arsip.

## Menjalankan aplikasi

1. Pastikan PHP 8.1 atau lebih baru tersedia dengan ekstensi OpenSSL.
2. Dari folder proyek, jalankan:

   ```powershell
   php -S localhost:8000
   ```

3. Buka `http://localhost:8000` di browser. Pada akses pertama, buat kata sandi admin minimal 12 karakter.

## Enkripsi

- Isi dokumen, judul, kategori, dan waktu disimpan sebagai satu payload terenkripsi AES-256-GCM.
- Setiap dokumen memperoleh kunci AES-256 acak; kunci itu dibungkus dengan RSA-OAEP.
- Kunci privat RSA dibuat otomatis dan dipakai untuk membuka dokumen setelah admin masuk.
- Arsip tersimpan di `utsweb_private` sebagai folder saudara dari folder proyek, bukan di document root. Lokasinya dapat diganti melalui variabel lingkungan `ARSIP_STORAGE_PATH`.

Simpan folder kunci privat dengan aman dan buat cadangan secara terpisah. Jika kunci privat hilang, arsip tidak dapat didekripsi. Aplikasi ini merupakan dasar praktikum; gunakan HTTPS, batasi akses filesystem, serta tambahkan pembatasan percobaan login sebelum dipasang di jaringan publik.
