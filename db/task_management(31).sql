-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 08, 2025 at 08:39 PM
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
-- Database: `task_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_assets`
--

CREATE TABLE `tbl_assets` (
  `a_id` int(50) NOT NULL,
  `a_ref_no` varchar(200) NOT NULL,
  `a_name` varchar(200) NOT NULL,
  `a_status` varchar(200) NOT NULL,
  `a_current_status` varchar(200) NOT NULL,
  `a_quantity` int(50) NOT NULL,
  `a_serial_no` varchar(200) NOT NULL,
  `a_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_assets`
--

INSERT INTO `tbl_assets` (`a_id`, `a_ref_no`, `a_name`, `a_status`, `a_current_status`, `a_quantity`, `a_serial_no`, `a_date`) VALUES
(6, 'TEST-001', 'Test', 'Borrowing', 'Borrowed', 1, '12345', '2025-08-25'),
(7, 'SUPERMODEM-001', 'Super Modem', 'Deployment', 'Deployed', 1, '0', '2025-08-25'),
(8, 'SUPERMODEM-002', 'Super Modem', 'Deployment', 'Available', 1, '0', '2025-08-25'),
(9, 'SUPERMODEM-003', 'Super Modem', 'Deployment', 'Available', 1, '0', '2025-08-25'),
(11, 'MODEM-001', 'Modem', 'Borrowing', 'Borrowed', 2, '0', '2025-08-25'),
(12, 'MODEM-002', 'Modem', 'Borrowing', 'Borrowed', 2, '0', '2025-08-25'),
(13, 'MODEM-003', 'Modem', 'Borrowing', 'Borrowed', 2, '0', '2025-08-25'),
(14, 'MODEM-004', 'Modem', 'Borrowing', 'Borrowed', 2, '0', '2025-08-25'),
(15, 'MODEM-005', 'Modem', 'Borrowing', 'Borrowed', 2, '0', '2025-08-25'),
(16, 'TESTING-001', 'Testing', 'Borrowing', 'Available', 1, '0', '2025-08-26'),
(17, 'TESTING-002', 'Testing', 'Borrowing', 'Available', 1, '0', '2025-08-26'),
(18, 'TESTING-003', 'Testing', 'Borrowing', 'Available', 1, '0', '2025-08-26'),
(19, 'TESTING-004', 'Testing', 'Borrowing', 'Available', 1, '0', '2025-08-26'),
(20, 'TESTING-005', 'Testing', 'Borrowing', 'Available', 1, '0', '2025-08-26'),
(21, 'LADDER-001', 'Ladder', 'Borrowing', 'Borrowed', 2, '0', '2025-08-26'),
(22, 'LADDER-002', 'Ladder', 'Borrowing', 'Borrowed', 2, '0', '2025-08-26'),
(23, 'LADDER-003', 'Ladder', 'Borrowing', 'Borrowed', 2, '0', '2025-08-26'),
(24, 'LADDER-004', 'Ladder', 'Borrowing', 'Borrowed', 2, '0', '2025-08-26'),
(25, 'LADDER-005', 'Ladder', 'Borrowing', 'Available', 2, '0', '2025-08-26'),
(26, 'MIKHA-001', 'Mikha', 'Borrowing', 'Available', 1, '0', '2025-08-27'),
(27, 'MIKHA-002', 'Mikha', 'Borrowing', 'Available', 1, '0', '2025-08-27'),
(28, 'MIKHA-003', 'Mikha', 'Borrowing', 'Available', 1, '0', '2025-08-27'),
(29, 'MIKHA-004', 'Mikha', 'Borrowing', 'Available', 1, '0', '2025-08-27'),
(30, 'MIKHA-005', 'Mikha', 'Borrowing', 'Available', 1, '0', '2025-08-27'),
(31, 'MIKHA-006', 'Mikha', 'Borrowing', 'Available', 1, '0', '2025-08-27'),
(32, 'MIKHA-007', 'Mikha', 'Borrowing', 'Available', 1, '0', '2025-08-27'),
(33, 'MIKHA-008', 'Mikha', 'Borrowing', 'Available', 1, '0', '2025-08-27'),
(34, 'MIKHA-009', 'Mikha', 'Borrowing', 'Available', 1, '0', '2025-08-27'),
(35, 'MIKHA-010', 'Mikha', 'Borrowing', 'Available', 1, '0', '2025-08-27'),
(36, 'LADDER-006', 'Ladder', 'Borrowing', 'Available', 1, '0', '2025-08-27'),
(37, 'LADDER-007', 'Ladder', 'Borrowing', 'Available', 1, '0', '2025-08-27'),
(38, 'LADDER-008', 'Ladder', 'Borrowing', 'Available', 1, '0', '2025-08-27'),
(39, 'LADDER-009', 'Ladder', 'Borrowing', 'Available', 1, '0', '2025-08-27'),
(40, 'LADDER-010', 'Ladder', 'Borrowing', 'Available', 1, '0', '2025-08-27'),
(41, 'SUPERLADDER-001', 'Super Ladder', 'Borrowing', 'Available', 1, '0', '2025-08-27'),
(42, 'SUPERLADDER-002', 'Super Ladder', 'Borrowing', 'Borrowed', 1, 'ZXIC0EA08BDB', '2025-08-27'),
(43, 'LADDER-011', 'Ladder', 'Borrowing', 'Available', 1, '', '2025-08-27'),
(44, 'LADDER-012', 'Ladder', 'Borrowing', 'Available', 1, '', '2025-08-27'),
(45, 'LADDER-013', 'Ladder', 'Borrowing', 'Available', 1, '', '2025-08-27'),
(46, 'LADDER-014', 'Ladder', 'Borrowing', 'Available', 1, '', '2025-08-27'),
(47, 'LADDER-015', 'Ladder', 'Borrowing', 'Available', 1, '', '2025-08-27'),
(48, 'SUPERLADER-001', 'Super Lader', 'Borrowing', 'Borrowed', 1, 'ZXIC0EA08ADC', '2025-08-27'),
(49, 'SUPERLADER-002', 'Super Lader', 'Borrowing', 'Borrowed', 1, 'ZXIC0EA08QWE', '2025-08-27'),
(50, 'LADDER-016', 'Ladder', 'Borrowing', 'Available', 1, '', '2025-08-27'),
(51, 'LADDER-017', 'Ladder', 'Borrowing', 'Available', 1, '', '2025-08-27'),
(52, 'LADDER-018', 'Ladder', 'Borrowing', 'Available', 1, '', '2025-08-27'),
(53, 'LADDER-019', 'Ladder', 'Borrowing', 'Available', 1, '', '2025-08-27'),
(54, 'LADDER-020', 'Ladder', 'Borrowing', 'Available', 1, '', '2025-08-27'),
(55, 'OCEAN-001', 'Ocean', 'Deployment', 'Deployed', 1, '', '2025-08-31'),
(56, 'OCEAN-002', 'Ocean', 'Deployment', 'Available', 1, '', '2025-08-31'),
(57, 'OCEAN-003', 'Ocean', 'Deployment', 'Available', 1, '', '2025-08-31'),
(58, 'OCEAN-004', 'Ocean', 'Deployment', 'Available', 1, '', '2025-08-31'),
(59, 'OCEAN-005', 'Ocean', 'Deployment', 'Available', 1, '', '2025-08-31'),
(60, 'TESTER-001', 'Tester', 'Borrowing', 'Available', 1, '', '2025-09-03'),
(61, 'TESTER-002', 'Tester', 'Borrowing', 'Available', 1, '', '2025-09-03'),
(62, 'TESTER-003', 'Tester', 'Borrowing', 'Available', 1, '', '2025-09-03'),
(63, 'TESTER-004', 'Tester', 'Borrowing', 'Available', 1, '', '2025-09-03'),
(64, 'TESTER-005', 'Tester', 'Borrowing', 'Available', 1, '', '2025-09-03'),
(65, 'WIRE-001', 'Wire', 'Borrowing', 'Available', 1, '', '2025-09-04'),
(66, 'WIRE-002', 'Wire', 'Borrowing', 'Available', 1, '', '2025-09-04'),
(67, 'WIRE-003', 'Wire', 'Borrowing', 'Available', 1, '', '2025-09-04'),
(68, 'WIRE-004', 'Wire', 'Borrowing', 'Available', 1, '', '2025-09-04'),
(69, 'WIRE-005', 'Wire', 'Borrowing', 'Available', 1, '', '2025-09-04'),
(70, 'COPPERWIRES-001', 'copper wires', 'Borrowing', 'Available', 1, '', '0000-00-00'),
(71, 'LADDER-021', 'Ladder', 'Borrowing', 'Available', 1, '', '2025-09-05'),
(72, 'LADDER-022', 'Ladder', 'Borrowing', 'Available', 1, '', '2025-09-05'),
(73, 'LADDER-023', 'Ladder', 'Borrowing', 'Available', 1, '', '2025-09-05');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_asset_status`
--

CREATE TABLE `tbl_asset_status` (
  `a_id` int(50) NOT NULL,
  `a_ref_no` varchar(200) NOT NULL,
  `a_name` varchar(200) NOT NULL,
  `tech_name` varchar(200) NOT NULL,
  `tech_id` int(50) NOT NULL,
  `a_serial_no` varchar(200) NOT NULL,
  `a_date` date NOT NULL,
  `a_return_date` date NOT NULL,
  `a_status` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_asset_status`
--

INSERT INTO `tbl_asset_status` (`a_id`, `a_ref_no`, `a_name`, `tech_name`, `tech_id`, `a_serial_no`, `a_date`, `a_return_date`, `a_status`) VALUES
(1, 'LADDER-003', 'Ladder', '', 0, '0', '2025-08-31', '2025-09-01', 'Returned'),
(2, 'LADDER-004', 'Ladder', 'Tatyana Hayden', 37, '0', '2025-08-31', '2025-09-01', 'Returned'),
(3, 'OCEAN-001', 'Ocean', 'Zia Jackson', 42, '', '2025-08-31', '0000-00-00', 'Deployed'),
(4, 'SUPERLADDER-002', 'Super Ladder', 'tech admin', 77, 'ZXIC0EA08BDB', '2025-08-31', '2025-09-01', 'Returned'),
(5, 'LADDER-003', 'Ladder', 'tech admin', 77, '0', '2025-09-01', '0000-00-00', 'Borrowed'),
(6, 'LADDER-004', 'Ladder', 'tech admin', 77, '0', '2025-09-01', '0000-00-00', 'Borrowed'),
(7, 'SUPERLADER-002', 'Super Lader', 'tech admin', 77, 'ZXIC0EA08QWE', '2025-09-01', '0000-00-00', 'Borrowed'),
(8, 'TEST-001', 'Test', 'tech admin', 77, '12345', '2025-09-01', '0000-00-00', 'Borrowed'),
(9, 'SUPERMODEM-001', 'Super Modem', 'Tatyana Hayden', 37, '0', '2025-09-03', '0000-00-00', 'Deployed'),
(10, 'TESTER-001', 'Tester', 'Tatyana Hayden', 37, '', '2025-09-03', '2025-09-03', 'Returned'),
(11, 'LADDER-001', 'Ladder', 'Tatyana Hayden', 37, '0', '2025-09-04', '0000-00-00', 'Borrowed'),
(12, 'SUPERLADDER-002', 'Super Ladder', 'haahays haha', 65, 'ZXIC0EA08BDB', '2025-09-05', '0000-00-00', 'Borrowed');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_close_regular`
--

CREATE TABLE `tbl_close_regular` (
  `t_ref` varchar(200) NOT NULL,
  `t_aname` varchar(200) NOT NULL,
  `te_technician` varchar(200) NOT NULL,
  `t_subject` varchar(200) NOT NULL,
  `t_status` varchar(200) NOT NULL,
  `t_details` varchar(200) NOT NULL,
  `te_date` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_close_regular`
--

INSERT INTO `tbl_close_regular` (`t_ref`, `t_aname`, `te_technician`, `t_subject`, `t_status`, `t_details`, `te_date`) VALUES
('ref#-22-05-2025-107976', 'Haley Waller', 'haahays haha', 'stoplight', 'closed', 'awawaw', '2025-07-26 22:52:31'),
('ref#-26-07-2025-281372', 'David Burns', 'haahays haha', 'awaw', 'closed', 'awaw', '2025-07-27 00:06:41'),
('ref#-27-07-2025-567344', 'ryan cansancio', 'haahays haha', 'aray koo', 'closed', 'okaay', '2025-07-27 00:14:14'),
('ref#-08-15-2025-358045', 'Pham Hanni', 'haahays haha', 'red light', 'closed', 'shshs', '2025-09-06 03:28:48'),
('ref#-09-29-2025-000348', 'ryan cansancio', 'techsss', 'awawa', 'closed', 'wawaw', '2025-09-29 07:13:36'),
('ref#-09-16-2025-982641', 'ryan cansancio', 'techsss', 'WAWAW', 'closed', 'AWAW', '2025-10-04 09:39:07'),
('ref#-10-04-2025-219683', 'ryan cansancio', 'techsss', 'Moon', 'closed', 'Stars', '2025-10-04 10:01:45'),
('ref#-09-15-2025-193019', 'Pham Hanni', 'techsss', 'awawaw', 'closed', 'awawaw', '2025-10-04 10:32:46'),
('ref#-09-14-2025-851882', 'ryan cansancio', 'techsss', 'awawaw', 'closed', 'awawaw', '2025-10-06 11:30:15'),
('ref#-09-14-2025-242664', 'ryan cansancio', 'techsss', 'awawawa', 'closed', 'wawaw', '2025-10-06 11:33:51'),
('ref#-10-09-2025-505721', 'ryan cansancio', 'tech admin', 'Moon', 'Closed', 'lighters', '2025-10-09 01:42:22'),
('ref#-10-09-2025-999271', 'ryan cansancio', 'tech admin', 'Moon', 'Closed', 'sadasdsad', '2025-10-09 02:06:58');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_close_supp`
--

CREATE TABLE `tbl_close_supp` (
  `s_ref` varchar(200) NOT NULL,
  `c_id` int(50) NOT NULL,
  `te_technician` varchar(200) NOT NULL,
  `c_fname` varchar(200) NOT NULL,
  `c_lname` varchar(200) NOT NULL,
  `s_subject` varchar(200) NOT NULL,
  `s_message` varchar(200) NOT NULL,
  `s_status` varchar(200) NOT NULL,
  `s_date` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_close_supp`
--

INSERT INTO `tbl_close_supp` (`s_ref`, `c_id`, `te_technician`, `c_fname`, `c_lname`, `s_subject`, `s_message`, `s_status`, `s_date`) VALUES
('ref#-04-09-2025-312296', 29, 'haahays haha', 'Lyle', 'Barr', 'SLOW', 'testing para sabado', 'closed', '2025-09-06 01:42:47'),
('ref#-02-10-2025-538165', 33, 'techsss', 'ryan', 'cansancio', 'awww', 'wawawaw', 'closed', '2025-10-02 08:24:10'),
('ref#-04-10-2025-209485', 33, 'techsss', 'ryan', 'cansancio', 'Moon', 'Stars', 'closed', '2025-10-04 10:01:41'),
('ref#-04-10-2025-245305', 33, 'techsss', 'ryan', 'cansancio', 'Moon', 'Moon', 'closed', '2025-10-04 10:15:26'),
('ref#-02-10-2025-376857', 33, 'techsss', 'ryan', 'cansancio', 'awaw', 'awawaw', 'closed', '2025-10-04 10:34:00'),
('ref#-05-09-2025-457302', 29, 'techsss', 'Lyle', 'Barr', 'Faster', 'hjfyujyjy', 'closed', '2025-10-06 11:34:29'),
('ref#-02-10-2025-771863', 33, 'techsss', 'ryan', 'cansancio', 'aawawaw', 'waawaw', 'closed', '2025-10-06 11:48:22'),
('ref#-06-10-2025-366867', 33, 'techsss', 'ryan', 'cansancio', 'akoako', 'akoako', 'closed', '2025-10-06 12:08:38'),
('ref#-04-09-2025-511310', 29, 'techsss', 'Lyle', 'Barr', 'kapoy', 'fdvdfsgdrg', 'closed', '2025-10-08 18:08:36'),
('ref#-08-10-2025-253782', 33, 'tech admin', '', '', 'okssss', 'paba', 'Closed', '2025-10-09 01:44:26');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_customer`
--

CREATE TABLE `tbl_customer` (
  `c_id` int(50) NOT NULL,
  `c_fname` varchar(200) NOT NULL,
  `c_lname` varchar(200) NOT NULL,
  `c_purok` varchar(200) NOT NULL,
  `c_barangay` varchar(200) NOT NULL,
  `c_contact` varchar(100) NOT NULL,
  `c_email` varchar(200) NOT NULL,
  `c_date` date NOT NULL,
  `c_napname` varchar(200) NOT NULL,
  `c_napport` int(50) NOT NULL,
  `c_macaddress` varchar(200) NOT NULL,
  `c_status` varchar(200) NOT NULL,
  `c_plan` varchar(200) NOT NULL,
  `c_equipment` varchar(200) NOT NULL,
  `c_account_no` int(100) NOT NULL,
  `c_coordinates` varchar(200) NOT NULL,
  `c_balance` int(50) NOT NULL,
  `c_startdate` varchar(100) NOT NULL,
  `c_nextdue` varchar(100) NOT NULL,
  `c_lastdue` varchar(100) NOT NULL,
  `c_nextbill` varchar(100) NOT NULL,
  `c_billstatus` varchar(100) NOT NULL,
  `c_advancedays` varchar(200) NOT NULL,
  `c_advance` int(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_customer`
--

INSERT INTO `tbl_customer` (`c_id`, `c_fname`, `c_lname`, `c_purok`, `c_barangay`, `c_contact`, `c_email`, `c_date`, `c_napname`, `c_napport`, `c_macaddress`, `c_status`, `c_plan`, `c_equipment`, `c_account_no`, `c_coordinates`, `c_balance`, `c_startdate`, `c_nextdue`, `c_lastdue`, `c_nextbill`, `c_billstatus`, `c_advancedays`, `c_advance`) VALUES
(29, 'Lyle', 'Barr', 'Lance Heath', 'Deleniti dolore est', '25690765564', 'moluxonu@mailinator.com', '2025-05-24', 'Lp1 Np8', 5, 'Aconsecteturerror', 'Active', 'Plan 2500', 'Customer-Owned', 69037768, 'Reprehenderit atque', -10000, '2025-08-03', '2025-11-04', '2025-10-04', '2025-11-04', 'Active', '0', 0),
(30, 'David', 'Burns', 'Maris Ferguson', 'Aut nostrud veniam', '77445677712', 'demarexe@mailinator.com', '2025-05-24', 'Lp1 Np5', 7, 'AbNamomnisquisin123', 'Active', 'Plan 999', 'ISP-Provided Modem/Router', 85849371, '14.23245,562.67523', -1000, '2025-08-03', '2025-11-04', '2025-10-04', '2025-11-04', 'Active', '0', 0),
(31, 'Eve', 'Rosa', 'Aileen Colon', 'In et ut dolorem max', '802', 'darywyje@mailinator.com', '2025-05-24', 'Lp1 Np6', 4, 'PorroNamullamcosi', 'Active', 'Plan 1299', 'ISP-Provided Modem/Router', 52111545, 'Quasi a recusandae', 0, '2025-08-03', '2025-11-04', '2025-10-04', '2025-11-04', 'Active', '0', 0),
(32, 'John William', 'Mayormita', 'Purok Tambis', 'Banhigan', '09394578940', 'demarexe@mailinator.com', '2025-07-20', 'Lp1 Np2', 3, 'testing', 'Active', 'Plan 1499', 'ISP-Provided Modem/Router', 59993635, 'Reprehenderitatque', 1, '2025-08-06', '2025-11-07', '2025-10-07', '2025-11-07', 'Active', '0', 0),
(33, 'ryan', 'cansancio', 'tambis', 'ward', '0900909099', 'ryancansancio7@gmail.com', '2025-07-26', 'Lp1 Np6', 5, 'ward', 'Active', 'Plan 2500', 'ISP-Provided Modem/Router', 83775848, 'awawawaw', 500, '2025-08-03', '2025-11-04', '2025-10-04', '2025-11-04', 'Active', '0', 0),
(34, 'Mikha', 'Lim', 'Purok Wildflower', 'Pakigne', '09560390918', 'williammayormita69@gmail.com', '2025-08-14', 'Lp1 Np1', 4, 'Amzxksdn', 'Active', 'Plan 799', 'Customer-Owned', 31010041, '142.342.234.234', 1001, '2025-08-15', '2025-10-16', '2025-09-15', '2025-10-08', 'Active', '8', 0),
(35, 'Pham', 'Hanni', 'Curvada', 'Banhigan', '092345678918', 'williammayormita69@gmail.com', '2025-08-14', 'Lp1 Np1', 6, 'ahsshAJAJSDB', 'Active', 'Plan 1799', 'ISP-Provided Modem/Router', 48926186, '142.342.234.234', 201, '2025-08-15', '2025-10-16', '2025-09-15', '2025-10-08', 'Active', '8', 0),
(36, 'cy', 'xai', 'Purok Wildflower', 'Pakigne', '09999999999', 'xaicy@gmail.com', '2025-09-05', 'Lp1 Np1', 1, 'mingla', 'Active', 'Plan 1499', 'ISP-Provided Modem/Router', 66686351, '142.342.234.234', 0, '', '', '', '', '', '', 0);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_customer_ticket`
--

CREATE TABLE `tbl_customer_ticket` (
  `s_ref` varchar(200) NOT NULL,
  `c_id` int(50) NOT NULL,
  `c_fname` varchar(200) NOT NULL,
  `c_lname` varchar(200) NOT NULL,
  `s_subject` varchar(200) NOT NULL,
  `s_message` varchar(200) NOT NULL,
  `s_status` varchar(200) NOT NULL,
  `s_remarks` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_customer_ticket`
--

INSERT INTO `tbl_customer_ticket` (`s_ref`, `c_id`, `c_fname`, `c_lname`, `s_subject`, `s_message`, `s_status`, `s_remarks`) VALUES
('ref#-23-05-2025-511113', 9, 'Latifah', 'Sims', 'redlight', 'okaay kaayo', 'Approved', ''),
('ref#-23-05-2025-565607', 9, 'Latifah', 'Sims', 'stoplight', 'bugnaw', 'Approved', ''),
('ref#-23-05-2025-627429', 11, 'Lunea', 'Mendez', 'redlight', 'no net', 'Approved', ''),
('ref#-23-05-2025-758042', 9, 'Latifah', 'Sims', 'stop', 'okay nako bahala nani', 'Approved', ''),
('ref#-23-05-2025-884487', 9, 'Latifah', 'Sims', 'okaay', 'goods', 'Rejected', ''),
('ref#-23-05-2025-331671', 11, 'Lunea', 'Mendez', 'okaay kaayo', 'natuk an ko', 'Rejected', ''),
('ref#-23-05-2025-716828', 9, 'Latifah', 'Sims', 'okaay rako', 'bantay btw', 'Rejected', ''),
('ref#-23-05-2025-951343', 9, 'Latifah', 'Sims', 'okaay kaayo', 'bugnaw', 'Approved', ''),
('ref#-23-05-2025-803167', 9, 'Latifah', 'Sims', 'qpal', 'okaay', 'Approved', ''),
('ref#-27-06-2025-928737', 1, 'awawawa', 'wawawawaw', 'test', 'testing purposes', 'Approved', ''),
('ref#-27-06-2025-477366', 1, 'awawawa', 'wawawawaw', 'test', 'for testing', 'Approved', ''),
('ref#-27-06-2025-980002', 1, 'awawawa', 'wawawawaw', 'test test', 'for testing purposes', 'Approved', ''),
('ref#-23-08-2025-994669', 35, 'Pham', 'Hanni', 'ambot', 'ambot', 'Approved', ''),
('ref#-04-09-2025-319876', 29, 'Lyle', 'Barr', 'kapoy', 'hahahah', 'Approved', ''),
('ref#-04-09-2025-511310', 29, 'Lyle', 'Barr', 'kapoy', 'fdvdfsgdrg', 'Approved', ''),
('ref#-04-09-2025-513611', 0, '', '', 'tests', 'dong awa', 'Approved', ''),
('ref#-04-09-2025-312296', 29, 'Lyle', 'Barr', 'SLOW', 'testing para sabado', 'Approved', ''),
('ref#-04-09-2025-682131', 29, 'Lyle', 'Barr', 'Faster', 'jdknadk', 'Approved', ''),
('ref#-05-09-2025-457302', 29, 'Lyle', 'Barr', 'Faster', 'hjfyujyjy', 'Approved', ''),
('ref#-05-09-2025-679738', 34, 'Mikha', 'Lim', 'kalipay', 'testing testing', 'Approved', ''),
('ref#-05-09-2025-980166', 34, 'Mikha', 'Lim', 'best', 'don\'t forgive me', 'Approved', ''),
('ref#-05-09-2025-120710', 34, 'Mikha', 'Lim', 'last summer', 'testing', 'Approved', ''),
('ref#-05-09-2025-901988', 29, 'Lyle', 'Barr', 'MAMACITA', 'promise', 'Approved', ''),
('ref#-05-09-2025-321543', 34, 'Mikha', 'Lim', 'california', 'testing', 'Approved', ''),
('ref#-05-09-2025-199679', 34, 'Mikha', 'Lim', 'waragudt', 'dahsedajqwerikqeuw', 'Approved', ''),
('ref#-05-09-2025-829025', 34, 'Mikha', 'Lim', 'hahaha', 'fwf', 'Rejected', ''),
('ref#-14-09-2025-177742', 30, 'David', 'Burns', 'goods', 'awawwawaw', 'Approved', ''),
('ref#-29-09-2025-907359', 33, 'ryan', 'cansancio', 'awawaw', 'awawaw', 'Rejected', 'okaay pako'),
('ref#-29-09-2025-383494', 34, 'Mikha', 'Lim', 'wawawaw', 'awawaw', 'Approved', ''),
('ref#-29-09-2025-563914', 33, 'ryan', 'cansancio', 'awawaw', 'awawaw', 'Declined', 'awawa'),
('ref#-02-10-2025-538165', 33, 'ryan', 'cansancio', 'awww', 'wawawaw', 'Approved', ''),
('ref#-02-10-2025-771863', 33, 'ryan', 'cansancio', 'aawawaw', 'waawaw', 'Approved', ''),
('ref#-02-10-2025-376857', 33, 'ryan', 'cansancio', 'awaw', 'awawaw', 'Approved', ''),
('ref#-02-10-2025-459426', 33, 'ryan', 'cansancio', 'aww', 'wawa', 'Declined', 'paksit'),
('ref#-02-10-2025-440509', 34, 'Mikha', 'Lim', 'no internet', 'bad weather', 'Declined', 'no valid information'),
('ref#-04-10-2025-209485', 33, 'ryan', 'cansancio', 'Moon', 'Stars', 'Approved', ''),
('ref#-04-10-2025-245305', 33, 'ryan', 'cansancio', 'Moon', 'Moon', 'Approved', ''),
('ref#-06-10-2025-366867', 33, 'ryan', 'cansancio', 'akoako', 'akoako', 'Approved', ''),
('ref#-08-10-2025-253782', 33, 'ryan', 'cansancio', 'okssss', 'paba', 'Approved', '');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_logs`
--

CREATE TABLE `tbl_logs` (
  `l_stamp` varchar(200) NOT NULL,
  `l_type` varchar(200) NOT NULL,
  `l_description` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_logs`
--

INSERT INTO `tbl_logs` (`l_stamp`, `l_type`, `l_description`) VALUES
('2025-05-23 12:03:03', 'Staff Zeph Patel', 'Staff Zeph Patel archived ticket ref#-22-05-2025-107976'),
('2025-05-23 12:07:17', 'Technician Joelle Graves', 'Unarchived regular ticket ref#ref#-22-05-2025-107976'),
('2025-05-23 12:31:04', 'Technician Joelle Graves', 'Archived regular ticket ref#ref#-22-05-2025-107976'),
('2025-05-23 12:31:19', 'Technician Joelle Graves', 'Unarchived regular ticket ref#ref#-22-05-2025-107976'),
('2025-05-23 12:33:03', 'Technician Joelle Graves', 'Archived support ticket ref#ref#-22-05-2025-483623'),
('2025-05-23 13:39:02', 'Ann Daniel', 'has successfully logged in'),
('2025-05-23 13:47:36', 'customer Ann Daniel', 'created ticket ref#-23-05-2025-114488'),
('2025-05-23 13:48:45', 'customer Ann Daniel', 'archived ticket ref#-23-05-2025-114488'),
('2025-05-23 13:49:21', 'customer Ann Daniel', 'unarchived ticket ref#-23-05-2025-114488'),
('2025-05-23 13:53:49', 'customer Ann Daniel', 'archived ticket ref#-23-05-2025-114488'),
('2025-05-23 13:55:54', 'customer Ann Daniel', 'unarchived ticket ref#-23-05-2025-114488'),
('2025-05-23 13:57:34', 'Staff Aiah Love', 'Staff Aiah has successfully logged in'),
('2025-05-23 14:38:59', 'Latifah Sims', 'has successfully logged in'),
('2025-05-23 14:40:26', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-05-23 15:36:19', 'Latifah Sims', 'has successfully logged in'),
('2025-05-23 15:36:41', 'customer Latifah Sims', 'archived ticket ref#-22-05-2025-483623'),
('2025-05-23 16:41:02', 'customer Latifah Sims', 'archived ticket ref#-22-05-2025-261353'),
('2025-05-23 17:10:03', 'Latifah Sims', 'has successfully logged in'),
('2025-05-23 20:15:44', 'Latifah Sims', 'has successfully logged in'),
('2025-05-23 20:16:00', 'customer Latifah Sims', 'created ticket ref#-23-05-2025-511113'),
('2025-05-23 20:39:21', 'Staff awaww', 'Staff awaww approved customer ticket ref#-23-05-2025-511113 for customer Latifah Sims'),
('2025-05-23 20:47:30', 'Latifah Sims', 'has successfully logged in'),
('2025-05-23 20:47:46', 'customer Latifah Sims', 'created ticket ref#-23-05-2025-565607'),
('2025-05-23 20:48:19', 'Staff awaww', 'Staff awaww approved customer ticket ref#-23-05-2025-565607 for customer Latifah Sims'),
('2025-05-23 20:48:56', 'Latifah Sims', 'has successfully logged in'),
('2025-05-23 21:28:09', 'Lunea Mendez', 'has successfully logged in'),
('2025-05-23 21:28:22', 'customer Lunea Mendez', 'created ticket ref#-23-05-2025-627429'),
('2025-05-23 21:29:35', 'Staff awaww', 'Staff awaww approved customer ticket ref#-23-05-2025-627429 for customer Lunea Mendez'),
('2025-05-23 21:29:54', 'Lunea Mendez', 'has successfully logged in'),
('2025-05-23 22:19:51', 'Latifah Sims', 'has successfully logged in'),
('2025-05-23 22:20:08', 'customer Latifah Sims', 'created ticket ref#-23-05-2025-758042'),
('2025-05-23 22:20:32', 'Staff awaww', 'Staff awaww approved customer ticket ref#-23-05-2025-758042 for customer Latifah Sims'),
('2025-05-23 22:44:59', 'Latifah Sims', 'has successfully logged in'),
('2025-05-23 22:55:01', 'Latifah Sims', 'has successfully logged in'),
('2025-05-23 22:59:18', 'Latifah Sims', 'has successfully logged in'),
('2025-05-23 22:59:48', 'customer Latifah Sims', 'created ticket ref#-23-05-2025-884487'),
('2025-05-23 23:15:35', 'Staff awaww awawaww', 'Staff awaww awawaww rejected customer ticket ref#-23-05-2025-884487 for customer Latifah Sims'),
('2025-05-23 23:17:28', 'Latifah Sims', 'has successfully logged in'),
('2025-05-23 23:37:48', 'Latifah Sims', 'has successfully logged in'),
('2025-05-23 23:37:59', 'Lunea Mendez', 'has successfully logged in'),
('2025-05-23 23:38:24', 'customer Lunea Mendez', 'created ticket ref#-23-05-2025-331671'),
('2025-05-23 23:38:45', 'Staff awaww awawaww', 'Staff awaww awawaww rejected customer ticket ref#-23-05-2025-331671 for customer Lunea Mendez'),
('2025-05-23 23:39:05', 'Lunea Mendez', 'has successfully logged in'),
('2025-05-23 23:45:02', 'Latifah Sims', 'has successfully logged in'),
('2025-05-23 23:45:43', 'customer Latifah Sims', 'edited ticket ref#-22-05-2025-104028'),
('2025-05-23 23:45:48', 'customer Latifah Sims', 'archived ticket ref#-22-05-2025-104028'),
('2025-05-24 00:50:36', 'customer Latifah Sims', 'unarchived ticket ref#-22-05-2025-483623'),
('2025-05-24 00:50:43', 'customer Latifah Sims', 'unarchived ticket ref#-22-05-2025-261353'),
('2025-05-24 00:50:50', 'customer Latifah Sims', 'unarchived ticket ref#-22-05-2025-104028'),
('2025-05-24 01:01:15', 'customer Latifah Sims', 'archived ticket ref#-22-05-2025-483623'),
('2025-05-24 01:01:38', 'customer Latifah Sims', 'unarchived ticket ref#-22-05-2025-483623'),
('2025-05-24 01:07:02', 'customer Latifah Sims', 'archived ticket ref#-22-05-2025-261353'),
('2025-05-24 01:07:07', 'customer Latifah Sims', 'unarchived ticket ref#-22-05-2025-261353'),
('2025-05-24 01:07:15', 'customer Latifah Sims', 'archived ticket ref#-22-05-2025-483623'),
('2025-05-24 01:07:23', 'customer Latifah Sims', 'deleted ticket ref#-22-05-2025-483623'),
('2025-05-24 01:23:28', 'Latifah Sims', 'has successfully logged in'),
('2025-05-24 01:23:43', 'customer Latifah Sims', 'created ticket ref#-23-05-2025-716828'),
('2025-05-24 01:24:36', 'Staff awaww awawaww', 'Staff awaww awawaww rejected customer ticket ref#-23-05-2025-716828 for customer Latifah Sims with remarks: tarunga na imong pag report qpal kaba'),
('2025-05-24 01:24:50', 'Latifah Sims', 'has successfully logged in'),
('2025-05-24 01:32:52', 'Latifah Sims', 'has successfully logged in'),
('2025-05-24 01:33:00', 'customer Latifah Sims', 'archived ticket ref#-22-05-2025-261353'),
('2025-05-24 01:34:22', 'customer Latifah Sims', 'created ticket ref#-23-05-2025-951343'),
('2025-05-24 01:34:44', 'Staff awaww', 'Staff awaww approved customer ticket ref#-23-05-2025-951343 for customer Latifah Sims'),
('2025-05-24 01:37:09', 'Latifah Sims', 'has successfully logged in'),
('2025-05-24 01:37:22', 'customer Latifah Sims', 'created ticket ref#-23-05-2025-803167'),
('2025-05-24 01:38:04', 'Staff awaww', 'Staff awaww approved customer ticket ref#-23-05-2025-803167 for customer Latifah Sims'),
('2025-05-24 01:38:17', 'Latifah Sims', 'has successfully logged in'),
('2025-05-24 01:51:54', 'Staff awaww awawaww', 'Staff awaww awawaww edited ticket ref#-22-05-2025-107976 subject'),
('2025-06-27 22:53:50', 'awawawa wawawawaw', 'has successfully logged in'),
('2025-06-27 22:55:58', 'customer awawawa wawawawaw', 'created ticket ref#-27-06-2025-928737'),
('2025-06-27 23:08:24', 'Staff Ray Quan', 'Staff Ray Quan approved customer ticket ref#-27-06-2025-928737 for customer awawawa wawawawaw'),
('2025-06-27 23:09:50', 'awawawa wawawawaw', 'has successfully logged in'),
('2025-06-27 23:10:19', 'customer awawawa wawawawaw', 'created ticket ref#-27-06-2025-477366'),
('2025-06-27 23:25:50', 'Staff Ray Quan', 'Staff Ray Quan approved customer ticket ref#-27-06-2025-477366 for customer awawawa wawawawaw'),
('2025-06-27 23:26:31', 'awawawa wawawawaw', 'has successfully logged in'),
('2025-06-27 23:27:04', 'customer awawawa wawawawaw', 'created ticket ref#-27-06-2025-980002'),
('2025-06-27 23:45:21', 'awawawa wawawawaw', 'has successfully logged in'),
('2025-06-28 00:00:20', 'Lunea Mendez', 'has successfully logged in'),
('2025-06-30 13:11:21', '', 'Admin xie123 deleted closed support ticket ID ref#-18-05-2025-189091'),
('2025-06-30 13:11:24', '', 'Admin xie123 deleted closed support ticket ID ref#-07-04-2025-484929'),
('2025-06-30 13:11:26', '', 'Admin xie123 deleted closed support ticket ID ref#-19-05-2025-710976'),
('2025-07-16 23:12:58', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-07-17 22:07:45', 'Eve Rosa', 'has successfully logged in'),
('2025-07-17 22:13:02', 'Eve Rosa', 'has successfully logged in'),
('2025-07-17 22:43:48', 'Eve Rosa', 'has successfully logged in'),
('2025-07-17 23:26:05', 'Eve Rosa', 'has successfully logged in'),
('2025-07-20 23:43:22', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-07-26 22:51:40', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-07-26 22:51:56', 'Staff awaww awawaww', 'Assigned ticket ref#-22-05-2025-107976 to technician tech12 by awaww awawaww (Type: regular)'),
('2025-07-26 22:52:31', 'Technician haahays haha', 'Ticket ref#-22-05-2025-107976 closed by technician haahays haha (Type: regular)'),
('2025-07-26 23:42:23', 'Staff awaww awawaww', 'Created ticket #ref#-26-07-2025-281372 for customer David Burns'),
('2025-07-26 23:42:30', 'Staff awaww awawaww', 'Assigned ticket ref#-26-07-2025-281372 to technician tech12 by awaww awawaww (Type: regular)'),
('2025-07-27 00:06:41', 'Technician haahays haha', 'Ticket ref#-26-07-2025-281372 closed by technician haahays haha (Type: regular); Email failed: Customer email not found.'),
('2025-07-27 00:13:45', 'Staff awaww awawaww', 'Created ticket #ref#-27-07-2025-567344 for customer ryan cansancio'),
('2025-07-27 00:13:53', 'Staff awaww awawaww', 'Assigned ticket ref#-27-07-2025-567344 to technician tech12 by awaww awawaww (Type: regular)'),
('2025-07-27 00:14:18', 'Technician haahays haha', 'Ticket ref#-27-07-2025-567344 closed by technician haahays haha (Type: regular)'),
('2025-08-03 14:11:30', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-08-03 16:23:35', 'Lyle Barr', 'has successfully logged in'),
('2025-08-03 17:09:21', 'Lyle Barr', 'has successfully logged in'),
('2025-08-03 18:06:28', 'Lyle Barr', 'has successfully logged in'),
('2025-08-03 18:23:02', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-08-05 23:15:12', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-08-06 02:38:44', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-08-08 12:47:12', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-08-08 13:59:58', 'Lyle Barr', 'has successfully logged in'),
('2025-08-08 14:08:59', 'Lyle Barr', 'has successfully logged in'),
('2025-08-08 14:39:28', 'Lyle Barr', 'has successfully logged in'),
('2025-08-08 15:32:00', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-08-14 12:34:44', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-08-14 22:55:47', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-08-14 23:35:29', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-08-15 00:40:19', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-08-15 00:57:45', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-08-15 01:26:17', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-08-15 09:07:30', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-08-15 09:21:03', 'Staff xiexie Ryan', 'Created ticket #ref#-08-15-2025-468031 for customer Mikha Lim'),
('2025-08-15 09:21:25', 'Staff xiexie Ryan', 'Staff xiexie Ryan edited ticket ref#-08-15-2025-468031 subject'),
('2025-08-15 09:43:58', 'Staff xiexie Ryan', 'Staff xiexie Ryan archived ticket ref#-08-15-2025-468031'),
('2025-08-15 09:44:26', 'Staff xiexie Ryan', 'Staff xiexie Ryan unarchived ticket ref#-08-15-2025-468031'),
('2025-08-15 09:45:25', 'Staff xiexie Ryan', 'Staff xiexie Ryan archived ticket ref#-08-15-2025-468031'),
('2025-08-15 09:53:52', 'Staff xiexie Ryan', 'Staff xiexie Ryan unarchived ticket ref#-08-15-2025-468031'),
('2025-08-15 09:55:12', 'Staff xiexie Ryan', 'Staff xiexie Ryan archived ticket ref#-08-15-2025-468031'),
('2025-08-15 09:58:03', 'Staff xiexie Ryan', 'Staff xiexie Ryan unarchived ticket ref#-08-15-2025-468031'),
('2025-08-15 09:59:38', 'Staff xiexie Ryan', 'Staff xiexie Ryan archived ticket ref#-08-15-2025-468031'),
('2025-08-15 10:00:31', 'Staff xiexie Ryan', 'Staff xiexie Ryan unarchived ticket ref#-08-15-2025-468031'),
('2025-08-15 10:07:00', 'Staff xiexie Ryan', 'Created ticket #ref#-08-15-2025-152225 for customer John William Mayormita'),
('2025-08-15 10:08:22', 'Staff xiexie Ryan', 'Staff xiexie Ryan archived ticket ref#-08-15-2025-468031'),
('2025-08-15 10:08:24', 'Staff xiexie Ryan', 'Staff xiexie Ryan archived ticket ref#-08-15-2025-152225'),
('2025-08-15 10:08:29', 'Staff xiexie Ryan', 'Staff xiexie Ryan unarchived ticket ref#-08-15-2025-468031'),
('2025-08-15 10:08:33', 'Staff xiexie Ryan', 'Staff xiexie Ryan archived ticket ref#-08-15-2025-468031'),
('2025-08-15 10:08:39', 'Staff xiexie Ryan', 'Staff xiexie Ryan unarchived ticket ref#-08-15-2025-468031'),
('2025-08-15 10:08:44', 'Staff xiexie Ryan', 'Staff xiexie Ryan archived ticket ref#-08-15-2025-468031'),
('2025-08-15 10:08:49', 'Staff xiexie Ryan', 'Staff xiexie Ryan unarchived ticket ref#-08-15-2025-468031'),
('2025-08-15 10:08:55', 'Staff xiexie Ryan', 'Staff xiexie Ryan archived ticket ref#-08-15-2025-468031'),
('2025-08-15 10:24:07', 'Staff xiexie Ryan', 'Staff xiexie Ryan unarchived ticket ref#-08-15-2025-468031'),
('2025-08-15 10:24:13', 'Staff xiexie Ryan', 'Staff xiexie Ryan unarchived ticket ref#-08-15-2025-152225'),
('2025-08-15 10:25:13', 'Staff xiexie Ryan', 'Staff xiexie Ryan archived ticket ref#-08-15-2025-468031'),
('2025-08-15 10:25:26', 'Staff xiexie Ryan', 'Staff xiexie Ryan unarchived ticket ref#-08-15-2025-468031'),
('2025-08-15 10:25:45', 'Staff xiexie Ryan', 'Created ticket #ref#-08-15-2025-358045 for customer Pham Hanni'),
('2025-08-15 10:25:52', 'Staff xiexie Ryan', 'Staff xiexie Ryan archived ticket ref#-08-15-2025-358045'),
('2025-08-15 10:26:00', 'Staff xiexie Ryan', 'Staff xiexie Ryan unarchived ticket ref#-08-15-2025-358045'),
('2025-08-23 15:18:39', 'Pham Hanni', 'has successfully logged in'),
('2025-08-23 15:19:00', 'customer Pham Hanni', 'created ticket ref#-23-08-2025-994669'),
('2025-08-23 16:14:57', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-08-23 18:09:02', 'David Burns', 'has successfully logged in'),
('2025-08-25 14:17:35', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-08-25 18:04:13', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-08-25 19:39:35', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-08-25 20:37:07', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-08-27 15:04:50', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-08-27 16:24:16', 'Staff ryanryan', 'Staff ryanryan added 5 asset(s) named \'Mikha\''),
('2025-08-27 16:43:20', 'Staff ryanryan', 'Staff ryanryan added 5 asset(s) named \'Ladder\''),
('2025-08-27 16:45:02', 'Staff ryanryan', 'Staff ryanryan added 1 asset(s) named \'Super Ladder\''),
('2025-08-27 16:57:59', 'Staff ryanryan', 'Staff ryanryan added 1 asset(s) named \'Super Ladder\''),
('2025-08-27 18:32:36', 'Staff ryanryan', 'Staff ryanryan added 5 asset(s) named \'Ladder\''),
('2025-08-27 18:32:57', 'Staff ryanryan', 'Staff ryanryan added 1 asset(s) named \'Super Lader\''),
('2025-08-27 18:34:09', 'Staff ryanryan', 'Staff ryanryan added 1 asset(s) named \'Super Lader\''),
('2025-08-27 18:45:48', 'Staff ryanryan', 'Staff ryanryan added 5 asset(s) named \'Ladder\''),
('2025-08-31 13:52:49', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-08-31 15:38:34', 'Staff ryanryan', 'Staff ryanryan borrowed 1 asset(s) to technician Zia Jackson (ID: 42)'),
('2025-08-31 16:47:33', 'Staff ryanryan', 'Staff ryanryan borrowed 1 asset(s) to technician Kolehe Kai (ID: 41)'),
('2025-08-31 17:18:06', 'Staff ryanryan', 'Staff ryanryan borrowed 1 asset(s) to technician Pham Hanny (ID: 73)'),
('2025-08-31 18:40:24', 'Staff ryanryan', 'Staff ryanryan borrowed 1 asset(s) to technician Yumi Chan (ID: 45)'),
('2025-08-31 19:47:40', 'Staff ryanryan', 'Staff ryanryan added 5 asset(s) named \'Ocean\''),
('2025-08-31 20:52:19', 'Staff ryanryan', 'Staff ryanryan borrowed 1 asset(s) to technician Tatyana Hayden (ID: 37)'),
('2025-08-31 21:04:18', 'Staff ryanryan', 'Staff ryanryan deployed 1 asset(s) to technician Zia Jackson (ID: 42)'),
('2025-08-31 23:00:53', 'Staff ryanryan', 'Staff ryanryan borrowed 1 asset(s) to technician tech admin (ID: 77)'),
('2025-09-02 01:40:38', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-09-02 04:21:26', 'Staff techsss', 'Staff techsss borrowed 1 asset(s) to technician tech admin (ID: 77)'),
('2025-09-02 04:23:34', 'Staff techsss', 'Staff techsss borrowed 1 asset(s) to technician tech admin (ID: 77)'),
('2025-09-02 04:24:02', 'Staff techsss', 'Staff techsss borrowed 1 asset(s) to technician tech admin (ID: 77)'),
('2025-09-02 04:25:21', 'Staff techsss', 'Staff techsss borrowed 1 asset(s) to technician tech admin (ID: 77)'),
('2025-09-03 18:30:45', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-09-03 19:04:31', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-09-03 19:37:58', 'Staff xie123', 'Staff xie123 deployed 1 asset(s) to technician Tatyana Hayden (ID: 37)'),
('2025-09-03 23:24:28', 'Staff xie123', 'Staff xie123 added 5 asset(s) named \'Tester\''),
('2025-09-03 23:25:01', 'Staff xie123', 'Staff xie123 borrowed 1 asset(s) to technician Tatyana Hayden (ID: 37)'),
('2025-09-03 23:55:19', 'xiexie Ryan', 'has successfully logged in'),
('2025-09-04 00:53:10', 'xiexie Ryan', 'has successfully logged in'),
('2025-09-04 00:53:10', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-09-04 00:53:35', 'xiexie Ryan', 'has successfully logged in'),
('2025-09-04 14:55:46', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-09-04 15:55:09', 'Staff ryanryan', 'Staff ryanryan added 5 asset(s) named \'Wire\''),
('2025-09-04 15:55:25', 'Staff ryanryan', 'Staff ryanryan borrowed 1 asset(s) to technician Tatyana Hayden (ID: 37)'),
('2025-09-04 16:01:53', 'Staff ryanryan', 'Staff ryanryan added 1 asset(s) named \'copper wires\''),
('2025-09-04 23:48:30', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-09-04 23:58:32', 'Lyle Barr', 'has successfully logged in'),
('2025-09-05 00:30:53', 'Lyle Barr', 'has successfully logged in'),
('2025-09-05 00:31:18', 'customer Lyle Barr', 'created ticket ref#-04-09-2025-319876'),
('2025-09-05 00:55:19', 'customer Lyle Barr', 'created ticket ref#-04-09-2025-511310'),
('2025-09-05 01:02:40', 'Staff xiexie Ryan', 'Staff xiexie Ryan approved customer ticket ref#-04-09-2025-511310 for customer Lyle Barr'),
('2025-09-05 01:24:18', 'technician  ', 'created ticket ref#-04-09-2025-513611'),
('2025-09-05 01:38:20', 'Lyle Barr', 'has successfully logged in'),
('2025-09-05 01:42:45', 'Staff xiexie Ryan', 'Staff xiexie Ryan approved customer ticket ref#-04-09-2025-319876 for customer Lyle Barr'),
('2025-09-05 01:46:46', 'Lyle Barr', 'has successfully logged in'),
('2025-09-05 01:48:12', 'Lyle Barr', 'has successfully logged in'),
('2025-09-05 01:48:35', 'customer Lyle Barr', 'created ticket ref#-04-09-2025-312296'),
('2025-09-05 01:49:25', 'Staff xiexie Ryan', 'Staff xiexie Ryan approved customer ticket ref#-04-09-2025-312296 for customer Lyle Barr'),
('2025-09-05 01:55:42', 'Lyle Barr', 'has successfully logged in'),
('2025-09-05 01:55:55', 'customer Lyle Barr', 'created ticket ref#-04-09-2025-682131'),
('2025-09-05 13:45:29', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-09-05 13:56:57', 'haahays haha', 'has successfully logged in'),
('2025-09-05 13:57:17', 'Lyle Barr', 'has successfully logged in'),
('2025-09-05 14:00:08', 'Staff xiexie Ryan', 'Staff xiexie Ryan approved customer ticket ref#-04-09-2025-682131 for customer Lyle Barr'),
('2025-09-05 14:00:23', 'Lyle Barr', 'has successfully logged in'),
('2025-09-05 14:00:50', 'customer Lyle Barr', 'created ticket ref#-05-09-2025-457302'),
('2025-09-05 14:05:22', 'Staff xiexie Ryan', 'Staff xiexie Ryan archived ticket ref#-08-15-2025-468031'),
('2025-09-05 14:06:16', 'Staff xiexie Ryan', 'Staff xiexie Ryan unarchived ticket ref#-08-15-2025-468031'),
('2025-09-05 14:12:03', 'Lyle Barr', 'has successfully logged in'),
('2025-09-05 14:55:04', 'xiexie Ryan', 'has successfully logged in'),
('2025-09-05 14:55:24', 'Lyle Barr', 'has successfully logged in'),
('2025-09-05 14:58:00', 'Staff xiexie Ryan', 'Staff xiexie Ryan approved customer ticket ref#-05-09-2025-457302 for customer Lyle Barr'),
('2025-09-05 14:58:16', 'Lyle Barr', 'has successfully logged in'),
('2025-09-05 14:59:21', 'Mikha Lim', 'has successfully logged in'),
('2025-09-05 15:00:03', 'customer Mikha Lim', 'created ticket ref#-05-09-2025-679738'),
('2025-09-05 15:01:57', 'Staff xiexie Ryan', 'Staff xiexie Ryan approved customer ticket ref#-05-09-2025-679738 for customer Mikha Lim'),
('2025-09-05 15:02:22', 'Staff xiexie Ryan', 'Staff xiexie Ryan approved customer ticket ref#-27-06-2025-980002 for customer awawawa wawawawaw'),
('2025-09-05 15:02:38', 'Staff xiexie Ryan', 'Staff xiexie Ryan approved customer ticket ref#-23-08-2025-994669 for customer Pham Hanni'),
('2025-09-05 15:03:37', 'Staff xiexie Ryan', 'Staff xiexie Ryan approved customer ticket ref#-04-09-2025-513611 for customer  '),
('2025-09-05 15:12:11', 'Mikha Lim', 'has successfully logged in'),
('2025-09-05 15:12:28', 'customer Mikha Lim', 'created ticket ref#-05-09-2025-980166'),
('2025-09-05 15:13:05', 'Staff xiexie Ryan', 'Staff xiexie Ryan approved customer ticket ref#-05-09-2025-980166 for customer Mikha Lim'),
('2025-09-05 15:26:16', 'Mikha Lim', 'has successfully logged in'),
('2025-09-05 15:26:30', 'customer Mikha Lim', 'created ticket ref#-05-09-2025-120710'),
('2025-09-05 15:27:30', 'Staff xiexie Ryan', 'Staff xiexie Ryan approved customer ticket ref#-05-09-2025-120710 for customer Mikha Lim'),
('2025-09-05 15:29:03', 'Lyle Barr', 'has successfully logged in'),
('2025-09-05 15:29:19', 'customer Lyle Barr', 'created ticket ref#-05-09-2025-901988'),
('2025-09-05 15:41:57', 'Mikha Lim', 'has successfully logged in'),
('2025-09-05 15:42:17', 'customer Mikha Lim', 'created ticket ref#-05-09-2025-321543'),
('2025-09-05 15:44:14', 'Staff xiexie Ryan', 'Staff xiexie Ryan approved customer ticket ref#-05-09-2025-321543 for customer Mikha Lim'),
('2025-09-05 15:48:21', 'haahays haha', 'has successfully logged in'),
('2025-09-05 15:56:52', 'Staff tech12', 'Staff tech12 borrowed 1 asset(s) to technician haahays haha (ID: 65)'),
('2025-09-05 15:57:37', 'haahays haha', 'has successfully logged in'),
('2025-09-05 16:01:50', 'xiexie Ryan', 'has successfully logged in'),
('2025-09-05 16:09:25', 'Staff ryanryan', 'Staff ryanryan added 3 asset(s) named \'Ladder\''),
('2025-09-05 16:46:33', 'Staff xiexie Ryan', 'Created ticket #ref#-09-05-2025-590853 for customer cy xai'),
('2025-09-05 17:12:24', 'Mikha Lim', 'has successfully logged in'),
('2025-09-05 17:13:05', 'customer Mikha Lim', 'created ticket ref#-05-09-2025-199679'),
('2025-09-05 17:14:44', 'Staff xiexie Ryan', 'Staff xiexie Ryan approved customer ticket ref#-05-09-2025-199679 for customer Mikha Lim'),
('2025-09-05 17:15:44', 'Mikha Lim', 'has successfully logged in'),
('2025-09-05 17:17:44', 'Mikha Lim', 'has successfully logged in'),
('2025-09-05 17:17:52', 'customer Mikha Lim', 'created ticket ref#-05-09-2025-829025'),
('2025-09-05 17:18:36', 'Staff xiexie Ryan', 'Staff xiexie Ryan rejected customer ticket ref#-05-09-2025-829025 for customer Mikha Lim with remarks: warasadt'),
('2025-09-05 17:19:29', 'Mikha Lim', 'has successfully logged in'),
('2025-09-05 17:59:19', 'haahays haha', 'has successfully logged in'),
('2025-09-05 18:27:43', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-09-05 18:29:17', 'Staff xiexie Ryan', 'Toggled status for technician Kolohe to Available by xiexie Ryan'),
('2025-09-05 18:39:26', 'Staff xiexie Ryan', 'Staff xiexie Ryan archived ticket ref#-08-15-2025-468031'),
('2025-09-05 18:39:47', 'Staff xiexie Ryan', 'Staff xiexie Ryan edited ticket ref#-08-15-2025-152225 ticket details'),
('2025-09-05 18:40:05', 'Staff xiexie Ryan', 'Staff xiexie Ryan unarchived ticket ref#-08-15-2025-468031'),
('2025-09-05 18:40:42', 'Staff xiexie Ryan', 'Created ticket #ref#-09-05-2025-872512 for customer Mikha Lim'),
('2025-09-05 18:41:05', 'Staff xiexie Ryan', 'Created ticket #ref#-09-05-2025-184144 for customer Mikha Lim'),
('2025-09-05 18:41:42', 'Staff xiexie Ryan', 'Toggled status for technician techs to Available by xiexie Ryan'),
('2025-09-05 18:41:42', 'Staff xiexie Ryan', 'Toggled status for technician techs to Unavailable by xiexie Ryan'),
('2025-09-05 18:41:43', 'Staff xiexie Ryan', 'Toggled status for technician techs to Available by xiexie Ryan'),
('2025-09-05 18:41:44', 'Staff xiexie Ryan', 'Toggled status for technician techs to Unavailable by xiexie Ryan'),
('2025-09-05 18:42:07', 'Staff xiexie Ryan', 'Toggled status for technician Xai Cy to Available by xiexie Ryan'),
('2025-09-05 18:42:08', 'Staff xiexie Ryan', 'Toggled status for technician Xai Cy to Unavailable by xiexie Ryan'),
('2025-09-05 18:42:08', 'Staff xiexie Ryan', 'Toggled status for technician Xai Cy to Available by xiexie Ryan'),
('2025-09-05 18:42:13', 'Staff xiexie Ryan', 'Toggled status for technician Xai Cy to Unavailable by xiexie Ryan'),
('2025-09-05 18:42:22', 'Staff xiexie Ryan', 'Toggled status for technician Xai Cy to Available by xiexie Ryan'),
('2025-09-05 18:42:24', 'Staff xiexie Ryan', 'Toggled status for technician Xai Cy to Unavailable by xiexie Ryan'),
('2025-09-05 18:42:26', 'Staff xiexie Ryan', 'Toggled status for technician Xai Cy to Available by xiexie Ryan'),
('2025-09-05 18:43:03', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-09-05 18:43:08', 'Staff awaww awawaww', 'Staff awaww awawaww closed ticket ref#-08-15-2025-468031'),
('2025-09-05 20:32:27', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-09-05 20:36:10', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-08-15-2025-468031'),
('2025-09-05 20:37:48', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-08-15-2025-468031'),
('2025-09-05 21:36:21', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-08-15-2025-468031'),
('2025-09-05 21:45:14', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-08-15-2025-152225'),
('2025-09-05 21:45:34', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-08-15-2025-358045'),
('2025-09-05 21:47:56', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-05-2025-590853'),
('2025-09-05 22:07:24', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-05-2025-872512'),
('2025-09-05 22:28:01', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-05-2025-184144'),
('2025-09-05 22:32:54', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-08-15-2025-468031'),
('2025-09-05 22:33:23', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-08-15-2025-152225'),
('2025-09-05 22:33:27', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-08-15-2025-358045'),
('2025-09-05 22:33:30', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-05-2025-590853'),
('2025-09-05 22:33:32', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-05-2025-872512'),
('2025-09-05 22:33:35', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-05-2025-184144'),
('2025-09-05 23:19:52', 'Staff awaww awawaww', 'Created ticket #ref#-09-05-2025-034537 for customer ryan cansancio'),
('2025-09-05 23:20:06', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-08-15-2025-468031'),
('2025-09-05 23:21:03', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-08-15-2025-152225'),
('2025-09-05 23:21:21', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-08-15-2025-358045'),
('2025-09-05 23:22:13', 'Staff awaww awawaww', 'Created ticket #ref#-09-05-2025-347373 for customer ryan cansancio'),
('2025-09-05 23:22:29', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-05-2025-590853'),
('2025-09-05 23:24:18', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-08-15-2025-468031'),
('2025-09-06 00:04:19', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-08-15-2025-468031'),
('2025-09-06 00:10:28', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-05-2025-872512'),
('2025-09-06 00:27:14', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-05-2025-184144'),
('2025-09-06 00:39:40', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-05-2025-034537'),
('2025-09-06 00:45:35', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-08-15-2025-468031'),
('2025-09-06 00:45:41', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-08-15-2025-468031'),
('2025-09-06 01:00:53', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-05-2025-347373'),
('2025-09-06 01:16:18', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-08-15-2025-152225'),
('2025-09-06 01:32:02', 'Staff awaww awawaww', 'Toggled status for technician Xai Cy to Unavailable by awaww awawaww'),
('2025-09-06 01:32:03', 'Staff awaww awawaww', 'Toggled status for technician Xai Cy to Available by awaww awawaww'),
('2025-09-06 01:42:31', 'Staff awaww awawaww', 'Assigned ticket ref#-04-09-2025-312296 to technician tech12 by awaww awawaww (Type: support)'),
('2025-09-06 01:42:47', 'Technician haahays haha', 'Support ticket ref#-04-09-2025-312296 closed by technician haahays haha'),
('2025-09-06 02:08:20', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-09-06 02:08:25', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-08-15-2025-152225'),
('2025-09-06 02:08:57', 'Staff awaww awawaww', 'Assigned ticket ref#-08-15-2025-152225 to technician tech12 by awaww awawaww (Type: regular)'),
('2025-09-06 02:10:45', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-09-06 02:11:00', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-05-2025-184144'),
('2025-09-06 02:11:06', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-08-15-2025-468031'),
('2025-09-06 02:11:09', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-08-15-2025-152225'),
('2025-09-06 02:11:12', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-08-15-2025-358045'),
('2025-09-06 02:11:15', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-05-2025-590853'),
('2025-09-06 02:11:18', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-05-2025-872512'),
('2025-09-06 02:11:20', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-05-2025-034537'),
('2025-09-06 02:11:23', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-05-2025-347373'),
('2025-09-06 02:13:42', 'Technician haahays haha', 'Ticket ref#-08-15-2025-152225 archived by technician haahays haha (Type: regular)'),
('2025-09-06 02:21:19', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-08-15-2025-468031'),
('2025-09-06 02:33:08', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-08-15-2025-152225'),
('2025-09-06 02:33:30', 'Technician haahays haha', 'Ticket ref#-08-15-2025-152225 archived by technician haahays haha (Type: regular)'),
('2025-09-06 02:44:57', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-08-15-2025-152225'),
('2025-09-06 02:45:06', 'Staff awaww awawaww', 'Staff awaww awawaww closed ticket ref#-08-15-2025-152225'),
('2025-09-06 02:55:14', 'Technician haahays haha', 'Ticket ref#-08-15-2025-152225 archived by technician haahays haha (Type: regular)'),
('2025-09-06 03:01:16', 'Technician haahays haha', 'Ticket ref#-08-15-2025-152225 unarchived by technician haahays haha (Type: regular)'),
('2025-09-06 03:01:22', 'Technician haahays haha', 'Ticket ref#-08-15-2025-152225 unarchived by technician haahays haha (Type: regular)'),
('2025-09-06 03:02:44', 'Staff awaww awawaww', 'Assigned ticket ref#-09-05-2025-590853 to technician tech12 by awaww awawaww (Type: regular)'),
('2025-09-06 03:03:03', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-09-06 03:03:07', 'Staff awaww awawaww', 'Staff awaww awawaww closed ticket ref#-09-05-2025-590853'),
('2025-09-06 03:28:14', 'Staff awaww awawaww', 'Assigned ticket ref#-08-15-2025-358045 to technician tech12 by awaww awawaww (Type: regular)'),
('2025-09-06 03:28:20', 'Staff awaww awawaww', 'Assigned ticket ref#-23-08-2025-994669 to technician tech12 by awaww awawaww (Type: support)'),
('2025-09-06 03:28:48', 'Technician haahays haha', 'Ticket ref#-08-15-2025-358045 closed by technician haahays haha (Type: regular)'),
('2025-09-06 03:29:04', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-09-06 03:58:55', 'Technician haahays haha', 'Ticket ref#-08-15-2025-152225 archived by technician haahays haha (Type: regular)'),
('2025-09-06 03:59:08', 'Technician haahays haha', 'Ticket ref#-09-05-2025-590853 archived by technician haahays haha (Type: regular)'),
('2025-09-06 04:00:47', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-09-06 04:14:46', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-09-06 04:14:59', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-08-15-2025-468031'),
('2025-09-06 04:15:02', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-08-15-2025-152225'),
('2025-09-06 04:15:05', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-05-2025-590853'),
('2025-09-06 04:15:25', 'Staff awaww awawaww', 'Created ticket #ref#-09-06-2025-473597 for customer ryan cansancio'),
('2025-09-06 04:15:43', 'Staff awaww awawaww', 'Created ticket #ref#-09-06-2025-839788 for customer ryan cansancio'),
('2025-09-06 04:15:56', 'Staff awaww awawaww', 'Created ticket #ref#-09-06-2025-170779 for customer ryan cansancio'),
('2025-09-06 04:16:08', 'Staff awaww awawaww', 'Created ticket #ref#-09-06-2025-838631 for customer ryan cansancio'),
('2025-09-06 04:30:41', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-08-15-2025-468031'),
('2025-09-06 04:30:54', 'Staff awaww awawaww', 'Created ticket #ref#-09-06-2025-228995 for customer ryan cansancio'),
('2025-09-06 11:45:23', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-09-06 11:46:23', 'Staff awaww awawaww', 'Assigned ticket ref#-05-09-2025-120710 to technician tech12 by awaww awawaww (Type: support)'),
('2025-09-06 11:46:29', 'Staff awaww awawaww', 'Assigned ticket ref#-05-09-2025-199679 to technician tech12 by awaww awawaww (Type: support)'),
('2025-09-06 11:46:35', 'Staff awaww awawaww', 'Assigned ticket ref#-05-09-2025-321543 to technician tech12 by awaww awawaww (Type: support)'),
('2025-09-06 11:47:26', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-09-06 11:47:38', 'Staff awaww awawaww', 'Assigned ticket ref#-05-09-2025-679738 to technician tech12 by awaww awawaww (Type: support)'),
('2025-09-06 11:47:42', 'Staff awaww awawaww', 'Assigned ticket ref#-05-09-2025-980166 to technician tech12 by awaww awawaww (Type: support)'),
('2025-09-06 12:01:21', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-09-06 12:15:38', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-06-2025-838631'),
('2025-09-06 12:19:35', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-09-06 12:26:59', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-06-2025-228995'),
('2025-09-06 12:30:29', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-06-2025-839788'),
('2025-09-06 12:31:35', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-06-2025-170779'),
('2025-09-06 12:31:52', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-06-2025-473597'),
('2025-09-06 12:32:06', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-05-2025-590853'),
('2025-09-06 12:32:13', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-08-15-2025-468031'),
('2025-09-06 12:32:16', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-05-2025-590853'),
('2025-09-06 12:32:21', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-06-2025-473597'),
('2025-09-06 12:32:25', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-06-2025-839788'),
('2025-09-06 12:32:29', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-06-2025-170779'),
('2025-09-06 12:32:32', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-06-2025-838631'),
('2025-09-06 12:32:38', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-06-2025-228995'),
('2025-09-06 12:58:45', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-05-2025-872512'),
('2025-09-06 12:59:15', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-06-2025-838631'),
('2025-09-06 13:01:32', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-06-2025-228995'),
('2025-09-06 13:01:37', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-06-2025-170779'),
('2025-09-06 13:01:50', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-05-2025-872512'),
('2025-09-06 13:01:54', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-06-2025-170779'),
('2025-09-06 13:01:57', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-06-2025-838631'),
('2025-09-06 13:02:01', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-06-2025-228995'),
('2025-09-06 13:05:24', 'Staff awaww awawaww', 'Created ticket #ref#-09-06-2025-769915 for customer ryan cansancio'),
('2025-09-06 13:05:45', 'Staff awaww awawaww', 'Staff awaww awawaww edited ticket ref#-09-05-2025-872512 subject'),
('2025-09-06 13:05:49', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-05-2025-872512'),
('2025-09-06 13:05:54', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-05-2025-872512'),
('2025-09-06 13:06:01', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-05-2025-184144'),
('2025-09-06 13:06:06', 'Staff awaww awawaww', 'Staff awaww awawaww deleted ticket ref#-09-05-2025-184144'),
('2025-09-06 13:11:51', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-06-2025-838631'),
('2025-09-11 15:37:36', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-09-12 14:53:24', 'haahays haha', 'has successfully logged in'),
('2025-09-12 14:53:34', 'Technician haahays haha', 'Ticket ref#-09-05-2025-590853 archived by technician haahays haha (Type: regular)'),
('2025-09-12 14:53:43', 'awaww awawaww', 'has successfully logged in'),
('2025-09-12 15:19:25', 'haahays haha', 'has successfully logged in'),
('2025-09-12 15:20:05', 'Technician haahays haha', 'Ticket ref#-08-15-2025-152225 archived by technician haahays haha (Type: regular)'),
('2025-09-12 15:20:44', 'awaww awawaww', 'has successfully logged in'),
('2025-09-12 15:42:33', 'awaww awawaww', 'has successfully logged in'),
('2025-09-12 16:27:59', 'Lyle Barr', 'has successfully logged in'),
('2025-09-12 16:28:42', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-09-12 16:28:52', 'Staff awaww awawaww', 'Staff awaww awawaww approved customer ticket ref#-05-09-2025-901988 for customer Lyle Barr'),
('2025-09-12 16:29:33', 'haahays haha', 'has successfully logged in'),
('2025-09-12 16:29:59', 'awaww awawaww', 'has successfully logged in'),
('2025-09-12 16:30:15', 'Staff awaww awawaww', 'Assigned ticket ref#-05-09-2025-901988 to technician tech12 by awaww awawaww (Type: support)'),
('2025-09-12 16:30:28', 'haahays haha', 'has successfully logged in'),
('2025-09-12 16:30:40', 'Technician haahays haha', 'Ticket ref#-05-09-2025-901988 archived by technician haahays haha (Type: support)'),
('2025-09-12 16:31:23', 'Technician haahays haha', 'Ticket ref#-05-09-2025-901988 unarchived by technician haahays haha (Type: supportArchived)'),
('2025-09-12 16:31:29', 'Technician haahays haha', 'Ticket ref#-05-09-2025-901988 unarchived by technician haahays haha (Type: supportArchived)'),
('2025-09-12 19:25:41', 'Staff awaww awawaww', 'Created ticket #ref#-09-12-2025-898305 for customer ryan cansancio'),
('2025-09-12 19:25:55', 'Staff awaww awawaww', 'Toggled status for technician tech12 to Unavailable by awaww awawaww'),
('2025-09-12 19:26:01', 'Staff awaww awawaww', 'Toggled status for technician tech12 to Available by awaww awawaww'),
('2025-09-12 20:29:17', 'haahays haha', 'has successfully logged in'),
('2025-09-14 11:30:55', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-09-14 11:43:43', 'Staff awaww awawaww', 'Created ticket #ref#-09-14-2025-803480 for customer Mikha Lim'),
('2025-09-14 13:14:29', 'Staff awaww awawaww', 'Created ticket #ref#-09-14-2025-461129 for customer ryan cansancio'),
('2025-09-14 13:31:19', 'Staff awaww awawaww', 'Created ticket #ref#-09-14-2025-914887 for customer ryan cansancio'),
('2025-09-14 17:45:38', 'Staff awaww awawaww', 'Created ticket #ref#-09-14-2025-923719 for customer Mikha Lim'),
('2025-09-14 18:07:35', 'Staff awaww awawaww', 'Created ticket #ref#-09-14-2025-536663 for customer ryan cansancio'),
('2025-09-14 18:45:11', 'Staff awaww awawaww', 'Created ticket #ref#-09-14-2025-708102 for customer ryan cansancio'),
('2025-09-14 18:55:18', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-12-2025-898305'),
('2025-09-14 18:55:21', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-14-2025-803480'),
('2025-09-14 18:55:25', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-14-2025-461129'),
('2025-09-14 18:55:28', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-14-2025-914887'),
('2025-09-14 18:55:31', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-14-2025-923719'),
('2025-09-14 18:55:34', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-14-2025-536663'),
('2025-09-14 18:57:28', 'Staff awaww awawaww', 'Created ticket #ref#-09-14-2025-245086 for customer ryan cansancio'),
('2025-09-14 18:59:46', 'Staff awaww awawaww', 'Assigned ticket ref#-09-14-2025-245086 to technician tech12 by awaww awawaww (Type: regular)'),
('2025-09-14 19:09:29', 'David Burns', 'has successfully logged in'),
('2025-09-14 19:09:48', 'customer David Burns', 'created ticket ref#-14-09-2025-177742'),
('2025-09-14 19:10:09', 'Staff awaww awawaww', 'Staff awaww awawaww approved customer ticket ref#-14-09-2025-177742 for customer David Burns'),
('2025-09-14 19:13:17', 'haahays haha', 'has successfully logged in'),
('2025-09-14 19:31:30', 'awaww awawaww', 'has successfully logged in'),
('2025-09-14 19:31:40', 'Staff awaww awawaww', 'Assigned ticket ref#-09-14-2025-708102 to technician tech12 by awaww awawaww (Type: regular)'),
('2025-09-14 19:31:48', 'haahays haha', 'has successfully logged in'),
('2025-09-14 20:20:12', 'awaww awawaww', 'has successfully logged in'),
('2025-09-14 20:20:28', 'Staff awaww awawaww', 'Created ticket #ref#-09-14-2025-159306 for customer ryan cansancio'),
('2025-09-14 20:20:35', 'Staff awaww awawaww', 'Assigned ticket ref#-09-14-2025-159306 to technician tech12 by awaww awawaww (Type: regular)'),
('2025-09-14 20:20:43', 'haahays haha', 'has successfully logged in'),
('2025-09-14 20:26:56', 'awaww awawaww', 'has successfully logged in'),
('2025-09-14 20:27:05', 'Staff awaww awawaww', 'Created ticket #ref#-09-14-2025-091312 for customer ryan cansancio'),
('2025-09-14 20:27:12', 'Staff awaww awawaww', 'Assigned ticket ref#-09-14-2025-091312 to technician tech12 by awaww awawaww (Type: regular)'),
('2025-09-14 20:27:23', 'haahays haha', 'has successfully logged in'),
('2025-09-14 20:37:11', 'awaww awawaww', 'has successfully logged in'),
('2025-09-14 20:37:21', 'Staff awaww awawaww', 'Created ticket #ref#-09-14-2025-051578 for customer ryan cansancio'),
('2025-09-14 20:37:29', 'Staff awaww awawaww', 'Assigned ticket ref#-09-14-2025-051578 to technician tech12 by awaww awawaww (Type: regular)'),
('2025-09-14 20:37:37', 'haahays haha', 'has successfully logged in'),
('2025-09-14 20:43:35', 'awaww awawaww', 'has successfully logged in'),
('2025-09-14 20:43:43', 'Staff awaww awawaww', 'Created ticket #ref#-09-14-2025-270586 for customer ryan cansancio'),
('2025-09-14 20:43:52', 'Staff awaww awawaww', 'Assigned ticket ref#-09-14-2025-270586 to technician tech12 by awaww awawaww (Type: regular)'),
('2025-09-14 20:44:01', 'haahays haha', 'has successfully logged in'),
('2025-09-14 20:53:01', 'Technician haahays haha', 'Ticket ref#-09-14-2025-708102 archived by technician haahays haha (Type: regular)'),
('2025-09-14 20:54:14', 'Staff awaww awawaww', 'Created ticket #ref#-09-14-2025-919152 for customer ryan cansancio'),
('2025-09-14 20:54:22', 'Staff awaww awawaww', 'Assigned ticket ref#-09-14-2025-919152 to technician tech12 by awaww awawaww (Type: regular)'),
('2025-09-14 20:54:36', 'haahays haha', 'has successfully logged in'),
('2025-09-14 20:58:37', 'awaww awawaww', 'has successfully logged in'),
('2025-09-14 21:10:44', 'haahays haha', 'has successfully logged in'),
('2025-09-14 21:10:51', 'Technician haahays haha', 'Ticket ref#-09-14-2025-919152 archived by technician haahays haha (Type: regular)'),
('2025-09-14 21:10:59', 'awaww awawaww', 'has successfully logged in'),
('2025-09-14 21:11:36', 'haahays haha', 'has successfully logged in'),
('2025-09-14 21:20:12', 'awaww awawaww', 'has successfully logged in'),
('2025-09-14 21:20:57', 'haahays haha', 'has successfully logged in'),
('2025-09-14 21:21:03', 'Technician haahays haha', 'Ticket ref#-09-14-2025-270586 archived by technician haahays haha (Type: regular)'),
('2025-09-14 21:21:11', 'awaww awawaww', 'has successfully logged in'),
('2025-09-14 21:37:37', 'awaww awawaww', 'has successfully logged in'),
('2025-09-14 21:37:46', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-14-2025-051578'),
('2025-09-14 21:38:32', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-14-2025-270586'),
('2025-09-14 21:38:36', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-14-2025-051578'),
('2025-09-14 21:39:30', 'Staff awaww awawaww', 'Created ticket #ref#-09-14-2025-822541 for customer ryan cansancio'),
('2025-09-14 21:39:40', 'Staff awaww awawaww', 'Assigned ticket ref#-09-14-2025-822541 to technician tech12 by awaww awawaww (Type: regular)'),
('2025-09-14 21:39:47', 'haahays haha', 'has successfully logged in'),
('2025-09-14 21:39:55', 'Technician haahays haha', 'Ticket ref#-09-14-2025-822541 archived by technician haahays haha (Type: regular)'),
('2025-09-14 21:40:03', 'awaww awawaww', 'has successfully logged in'),
('2025-09-14 21:40:45', 'Staff awaww awawaww', 'Created ticket #ref#-09-14-2025-877994 for customer ryan cansancio'),
('2025-09-14 21:40:55', 'Staff awaww awawaww', 'Assigned ticket ref#-09-14-2025-877994 to technician tech12 by awaww awawaww (Type: regular)'),
('2025-09-14 21:41:02', 'haahays haha', 'has successfully logged in'),
('2025-09-14 21:41:07', 'Technician haahays haha', 'Ticket ref#-09-14-2025-877994 archived by technician haahays haha (Type: regular)'),
('2025-09-14 21:41:14', 'awaww awawaww', 'has successfully logged in'),
('2025-09-14 21:41:28', 'haahays haha', 'has successfully logged in'),
('2025-09-14 21:48:53', 'awaww awawaww', 'has successfully logged in'),
('2025-09-14 21:49:00', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-14-2025-536663'),
('2025-09-14 21:49:04', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-14-2025-708102'),
('2025-09-14 21:49:08', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-09-14-2025-923719'),
('2025-09-14 21:54:21', 'Staff awaww awawaww', 'Toggled status for technician Xai Cy to Unavailable by awaww awawaww'),
('2025-09-14 21:54:22', 'Staff awaww awawaww', 'Toggled status for technician Xai Cy to Available by awaww awawaww'),
('2025-09-14 21:54:23', 'Staff awaww awawaww', 'Toggled status for technician Xai Cy to Unavailable by awaww awawaww'),
('2025-09-14 21:54:24', 'Staff awaww awawaww', 'Toggled status for technician Xai Cy to Available by awaww awawaww'),
('2025-09-14 21:54:24', 'Staff awaww awawaww', 'Toggled status for technician Xai Cy to Unavailable by awaww awawaww'),
('2025-09-14 21:54:24', 'Staff awaww awawaww', 'Toggled status for technician Xai Cy to Available by awaww awawaww'),
('2025-09-14 21:54:24', 'Staff awaww awawaww', 'Toggled status for technician Xai Cy to Unavailable by awaww awawaww'),
('2025-09-14 21:54:25', 'Staff awaww awawaww', 'Toggled status for technician Xai Cy to Available by awaww awawaww'),
('2025-09-14 23:45:23', 'Staff awaww awawaww', 'Created ticket #ref#-09-14-2025-851882 for customer ryan cansancio'),
('2025-09-14 23:45:44', 'Staff awaww awawaww', 'Created ticket #ref#-09-14-2025-759183 for customer Mikha Lim'),
('2025-09-14 23:46:40', 'Staff awaww awawaww', 'Created ticket #ref#-09-14-2025-242664 for customer ryan cansancio'),
('2025-09-15 18:01:11', 'haahays haha', 'has successfully logged in'),
('2025-09-15 18:01:25', 'Technician haahays haha', 'Ticket ref#-09-14-2025-270586 archived by technician haahays haha (Type: regular)'),
('2025-09-15 18:01:33', 'awaww awawaww', 'has successfully logged in'),
('2025-09-15 18:02:00', 'haahays haha', 'has successfully logged in'),
('2025-09-15 18:23:53', 'awaww awawaww', 'has successfully logged in'),
('2025-09-15 18:24:43', 'Staff awaww awawaww', 'Staff awaww awawaww closed ticket ref#-09-14-2025-270586'),
('2025-09-15 18:25:05', 'haahays haha', 'has successfully logged in'),
('2025-09-15 18:59:05', 'awaww awawaww', 'has successfully logged in'),
('2025-09-15 19:04:20', 'Staff awaww awawaww', 'Created ticket #ref#-09-15-2025-254542 for customer ryan cansancio'),
('2025-09-15 20:31:01', 'Staff awaww awawaww', 'Created ticket #ref#-09-15-2025-107534 for customer ryan cansancio'),
('2025-09-15 21:28:07', 'Staff awaww awawaww', 'Created ticket #ref#-09-15-2025-193019 for customer Pham Hanni'),
('2025-09-15 23:04:40', 'Staff awaww awawaww', 'Toggled status for technician Kolohe to Unavailable by awaww awawaww'),
('2025-09-15 23:04:43', 'Staff awaww awawaww', 'Toggled status for technician Kolohe to Available by awaww awawaww'),
('2025-09-15 23:05:13', 'Staff awaww awawaww', 'Assigned ticket ref#-09-14-2025-242664 to technician techsss by awaww awawaww (Type: regular)'),
('2025-09-15 23:06:04', 'Staff awaww awawaww', 'Assigned ticket ref#-09-15-2025-254542 to technician jorge by awaww awawaww (Type: regular)'),
('2025-09-15 23:06:16', 'jorge bugwak', 'has successfully logged in'),
('2025-09-15 23:07:41', 'awaww awawaww', 'has successfully logged in');
INSERT INTO `tbl_logs` (`l_stamp`, `l_type`, `l_description`) VALUES
('2025-09-15 23:08:03', 'Staff awaww awawaww', 'Assigned ticket ref#-09-15-2025-107534 to technician jorge by awaww awawaww (Type: regular)'),
('2025-09-15 23:08:21', 'jorge bugwak', 'has successfully logged in'),
('2025-09-16 00:14:43', 'Staff awaww awawaww', 'Assigned ticket ref#-09-14-2025-851882 to technician techsss by awaww awawaww (Type: regular)'),
('2025-09-16 00:41:52', 'Staff awaww awawaww', 'Assigned ticket ref#-09-15-2025-193019 to technician techsss by awaww awawaww (Type: regular)'),
('2025-09-16 00:41:58', 'Staff awaww awawaww', 'Toggled status for technician Test ni ha to Available by awaww awawaww'),
('2025-09-16 00:41:59', 'Staff awaww awawaww', 'Toggled status for technician Test ni ha to Unavailable by awaww awawaww'),
('2025-09-16 00:42:08', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-09-14-2025-923719'),
('2025-09-16 00:42:24', 'Staff awaww awawaww', 'Created ticket #ref#-09-16-2025-982641 for customer ryan cansancio'),
('2025-09-16 00:42:41', 'haahays haha', 'has successfully logged in'),
('2025-09-16 00:47:16', 'Staff awaww awawaww', 'Assigned ticket ref#-04-09-2025-682131 to technician Kolohe by awaww awawaww (Type: support)'),
('2025-09-26 14:41:04', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-09-26 16:02:32', 'Mikha Lim', 'has successfully logged in'),
('2025-09-29 13:02:54', 'ryan cansancio', 'has successfully logged in'),
('2025-09-29 13:03:07', 'customer ryan cansancio', 'created ticket ref#-29-09-2025-907359'),
('2025-09-29 13:03:35', 'Staff awaww awawaww', 'Staff awaww awawaww rejected customer ticket ref#-29-09-2025-907359 for customer ryan cansancio with remarks: okaay pako'),
('2025-09-29 13:05:02', 'Mikha Lim', 'has successfully logged in'),
('2025-09-29 13:05:11', 'customer Mikha Lim', 'created ticket ref#-29-09-2025-383494'),
('2025-09-29 13:05:54', 'Staff awaww awawaww', 'Staff awaww awawaww approved customer ticket ref#-29-09-2025-383494 for customer Mikha Lim'),
('2025-09-29 13:10:42', 'ryan cansancio', 'has successfully logged in'),
('2025-09-29 13:10:53', 'customer ryan cansancio', 'created ticket ref#-29-09-2025-563914'),
('2025-09-29 13:11:27', 'Staff awaww awawaww', 'Staff awaww awawaww rejected customer ticket ref#-29-09-2025-563914 for customer ryan cansancio with remarks: awawa'),
('2025-09-29 13:12:51', 'Staff awaww awawaww', 'Created ticket #ref#-09-29-2025-000348 for customer ryan cansancio'),
('2025-09-29 13:13:14', 'Staff awaww awawaww', 'Assigned ticket ref#-09-29-2025-000348 to technician techsss by awaww awawaww (Type: regular)'),
('2025-09-29 13:13:30', 'tech admin', 'has successfully logged in'),
('2025-09-29 13:13:36', 'Technician tech admin', 'Ticket ref#-09-29-2025-000348 closed by technician tech admin (Type: regular, Close Date: 2025-09-29 07:13:36)'),
('2025-09-29 13:13:44', 'awaww awawaww', 'has successfully logged in'),
('2025-10-02 13:57:42', 'ryan cansancio', 'has successfully logged in'),
('2025-10-02 13:58:01', 'customer ryan cansancio', 'created ticket ref#-02-10-2025-538165'),
('2025-10-02 13:58:24', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-02 13:58:31', 'Staff awaww awawaww', 'Staff awaww awawaww approved customer ticket ref#-02-10-2025-538165 for customer ryan cansancio'),
('2025-10-02 13:58:46', 'ryan cansancio', 'has successfully logged in'),
('2025-10-02 13:59:28', 'Staff awaww awawaww', 'Assigned ticket ref#-02-10-2025-538165 to technician techsss by awaww awawaww (Type: support)'),
('2025-10-02 13:59:43', 'ryan cansancio', 'has successfully logged in'),
('2025-10-02 14:10:43', 'customer ryan cansancio', 'created ticket ref#-02-10-2025-771863'),
('2025-10-02 14:11:11', 'Staff awaww awawaww', 'Staff awaww awawaww approved customer ticket ref#-02-10-2025-771863 for customer ryan cansancio'),
('2025-10-02 14:11:21', 'Staff awaww awawaww', 'Assigned ticket ref#-02-10-2025-771863 to technician techsss by awaww awawaww (Type: support)'),
('2025-10-02 14:11:34', 'ryan cansancio', 'has successfully logged in'),
('2025-10-02 14:23:18', 'ryan cansancio', 'has successfully logged in'),
('2025-10-02 14:24:10', 'Technician tech admin', 'Ticket ref#-02-10-2025-538165 closed by technician tech admin (Type: support, Close Date: 2025-10-02 08:24:10)'),
('2025-10-02 14:24:23', 'ryan cansancio', 'has successfully logged in'),
('2025-10-02 14:24:51', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-02 14:28:51', 'ryan cansancio', 'has successfully logged in'),
('2025-10-02 14:28:59', 'customer ryan cansancio', 'created ticket ref#-02-10-2025-376857'),
('2025-10-02 14:29:19', 'Staff awaww awawaww', 'Staff awaww awawaww approved customer ticket ref#-02-10-2025-376857 for customer ryan cansancio'),
('2025-10-02 14:29:31', 'ryan cansancio', 'has successfully logged in'),
('2025-10-02 14:30:20', 'Staff awaww awawaww', 'Assigned ticket ref#-02-10-2025-376857 to technician techsss by awaww awawaww (Type: support)'),
('2025-10-02 14:30:45', 'ryan cansancio', 'has successfully logged in'),
('2025-10-02 15:36:51', 'ryan cansancio', 'has successfully logged in'),
('2025-10-02 15:37:00', 'customer ryan cansancio', 'created ticket ref#-02-10-2025-459426'),
('2025-10-02 15:37:27', 'Staff awaww awawaww', 'Staff awaww awawaww rejected customer ticket ref#-02-10-2025-459426 for customer ryan cansancio with remarks: paksit'),
('2025-10-02 15:37:40', 'ryan cansancio', 'has successfully logged in'),
('2025-10-02 16:02:01', 'ryan cansancio', 'has successfully logged in'),
('2025-10-02 23:22:33', 'Mikha Lim', 'has successfully logged in'),
('2025-10-02 23:35:11', 'customer Mikha Lim', 'created ticket ref#-02-10-2025-440509'),
('2025-10-02 23:35:32', 'xiexie Ryan', 'has successfully logged in'),
('2025-10-02 23:35:32', 'Staff xiexie Ryan', 'Staff xiexie has successfully logged in'),
('2025-10-02 23:35:47', 'Staff xiexie Ryan', 'Staff xiexie Ryan rejected customer ticket ref#-02-10-2025-440509 for customer Mikha Lim with remarks: no valid information'),
('2025-10-02 23:36:03', 'Mikha Lim', 'has successfully logged in'),
('2025-10-03 00:06:46', 'Mikha Lim', 'has successfully logged in'),
('2025-10-04 14:52:49', 'ryan cansancio', 'has successfully logged in'),
('2025-10-04 15:37:15', 'awaww awawaww', 'has successfully logged in'),
('2025-10-04 15:37:16', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-04 15:38:34', 'Staff awaww awawaww', 'Assigned ticket ref#-09-16-2025-982641 to technician techsss by awaww awawaww (Type: regular)'),
('2025-10-04 15:38:44', 'tech admin', 'has successfully logged in'),
('2025-10-04 15:39:08', 'Technician tech admin', 'Ticket ref#-09-16-2025-982641 closed by technician tech admin (Type: regular, Close Date: 2025-10-04 09:39:07)'),
('2025-10-04 15:39:20', 'awaww awawaww', 'has successfully logged in'),
('2025-10-04 15:39:36', 'Staff awaww awawaww', 'Toggled status for technician Kolohe to Unavailable by awaww awawaww'),
('2025-10-04 15:39:37', 'Staff awaww awawaww', 'Toggled status for technician Kolohe to Available by awaww awawaww'),
('2025-10-04 15:55:37', 'awaww awawaww', 'has successfully logged in'),
('2025-10-04 15:55:53', 'Staff awaww awawaww', 'Created ticket #ref#-10-04-2025-219683 for customer ryan cansancio'),
('2025-10-04 15:56:29', 'Staff awaww awawaww', 'Assigned ticket ref#-10-04-2025-219683 to technician techsss by awaww awawaww (Type: regular)'),
('2025-10-04 15:56:40', 'tech admin', 'has successfully logged in'),
('2025-10-04 15:57:06', 'awaww awawaww', 'has successfully logged in'),
('2025-10-04 15:57:12', 'Staff awaww awawaww', 'Assigned ticket ref#-05-09-2025-457302 to technician techsss by awaww awawaww (Type: support)'),
('2025-10-04 15:57:21', 'tech admin', 'has successfully logged in'),
('2025-10-04 15:58:01', 'ryan cansancio', 'has successfully logged in'),
('2025-10-04 15:58:15', 'customer ryan cansancio', 'created ticket ref#-04-10-2025-209485'),
('2025-10-04 15:58:40', 'awaww awawaww', 'has successfully logged in'),
('2025-10-04 15:58:44', 'Staff awaww awawaww', 'Staff awaww awawaww approved customer ticket ref#-04-10-2025-209485 for customer ryan cansancio'),
('2025-10-04 15:58:56', 'ryan cansancio', 'has successfully logged in'),
('2025-10-04 16:00:26', 'awaww awawaww', 'has successfully logged in'),
('2025-10-04 16:01:18', 'Staff awaww awawaww', 'Assigned ticket ref#-04-10-2025-209485 to technician techsss by awaww awawaww (Type: support)'),
('2025-10-04 16:01:27', 'tech admin', 'has successfully logged in'),
('2025-10-04 16:01:41', 'Technician tech admin', 'Ticket ref#-04-10-2025-209485 closed by technician tech admin (Type: support, Close Date: 2025-10-04 10:01:41)'),
('2025-10-04 16:01:45', 'Technician tech admin', 'Ticket ref#-10-04-2025-219683 closed by technician tech admin (Type: regular, Close Date: 2025-10-04 10:01:45)'),
('2025-10-04 16:01:52', 'awaww awawaww', 'has successfully logged in'),
('2025-10-04 16:02:09', 'ryan cansancio', 'has successfully logged in'),
('2025-10-04 16:12:10', 'customer ryan cansancio', 'created ticket ref#-04-10-2025-245305'),
('2025-10-04 16:12:34', 'awaww awawaww', 'has successfully logged in'),
('2025-10-04 16:12:40', 'Staff awaww awawaww', 'Staff awaww awawaww approved customer ticket ref#-04-10-2025-245305 for customer ryan cansancio'),
('2025-10-04 16:14:26', 'ryan cansancio', 'has successfully logged in'),
('2025-10-04 16:14:52', 'awaww awawaww', 'has successfully logged in'),
('2025-10-04 16:15:00', 'Staff awaww awawaww', 'Assigned ticket ref#-04-10-2025-245305 to technician techsss by awaww awawaww (Type: support)'),
('2025-10-04 16:15:19', 'tech admin', 'has successfully logged in'),
('2025-10-04 16:15:26', 'Technician tech admin', 'Ticket ref#-04-10-2025-245305 closed by technician tech admin (Type: support, Close Date: 2025-10-04 10:15:26)'),
('2025-10-04 16:16:38', 'tech admin', 'has successfully logged in'),
('2025-10-04 16:32:24', 'tech admin', 'has successfully logged in'),
('2025-10-04 16:32:46', 'Technician tech admin', 'Ticket ref#-09-15-2025-193019 closed by technician tech admin (Type: regular, Close Date: 2025-10-04 10:32:46)'),
('2025-10-04 16:32:54', 'awaww awawaww', 'has successfully logged in'),
('2025-10-04 16:33:13', 'ryan cansancio', 'has successfully logged in'),
('2025-10-04 16:33:36', 'awaww awawaww', 'has successfully logged in'),
('2025-10-04 16:33:55', 'tech admin', 'has successfully logged in'),
('2025-10-04 16:34:00', 'Technician tech admin', 'Ticket ref#-02-10-2025-376857 closed by technician tech admin (Type: support, Close Date: 2025-10-04 10:34:00)'),
('2025-10-04 16:34:10', 'ryan cansancio', 'has successfully logged in'),
('2025-10-06 16:42:57', 'awaww awawaww', 'has successfully logged in'),
('2025-10-06 16:42:57', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-06 17:06:56', 'tech admin', 'has successfully logged in'),
('2025-10-06 17:30:15', 'Technician tech admin', 'Ticket ref#-09-14-2025-851882 closed by technician tech admin (Type: regular, Close Date: 2025-10-06 11:30:15)'),
('2025-10-06 17:33:51', 'Technician tech admin', 'Ticket ref#-09-14-2025-242664 closed by technician tech admin (Type: regular, Close Date: 2025-10-06 11:33:51)'),
('2025-10-06 17:34:34', 'Technician tech admin', 'Ticket ref#-05-09-2025-457302 closed by technician tech admin (Type: support, Close Date: 2025-10-06 11:34:29)'),
('2025-10-06 17:48:26', 'Technician tech admin', 'Ticket ref#-02-10-2025-771863 closed by technician tech admin (Type: support, Close Date: 2025-10-06 11:48:22)'),
('2025-10-06 17:49:22', 'awaww awawaww', 'has successfully logged in'),
('2025-10-06 17:49:41', 'ryan cansancio', 'has successfully logged in'),
('2025-10-06 17:50:08', 'tech admin', 'has successfully logged in'),
('2025-10-06 18:00:32', 'awaww awawaww', 'has successfully logged in'),
('2025-10-06 18:00:43', 'Staff awaww awawaww', 'Assigned ticket ref#-04-09-2025-511310 to technician techsss by awaww awawaww (Type: support)'),
('2025-10-06 18:04:49', 'tech admin', 'has successfully logged in'),
('2025-10-06 18:06:25', 'ryan cansancio', 'has successfully logged in'),
('2025-10-06 18:07:02', 'customer ryan cansancio', 'created ticket ref#-06-10-2025-366867'),
('2025-10-06 18:07:22', 'awaww awawaww', 'has successfully logged in'),
('2025-10-06 18:07:30', 'Staff awaww awawaww', 'Staff awaww awawaww approved customer ticket ref#-06-10-2025-366867 for customer ryan cansancio'),
('2025-10-06 18:07:54', 'awaww awawaww', 'has successfully logged in'),
('2025-10-06 18:08:01', 'Staff awaww awawaww', 'Assigned ticket ref#-06-10-2025-366867 to technician techsss by awaww awawaww (Type: support)'),
('2025-10-06 18:08:12', 'ryan cansancio', 'has successfully logged in'),
('2025-10-06 18:08:33', 'tech admin', 'has successfully logged in'),
('2025-10-06 18:08:42', 'Technician tech admin', 'Ticket ref#-06-10-2025-366867 closed by technician tech admin (Type: support, Close Date: 2025-10-06 12:08:38)'),
('2025-10-06 18:09:32', 'ryan cansancio', 'has successfully logged in'),
('2025-10-08 23:54:52', 'awaww awawaww', 'has successfully logged in'),
('2025-10-09 00:00:28', 'tech admin', 'has successfully logged in'),
('2025-10-09 00:01:42', 'tech admin', 'has successfully logged in'),
('2025-10-09 00:02:32', 'tech admin', 'has successfully logged in'),
('2025-10-09 00:08:47', 'Technician tech admin', 'Ticket ref#-04-09-2025-511310 closed by technician tech admin (Type: support, Close Date: 2025-10-08 18:08:36)'),
('2025-10-09 00:10:04', 'awaww awawaww', 'has successfully logged in'),
('2025-10-09 00:10:12', 'Staff awaww awawaww', 'Assigned ticket ref#-09-14-2025-759183 to technician techsss by awaww awawaww (Type: regular)'),
('2025-10-09 00:10:32', 'tech admin', 'has successfully logged in'),
('2025-10-09 00:24:28', 'tech admin', 'has successfully logged in'),
('2025-10-09 00:55:14', 'tech admin', 'has successfully logged in'),
('2025-10-09 00:55:18', 'Technician tech admin', 'Ticket ref#-09-14-2025-759183 closed by technician tech admin (Type: regular)'),
('2025-10-09 00:56:11', 'tech admin', 'has successfully logged in'),
('2025-10-09 00:56:51', 'tech admin', 'has successfully logged in'),
('2025-10-09 01:00:25', 'tech admin', 'has successfully logged in'),
('2025-10-09 01:17:27', 'tech admin', 'has successfully logged in'),
('2025-10-09 01:18:00', 'awaww awawaww', 'has successfully logged in'),
('2025-10-09 01:18:00', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-09 01:18:08', 'Staff awaww awawaww', 'Assigned ticket ref#-04-09-2025-319876 to technician techsss by awaww awawaww (Type: support)'),
('2025-10-09 01:18:14', 'tech admin', 'has successfully logged in'),
('2025-10-09 01:18:19', 'Technician tech admin', 'Ticket ref#-04-09-2025-319876 closed by technician tech admin (Type: support)'),
('2025-10-09 01:21:49', 'awaww awawaww', 'has successfully logged in'),
('2025-10-09 01:21:49', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-09 01:22:09', 'Staff awaww awawaww', 'Created ticket #ref#-10-09-2025-706780 for customer ryan cansancio'),
('2025-10-09 01:22:19', 'Staff awaww awawaww', 'Assigned ticket ref#-10-09-2025-706780 to technician techsss by awaww awawaww (Type: regular)'),
('2025-10-09 01:22:29', 'tech admin', 'has successfully logged in'),
('2025-10-09 01:22:36', 'Technician tech admin', 'Ticket ref#-10-09-2025-706780 closed by technician tech admin (Type: regular)'),
('2025-10-09 01:22:51', 'awaww awawaww', 'has successfully logged in'),
('2025-10-09 01:22:51', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-09 01:25:15', 'awaww awawaww', 'has successfully logged in'),
('2025-10-09 01:25:27', 'Staff awaww awawaww', 'Created ticket #ref#-10-09-2025-966281 for customer ryan cansancio'),
('2025-10-09 01:25:32', 'Staff awaww awawaww', 'Assigned ticket ref#-10-09-2025-966281 to technician techsss by awaww awawaww (Type: regular)'),
('2025-10-09 01:25:40', 'tech admin', 'has successfully logged in'),
('2025-10-09 01:25:43', 'Technician tech admin', 'Ticket ref#-10-09-2025-966281 closed by technician tech admin (Type: regular)'),
('2025-10-09 01:41:48', 'awaww awawaww', 'has successfully logged in'),
('2025-10-09 01:41:48', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-09 01:42:01', 'Staff awaww awawaww', 'Created ticket #ref#-10-09-2025-505721 for customer ryan cansancio'),
('2025-10-09 01:42:08', 'Staff awaww awawaww', 'Assigned ticket ref#-10-09-2025-505721 to technician techsss by awaww awawaww (Type: regular)'),
('2025-10-09 01:42:18', 'tech admin', 'has successfully logged in'),
('2025-10-09 01:42:22', 'Technician tech admin', 'Ticket ref#-10-09-2025-505721 closed by technician tech admin (Type: regular)'),
('2025-10-09 01:43:20', 'ryan cansancio', 'has successfully logged in'),
('2025-10-09 01:43:43', 'customer ryan cansancio', 'created ticket ref#-08-10-2025-253782'),
('2025-10-09 01:44:05', 'awaww awawaww', 'has successfully logged in'),
('2025-10-09 01:44:05', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-09 01:44:10', 'Staff awaww awawaww', 'Staff awaww awawaww approved customer ticket ref#-08-10-2025-253782 for customer ryan cansancio'),
('2025-10-09 01:44:15', 'Staff awaww awawaww', 'Assigned ticket ref#-08-10-2025-253782 to technician techsss by awaww awawaww (Type: support)'),
('2025-10-09 01:44:22', 'tech admin', 'has successfully logged in'),
('2025-10-09 01:44:26', 'Technician tech admin', 'Ticket ref#-08-10-2025-253782 closed by technician tech admin (Type: support)'),
('2025-10-09 02:06:24', 'awaww awawaww', 'has successfully logged in'),
('2025-10-09 02:06:27', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-09 02:06:38', 'Staff awaww awawaww', 'Created ticket #ref#-10-09-2025-999271 for customer ryan cansancio'),
('2025-10-09 02:06:45', 'Staff awaww awawaww', 'Assigned ticket ref#-10-09-2025-999271 to technician techsss by awaww awawaww (Type: regular)'),
('2025-10-09 02:06:52', 'tech admin', 'has successfully logged in'),
('2025-10-09 02:38:03', 'tech admin', 'has successfully logged in'),
('2025-10-09 02:38:26', 'awaww awawaww', 'has successfully logged in'),
('2025-10-09 02:38:26', 'Staff awaww awawaww', 'Staff awaww has successfully logged in');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_supp_tickets`
--

CREATE TABLE `tbl_supp_tickets` (
  `c_id` int(50) NOT NULL,
  `c_lname` varchar(200) NOT NULL,
  `c_fname` varchar(200) NOT NULL,
  `s_ref` varchar(200) NOT NULL,
  `s_subject` varchar(200) NOT NULL,
  `s_message` varchar(200) NOT NULL,
  `s_status` varchar(200) NOT NULL,
  `id` int(50) NOT NULL,
  `technician_username` varchar(250) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_supp_tickets`
--

INSERT INTO `tbl_supp_tickets` (`c_id`, `c_lname`, `c_fname`, `s_ref`, `s_subject`, `s_message`, `s_status`, `id`, `technician_username`) VALUES
(9, 'Sims', 'Latifah', 'ref#-22-05-2025-261353', 'no connection', 'kusogg kaayo ang uwan', 'Archived', 21, ''),
(9, 'Sims', 'Latifah', 'ref#-22-05-2025-104028', 'nawala na', 'kussogg kaayo', 'Open', 22, ''),
(9, 'Sims', 'Latifah', 'ref#-22-05-2025-521587', 'redlight', 'stop', 'Open', 23, ''),
(23, 'Daniel', 'Ann', 'ref#-23-05-2025-114488', 'test', 'test lang ni para sa modal', 'Open', 24, ''),
(9, 'Sims', 'Latifah', 'ref#-23-05-2025-511113', 'redlight', 'okaay kaayo', 'Open', 25, ''),
(9, 'Sims', 'Latifah', 'ref#-23-05-2025-565607', 'stoplight', 'bugnaw', 'Open', 26, ''),
(11, 'Mendez', 'Lunea', 'ref#-23-05-2025-627429', 'redlight', 'no net', 'Open', 27, ''),
(9, 'Sims', 'Latifah', 'ref#-23-05-2025-758042', 'stop', 'okay nako bahala nani', 'Open', 28, ''),
(9, 'Sims', 'Latifah', 'ref#-23-05-2025-951343', 'okaay kaayo', 'bugnaw', 'Open', 29, ''),
(9, 'Sims', 'Latifah', 'ref#-23-05-2025-803167', 'qpal', 'okaay', 'Open', 30, ''),
(1, 'wawawawaw', 'awawawa', 'ref#-27-06-2025-928737', 'test', 'testing purposes', 'Open', 31, ''),
(1, 'wawawawaw', 'awawawa', 'ref#-27-06-2025-477366', 'test', 'for testing', 'Open', 32, ''),
(29, 'Barr', 'Lyle', 'ref#-04-09-2025-511310', 'kapoy', 'fdvdfsgdrg', 'Closed', 33, ''),
(29, 'Barr', 'Lyle', 'ref#-04-09-2025-319876', 'kapoy', 'hahahah', 'Closed', 34, ''),
(29, 'Barr', 'Lyle', 'ref#-04-09-2025-682131', 'Faster', 'jdknadk', 'Open', 36, 'Kolohe'),
(29, 'Barr', 'Lyle', 'ref#-05-09-2025-457302', 'Faster', 'hjfyujyjy', 'Closed', 37, ''),
(34, 'Lim', 'Mikha', 'ref#-05-09-2025-679738', 'kalipay', 'testing testing', 'Open', 38, 'tech12'),
(1, 'wawawawaw', 'awawawa', 'ref#-27-06-2025-980002', 'test test', 'for testing purposes', 'Open', 39, ''),
(35, 'Hanni', 'Pham', 'ref#-23-08-2025-994669', 'ambot', 'ambot', 'Open', 40, 'tech12'),
(0, '', '', 'ref#-04-09-2025-513611', 'tests', 'dong awa', 'Open', 41, ''),
(34, 'Lim', 'Mikha', 'ref#-05-09-2025-980166', 'best', 'don\'t forgive me', 'Open', 42, 'tech12'),
(34, 'Lim', 'Mikha', 'ref#-05-09-2025-120710', 'last summer', 'testing', 'Open', 43, 'tech12'),
(34, 'Lim', 'Mikha', 'ref#-05-09-2025-321543', 'california', 'testing', 'Open', 44, 'tech12'),
(34, 'Lim', 'Mikha', 'ref#-05-09-2025-199679', 'waragudt', 'dahsedajqwerikqeuw', 'Open', 45, 'tech12'),
(29, 'Barr', 'Lyle', 'ref#-05-09-2025-901988', 'MAMACITA', 'promise', 'Open', 46, 'tech12'),
(30, 'Burns', 'David', 'ref#-14-09-2025-177742', 'goods', 'awawwawaw', 'Open', 47, ''),
(34, 'Lim', 'Mikha', 'ref#-29-09-2025-383494', 'wawawaw', 'awawaw', 'Open', 48, ''),
(33, 'cansancio', 'ryan', 'ref#-02-10-2025-538165', 'awww', 'wawawaw', 'Closed', 49, ''),
(33, 'cansancio', 'ryan', 'ref#-02-10-2025-771863', 'aawawaw', 'waawaw', 'Closed', 50, ''),
(33, 'cansancio', 'ryan', 'ref#-02-10-2025-376857', 'awaw', 'awawaw', 'Closed', 51, ''),
(33, 'cansancio', 'ryan', 'ref#-04-10-2025-209485', 'Moon', 'Stars', 'Closed', 52, ''),
(33, 'cansancio', 'ryan', 'ref#-04-10-2025-245305', 'Moon', 'Moon', 'Closed', 53, ''),
(33, 'cansancio', 'ryan', 'ref#-06-10-2025-366867', 'akoako', 'akoako', 'Closed', 54, ''),
(33, 'cansancio', 'ryan', 'ref#-08-10-2025-253782', 'okssss', 'paba', 'Closed', 55, '');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_ticket`
--

CREATE TABLE `tbl_ticket` (
  `t_id` int(50) NOT NULL,
  `t_aname` varchar(200) NOT NULL,
  `t_subject` varchar(200) NOT NULL,
  `t_status` varchar(200) NOT NULL,
  `t_details` varchar(200) NOT NULL,
  `t_ref` varchar(200) NOT NULL,
  `technician_username` varchar(250) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_ticket`
--

INSERT INTO `tbl_ticket` (`t_id`, `t_aname`, `t_subject`, `t_status`, `t_details`, `t_ref`, `technician_username`) VALUES
(36, 'ryan cansancio', 'awawaw', 'Open', 'ARCHIVED:awawaw', 'ref#-09-12-2025-898305', 'haha12'),
(37, 'Mikha Lim', 'awaw', 'Open', 'ARCHIVED:awawaw', 'ref#-09-14-2025-803480', 'haha12'),
(38, 'ryan cansancio', 'awawaw', 'Open', 'ARCHIVED:awawaw', 'ref#-09-14-2025-461129', 'haha12'),
(39, 'ryan cansancio', 'aww', 'Open', 'ARCHIVED:aawaw', 'ref#-09-14-2025-914887', 'haha12'),
(40, 'Mikha Lim', 'aaw', 'Open', 'ARCHIVED:aww', 'ref#-09-14-2025-923719', '0'),
(41, 'ryan cansancio', 'awaaw', 'Open', 'awawaw', 'ref#-09-14-2025-536663', '0'),
(42, 'ryan cansancio', 'awawaw', 'Open', 'awawaw', 'ref#-09-14-2025-708102', 'tech12'),
(43, 'ryan cansancio', 'awaww', 'Open', 'awaw', 'ref#-09-14-2025-245086', 'tech12'),
(44, 'ryan cansancio', 'aawawaw', 'Open', 'awawaw', 'ref#-09-14-2025-159306', 'tech12'),
(45, 'ryan cansancio', 'awawaw', 'Open', 'awawaw', 'ref#-09-14-2025-091312', 'tech12'),
(46, 'ryan cansancio', 'awaw', 'Open', 'awaw', 'ref#-09-14-2025-051578', 'tech12'),
(47, 'ryan cansancio', 'awaww', 'Closed', 'awawaw', 'ref#-09-14-2025-270586', 'tech12'),
(48, 'ryan cansancio', 'awaw', 'Open', 'TECH_ARCHIVED:awaww', 'ref#-09-14-2025-919152', 'tech12'),
(49, 'ryan cansancio', 'awawaw', 'Open', 'TECH_ARCHIVED:awawaw', 'ref#-09-14-2025-822541', 'tech12'),
(50, 'ryan cansancio', 'awawaw', 'Open', 'TECH_ARCHIVED:aww', 'ref#-09-14-2025-877994', 'tech12'),
(51, 'ryan cansancio', 'awawaw', 'Closed', 'awawaw', 'ref#-09-14-2025-851882', 'techsss'),
(52, 'Mikha Lim', 'awawaw', 'Closed', 'awawaw', 'ref#-09-14-2025-759183', 'techsss'),
(53, 'ryan cansancio', 'awawawa', 'Closed', 'wawaw', 'ref#-09-14-2025-242664', 'techsss'),
(54, 'ryan cansancio', 'awawaw', 'Open', 'awaww', 'ref#-09-15-2025-254542', 'jorge'),
(55, 'ryan cansancio', 'awawaw', 'Open', 'awawaw', 'ref#-09-15-2025-107534', 'jorge'),
(56, 'Pham Hanni', 'awawaw', 'Closed', 'awawaw', 'ref#-09-15-2025-193019', 'techsss'),
(57, 'ryan cansancio', 'WAWAW', 'Closed', 'AWAW', 'ref#-09-16-2025-982641', 'techsss'),
(58, 'ryan cansancio', 'awawa', 'Closed', 'wawaw', 'ref#-09-29-2025-000348', 'techsss'),
(59, 'ryan cansancio', 'Moon', 'Closed', 'Stars', 'ref#-10-04-2025-219683', 'techsss'),
(60, 'ryan cansancio', 'Moon', 'Closed', 'ligh', 'ref#-10-09-2025-706780', 'techsss'),
(61, 'ryan cansancio', 'Moon', 'Closed', 'sadadasd', 'ref#-10-09-2025-966281', 'techsss'),
(62, 'ryan cansancio', 'Moon', 'Closed', 'lighters', 'ref#-10-09-2025-505721', 'techsss'),
(63, 'ryan cansancio', 'Moon', 'Closed', 'sadasdsad', 'ref#-10-09-2025-999271', 'techsss');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_transactions`
--

CREATE TABLE `tbl_transactions` (
  `t_id` int(50) NOT NULL,
  `t_date` varchar(200) NOT NULL,
  `t_balance` int(50) NOT NULL,
  `t_credit_date` varchar(200) NOT NULL,
  `t_description` varchar(200) NOT NULL,
  `t_amount` int(50) NOT NULL,
  `t_customer_name` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_transactions`
--

INSERT INTO `tbl_transactions` (`t_id`, `t_date`, `t_balance`, `t_credit_date`, `t_description`, `t_amount`, `t_customer_name`) VALUES
(6, '2025-08-06', 0, '2025-08-07', 'Plan 1499', 15000, 'John William Mayormita'),
(7, '2025-08-15', 201, '2025-08-15', 'Plan 1799', 2000, 'Pham Hanni'),
(8, '2025-08-15', 1001, '2025-08-15', 'Plan 1799', 1800, 'Mikha Lim'),
(9, '2025-08-15', 500, '2025-08-15', 'Plan 2500', 3000, 'ryan cansancio'),
(10, '2025-08-15', 1, '2025-08-15', 'Plan 1499', 1500, 'John William Mayormita');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_user`
--

CREATE TABLE `tbl_user` (
  `u_id` int(50) NOT NULL,
  `u_fname` varchar(200) NOT NULL,
  `u_lname` varchar(200) NOT NULL,
  `u_email` varchar(200) NOT NULL,
  `u_username` varchar(200) NOT NULL,
  `u_password` varchar(200) NOT NULL,
  `u_type` varchar(200) NOT NULL,
  `u_status` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_user`
--

INSERT INTO `tbl_user` (`u_id`, `u_fname`, `u_lname`, `u_email`, `u_username`, `u_password`, `u_type`, `u_status`) VALUES
(18, 'Scarlett', 'Williams', 'bukeke@mailinator.com', 'kogicosa', 'Pa$$w0rd!', 'admin', 'active'),
(20, 'Rize', 'Chan', 'Rize@gmail.com', 'Rizee', '$2y$10$.Pr5z4b2D4EWEq5SbYozlepjWFxEAhGeBpThqWi4O793b0rkGp80m', 'admin', 'Active'),
(21, 'Astark', 'Mayormita', 'larraverallo@gmail.com', 'Astark', '$2y$10$tacqlNHSmJWBh3M4dgWJNe3PJmYfkIeUllONrdTARDaYg8NSadOZS', 'admin', 'Active'),
(22, 'John', 'Wilyam', 'jonwilyammayormita@gmail.com', 'Stark', '$2y$10$z.cDRaq6kxiXAB6CAXHM4OUch5jFsrGbRYlwdQ6SONixDKcSrb6C6', 'admin', 'archived'),
(23, 'Ryan', 'Cansancio', 'jhonsepayla@yahoo.com', 'Ryan', '$2y$10$PxP0Kuq6wRU4J0QXLxuwFerGro3.cQoL6nc9shd0KIlUSKeix0if6', 'admin', 'Active'),
(25, 'John Wilyam', 'Wilyam', 'xugecev@mailinator.com', 'WilyamSama', '$2y$10$izLTdpzNFvJ7mfjs014Jj.7IzygMHQ6wCABcpbDFIn3PaP5/Ihs1O', 'admin', 'archived'),
(26, 'Xyla', 'Salinas', 'qiko@mailinator.com', 'qovalony', '$2y$10$1w/hBAF/J5QdrUtj4mtMhuBXuoPDIri6WAm4lYW.MXclYSDsOrit6', 'admin', 'archived'),
(27, 'Fiona', 'Rogers', 'fiona@gmail.com', 'Fiona Chan', '$2y$10$mJbipq.7nSIizFJAXf04POmIqSQdEQcDGat7lBXb9rbFdm35aHoTu', 'staff', 'active'),
(28, 'Illana', 'Alston', 'sijirugy@mailinator.com', 'meminubof', '$2y$10$RRcEmlzVVhhi6Uk.4Hh24OLfN0KkdCiJ4q5bnIcUKav7z4n8E85dq', 'admin', 'archived'),
(29, 'Brianna', 'Macias', 'dixypof@mailinator.com', 'Test', '$2y$10$b6e7YnYWZmwukVMoYV7X.eNkszNxKp3dHnr7dV4MYZ7kf57BClCli', 'staff', 'active'),
(30, 'Ursula', 'Walls', 'qofowoxoto@mailinator.com', 'Meowa', '$2y$10$VH3kXpwA6guV8aV4wg30Ne.grXF14qCOK9jImO7BjQzFhER3wGQye', 'admin', 'active'),
(31, 'Meredith', 'Dunlap', 'wojyx@mailinator.com', 'gypewa', '$2y$10$E2is5g3ncyrtNHdWXU8pH.aIig1PaeCxUCNozYLHvcYDPvG0iZxzG', 'admin', 'archived'),
(32, 'awawawa', 'wawawawawa', 'waw@gmail.com', 'oicnasnac', '$2y$10$EUZJMcTTpel2tHpMjIgT6.kDty.VTXcPU2rWbBWuARUFCdZCMao/W', 'admin', 'active'),
(33, 'awawawawa', 'wawawawaawaa', 'waw@gmail.com', 'oicnasnac12', '$2y$10$lNctiWTaf5CP1FYhlhgl3ehntsiEOMfsBnjnbjlq2BKHb9WOAYPa.', 'staff', 'active'),
(34, 'awawawawa', 'cansancio', 'waw@gmail.com', 'oicnasnac1234', '$2y$10$xVlsAy4RAGbUNwPIxTy6BuO2kCO3eoZ8aDJWw10h64CQ.RuPh6maC', 'staff', 'active'),
(36, 'Nattyy De Coco', 'Tillman', 'rage@mailinator.com', 'Natty', '$2y$10$JJd2ZIQG6nEBu8dZ7SRULufGWNQb9fl/OSeXzFi63/T6UguImRE/G', 'staff', 'active'),
(37, 'Tatyana', 'Hayden', 'jimeruveby@mailinator.com', 'Test ni ha', '$2y$10$GiKnS6yTtPo6qudbPLqITeo4fFbcBUjN2PucdtFhfPJnjYrQbyJP6', 'technician', 'active'),
(38, 'awaw', 'awawa', 'wwawaw@gmail.com', 'hahaha123', '$2y$10$sWiqat3sALnS5uxgTFEDpuCC0Rh6Jguk1HA0zNVfvbqbJgpN7eWj2', 'admin', 'active'),
(39, 'awaww', 'awawaww', 'wawa@gmail.com', 'haha12', '$2y$10$xwO0/0PFHhAk7weRGYyilefdTDQZomFIeH1HWRGswsasu2TLE86Vy', 'staff', 'active'),
(40, 'Mikha', 'Lim', 'mikhamylove@gmail.com', 'Mikha Lim', '$2y$10$wgKdbJ5BKYDxrU0GcdOTiuHchprGX8eKjWkB.cTtONksqZKRshZ4S', 'staff', 'active'),
(41, 'Kolehe', 'Kai', 'kolohekai@gmail.com', 'Kolohe', '$2y$10$p0uaBuH3RfB6BxHsbgIguevieEhZ9CplkIFXB50PetTP.0eFfmfkm', 'technician', 'active'),
(42, 'Zia', 'Jackson', 'cohulyjuha@mailinator.com', 'Zia', '$2y$10$ll3uoOyEKUDsdZeOOdPpAezyfFEVxkM0amJp3ILjtqWy/1fNWlRr.', 'technician', 'active'),
(43, 'Suzy', 'Bae', 'devizipot@mailinator.com', 'Suzyy', '$2y$10$AZueEfn.JBOkOzacbym8/OIANPAxZHMJpBmlX2FLgWPghFNTFZlU.', 'staff', 'active'),
(44, 'Admin', 'Test', 'admin@gmail.com', 'Admin', '$2y$10$LBq.HvsUTSzqerTOqSIfa.TGAsjEa3VokSXfB4NpNHhnQNRAC.dPO', 'admin', 'active'),
(45, 'Yumi', 'Chan', 'sohynyq@gmail.com', 'Yumi Chan', '$2y$10$GInrYkcHcu9PIrbehLTksO0MmcG4lAAZXC7Tgfiy0iaDXBJQ5mo0S', 'technician', 'active'),
(51, 'Aiah', 'Love', 'aiahlove@gmail.com', 'Aiahkins', '$2y$10$zthsh5racOm7RvvGDHj2i.xQV4jHp/.yS9gemChTZtsHfABSb/WsO', 'staff', 'active'),
(53, 'Zeph', 'Patel', 'huhezeru@mailinator.com', 'ADMINN', '$2y$10$rCAUF7yizAqKOlXGNmlf5OuO.wX.X5ZV2I4FBrBHMR3J3.qRo6V2u', 'admin', 'active'),
(54, 'Joelle', 'Graves', 'vefud@mailinator.com', 'TestT', '$2y$10$YcCrLftfHHg/JVNrXCJsO.x0pJ3vXRtukqGjj8OS00jDuA2JQez3i', 'technician', 'active'),
(55, 'Germaine', 'Gray', 'williammayormita69@gmail.com', 'Jawil', '$2y$10$8ZbZIaaUs001K4sgyso0VeizbeCokz.6bTY2hIzonOsr3.lLhZYWy', 'staff', 'active'),
(64, 'Samantha', 'Holloway', 'ruvixir@mailinator.com', 'Kysecu', '$2y$10$wUkZRXsNrZoYjgFYOX.HCecyTc2xzvPuP2VqrB7cprW7M7ZVmUUz.', 'admin', 'active'),
(65, 'haahays', 'haha', 'ryancansanco7@gmail.com', 'tech12', '$2y$10$qUzZa9kDhvkiXKNyO/7P3.5ZSm7ZMsxzVBd6wk2h1ro8fcOhv6oju', 'technician', 'active'),
(66, 'haha', 'hahah', 'ryancansanco7@gmail.com', 'techs', '$2y$10$DX/P7vKhwur7pt0n/2Y8f.qWbIwhm6/bYR5tcE5afBbYq3Vvvjb22', 'technician', 'active'),
(67, 'cha', 'hae', 'ryancansanco7@gmail.com', 'cha12', '$2y$10$yS2zmOWYPzWjIedtnrBuveQkMns4h.nOiks1ENdaqJHlilyh9rIzm', 'staff', 'active'),
(68, 'Stephen', 'Silva', 'Stephensilva@gmail.com', 'Stephen', '$2y$10$iLzCtJL1.gX/0CPTFGj9ae5SkSMlYH.BQ9xcow73jaPLPEoBr81RC', 'admin', 'active'),
(69, 'awawawawaaw', 'awawawwaw', 'haysss@gmail.com', 'ryan12345', '$2y$10$FJwstGNN0uVUz45URai4iu3U4uk2uMEvoSAeo1VFvDhtT8Wy463mm', 'admin', 'active'),
(70, 'awawaw', 'awawawawaw', 'awawawwaw@gmail.com', 'rizalday', '$2y$10$skuNrLDcfpRX0Vp5tn7oeuZqQUGwJ/UA3.72m26eHrQGZ0njlMVO2', 'admin', 'active'),
(71, 'awawaww', 'awaww', 'wwawaw@gmail.com', 'sprite', '$2y$10$6KdLZORtkdIXT3U4MNuBwONFhlBS/S82HJZf/aueW/GEzHJI.1riC', 'admin', 'active'),
(72, 'Ray', 'Quan', 'rayquan@gmail.com', 'Ray Quan', '$2y$10$RLxHYJDYEbnbKUG3SSh3/uSKKSpr7hJNK3w.7s6Az4Unw9dFtn0IW', 'staff', 'active'),
(73, 'Pham', 'Hanny', 'hanny@gmail.com', 'Hanni', '$2y$10$XSj9STnj0nWrnUF1V5tBEuN7Wa1IjLyw523qyVGdUbaH2FSbCpzzK', 'technician', 'active'),
(74, 'ryan', 'cansancio', 'ryan@gmail.com', 'joyuri', '$2y$10$AMaBJBi.DQbVYtHdHbvSledLRhCRT7YQUYiwGCXN/6j1JLahWjVy6', 'staff', 'active'),
(75, 'joyuri', 'cansancio', 'ryan@gmail.com', 'xie123', '$2y$10$lITLq46Lz9/ARfv.bIFOI.rr/b9NyPhyzGYBgkl7/dTjsXX6lgRNS', 'admin', 'active'),
(76, 'xiexie', 'Ryan', 'cansancio@gmail.com', 'ryanryan', '$2y$10$Twa624BuQNGvxsp3ZU.Z..ABQJpv2XACHo6nJy2cI5YoO9QaqLaei', 'staff', 'active'),
(77, 'tech', 'admin', 'admin@gmail.com', 'techsss', '$2y$10$8ROUBP0Hkx6rcidFGZdGM.ZvzYMVZe0Z6lQNFtmfdGShBIhyF6p0G', 'technician', 'active'),
(78, 'awaww', 'awawaw', 'ryancansancio7@gmail.com', 'xixi', '$2y$10$es3RXqsiRb2Yz32Vh815C.tNC9Y63KO7KBmBzAK9Fd7a8HKT/ZC0S', 'staff', 'active'),
(79, 'ryan', 'cansancio', 'ryancansancio7@gmail.com', 'xixie123', '$2y$10$SsTJdeVIhmM6ue2E5Eb1sOGcGw2ElHoiN3.IcPzqeSHvfzopKE20u', 'staff', 'active'),
(80, 'awaw', 'awawa', 'ryancansancio7@gmail.com', 'xixiee123', '$2y$10$96.iOjVSOgKNr79pfKRhseBiYTDlKtoLcNTO6Sd5fOrWJ7shEwz.y', 'staff', 'active'),
(81, 'awa', 'awaa', 'ryancansancio7@gmail.com', 'alaxan123', '$2y$10$R6i5pZHuNreKjhlLh8GPOeMRyITpc6zP7gtngyTHR34vadTH9S.KS', 'staff', 'archived'),
(82, 'Cyrel', 'Bini', 'agboncyrelann@gmail.com', 'Xai Cy', '$2y$10$ItjsvGzwVDzghGU77.UONeNRn..yBsK3NVM/fm2nq5xXX5R2u1L2m', 'technician', 'active'),
(83, 'brother', 'cansancio', 'ryancansancio7@gmail.com', 'brother', '$2y$10$chkUHWMGYc4NzuXwomUe4eNmxIs6k9TdvLjSMzBwG3oeGJXeSWd1S', 'technician', 'active'),
(84, 'jorge', 'bugwak', 'ryancansancio7@gmail.com', 'jorge', '$2y$10$ZlIrmry5Pg1sgzxmoWS2IuOXtjDLFz2HlcaGt5LNW8fA2BVre/dQG', 'technician', 'active'),
(85, 'georgia', 'jorge', 'ryancansancio7@gmail.com', 'georgia', '$2y$10$2RiTTGEzTMXCb5KX6Dquo.STvfHpFGk0FabxaSX6As2B.97pRqRz.', 'technician', 'active'),
(86, 'latina', 'georgiana', 'ryancansancio7@gmail.com', 'ansakit', '$2y$10$F3UzAY9I0ZCqNPwS1CxPs.W7LhNdINcGVigTQIh8mtvZ54CwZs3s2', 'technician', 'active'),
(87, 'awaaa', 'awaaa', 'ryancansancio7@gmail.com', 'okaayra', '$2y$10$omU.MZOpmgkVqp5fasiQCueDQTsmnuwu3A7TY2mL4ig7qsx7Ja7g.', 'technician', 'active'),
(88, 'Moon', 'Stars', 'ryancansancio7@gmail.com', 'samson', '$2y$10$JsHuYRgU10Du0vmNQaoqbuHKadF.ZH/MbZzPM6l/rZk2v4OLRsFKq', 'staff', 'active'),
(89, 'awaw', 'awawa', 'ryancansancio7@gmail.com', 'akoysayooo', '$2y$10$DQyZZP4.MGauMxb6ExSoS.80oaLi.9FsvuuxUX9Dyedvx9zgDom.2', 'staff', 'active'),
(90, 'awawa', 'wawawaw', 'ryancansancio7@gmail.com', 'Moon123', '$2y$10$NzZ/M5VOep4zLNrAs0LGOOk0Xfeg.oYYwxSmi4LWU7Jn9Ex4oLDPq', 'staff', 'active'),
(91, 'awa', 'awa', 'ryancansancio7@gmail.com', 'Moons', '$2y$10$x7xHkpu84nato9SKRUHCy.N.pNSUdtOfh3zs5dWIAIdQKZlVewnDm', 'staff', 'active'),
(92, 'awa', 'awawa', 'awawawaw@gmail.com', 'pending', '$2y$10$h2tzliinmbWxVjCGPxFvOO/KV03K876dSgaZKQpguZw5iWZEcVSpK', 'staff', 'pending'),
(93, 'awaw', 'awaw', 'ryancansancio7@gmail.com', 'arayymo', '$2y$10$iSZ/cIHdhTZO72bjhkgFie46qL18AL4fSBRI6J2NZ/UqiLHRj/ojG', 'staff', 'active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_assets`
--
ALTER TABLE `tbl_assets`
  ADD PRIMARY KEY (`a_id`);

--
-- Indexes for table `tbl_asset_status`
--
ALTER TABLE `tbl_asset_status`
  ADD PRIMARY KEY (`a_id`);

--
-- Indexes for table `tbl_customer`
--
ALTER TABLE `tbl_customer`
  ADD PRIMARY KEY (`c_id`);

--
-- Indexes for table `tbl_supp_tickets`
--
ALTER TABLE `tbl_supp_tickets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_ticket`
--
ALTER TABLE `tbl_ticket`
  ADD PRIMARY KEY (`t_id`);

--
-- Indexes for table `tbl_transactions`
--
ALTER TABLE `tbl_transactions`
  ADD PRIMARY KEY (`t_id`);

--
-- Indexes for table `tbl_user`
--
ALTER TABLE `tbl_user`
  ADD PRIMARY KEY (`u_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_assets`
--
ALTER TABLE `tbl_assets`
  MODIFY `a_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `tbl_asset_status`
--
ALTER TABLE `tbl_asset_status`
  MODIFY `a_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tbl_customer`
--
ALTER TABLE `tbl_customer`
  MODIFY `c_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `tbl_supp_tickets`
--
ALTER TABLE `tbl_supp_tickets`
  MODIFY `id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `tbl_ticket`
--
ALTER TABLE `tbl_ticket`
  MODIFY `t_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `tbl_transactions`
--
ALTER TABLE `tbl_transactions`
  MODIFY `t_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tbl_user`
--
ALTER TABLE `tbl_user`
  MODIFY `u_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
