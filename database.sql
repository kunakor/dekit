-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 02, 2026 at 07:06 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `school_asset`
--

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

DROP TABLE IF EXISTS `assets`;
CREATE TABLE `assets` (
  `id` int(11) NOT NULL,
  `asset_code` varchar(50) NOT NULL,
  `asset_name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `received_date` date DEFAULT NULL,
  `status` enum('available','in_use','repair','disposed','stationed') NOT NULL DEFAULT 'available',
  `image` varchar(255) DEFAULT NULL,
  `borrowed_by` varchar(100) DEFAULT NULL,
  `current_user_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `department_id` int(11) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT 0.00,
  `useful_life` int(11) DEFAULT 5,
  `salvage_value` decimal(10,2) DEFAULT 1.00,
  `category_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assets`
--

INSERT INTO `assets` (`id`, `asset_code`, `asset_name`, `category`, `location`, `description`, `price`, `received_date`, `status`, `image`, `borrowed_by`, `current_user_id`, `updated_at`, `department_id`, `serial_number`, `cost`, `useful_life`, `salvage_value`, `category_id`) VALUES
(6, '4444', 'ครู อดิสร', 'เครื่องคอมพิวเตอร์ (PC)', 'อาคาร 1 1035', '', 1.00, '2026-02-12', 'available', '', NULL, NULL, '2026-02-15 11:32:58', 8, '', 0.00, 5, 1.00, NULL),
(7, '14566', 'ครู อดิสร', 'โต๊ะ/เก้าอี้', 'อาคาร 1 1035', '', 12.00, '2026-02-12', 'available', NULL, NULL, NULL, '2026-02-13 01:26:11', NULL, NULL, 0.00, 5, 1.00, NULL),
(8, '888', '999', 'โปรเจคเตอร์', 'IT', '', 150.00, '2026-02-12', 'repair', '', NULL, NULL, '2026-02-15 11:47:44', 1, '', 0.00, 1, 1.00, NULL),
(9, '88888', '1111', 'อื่นๆ', 'IT', '', 9.00, '2026-02-12', 'disposed', '', NULL, NULL, '2026-02-16 14:03:44', 8, '', 0.00, 1, 1.00, NULL),
(11, 'ACC-CH-69-0001', '1234', 'เก้าอี้', 'อาคาร 1 ', '', 150.00, '2026-02-16', 'available', 'a_699329541ca7b.jpg', NULL, NULL, '2026-02-16 14:27:32', 1, '', 0.00, 2, 1.00, NULL),
(12, 'GEN-PC-69-0001', 'ครู อดิสร', 'เครื่องคอมพิวเตอร์ (PC)', ' 1035', '', 12.00, '2026-02-17', 'stationed', NULL, NULL, NULL, '2026-02-17 15:05:48', 9, '679', 0.00, 5, 1.00, NULL),
(13, 'IT-OT-69-0001', 'โทรศัพน์', 'อื่นๆ', 'อาคาร 9 ชั้น 1', 'หน้าจอเป็นเส้น แบตร์เตอรี่ เสื่อม', 1299.00, '2026-02-20', 'repair', '', NULL, NULL, '2026-02-20 02:45:44', 3, '1114449957623', 0.00, 5, 1.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `borrow_logs`
--

DROP TABLE IF EXISTS `borrow_logs`;
CREATE TABLE `borrow_logs` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` enum('borrow','return') NOT NULL,
  `log_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `note` text DEFAULT NULL,
  `action_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `borrow_logs`
--

INSERT INTO `borrow_logs` (`id`, `asset_id`, `user_id`, `action`, `log_date`, `note`, `action_date`) VALUES
(1, 1, 1, 'return', '2026-02-12 09:00:19', NULL, '2026-02-12 13:05:24'),
(2, 2, 2, 'borrow', '2026-02-12 09:01:15', NULL, '2026-02-12 13:05:24'),
(3, 2, 2, 'return', '2026-02-12 09:02:05', NULL, '2026-02-12 13:05:24'),
(4, 2, 2, 'borrow', '2026-02-12 09:07:40', NULL, '2026-02-12 13:05:24'),
(5, 3, 4, 'borrow', '2026-02-12 09:10:54', NULL, '2026-02-12 13:05:24'),
(6, 4, 4, 'borrow', '2026-02-12 09:10:54', NULL, '2026-02-12 13:05:24'),
(7, 5, 2, 'borrow', '2026-02-12 12:01:13', NULL, '2026-02-12 13:05:24'),
(8, 5, 2, 'return', '2026-02-12 12:04:21', NULL, '2026-02-12 13:05:24'),
(9, 6, 1, 'borrow', '2026-02-12 12:18:13', NULL, '2026-02-12 13:05:24'),
(10, 5, 2, 'borrow', '2026-02-12 13:08:51', NULL, '2026-02-12 13:08:51'),
(11, 7, 1, 'return', '2026-02-13 01:26:11', NULL, '2026-02-13 01:26:11'),
(12, 6, 1, 'return', '2026-02-13 01:26:22', NULL, '2026-02-13 01:26:22'),
(13, 5, 1, '', '2026-02-15 11:29:18', NULL, '2026-02-15 11:29:18'),
(14, 8, 1, '', '2026-02-15 11:32:38', NULL, '2026-02-15 11:32:38'),
(15, 6, 1, '', '2026-02-15 11:32:58', NULL, '2026-02-15 11:32:58'),
(16, 8, 1, '', '2026-02-15 11:47:44', NULL, '2026-02-15 11:47:44'),
(17, 8, 1, '', '2026-02-15 11:49:25', NULL, '2026-02-15 11:49:25'),
(18, 10, 1, '', '2026-02-16 13:30:28', NULL, '2026-02-16 13:30:28'),
(19, 9, 1, '', '2026-02-16 13:48:01', NULL, '2026-02-16 13:48:01'),
(20, 5, 1, 'return', '2026-02-16 13:53:37', NULL, '2026-02-16 13:53:37'),
(21, 9, 1, '', '2026-02-16 14:03:44', NULL, '2026-02-16 14:03:44'),
(22, 10, 1, '', '2026-02-16 14:16:29', NULL, '2026-02-16 14:16:29'),
(23, 11, 1, '', '2026-02-16 14:27:17', NULL, '2026-02-16 14:27:17'),
(24, 11, 1, '', '2026-02-16 14:27:32', NULL, '2026-02-16 14:27:32'),
(25, 12, 15, '', '2026-02-17 15:05:48', NULL, '2026-02-17 15:05:48'),
(26, 13, 2, '', '2026-02-20 02:45:00', NULL, '2026-02-20 02:45:00'),
(27, 13, 2, '', '2026-02-20 02:46:25', NULL, '2026-02-20 02:46:25');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `code`, `name`) VALUES
(1, 'PC', 'เครื่องคอมพิวเตอร์ (PC)'),
(2, 'NB', 'โน้ตบุ๊ก (Notebook)'),
(3, 'PJ', 'โปรเจคเตอร์'),
(4, 'TB', 'โต๊ะทำงาน/โต๊ะเรียน'),
(5, 'CH', 'เก้าอี้'),
(6, 'AC', 'เครื่องปรับอากาศ'),
(7, 'PR', 'เครื่องปริ้นเตอร์'),
(8, 'OT', 'อื่นๆ');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact_person` varchar(150) DEFAULT 'เจ้าหน้าที่สาขา'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `code`, `name`, `contact_person`) VALUES
(1, 'ACC', 'การบัญชี (Accounting)', 'เจ้าหน้าที่สาขา'),
(2, 'MKT', 'การตลาด (Marketing)', 'เจ้าหน้าที่สาขา'),
(3, 'IT', 'เทคโนโลยีสารสนเทศ (Information Technology)', 'ครูพรชัยตุ่นแก้ว'),
(4, 'COM', 'คอมพิวเตอร์ธุรกิจ (Computer Business)', 'เจ้าหน้าที่สาขา'),
(5, 'LOG', 'การจัดการโลจิสติกส์ (Logistics)', 'เจ้าหน้าที่สาขา'),
(6, 'HTL', 'การโรงแรม (Hotel Management)', 'เจ้าหน้าที่สาขา'),
(7, 'TRM', 'การท่องเที่ยว (Tourism)', 'เจ้าหน้าที่สาขา'),
(8, 'ENG', 'ภาษาต่างประเทศธุรกิจ (Business English)', 'เจ้าหน้าที่สาขา'),
(9, 'GEN', 'หมวดวิชาสามัญ (General Education)', 'เจ้าหน้าที่สาขา');

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

DROP TABLE IF EXISTS `login_logs`;
CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL,
  `teacher_id` varchar(50) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_logs`
--

INSERT INTO `login_logs` (`id`, `teacher_id`, `ip_address`, `login_time`) VALUES
(1, 'T001', '::1', '2026-02-12 08:34:40'),
(2, 'T001', '::1', '2026-02-12 08:47:05'),
(3, 'T002', '::1', '2026-02-12 08:48:21'),
(4, 'T001', '::1', '2026-02-12 08:48:49'),
(5, 'T002', '::1', '2026-02-12 08:59:53'),
(6, 'T001', '::1', '2026-02-12 09:00:13'),
(7, 'T002', '::1', '2026-02-12 09:01:07'),
(8, 'T001', '::1', '2026-02-12 09:01:21'),
(9, 'T003', '::1', '2026-02-12 09:01:47'),
(10, 'T002', '::1', '2026-02-12 09:02:00'),
(11, 'T001', '::1', '2026-02-12 09:08:02'),
(12, 'T004', '::1', '2026-02-12 09:10:37'),
(13, 'T001', '::1', '2026-02-12 11:22:45'),
(14, 'T002', '::1', '2026-02-12 12:00:38'),
(15, 'T001', '::1', '2026-02-12 12:12:04'),
(16, 'T001', '::1', '2026-02-12 12:31:08'),
(17, 'T002', '::1', '2026-02-12 12:42:20'),
(18, 'T001', '::1', '2026-02-12 13:27:12'),
(19, 'T002', '::1', '2026-02-13 01:25:39'),
(20, 'T001', '::1', '2026-02-13 01:26:03'),
(21, 'T001', '::1', '2026-02-13 02:45:32'),
(22, 'T001', '::1', '2026-02-13 02:48:38'),
(23, 'T001', '::1', '2026-02-15 10:40:51');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_logs`
--

DROP TABLE IF EXISTS `maintenance_logs`;
CREATE TABLE `maintenance_logs` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `issue_description` text NOT NULL COMMENT 'อาการเสีย',
  `repair_cost` decimal(10,2) DEFAULT 0.00 COMMENT 'ค่าซ่อม',
  `vendor_name` varchar(100) DEFAULT NULL COMMENT 'ชื่อร้านซ่อม/ช่าง',
  `repair_date` date NOT NULL COMMENT 'วันที่ส่งซ่อม',
  `created_by` int(11) DEFAULT NULL COMMENT 'User ID คนบันทึก',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `maintenance_logs`
--

INSERT INTO `maintenance_logs` (`id`, `asset_id`, `issue_description`, `repair_cost`, `vendor_name`, `repair_date`, `created_by`, `created_at`) VALUES
(1, 8, 'เปิดไม่ติด ', 150.00, 'ร้าน Compass', '2026-02-15', 1, '2026-02-15 11:49:25'),
(2, 13, 'จอเป้นเส้น แบตร์เตอรี่เสื่อม', 23599.00, 'ร้าน Compass', '2026-02-20', 2, '2026-02-20 02:45:00'),
(3, 13, 'จอเป็นเส้น แบตเตอรี่เสื่อม', 599.00, 'ร้าน Compass', '2026-02-20', 2, '2026-02-20 02:46:25');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `teacher_id` varchar(50) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'user',
  `department_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `teacher_id`, `password`, `fullname`, `role`, `department_id`, `created_at`) VALUES
(1, 'admin', 'ADM001', '1234', 'ผู้ดูแลระบบสูงสุด', 'admin', NULL, '2026-02-15 12:13:08'),
(2, 'T001', 'T001', '1234', 'อาจารย์สมชาย', 'admin', NULL, '2026-02-15 12:25:30'),
(3, 'T002', NULL, '1234', 'อาจารย์ สมจิต', '', NULL, '2026-02-15 12:29:03'),
(5, 'T003', 'T003', '12345', 'อาจารย์ สมศรี', '', NULL, '2026-02-15 12:30:01'),
(11, 'T0011', NULL, '$2y$10$HOsuN7WYqW7Fo4Y74h00Ke3m5u6BQ8Vq9lh2P9dv01cWRfjoiI6ly', 'ครูพรชัย ตุ่นแกง', 'teacher', 3, '2026-02-16 14:52:15'),
(13, 'T0012', NULL, '$2y$10$F//ZsOQ49DpKACeQcqhPW.qvt4Tl9i8klrI7GoQd35EOwfk0WL.Dy', 'ครูพรชัย ตุ่นแกงง', 'teacher', 1, '2026-02-17 14:44:47'),
(14, 'T0013', NULL, '$2y$10$0gQbqc7.cNs6miNTGe8uPeB/LJTUtMrvXhgro7KWR6Mwhj/TDDAf2', 'ครูพรชัย ตุ่นแกง', 'teacher', 1, '2026-02-17 14:52:37'),
(15, 'T0015', NULL, '$2y$10$dD8FUrzRTHw.355PDycVHuPgUxD0tl6R3bvpaqm.W6KLsej0QT5Ri', 'ครูพรชัย ตุ่นแก', 'teacher', 9, '2026-02-17 14:59:15');

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_asset_value`
-- (See below for the actual view)
--

DROP TABLE IF EXISTS `view_asset_value`;
CREATE TABLE `view_asset_value` (
`id` int(11)
,`asset_code` varchar(50)
,`asset_name` varchar(100)
,`cost` decimal(10,2)
,`useful_life` int(11)
,`received_date` date
,`years_used` bigint(21)
,`current_value` decimal(36,6)
);

-- --------------------------------------------------------

--
-- Structure for view `view_asset_value`
--
DROP TABLE IF EXISTS `view_asset_value`;
DROP VIEW IF EXISTS `view_asset_value`;

CREATE ALGORITHM=UNDEFINED VIEW `view_asset_value`  AS SELECT `assets`.`id` AS `id`, `assets`.`asset_code` AS `asset_code`, `assets`.`asset_name` AS `asset_name`, `assets`.`cost` AS `cost`, `assets`.`useful_life` AS `useful_life`, `assets`.`received_date` AS `received_date`, timestampdiff(YEAR,`assets`.`received_date`,curdate()) AS `years_used`, CASE WHEN timestampdiff(YEAR,`assets`.`received_date`,curdate()) >= `assets`.`useful_life` THEN `assets`.`salvage_value` ELSE `assets`.`cost`- (`assets`.`cost` - `assets`.`salvage_value`) / `assets`.`useful_life` * timestampdiff(YEAR,`assets`.`received_date`,curdate()) END AS `current_value` FROM `assets` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `asset_code` (`asset_code`);

--
-- Indexes for table `borrow_logs`
--
ALTER TABLE `borrow_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `maintenance_logs`
--
ALTER TABLE `maintenance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_id` (`asset_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `borrow_logs`
--
ALTER TABLE `borrow_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `maintenance_logs`
--
ALTER TABLE `maintenance_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `maintenance_logs`
--
ALTER TABLE `maintenance_logs`
  ADD CONSTRAINT `maintenance_logs_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE;
COMMIT;

SET FOREIGN_KEY_CHECKS = 1;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;