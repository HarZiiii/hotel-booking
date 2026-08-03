-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 28, 2026 at 03:00 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hotel_booking_system_v3`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_value` longtext DEFAULT NULL,
  `new_value` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action`, `table_name`, `record_id`, `old_value`, `new_value`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'DATABASE_PROVISIONING', 'users', 1, NULL, 'Initial seeding config root profile deployed.', '127.0.0.1', 'Mozilla/5.0 Terminal Platform Engine', '2026-07-19 15:04:08'),
(2, 1, 'BOOKING_STATUS_UPDATED', 'bookings', 5, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 14:15:57'),
(3, 1, 'PAYMENT_STATUS_UPDATED', 'payments', 5, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 14:38:02'),
(4, 1, 'PAYMENT_STATUS_UPDATED', 'payments', 4, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 14:38:05'),
(5, 1, 'PAYMENT_STATUS_UPDATED', 'payments', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 14:38:07'),
(6, 1, 'PAYMENT_STATUS_UPDATED', 'payments', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 14:38:15'),
(7, 1, 'HOTEL_APPROVED', 'hotels', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 14:38:35'),
(8, 3, 'BOOKING_STATUS_UPDATED', 'bookings', 15, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 14:41:15'),
(9, 3, 'BOOKING_STATUS_UPDATED', 'bookings', 4, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 14:41:21'),
(10, 3, 'BOOKING_STATUS_UPDATED', 'bookings', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 14:41:24'),
(11, 3, 'BOOKING_STATUS_UPDATED', 'bookings', 15, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 14:41:27'),
(12, 3, 'COMMISSION_PAYMENT_SUBMITTED', 'commissions', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 14:42:59');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `booking_id` int(11) NOT NULL,
  `booking_code` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `hotel_id` int(11) NOT NULL,
  `check_in` date NOT NULL,
  `check_out` date NOT NULL,
  `adults` int(11) DEFAULT 1,
  `children` int(11) DEFAULT 0,
  `rooms_booked` int(11) DEFAULT 1,
  `total_amount` decimal(12,2) NOT NULL,
  `booking_status` enum('Pending','Confirmed','Checked In','Checked Out','Cancelled','Completed','Expired') DEFAULT 'Pending',
  `cancellation_policy` enum('Free Cancellation','Non-refundable') DEFAULT 'Free Cancellation',
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`booking_id`, `booking_code`, `customer_id`, `hotel_id`, `check_in`, `check_out`, `adults`, `children`, `rooms_booked`, `total_amount`, `booking_status`, `cancellation_policy`, `expires_at`, `created_at`, `updated_at`) VALUES
(1, 'HBSV3-A98B23', 4, 1, '2026-08-01', '2026-08-03', 2, 1, 1, 240000.00, 'Cancelled', 'Free Cancellation', NULL, '2026-07-19 15:04:08', '2026-07-20 12:06:27'),
(2, 'HBSV3-C12F45', 4, 2, '2026-09-10', '2026-09-11', 2, 0, 1, 95000.00, 'Checked Out', 'Free Cancellation', NULL, '2026-07-19 15:04:08', '2026-07-25 14:41:24'),
(3, 'HBSV3-84991A', 4, 1, '2026-07-21', '2026-07-23', 1, 0, 1, 240000.00, 'Cancelled', 'Free Cancellation', NULL, '2026-07-20 12:02:44', '2026-07-20 12:06:15'),
(4, 'HBSV3-519E05', 4, 2, '2026-07-23', '2026-07-24', 1, 0, 2, 190000.00, 'Checked Out', 'Free Cancellation', NULL, '2026-07-20 12:16:08', '2026-07-25 14:41:21'),
(5, 'HBSV3-11DC60', 4, 1, '2026-07-21', '2026-07-22', 1, 0, 3, 255000.00, 'Checked Out', 'Free Cancellation', NULL, '2026-07-20 12:19:45', '2026-07-20 14:46:02'),
(6, 'HBSV3-D69A99', 4, 1, '2026-07-21', '2026-07-23', 1, 0, 1, 170000.00, 'Checked Out', 'Free Cancellation', NULL, '2026-07-20 15:00:17', '2026-07-22 06:06:35'),
(7, 'HBSV3-C5AC2A', 4, 1, '2026-07-28', '2026-07-29', 1, 0, 1, 120000.00, 'Checked Out', 'Free Cancellation', NULL, '2026-07-20 16:35:07', '2026-07-21 13:50:39'),
(8, 'HBSV3-B80943', 4, 1, '2026-07-23', '2026-07-24', 1, 0, 1, 120000.00, 'Checked Out', 'Free Cancellation', NULL, '2026-07-20 16:38:23', '2026-07-22 06:06:26'),
(9, 'HBSV3-C1E38D', 4, 1, '2026-07-25', '2026-07-26', 1, 0, 1, 120000.00, 'Checked Out', 'Free Cancellation', NULL, '2026-07-20 16:41:51', '2026-07-22 06:06:24'),
(10, 'HBSV3-66844B', 4, 1, '2026-07-25', '2026-07-26', 1, 0, 1, 120000.00, 'Checked Out', 'Free Cancellation', NULL, '2026-07-20 16:42:17', '2026-07-22 06:06:22'),
(11, 'HBSV3-795857', 4, 1, '2026-07-22', '2026-07-23', 1, 0, 2, 240000.00, 'Checked Out', 'Free Cancellation', NULL, '2026-07-21 11:16:42', '2026-07-22 06:06:20'),
(12, 'HBSV3-97BD3B', 4, 1, '2026-07-30', '2026-07-31', 1, 0, 1, 150000.00, 'Checked Out', 'Free Cancellation', NULL, '2026-07-21 14:02:53', '2026-07-22 06:06:14'),
(13, 'HBSV3-656CF0', 4, 1, '2026-07-30', '2026-07-31', 1, 0, 1, 150000.00, 'Checked Out', 'Free Cancellation', NULL, '2026-07-21 14:08:22', '2026-07-22 06:06:11'),
(14, 'HBSV3-14F3E2', 4, 1, '2026-07-22', '2026-07-23', 1, 0, 1, 85000.00, 'Checked Out', 'Free Cancellation', NULL, '2026-07-21 14:15:09', '2026-07-22 06:05:44'),
(15, 'HBSV3-F98D06', 4, 2, '2026-07-31', '2026-08-01', 1, 0, 1, 95000.00, 'Checked Out', 'Free Cancellation', NULL, '2026-07-22 06:04:21', '2026-07-25 14:41:27'),
(16, 'HBSV3-470E91', 4, 1, '2026-08-01', '2026-08-02', 1, 0, 1, 85000.00, 'Checked Out', 'Free Cancellation', NULL, '2026-07-23 05:57:16', '2026-07-23 06:03:43'),
(17, 'HBSV3-D1B122', 4, 1, '2026-07-28', '2026-07-30', 1, 0, 1, 170000.00, 'Pending', 'Free Cancellation', NULL, '2026-07-28 12:53:04', '2026-07-28 12:53:04');

-- --------------------------------------------------------

--
-- Table structure for table `booking_rooms`
--

CREATE TABLE `booking_rooms` (
  `booking_room_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `price_per_night` decimal(12,2) NOT NULL,
  `total_price` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking_rooms`
--

INSERT INTO `booking_rooms` (`booking_room_id`, `booking_id`, `room_id`, `quantity`, `price_per_night`, `total_price`) VALUES
(1, 1, 1, 1, 120000.00, 240000.00),
(2, 2, 3, 1, 95000.00, 95000.00),
(3, 3, 1, 1, 120000.00, 240000.00),
(4, 4, 3, 2, 95000.00, 190000.00),
(5, 5, 2, 3, 85000.00, 255000.00),
(6, 6, 2, 1, 85000.00, 170000.00),
(7, 7, 1, 1, 120000.00, 120000.00),
(8, 8, 1, 1, 120000.00, 120000.00),
(9, 9, 1, 1, 120000.00, 120000.00),
(10, 10, 1, 1, 120000.00, 120000.00),
(11, 11, 1, 2, 120000.00, 240000.00),
(12, 12, 4, 1, 150000.00, 150000.00),
(13, 13, 4, 1, 150000.00, 150000.00),
(14, 14, 2, 1, 85000.00, 85000.00),
(15, 15, 3, 1, 95000.00, 95000.00),
(16, 16, 2, 1, 85000.00, 85000.00),
(17, 17, 2, 1, 85000.00, 170000.00);

-- --------------------------------------------------------

--
-- Table structure for table `commissions`
--

CREATE TABLE `commissions` (
  `commission_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_slip` varchar(255) NOT NULL,
  `booking_amount` decimal(12,2) NOT NULL,
  `commission_rate` decimal(5,2) NOT NULL DEFAULT 10.00,
  `commission_amount` decimal(12,2) NOT NULL,
  `owner_amount` decimal(12,2) NOT NULL,
  `commission_status` enum('Pending','Paid') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `commissions`
--

INSERT INTO `commissions` (`commission_id`, `booking_id`, `owner_id`, `amount`, `payment_slip`, `booking_amount`, `commission_rate`, `commission_amount`, `owner_amount`, `commission_status`, `created_at`) VALUES
(1, 1, 2, 0.00, '1784646963_makeup.jpg', 240000.00, 10.00, 24000.00, 216000.00, 'Paid', '2026-07-19 15:04:08'),
(2, 14, 2, 0.00, '1784646963_makeup.jpg', 85000.00, 10.00, 8500.00, 76500.00, 'Paid', '2026-07-21 14:35:58'),
(3, 5, 2, 0.00, '1784647454_makeup.jpg', 255000.00, 10.00, 25500.00, 229500.00, 'Pending', '2026-07-21 15:24:14'),
(4, 7, 2, 0.00, '1784647454_makeup.jpg', 120000.00, 10.00, 12000.00, 108000.00, 'Pending', '2026-07-21 15:24:15'),
(5, 8, 2, 0.00, '1784647454_makeup.jpg', 120000.00, 10.00, 12000.00, 108000.00, 'Pending', '2026-07-21 15:24:15'),
(6, 9, 2, 0.00, '1784647454_makeup.jpg', 120000.00, 10.00, 12000.00, 108000.00, 'Pending', '2026-07-21 15:24:15'),
(7, 10, 2, 0.00, '1784647454_makeup.jpg', 120000.00, 10.00, 12000.00, 108000.00, 'Pending', '2026-07-21 15:24:15'),
(8, 11, 2, 0.00, '1784647454_makeup.jpg', 240000.00, 10.00, 24000.00, 216000.00, '', '2026-07-21 15:24:15'),
(9, 15, 3, 0.00, '', 95000.00, 10.00, 9500.00, 85500.00, 'Pending', '2026-07-22 06:04:29'),
(10, 6, 2, 0.00, '1784700450_makeup.jpg', 170000.00, 10.00, 17000.00, 153000.00, 'Pending', '2026-07-22 06:07:30'),
(11, 12, 2, 0.00, '1784700450_makeup.jpg', 150000.00, 10.00, 15000.00, 135000.00, 'Pending', '2026-07-22 06:07:30'),
(12, 13, 2, 0.00, '1784700450_makeup.jpg', 150000.00, 10.00, 15000.00, 135000.00, 'Pending', '2026-07-22 06:07:30'),
(13, 16, 2, 0.00, '', 85000.00, 10.00, 8500.00, 76500.00, 'Pending', '2026-07-23 05:57:36'),
(14, 2, 3, 0.00, '1784990579_bc1c911626.jpg', 95000.00, 10.00, 9500.00, 85500.00, 'Pending', '2026-07-25 14:42:59'),
(15, 4, 3, 0.00, '1784990579_bc1c911626.jpg', 190000.00, 10.00, 19000.00, 171000.00, 'Pending', '2026-07-25 14:42:59'),
(16, 17, 2, 0.00, '', 170000.00, 10.00, 17000.00, 153000.00, 'Pending', '2026-07-28 12:53:07');

-- --------------------------------------------------------

--
-- Table structure for table `facilities`
--

CREATE TABLE `facilities` (
  `facility_id` int(11) NOT NULL,
  `facility_name` varchar(100) NOT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `facilities`
--

INSERT INTO `facilities` (`facility_id`, `facility_name`, `icon`, `created_at`) VALUES
(1, 'Free Wi-Fi', 'fa-wifi', '2026-07-19 15:04:08'),
(2, 'Swimming Pool', 'fa-swimming-pool', '2026-07-19 15:04:08'),
(3, 'Fitness Center', 'fa-dumbbell', '2026-07-19 15:04:08'),
(4, 'Spa & Wellness', 'fa-spa', '2026-07-19 15:04:08'),
(5, 'Air Conditioning', 'fa-wind', '2026-07-19 15:04:08');

-- --------------------------------------------------------

--
-- Table structure for table `hotels`
--

CREATE TABLE `hotels` (
  `hotel_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `hotel_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `address` text NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `hotel_phone` varchar(30) DEFAULT NULL,
  `hotel_email` varchar(150) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `star_rating` decimal(2,1) DEFAULT 0.0,
  `check_in_time` time DEFAULT '14:00:00',
  `check_out_time` time DEFAULT '12:00:00',
  `status` enum('pending','approved','rejected','inactive') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hotels`
--

INSERT INTO `hotels` (`hotel_id`, `owner_id`, `hotel_name`, `description`, `address`, `city`, `state`, `country`, `postal_code`, `latitude`, `longitude`, `hotel_phone`, `hotel_email`, `website`, `star_rating`, `check_in_time`, `check_out_time`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 'The Grand Yangon Sanctuary', 'Experience unmatched corporate luxury in the heart of Yangon. Features premium workspace views.', 'No. 45, Pyay Road', 'Yangon', 'Yangon Region', 'Myanmar', '11041', 16.82560000, 96.13450000, '+9591234567', 'grand@yangonsanctuary.com', 'www.yangonsanctuary.com', 5.0, '14:00:00', '12:00:00', 'approved', '2026-07-19 15:04:08', '2026-07-19 15:04:08'),
(2, 3, 'Mandalay Palace Vista', 'A beautiful scenic cultural residency overlooking the royal palace walls.', '78th Street', 'Mandalay', 'Mandalay Region', 'Myanmar', '05011', 21.98560000, 96.08910000, '+9597654321', 'vista@mandalaypalace.com', 'www.mandalaypalacevista.com', 4.5, '14:00:00', '12:00:00', 'approved', '2026-07-19 15:04:08', '2026-07-19 15:04:08'),
(3, 2, 'Inle Lake Horizon Resort', 'A dynamic over-water pipeline resort concept awaiting review.', 'Near Inle Lake Trade Zone', 'Taunggyi', 'Shan State', 'Myanmar', '14011', 20.58940000, 96.93120000, '+9595556667', 'horizon@inlelake.com', NULL, 4.0, '14:00:00', '12:00:00', 'approved', '2026-07-19 15:04:08', '2026-07-25 14:38:35');

-- --------------------------------------------------------

--
-- Table structure for table `hotel_facilities`
--

CREATE TABLE `hotel_facilities` (
  `hotel_id` int(11) NOT NULL,
  `facility_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hotel_facilities`
--

INSERT INTO `hotel_facilities` (`hotel_id`, `facility_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 5),
(2, 1),
(2, 4),
(2, 5);

-- --------------------------------------------------------

--
-- Table structure for table `hotel_images`
--

CREATE TABLE `hotel_images` (
  `image_id` int(11) NOT NULL,
  `hotel_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_cover` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 1,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hotel_images`
--

INSERT INTO `hotel_images` (`image_id`, `hotel_id`, `image_path`, `is_cover`, `sort_order`, `uploaded_at`) VALUES
(1, 1, 'grand_yangon_cover.jpg', 1, 1, '2026-07-19 15:04:08'),
(2, 1, 'grand_yangon_lobby.jpg', 0, 2, '2026-07-19 15:04:08'),
(3, 2, 'mandalay_vista_cover.jpg', 1, 1, '2026-07-19 15:04:08');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'general',
  `notification_type` enum('Booking','Payment','Refund','Reminder','System') DEFAULT 'System',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `type`, `notification_type`, `is_read`, `created_at`) VALUES
(1, 4, 'Welcome to HBS V3', 'Your global user registry account has been fully integrated into the terminal.', 'general', 'System', 1, '2026-07-19 15:04:08'),
(2, 2, 'New Booking Received', 'Booking code HBSV3-A98B23 has been locked against your room stock.', 'general', 'Booking', 1, '2026-07-19 15:04:08'),
(3, 4, 'Booking Created', 'Your booking reservation HBSV3-84991A for The Grand Yangon Sanctuary has been created successfully. Please complete the payment.', 'general', 'Booking', 1, '2026-07-20 12:02:44'),
(4, 4, 'Reservation Status Update', 'Your hotel reservation code HBSV3-84991A status has been marked as: CANCELLED', 'general', 'Booking', 1, '2026-07-20 12:06:15'),
(5, 4, 'Reservation Status Update', 'Your hotel reservation code HBSV3-A98B23 status has been marked as: CANCELLED', 'general', 'Booking', 1, '2026-07-20 12:06:27'),
(6, 4, 'Booking Created', 'Your booking reservation HBSV3-519E05 for Mandalay Palace Vista has been created successfully. Please complete the payment.', 'general', 'Booking', 1, '2026-07-20 12:16:08'),
(7, 4, 'Booking Created', 'Your booking reservation HBSV3-11DC60 for The Grand Yangon Sanctuary has been created successfully. Please complete the payment.', 'general', 'Booking', 1, '2026-07-20 12:19:45'),
(8, 4, 'Reservation Status Update', 'Your hotel reservation code HBSV3-11DC60 status has been marked as: CANCELLED', 'general', 'Booking', 1, '2026-07-20 12:21:12'),
(9, 4, 'Reservation Status Update', 'Your hotel reservation code HBSV3-519E05 status has been marked as: CANCELLED', 'general', 'Booking', 1, '2026-07-20 13:05:30'),
(10, 4, 'Reservation Status Update', 'Your hotel reservation code HBSV3-C12F45 status has been marked as: CANCELLED', 'general', 'Booking', 1, '2026-07-20 13:05:36'),
(11, 4, 'Booking Created', 'Your reservation (HBSV3-D69A99) for The Grand Yangon Sanctuary was created successfully. Please finalize payment.', 'general', 'Booking', 1, '2026-07-20 15:00:17'),
(12, 4, 'Booking Created', 'Your reservation (HBSV3-C5AC2A) for The Grand Yangon Sanctuary was created successfully. Please finalize payment.', 'general', 'Booking', 1, '2026-07-20 16:35:07'),
(13, 4, 'Booking Created', 'Your reservation (HBSV3-B80943) for The Grand Yangon Sanctuary was created successfully.', 'general', 'Booking', 1, '2026-07-20 16:38:23'),
(14, 4, 'Booking Created', 'Your reservation (HBSV3-C1E38D) for The Grand Yangon Sanctuary was created successfully. Please finalize payment.', 'general', 'Booking', 1, '2026-07-20 16:41:51'),
(15, 4, 'Booking Created', 'Your reservation (HBSV3-66844B) for The Grand Yangon Sanctuary was created successfully. Please finalize payment.', 'general', 'Booking', 1, '2026-07-20 16:42:17'),
(16, 4, 'Booking Created', 'Your reservation (HBSV3-795857) for The Grand Yangon Sanctuary was created successfully. Please finalize payment.', 'general', 'Booking', 1, '2026-07-21 11:16:42'),
(17, 4, 'Reservation Status Update', 'Your hotel reservation (HBSV3-C1E38D) status has been updated to: CONFIRMED', 'booking', 'System', 1, '2026-07-21 13:50:26'),
(18, 4, 'Reservation Status Update', 'Your hotel reservation (HBSV3-B80943) status has been updated to: CONFIRMED', 'booking', 'System', 1, '2026-07-21 13:50:30'),
(19, 4, 'Reservation Status Update', 'Your hotel reservation (HBSV3-C5AC2A) status has been updated to: CONFIRMED', 'booking', 'System', 1, '2026-07-21 13:50:36'),
(20, 4, 'Reservation Status Update', 'Your hotel reservation (HBSV3-C5AC2A) status has been updated to: CHECKED OUT', 'booking', 'System', 1, '2026-07-21 13:50:39'),
(21, 4, 'Booking Created', 'Your reservation (HBSV3-97BD3B) for The Grand Yangon Sanctuary was created successfully. Please finalize payment.', 'general', 'Booking', 1, '2026-07-21 14:02:53'),
(22, 4, 'Booking Created', 'Your reservation (HBSV3-656CF0) for The Grand Yangon Sanctuary was created successfully. Please finalize payment.', 'general', 'Booking', 1, '2026-07-21 14:08:22'),
(23, 4, 'Booking Created', 'Your reservation (HBSV3-14F3E2) for The Grand Yangon Sanctuary was created successfully. Please finalize payment.', 'general', 'Booking', 1, '2026-07-21 14:15:09'),
(24, 4, 'Payment Submitted', 'Payment request submitted for booking HBSV3-14F3E2', 'general', 'Payment', 1, '2026-07-21 14:35:58'),
(25, 4, 'Booking Created', 'Your reservation (HBSV3-F98D06) for Mandalay Palace Vista was created successfully. Please finalize payment.', 'general', 'Booking', 1, '2026-07-22 06:04:21'),
(26, 4, 'Payment Submitted', 'Payment request submitted for booking HBSV3-F98D06', 'general', 'Payment', 1, '2026-07-22 06:04:29'),
(27, 4, 'Reservation Status Update', 'Your hotel reservation (HBSV3-14F3E2) status has been updated to: CONFIRMED', 'booking', 'System', 1, '2026-07-22 06:05:16'),
(28, 4, 'Reservation Status Update', 'Your hotel reservation (HBSV3-14F3E2) status has been updated to: CHECKED OUT', 'booking', 'System', 1, '2026-07-22 06:05:44'),
(29, 4, 'Reservation Status Update', 'Your hotel reservation (HBSV3-D69A99) status has been updated to: CONFIRMED', 'booking', 'System', 1, '2026-07-22 06:06:00'),
(30, 4, 'Reservation Status Update', 'Your hotel reservation (HBSV3-656CF0) status has been updated to: CONFIRMED', 'booking', 'System', 1, '2026-07-22 06:06:07'),
(31, 4, 'Reservation Status Update', 'Your hotel reservation (HBSV3-97BD3B) status has been updated to: CONFIRMED', 'booking', 'System', 1, '2026-07-22 06:06:09'),
(32, 4, 'Reservation Status Update', 'Your hotel reservation (HBSV3-656CF0) status has been updated to: CHECKED OUT', 'booking', 'System', 1, '2026-07-22 06:06:11'),
(33, 4, 'Reservation Status Update', 'Your hotel reservation (HBSV3-97BD3B) status has been updated to: CHECKED OUT', 'booking', 'System', 1, '2026-07-22 06:06:14'),
(34, 4, 'Reservation Status Update', 'Your hotel reservation (HBSV3-795857) status has been updated to: CHECKED OUT', 'booking', 'System', 1, '2026-07-22 06:06:20'),
(35, 4, 'Reservation Status Update', 'Your hotel reservation (HBSV3-66844B) status has been updated to: CHECKED OUT', 'booking', 'System', 1, '2026-07-22 06:06:22'),
(36, 4, 'Reservation Status Update', 'Your hotel reservation (HBSV3-C1E38D) status has been updated to: CHECKED OUT', 'booking', 'System', 1, '2026-07-22 06:06:24'),
(37, 4, 'Reservation Status Update', 'Your hotel reservation (HBSV3-B80943) status has been updated to: CHECKED OUT', 'booking', 'System', 1, '2026-07-22 06:06:26'),
(38, 4, 'Reservation Status Update', 'Your hotel reservation (HBSV3-D69A99) status has been updated to: CHECKED OUT', 'booking', 'System', 1, '2026-07-22 06:06:35'),
(39, 4, 'Booking Created', 'Your reservation (HBSV3-470E91) for The Grand Yangon Sanctuary was created successfully. Please finalize payment.', 'general', 'Booking', 1, '2026-07-23 05:57:16'),
(40, 4, 'Payment Submitted', 'Payment request submitted for booking HBSV3-470E91', 'general', 'Payment', 1, '2026-07-23 05:57:36'),
(41, 4, 'Reservation Status Update', 'Your hotel reservation (HBSV3-470E91) status has been updated to: CONFIRMED', 'booking', 'System', 1, '2026-07-23 05:59:09'),
(42, 4, 'Reservation Status Update', 'Your hotel reservation (HBSV3-470E91) status has been updated to: CHECKED OUT', 'booking', 'System', 1, '2026-07-23 06:03:43'),
(43, 4, 'Payment Status Updated', 'Your payment for booking HBSV3-470E91 has been updated to Paid', 'general', 'Payment', 0, '2026-07-25 14:38:02'),
(44, 4, 'Payment Status Updated', 'Your payment for booking HBSV3-F98D06 has been updated to Paid', 'general', 'Payment', 0, '2026-07-25 14:38:05'),
(45, 4, 'Payment Status Updated', 'Your payment for booking HBSV3-14F3E2 has been updated to Paid', 'general', 'Payment', 0, '2026-07-25 14:38:07'),
(46, 4, 'Payment Status Updated', 'Your payment for booking HBSV3-C12F45 has been updated to Paid', 'general', 'Payment', 0, '2026-07-25 14:38:15'),
(47, 2, 'Hotel Status Updated', 'Your hotel \'Inle Lake Horizon Resort\' status has been updated to approved.', 'general', 'System', 0, '2026-07-25 14:38:35'),
(48, 4, 'Reservation Status Update', 'Your booking HBSV3-F98D06 at Mandalay Palace Vista is now CONFIRMED', 'booking', 'System', 0, '2026-07-25 14:41:15'),
(49, 4, 'Reservation Status Update', 'Your booking HBSV3-519E05 at Mandalay Palace Vista is now CHECKED OUT', 'booking', 'System', 0, '2026-07-25 14:41:21'),
(50, 4, 'Reservation Status Update', 'Your booking HBSV3-C12F45 at Mandalay Palace Vista is now CHECKED OUT', 'booking', 'System', 0, '2026-07-25 14:41:24'),
(51, 4, 'Reservation Status Update', 'Your booking HBSV3-F98D06 at Mandalay Palace Vista is now CHECKED OUT', 'booking', 'System', 0, '2026-07-25 14:41:27'),
(52, 1, 'Commission Payment Submitted', 'Hotel owner submitted commission payment slip.', 'general', '', 0, '2026-07-25 14:42:59'),
(53, 4, 'Booking Created', 'Your reservation (HBSV3-D1B122) for The Grand Yangon Sanctuary was created successfully. Please finalize payment.', 'general', 'Booking', 0, '2026-07-28 12:53:04'),
(54, 4, 'Payment Submitted', 'Payment request submitted for booking HBSV3-D1B122', 'general', 'Payment', 0, '2026-07-28 12:53:07');

-- --------------------------------------------------------

--
-- Table structure for table `owner_agreements`
--

CREATE TABLE `owner_agreements` (
  `agreement_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `commission_rate` decimal(5,2) DEFAULT 10.00,
  `accepted` tinyint(1) DEFAULT 0,
  `accepted_date` datetime DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `owner_agreements`
--

INSERT INTO `owner_agreements` (`agreement_id`, `owner_id`, `commission_rate`, `accepted`, `accepted_date`, `created_by`, `created_at`) VALUES
(1, 2, 10.00, 1, '2026-07-19 21:34:08', 1, '2026-07-19 15:04:08'),
(2, 3, 12.50, 1, '2026-07-19 21:34:08', 1, '2026-07-19 15:04:08');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `payment_method` enum('KBZPay','WavePay','AYA Pay','CB Pay','Credit Card','Bank Transfer') NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_status` enum('Pending','Paid','Failed','Refunded') DEFAULT 'Pending',
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `booking_id`, `payment_method`, `transaction_id`, `amount`, `payment_status`, `paid_at`, `created_at`) VALUES
(1, 1, 'KBZPay', 'TXN-20260719-88901', 240000.00, 'Paid', '2026-07-19 21:34:08', '2026-07-19 15:04:08'),
(2, 2, 'CB Pay', 'TXN-20260719-99412', 95000.00, 'Paid', '2026-07-25 21:08:15', '2026-07-19 15:04:08'),
(3, 14, 'KBZPay', 'TXN-740FC328', 85000.00, 'Paid', '2026-07-25 21:08:07', '2026-07-21 14:35:58'),
(4, 15, 'KBZPay', 'TXN-62A611D7', 95000.00, 'Paid', '2026-07-25 21:08:05', '2026-07-22 06:04:29'),
(5, 16, 'AYA Pay', 'TXN-4B96EB6A', 85000.00, 'Paid', '2026-07-25 21:08:01', '2026-07-23 05:57:36'),
(6, 17, 'KBZPay', 'TXN-DC90C7FF', 170000.00, 'Pending', NULL, '2026-07-28 12:53:07');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `hotel_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `title` varchar(150) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `review_status` enum('Pending','Approved','Hidden') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`review_id`, `booking_id`, `customer_id`, `hotel_id`, `rating`, `title`, `comment`, `review_status`, `created_at`) VALUES
(1, 1, 4, 1, 5, 'Flawless Corporate Stay', 'Exceptional service and flawless room infrastructure. Highly recommended!', 'Approved', '2026-07-19 15:04:08');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL,
  `hotel_id` int(11) NOT NULL,
  `room_name` varchar(150) NOT NULL,
  `room_type` enum('Single','Double','Twin','Triple','Deluxe','Suite','Family','Executive') NOT NULL,
  `room_description` text DEFAULT NULL,
  `bed_type` enum('Single Bed','Double Bed','Queen Bed','King Bed','Twin Bed','Mixed') NOT NULL,
  `room_size` decimal(6,2) DEFAULT NULL,
  `room_size_unit` enum('sqm','sqft') DEFAULT 'sqm',
  `floor_no` int(11) DEFAULT NULL,
  `total_rooms` int(11) NOT NULL DEFAULT 1,
  `max_adults` int(11) NOT NULL DEFAULT 2,
  `max_children` int(11) NOT NULL DEFAULT 0,
  `base_price` decimal(12,2) NOT NULL,
  `extra_bed_price` decimal(12,2) DEFAULT 0.00,
  `breakfast_included` tinyint(1) DEFAULT 0,
  `free_cancellation` tinyint(1) DEFAULT 1,
  `smoking_allowed` tinyint(1) DEFAULT 0,
  `room_status` enum('available','maintenance','inactive') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`room_id`, `hotel_id`, `room_name`, `room_type`, `room_description`, `bed_type`, `room_size`, `room_size_unit`, `floor_no`, `total_rooms`, `max_adults`, `max_children`, `base_price`, `extra_bed_price`, `breakfast_included`, `free_cancellation`, `smoking_allowed`, `room_status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Executive Corporate Suite', 'Suite', 'High-performance multi-room infrastructure equipped with premium enterprise aesthetics.', 'King Bed', 55.00, 'sqm', 4, 5, 2, 1, 120000.00, 30000.00, 1, 1, 0, 'available', '2026-07-19 15:04:08', '2026-07-19 15:04:08'),
(2, 1, 'Deluxe Urban Twin', 'Deluxe', 'Perfect dynamic configuration for partner business travel arrays.', 'Twin Bed', 40.00, 'sqm', 3, 10, 2, 2, 85000.00, 20000.00, 1, 0, 0, 'available', '2026-07-19 15:04:08', '2026-07-19 15:04:08'),
(3, 2, 'Royal Heritage Double', 'Deluxe', 'Authentic traditional timber alignments combined with modern digital cooling controls.', 'Double Bed', 45.00, 'sqm', 1, 8, 2, 0, 95000.00, 25000.00, 1, 1, 1, 'available', '2026-07-19 15:04:08', '2026-07-19 15:04:08'),
(4, 1, 'Premium Deluxe', 'Deluxe', NULL, '', 50.00, 'sqm', NULL, 5, 2, 0, 150000.00, 0.00, 0, 1, 0, 'available', '2026-07-20 14:43:32', '2026-07-20 14:43:32');

-- --------------------------------------------------------

--
-- Table structure for table `room_availability`
--

CREATE TABLE `room_availability` (
  `availability_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `available_date` date NOT NULL,
  `total_rooms` int(11) NOT NULL,
  `booked_rooms` int(11) DEFAULT 0,
  `blocked_rooms` int(11) DEFAULT 0,
  `available_rooms` int(11) GENERATED ALWAYS AS (`total_rooms` - `booked_rooms` - `blocked_rooms`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room_availability`
--

INSERT INTO `room_availability` (`availability_id`, `room_id`, `available_date`, `total_rooms`, `booked_rooms`, `blocked_rooms`, `created_at`) VALUES
(1, 1, '2026-08-01', 5, 1, 0, '2026-07-19 15:04:08'),
(2, 1, '2026-08-02', 5, 1, 0, '2026-07-19 15:04:08'),
(3, 3, '2026-09-10', 8, 1, 0, '2026-07-19 15:04:08');

-- --------------------------------------------------------

--
-- Table structure for table `room_images`
--

CREATE TABLE `room_images` (
  `room_image_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_cover` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 1,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room_images`
--

INSERT INTO `room_images` (`room_image_id`, `room_id`, `image_path`, `is_cover`, `sort_order`, `uploaded_at`) VALUES
(1, 1, 'suite_livingroom.jpg', 1, 1, '2026-07-19 15:04:08'),
(2, 3, 'heritage_bed.jpg', 1, 1, '2026-07-19 15:04:08');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `profile_image` varchar(255) DEFAULT 'default.png',
  `role` enum('admin','owner','customer') NOT NULL DEFAULT 'customer',
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `address` text NOT NULL,
  `city` varchar(50) NOT NULL,
  `country` varchar(50) NOT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive','blocked') DEFAULT 'active',
  `email_verified` tinyint(1) DEFAULT 0,
  `remember_token` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `full_name`, `first_name`, `last_name`, `email`, `phone`, `password`, `profile_image`, `role`, `gender`, `date_of_birth`, `address`, `city`, `country`, `postal_code`, `status`, `email_verified`, `remember_token`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'superadmin', 'Corporate Administrator', 'Corporate', 'Administrator', 'admin@hbs-v3.com', '+959111222333', 'password123', 'default.png', 'admin', 'Male', '1990-01-01', 'No. 123, Sule Pagoda Road', 'Yangon', 'Myanmar', '11181', 'active', 1, NULL, '2026-07-25 21:06:19', '2026-07-19 15:04:08', '2026-07-25 14:36:19'),
(2, 'hotelowner1', 'U Thura Maung', 'Thura', 'Maung', 'owner1@gmail.com', '+959444555666', 'password123', 'default.png', 'owner', 'Male', '1985-05-12', 'No. 45, Pyay Road, Kamayut Township', 'Yangon', 'Myanmar', '11041', 'active', 1, NULL, '2026-07-28 19:23:40', '2026-07-19 15:04:08', '2026-07-28 12:53:40'),
(3, 'hotelowner2', 'Daw Hla Hla Win', 'Hla Hla', 'Win', 'owner2@gmail.com', '+959777888999', 'password123', 'default.png', 'owner', 'Female', '1988-10-20', '78th Street, Between 30th & 31st', 'Mandalay', 'Myanmar', '05011', 'active', 1, NULL, '2026-07-25 21:11:01', '2026-07-19 15:04:08', '2026-07-25 14:41:01'),
(4, 'customer_guest', 'Mg Kaung Set', 'Kaung', 'Set', 'customer@gmail.com', '+959222333444', 'password123', 'default.png', 'customer', 'Male', '2000-08-15', 'No. 89, Insein Road', 'Yangon', 'Myanmar', '11011', 'active', 1, NULL, '2026-07-25 21:01:52', '2026-07-19 15:04:08', '2026-07-25 14:31:52');

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `wishlist_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `hotel_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wishlist`
--

INSERT INTO `wishlist` (`wishlist_id`, `customer_id`, `hotel_id`, `created_at`) VALUES
(1, 4, 2, '2026-07-19 15:04:08');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_audit_user` (`user_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD UNIQUE KEY `booking_code` (`booking_code`),
  ADD KEY `idx_booking_customer` (`customer_id`),
  ADD KEY `idx_booking_hotel` (`hotel_id`),
  ADD KEY `idx_booking_status` (`booking_status`),
  ADD KEY `idx_booking_dates` (`check_in`,`check_out`);

--
-- Indexes for table `booking_rooms`
--
ALTER TABLE `booking_rooms`
  ADD PRIMARY KEY (`booking_room_id`),
  ADD KEY `idx_booking_rooms_booking` (`booking_id`),
  ADD KEY `idx_booking_rooms_room` (`room_id`);

--
-- Indexes for table `commissions`
--
ALTER TABLE `commissions`
  ADD PRIMARY KEY (`commission_id`),
  ADD KEY `idx_commission_booking` (`booking_id`),
  ADD KEY `fk_commission_owner` (`owner_id`);

--
-- Indexes for table `facilities`
--
ALTER TABLE `facilities`
  ADD PRIMARY KEY (`facility_id`),
  ADD UNIQUE KEY `facility_name` (`facility_name`);

--
-- Indexes for table `hotels`
--
ALTER TABLE `hotels`
  ADD PRIMARY KEY (`hotel_id`),
  ADD KEY `idx_hotels_owner` (`owner_id`),
  ADD KEY `idx_hotels_city` (`city`),
  ADD KEY `idx_hotels_status` (`status`),
  ADD KEY `idx_hotels_star` (`star_rating`);

--
-- Indexes for table `hotel_facilities`
--
ALTER TABLE `hotel_facilities`
  ADD PRIMARY KEY (`hotel_id`,`facility_id`),
  ADD KEY `fk_hotel_facility_facility` (`facility_id`);

--
-- Indexes for table `hotel_images`
--
ALTER TABLE `hotel_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `idx_hotel_images` (`hotel_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_notification_user` (`user_id`);

--
-- Indexes for table `owner_agreements`
--
ALTER TABLE `owner_agreements`
  ADD PRIMARY KEY (`agreement_id`),
  ADD KEY `fk_owner_agreement_owner` (`owner_id`),
  ADD KEY `fk_owner_agreement_admin` (`created_by`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD KEY `idx_payment_booking` (`booking_id`),
  ADD KEY `idx_payment_status` (`payment_status`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `idx_review_hotel` (`hotel_id`),
  ADD KEY `idx_review_customer` (`customer_id`),
  ADD KEY `idx_review_status` (`review_status`),
  ADD KEY `fk_review_booking` (`booking_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD KEY `idx_rooms_hotel` (`hotel_id`),
  ADD KEY `idx_rooms_type` (`room_type`),
  ADD KEY `idx_rooms_status` (`room_status`),
  ADD KEY `idx_rooms_price` (`base_price`);

--
-- Indexes for table `room_availability`
--
ALTER TABLE `room_availability`
  ADD PRIMARY KEY (`availability_id`),
  ADD UNIQUE KEY `room_date` (`room_id`,`available_date`),
  ADD KEY `idx_room_availability_date` (`available_date`);

--
-- Indexes for table `room_images`
--
ALTER TABLE `room_images`
  ADD PRIMARY KEY (`room_image_id`),
  ADD KEY `idx_room_images` (`room_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_status` (`status`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`wishlist_id`),
  ADD UNIQUE KEY `customer_hotel` (`customer_id`,`hotel_id`),
  ADD KEY `fk_wishlist_hotel` (`hotel_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `booking_rooms`
--
ALTER TABLE `booking_rooms`
  MODIFY `booking_room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `commissions`
--
ALTER TABLE `commissions`
  MODIFY `commission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `facilities`
--
ALTER TABLE `facilities`
  MODIFY `facility_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `hotels`
--
ALTER TABLE `hotels`
  MODIFY `hotel_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `hotel_images`
--
ALTER TABLE `hotel_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `owner_agreements`
--
ALTER TABLE `owner_agreements`
  MODIFY `agreement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `room_availability`
--
ALTER TABLE `room_availability`
  MODIFY `availability_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `room_images`
--
ALTER TABLE `room_images`
  MODIFY `room_image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `wishlist_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_booking_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_booking_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`hotel_id`) ON DELETE CASCADE;

--
-- Constraints for table `booking_rooms`
--
ALTER TABLE `booking_rooms`
  ADD CONSTRAINT `fk_booking_rooms_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_booking_rooms_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE;

--
-- Constraints for table `commissions`
--
ALTER TABLE `commissions`
  ADD CONSTRAINT `fk_commission_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_commission_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `hotels`
--
ALTER TABLE `hotels`
  ADD CONSTRAINT `fk_hotels_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `hotel_facilities`
--
ALTER TABLE `hotel_facilities`
  ADD CONSTRAINT `fk_hotel_facility_facility` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`facility_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_hotel_facility_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`hotel_id`) ON DELETE CASCADE;

--
-- Constraints for table `hotel_images`
--
ALTER TABLE `hotel_images`
  ADD CONSTRAINT `fk_hotel_images` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`hotel_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `owner_agreements`
--
ALTER TABLE `owner_agreements`
  ADD CONSTRAINT `fk_owner_agreement_admin` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_owner_agreement_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_review_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_review_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_review_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`hotel_id`) ON DELETE CASCADE;

--
-- Constraints for table `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `fk_rooms_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`hotel_id`) ON DELETE CASCADE;

--
-- Constraints for table `room_availability`
--
ALTER TABLE `room_availability`
  ADD CONSTRAINT `fk_room_availability_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE;

--
-- Constraints for table `room_images`
--
ALTER TABLE `room_images`
  ADD CONSTRAINT `fk_room_images` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE;

--
-- Constraints for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `fk_wishlist_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_wishlist_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`hotel_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
