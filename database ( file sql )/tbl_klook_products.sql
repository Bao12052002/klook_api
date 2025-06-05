-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Máy chủ: localhost:3306
-- Thời gian đã tạo: Th6 05, 2025 lúc 10:40 AM
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
-- Cấu trúc bảng cho bảng `tbl_klook_products`
--

CREATE TABLE `tbl_klook_products` (
  `id` varchar(255) NOT NULL,
  `internal_name` varchar(255) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `locale` varchar(10) NOT NULL DEFAULT 'en-GB',
  `time_zone` varchar(50) NOT NULL DEFAULT 'Asia/Ho_Chi_Minh',
  `allow_freesale` tinyint(1) NOT NULL DEFAULT 0,
  `instant_confirmation` tinyint(1) NOT NULL DEFAULT 1,
  `instant_delivery` tinyint(1) NOT NULL DEFAULT 1,
  `availability_required` tinyint(1) NOT NULL DEFAULT 1,
  `availability_type` enum('START_TIME','OPENING_HOURS') NOT NULL DEFAULT 'START_TIME',
  `delivery_formats` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`delivery_formats`)),
  `delivery_methods` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`delivery_methods`)),
  `redemption_method` enum('DIGITAL','PRINT','MANIFEST') NOT NULL DEFAULT 'DIGITAL',
  `default_currency` varchar(3) NOT NULL DEFAULT 'USD' COMMENT 'Tiền tệ mặc định cho sản phẩm này',
  `available_currencies` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Mảng các mã tiền tệ khả dụng, ví dụ: ["USD", "VND"]' CHECK (json_valid(`available_currencies`)),
  `pricing_per` enum('UNIT','BOOKING') NOT NULL DEFAULT 'UNIT' COMMENT 'Giá được tính trên mỗi UNIT hay mỗi BOOKING',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_klook_products`
--

INSERT INTO `tbl_klook_products` (`id`, `internal_name`, `reference`, `locale`, `time_zone`, `allow_freesale`, `instant_confirmation`, `instant_delivery`, `availability_required`, `availability_type`, `delivery_formats`, `delivery_methods`, `redemption_method`, `default_currency`, `available_currencies`, `pricing_per`, `status`, `created_at`, `updated_at`) VALUES
('phu-quoc-snorkeling-56528', 'Phu Quoc Snorkeling Day Tour by Speedboat', 'PQ-SNK-001', 'en-US', 'Asia/Ho_Chi_Minh', 0, 1, 1, 1, 'START_TIME', '[\"QRCODE\", \"PDF_URL\"]', '[\"VOUCHER\", \"TICKET\"]', 'DIGITAL', 'USD', '[\"VND\", \"USD\"]', 'UNIT', 'active', '2025-05-29 03:52:25', '2025-06-04 03:19:08');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `tbl_klook_products`
--
ALTER TABLE `tbl_klook_products`
  ADD PRIMARY KEY (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
