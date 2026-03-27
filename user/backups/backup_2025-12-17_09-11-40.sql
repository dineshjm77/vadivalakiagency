DROP TABLE IF EXISTS `brands`;
CREATE TABLE `brands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `brand_name` varchar(100) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `brand_name` (`brand_name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `brands` VALUES("1","Bisleri","active","2025-12-16 14:55:17","2025-12-16 14:55:17");


DROP TABLE IF EXISTS `business_settings`;
CREATE TABLE `business_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `business_settings` VALUES("1","APR Water Agencies","Water Supply Business","Owner","info@aprwater.com","","9876543210","123 Main Street","Bangalore","Karnataka","560001","","","₹","18.00","30.00","INV","1001","7","10","","","","1","1","0","","","","","","","","","","1","2025-12-17 03:18:16","2025-12-17 03:18:16");


DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_name` (`category_name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` VALUES("1","Water","active","2025-12-16 14:55:33","2025-12-16 14:55:54");


DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_code` varchar(20) NOT NULL,
  `shop_name` varchar(150) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_contact` varchar(15) NOT NULL,
  `alternate_contact` varchar(15) DEFAULT NULL,
  `shop_location` text NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `customer_type` enum('retail','wholesale','hotel','office','residential','other') DEFAULT 'retail',
  `payment_terms` enum('cash','credit_7','credit_15','credit_30','prepaid','weekly','monthly') DEFAULT 'cash',
  `credit_limit` decimal(10,2) DEFAULT 0.00,
  `current_balance` decimal(10,2) DEFAULT 0.00,
  `total_purchases` decimal(10,2) DEFAULT 0.00,
  `last_purchase_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','inactive','blocked') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_code` (`customer_code`),
  KEY `idx_customer_code` (`customer_code`),
  KEY `idx_customer_contact` (`customer_contact`),
  KEY `idx_customer_name` (`customer_name`),
  KEY `idx_shop_name` (`shop_name`),
  KEY `idx_customer_type` (`customer_type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `customers` VALUES("1","CUST2512315","Hifi11 Technologies","ARIHARASUDHAN P","7200314099","","Papinayakanahalli variyar school opposite pennagaram main road dharmapuri","ariharasudhanonofficial@gmail.com","retail","cash","0.00","0.00","0.00","","","active","2025-12-16 17:38:00","2025-12-16 17:38:00");


DROP TABLE IF EXISTS `linemen`;
CREATE TABLE `linemen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `linemen` VALUES("1","LM2512949","Deepika","deepikasmileybaby@gmail.com","9597722767","dharmapuri","dharmapuri","Tamilnadu","636803","Central Zone","0.00","0.00","Deepika","$2y$10$gSbH5xLc0gHu7dYwtIobPeL2lP5l.ddBJ6ja34Q5rQDcIo0GPv3Pe","active","2025-12-16 12:15:55","2025-12-16 12:15:55");
INSERT INTO `linemen` VALUES("2","LM2512374","ARIHARASUDHAN P","ariharasudhanonofficial@gmail.com","7200314099","Maruvetupallam Aalamararhupatti\nAalamararhupatti bedarahalli po pennagaram tk","Dharmapuri","Tamil Nadu","636803","Central Zone","0.00","0.00","Admin","$2y$10$Z4v70z7HMPRoO0GHRL0ZF.IWB/SmzTwmadaM19hP.LjRvw5lIF3Zi","active","2025-12-16 14:19:18","2025-12-16 14:19:18");


DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_code` (`product_code`),
  KEY `category_id` (`category_id`),
  KEY `brand_id` (`brand_id`),
  KEY `idx_product_code` (`product_code`),
  KEY `idx_product_name` (`product_name`),
  KEY `idx_status` (`status`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_ibfk_2` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `products` VALUES("1","PROD2512541","Minaral Water 1 L","1","1","8.00","12.00","1000","4.00","50.00","","active","2025-12-16 15:13:22","2025-12-16 17:12:38");


DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` VALUES("1","low_stock_threshold","10","Low stock alert threshold in units","2025-12-16 17:24:05");


DROP TABLE IF EXISTS `stock_transactions`;
CREATE TABLE `stock_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `transaction_type` enum('purchase','sale','adjustment','return') DEFAULT 'purchase',
  `quantity` int(11) NOT NULL,
  `stock_price` decimal(10,2) NOT NULL,
  `previous_quantity` int(11) NOT NULL,
  `new_quantity` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_transaction_type` (`transaction_type`),
  CONSTRAINT `stock_transactions_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `stock_transactions` VALUES("1","1","purchase","500","8.00","500","1000","","","2025-12-16 15:34:29");
INSERT INTO `stock_transactions` VALUES("2","1","adjustment","500","0.00","1000","500","Damaged stock","","2025-12-16 15:46:19");
INSERT INTO `stock_transactions` VALUES("3","1","adjustment","500","0.00","500","1000","Undo of adjustment #2","","2025-12-16 17:12:38");


DROP TABLE IF EXISTS `support_tickets`;
CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



