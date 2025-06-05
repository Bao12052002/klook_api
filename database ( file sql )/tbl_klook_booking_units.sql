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
-- Cấu trúc bảng cho bảng `tbl_klook_booking_units`
--

CREATE TABLE `tbl_klook_booking_units` (
  `id` bigint(20) NOT NULL,
  `booking_id` varchar(255) NOT NULL,
  `unit_item_uuid` varchar(36) DEFAULT NULL COMMENT 'Idempotency key cho unit item này',
  `unit_id` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `pax_count` int(11) NOT NULL DEFAULT 1 COMMENT 'Số khách mà unit này đại diện (từ định nghĩa unit)',
  `unit_price` decimal(10,2) NOT NULL,
  `traveller_first_name` varchar(100) DEFAULT NULL,
  `traveller_last_name` varchar(100) DEFAULT NULL,
  `traveller_email` varchar(255) DEFAULT NULL,
  `traveller_phone` varchar(50) DEFAULT NULL,
  `ticket_code` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'ON_HOLD' COMMENT 'Trạng thái của unit item này (ON_HOLD, CONFIRMED, etc.)',
  `utc_redeemed_at` datetime DEFAULT NULL COMMENT 'Thời gian unit item này được redeem UTC',
  `supplier_reference_item` varchar(100) DEFAULT NULL COMMENT 'Tham chiếu của supplier cho unit item/vé này',
  `reseller_reference_item` varchar(100) DEFAULT NULL COMMENT 'Tham chiếu của reseller cho unit item này',
  `contact_details_item` text DEFAULT NULL COMMENT 'JSON thông tin liên hệ của khách cho unit item này',
  `ticket_details_item` text DEFAULT NULL COMMENT 'JSON chi tiết vé của unit item này (QR, barcode value)',
  `unit_item_pricing_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON pricing cho unit item này (nếu pricing_per=UNIT)' CHECK (json_valid(`unit_item_pricing_details`)),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tbl_klook_booking_units`
--

INSERT INTO `tbl_klook_booking_units` (`id`, `booking_id`, `unit_item_uuid`, `unit_id`, `quantity`, `pax_count`, `unit_price`, `traveller_first_name`, `traveller_last_name`, `traveller_email`, `traveller_phone`, `ticket_code`, `status`, `utc_redeemed_at`, `supplier_reference_item`, `reseller_reference_item`, `contact_details_item`, `ticket_details_item`, `unit_item_pricing_details`, `created_at`) VALUES
(1, 'PQ-BK-001', '11111111-aaaa-1111-aaaa-111111111111', 'adult-sml', 2, 1, 55.00, 'John', 'Smith', 'john.smith@email.com', '+84-123-456-789', 'PQ-TK-001-01', 'CONFIRMED', NULL, 'TICKET-001A', NULL, NULL, NULL, '{\"original\": 6000, \"retail\": 5500, \"net\": 4500, \"currency\": \"USD\", \"currencyPrecision\": 2, \"includedTaxes\": [{\"name\": \"Service Fee\", \"retail\": 200, \"net\": 100}]}', '2025-05-29 03:52:25'),
(2, 'PQ-BK-002', '22222222-bbbb-2222-bbbb-222222222222', 'adult-sml', 3, 1, 1300000.00, 'Sarah', 'Johnson', 'sarah.j@email.com', '+84-987-654-321', 'PQ-TK-002-01', 'ON_HOLD', NULL, 'TICKET-002A', NULL, NULL, NULL, '{\"original\": 1400000, \"retail\": 1300000, \"net\": 1100000, \"currency\": \"VND\", \"currencyPrecision\": 0, \"includedTaxes\": [{\"name\": \"VAT\", \"retail\": 100000, \"net\": 80000}]}', '2025-05-29 03:52:25'),
(3, 'BKG68407CDE775FA', 'klookunititem-68407cde77790', 'adult-sml', 1, 1, 0.00, NULL, NULL, NULL, NULL, 'BKG68407CDE775FA-adult-sml-1', 'ON_HOLD', NULL, NULL, NULL, NULL, NULL, NULL, '2025-06-04 17:05:34'),
(4, 'BKG68407CDE775FA', 'klookunititem-68407cde7784c', 'child-sml', 1, 1, 0.00, NULL, NULL, NULL, NULL, 'BKG68407CDE775FA-child-sml-1', 'ON_HOLD', NULL, NULL, NULL, NULL, NULL, NULL, '2025-06-04 17:05:34'),
(5, 'BKG6841095E79E40', 'klookunititem-adult-sml-6841095e7a17', 'adult-sml', 1, 1, 4969.00, NULL, NULL, NULL, NULL, 'BKG6841095E79E40-ADULT-1635', 'ON_HOLD', NULL, NULL, NULL, NULL, NULL, '{\"original\":6169,\"retail\":4969,\"net\":4223,\"currency\":\"USD\",\"currencyPrecision\":2,\"includedTaxes\":[{\"name\":\"Service Fee\",\"retail\":200,\"net\":100}]}', '2025-06-05 03:05:02'),
(6, 'BKG6841095E79E40', 'klookunititem-child-sml-6841095e7a21', 'child-sml', 1, 1, 2795.00, NULL, NULL, NULL, NULL, 'BKG6841095E79E40-CHILD-1121', 'ON_HOLD', NULL, NULL, NULL, NULL, NULL, '{\"original\":3005,\"retail\":2795,\"net\":2375,\"currency\":\"USD\",\"currencyPrecision\":2,\"includedTaxes\":[]}', '2025-06-05 03:05:02'),
(7, 'BKG68410AD9BB21D', 'klookunititem-adult-sml-68410ad9bb6b', 'adult-sml', 1, 1, 4969.00, NULL, NULL, NULL, NULL, 'BKG68410AD9BB21D-ADULT-1968', 'ON_HOLD', NULL, NULL, NULL, NULL, NULL, '{\"original\":6169,\"retail\":4969,\"net\":4223,\"currency\":\"USD\",\"currencyPrecision\":2,\"includedTaxes\":[{\"name\":\"Service Fee\",\"retail\":200,\"net\":100}]}', '2025-06-05 03:11:21'),
(8, 'BKG68410AD9BB21D', 'klookunititem-child-sml-68410ad9bb8a', 'child-sml', 1, 1, 2795.00, NULL, NULL, NULL, NULL, 'BKG68410AD9BB21D-CHILD-1229', 'ON_HOLD', NULL, NULL, NULL, NULL, NULL, '{\"original\":3005,\"retail\":2795,\"net\":2375,\"currency\":\"USD\",\"currencyPrecision\":2,\"includedTaxes\":[]}', '2025-06-05 03:11:21'),
(9, 'BKG68410BD83505A', 'klookunititem-adult-sml-68410bd8353f', 'adult-sml', 1, 1, 4969.00, NULL, NULL, NULL, NULL, 'BKG68410BD83505A-ADULT-1202', 'ON_HOLD', NULL, NULL, NULL, NULL, NULL, '{\"original\":6169,\"retail\":4969,\"net\":4223,\"currency\":\"USD\",\"currencyPrecision\":2,\"includedTaxes\":[{\"name\":\"Service Fee\",\"retail\":200,\"net\":100}]}', '2025-06-05 03:15:36'),
(10, 'BKG68410BD83505A', 'klookunititem-child-sml-68410bd8354e', 'child-sml', 1, 1, 2795.00, NULL, NULL, NULL, NULL, 'BKG68410BD83505A-CHILD-1773', 'ON_HOLD', NULL, NULL, NULL, NULL, NULL, '{\"original\":3005,\"retail\":2795,\"net\":2375,\"currency\":\"USD\",\"currencyPrecision\":2,\"includedTaxes\":[]}', '2025-06-05 03:15:36'),
(11, 'BKG68410D4A9F805', 'klookunititem-adult-sml-68410d4a9fb7', 'adult-sml', 1, 1, 4969.00, NULL, NULL, NULL, NULL, 'BKG68410D4A9F805-ADULT-1460', 'ON_HOLD', NULL, NULL, NULL, NULL, NULL, '{\"original\":6169,\"retail\":4969,\"net\":4223,\"currency\":\"USD\",\"currencyPrecision\":2,\"includedTaxes\":[{\"name\":\"Service Fee\",\"retail\":200,\"net\":100}]}', '2025-06-05 03:21:46'),
(12, 'BKG68410D4A9F805', 'klookunititem-child-sml-68410d4a9fc1', 'child-sml', 1, 1, 2795.00, NULL, NULL, NULL, NULL, 'BKG68410D4A9F805-CHILD-1315', 'ON_HOLD', NULL, NULL, NULL, NULL, NULL, '{\"original\":3005,\"retail\":2795,\"net\":2375,\"currency\":\"USD\",\"currencyPrecision\":2,\"includedTaxes\":[]}', '2025-06-05 03:21:46'),
(13, 'BKG68410F54A6657', 'klookunititem-adult-sml-68410f54a6b1', 'adult-sml', 1, 1, 4969.00, NULL, NULL, NULL, NULL, 'BKG68410F54A6657-ADULT-1249', 'ON_HOLD', NULL, NULL, NULL, NULL, NULL, '{\"original\":6169,\"retail\":4969,\"net\":4223,\"currency\":\"USD\",\"currencyPrecision\":2,\"includedTaxes\":[{\"name\":\"Service Fee\",\"retail\":200,\"net\":100}]}', '2025-06-05 03:30:28'),
(14, 'BKG68410F54A6657', 'klookunititem-child-sml-68410f54a6c4', 'child-sml', 1, 1, 2795.00, NULL, NULL, NULL, NULL, 'BKG68410F54A6657-CHILD-1964', 'ON_HOLD', NULL, NULL, NULL, NULL, NULL, '{\"original\":3005,\"retail\":2795,\"net\":2375,\"currency\":\"USD\",\"currencyPrecision\":2,\"includedTaxes\":[]}', '2025-06-05 03:30:28'),
(15, 'BKG684110B86C3E9', 'klookunititem-adult-sml-684110b86c7c', 'adult-sml', 1, 1, 4969.00, NULL, NULL, NULL, NULL, 'BKG684110B86C3E9-ADULT-1771', 'ON_HOLD', NULL, NULL, NULL, NULL, NULL, '{\"original\":6169,\"retail\":4969,\"net\":4223,\"currency\":\"USD\",\"currencyPrecision\":2,\"includedTaxes\":[{\"name\":\"Service Fee\",\"retail\":200,\"net\":100}]}', '2025-06-05 03:36:24'),
(16, 'BKG684110B86C3E9', 'klookunititem-child-sml-684110b86c87', 'child-sml', 1, 1, 2795.00, NULL, NULL, NULL, NULL, 'BKG684110B86C3E9-CHILD-1689', 'ON_HOLD', NULL, NULL, NULL, NULL, NULL, '{\"original\":3005,\"retail\":2795,\"net\":2375,\"currency\":\"USD\",\"currencyPrecision\":2,\"includedTaxes\":[]}', '2025-06-05 03:36:24'),
(17, 'BKG684111529062F', 'klookunititem-adult-sml-68411152909a', 'adult-sml', 1, 1, 4969.00, NULL, NULL, NULL, NULL, 'BKG684111529062F-ADULT-1733', 'ON_HOLD', NULL, NULL, NULL, NULL, NULL, '{\"original\":6169,\"retail\":4969,\"net\":4223,\"currency\":\"USD\",\"currencyPrecision\":2,\"includedTaxes\":[{\"name\":\"Service Fee\",\"retail\":200,\"net\":100}]}', '2025-06-05 03:38:58'),
(18, 'BKG684111529062F', 'klookunititem-child-sml-6841115290d0', 'child-sml', 1, 1, 2795.00, NULL, NULL, NULL, NULL, 'BKG684111529062F-CHILD-1620', 'ON_HOLD', NULL, NULL, NULL, NULL, NULL, '{\"original\":3005,\"retail\":2795,\"net\":2375,\"currency\":\"USD\",\"currencyPrecision\":2,\"includedTaxes\":[]}', '2025-06-05 03:38:58');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `tbl_klook_booking_units`
--
ALTER TABLE `tbl_klook_booking_units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unit_item_uuid` (`unit_item_uuid`),
  ADD KEY `booking_id` (`booking_id`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `tbl_klook_booking_units`
--
ALTER TABLE `tbl_klook_booking_units`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
