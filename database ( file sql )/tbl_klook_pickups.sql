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
-- Cấu trúc bảng cho bảng `tbl_klook_pickups`
--

CREATE TABLE `tbl_klook_pickups` (
  `id` bigint(20) NOT NULL,
  `location` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_klook_pickups`
--

INSERT INTO `tbl_klook_pickups` (`id`, `location`, `address`, `latitude`, `longitude`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Duong Dong Center', 'Duong Dong Town Center, Phu Quoc, Kien Giang', 10.22160000, 103.96770000, 'active', '2025-05-29 03:52:25', '2025-05-29 03:52:25'),
(2, 'An Thoi Harbor', 'An Thoi Harbor, An Thoi Town, Phu Quoc, Kien Giang', 10.05840000, 103.93080000, 'active', '2025-05-29 03:52:25', '2025-05-29 03:52:25'),
(3, 'Ong Lang Beach Area', 'Ong Lang Beach, Cua Duong, Phu Quoc, Kien Giang', 10.26590000, 103.94060000, 'active', '2025-05-29 03:52:25', '2025-05-29 03:52:25'),
(4, 'Long Beach Area', 'Long Beach (Bai Truong), Duong Dong, Phu Quoc, Kien Giang', 10.18910000, 103.95760000, 'active', '2025-05-29 03:52:25', '2025-05-29 03:52:25'),
(5, 'Sao Beach Area', 'Sao Beach (Bai Sao), An Thoi, Phu Quoc, Kien Giang', 10.07890000, 103.98260000, 'active', '2025-05-29 03:52:25', '2025-05-29 03:52:25');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `tbl_klook_pickups`
--
ALTER TABLE `tbl_klook_pickups`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `tbl_klook_pickups`
--
ALTER TABLE `tbl_klook_pickups`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
