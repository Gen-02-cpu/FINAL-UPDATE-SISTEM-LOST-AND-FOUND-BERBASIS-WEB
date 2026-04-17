Berikut adalah langkah-langkah sederhana untuk menjalankannya:

1. Persiapan Server Lokal
Jika belum punya, unduh dan instal aplikasi seperti Laragon (sangat disarankan, karena di kodenya terdapat komentar bawaan Laragon) atau XAMPP.

Buka aplikasinya, lalu jalankan (Start) layanan Apache dan MySQL.

2. Simpan File Proyek
Buat folder baru dengan nama lost-and-found di dalam folder server lokalmu:

Jika menggunakan Laragon: simpan di folder C:\laragon\www\lost-and-found

Jika menggunakan XAMPP: simpan di folder C:\xampp\htdocs\lost-and-found

Pindahkan semua file yang sudah kita buat tadi (config.php, functions.php, actions.php, index.php, dan folder assets) ke dalam folder tersebut.

3. Setup Database 

Buka browser dan ketik: http://localhost/phpmyadmin

Buat database baru dengan nama lost_found_db

Impor file SQL (biasanya bernama lost_found_setup.sql yang kamu miliki dari proyek aslinya) ke dalam database tersebut. File SQL ini berisi struktur tabel (users, reports, chat_messages) dan data awal.

4. Buka Aplikasi di Browser
Setelah database siap, buka tab baru di browser dan ketik alamat berikut:
http://localhost/lost-and-found

Daftar Akun :

Akun Administrator (Bisa melihat dashboard & semua laporan):

Username: admin

Password: admin123/password

Akun Pengguna Biasa (Bisa melapor & melihat riwayat sendiri):

Username: budi

Password: budi123/password

Akun Pengguna Biasa Kedua:

Username: siti

Password: siti123/password
