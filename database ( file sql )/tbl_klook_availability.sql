-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Máy chủ: localhost:3306
-- Thời gian đã tạo: Th6 05, 2025 lúc 10:34 AM
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
-- Cấu trúc bảng cho bảng `tbl_klook_availability`
--

CREATE TABLE `tbl_klook_availability` (
  `id` int(11) NOT NULL,
  `product_id` varchar(100) NOT NULL,
  `option_id` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `available_slots` int(11) DEFAULT 0,
  `capacity` int(11) DEFAULT 0,
  `is_blocked` tinyint(1) DEFAULT 0,
  `excluded_dates` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`excluded_dates`)),
  `cancellationCutoffAmount` int(11) DEFAULT 0,
  `cancellationCutoffUnit` varchar(10) DEFAULT 'hour',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_klook_availability`
--

INSERT INTO `tbl_klook_availability` (`id`, `product_id`, `option_id`, `start_date`, `end_date`, `available_slots`, `capacity`, `is_blocked`, `excluded_dates`, `cancellationCutoffAmount`, `cancellationCutoffUnit`, `created_at`, `updated_at`) VALUES
(2, 'phu-quoc-snorkeling-56528', 'SMALL_GROUP', '2025-06-01', '2025-06-30', 0, 10, 0, '[\"2025-06-10\", \"2025-06-15\"]', 24, 'hour', '2025-06-02 16:09:17', '2025-06-05 10:30:28'),
(3, 'phu-quoc-snorkeling-56528', 'LARGE_GROUP', '2025-06-01', '2025-06-30', 12, 12, 0, '[\"2025-06-10\", \"2025-06-15\"]', 24, 'hour', '2025-06-02 16:09:17', '2025-06-02 16:09:17');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `tbl_klook_availability`
--
ALTER TABLE `tbl_klook_availability`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `tbl_klook_availability`
--
ALTER TABLE `tbl_klook_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
