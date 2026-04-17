-- ============================================================
-- SNI Lost & Found – Database Setup untuk Laragon (MySQL)
-- Jalankan file ini di phpMyAdmin atau HeidiSQL
-- ============================================================

CREATE DATABASE IF NOT EXISTS `lost_found_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `lost_found_db`;

-- ─── TABEL USERS ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username`   VARCHAR(50)  NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,   -- disimpan sebagai hash (password_hash)
  `role`       ENUM('admin','user') NOT NULL DEFAULT 'user',
  `name`       VARCHAR(100) NOT NULL,
  `avatar`     CHAR(1)      NOT NULL DEFAULT 'U',
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── TABEL REPORTS ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `reports` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `type`        ENUM('lost','found') NOT NULL,
  `title`       VARCHAR(150) NOT NULL,
  `category`    VARCHAR(60)  NOT NULL DEFAULT '',
  `location`    VARCHAR(150) NOT NULL,
  `date`        DATE         NOT NULL,
  `description` TEXT         NOT NULL,
  `photo`       MEDIUMTEXT   NULL,       -- base64 data-URI
  `status`      ENUM('open','matched','closed') NOT NULL DEFAULT 'open',
  `reporter`    VARCHAR(100) NOT NULL,
  `user_id`     INT UNSIGNED NOT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── DATA AWAL (SEED) ──────────────────────────────────────
-- Password: admin123  → hash bawaan PHP password_hash('admin123', PASSWORD_DEFAULT)
-- Karena hash berbeda tiap generate, kita pakai plain dulu lalu
-- ganti via PHP. Untuk kemudahan dev, password disimpan plain di seed,
-- script PHP akan mendeteksinya dan auto-hash saat login pertama.
-- ATAU jalankan seed_users.php yang disertakan.

-- ─── TABEL CHAT MESSAGES ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sender_id`   INT UNSIGNED NOT NULL,
  `receiver_id` INT UNSIGNED NOT NULL,   -- admin id (untuk user→admin) atau user id (untuk admin→user)
  `room_user_id` INT UNSIGNED NOT NULL,  -- selalu = id user non-admin (sebagai room identifier)
  `message`     TEXT NOT NULL,
  `is_read`     TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`sender_id`)   REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── DATA AWAL (SEED) ──────────────────────────────────────
INSERT IGNORE INTO `users` (`id`,`username`,`password`,`role`,`name`,`avatar`) VALUES
(1,'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin','Administrator','A'),
(2,'budi',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'Budi Santoso',  'B'),
(3,'siti',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'Siti Rahayu',   'S');
-- Hash di atas = password  (string "password")
-- Ganti dengan hash sesuai kebutuhan, atau jalankan seed_users.php

INSERT IGNORE INTO `reports`
  (`id`,`type`,`title`,`category`,`location`,`date`,`description`,`photo`,`status`,`reporter`,`user_id`,`created_at`)
VALUES
(1,'lost','Dompet Kulit Hitam','Dompet / Tas','Kantin Lantai 2','2026-02-10',
 'Dompet kulit hitam merek Coach berisi KTP, SIM, dan 3 kartu ATM. Terakhir terlihat saat makan siang.',
 NULL,'open','Budi Santoso',2,'2026-02-10 08:30:00'),
(2,'found','Kunci Motor Honda Beat','Kunci','Parkiran Barat','2026-02-12',
 'Menemukan kunci motor Honda Beat dengan gantungan kunci boneka beruang warna merah.',
 NULL,'matched','Administrator',1,'2026-02-12 09:15:00'),
(3,'lost','Laptop ASUS ROG','Elektronik','Perpustakaan R.204','2026-02-14',
 'Laptop ASUS ROG warna abu-abu gelap, ada stiker bendera Indonesia di pojok kiri bawah.',
 NULL,'open','Siti Rahayu',3,'2026-02-14 13:00:00'),
(4,'found','Payung Lipat Biru Polkadot','Lainnya','Lobby Utama','2026-02-18',
 'Payung lipat biru motif polkadot putih ditemukan di kursi lobby.',
 NULL,'closed','Budi Santoso',2,'2026-02-18 10:45:00'),
(5,'lost','AirPods Pro Gen 2','Elektronik','Ruang Meeting A','2026-02-20',
 'AirPods Pro putih dalam case MagSafe, ada nama Andi W. di dalam case.',
 NULL,'open','Administrator',1,'2026-02-20 14:20:00'),
(6,'found','KTP atas nama Reza Pratama','Dokumen','Mushola Lantai 3','2026-02-22',
 'KTP ditemukan di mushola. Beralamat Jl. Mawar No. 12 Jakarta Selatan.',
 NULL,'open','Siti Rahayu',3,'2026-02-22 11:00:00'),
(7,'lost','Jam Tangan Casio G-Shock','Perhiasan','Ruang Gym','2026-02-23',
 'G-Shock warna hitam DW5600 ditinggal di loker gym no. 14.',
 NULL,'matched','Budi Santoso',2,'2026-02-23 07:30:00');
