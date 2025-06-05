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
-- Cấu trúc bảng cho bảng `tbl_klook_pricing_rules`
--

CREATE TABLE `tbl_klook_pricing_rules` (
  `id` int(11) NOT NULL,
  `rule_name` varchar(255) DEFAULT NULL COMMENT 'Tên gợi nhớ cho quy tắc giá này (ví dụ: "Giá hè 2025 Adul")',
  `product_id` varchar(255) NOT NULL COMMENT 'FK (logic) đến tbl_klook_products.id',
  `option_id` varchar(255) DEFAULT NULL COMMENT 'FK (logic) đến tbl_klook_product_options.id (nếu giá theo option hoặc unit)',
  `unit_id` varchar(255) DEFAULT NULL COMMENT 'FK (logic) đến tbl_klook_units.id (nếu giá theo unit)',
  `currency` varchar(3) NOT NULL COMMENT 'Mã tiền tệ (ví dụ: USD, VND)',
  `original_price` int(11) NOT NULL COMMENT 'Giá gốc (số nguyên, vd: 10000 cho 100.00 USD nếu precision=2)',
  `retail_price` int(11) NOT NULL COMMENT 'Giá bán lẻ Klook sẽ hiển thị (số nguyên)',
  `net_price` int(11) DEFAULT NULL COMMENT 'Giá net nhà cung cấp thu (số nguyên)',
  `currency_precision` int(11) NOT NULL DEFAULT 2 COMMENT 'Số chữ số thập phân của tiền tệ (USD=2, JPY=0)',
  `included_taxes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Mảng các đối tượng thuế đã bao gồm, ví dụ: [{"name": "VAT", "retail": 1000, "net": 800}]' CHECK (json_valid(`included_taxes`)),
  `valid_from` date DEFAULT NULL COMMENT 'Ngày quy tắc giá bắt đầu có hiệu lực (YYYY-MM-DD)',
  `valid_to` date DEFAULT NULL COMMENT 'Ngày quy tắc giá kết thúc hiệu lực (YYYY-MM-DD)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Quy tắc giá này có đang hoạt động không',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lưu trữ các quy tắc giá cho sản phẩm/option/unit';

--
-- Đang đổ dữ liệu cho bảng `tbl_klook_pricing_rules`
--

INSERT INTO `tbl_klook_pricing_rules` (`id`, `rule_name`, `product_id`, `option_id`, `unit_id`, `currency`, `original_price`, `retail_price`, `net_price`, `currency_precision`, `included_taxes`, `valid_from`, `valid_to`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Adult Small Group VND', 'phu-quoc-snorkeling-56528', 'SMALL_GROUP', 'adult-sml', 'VND', 1525600, 1284352, 1091699, 0, '[{\"name\": \"VAT\", \"retail\": 100000, \"net\": 80000}]', '2025-01-01', '2025-12-31', 1, '2025-06-03 09:42:01', '2025-06-04 03:39:15'),
(2, 'Adult Small Group USD', 'phu-quoc-snorkeling-56528', 'SMALL_GROUP', 'adult-sml', 'USD', 6169, 4969, 4223, 2, '[{\"name\": \"Service Fee\", \"retail\": 200, \"net\": 100}]', '2025-01-01', '2025-12-31', 1, '2025-06-03 09:42:01', '2025-06-04 03:39:42'),
(3, 'Child Small Group VND', 'phu-quoc-snorkeling-56528', 'SMALL_GROUP', 'child-sml', 'VND', 743700, 626097, 532182, 0, NULL, '2025-01-01', '2025-12-31', 1, '2025-06-03 09:42:01', '2025-06-04 03:39:58'),
(4, 'Child Small Group USD', 'phu-quoc-snorkeling-56528', 'SMALL_GROUP', 'child-sml', 'USD', 3005, 2795, 2375, 2, NULL, '2025-01-01', '2025-12-31', 1, '2025-06-03 09:42:01', '2025-06-04 03:40:16');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `tbl_klook_pricing_rules`
--
ALTER TABLE `tbl_klook_pricing_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pricing_lookup` (`product_id`(100),`option_id`(100),`unit_id`(100),`currency`,`is_active`,`valid_from`,`valid_to`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `tbl_klook_pricing_rules`
--
ALTER TABLE `tbl_klook_pricing_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
