-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 28 Feb 2026 pada 04.19
-- Versi server: 8.0.44-cll-lve
-- Versi PHP: 8.4.17

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `taufikma_property`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `folder_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_lengkap` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','manager','developer','manager_developer','finance','finance_platform','marketing_external','marketing') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'developer',
  `marketing_type_id` int DEFAULT NULL,
  `developer_id` int DEFAULT NULL,
  `location_access` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'List lokasi yang bisa diakses developer (comma separated)',
  `distribution_mode` enum('FULL_INTERNAL_PLATFORM','SPLIT_50_50') COLLATE utf8mb4_unicode_ci DEFAULT 'FULL_INTERNAL_PLATFORM',
  `alamat` text COLLATE utf8mb4_unicode_ci,
  `kota` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `npwp` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `siup` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telepon_perusahaan` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fax` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_perusahaan` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bidang_usaha` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tahun_berdiri` year DEFAULT NULL,
  `jumlah_proyek` int DEFAULT '0',
  `legalitas_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_photo_updated_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `last_login` datetime DEFAULT NULL,
  `last_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `nama_perusahaan` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat_perusahaan` text COLLATE utf8mb4_unicode_ci,
  `kota_perusahaan` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provinsi` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kode_pos` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website_perusahaan` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `npwp_perusahaan` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `siup_nib` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nama_direktur` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_perusahaan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_proyek_aktif` int DEFAULT '0',
  `total_unit_terjual` int DEFAULT '0',
  `tracking_global_override` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `folder_name`, `email`, `phone`, `password`, `nama_lengkap`, `role`, `marketing_type_id`, `developer_id`, `location_access`, `distribution_mode`, `alamat`, `kota`, `npwp`, `siup`, `telepon_perusahaan`, `fax`, `website`, `email_perusahaan`, `contact_person`, `contact_phone`, `bidang_usaha`, `tahun_berdiri`, `jumlah_proyek`, `legalitas_file`, `profile_photo`, `profile_photo_updated_at`, `is_active`, `last_login`, `last_ip`, `remember_token`, `token_expiry`, `created_at`, `updated_at`, `deleted_at`, `nama_perusahaan`, `alamat_perusahaan`, `kota_perusahaan`, `provinsi`, `kode_pos`, `website_perusahaan`, `npwp_perusahaan`, `siup_nib`, `nama_direktur`, `logo_perusahaan`, `total_proyek_aktif`, `total_unit_terjual`, `tracking_global_override`) VALUES
(1, 'tovic', NULL, 'lapakmarie@gmail.com', NULL, '$2y$10$9HLgmFYTSE2HEQbkBqNuZenO.qeIMc9a4H0a2jYdQBuPPo9udbBU.', 'Tovic Marie', 'admin', NULL, NULL, '', 'FULL_INTERNAL_PLATFORM', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'admin_1_1771687068.jpg', '2026-02-21 22:17:48', 1, '2026-02-28 04:10:46', '182.10.161.189', '8556a6b30dd27f6520da6f66a213290f7db1c25dac2b40bf43c7941d8572d070', '2026-03-30 04:10:46', '2026-02-17 15:04:30', '2026-02-28 04:10:46', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0),
(2, 'marie', NULL, 'manager@taufikmarie.com', NULL, '$2y$10$UgcQT25N3jxqHebAZAtmtutCnoMYqkZ7736fmxvWddND5PPJkTr5u', 'TMarie', 'manager', NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, 0, NULL, 'manager_2_1771692882.png', '2026-02-21 23:54:42', 1, '2026-02-25 17:37:42', '182.10.161.176', 'a867b98014fdd6e3fc4822f6b244b3cbe44579a2d1e4518378b9a78e0e9bab41', '2026-03-27 17:37:42', '2026-02-17 15:04:30', '2026-02-28 00:59:05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0),
(3, 'bimulia', 'kertamulya', 'bimulialand@gmail.com', NULL, '$2y$10$2glJW.ureeonVQTvGcZgP.EbUD2mwKpyY2Ee.vU4f9uNm2F6lhDoK', 'Bimulia', 'developer', NULL, NULL, 'kertamulya', 'SPLIT_50_50', 'Jalan Kertayasa, Blok Congkleng, Kertayasa, Kec. Sindangagung, Kabupaten Kuningan, Jawa Barat 45573', '', '', '', '082115888686', '', 'https://kertamulyaresidence.com', 'info@kertamulyaresidence.com', 'Eka Fauzi', '085721093922', 'developer', '2018', 1, NULL, 'developer_3_1771858035.jpg', '2026-02-23 21:47:15', 1, '2026-02-28 04:07:22', '182.10.161.189', '6f514e470cf4f22f421776ee6f8d9fb6946eeaaae0e8ee4f556fb951ea994bb4', '2026-03-30 04:07:22', '2026-02-17 15:04:30', '2026-02-28 04:07:22', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0),
(4, 'developer2', 'kertayasa', 'dev2@taufikmarie.com', NULL, '$2y$10$O5yToWEDoHbnV1d7KThpHOg42gQyjVZC5I1JWmTzwO/.HfCcIt.5i', 'Kertayasa', 'developer', NULL, NULL, 'kertayasa', 'FULL_INTERNAL_PLATFORM', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, '2026-02-17 15:04:30', '2026-02-27 03:34:54', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0),
(5, 'developer3', 'ciperna', 'dev3@taufikmarie.com', NULL, '$2y$10$O5yToWEDoHbnV1d7KThpHOg42gQyjVZC5I1JWmTzwO/.HfCcIt.5i', 'Ciperna', 'developer', NULL, NULL, 'ciperna', 'FULL_INTERNAL_PLATFORM', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, '2026-02-17 15:04:30', '2026-02-27 03:34:54', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0),
(6, 'developer4', 'windusari', 'dev4@taufikmarie.com', NULL, '$2y$10$O5yToWEDoHbnV1d7KThpHOg42gQyjVZC5I1JWmTzwO/.HfCcIt.5i', 'Windusari', 'developer', NULL, NULL, 'windusari', 'FULL_INTERNAL_PLATFORM', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, '2026-02-17 15:04:30', '2026-02-27 03:34:54', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0),
(10, 'sarah', NULL, 'finance@taufikmarie.com', NULL, '$2y$10$92I8O.2b22sDYAttaJqmuOOq8y2j2Ozli4yM.G2dqP9gZw2Y7n98m', 'sarah', 'finance_platform', NULL, NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'finance_platform_10_1772096991.jpg', '2026-02-26 16:09:51', 1, '2026-02-28 01:38:30', '182.10.161.189', '0670a2f30eaa51820a9b7479e5d509312edf916a1055e7f85724c1e0520fe126', '2026-03-30 01:38:30', '2026-02-26 05:48:25', '2026-02-28 01:38:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0),
(12, 'finance_platform2', NULL, 'finance.platform@taufikmarie.com', NULL, '$2y$10$9HLgmFYTSE2HEQbkBqNuZenO.qeIMc9a4H0a2jYdQBuPPo9udbBU.', 'Finance Platform', 'finance_platform', NULL, NULL, NULL, 'FULL_INTERNAL_PLATFORM', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, '2026-02-26 13:27:36', '2026-02-26 13:27:36', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0),
(20, 'jauza', NULL, 'jauza@gmail.com', NULL, '$2y$10$8IylkGkb07ITpzHjYkpkB.eQL7jEF9k8zH6DmC53kW7wkW8m8aUdm', 'Jauza', 'marketing_external', NULL, NULL, '', 'FULL_INTERNAL_PLATFORM', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '628133150023', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-02-28 03:09:52', '182.10.161.189', '98db43c7e40be97c84a19655a9c6227e5d5099b42649e2b8370903cb640bee7d', '2026-03-30 03:09:52', '2026-02-27 09:47:32', '2026-02-28 03:09:52', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0),
(24, 'andi', NULL, 'andi@gmail.com', '6282112207106', '$2y$10$Muqdh1VEj3ftk4WkB3KPUOb2FaAArvSPlXIRLazKkb20TTtzanp3u', 'andi rahmat', 'manager_developer', NULL, 3, NULL, 'FULL_INTERNAL_PLATFORM', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-02-28 04:04:26', '182.10.161.189', 'ed99dbfbea46a48846371170f95ed503ff674d735825e6fe52948b8bedde8c6a', '2026-03-30 04:04:26', '2026-02-28 04:03:26', '2026-02-28 04:04:26', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0),
(25, 'rebi', NULL, 'rebi@gmail.com', '628977771080', '$2y$10$g4MqxypOPBIq8SLTO1XmVe9NUXprAT0Yyj8gF0QcQmcWc19kPk1qS', 'Rebi', 'marketing', NULL, 3, NULL, 'FULL_INTERNAL_PLATFORM', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-02-28 04:04:17', '182.10.161.189', 'a05460b799ff292a40914f8418678d7f5c8b84c648b1569c988fd82329cccfd4', '2026-03-30 04:04:17', '2026-02-28 04:04:08', '2026-02-28 04:04:17', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0);

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `unique_folder` (`folder_name`),
  ADD KEY `idx_distribution_mode` (`distribution_mode`),
  ADD KEY `idx_developer_id` (`developer_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_developer` FOREIGN KEY (`developer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
