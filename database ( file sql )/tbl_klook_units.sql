-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Máy chủ: localhost:3306
-- Thời gian đã tạo: Th6 05, 2025 lúc 10:41 AM
-- Phiên bản máy phục vụ: 10.11.13-MariaDB-log
-- Phiên bản PHP: 8.3.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `manhphuo_crm_phuquoc`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tbl_klook_units`
--

CREATE TABLE `tbl_klook_units` (
  `id` varchar(255) NOT NULL,
  `product_id` varchar(255) NOT NULL,
  `option_id` varchar(255) NOT NULL,
  `internal_name` varchar(255) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `type` enum('ADULT','YOUTH','CHILD','INFANT','FAMILY','SENIOR','STUDENT','MILITARY','OTHER') NOT NULL,
  `required_contact_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`required_contact_fields`)),
  `min_age` int(11) DEFAULT NULL,
  `max_age` int(11) DEFAULT NULL,
  `id_required` tinyint(1) NOT NULL DEFAULT 0,
  `min_quantity` int(11) DEFAULT NULL,
  `max_quantity` int(11) DEFAULT NULL,
  `pax_count` int(11) NOT NULL DEFAULT 1,
  `accompanied_by` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`accompanied_by`)),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_klook_units`
--

INSERT INTO `tbl_klook_units` (`id`, `product_id`, `option_id`, `internal_name`, `reference`, `type`, `required_contact_fields`, `min_age`, `max_age`, `id_required`, `min_quantity`, `max_quantity`, `pax_count`, `accompanied_by`, `status`, `created_at`, `updated_at`) VALUES
('adult-sml', 'phu-quoc-snorkeling-56528', 'SMALL_GROUP', 'Adult', 'PQ-SML-AD', 'ADULT', '[\"firstName\", \"lastName\", \"emailAddress\", \"phoneNumber\"]', 12, 99, 0, 1, 6, 1, NULL, 'active', '2025-05-29 03:52:25', '2025-05-29 03:52:25'),
('child-sml', 'phu-quoc-snorkeling-56528', 'SMALL_GROUP', 'Child (3-11 years)', 'PQ-SML-CH', 'CHILD', '[\"firstName\", \"lastName\"]', 3, 11, 0, 0, 5, 1, '[\"adult-sml\"]', 'active', '2025-05-29 03:52:25', '2025-06-03 08:20:08');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `tbl_klook_units`
--
ALTER TABLE `tbl_klook_units`
  ADD PRIMARY KEY (`id`,`product_id`,`option_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `option_id` (`option_id`,`product_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
