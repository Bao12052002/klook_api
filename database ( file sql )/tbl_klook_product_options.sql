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
-- Cấu trúc bảng cho bảng `tbl_klook_product_options`
--

CREATE TABLE `tbl_klook_product_options` (
  `id` varchar(255) NOT NULL,
  `product_id` varchar(255) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `internal_name` varchar(255) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `availability_local_start_times` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`availability_local_start_times`)),
  `cancellation_cutoff` varchar(100) NOT NULL,
  `cancellation_cutoff_amount` int(11) NOT NULL,
  `cancellation_cutoff_unit` enum('hour','minute','day') NOT NULL,
  `required_contact_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`required_contact_fields`)),
  `min_units` int(11) DEFAULT NULL,
  `max_units` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_klook_product_options`
--

INSERT INTO `tbl_klook_product_options` (`id`, `product_id`, `is_default`, `internal_name`, `reference`, `availability_local_start_times`, `cancellation_cutoff`, `cancellation_cutoff_amount`, `cancellation_cutoff_unit`, `required_contact_fields`, `min_units`, `max_units`, `created_at`, `updated_at`) VALUES
('LARGE_GROUP', 'phu-quoc-snorkeling-56528', 0, 'Nhóm lớn ghép (Tối đa 12 khách)', 'PQ-LARGE', '[\"07:30\", \"12:30\"]', '24 hours', 24, 'hour', '[\"firstName\", \"lastName\", \"emailAddress\", \"phoneNumber\"]', 1, 12, '2025-06-02 09:07:33', '2025-06-02 09:07:33'),
('SMALL_GROUP', 'phu-quoc-snorkeling-56528', 1, 'Nhóm nhỏ ghép (Tối đa 10 khách)', 'PQ-SMALL', '[\"07:30\", \"12:30\"]', '24 hours', 24, 'hour', '[\"firstName\", \"lastName\", \"emailAddress\", \"phoneNumber\"]', 1, 10, '2025-06-02 09:07:33', '2025-06-04 09:14:06');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `tbl_klook_product_options`
--
ALTER TABLE `tbl_klook_product_options`
  ADD PRIMARY KEY (`id`,`product_id`),
  ADD KEY `product_id` (`product_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
