# OrbitFlow — Phase 2 Stage 5: Backup & Recovery

## Fitur yang ditambahkan

- Halaman **Cadangan & Pemulihan** khusus Super Admin.
- Arsip backup terenkripsi yang disimpan di `storage/app/private/orbitflow-backups`.
- Snapshot database MySQL/MariaDB menggunakan `mysqldump`/`mariadb-dump`, atau database SQLite berbentuk file menggunakan `VACUUM INTO`.
- File pada `storage/app/public` ikut disertakan dan diperiksa dengan checksum SHA-256.
- Isi arsip ZIP dienkripsi AES-256. Kunci arsip diturunkan dari `APP_KEY` Laravel saat ini.
- Pembuatan backup manual, unduhan privat, dan pemeriksaan integritas melalui halaman admin.
- Backup otomatis harian dan pembersihan retensi (default 30 hari).
- Restore hanya melalui Artisan, dengan pemeriksaan arsip, backup pengaman sebelum restore, dan maintenance mode.
- `.env`, `APP_KEY`, `storage/app/private`, log, sesi, cache, `vendor`, dan `node_modules` tidak dimasukkan ke arsip.

## Pemasangan patch

1. Buat ekspor SQL terbaru dari database `orbitflow` melalui phpMyAdmin.
2. Letakkan file patch ZIP dan `APPLY-ORBITFLOW-PHASE2-STAGE5.ps1` di folder yang sama.
3. Jalankan script PowerShell. File sumber yang akan diganti disalin terlebih dahulu ke folder backup bertanggal di Desktop.
4. Buka PowerShell di folder proyek, kemudian jalankan:

   ```powershell
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   php artisan test
   ```

Tahap ini tidak membutuhkan migrasi database. Jangan jalankan `migrate:fresh`.

## Persyaratan PHP

- PHP 8.2 atau lebih baru.
- Ekstensi PHP `zip` dengan dukungan `ZipArchive::EM_AES_256` dan `ZipArchive::setEncryptionName`.
- Untuk MySQL/MariaDB, executable `mysqldump.exe`/`mariadb-dump.exe` dan `mysql.exe`/`mariadb.exe` yang sesuai.
- Symfony Process (sudah termasuk dalam dependensi Laravel).

Periksa dukungan ZIP pada PHP CLI yang digunakan oleh Artisan:

```powershell
php -r "var_dump(class_exists('ZipArchive'), defined('ZipArchive::EM_AES_256'));"
```

Keduanya harus menghasilkan `true`. Jika tidak, aktifkan ekstensi `zip` pada `php.ini` XAMPP yang digunakan oleh CLI, lalu mulai ulang terminal/Apache.

Pada instalasi XAMPP Windows biasa, aplikasi mencari executable di `C:\xampp\mysql\bin`. Jika XAMPP dipasang di lokasi lain, atur path berikut di `.env`:

```dotenv
ORBITFLOW_MYSQL_DUMP_BINARY="C:\\xampp\\mysql\\bin\\mysqldump.exe"
ORBITFLOW_MYSQL_BINARY="C:\\xampp\\mysql\\bin\\mysql.exe"
```

Setelah mengubah `.env`, jalankan `php artisan config:clear`.

## Membuat dan memverifikasi backup

- Login sebagai Super Admin.
- Buka **Cadangan & Pemulihan**.
- Pilih **Buat backup sekarang**.
- Gunakan tombol **Verifikasi** untuk memeriksa manifest arsip dan seluruh checksum SHA-256.
- Gunakan tombol **Unduh** untuk menyalin arsip ke lokasi aman yang terpisah.

Pembuatan backup melalui terminal juga tersedia:

```powershell
php artisan orbitflow:backup
```

## Backup otomatis harian di Windows

Laravel Scheduler memiliki tugas backup harian pada waktu `ORBITFLOW_BACKUP_SCHEDULE_TIME` (default `01:00` WIB). Laravel tidak menjalankan Scheduler secara otomatis; Windows Task Scheduler harus memanggilnya.

Buat tugas melalui Windows Task Scheduler:

- Trigger: ulangi setiap 1 menit tanpa batas akhir.
- Program: executable PHP yang digunakan XAMPP, biasanya `C:\xampp\php\php.exe`.
- Arguments: `artisan schedule:run`.
- Start in: folder proyek OrbitFlow.

Laravel Scheduler akan menjalankan backup pada jadwal yang ditentukan. Untuk memeriksa jadwal secara manual:

```powershell
php artisan schedule:list
php artisan schedule:run
```

Pada Linux, tambahkan cron standar yang menjalankan `php artisan schedule:run` setiap menit dari folder proyek.

## Retensi backup

`ORBITFLOW_BACKUP_RETENTION_DAYS` memiliki nilai default 30 hari. Pembersihan hanya memeriksa arsip `.zip` dengan pola nama yang dibuat OrbitFlow dan hanya berjalan setelah backup baru berhasil dibuat. File atau folder lain tidak dihapus.

## Pemulihan backup (hanya melalui terminal)

Restore sengaja tidak disediakan sebagai tombol web. Proses ini mengganti database aktif dan `storage/app/public`, sehingga harus dijalankan oleh operator tepercaya melalui terminal server.

1. Pastikan file `.env` asli dan `APP_KEY` yang sama tersedia. APP_KEY dibutuhkan untuk membuka arsip terenkripsi.
2. Letakkan file backup ZIP di `storage/app/private/orbitflow-backups` pada server tujuan.
3. Pastikan database target sudah dibuat dan kredensial database memiliki izin yang diperlukan. Konfigurasi `.env` dan `APP_KEY` harus sesuai dengan saat backup dibuat.
4. Jika server berjalan, verifikasi backup melalui halaman admin terlebih dahulu.
5. Jalankan perintah berikut, ganti nama file dengan nama arsip yang sebenarnya:

   ```powershell
   php artisan orbitflow:backup:restore orbitflow-backup-manual-YYYYMMDD-HHMMSS-xxxxxxxx.zip
   ```

6. Baca ringkasan backup dan konfirmasi pemulihan.
7. Perintah akan membuat backup `pre-restore`, memverifikasi arsip, mengaktifkan maintenance mode, memulihkan database dan storage publik, memeriksa koneksi database, lalu menonaktifkan maintenance mode.

Perintah restore menolak path traversal, nama arsip yang tidak dikenal, format yang tidak didukung, perbedaan driver database, dan checksum yang gagal. Migrasi tidak dijalankan otomatis; backup harus dipulihkan ke versi aplikasi yang kompatibel.

Jika restore gagal setelah maintenance mode aktif, OrbitFlow sengaja tetap berada dalam maintenance mode agar data yang mungkin tidak konsisten tidak digunakan. Periksa error dan backup `pre-restore` terlebih dahulu. Jalankan `php artisan up` hanya setelah database dan file publik diverifikasi atau backup pengaman berhasil dipulihkan.

## Catatan keamanan dan pemulihan bencana

- Arsip ZIP dienkripsi AES-256 menggunakan kunci turunan dari `APP_KEY`. Jika `APP_KEY` hilang atau berubah, arsip lama tidak dapat dibuka. Simpan salinan `.env`/`APP_KEY` secara terpisah di tempat rahasia offline yang disetujui. Jangan masukkan `.env` ke source ZIP atau kirim melalui chat.
- Arsip tidak menyertakan `storage/app/private` karena versi OrbitFlow saat ini memakai folder itu untuk pratinjau CSV sementara dan ekspor tautan aktivasi sekali pakai. Data rahasia tersebut tidak boleh disalin ke backup umum.
- Pemeriksaan source saat ini tidak menemukan fitur unggah file permanen. Backup menyertakan public disk (`storage/app/public`) agar file pada disk publik ikut terlindungi bila fitur unggah ditambahkan kelak.
- Backup lokal membantu menghadapi kesalahan logis, tetapi tidak melindungi dari kerusakan atau kehilangan disk server. Unduh backup terverifikasi secara berkala ke drive terenkripsi terpisah atau penyimpanan off-site yang disetujui. Transfer off-site belum otomatis pada tahap ini.
- Jangan simpan arsip backup di folder `public/` atau buat symlink ke folder backup privat.
- Sebelum penggunaan sekolah sebenarnya, gunakan HTTPS dan batasi akses sistem operasi ke folder storage proyek.
