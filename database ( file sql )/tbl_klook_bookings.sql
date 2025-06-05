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
-- Cấu trúc bảng cho bảng `tbl_klook_bookings`
--

CREATE TABLE `tbl_klook_bookings` (
  `id` varchar(255) NOT NULL,
  `uuid` varchar(36) DEFAULT NULL COMMENT 'Klook idempotency key',
  `product_id` varchar(255) NOT NULL,
  `option_id` varchar(255) NOT NULL,
  `klook_availability_id` varchar(255) DEFAULT NULL COMMENT 'ID availability dạng chuỗi ISO8601 từ Klook',
  `availability_id` bigint(20) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_email` varchar(255) NOT NULL,
  `customer_phone` varchar(50) DEFAULT NULL,
  `booking_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `status` enum('PENDING','ON_HOLD','CONFIRMED','CANCELLED','EXPIRED','REDEEMED','REJECTED','COMPLETED') NOT NULL DEFAULT 'PENDING' COMMENT 'Trạng thái booking',
  `notes` text DEFAULT NULL,
  `voucher_code` varchar(100) DEFAULT NULL,
  `utc_created_at` datetime DEFAULT NULL COMMENT 'Thời gian tạo booking UTC',
  `utc_updated_at` datetime DEFAULT NULL COMMENT 'Thời gian cập nhật booking UTC',
  `utc_expires_at` datetime DEFAULT NULL COMMENT 'Thời gian reservation hết hạn UTC (cho status ON_HOLD)',
  `utc_confirmed_at` datetime DEFAULT NULL COMMENT 'Thời gian booking được confirm UTC',
  `utc_redeemed_at` datetime DEFAULT NULL COMMENT 'Thời gian booking được redeem UTC',
  `supplier_reference` varchar(100) DEFAULT NULL COMMENT 'Tham chiếu của Supplier cho booking này',
  `reseller_reference` varchar(100) DEFAULT NULL COMMENT 'Tham chiếu của Reseller (Klook) cho booking này',
  `cancellable` tinyint(1) DEFAULT 1 COMMENT 'Booking có thể hủy hay không',
  `cancellation_details` text DEFAULT NULL COMMENT 'JSON chi tiết hủy (reason, refund, utcCancelledAt)',
  `freesale_booking` tinyint(1) DEFAULT 0 COMMENT 'Booking được tạo dạng freesale',
  `contact_details` text DEFAULT NULL COMMENT 'JSON thông tin liên hệ chính của booking',
  `delivery_methods_snapshot` text DEFAULT NULL COMMENT 'JSON các phương thức giao vé của booking',
  `voucher_details` text DEFAULT NULL COMMENT 'JSON chi tiết voucher của booking',
  `test_mode` tinyint(1) DEFAULT 0 COMMENT 'Booking được tạo ở chế độ test',
  `booking_pricing_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON pricing cuối cùng của booking' CHECK (json_valid(`booking_pricing_details`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_klook_bookings`
--

INSERT INTO `tbl_klook_bookings` (`id`, `uuid`, `product_id`, `option_id`, `klook_availability_id`, `availability_id`, `customer_name`, `customer_email`, `customer_phone`, `booking_date`, `start_time`, `total_amount`, `currency`, `status`, `notes`, `voucher_code`, `utc_created_at`, `utc_updated_at`, `utc_expires_at`, `utc_confirmed_at`, `utc_redeemed_at`, `supplier_reference`, `reseller_reference`, `cancellable`, `cancellation_details`, `freesale_booking`, `contact_details`, `delivery_methods_snapshot`, `voucher_details`, `test_mode`, `booking_pricing_details`, `created_at`, `updated_at`) VALUES
('BKG68407CDE775FA', 'BKG68407CDE775FA', 'phu-quoc-snorkeling-56528', 'Small_Group', '2025-06-06T07:30:00+07:00', NULL, '', '', NULL, '2025-06-06', '07:30:00', 0.00, 'USD', 'ON_HOLD', 'Optional notes for the booking', NULL, '2025-06-04 17:05:34', NULL, '2025-06-04 17:35:34', NULL, NULL, 'BKG68407CDE775FA-S', NULL, 1, NULL, 0, NULL, NULL, NULL, 0, '{\"original\":0,\"retail\":0,\"net\":0,\"currency\":\"USD\",\"currencyPrecision\":2,\"includedTaxes\":[]}', '2025-06-04 17:05:34', '2025-06-04 17:05:34'),
('BKG6841095E79E40', 'BKG6841095E79E40', 'phu-quoc-snorkeling-56528', 'Small_Group', '2025-06-07T07:30:00+07:00', NULL, '', '', NULL, '2025-06-07', '07:30:00', 7764.00, 'USD', 'ON_HOLD', 'Optional notes for the booking', NULL, '2025-06-05 03:05:02', NULL, '2025-06-05 03:35:02', NULL, NULL, 'BKG6841095E79E40-S', NULL, 1, NULL, 0, NULL, NULL, NULL, 0, '{\"original\":9174,\"retail\":7764,\"net\":6598,\"currency\":\"USD\",\"currencyPrecision\":2,\"includedTaxes\":[{\"name\":\"Service Fee\",\"retail\":200,\"net\":100}]}', '2025-06-05 03:05:02', '2025-06-05 03:05:02'),
('BKG68410AD9BB21D', 'BKG68410AD9BB21D', 'phu-quoc-snorkeling-56528', 'Small_Group', '2025-06-07T07:30:00+07:00', NULL, '', '', NULL, '2025-06-07', '07:30:00', 7764.00, 'USD', 'ON_HOLD', 'Optional notes for the booking', NULL, '2025-06-05 03:11:21', NULL, '2025-06-05 03:41:21', NULL, NULL, 'BKG68410AD9BB21D-S', NULL, 1, NULL, 0, NULL, NULL, NULL, 0, '{\"original\":9174,\"retail\":7764,\"net\":6598,\"currency\":\"USD\",\"currencyPrecision\":2,\"includedTaxes\":[{\"name\":\"Service Fee\",\"retail\":200,\"net\":100}]}', '2025-06-05 03:11:21', '2025-06-05 03:11:21'),
('BKG68410BD83505A', 'BKG68410BD83505A', 'phu-quoc-snorkeling-56528', 'Small_Group', '2025-06-07T07:30:00+07:00', NULL, '', '', NULL, '2025-06-07', '07:30:00', 7764.00, 'USD', 'ON_HOLD', 'Optional notes for the booking', NULL, '2025-06-05 03:15:36', NULL, '2025-06-05 03:45:36', NULL, NULL, 'BKG68410BD83505A-S', NULL, 1, NULL, 0, NULL, NULL, NULL, 0, '{\"original\":9174,\"retail\":7764,\"net\":6598,\"currency\":\"USD\",\"currencyPrecision\":2,\"includedTaxes\":[{\"name\":\"Service Fee\",\"retail\":200,\"net\":100}]}', '2025-06-05 03:15:36', '2025-06-05 03:15:36'),
('BKG68410D4A9F805', 'BKG68410D4A9F805', 'phu-quoc-snorkeling-56528', 'Small_Group', '2025-06-07T07:30:00+07:00', NULL, '', '', NULL, '2025-06-07', '07:30:00', 7764.00, 'USD', 'ON_HOLD', 'Optional notes for the booking', NULL, '2025-06-05 03:21:46', NULL, '2025-06-05 03:51:46', NULL, NULL, 'BKG68410D4A9F805-S', NULL, 1, NULL, 0, NULL, NULL, NULL, 0, '{\"original\":9174,\"retail\":7764,\"net\":6598,\"currency\":\"USD\",\"currencyPrecision\":2,\"includedTaxes\":[{\"name\":\"Service Fee\",\"retail\":200,\"net\":100}]}', '2025-06-05 03:21:46', '2025-06-05 03:21:46'),
('BKG68410F54A6657', 'BKG68410F54A6657', 'phu-quoc-snorkeling-56528', 'Small_Group', '2025-06-07T07:30:00+07:00', NULL, '', '', NULL, '2025-06-07', '07:30:00', 7764.00, 'USD', 'ON_HOLD', 'Optional notes for the booking', NULL, '2025-06-05 03:30:28', NULL, '2025-06-05 04:00:28', NULL, NULL, 'BKG68410F54A6657-S', NULL, 1, NULL, 0, NULL, NULL, NULL, 0, '{\"original\":9174,\"retail\":7764,\"net\":6598,\"currency\":\"USD\",\"currencyPrecision\":2,\"includedTaxes\":[{\"name\":\"Service Fee\",\"retail\":200,\"net\":100}]}', '2025-06-05 03:30:28', '2025-06-05 03:30:28'),
('BKG684110B86C3E9', 'BKG684110B86C3E9', 'phu-quoc-snorkeling-56528', 'Small_Group', '2025-06-07T07:30:00+07:00', NULL, '', '', NULL, '2025-06-07', '07:30:00', 7764.00, 'USD', 'ON_HOLD', 'Optional notes for the booking', NULL, '2025-06-05 03:36:24', NULL, '2025-06-05 04:06:24', NULL, NULL, 'BKG684110B86C3E9-S', NULL, 1, NULL, 0, NULL, NULL, NULL, 0, '{\"original\":9174,\"retail\":7764,\"net\":6598,\"currency\":\"USD\",\"currencyPrecision\":2,\"includedTaxes\":[{\"name\":\"Service Fee\",\"retail\":200,\"net\":100}]}', '2025-06-05 03:36:24', '2025-06-05 03:36:24'),
('BKG684111529062F', 'BKG684111529062F', 'phu-quoc-snorkeling-56528', 'Small_Group', '2025-06-07T07:30:00+07:00', NULL, '', '', NULL, '2025-06-07', '07:30:00', 7764.00, 'USD', 'ON_HOLD', 'Optional notes for the booking', NULL, '2025-06-05 03:38:58', NULL, '2025-06-05 04:08:58', NULL, NULL, 'BKG684111529062F-S', NULL, 1, NULL, 0, NULL, NULL, NULL, 0, '{\"original\":9174,\"retail\":7764,\"net\":6598,\"currency\":\"USD\",\"currencyPrecision\":2,\"includedTaxes\":[{\"name\":\"Service Fee\",\"retail\":200,\"net\":100}]}', '2025-06-05 03:38:58', '2025-06-05 03:38:58'),
('BOOK_684065861C5A5', 'klook-booking-684065861bf0e', 'phu-quoc-snorkeling-56528', 'Small_Group', '2025-06-06T07:30:00+07:00', NULL, '', '', NULL, '0000-00-00', NULL, 7764.00, 'USD', 'ON_HOLD', 'Optional notes for the booking', NULL, '2025-06-04 15:25:58', NULL, '2025-06-04 15:55:58', NULL, NULL, NULL, NULL, 1, NULL, 0, NULL, NULL, NULL, 0, '{\"original\":9174,\"retail\":7764,\"net\":6598,\"currency\":\"USD\",\"currencyPrecision\":2,\"includedTaxes\":[{\"name\":\"Service Fee\",\"retail\":200,\"net\":100}]}', '2025-06-04 15:25:58', '2025-06-04 15:25:58'),
('PQ-BK-001', '11111111-1111-1111-1111-111111111111', 'phu-quoc-snorkeling-56528', 'SMALL_GROUP', '2025-06-01T08:00:00+07:00', 1, 'John Smith', 'john.smith@email.com', '+84-123-456-789', '2025-06-01', '08:00:00', 110.00, 'USD', 'CONFIRMED', 'Vegetarian meal requested. Changed to SMALL_GROUP.', 'PQ2024001', '2025-05-29 03:52:25', '2025-06-03 16:42:01', NULL, '2025-05-29 04:00:00', NULL, 'SUP-PQBK001', 'KLOOK-REF-001', 1, NULL, 0, '{\"fullName\": \"John Smith\", \"firstName\": \"John\", \"lastName\": \"Smith\", \"emailAddress\": \"john.smith@email.com\", \"phoneNumber\": \"+84-123-456-789\", \"locales\": [\"en\"], \"country\": \"US\"}', '[\"VOUCHER\", \"TICKET\"]', NULL, 0, '{\"original\": 12000, \"retail\": 11000, \"net\": 9000, \"currency\": \"USD\", \"currencyPrecision\": 2, \"includedTaxes\": [{\"name\": \"Service Fee\", \"retail\": 400, \"net\": 200}]}', '2025-05-29 03:52:25', '2025-06-03 09:42:01'),
('PQ-BK-002', '22222222-2222-2222-2222-222222222222', 'phu-quoc-snorkeling-56528', 'SMALL_GROUP', '2025-06-03T08:30:00+07:00', NULL, 'Sarah Johnson', 'sarah.j@email.com', '+84-987-654-321', '2025-06-03', '08:30:00', 3900000.00, 'VND', 'ON_HOLD', 'Professional photographer requested', 'PQ2024002', '2025-05-29 03:55:00', '2025-06-03 16:42:01', '2025-06-03 17:12:01', NULL, NULL, 'SUP-PQBK002', 'KLOOK-REF-002', 1, NULL, 0, '{\"fullName\": \"Sarah Johnson\", \"firstName\": \"Sarah\", \"lastName\": \"Johnson\", \"emailAddress\": \"sarah.j@email.com\", \"phoneNumber\": \"+84-987-654-321\", \"locales\": [\"en-GB\"], \"country\": \"GB\"}', '[\"VOUCHER\", \"TICKET\"]', NULL, 1, '{\"original\": 4200000, \"retail\": 3900000, \"net\": 3300000, \"currency\": \"VND\", \"currencyPrecision\": 0, \"includedTaxes\": [{\"name\": \"VAT\", \"retail\": 300000, \"net\": 240000}]}', '2025-05-29 03:52:25', '2025-06-03 09:42:01');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `tbl_klook_bookings`
--
ALTER TABLE `tbl_klook_bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `option_id` (`option_id`,`product_id`),
  ADD KEY `availability_id` (`availability_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
