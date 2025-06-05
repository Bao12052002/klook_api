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
-- Cấu trúc bảng cho bảng `tbl_klook_product_pickups`
--

CREATE TABLE `tbl_klook_product_pickups` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `pickup_id` int(11) NOT NULL,
  `additional_cost` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_klook_product_pickups`
--

INSERT INTO `tbl_klook_product_pickups` (`id`, `product_id`, `pickup_id`, `additional_cost`, `created_at`) VALUES
(1, 1, 1, 0.00, '2025-05-28 15:55:28'),
(2, 1, 2, 2.00, '2025-05-28 15:55:28'),
(3, 1, 3, 3.30, '2025-05-28 15:55:28'),
(4, 1, 4, 4.10, '2025-05-28 15:55:28'),
(5, 1, 5, 0.00, '2025-05-28 15:55:28'),
(6, 2, 1, 0.00, '2025-05-28 15:55:28'),
(7, 2, 2, 2.00, '2025-05-28 15:55:28'),
(8, 2, 5, 0.00, '2025-05-28 15:55:28'),
(9, 3, 1, 0.00, '2025-05-28 15:55:28'),
(10, 3, 2, 2.00, '2025-05-28 15:55:28'),
(11, 3, 3, 3.30, '2025-05-28 15:55:28'),
(12, 3, 5, 0.00, '2025-05-28 15:55:28'),
(13, 4, 1, 0.00, '2025-05-28 15:55:28'),
(14, 4, 2, 2.00, '2025-05-28 15:55:28'),
(15, 4, 6, 2.90, '2025-05-28 15:55:28'),
(16, 5, 1, 0.00, '2025-05-28 15:55:28'),
(17, 5, 5, 0.00, '2025-05-28 15:55:28');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `tbl_klook_product_pickups`
--
ALTER TABLE `tbl_klook_product_pickups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_product_pickup` (`product_id`,`pickup_id`),
  ADD KEY `pickup_id` (`pickup_id`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `tbl_klook_product_pickups`
--
ALTER TABLE `tbl_klook_product_pickups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
