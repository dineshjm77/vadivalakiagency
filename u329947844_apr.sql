-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Feb 19, 2026 at 08:47 AM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u329947844_apr`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','super_admin') DEFAULT 'admin',
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `remember_token` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `name`, `email`, `username`, `password`, `role`, `status`, `last_login`, `created_at`, `remember_token`) VALUES
(1, 'Admin User', 'admin@aprwater.com', 'admin', '$2y$10$YourHashedPasswordHere', 'super_admin', 'active', NULL, '2025-12-18 19:39:14', NULL),
(2, 'Ariharan', 'goldarts20@gmail.com', 'Ariharan', '$2y$10$gwniKAgiB08xkV6ZJ8oED.limwXGH.Z6p8ngU1tJLcsSPKxh15BEi', 'admin', 'active', '2026-01-08 12:45:41', '2025-12-18 19:42:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, NULL, 'delete_payment', '{\"payment_id\":\"PAY202512203701\",\"amount\":\"24.00\",\"customer_id\":1,\"order_id\":1,\"payment_method\":\"cash\",\"reference_no\":\"\",\"notes\":\"\",\"deleted_by\":null,\"deleted_at\":\"2025-12-21 21:53:45\"}', '2401:4900:88de:c30f:51b7:c33f:8fe8:816d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-21 16:23:45');

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE `brands` (
  `id` int(11) NOT NULL,
  `brand_name` varchar(100) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `brands`
--

INSERT INTO `brands` (`id`, `brand_name`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Bisleri', 'active', '2025-12-16 14:55:17', '2025-12-16 14:55:17'),
(2, 'Oreo', 'active', '2025-12-23 09:47:35', '2025-12-23 09:47:35'),
(3, 'Madhumitha Milk', 'active', '2025-12-26 13:56:40', '2025-12-26 13:56:40');

-- --------------------------------------------------------

--
-- Table structure for table `business_settings`
--

CREATE TABLE `business_settings` (
  `id` int(11) NOT NULL,
  `business_name` varchar(255) NOT NULL DEFAULT 'APR Water Agencies',
  `business_type` varchar(100) DEFAULT 'Water Supply Business',
  `contact_person` varchar(100) NOT NULL DEFAULT 'Owner',
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `mobile` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `gstin` varchar(20) DEFAULT NULL,
  `business_logo` varchar(255) DEFAULT NULL,
  `currency` varchar(10) DEFAULT '₹',
  `tax_percentage` decimal(5,2) DEFAULT 18.00,
  `default_profit_margin` decimal(5,2) DEFAULT 30.00,
  `invoice_prefix` varchar(10) DEFAULT 'INV',
  `invoice_start_no` int(11) DEFAULT 1001,
  `quote_validity_days` int(11) DEFAULT 7,
  `low_stock_threshold` int(11) DEFAULT 10,
  `invoice_footer` text DEFAULT NULL,
  `terms_conditions` text DEFAULT NULL,
  `payment_instructions` text DEFAULT NULL,
  `show_logo_invoice` tinyint(1) DEFAULT 1,
  `show_tax_invoice` tinyint(1) DEFAULT 1,
  `show_qr_code` tinyint(1) DEFAULT 0,
  `smtp_host` varchar(100) DEFAULT NULL,
  `smtp_port` varchar(10) DEFAULT NULL,
  `smtp_username` varchar(100) DEFAULT NULL,
  `smtp_password` varchar(100) DEFAULT NULL,
  `smtp_encryption` varchar(10) DEFAULT NULL,
  `from_email` varchar(100) DEFAULT NULL,
  `from_name` varchar(100) DEFAULT NULL,
  `invoice_email_subject` varchar(255) DEFAULT NULL,
  `invoice_email_body` text DEFAULT NULL,
  `auto_backup` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `business_settings`
--

INSERT INTO `business_settings` (`id`, `business_name`, `business_type`, `contact_person`, `email`, `phone`, `mobile`, `address`, `city`, `state`, `pincode`, `gstin`, `business_logo`, `currency`, `tax_percentage`, `default_profit_margin`, `invoice_prefix`, `invoice_start_no`, `quote_validity_days`, `low_stock_threshold`, `invoice_footer`, `terms_conditions`, `payment_instructions`, `show_logo_invoice`, `show_tax_invoice`, `show_qr_code`, `smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `smtp_encryption`, `from_email`, `from_name`, `invoice_email_subject`, `invoice_email_body`, `auto_backup`, `created_at`, `updated_at`) VALUES
(1, 'APR Water Agencies', 'Water Supply Business', 'Owner', 'info@aprwater.com', '9514931472', '9943615068', '123 Main Street', 'Bangalore', 'Karnataka', '560001', '', NULL, '₹', 18.00, 30.00, 'INV', 1001, 7, 10, NULL, NULL, NULL, 1, 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-12-17 03:18:16', '2025-12-26 13:47:17');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `category_name`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Water', 'active', '2025-12-16 14:55:33', '2025-12-16 14:55:54'),
(2, 'Biscuit', 'active', '2025-12-23 09:47:25', '2025-12-23 09:47:25'),
(3, 'Milk', 'active', '2025-12-26 13:56:16', '2025-12-26 13:56:16');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `customer_code` varchar(20) NOT NULL,
  `shop_name` varchar(150) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_contact` varchar(15) NOT NULL,
  `alternate_contact` varchar(15) DEFAULT NULL,
  `shop_location` text NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `customer_type` enum('retail','wholesale','hotel','office','residential','other') DEFAULT 'retail',
  `assigned_lineman_id` int(11) DEFAULT NULL,
  `assigned_area` varchar(100) DEFAULT NULL,
  `payment_terms` enum('cash','credit_7','credit_15','credit_30','prepaid','weekly','monthly') DEFAULT 'cash',
  `credit_limit` decimal(10,2) DEFAULT 0.00,
  `current_balance` decimal(10,2) DEFAULT 0.00,
  `total_purchases` decimal(10,2) DEFAULT 0.00,
  `last_purchase_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','inactive','blocked') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `customer_code`, `shop_name`, `customer_name`, `customer_contact`, `alternate_contact`, `shop_location`, `email`, `customer_type`, `assigned_lineman_id`, `assigned_area`, `payment_terms`, `credit_limit`, `current_balance`, `total_purchases`, `last_purchase_date`, `notes`, `status`, `created_at`, `updated_at`) VALUES
(1, 'CUST2512315', 'Hifi11 Technologies', 'ARIHARASUDHAN P', '7200314099', '', 'Papinayakanahalli variyar school opposite pennagaram main road dharmapuri', 'ariharasudhanonofficial@gmail.com', 'retail', 4, NULL, 'cash', 0.00, 811.00, 1393.00, '2026-01-08', '', 'active', '2025-12-16 17:38:00', '2026-01-08 12:49:37'),
(2, 'CUST2512423', 'Ramu water', 'RAMAKRISHNAN', '9943615068', '', 'BOMMASAMUTHIRAM', '', 'retail', 2, NULL, 'cash', 0.00, -85.00, 85.00, '2025-12-26', '', 'active', '2025-12-26 13:52:22', '2026-01-08 12:45:16');

-- --------------------------------------------------------

--
-- Table structure for table `deletion_logs`
--

CREATE TABLE `deletion_logs` (
  `id` int(11) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `data` text DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `email_type` varchar(50) NOT NULL,
  `sent_to` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `status` enum('sent','failed','pending') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `linemen`
--

CREATE TABLE `linemen` (
  `id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `assigned_area` varchar(100) NOT NULL,
  `salary` decimal(10,2) DEFAULT 0.00,
  `commission` decimal(5,2) DEFAULT 0.00,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('active','inactive','on_leave') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `remember_token` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `linemen`
--

INSERT INTO `linemen` (`id`, `employee_id`, `full_name`, `email`, `phone`, `address`, `city`, `state`, `pincode`, `assigned_area`, `salary`, `commission`, `username`, `password`, `status`, `created_at`, `updated_at`, `remember_token`) VALUES
(1, 'LM2512949', 'Deepika', 'deepikasmileybaby@gmail.com', '9597722767', 'dharmapuri', 'dharmapuri', 'Tamilnadu', '636803', 'Central Zone', 0.00, 0.00, 'Deepika', '$2y$10$gSbH5xLc0gHu7dYwtIobPeL2lP5l.ddBJ6ja34Q5rQDcIo0GPv3Pe', 'active', '2025-12-16 12:15:55', '2025-12-16 12:15:55', NULL),
(2, 'LM2512374', 'ARIHARASUDHAN', 'ariharasudhanonofficial@gmail.com', '7200314099', 'Maruvetupallam Aalamararhupatti\\r\\nAalamararhupatti bedarahalli po pennagaram tk', 'Dharmapuri', 'Tamil Nadu', '636803', 'Central Zone', 0.00, 0.00, 'Admin', '$2y$10$0hmPirvwXsAV1KuGNMKiROt864gww/2T8XiEr2pd2Pw/4ZVFKBBU2', 'active', '2025-12-16 14:19:18', '2025-12-18 19:29:18', NULL),
(3, 'LM2512711', 'Sanjeevan', 'goldarts20@gmail.com', '9025327501', 'Maruvetupallam Aalamararhupatti\r\nAalamararhupatti bedarahalli po pennagaram tk', 'Dharmapuri', 'Tamil Nadu', '636803', 'Central Zone', 0.00, 0.00, 'Sanjeev', '$2y$10$rIlaJiL8a8/GKGZN2aOI4u2u/fDPy9va5FXioKEr4u7ldqVhPsv2e', 'active', '2025-12-18 20:10:07', '2025-12-18 20:10:07', NULL),
(4, 'LM2512783', 'Chandru', '', '9025327501', '', '', '', '', 'Other', 0.00, 0.00, 'Chandru', '$2y$10$pXg2hwDIoGAuoaKXCoj.4eKySTFCKOYNDadjfegD39x4I45.QeU8.', 'active', '2025-12-23 09:53:38', '2025-12-23 09:53:38', NULL),
(5, 'LM2512716', 'THAMARAIKANNAN', '', '9043476157', '', '', '', '', 'Other', 0.00, 0.00, 'Thamarai', '$2y$10$Moupe98c7ffPhoaODywFrOio3F4D3zg6PHou65k4SwBhu2iGIl6kK', 'active', '2025-12-26 13:53:58', '2025-12-26 13:53:58', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `order_date` date NOT NULL,
  `total_items` int(11) DEFAULT 0,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','processing','delivered','cancelled') DEFAULT 'pending',
  `delivery_date` date DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `payment_method` varchar(50) NOT NULL DEFAULT 'cash',
  `payment_status` enum('paid','partial','pending') NOT NULL DEFAULT 'pending',
  `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `pending_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `customer_id`, `order_number`, `order_date`, `total_items`, `total_amount`, `status`, `delivery_date`, `payment_date`, `notes`, `created_by`, `created_at`, `payment_method`, `payment_status`, `paid_amount`, `pending_amount`, `updated_at`) VALUES
(1, 1, 'ORD202512202220', '2025-12-20', 0, 24.00, 'delivered', NULL, '2025-12-21 16:36:52', '', 3, '2025-12-20 10:07:57', 'cash', 'paid', 24.00, 0.00, '2025-12-21 16:36:52'),
(2, 1, 'ORD202512204916', '2025-12-20', 1, 564.00, 'delivered', NULL, '2025-12-21 16:36:58', '', 3, '2025-12-20 16:18:46', 'cash', 'paid', 60.00, 0.00, '2025-12-21 16:36:58'),
(3, 1, 'ORD202512224649', '2025-12-22', 0, 36.00, 'processing', NULL, NULL, '', 2, '2025-12-22 12:37:08', 'cash', 'paid', 36.00, 0.00, '2025-12-22 12:38:31'),
(4, 1, 'ORD202512268771', '2025-12-26', 0, 153.00, 'pending', NULL, NULL, '', 2, '2025-12-26 09:56:28', 'cash', 'paid', 153.00, 0.00, '2025-12-26 09:56:28'),
(5, 2, 'ORD202512265257', '2025-12-26', 0, 85.00, 'pending', NULL, NULL, '', 5, '2025-12-26 14:05:15', 'cash', 'paid', 85.00, 0.00, '2025-12-26 14:05:56'),
(6, 1, 'ORD202601088139', '2026-01-08', 0, 1000.00, 'pending', NULL, NULL, '', 4, '2026-01-08 12:49:37', 'cash', 'pending', 0.00, 1000.00, '2026-01-08 12:49:37');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`, `total`, `created_at`) VALUES
(1, 1, 1, 2, 12.00, 24.00, '2025-12-20 10:07:57'),
(4, 2, 1, 47, 12.00, 564.00, '2025-12-21 07:05:16'),
(5, 3, 1, 3, 12.00, 36.00, '2025-12-22 12:37:08'),
(6, 4, 2, 3, 15.00, 45.00, '2025-12-26 09:56:28'),
(7, 4, 1, 9, 12.00, 108.00, '2025-12-26 09:56:28'),
(8, 5, 3, 10, 8.50, 85.00, '2025-12-26 14:05:15'),
(9, 6, 3, 100, 8.50, 850.00, '2026-01-08 12:49:37'),
(10, 6, 2, 10, 15.00, 150.00, '2026-01-08 12:49:37');

-- --------------------------------------------------------

--
-- Table structure for table `payment_history`
--

CREATE TABLE `payment_history` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'cash',
  `reference_no` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_history`
--

INSERT INTO `payment_history` (`id`, `order_id`, `transaction_id`, `amount_paid`, `payment_method`, `reference_no`, `notes`, `created_by`, `created_at`) VALUES
(1, 1, NULL, 24.00, 'cash', '', '', NULL, '2025-12-21 16:36:52'),
(2, 2, NULL, 60.00, 'cash', '', '', NULL, '2025-12-21 16:36:58');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `product_code` varchar(20) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `stock_price` decimal(10,2) NOT NULL,
  `customer_price` decimal(10,2) NOT NULL,
  `quantity` int(11) DEFAULT 0,
  `profit` decimal(10,2) DEFAULT 0.00,
  `profit_percentage` decimal(5,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive','out_of_stock') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_code`, `product_name`, `category_id`, `brand_id`, `stock_price`, `customer_price`, `quantity`, `profit`, `profit_percentage`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'PROD2512541', 'Minaral Water 1 L', 1, 1, 8.00, 12.00, 1486, 4.00, 50.00, '', 'active', '2025-12-16 15:13:22', '2025-12-26 09:56:28'),
(2, 'PROD2512596', 'Oreo 10 pc ', 2, 2, 10.00, 15.00, 87, 5.00, 50.00, '', 'active', '2025-12-23 09:48:22', '2026-01-08 12:49:37'),
(3, 'PROD2512686', 'Milk 160 ml', 3, 3, 7.60, 8.50, 290, 0.90, 11.84, '', 'active', '2025-12-26 13:58:16', '2026-01-08 12:49:37');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES
(1, 'low_stock_threshold', '10', 'Low stock alert threshold in units', '2025-12-16 17:24:05');

-- --------------------------------------------------------

--
-- Table structure for table `status_logs`
--

CREATE TABLE `status_logs` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `old_status` varchar(20) DEFAULT NULL,
  `new_status` varchar(20) DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `status_logs`
--

INSERT INTO `status_logs` (`id`, `customer_id`, `old_status`, `new_status`, `changed_by`, `notes`, `created_at`) VALUES
(1, 1, 'payment_deleted', 'balance_adjusted', NULL, 'Payment deleted: PAY202512203701 - Amount: ₹24.00', '2025-12-21 16:23:45');

-- --------------------------------------------------------

--
-- Table structure for table `stock_requests`
--

CREATE TABLE `stock_requests` (
  `id` int(11) NOT NULL,
  `request_id` varchar(50) NOT NULL,
  `product_id` int(11) NOT NULL,
  `requested_qty` int(11) NOT NULL,
  `current_qty` int(11) NOT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `notes` text DEFAULT NULL,
  `requested_by` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_requests`
--

INSERT INTO `stock_requests` (`id`, `request_id`, `product_id`, `requested_qty`, `current_qty`, `priority`, `notes`, `requested_by`, `status`, `approved_by`, `approved_at`, `created_at`, `updated_at`) VALUES
(1, 'REQ20251220342', 1, 100, 993, 'medium', '', 3, 'completed', NULL, NULL, '2025-12-20 16:46:04', '2025-12-20 17:02:09');

-- --------------------------------------------------------

--
-- Table structure for table `stock_transactions`
--

CREATE TABLE `stock_transactions` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `transaction_type` enum('purchase','sale','adjustment','return') DEFAULT 'purchase',
  `quantity` int(11) NOT NULL,
  `stock_price` decimal(10,2) NOT NULL,
  `previous_quantity` int(11) NOT NULL,
  `new_quantity` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_transactions`
--

INSERT INTO `stock_transactions` (`id`, `product_id`, `transaction_type`, `quantity`, `stock_price`, `previous_quantity`, `new_quantity`, `notes`, `created_by`, `created_at`) VALUES
(1, 1, 'purchase', 500, 8.00, 500, 1000, '', NULL, '2025-12-16 15:34:29'),
(2, 1, 'adjustment', 500, 0.00, 1000, 500, 'Damaged stock', NULL, '2025-12-16 15:46:19'),
(3, 1, 'adjustment', 500, 0.00, 500, 1000, 'Undo of adjustment #2', NULL, '2025-12-16 17:12:38'),
(4, 1, 'purchase', 497, 8.00, 993, 1490, '', 3, '2025-12-20 17:00:10'),
(5, 1, 'purchase', 50, 8.00, 1490, 1540, 'Quick increase by 50 units', 3, '2025-12-20 17:48:32'),
(6, 1, 'sale', 11, 0.00, 1540, 1529, 'Order edited: increased sold qty by 11 (order id 2)', 3, '2025-12-21 07:05:12'),
(7, 2, 'purchase', 50, 10.00, 50, 100, '', NULL, '2025-12-23 09:49:48'),
(8, 3, 'adjustment', 100, 0.00, 290, 390, 'Other', NULL, '2025-12-26 14:09:14');

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `status` enum('new','in_progress','resolved','closed') DEFAULT 'new',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `payment_id` varchar(50) DEFAULT NULL,
  `type` enum('payment','purchase','refund','adjustment') DEFAULT 'payment',
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'cash',
  `reference_no` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `customer_id`, `order_id`, `payment_id`, `type`, `amount`, `payment_method`, `reference_no`, `notes`, `created_by`, `created_at`) VALUES
(2, 1, 1, 'PAY2025122122065240', 'payment', 24.00, '0', '', '', NULL, '2025-12-21 16:36:52'),
(3, 1, 2, 'PAY2025122122065873', 'payment', 60.00, '0', '', '', NULL, '2025-12-21 16:36:58'),
(4, 1, 3, 'PAY20251222142', 'payment', 36.00, 'cash', '', '', 1, '2025-12-22 12:38:31'),
(5, 1, NULL, NULL, 'payment', 153.00, 'cash', 'ORD202512268771', 'Payment for order #ORD202512268771', 2, '2025-12-26 09:56:28'),
(6, 2, NULL, NULL, 'payment', 50.00, 'cash', 'ORD202512265257', 'Payment for order #ORD202512265257', 5, '2025-12-26 14:05:15'),
(7, 2, 5, 'PAY202512267765', 'payment', 35.00, 'cash', '', '', 5, '2025-12-26 14:05:56');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_remember_token_admin` (`remember_token`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `brand_name` (`brand_name`);

--
-- Indexes for table `business_settings`
--
ALTER TABLE `business_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_code` (`customer_code`),
  ADD KEY `idx_customer_code` (`customer_code`),
  ADD KEY `idx_customer_contact` (`customer_contact`),
  ADD KEY `idx_customer_name` (`customer_name`),
  ADD KEY `idx_shop_name` (`shop_name`),
  ADD KEY `idx_customer_type` (`customer_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_assigned_lineman` (`assigned_lineman_id`),
  ADD KEY `idx_assigned_area` (`assigned_area`);

--
-- Indexes for table `deletion_logs`
--
ALTER TABLE `deletion_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_table_record` (`table_name`,`record_id`),
  ADD KEY `idx_deleted_at` (`deleted_at`),
  ADD KEY `idx_deleted_by` (`deleted_by`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `email_type` (`email_type`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `linemen`
--
ALTER TABLE `linemen`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_remember_token_lineman` (`remember_token`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orders_customer` (`customer_id`),
  ADD KEY `idx_orders_created_by` (`created_by`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_pending_amount` (`pending_amount`),
  ADD KEY `idx_order_date` (`order_date`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_items_order` (`order_id`),
  ADD KEY `idx_order_items_product` (`product_id`);

--
-- Indexes for table `payment_history`
--
ALTER TABLE `payment_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_code` (`product_code`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `brand_id` (`brand_id`),
  ADD KEY `idx_product_code` (`product_code`),
  ADD KEY `idx_product_name` (`product_name`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_setting_key` (`setting_key`);

--
-- Indexes for table `status_logs`
--
ALTER TABLE `status_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `stock_requests`
--
ALTER TABLE `stock_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_transaction_type` (`transaction_type`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transactions_customer` (`customer_id`),
  ADD KEY `fk_transactions_order` (`order_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `business_settings`
--
ALTER TABLE `business_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `deletion_logs`
--
ALTER TABLE `deletion_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `linemen`
--
ALTER TABLE `linemen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `payment_history`
--
ALTER TABLE `payment_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `status_logs`
--
ALTER TABLE `status_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stock_requests`
--
ALTER TABLE `stock_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`assigned_lineman_id`) REFERENCES `linemen` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD CONSTRAINT `email_logs_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `email_logs_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_history`
--
ALTER TABLE `payment_history`
  ADD CONSTRAINT `payment_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payment_history_ibfk_2` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `status_logs`
--
ALTER TABLE `status_logs`
  ADD CONSTRAINT `status_logs_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_requests`
--
ALTER TABLE `stock_requests`
  ADD CONSTRAINT `stock_requests_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  ADD CONSTRAINT `stock_transactions_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_transactions_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
