-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 21, 2026 at 05:24 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sun_office`
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_users`
-- (See below for the actual view)
--
CREATE TABLE `active_users` (
`id` int(11)
,`name` varchar(100)
,`email` varchar(100)
,`role` enum('admin','staff')
,`last_login` timestamp
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `areas`
--

CREATE TABLE `areas` (
  `id` int(11) NOT NULL,
  `district_name` varchar(100) NOT NULL,
  `district_code` varchar(20) NOT NULL,
  `population` int(11) DEFAULT NULL,
  `area_sqkm` decimal(10,2) DEFAULT NULL,
  `headquarters` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `areas`
--

INSERT INTO `areas` (`id`, `district_name`, `district_code`, `population`, `area_sqkm`, `headquarters`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Tirunelveli', 'TVL', 3072880, 3876.00, 'Tirunelveli', 'Rice bowl of Tamil Nadu, known for temples and education', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(2, 'Thoothukudi', 'TUT', 1750176, 4621.00, 'Thoothukudi', 'Major port city, pearl city of India', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(3, 'Kanyakumari', 'KK', 1870374, 1672.00, 'Nagercoil', 'Southernmost district, tourist paradise', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(4, 'Tenkasi', 'TSI', 1405727, 2916.00, 'Tenkasi', 'Newly formed district, scenic beauty', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'service_create', 'Created service order SVC032', '::1', NULL, '2026-02-10 17:29:00'),
(2, 1, 'service_create', 'Created service order SVC033', '::1', NULL, '2026-02-10 17:49:17');

-- --------------------------------------------------------

--
-- Table structure for table `batteries`
--

CREATE TABLE `batteries` (
  `id` int(11) NOT NULL,
  `battery_code` varchar(20) NOT NULL,
  `battery_model` varchar(100) DEFAULT NULL,
  `battery_serial` varchar(100) DEFAULT NULL,
  `brand` varchar(50) DEFAULT NULL,
  `capacity` varchar(20) DEFAULT NULL,
  `voltage` varchar(20) DEFAULT '12V',
  `battery_type` enum('lead_acid','lithium_ion','gel','agm','tubular','other') DEFAULT 'lead_acid',
  `category` enum('inverter','solar','ups','automotive','other') DEFAULT 'inverter',
  `specifications` text DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `warranty_period` varchar(20) DEFAULT NULL,
  `amc_period` varchar(20) DEFAULT '0',
  `price` decimal(10,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `installation_date` date DEFAULT NULL,
  `battery_condition` enum('excellent','good','fair','poor','dead') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `inverter` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `batteries`
--

INSERT INTO `batteries` (`id`, `battery_code`, `battery_model`, `battery_serial`, `brand`, `capacity`, `voltage`, `battery_type`, `category`, `specifications`, `purchase_date`, `warranty_period`, `amc_period`, `price`, `status`, `installation_date`, `battery_condition`, `created_at`, `updated_at`, `inverter`) VALUES
(1, 'BATT001', 'Luminous Red Charge RC 18000', 'LUM00123456', 'Luminous', '150AH', '12V', 'lead_acid', 'inverter', 'Maintenance Free, Deep Cycle, Tall Tubular', '2024-01-15', '3 years', '1 year', 12500.00, 'active', '2026-01-15', 'good', '2024-01-16 17:15:51', '2026-02-14 17:14:34', NULL),
(2, 'BATT002', 'Exide Inva Master IT500', 'EXD00123457', 'Exide', '200AH', '12V', 'tubular', 'inverter', 'Tall Tubular, Deep Cycle, High Efficiency', '2024-02-10', '5 years', '2 years', 18000.00, '', '2026-02-12', 'good', '2024-01-16 17:15:51', '2026-02-14 17:15:34', NULL),
(3, 'BATT003', 'Amaron Invader I-2000', 'AMR00123458', 'Amaron', '120AH', '12V', 'agm', 'inverter', 'AGM Technology, Maintenance Free', '2024-03-05', '4 years', '1 year', 9500.00, 'active', '2026-02-10', 'excellent', '2024-01-16 17:15:51', '2026-02-14 17:16:14', NULL),
(4, 'BATT004', 'Exide Inva Master 150AH', 'EXD150AH2024001', 'Exide', '150AH', '12V', 'lead_acid', 'inverter', 'Tall Tubular, Maintenance Free, Deep Cycle', '2024-01-15', '3 years', '1 year', 18500.00, '', '2024-01-20', 'good', '2024-01-22 14:30:20', '2026-02-01 18:11:25', NULL),
(5, 'BATT005', 'Microtek Inverter Battery SEBZ 150', 'MTK1502024001', 'Microtek', '150AH', '12V', 'tubular', 'inverter', 'Solar Inverter Compatible, Long Life', '2024-04-12', '4 years', '2 years', 16500.00, 'active', '2024-04-18', 'good', '2024-01-22 14:31:12', '2026-02-05 15:08:03', NULL),
(6, 'BATT006', 'Su-Kam Falcon 200AH', 'SKM2002024001', 'Su-Kam', '200AH', '12V', 'lead_acid', 'inverter', 'Pure Lead, High Performance', '2024-05-20', '3 years', '1 year', 21000.00, '', '2024-05-25', 'good', '2024-01-22 14:42:23', '2026-02-04 17:20:12', NULL),
(7, 'BATT007', 'LivGuard Invarcell 135AH', 'LIV1352024001', 'LivGuard', '135AH', '12V', 'agm', 'inverter', 'VRLA Technology, Spill Proof', '2024-06-08', '4 years', '1 year', 14000.00, 'active', '2024-06-15', 'excellent', '2024-01-22 15:45:57', '2026-02-05 16:10:06', NULL),
(8, 'BATT008', 'HBL Power Inverter 180AH', 'HBL1802024001', 'HBL', '180AH', '12V', 'tubular', 'inverter', 'Industrial Grade, Deep Discharge', '2024-07-14', '5 years', '2 years', 19500.00, 'active', '2024-07-20', 'good', '2024-02-10 09:30:00', '2024-02-10 09:30:00', NULL),
(9, 'BATT009', 'Okaya Inverter Battery 200AH', 'OKA2002024001', 'Okaya', '200AH', '12V', 'lead_acid', 'inverter', 'Maintenance Free, Fast Charging', '2024-08-22', '3 years', '1 year', 17500.00, 'active', '2024-08-28', 'excellent', '2024-02-10 10:15:00', '2024-02-10 10:15:00', NULL),
(10, 'BATT010', 'Amaron Quanta 150AH', 'AMR1502024002', 'Amaron', '150AH', '12V', 'gel', 'inverter', 'Gel Technology, Silent Operation', '2024-09-05', '4 years', '2 years', 22000.00, 'active', '2024-09-10', 'good', '2024-02-10 11:00:00', '2024-02-10 11:00:00', NULL),
(11, 'BATT011', 'Luminous Solar Charge 200AH', 'LUM200SOL001', 'Luminous', '200AH', '12V', 'tubular', 'solar', 'Solar Compatible, Deep Cycle, High Efficiency', '2024-03-10', '5 years', '2 years', 24000.00, 'active', '2024-03-15', 'excellent', '2024-02-10 12:00:00', '2024-02-10 12:00:00', NULL),
(12, 'BATT012', 'Exide Solar Tubular 150AH', 'EXD150SOL001', 'Exide', '150AH', '12V', 'tubular', 'solar', 'Solar Grade, Long Cycle Life', '2024-04-18', '5 years', '2 years', 19000.00, '', '2024-04-25', 'good', '2024-02-10 13:30:00', '2024-02-10 13:30:00', NULL),
(13, 'BATT013', 'Microtek Solar Gold 120AH', 'MTK120SOL001', 'Microtek', '120AH', '12V', 'lead_acid', 'solar', 'Solar Inverter Optimized', '2024-05-25', '4 years', '1 year', 16000.00, 'active', '2024-06-01', 'good', '2024-02-10 14:45:00', '2024-02-10 14:45:00', NULL),
(14, 'BATT014', 'Su-Kam Solar Pro 200AH', 'SKM200SOL001', 'Su-Kam', '200AH', '24V', 'tubular', 'solar', '24V System, High Capacity', '2024-06-30', '5 years', '3 years', 28000.00, 'active', '2024-07-05', 'excellent', '2024-02-10 15:20:00', '2024-02-10 15:20:00', NULL),
(15, 'BATT015', 'LivGuard Solar Max 150AH', 'LIV150SOL001', 'LivGuard', '150AH', '12V', 'agm', 'solar', 'AGM Solar Battery, Maintenance Free', '2024-08-05', '4 years', '2 years', 21000.00, 'active', '2024-08-10', 'good', '2024-02-10 16:10:00', '2024-02-10 16:10:00', NULL),
(16, 'BATT016', 'APC Smart-UPS 100AH', 'APC100UPS001', 'APC', '100AH', '12V', 'agm', 'ups', 'UPS Compatible, Online Double Conversion', '2024-02-15', '3 years', '1 year', 18000.00, 'active', '2024-02-20', 'excellent', '2024-02-10 17:00:00', '2024-02-10 17:00:00', NULL),
(17, 'BATT017', 'CyberPower 150AH UPS', 'CPW150UPS001', 'CyberPower', '150AH', '12V', 'lead_acid', 'ups', 'Line Interactive, AVR', '2024-03-22', '2 years', '1 year', 12500.00, '', '2024-03-28', 'good', '2024-02-10 18:30:00', '2024-02-10 18:30:00', NULL),
(18, 'BATT018', 'Microtek UPS 65AH', 'MTK65UPS001', 'Microtek', '65AH', '12V', 'agm', 'ups', 'Desktop UPS Compatible', '2024-04-10', '2 years', '0', 8500.00, 'active', '2024-04-15', 'good', '2024-02-10 19:15:00', '2024-02-10 19:15:00', NULL),
(19, 'BATT019', 'Luminous UPS 120AH', 'LUM120UPS001', 'Luminous', '120AH', '12V', 'lead_acid', 'ups', 'Home UPS System', '2024-05-05', '3 years', '1 year', 11000.00, 'active', '2024-05-10', 'excellent', '2024-02-10 20:00:00', '2024-02-10 20:00:00', NULL),
(20, 'BATT020', 'Exide Car Premium 65AH', 'EXD65AUTO001', 'Exide', '65AH', '12V', 'agm', 'automotive', 'Automotive, Maintenance Free', '2024-01-10', '2 years', '0', 8500.00, 'active', '2024-01-15', 'excellent', '2024-02-10 21:00:00', '2024-02-10 21:00:00', NULL),
(21, 'BATT021', 'Amaron Go 45AH', 'AMR45AUTO001', 'Amaron', '45AH', '12V', 'lead_acid', 'automotive', 'Car Battery, High CCA', '2024-02-18', '3 years', '0', 7500.00, 'active', '2024-02-25', 'good', '2024-02-10 22:00:00', '2024-02-10 22:00:00', NULL),
(22, 'BATT022', 'LivGuard Car 60AH', 'LIV60AUTO001', 'LivGuard', '60AH', '12V', 'agm', 'automotive', 'Automotive AGM, Spill Proof', '2024-03-12', '2 years', '0', 9200.00, '', '2024-03-18', 'good', '2024-02-10 23:00:00', '2024-02-10 23:00:00', NULL),
(23, 'BATT023', 'Luminous Red Charge RC 18000 Spare', 'LUM180SP001', 'Luminous', '150AH', '12V', 'lead_acid', 'inverter', 'Spare Battery, Maintenance Free', '2024-04-20', '3 years', '1 year', 12500.00, 'active', NULL, 'excellent', '2024-02-11 09:00:00', '2024-02-11 09:00:00', NULL),
(24, 'BATT024', 'Exide Inva Master 200AH Spare', 'EXD200SP001', 'Exide', '200AH', '12V', 'tubular', 'inverter', 'Spare Battery, Tall Tubular', '2024-05-15', '5 years', '2 years', 18500.00, 'active', NULL, 'excellent', '2024-02-11 10:00:00', '2024-02-11 10:00:00', NULL),
(25, 'BATT025', 'Amaron Invader 120AH Spare', 'AMR120SP001', 'Amaron', '120AH', '12V', 'agm', 'inverter', 'Spare Battery, AGM Technology', '2024-06-10', '4 years', '1 year', 9800.00, 'active', NULL, 'excellent', '2024-02-11 11:00:00', '2024-02-11 11:00:00', NULL),
(26, 'BATT026', 'Microtek Solar 150AH Spare', 'MTK150SP001', 'Microtek', '150AH', '12V', 'tubular', 'solar', 'Spare Solar Battery', '2024-07-05', '4 years', '2 years', 17000.00, 'active', NULL, 'excellent', '2024-02-11 12:00:00', '2024-02-11 12:00:00', NULL),
(27, 'BATT027', 'Su-Kam UPS 100AH Spare', 'SKM100SP001', 'Su-Kam', '100AH', '12V', 'agm', 'ups', 'Spare UPS Battery', '2024-08-20', '3 years', '1 year', 13500.00, 'active', NULL, 'excellent', '2024-02-11 13:00:00', '2024-02-11 13:00:00', NULL),
(28, 'BATT028', 'Luminous Li-On 100AH', 'LUM100LI001', 'Luminous', '100AH', '12V', 'lithium_ion', 'inverter', 'Lithium Ion, Light Weight, Long Life', '2024-06-15', '10 years', '5 years', 45000.00, 'active', '2024-06-20', 'excellent', '2024-02-11 14:00:00', '2024-02-11 14:00:00', NULL),
(29, 'BATT029', 'Exide Lithium 150AH', 'EXD150LI001', 'Exide', '150AH', '24V', 'lithium_ion', 'solar', 'Solar Lithium, High Efficiency', '2024-07-22', '10 years', '5 years', 68000.00, 'active', '2024-07-28', 'excellent', '2024-02-11 15:00:00', '2024-02-11 15:00:00', NULL),
(30, 'BATT030', 'Su-Kam Li-Pro 200AH', 'SKM200LI001', 'Su-Kam', '200AH', '48V', 'lithium_ion', 'solar', '48V System, High Capacity Lithium', '2024-08-30', '12 years', '5 years', 95000.00, 'active', '2024-09-05', 'excellent', '2024-02-11 16:00:00', '2024-02-11 16:00:00', NULL),
(31, 'BATT031', 'Luminous Old Model 135AH', 'LUM135OLD001', 'Luminous', '135AH', '12V', 'lead_acid', 'inverter', 'Old Model, Being Phased Out', '2023-11-10', '2 years', '0', 11000.00, '', NULL, 'fair', '2024-02-11 17:00:00', '2024-02-11 17:00:00', NULL),
(32, 'BATT032', 'Exide Classic 100AH', 'EXD100OLD001', 'Exide', '100AH', '12V', 'lead_acid', 'inverter', 'Classic Model, Not in Production', '2023-09-05', '3 years', '0', 9500.00, '', NULL, 'fair', '2024-02-11 18:00:00', '2024-02-11 18:00:00', NULL),
(33, 'BATT033', 'Amaron Classic 150AH', 'AMR150REP001', 'Amaron', '150AH', '12V', 'lead_acid', 'inverter', 'Replaced Due to Age', '2021-08-15', '3 years', '0', 14000.00, '', '2021-08-20', 'poor', '2024-02-11 19:00:00', '2024-02-11 19:00:00', NULL),
(34, 'BATT034', 'Microtek Old Model 120AH', 'MTK120REP001', 'Microtek', '120AH', '12V', 'lead_acid', 'solar', 'Replaced Under Warranty', '2022-03-10', '4 years', '1 year', 13500.00, '', '2022-03-15', 'dead', '2024-02-11 20:00:00', '2024-02-11 20:00:00', NULL),
(35, 'BATT035', 'HBL Solar Tubular 200AH', 'HBL200SOL001', 'HBL', '200AH', '24V', 'tubular', 'solar', '24V Solar System, Industrial Grade', '2024-09-12', '5 years', '2 years', 32000.00, 'active', '2024-09-18', 'good', '2024-02-12 09:00:00', '2024-02-12 09:00:00', NULL),
(36, 'BATT036', 'Okaya Solar Gel 150AH', 'OKA150SOL001', 'Okaya', '150AH', '12V', 'gel', 'solar', 'Gel Solar Battery, Maintenance Free', '2024-10-05', '4 years', '2 years', 25000.00, 'active', '2024-10-10', 'excellent', '2024-02-12 10:00:00', '2024-02-12 10:00:00', NULL),
(37, 'BATT037', 'LivGuard Inverter 180AH', 'LIV180INV001', 'LivGuard', '180AH', '12V', 'agm', 'inverter', 'AGM Technology, High Performance', '2024-11-08', '4 years', '1 year', 19500.00, 'active', '2024-11-15', 'good', '2024-02-12 11:00:00', '2024-02-12 11:00:00', NULL),
(38, 'BATT038', 'Su-Kam Inverter 250AH', 'SKM250INV001', 'Su-Kam', '250AH', '12V', 'tubular', 'inverter', 'High Capacity, Industrial Use', '2024-12-10', '5 years', '3 years', 28000.00, 'active', '2024-12-15', 'excellent', '2024-02-12 12:00:00', '2024-02-12 12:00:00', NULL),
(39, 'BATT039', 'Exide UPS 75AH', 'EXD75UPS001', 'Exide', '75AH', '12V', 'agm', 'ups', 'UPS System, Online Compatible', '2025-01-05', '3 years', '1 year', 11500.00, 'active', '2025-01-10', 'good', '2024-02-12 13:00:00', '2024-02-12 13:00:00', NULL),
(40, 'BATT040', 'Amaron Car 55AH', 'AMR55AUTO001', 'Amaron', '55AH', '12V', 'agm', 'automotive', 'Automotive AGM, High CCA', '2025-01-20', '3 years', '0', 8800.00, 'active', '2025-01-25', 'excellent', '2024-02-12 14:00:00', '2024-02-12 14:00:00', NULL),
(41, 'BATT041', 'Microtek Inverter 135AH', 'MTK135INV001', 'Microtek', '135AH', '12V', 'lead_acid', 'inverter', 'Home Inverter Compatible', '2025-02-08', '3 years', '1 year', 14500.00, 'active', '2025-02-15', 'good', '2024-02-12 15:00:00', '2024-02-12 15:00:00', NULL),
(42, 'BATT042', 'Luminous Solar 180AH', 'LUM180SOL001', 'Luminous', '180AH', '24V', 'tubular', 'solar', '24V Solar System, High Efficiency', '2025-02-18', '5 years', '2 years', 29500.00, 'active', '2025-02-25', 'excellent', '2024-02-12 16:00:00', '2024-02-12 16:00:00', NULL),
(45, 'BATT045', 'Su-Kam Solar Max 250AH', 'SKM250SOL001', 'Su-Kam', '250AH', '48V', 'tubular', 'solar', '48V Off-grid System', '2025-04-01', '5 years', '3 years', 45000.00, 'active', '2025-04-05', 'excellent', '2024-02-12 19:00:00', '2024-02-12 19:00:00', NULL),
(46, 'BATT046', 'LivGuard UPS 90AH', 'LIV90UPS001', 'LivGuard', '90AH', '12V', 'agm', 'ups', 'UPS System, Line Interactive', '2025-04-10', '3 years', '1 year', 12500.00, 'active', '2025-04-15', 'good', '2024-02-12 20:00:00', '2024-02-12 20:00:00', NULL),
(48, 'BATT048', 'Luminous Lithium 120AH', 'LUM120LI001', 'Luminous', '120AH', '12V', 'lithium_ion', 'inverter', 'Lithium Ion, Compact Design', '2025-05-05', '10 years', '5 years', 52000.00, 'active', '2025-05-10', 'excellent', '2024-02-12 22:00:00', '2024-02-12 22:00:00', NULL),
(49, 'BATT049', 'Exide Solar Gel 100AH', 'EXD100SOL001', 'Exide', '100AH', '12V', 'gel', 'solar', 'Gel Solar Battery, Deep Cycle', '2025-05-15', '4 years', '2 years', 21000.00, 'active', '2025-05-20', 'excellent', '2024-02-12 23:00:00', '2024-02-12 23:00:00', NULL),
(50, 'BATT050', 'Amaron Invader Pro 180AH', 'AMR180INV001', 'Amaron', '180AH', '12V', 'agm', 'inverter', 'Professional Grade, AGM Technology', '2025-06-01', '4 years', '2 years', 24500.00, 'active', '2025-06-05', 'excellent', '2024-02-13 00:00:00', '2024-02-13 00:00:00', NULL),
(51, 'BATT051', 'Exide Inva Master 150AH', 'AMR001234585525734554645', 'Amaron', '120AH', '12V', 'lead_acid', 'inverter', 'fgfdfj', '2026-02-11', '1 year', '1 year', 8552.00, 'active', '2026-02-12', 'good', '2026-02-11 15:51:01', '2026-02-11 15:51:01', 'xxxyyyzzz'),
(52, 'BATT052', '509TOUH23590', 'AMR00123458552573455415841', 'Amaron', '150AH', '12V', 'lead_acid', 'inverter', 'svsdbv', '2026-02-11', '1 year', '1 year', 54541.00, 'active', '2026-02-11', 'good', '2026-02-11 16:20:17', '2026-02-11 16:20:54', ''),
(53, 'BATT053', 'luminous 150ah tall 60m', 'kcpdh2t1024970', 'luminous', '150AH', '12V', 'lead_acid', 'inverter', '', '2026-02-13', '5 years', '3 years', 15000.00, 'active', '2026-02-13', 'excellent', '2026-02-14 11:17:10', '2026-02-18 15:32:15', ''),
(55, 'BATT054', 'Exide Inva Master 150AH', 'AMR001234585525734544151', 'Amaron', '150AH', '12V', 'lead_acid', 'inverter', 'sasavava', '2026-02-19', '1 year', '0', 0.00, 'active', '2026-02-19', 'good', '2026-02-18 19:04:41', '2026-02-18 19:04:41', ''),
(57, 'BATT055', 'Exide Inva Master 150AH', 'AMR001', 'Exide', '150AH', '24V', 'lithium_ion', 'inverter', 'reerherwheh', '2026-02-24', '2 years', '1 year', 10000.00, 'active', '2026-02-25', 'excellent', '2026-02-24 16:44:03', '2026-02-24 16:44:03', ''),
(58, 'BATT056', 'Amaron Invader I-2000', 'AMR001234585523549565', 'Amaron', '150AH', '24V', 'lead_acid', 'inverter', 'Best price with offer', '2026-02-25', '3 years', '1 year', 15050.00, 'active', '2026-02-26', 'excellent', '2026-02-25 16:12:09', '2026-02-25 16:14:13', ''),
(59, 'BATT057', 'Exide Inva Master 150AH', 'AMR00123457856', 'Exide', '150AH', '24V', 'lead_acid', 'inverter', 'rhgreheherth', '2026-03-02', '5 years', '3 years', 5000.00, 'active', '2026-03-03', 'excellent', '2026-03-02 16:24:26', '2026-03-02 16:24:26', ''),
(60, 'BATT058', '', '', '', '150AH', '12V', 'lead_acid', 'inverter', '', NULL, '1 year', '0', 500.00, 'active', NULL, 'good', '2026-03-07 16:07:31', '2026-03-07 16:07:31', '');

--
-- Triggers `batteries`
--
DELIMITER $$
CREATE TRIGGER `before_battery_insert` BEFORE INSERT ON `batteries` FOR EACH ROW BEGIN
    DECLARE next_num INT;
    IF NEW.battery_code IS NULL OR NEW.battery_code = '' THEN
        SELECT COALESCE(MAX(CAST(SUBSTRING(battery_code, 5) AS UNSIGNED)), 0) + 1 
        INTO next_num 
        FROM `batteries`;
        SET NEW.battery_code = CONCAT('BATT', LPAD(next_num, 3, '0'));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `customer_code` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `alternate_phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `customer_code`, `full_name`, `email`, `phone`, `alternate_phone`, `address`, `city`, `state`, `zip_code`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'CUST001', 'John Doe', 'john@example.com', '9876543210', NULL, '123 Main Street', 'Mumbai', 'Maharashtra', '400001', NULL, '2026-01-16 17:15:51', '2026-01-16 17:15:51'),
(2, 'CUST002', 'Jane Smith', 'jane@example.com', '9876543211', NULL, '456 Park Avenue', 'Delhi', 'Delhi', '110001', NULL, '2026-01-16 17:15:51', '2026-01-16 17:15:51'),
(3, 'CUST003', 'Robert Johnson', 'robert@example.com', '9876543212', NULL, '789 MG Road', 'Bangalore', 'Karnataka', '560001', NULL, '2026-01-16 17:15:51', '2026-01-16 17:15:51'),
(4, 'CUST004', 'Rajesh Kumar', 'rajesh@example.com', '4585961235', NULL, '456 Park Avenue', 'Delhi', 'Delhi', '110001', 'New customer', '2026-01-22 14:37:06', '2026-02-17 15:03:44'),
(5, 'CUST005', 'athi', 'jeevan2k3linux@gmail.com', '08220647708', NULL, '137/4 MAIN ROAD MANUR', 'Tirunelveli', 'Tamil Nadu', '627201', 'ddsdsdssd', '2026-01-22 15:45:03', '2026-01-22 15:45:03'),
(6, 'CUST006', 'Diviya', 'jeevan2k3linux@gmail.com', '8220647708', '7598958319', '137/4 MAIN ROAD MANUR', 'manur', 'Tamil Nadu', '627201', 'fddfd', '2026-02-08 08:29:27', '2026-03-09 18:36:15'),
(8, 'CUST007', 'anto', 'anto@sun.com', '1234567890', NULL, '137/4 MAIN ROAD MANUR', 'Tirunelveli', 'Tamil Nadu', '627201', NULL, '2026-02-14 11:58:52', '2026-02-14 11:58:52'),
(9, 'CUST008', 'allapitchai.ks(pathamadai)', NULL, '9442525351', NULL, NULL, 'pathamadai,tirunelveli', 'tamilnadu', '627003', NULL, '2026-02-14 12:15:31', '2026-02-14 12:15:31'),
(11, 'CUST009', 'Abi', 'jeevan2k3linux@gmail.com', '7858961436', NULL, '137/4 MAIN ROAD MANUR', 'Tirunelveli', 'Tamil Nadu', '627201', 'saag', '2026-02-17 19:30:59', '2026-02-17 19:30:59'),
(12, 'CUST010', 'anitha', 'jeevan2k3linux@gmail.com', '9876543218', NULL, '137/4 MAIN ROAD MANUR', 'palayamkottai', 'Tamil Nadu', '627201', NULL, '2026-02-18 19:04:07', '2026-02-18 19:04:07'),
(13, 'CUST011', 'kavitha', 'jeevan2k3linux@gmail.com', '7896142356', NULL, '137/4 MAIN ROAD MANUR', 'Tirunelveli', 'Tamil Nadu', '627201', 'gwehgwwg', '2026-02-19 18:42:37', '2026-02-19 18:42:37'),
(15, 'CUST012', 'Rajesh Kumar', 'rajesh.kumar@example.com', '8844844051', NULL, '45 Gandhi Nagar', 'Chennai', 'Tamil Nadu', '600001', 'Preferred customer, needs battery replacement', '2026-02-24 17:31:06', '2026-02-24 17:31:06'),
(16, 'CUST013', 'suthava', 'suthava@sun.com', '9344682013', NULL, '137/4 MAIN ROAD Tenkasi', 'Tenkasi', 'Tamil Nadu', '627201', 'afsafsaaasaas', '2026-02-24 18:03:14', '2026-02-24 18:03:14'),
(17, 'CUST014', 'Diviyasafa', 'jeevan2k3linux@gmail.com', '8250647708', NULL, '137/4 MAIN ROAD Tenkasi', 'Tenkasi', 'Tamil Nadu', '627201', 'safas', '2026-02-24 18:03:44', '2026-02-24 18:07:38'),
(18, 'CUST015', 'mani', 'mani@sun.com', '8220646708', NULL, '137/4 MAIN ROAD nagercoil', 'nagercoil', 'Tamil Nadu', '627201', 'ddsds', '2026-02-24 18:10:17', '2026-02-24 18:10:17'),
(19, 'CUST016', 'sharmi', 'jeevan2k3lx@gmail.com', '8220627708', NULL, '137/4 MAIN ROAD MANUR', 'Tirunelveli', 'Tamil Nadu', '627201', 'sasfsaf', '2026-02-24 18:25:07', '2026-02-24 18:25:07'),
(20, 'CUST017', 'appi', 'jeevan2k3linux@gmail.com', '0822047708', NULL, '137/4 MAIN ROAD MANUR', 'Tirunelveli', 'Tamil Nadu', '627201', NULL, '2026-02-24 18:32:45', '2026-02-24 18:32:45'),
(21, 'CUST018', 'Diviyassdsds', 'jeevan23linux@gmail.com', '0822147708', NULL, '137/4 MAIN ROAD MANUR', 'Tirunelveli', 'Tamil Nadu', '627201', NULL, '2026-02-24 18:51:27', '2026-02-24 18:51:27'),
(22, 'CUST019', 'vignash', 'jeevan2k3linux@gmail.com', '0822064770', NULL, '137/4 MAIN ROAD MANUR', 'Tirunelveli', 'Tamil Nadu', '627201', NULL, '2026-02-24 18:56:56', '2026-02-24 18:56:56'),
(23, 'CUST020', 'kuttyma', 'jeevan2k3linux@gmail.com', '0820647708', NULL, '137/4 MAIN ROAD MANUR', 'Tirunelveli', 'Tamil Nadu', '627201', 'ssafsa', '2026-02-24 19:05:30', '2026-02-24 19:05:30'),
(24, 'CUST021', 'appikutty', 'jeevan2k3sslinux@gmail.com', '0820647706', NULL, '137/4 MAIN ROAD MANUR\nNEAR SBI BANK MANUR', 'TIRUNELVLEI', 'TAMIL NADU', '627201', NULL, '2026-02-24 19:18:21', '2026-02-24 19:18:21'),
(25, 'CUST022', 'antokutty', 'jeefgvan2k3linux@gmail.com', '0822047082', NULL, '137/4 MAIN ROAD MANUR\nNEAR SBI BANK MANUR', 'TIRUNELVLEI', 'TAMIL NADU', '627201', NULL, '2026-02-24 19:19:27', '2026-02-24 19:19:27'),
(26, 'CUST023', 'thami', 'thama@sun.com', '7412589632', NULL, '137/4 MAIN ROAD MANUR\nNEAR SBI BANK MANUR', 'Chennai', 'TAMIL NADU', '627201', 'saaasg', '2026-02-25 05:29:14', '2026-02-25 05:29:14'),
(27, 'CUST024', 'athikutty', 'jeevan2k3linux@gmail.com', '7894561235', NULL, '137/4 MAIN ROAD MANUR', 'Tirunelveli', 'Tamil Nadu', '627201', 'safafa', '2026-02-25 06:18:53', '2026-02-25 06:18:53'),
(28, 'CUST025', 'Dhanish', 'dhanish@gmail.com', '6383158509', NULL, '137/4 MAIN ROAD Kanniyakumari\n\nNEAR SBI BANK Kanniyakumari', 'Kanniyakumari', 'TAMIL NADU', '627201', 'A to Z studio', '2026-02-25 16:09:50', '2026-02-25 16:13:32'),
(29, 'CUST026', 'athijeeva', 'jeevan2k3linux@gmail.com', '8220687708', NULL, '137/4 MAIN ROAD MANUR\nNEAR SBI BANK MANUR', 'TIRUNELVLEI', 'TAMIL NADU', '627201', 'asa', '2026-02-26 19:33:00', '2026-02-26 19:33:00'),
(30, 'CUST027', 'saro', 'saro@gmail.com', '1478523690', NULL, '137/4 MAIN ROAD Tiruchi', 'tiruchi', 'Tamil Nadu', '627201', 'sagdvsgbdsh', '2026-03-02 16:23:21', '2026-03-02 16:23:21'),
(31, 'CUST028', 'Rathi', 'jeevan2k3linux@gmail.com', '8220647701', '9629212739', '137/4 MAIN ROAD MANUR', 'Tirunelveli', 'Tamil Nadu', '627201', 'sfsaasas', '2026-03-09 18:39:01', '2026-03-09 18:57:12'),
(32, 'CUST029', 'Diviyassdf', 'jeevan2k3linux@gmail.com', '8220647705', '9629212739', '137/4 MAIN ROAD MANUR', 'Tirunelveli', 'Tamil Nadu', '627201', 'ewqrfegfwegewg', '2026-03-21 10:38:18', '2026-03-21 10:38:18');

--
-- Triggers `customers`
--
DELIMITER $$
CREATE TRIGGER `before_customer_insert` BEFORE INSERT ON `customers` FOR EACH ROW BEGIN
    DECLARE next_num INT;
    IF NEW.customer_code IS NULL OR NEW.customer_code = '' THEN
        SELECT COALESCE(MAX(CAST(SUBSTRING(customer_code, 5) AS UNSIGNED)), 0) + 1 
        INTO next_num 
        FROM `customers`;
        SET NEW.customer_code = CONCAT('CUST', LPAD(next_num, 3, '0'));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `service_type` enum('water','inverter') NOT NULL DEFAULT 'water' COMMENT 'water: Water Services, inverter: Inverter Services, both: Both Services',
  `staff_name` varchar(100) NOT NULL,
  `expense_type` enum('petrol','others') NOT NULL DEFAULT 'others',
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `expense_date` date NOT NULL,
  `payment_method` enum('cash','card','online') DEFAULT 'cash',
  `receipt_number` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `staff_id`, `service_type`, `staff_name`, `expense_type`, `amount`, `description`, `expense_date`, `payment_method`, `receipt_number`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(8, 3, 'water', 'Kalai', 'petrol', 200.00, 'torinelvlei to manur', '2026-02-14', 'cash', NULL, '', 1, '2026-02-14 12:22:07', '2026-02-16 16:07:43'),
(9, 1, 'inverter', 'Admin User', 'petrol', 300.00, 'office', '2026-02-16', 'cash', NULL, '', 1, '2026-02-16 16:09:50', '2026-02-16 16:10:14'),
(10, 2, 'inverter', 'Staff User', 'petrol', 500.00, 'office', '2026-02-16', 'cash', NULL, '', 1, '2026-02-16 16:10:02', '2026-02-16 16:16:58'),
(11, 2, 'water', 'Staff User', 'others', 500.00, 'trdfhdfrh', '2026-02-16', 'cash', NULL, '', 1, '2026-02-16 16:52:45', '2026-02-16 16:53:03'),
(12, NULL, 'water', 'Mari', 'petrol', 300.00, 'bike', '2026-02-19', 'cash', NULL, '', 1, '2026-02-19 19:05:30', '2026-02-19 19:05:30'),
(13, NULL, 'water', 'athi', 'petrol', 200.00, 'reher', '2026-02-22', 'cash', NULL, '', 1, '2026-02-22 07:00:47', '2026-02-22 07:00:47'),
(14, 9, 'water', 'Mariappan', 'petrol', 50.00, 'go to new bus stop', '2026-02-25', 'cash', '8669841841', 'delivery', 1, '2026-02-25 16:19:12', '2026-02-25 16:19:12'),
(15, 3, 'water', 'Kalai', 'petrol', 100.00, 'tvn', '2026-03-02', 'cash', '8778498489', 'sdsgd', 1, '2026-03-02 16:38:30', '2026-03-02 16:38:30');

--
-- Triggers `expenses`
--
DELIMITER $$
CREATE TRIGGER `before_expense_insert` BEFORE INSERT ON `expenses` FOR EACH ROW BEGIN
    DECLARE staff_user_name VARCHAR(100);
    
    -- If staff_id is provided, get the staff_name from users table
    IF NEW.staff_id IS NOT NULL AND (NEW.staff_name IS NULL OR NEW.staff_name = '') THEN
        SELECT name INTO staff_user_name FROM users WHERE id = NEW.staff_id;
        SET NEW.staff_name = staff_user_name;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expense_categories`
--

INSERT INTO `expense_categories` (`id`, `category_name`, `description`, `is_active`, `created_at`) VALUES
(1, 'petrol', 'Fuel expenses for vehicles', 1, '2026-02-13 17:02:06'),
(2, 'maintenance', 'Vehicle and equipment maintenance', 1, '2026-02-13 17:02:06'),
(3, 'tools', 'Purchase of tools and equipment', 1, '2026-02-13 17:02:06'),
(4, 'office_supplies', 'Stationery and office supplies', 1, '2026-02-13 17:02:06'),
(5, 'travel', 'Travel allowances and expenses', 1, '2026-02-13 17:02:06'),
(6, 'food', 'Food and refreshments', 1, '2026-02-13 17:02:06'),
(7, 'others', 'Other miscellaneous expenses', 1, '2026-02-13 17:02:06');

-- --------------------------------------------------------

--
-- Stand-in structure for view `expense_summary`
-- (See below for the actual view)
--
CREATE TABLE `expense_summary` (
`month` varchar(7)
,`expense_type` enum('petrol','others')
,`total_expenses` bigint(21)
,`total_amount` decimal(32,2)
,`average_amount` decimal(14,6)
,`min_amount` decimal(10,2)
,`max_amount` decimal(10,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `inverters`
--

CREATE TABLE `inverters` (
  `id` int(11) NOT NULL,
  `inverter_code` varchar(20) NOT NULL,
  `inverter_model` varchar(255) DEFAULT NULL,
  `inverter_serial` varchar(100) DEFAULT NULL,
  `inverter_brand` varchar(255) DEFAULT NULL,
  `power_rating` varchar(50) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `wave_type` enum('pure_sine','modified_sine','square_wave') DEFAULT 'modified_sine',
  `input_voltage` varchar(20) DEFAULT NULL,
  `output_voltage` varchar(20) DEFAULT '230V',
  `efficiency` varchar(10) DEFAULT NULL,
  `battery_voltage` varchar(20) DEFAULT '12V',
  `specifications` text DEFAULT NULL,
  `warranty_period` varchar(20) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `status` varchar(50) DEFAULT 'active',
  `purchase_date` date DEFAULT NULL,
  `installation_date` date DEFAULT NULL,
  `inverter_condition` enum('excellent','good','fair','poor','dead') DEFAULT 'good',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inverters`
--

INSERT INTO `inverters` (`id`, `inverter_code`, `inverter_model`, `inverter_serial`, `inverter_brand`, `power_rating`, `type`, `wave_type`, `input_voltage`, `output_voltage`, `efficiency`, `battery_voltage`, `specifications`, `warranty_period`, `price`, `status`, `purchase_date`, `installation_date`, `inverter_condition`, `created_at`, `updated_at`) VALUES
(1, 'INV001', 'Luminous Zelio 1100', NULL, 'Luminous', '1100VA', 'inverter', 'pure_sine', NULL, '230V', NULL, '12V', 'Digital inverter with PWM technology', '2 years', 6500.00, 'active', '2024-01-15', '2024-01-20', 'good', '2026-02-14 15:57:59', '2026-02-14 15:57:59'),
(3, 'INV003', 'Exide Inverter 1500VA', NULL, 'Exide', '1500VA', 'inverter', 'pure_sine', NULL, '230V', NULL, '12V', 'Heavy duty inverter for home', '3 years', 8500.00, 'active', '2024-03-05', '2024-03-10', 'good', '2026-02-14 15:57:59', '2026-02-14 15:57:59'),
(4, 'INV004', 'Luminous Eco Volt+ 1050', NULL, '65458148941', '1100', 'inverter', 'pure_sine', '230V', '230V', '90%', '12V', 'sswwf', '4 years', 14500.00, 'active', '2026-02-14', '2026-02-15', 'excellent', '2026-02-14 16:32:09', '2026-02-14 16:32:09'),
(10, 'INV005', 'Luminous Eco Volt+ 10500', NULL, '6545814894154151', '1100', 'inverter', 'modified_sine', '230V', '230V', '90%', '12V', 'jb', '1 year', 1000.00, 'active', '2026-02-17', '2026-02-17', 'good', '2026-02-17 16:54:17', '2026-02-17 16:54:17'),
(20, 'INV2026020001', 'Luminous Eco Volt+ 1050078', NULL, '654581489415415154151150', '1100', 'inverter', 'modified_sine', '230V', '230V', '', '12V', 'bfdfhbf', '1 year', 12000.00, 'active', '2026-02-18', '2026-02-19', 'good', '2026-02-18 15:17:41', '2026-02-18 15:32:37'),
(21, 'INV2026022026020002', 'Luminous Eco', 'KJUSF4684188169116516', '654581', '1100', 'inverter', 'modified_sine', '230V', '230V', '90%', '24V', 'erherjejrejeje4', '2 years', 10000.00, 'active', '2026-02-24', '2026-02-25', 'excellent', '2026-02-24 16:44:47', '2026-02-27 17:15:52'),
(27, 'INV20260220260220260', 'Luminous Eco Volt+ 1050659', NULL, 'Luminous', '1100', 'inverter', 'modified_sine', '230V', '230V', '95%', '12V', 'ghhbesdhb', '3 years', 15000.00, 'active', '2026-02-27', '2026-02-28', 'excellent', '2026-02-27 17:19:24', '2026-02-27 17:19:24'),
(33, 'INV2026022602', 'Luminous Eco Volt+ 105', '5699968768', 'Luminous', '1400', 'inverter', 'modified_sine', '230V', '230V', '90%', '12V', 'uigy8gyfv', '3 years', 15000.00, 'active', '2026-02-27', '2026-02-28', 'excellent', '2026-02-27 17:35:48', '2026-02-27 17:35:48'),
(36, 'INV2026030001', 'Luminous Eco Volt+ 10500', '698498494sdsdhhbdaSDSG498414', '6545814894154151', '1400', 'inverter', 'modified_sine', '230V', '230V', '90%', '12V', 'hbrhgtreh', '5 years', 5000.00, 'active', '2026-03-02', '2026-03-02', 'excellent', '2026-03-02 16:30:59', '2026-03-02 16:30:59'),
(37, 'INV2026030002', '', '', '', '', 'inverter', 'modified_sine', '230V', '230V', '', '12V', '', '1 year', 0.00, 'active', NULL, NULL, 'good', '2026-03-07 16:08:16', '2026-03-07 16:08:16'),
(38, 'INV2026030003', 'Luminous Eco Volt+ 10500', '698498494sdsdhhbdaSDSG498414477', 'Luminous', '', 'inverter', 'modified_sine', '230V', '230V', '', '12V', '', '1 year', 0.00, 'active', NULL, NULL, 'good', '2026-03-12 16:31:22', '2026-03-12 16:31:22');

--
-- Triggers `inverters`
--
DELIMITER $$
CREATE TRIGGER `before_inverter_insert` BEFORE INSERT ON `inverters` FOR EACH ROW BEGIN
    DECLARE next_num INT;
    DECLARE year_prefix VARCHAR(10);
    
    -- Generate inverter_code if not provided
    IF NEW.inverter_code IS NULL OR NEW.inverter_code = '' THEN
        SET year_prefix = DATE_FORMAT(NOW(), '%Y%m');
        
        SELECT COALESCE(MAX(CAST(SUBSTRING(inverter_code, 11) AS UNSIGNED)), 0) + 1 
        INTO next_num 
        FROM `inverters` 
        WHERE inverter_code LIKE CONCAT('INV', year_prefix, '%');
        
        SET NEW.inverter_code = CONCAT('INV', year_prefix, LPAD(next_num, 4, '0'));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `inverter_services`
--

CREATE TABLE `inverter_services` (
  `id` int(11) NOT NULL,
  `service_code` varchar(20) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `inverter_id` int(11) DEFAULT NULL,
  `service_staff_id` int(11) DEFAULT NULL,
  `issue_description` text DEFAULT NULL,
  `warranty_status` enum('in_warranty','extended_warranty','out_of_warranty') NOT NULL DEFAULT 'out_of_warranty',
  `amc_status` enum('active','expired','no_amc') DEFAULT 'no_amc',
  `status` enum('pending','in_progress','diagnostic','repairing','testing','completed','delivered','cancelled') DEFAULT 'pending',
  `payment_status` enum('pending','paid','refunded') DEFAULT 'pending',
  `final_cost` decimal(10,2) DEFAULT 0.00,
  `estimated_completion_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inverter_services`
--

INSERT INTO `inverter_services` (`id`, `service_code`, `customer_id`, `customer_phone`, `inverter_id`, `service_staff_id`, `issue_description`, `warranty_status`, `amc_status`, `status`, `payment_status`, `final_cost`, `estimated_completion_date`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'INV001', 1, '9876543210', 1, 2, 'Inverter not charging battery', 'in_warranty', 'active', 'completed', 'paid', 1800.00, '2026-02-16', 'Customer satisfied with service', '2026-02-14 18:59:34', '2026-02-14 18:59:34'),
(2, 'INV002', 2, '8765432109', 2, 3, 'UPS beeping continuously', 'out_of_warranty', 'no_amc', 'in_progress', 'paid', 2800.00, '2026-02-15', 'Waiting for battery delivery', '2026-02-14 18:59:34', '2026-02-16 15:58:26'),
(4, 'INV004', 6, '8220647708', 3, 2, 'safasfasfs', 'in_warranty', 'no_amc', 'pending', 'pending', 300.00, '2026-02-17', 'safsafa', '2026-02-16 15:00:40', '2026-02-16 15:00:40'),
(15, 'INV005', 11, '7858961436', 20, 3, 'ssgsedgdsddsg', 'in_warranty', 'no_amc', 'in_progress', 'paid', 500.00, '2026-02-20', 'assabga', '2026-02-18 15:31:13', '2026-02-23 17:51:25'),
(17, 'INV006', 13, '7896142356', 10, 4, 'asaag', 'in_warranty', 'no_amc', 'in_progress', 'paid', 5000.00, '2026-02-24', 'asasfasfa', '2026-02-24 16:24:37', '2026-02-24 16:24:37'),
(18, 'INV007', 26, '7412589632', 20, 3, 'sdafsd', 'in_warranty', 'no_amc', 'testing', 'paid', 200.00, '2026-02-25', 'sdds', '2026-02-25 16:01:24', '2026-02-25 16:01:24'),
(19, 'INV008', 28, '6383158509', 22, 9, 'cable change', 'in_warranty', 'no_amc', 'in_progress', 'paid', 1000.00, '2026-02-27', 'best client', '2026-02-25 16:17:26', '2026-02-25 16:17:26'),
(20, 'INV009', 28, '6383158509', 21, 2, 'dgsd', 'in_warranty', 'no_amc', 'in_progress', 'paid', 500.00, '2026-02-27', 'sdaasa', '2026-02-26 19:32:37', '2026-02-26 19:32:37'),
(21, 'INV010', 29, '8220687708', 33, 3, 'rfehbrhberr', 'in_warranty', 'no_amc', 'in_progress', 'paid', 300.00, '2026-03-02', 'rgrhgerg', '2026-03-02 16:21:59', '2026-03-02 16:21:59'),
(22, 'INV011', 30, '1478523690', NULL, NULL, 'ddd', 'in_warranty', 'no_amc', 'pending', 'pending', 0.00, NULL, '', '2026-03-06 17:04:15', '2026-03-06 17:04:15'),
(23, 'INV2026030001', 30, '1478523690', NULL, NULL, '', 'in_warranty', 'no_amc', 'pending', 'pending', 0.00, '0000-00-00', '', '2026-03-06 17:15:52', '2026-03-06 17:15:52'),
(24, 'INV2026030002', 28, '6383158509', NULL, NULL, '', 'in_warranty', 'no_amc', 'pending', 'pending', 0.00, '0000-00-00', '', '2026-03-06 17:18:39', '2026-03-06 17:18:39'),
(25, 'INV2026030003', 28, '6383158509', NULL, NULL, '', 'in_warranty', 'no_amc', 'pending', 'pending', 0.00, '0000-00-00', '', '2026-03-07 16:24:55', '2026-03-07 16:24:55'),
(26, 'INV2026030004', 30, '1478523690', 37, NULL, '', 'in_warranty', 'no_amc', 'pending', 'pending', 0.00, '0000-00-00', '', '2026-03-07 16:32:13', '2026-03-07 16:32:13'),
(27, 'INV2026030005', 26, '7412589632', 37, NULL, '', 'in_warranty', 'no_amc', 'pending', 'pending', 0.00, '0000-00-00', '', '2026-03-07 16:32:46', '2026-03-07 16:32:46'),
(28, 'INV2026030006', 30, '1478523690', 38, NULL, '', 'in_warranty', 'no_amc', 'pending', 'pending', 0.00, '0000-00-00', '', '2026-03-12 17:27:44', '2026-03-12 17:27:44');

--
-- Triggers `inverter_services`
--
DELIMITER $$
CREATE TRIGGER `before_inverter_service_insert` BEFORE INSERT ON `inverter_services` FOR EACH ROW BEGIN
    DECLARE next_num INT;
    IF NEW.service_code IS NULL OR NEW.service_code = '' THEN
        SELECT COALESCE(MAX(CAST(SUBSTRING(service_code, 4) AS UNSIGNED)), 0) + 1 
        INTO next_num 
        FROM `inverter_services`;
        SET NEW.service_code = CONCAT('INV', LPAD(next_num, 3, '0'));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `places`
--

CREATE TABLE `places` (
  `id` int(11) NOT NULL,
  `area_id` int(11) NOT NULL,
  `place_name` varchar(200) NOT NULL,
  `place_type` enum('city','town','village','tourist','commercial','residential','industrial','educational') DEFAULT 'town',
  `popularity_rank` int(11) DEFAULT 999,
  `pincode` varchar(10) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `population` int(11) DEFAULT NULL,
  `special_notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `places`
--

INSERT INTO `places` (`id`, `area_id`, `place_name`, `place_type`, `popularity_rank`, `pincode`, `latitude`, `longitude`, `population`, `special_notes`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Tirunelveli City', 'city', 1, '627001', 8.72861100, 77.70805600, 473637, 'Major educational hub with Manonmaniam Sundaranar University', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(2, 1, 'Palayamkottai', 'city', 2, '627002', 8.71000000, 77.76000000, 350000, 'Educational center with many colleges and schools', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(3, 1, 'Ambasamudram', 'town', 3, '627401', 8.70694400, 77.44833300, 55000, 'Taluk headquarters, banks of Tamirabarani River', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(4, 1, 'Sankarankovil', 'town', 4, '627756', 9.17111100, 77.54972200, 65000, 'Famous for Sankarankovil Temple', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(5, 1, 'Tiruchendur', 'tourist', 5, '628215', 8.49583300, 78.11944400, 32000, 'Famous Murugan Temple, coastal town', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(6, 1, 'Cheranmahadevi', 'town', 6, '627414', 8.66388900, 77.53861100, 28000, 'Historical town on Tamirabarani river banks', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(7, 1, 'Vallioor', 'town', 7, '627117', 8.38472200, 77.65111100, 30000, 'Famous for Ayyappa Temple', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(8, 1, 'Thisayanvilai', 'town', 8, '627657', 8.31444400, 77.85722200, 25000, 'Commercial town near Tiruchendur', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(9, 1, 'Radhapuram', 'town', 9, '627133', 8.29555600, 77.76888900, 20000, 'Taluk headquarters', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(10, 1, 'Nanguneri', 'town', 10, '627108', 8.49333300, 77.66666700, 18000, 'Famous for Vanamamalai Temple', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(11, 2, 'Thoothukudi City', 'city', 1, '628001', 8.76416600, 78.13472200, 410760, 'Major port city, industrial hub', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(12, 2, 'Kovilpatti', 'city', 2, '628501', 9.17111100, 77.86944400, 105000, 'Major industrial and commercial center', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(13, 2, 'Kayalpattinam', 'town', 3, '628204', 8.57111100, 78.11972200, 40000, 'Historic Muslim fishing town', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(14, 2, 'Ettayapuram', 'town', 4, '628902', 9.14888900, 77.99000000, 18000, 'Birthplace of poet Subramania Bharati', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(15, 2, 'Vilathikulam', 'town', 5, '628907', 9.13333300, 78.16666700, 15000, 'Taluk headquarters', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(16, 2, 'Srivaikuntam', 'town', 6, '628601', 8.62916700, 77.92583300, 20000, 'Famous for ancient temple', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(17, 3, 'Nagercoil', 'city', 1, '629001', 8.17833300, 77.43138900, 224849, 'District headquarters, commercial center', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(18, 3, 'Kanyakumari', 'tourist', 2, '629702', 8.08830600, 77.53852800, 29679, 'Southernmost tip of India, tourist paradise', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(19, 3, 'Kuzhithurai', 'town', 3, '629163', 8.31750000, 77.19138900, 30000, 'Border town near Kerala', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(20, 3, 'Colachel', 'town', 4, '629251', 8.17916700, 77.25833300, 25000, 'Major fishing harbor', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(21, 3, 'Padmanabhapuram', 'tourist', 5, '629175', 8.24472200, 77.32666700, 15000, 'Historic palace, former Travancore capital', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(22, 3, 'Marthandam', 'town', 6, '629165', 8.31027800, 77.21416700, 40000, 'Commercial center near Kerala border', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(23, 4, 'Tenkasi City', 'city', 1, '627811', 8.96000000, 77.31000000, 70000, 'District headquarters, gateway to Western Ghats', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(24, 4, 'Sivagiri', 'town', 2, '627758', 9.33388900, 77.43444400, 25000, 'Famous for Sivagiri Temple', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(25, 4, 'Shencottah', 'town', 3, '627809', 9.02638900, 77.37361100, 30000, 'Border town with Kerala', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(26, 4, 'Alangulam', 'town', 4, '627851', 8.86444400, 77.50027800, 20000, 'Taluk headquarters', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51'),
(27, 4, 'Kadayanallur', 'town', 5, '627751', 9.07638900, 77.34305600, 90000, 'Major town in Tenkasi district', 1, '2026-02-10 17:15:51', '2026-02-10 17:15:51');

-- --------------------------------------------------------

--
-- Table structure for table `salary`
--

CREATE TABLE `salary` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `service_type` enum('water','inverter') NOT NULL DEFAULT 'water' COMMENT 'water: Water Services, inverter: Inverter Services, both: Both Services',
  `staff_name` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `salary_date` date NOT NULL,
  `salary_month` varchar(7) NOT NULL COMMENT 'Format: YYYY-MM',
  `payment_method` enum('cash','card','bank_transfer') DEFAULT 'bank_transfer',
  `transaction_id` varchar(100) DEFAULT NULL,
  `bonus` decimal(10,2) DEFAULT 0.00,
  `deductions` decimal(10,2) DEFAULT 0.00,
  `net_amount` decimal(10,2) GENERATED ALWAYS AS (`amount` + `bonus` - `deductions`) STORED,
  `notes` text DEFAULT NULL,
  `paid_by` int(11) DEFAULT NULL,
  `paid_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `salary`
--

INSERT INTO `salary` (`id`, `staff_id`, `service_type`, `staff_name`, `amount`, `salary_date`, `salary_month`, `payment_method`, `transaction_id`, `bonus`, `deductions`, `notes`, `paid_by`, `paid_at`, `created_at`, `updated_at`) VALUES
(6, 2, 'inverter', 'Staff User', 800.00, '2026-02-28', '2026-02', 'bank_transfer', 'TXN1771258104657', 0.00, 0.00, '', 1, '2026-02-16 16:08:24', '2026-02-16 16:08:24', '2026-02-16 16:08:44'),
(7, 1, 'water', 'Admin User', 500.00, '2026-02-28', '2026-02', 'bank_transfer', 'TXN1771258146473', 0.00, 0.00, '', 1, '2026-02-16 16:09:06', '2026-02-16 16:09:06', '2026-02-16 16:17:25'),
(8, 3, 'water', 'Kalai', 3500.00, '2026-02-28', '2026-02', 'bank_transfer', 'TXN1771260849755', 0.00, 0.00, '', 1, '2026-02-16 16:54:09', '2026-02-16 16:54:09', '2026-02-16 16:54:09'),
(9, NULL, 'water', 'Mari', 1500.00, '2026-02-19', '2026-02', 'cash', 'TXN1771527833937', 0.00, 0.00, '', 1, '2026-02-19 19:04:07', '2026-02-19 19:04:07', '2026-02-19 19:04:07'),
(10, NULL, 'water', 'athi', 1000.00, '2026-02-22', '2026-02', 'bank_transfer', 'TXN1771743591326', 0.00, 0.00, 'jm,jhmhg', 1, '2026-02-22 07:00:02', '2026-02-22 07:00:02', '2026-02-22 07:00:02'),
(11, 8, 'water', 'nambi', 500.00, '2026-02-24', '2026-02', 'bank_transfer', 'TXN1771952130506', 0.00, 0.00, 'csdsd', 1, '2026-02-24 16:55:50', '2026-02-24 16:55:50', '2026-02-24 16:55:50'),
(12, 7, 'water', 'shandi', 500.00, '2026-02-24', '2026-02', 'bank_transfer', 'TXN1771958058867', 0.00, 0.00, 'trrtr', 1, '2026-02-24 18:34:28', '2026-02-24 18:34:28', '2026-02-24 18:34:28'),
(13, 7, 'inverter', 'shandi', 500.00, '2026-02-24', '2026-02', 'bank_transfer', 'TXN1771960388112', 0.00, 0.00, '', 1, '2026-02-24 19:13:14', '2026-02-24 19:13:14', '2026-02-24 19:13:14'),
(14, 8, 'inverter', 'nambi', 500.00, '2026-02-25', '2026-02', 'bank_transfer', 'TXN1772001502197', 0.00, 0.00, '', 1, '2026-02-25 06:39:03', '2026-02-25 06:39:03', '2026-02-25 06:39:03'),
(15, 9, 'water', 'Mariappan', 300.00, '2026-02-25', '2026-02', 'cash', 'TXN1772036359299', 0.00, 0.00, 'water  service salary', 1, '2026-02-25 16:20:20', '2026-02-25 16:20:20', '2026-02-25 16:20:20'),
(16, 9, 'inverter', 'Mariappan', 250.00, '2026-02-25', '2026-02', 'cash', 'TXN1772037745017', 0.00, 0.00, 'inverter service salary', 1, '2026-02-25 16:42:50', '2026-02-25 16:42:50', '2026-02-25 16:42:50'),
(19, 3, 'water', 'Kalai', 100.00, '2026-03-02', '2026-03', 'bank_transfer', 'TXN1772472642591', 0.00, 0.00, 'szv', 1, '2026-03-02 17:31:08', '2026-03-02 17:31:08', '2026-03-02 17:31:08'),
(20, 3, 'inverter', 'Kalai', 100.00, '2026-03-02', '2026-03', 'cash', 'TXN1772472713942', 0.00, 0.00, '', 1, '2026-03-02 17:32:08', '2026-03-02 17:32:08', '2026-03-02 17:32:08'),
(21, 1, 'water', 'Admin User', 50.00, '2026-03-02', '2026-03', 'bank_transfer', 'TXN1772476142105', 0.00, 0.00, 'bffbsd', 1, '2026-03-02 18:29:12', '2026-03-02 18:29:12', '2026-03-02 18:29:12'),
(22, 9, 'water', 'Mariappan', 50.00, '2026-03-02', '2026-03', 'bank_transfer', 'TXN1772477612087', 0.00, 0.00, '', 1, '2026-03-02 18:53:51', '2026-03-02 18:53:51', '2026-03-02 18:53:51'),
(23, 7, 'water', 'shandi', 25.00, '2026-03-02', '2026-03', 'bank_transfer', 'TXN1772479496020', 0.00, 0.00, '', 1, '2026-03-02 19:25:04', '2026-03-02 19:25:04', '2026-03-02 19:25:04');

--
-- Triggers `salary`
--
DELIMITER $$
CREATE TRIGGER `before_salary_insert` BEFORE INSERT ON `salary` FOR EACH ROW BEGIN
    DECLARE staff_user_name VARCHAR(100);
    
    -- If staff_id is provided, get the staff_name from users table
    IF NEW.staff_id IS NOT NULL AND (NEW.staff_name IS NULL OR NEW.staff_name = '') THEN
        SELECT name INTO staff_user_name FROM users WHERE id = NEW.staff_id;
        SET NEW.staff_name = staff_user_name;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `salary_summary`
-- (See below for the actual view)
--
CREATE TABLE `salary_summary` (
`salary_month` varchar(7)
,`total_employees` bigint(21)
,`total_base_salary` decimal(32,2)
,`total_bonus` decimal(32,2)
,`total_deductions` decimal(32,2)
,`total_salary_paid` decimal(32,2)
,`average_salary` decimal(14,6)
);

-- --------------------------------------------------------

--
-- Table structure for table `service_orders`
--

CREATE TABLE `service_orders` (
  `id` int(11) NOT NULL,
  `service_code` varchar(20) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `battery_id` int(11) DEFAULT NULL,
  `inverter_id` int(11) DEFAULT NULL,
  `service_staff_id` int(11) DEFAULT NULL,
  `warranty_status` enum('in_warranty','extended_warranty','out_of_warranty') NOT NULL DEFAULT 'out_of_warranty',
  `amc_status` enum('active','expired','no_amc') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_orders`
--

INSERT INTO `service_orders` (`id`, `service_code`, `customer_id`, `customer_phone`, `battery_id`, `inverter_id`, `service_staff_id`, `warranty_status`, `amc_status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'SVC001', 1, NULL, 1, 1, 1, 'out_of_warranty', 'no_amc', NULL, '2026-01-16 17:15:51', '2026-03-12 17:10:03'),
(2, 'SVC002', 2, NULL, 2, NULL, 1, 'in_warranty', 'active', NULL, '2026-01-16 17:15:51', '2026-01-16 17:15:51'),
(3, 'SVC003', 3, NULL, 3, NULL, 1, 'out_of_warranty', 'expired', NULL, '2026-01-16 17:15:51', '2026-01-16 17:15:51'),
(5, 'SVC004', 5, '82554465521', 5, NULL, 2, 'in_warranty', 'active', 'utrjtjt ', '2026-01-22 16:47:09', '2026-03-07 16:47:21'),
(6, 'SVC005', 5, '9876543210', 6, NULL, 2, 'out_of_warranty', 'no_amc', 'asafaakjklakpasma aoanoias asm mpasm ', '2026-01-23 18:14:29', '2026-02-06 18:12:41'),
(7, 'SVC006', 1, '9876543210', 6, NULL, 2, '', 'no_amc', 'swiomapmpamddpqa[d,qadokq-dqdkq0dj0qdnqinpqfpqfqfqfqf', '2026-01-23 18:32:45', '2026-02-06 18:12:41'),
(8, 'SVC007', 1, '82554465521', 5, NULL, 1, 'extended_warranty', 'expired', 'ooijefqnuefoiefqoimfqenonoimwqoimeqfoifenoi', '2026-01-25 14:38:39', '2026-02-06 18:12:41'),
(9, 'SVC008', 4, '9876543210', 5, NULL, 1, 'out_of_warranty', 'no_amc', 'ssagadasdaag', '2026-01-25 15:21:39', '2026-02-06 18:12:41'),
(10, 'SVC009', 5, '001144775566', 3, NULL, 2, 'out_of_warranty', 'active', 'battrty is  may be  replacemet', '2026-01-25 15:27:48', '2026-02-06 18:12:41'),
(11, 'SVC010', 2, '9876543210', 6, NULL, 2, '', 'active', 'dfffdhsshsshsdsds', '2026-01-25 15:40:51', '2026-02-06 18:12:41'),
(12, 'SVC011', 4, '9876543210', 6, NULL, 2, '', 'active', 'ewqfqwkioinq', '2026-01-25 16:14:03', '2026-02-08 15:24:35'),
(13, 'SVC012', 3, '001144775566', 3, NULL, 3, '', 'active', ' aagaeahghaseheaqhe', '2026-01-25 16:49:07', '2026-02-06 18:12:41'),
(14, 'SVC013', 3, '82554465521', 6, NULL, 3, '', 'active', 'egehwjrhwhwhw', '2026-01-25 16:59:47', '2026-02-06 18:12:41'),
(15, 'SVC014', 2, '82554465521', 6, NULL, 2, 'in_warranty', 'active', 'hwrrhrhwsrwjrwjgqa', '2026-01-25 17:34:20', '2026-02-06 18:12:41'),
(16, 'SVC015', 4, '82554465521', 6, NULL, 1, '', 'active', 'poi 3poiknewqfinqewfn', '2026-01-25 17:50:40', '2026-02-06 18:12:41'),
(18, 'SVC016', 4, '9876543210', 6, NULL, 3, 'out_of_warranty', 'active', 'gadagaggagasdgaga', '2026-01-25 18:50:25', '2026-02-06 18:12:41'),
(20, 'SVC017', 4, '9876543210', 7, NULL, 3, 'out_of_warranty', 'no_amc', 'dssdsdsds', '2026-01-26 17:14:06', '2026-02-06 18:12:41'),
(21, 'SVC018', 5, '8220647708', 5, NULL, 2, 'out_of_warranty', 'active', '', '2026-01-29 17:10:41', '2026-02-06 18:12:41'),
(22, 'SVC019', 5, '08220647708', 6, NULL, 1, 'in_warranty', 'expired', 'yhytrrrurursrgtswtgswg', '2026-02-04 17:20:12', '2026-02-06 18:12:41'),
(23, 'SVC020', 3, '9876543212', 3, NULL, 3, '', 'active', 'zzxvzxv V ZVxszv ', '2026-02-04 17:22:26', '2026-02-06 18:12:41'),
(24, 'SVC021', 2, '9876543211', 5, NULL, 1, '', 'active', 'yrfhtgfdhtgfhf', '2026-02-04 17:57:56', '2026-02-06 18:12:41'),
(25, 'SVC022', 2, '9876543211', 5, NULL, 1, 'in_warranty', 'active', 'ssvavasvsavsdabvsav ', '2026-02-05 15:08:03', '2026-02-08 15:18:23'),
(26, 'SVC023', 4, '112255447788', 1, NULL, 2, '', 'active', 'ageegeeeeeeeeeeeeeeeeeeeeeeeeewe', '2026-02-05 15:18:24', '2026-02-06 18:12:41'),
(28, 'SVC025', 4, '112255447788', 7, NULL, 3, 'extended_warranty', 'active', 'eegwwhgwhwh', '2026-02-05 16:10:06', '2026-02-06 18:12:41'),
(29, 'SVC026', 1, '9876543210', 5, NULL, 1, 'in_warranty', 'active', 'dssbhdb', '2026-02-05 16:15:40', '2026-02-08 15:17:17'),
(30, 'SVC027', 1, '9876543210', 2, NULL, 1, '', 'active', 'sabgasaagva', '2026-02-05 16:34:08', '2026-02-06 18:12:41'),
(32, 'SVC029', 5, '08220647708', 5, NULL, 1, 'in_warranty', 'no_amc', 'dshdshswhsw', '2026-02-05 17:08:56', '2026-02-06 18:12:41'),
(35, 'SVC030', 4, '112255447788', 6, NULL, 2, 'extended_warranty', 'no_amc', '', '2026-02-06 18:19:11', '2026-02-06 18:19:11'),
(37, 'SVC032', 5, '08220647708', 49, NULL, 2, 'in_warranty', 'active', 'ddsd', '2026-02-10 17:29:00', '2026-02-10 17:29:00'),
(38, 'SVC033', 5, '08220647708', 42, NULL, 1, 'in_warranty', 'no_amc', 'gfk', '2026-02-10 17:49:17', '2026-02-10 17:49:17'),
(41, 'SVC036', 5, '08220647708', 50, NULL, 2, 'in_warranty', 'active', 'eagedgedw', '2026-02-11 19:04:39', '2026-02-12 13:58:50'),
(42, 'SVC037', 3, '9876543212', 53, NULL, 3, 'in_warranty', 'active', NULL, '2026-02-14 11:19:24', '2026-02-14 11:19:24'),
(43, 'SVC038', 6, '8220647708', 42, NULL, 3, 'in_warranty', 'active', 'hbgyufvyvt.', '2026-02-14 11:23:58', '2026-02-14 11:23:58'),
(44, 'SVC039', 8, '1234567890', 52, NULL, 3, 'in_warranty', 'active', NULL, '2026-02-14 11:59:27', '2026-02-14 11:59:27'),
(45, 'SVC040', 9, '9442525351', 53, NULL, 3, 'in_warranty', 'expired', NULL, '2026-02-14 12:16:48', '2026-02-16 17:13:36'),
(49, 'SVC042', 12, '9876543218', 55, NULL, 4, 'in_warranty', 'active', 'fqwgfqewgqgq', '2026-02-18 19:05:04', '2026-02-18 19:05:04'),
(50, 'SVC043', 11, '7858961436', 55, NULL, 4, 'in_warranty', 'active', 'dsg', '2026-02-19 18:39:51', '2026-02-19 18:39:51'),
(55, 'SVC044', 13, '7896142356', 36, NULL, 6, 'in_warranty', 'active', 'egewgwegw', '2026-02-24 16:24:01', '2026-02-24 16:24:01'),
(56, 'SVC045', 11, '7858961436', 41, NULL, 6, 'in_warranty', 'active', 'edgwgw', '2026-02-24 16:32:00', '2026-02-24 16:32:00'),
(57, 'SVC046', 16, '9344682013', 57, NULL, 7, 'in_warranty', 'active', 'dfefwefwed', '2026-02-24 18:06:42', '2026-02-24 18:06:42'),
(58, 'SVC047', 17, '8250647708', 46, NULL, 8, 'in_warranty', 'active', 'sfasfafa', '2026-02-24 18:08:29', '2026-02-24 18:08:29'),
(59, 'SVC048', 28, '6383158504', 58, NULL, 9, 'extended_warranty', 'active', 'Water service', '2026-02-25 16:12:40', '2026-02-25 16:13:53'),
(60, 'SVC049', 27, '7894561235', 57, NULL, 2, 'in_warranty', 'active', 'rfddf', '2026-02-26 19:31:47', '2026-02-26 19:31:47'),
(61, 'SVC050', 28, '6383158509', 57, NULL, 2, 'in_warranty', 'active', 'yr h', '2026-02-27 16:54:33', '2026-02-27 16:54:33'),
(62, 'SVC051', 29, '8220687708', 58, NULL, 2, 'in_warranty', 'active', 'nnonledndsdv', '2026-03-02 16:21:10', '2026-03-02 16:21:10'),
(63, 'SVC052', 29, '8220687708', 59, NULL, 2, 'in_warranty', 'active', 'sgf', '2026-03-06 16:33:31', '2026-03-06 16:33:31'),
(64, 'SVC053', 30, '1478523690', 59, NULL, 1, 'in_warranty', 'active', 'g', '2026-03-06 16:42:47', '2026-03-06 16:42:47'),
(65, 'SVC-20260306-0003', 30, '1478523690', NULL, NULL, NULL, 'in_warranty', 'no_amc', 'ss', '2026-03-06 16:51:06', '2026-03-06 16:51:06'),
(66, 'SVC-20260306-0004', 26, '7412589632', NULL, NULL, NULL, 'in_warranty', 'no_amc', 'ffff', '2026-03-06 16:51:17', '2026-03-06 16:51:17'),
(67, 'SVC-20260306-0005', 27, '7894561235', NULL, NULL, NULL, 'in_warranty', 'no_amc', NULL, '2026-03-06 16:55:18', '2026-03-06 16:55:18'),
(68, 'SVC-20260307-0001', 30, '1478523690', 60, NULL, NULL, 'in_warranty', 'no_amc', NULL, '2026-03-07 16:25:27', '2026-03-07 16:25:27'),
(69, 'SVC-20260307-0002', 30, '1478523690', 60, NULL, NULL, 'in_warranty', 'no_amc', NULL, '2026-03-07 16:39:32', '2026-03-07 16:39:32'),
(70, 'SVC-20260307-0003', 27, '7894561235', 60, NULL, NULL, 'in_warranty', 'no_amc', NULL, '2026-03-07 16:39:41', '2026-03-07 16:39:41'),
(71, 'SVC-20260312-0001', 31, '8220647701', 58, 21, 1, 'in_warranty', 'no_amc', NULL, '2026-03-12 16:44:03', '2026-03-12 17:52:19'),
(72, 'SVC-20260312-0002', 30, '1478523690', 58, NULL, 2, 'in_warranty', 'no_amc', NULL, '2026-03-12 16:45:18', '2026-03-12 16:45:18'),
(73, 'SVC-20260312-0003', 31, '8220647701', 59, 38, 1, 'in_warranty', 'no_amc', 'saccasdadv', '2026-03-12 16:53:21', '2026-03-12 17:15:08'),
(74, 'SVC-20260313-0001', 29, '8220687708', 57, 36, NULL, 'in_warranty', 'no_amc', NULL, '2026-03-12 19:46:12', '2026-03-12 19:46:12'),
(75, 'SVC-20260313-0004', 27, '7894561235', 55, 36, NULL, 'in_warranty', 'no_amc', NULL, '2026-03-12 19:54:17', '2026-03-12 19:54:17'),
(76, 'SVC-20260321-0001', 32, '8220647705', 59, 38, 2, 'in_warranty', 'active', 'sf', '2026-03-21 11:43:20', '2026-03-21 11:43:20');

--
-- Triggers `service_orders`
--
DELIMITER $$
CREATE TRIGGER `before_service_orders_insert` BEFORE INSERT ON `service_orders` FOR EACH ROW BEGIN
    DECLARE next_number INT;
    DECLARE new_code VARCHAR(20);
    DECLARE date_prefix VARCHAR(8);
    
    -- Get current date in YYYYMMDD format
    SET date_prefix = DATE_FORMAT(NOW(), '%Y%m%d');
    
    -- Get the next number for today
    SELECT COALESCE(MAX(CAST(SUBSTRING(service_code, 12) AS UNSIGNED)), 0) + 1
    INTO next_number
    FROM service_orders
    WHERE service_code LIKE CONCAT('SVC-', date_prefix, '-%');
    
    -- Generate new service code (format: SVC-YYYYMMDD-XXXX)
    SET new_code = CONCAT('SVC-', date_prefix, '-', LPAD(next_number, 4, '0'));
    
    -- Set the service_code
    SET NEW.service_code = new_code;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `staff_monthly_earnings`
-- (See below for the actual view)
--
CREATE TABLE `staff_monthly_earnings` (
`staff_id` int(11)
,`staff_name` varchar(100)
,`salary_month` varchar(7)
,`base_salary` decimal(10,2)
,`bonus` decimal(10,2)
,`deductions` decimal(10,2)
,`net_amount` decimal(10,2)
,`total_expenses_claimed` decimal(32,2)
,`petrol_expenses` decimal(32,2)
,`other_expenses` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff') DEFAULT 'staff',
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `phone`, `address`, `department`, `position`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'Admin User', 'admin@sunoffice.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL, NULL, NULL, NULL, 1, '2026-03-02 18:39:11', '2026-02-10 14:21:51', '2026-03-02 18:39:11'),
(2, 'Staff User', 'staff@sunoffice.com', '$2y$10$YourHashedPasswordHere', 'staff', NULL, NULL, NULL, NULL, 1, NULL, '2026-02-10 14:21:51', '2026-02-10 14:21:51'),
(3, 'Kalai', 'kalaix@sun.com', '$2y$10$jwDRDyfM3BWQPiwGN2WOS.Q9MY8/UeyoizPBZGBKyhK.XU4Uw30Jm', 'admin', '441455125125', '137/4 MAIN ROAD MANUR', 'office', 'admin', 1, '2026-02-27 18:35:50', '2026-02-14 11:06:21', '2026-02-27 18:35:50'),
(7, 'shandi', 'shandi@sun.com', '$2y$10$QT3fEL7aDHtBTFbphwy.Y.hoc1VEvHj8gIg0rWSZ98sSWCPGKD53a', 'staff', '9629212738', 'usnbbnan acaocnoanan acnacav', 'Office', 'admin', 1, '2026-02-24 16:16:37', '2026-02-24 16:14:19', '2026-02-24 16:50:36'),
(8, 'nambi', 'jeevan2k3liux@gmail.com', '$2y$10$VMYC3uS4hE8RjHq2VOCE/eoEkaeroACWK3uxoH0396Q4CUmOvMO6a', '', '8220647708', 'ewwehbvvdwsegfw', 'admin', 'admin', 1, NULL, '2026-02-24 16:53:27', '2026-02-24 16:53:27'),
(9, 'Mariappan', 'mari@sun.com', '$2y$10$1ItzGVy1tnV4gEBp29VjK.ANIiO.ajTfBuQC53B5LgF75YhT8ujX.', 'staff', '9361392471', '45/6 Main road samabathanapuram, palayamkottai,tirunelveli', 'sales', 'staff', 1, '2026-03-09 18:28:11', '2026-02-25 16:05:38', '2026-03-09 18:28:11'),
(10, 'bennai', 'banni@sun.com', '$2y$10$JZdvvlCA21Ft4wUacNBymuS4v7nwaDxE26t9eW.HAxIyA7GwBVAFm', 'admin', '7412589634', 'egegwegwgwg', 'admin', 'admin', 1, NULL, '2026-03-02 17:55:06', '2026-03-02 17:55:06'),
(11, 'appikutty', 'appi@!sun.com', '$2y$10$rdUJaL5eFoqbjb7QcuJ34ueJADOQij7x.Rf9K2GetH7exXD6/uD.G', 'admin', '7894561235', 'egwgeewg', 'admin', 'admin', 1, NULL, '2026-03-02 18:54:37', '2026-03-02 18:54:37');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `water_services`
--

CREATE TABLE `water_services` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `service_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `water_services`
--

INSERT INTO `water_services` (`id`, `service_id`, `customer_id`, `amount`, `service_date`, `notes`, `created_by`, `created_at`) VALUES
(2, 41, 5, 100.00, '2026-02-12', 'Water service payment for service #SVC036 - athi', 1, '2026-02-12 18:56:20'),
(3, 35, 4, 100.00, '2026-02-13', 'Water service payment for service #SVC030 - Rajesh Kumar', 1, '2026-02-12 18:56:49'),
(4, 30, 1, 100.00, '2026-02-12', 'Water service payment for service #SVC027 - John Doe', 1, '2026-02-12 18:57:25'),
(5, 27, 5, 150.00, '2026-02-12', 'Water service payment for service #SVC024 - athi', 1, '2026-02-12 18:58:51'),
(6, 30, 1, 100.00, '2026-02-12', 'Water service payment for service #SVC027 - John Doe', 1, '2026-02-12 19:05:12'),
(7, 37, 5, 150.00, '2026-02-13', 'Water service payment for service #SVC032 - athi', 1, '2026-02-13 16:37:22'),
(8, 42, 3, 100.00, '2026-02-14', 'Water service payment for service #SVC037 - Robert Johnson', 1, '2026-02-14 12:22:26'),
(9, 44, 8, 100.00, '2026-02-14', 'Water service payment for service #SVC039 - anto', 1, '2026-02-14 12:59:38'),
(10, 45, 9, 150.00, '2026-02-14', 'Water service payment for service #SVC040 - allapitchai.ks(pathamadai)', 1, '2026-02-14 13:19:48'),
(11, 15, 2, 100.00, '2026-02-18', 'Water service payment for service #INV005 - Abi', 1, '2026-02-18 17:59:41'),
(12, 48, 8, 100.00, '2026-02-18', 'Water service payment for service #SVC041 - anto', 1, '2026-02-18 17:59:52'),
(13, 4, 6, 300.00, '2026-02-18', 'Water service payment for service #INV004 - Diviya', 1, '2026-02-18 18:00:02'),
(14, 43, 6, 600.00, '2026-02-18', 'Water service payment for service #SVC038 - Diviya', 1, '2026-02-18 18:03:39'),
(15, 50, 11, 100.00, '2026-02-23', 'Water service payment for service #SVC043 - Abi', 1, '2026-02-23 19:07:21'),
(16, 55, 13, 100.00, '2026-01-14', 'Water service payment for service #SVC044 - kavitha', 1, '2026-02-24 17:28:34'),
(17, 55, 13, 100.00, '2026-02-24', 'Water service payment for service #SVC044 - kavitha', 1, '2026-02-24 19:50:09'),
(18, 59, 28, 500.00, '2026-02-25', 'Water service payment for service #SVC048 - Dhanish', 1, '2026-02-25 17:18:14'),
(19, 62, 29, 200.00, '2026-03-02', 'Water service payment for service #SVC051 - athijeeva', 1, '2026-03-02 17:34:00'),
(20, 61, 28, 200.00, '2026-03-02', 'Water service payment for service #SVC050 - Dhanish', 1, '2026-03-02 17:37:00'),
(21, 67, 27, 100.00, '2026-03-06', 'Water service payment for service #SVC-20260306-0005 - athikutty', 1, '2026-03-06 18:18:59'),
(22, 66, 26, 200.00, '2026-03-06', 'Water service payment for service #SVC-20260306-0004 - thami', 1, '2026-03-06 18:19:26'),
(24, 70, 27, 100.00, '2026-02-11', 'Water service payment for service #SVC-20260307-0003 - athikutty', 1, '2026-03-08 00:44:04'),
(25, 70, 27, 100.00, '2026-02-19', 'Water service payment for service #SVC-20260307-0003 - athikutty', 1, '2026-03-07 20:14:38'),
(26, 70, 27, 100.00, '2026-02-17', 'Water service payment for service #SVC-20260307-0003 - athikutty', 1, '2026-03-07 20:25:33'),
(27, 69, 30, 200.00, '2026-02-18', 'Water service payment for service #SVC-20260307-0002 - saro', 1, '2026-03-07 20:27:55'),
(28, 66, 26, 100.00, '2026-02-18', 'Water service payment for service #SVC-20260306-0004 - thami', 1, '2026-03-07 20:29:41'),
(29, 68, 30, 100.00, '2026-02-17', 'Water service payment for service #SVC-20260307-0001 - saro', 1, '2026-03-08 09:18:24'),
(30, 63, 29, 100.00, '2026-03-08', 'Water service payment for service #SVC052 - athijeeva', 1, '2026-03-08 09:18:55');

-- --------------------------------------------------------

--
-- Structure for view `active_users`
--
DROP TABLE IF EXISTS `active_users`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_users`  AS SELECT `users`.`id` AS `id`, `users`.`name` AS `name`, `users`.`email` AS `email`, `users`.`role` AS `role`, `users`.`last_login` AS `last_login`, `users`.`created_at` AS `created_at` FROM `users` WHERE `users`.`is_active` = 1 ;

-- --------------------------------------------------------

--
-- Structure for view `expense_summary`
--
DROP TABLE IF EXISTS `expense_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `expense_summary`  AS SELECT date_format(`expenses`.`expense_date`,'%Y-%m') AS `month`, `expenses`.`expense_type` AS `expense_type`, count(0) AS `total_expenses`, sum(`expenses`.`amount`) AS `total_amount`, avg(`expenses`.`amount`) AS `average_amount`, min(`expenses`.`amount`) AS `min_amount`, max(`expenses`.`amount`) AS `max_amount` FROM `expenses` GROUP BY date_format(`expenses`.`expense_date`,'%Y-%m'), `expenses`.`expense_type` ORDER BY date_format(`expenses`.`expense_date`,'%Y-%m') DESC, `expenses`.`expense_type` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `salary_summary`
--
DROP TABLE IF EXISTS `salary_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `salary_summary`  AS SELECT `salary`.`salary_month` AS `salary_month`, count(0) AS `total_employees`, sum(`salary`.`amount`) AS `total_base_salary`, sum(`salary`.`bonus`) AS `total_bonus`, sum(`salary`.`deductions`) AS `total_deductions`, sum(`salary`.`net_amount`) AS `total_salary_paid`, avg(`salary`.`net_amount`) AS `average_salary` FROM `salary` GROUP BY `salary`.`salary_month` ORDER BY `salary`.`salary_month` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `staff_monthly_earnings`
--
DROP TABLE IF EXISTS `staff_monthly_earnings`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `staff_monthly_earnings`  AS SELECT `s`.`staff_id` AS `staff_id`, `s`.`staff_name` AS `staff_name`, `s`.`salary_month` AS `salary_month`, `s`.`amount` AS `base_salary`, `s`.`bonus` AS `bonus`, `s`.`deductions` AS `deductions`, `s`.`net_amount` AS `net_amount`, coalesce(`e`.`total_expenses`,0) AS `total_expenses_claimed`, coalesce(`e`.`petrol_expenses`,0) AS `petrol_expenses`, coalesce(`e`.`other_expenses`,0) AS `other_expenses` FROM (`salary` `s` left join (select `expenses`.`staff_id` AS `staff_id`,date_format(`expenses`.`expense_date`,'%Y-%m') AS `expense_month`,sum(`expenses`.`amount`) AS `total_expenses`,sum(case when `expenses`.`expense_type` = 'petrol' then `expenses`.`amount` else 0 end) AS `petrol_expenses`,sum(case when `expenses`.`expense_type` = 'others' then `expenses`.`amount` else 0 end) AS `other_expenses` from `expenses` group by `expenses`.`staff_id`,date_format(`expenses`.`expense_date`,'%Y-%m')) `e` on(`s`.`staff_id` = `e`.`staff_id` and `s`.`salary_month` = `e`.`expense_month`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `district_code` (`district_code`),
  ADD KEY `idx_area_district` (`district_name`),
  ADD KEY `idx_area_active` (`is_active`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `batteries`
--
ALTER TABLE `batteries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `battery_code` (`battery_code`),
  ADD UNIQUE KEY `battery_serial` (`battery_serial`),
  ADD KEY `idx_battery_serial` (`battery_serial`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_code` (`customer_code`),
  ADD KEY `idx_customer_phone` (`phone`),
  ADD KEY `idx_customer_name` (`full_name`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_expense_staff` (`staff_id`),
  ADD KEY `idx_expense_staff_name` (`staff_name`),
  ADD KEY `idx_expense_type` (`expense_type`),
  ADD KEY `idx_expense_date` (`expense_date`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_service_type` (`service_type`);

--
-- Indexes for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_category_name` (`category_name`);

--
-- Indexes for table `inverters`
--
ALTER TABLE `inverters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `inverter_code` (`inverter_code`),
  ADD UNIQUE KEY `unique_serial` (`inverter_serial`),
  ADD KEY `idx_model` (`inverter_model`),
  ADD KEY `idx_brand` (`inverter_brand`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_serial` (`inverter_serial`);

--
-- Indexes for table `inverter_services`
--
ALTER TABLE `inverter_services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `service_code` (`service_code`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_inverter` (`inverter_id`),
  ADD KEY `idx_staff` (`service_staff_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `places`
--
ALTER TABLE `places`
  ADD PRIMARY KEY (`id`),
  ADD KEY `area_id` (`area_id`),
  ADD KEY `idx_place_name` (`place_name`),
  ADD KEY `idx_place_type` (`place_type`),
  ADD KEY `idx_place_rank` (`popularity_rank`),
  ADD KEY `idx_place_active` (`is_active`);

--
-- Indexes for table `salary`
--
ALTER TABLE `salary`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_unique_staff_month_service` (`staff_id`,`salary_month`,`service_type`),
  ADD KEY `idx_salary_staff` (`staff_id`),
  ADD KEY `idx_salary_staff_name` (`staff_name`),
  ADD KEY `idx_salary_month` (`salary_month`),
  ADD KEY `idx_salary_date` (`salary_date`),
  ADD KEY `idx_paid_by` (`paid_by`),
  ADD KEY `idx_service_type` (`service_type`);

--
-- Indexes for table `service_orders`
--
ALTER TABLE `service_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_unique_service_code` (`service_code`),
  ADD UNIQUE KEY `idx_service_code` (`service_code`),
  ADD KEY `idx_service_customer` (`customer_id`),
  ADD KEY `idx_service_battery` (`battery_id`),
  ADD KEY `idx_service_staff` (`service_staff_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_service_inverter` (`inverter_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `water_services`
--
ALTER TABLE `water_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_water_services_customer` (`customer_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `areas`
--
ALTER TABLE `areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `batteries`
--
ALTER TABLE `batteries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `inverters`
--
ALTER TABLE `inverters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `inverter_services`
--
ALTER TABLE `inverter_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `places`
--
ALTER TABLE `places`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `salary`
--
ALTER TABLE `salary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `service_orders`
--
ALTER TABLE `service_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `water_services`
--
ALTER TABLE `water_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `fk_expenses_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_expenses_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `places`
--
ALTER TABLE `places`
  ADD CONSTRAINT `places_ibfk_1` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `salary`
--
ALTER TABLE `salary`
  ADD CONSTRAINT `fk_salary_paid_by` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_salary_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `service_orders`
--
ALTER TABLE `service_orders`
  ADD CONSTRAINT `fk_service_orders_inverter` FOREIGN KEY (`inverter_id`) REFERENCES `inverters` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `water_services`
--
ALTER TABLE `water_services`
  ADD CONSTRAINT `fk_water_services_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
