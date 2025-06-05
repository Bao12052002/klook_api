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
-- Cấu trúc bảng cho bảng `tbl_klook_suppliers`
--

CREATE TABLE `tbl_klook_suppliers` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `endpoint` varchar(500) DEFAULT NULL,
  `website` varchar(500) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telephone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_klook_suppliers`
--

INSERT INTO `tbl_klook_suppliers` (`id`, `name`, `endpoint`, `website`, `email`, `telephone`, `address`, `status`, `created_at`, `updated_at`) VALUES
('2ba2j0k4-nb20', 'OnBird - Soft-Adventure Journeys', 'https://coralmountainltd.com/klook', 'https://onbird.vn/', 'hello@onbird.vn', '(+84) 36 37 59 280', 'Valley Village Phu Quoc, Cua Lap, Duong To, Phu Quoc, Vietnam', 'active', '2025-05-29 08:54:17', '2025-05-29 08:54:17');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `tbl_klook_suppliers`
--
ALTER TABLE `tbl_klook_suppliers`
  ADD PRIMARY KEY (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
