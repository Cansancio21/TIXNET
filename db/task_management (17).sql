-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 27, 2025 at 06:56 PM
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
-- Table structure for table `tbl_archive`
--

CREATE TABLE `tbl_archive` (
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
-- Dumping data for table `tbl_archive`
--

INSERT INTO `tbl_archive` (`u_id`, `u_fname`, `u_lname`, `u_email`, `u_username`, `u_password`, `u_type`, `u_status`) VALUES
(52, 'Miriam', 'Cain', 'zirozak@mailinator.com', 'AdminTest', '$2y$10$QCyUE/GnyoYL.ot7nSz7OuX.3lf1BISAne/z9DUz1wNCBfutoIuAC', 'admin', 'active'),
(16, 'Huh', 'Yun', 'huhjennifer@gmail.com', 'Rize', '$2y$10$Og60E6rElNb8jpKCV51OOeNb6Hx2iilWoYCxFH3NV42TjsPEQs2Ea', 'admin', 'active'),
(17, 'John William', 'Mayor', 'jonwilyammayormita@gmail.com', 'Astark123', '$2y$10$TIr2Iy4HbODOaqgZ0akJbeibY.n8TCmEP5sbHGAfpbE1uYl2Mco1u', 'admin', 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_assets`
--

CREATE TABLE `tbl_assets` (
  `a_id` int(50) NOT NULL,
  `a_name` varchar(200) NOT NULL,
  `a_status` varchar(200) NOT NULL,
  `a_quantity` int(50) NOT NULL,
  `a_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_assets`
--

INSERT INTO `tbl_assets` (`a_id`, `a_name`, `a_status`, `a_quantity`, `a_date`) VALUES
(1, 'Ladder', 'Borrowing', 1, '2025-05-16'),
(2, 'Modemsss', 'Archived', 9, '2025-05-16'),
(3, 'Wire', 'Archived', 16, '2025-05-16'),
(4, 'Fiber Optic Cables', 'Archived', 13, '2025-05-16'),
(5, 'Example', 'Archived', 3, '2025-05-16'),
(6, 'example', 'Deployment', 2, '2025-05-21'),
(7, 'fiber', 'Deployment', 10, '2025-05-14'),
(8, 'fiber', 'Borrowing', 10, '2025-05-18'),
(9, 'example', 'Borrowing', 2, '2025-05-13'),
(10, 'optic', 'Deployment', 5, '2025-05-13'),
(11, 'example', 'Borrowing', 2, '2025-05-13'),
(12, 'knife', 'Borrowing', 9, '2025-05-19'),
(13, 'Tester', 'Borrowing', 5, '2025-05-19'),
(14, 'optal', 'Borrowing', 5, '2025-05-10'),
(15, 'fibrics', 'Borrowing', 7, '2025-05-21'),
(16, 'Test', 'Deployment', 15, '2025-05-23');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_borrowed`
--

CREATE TABLE `tbl_borrowed` (
  `b_id` int(50) NOT NULL,
  `b_assets_name` varchar(100) NOT NULL,
  `b_quantity` int(50) NOT NULL,
  `b_technician_name` varchar(100) NOT NULL,
  `b_date` date NOT NULL,
  `b_technician_id` int(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_borrowed`
--

INSERT INTO `tbl_borrowed` (`b_id`, `b_assets_name`, `b_quantity`, `b_technician_name`, `b_date`, `b_technician_id`) VALUES
(31, 'Ladder', 10, 'Tatyana Hayden', '2025-05-23', 37);

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
('ref#-27-07-2025-567344', 'ryan cansancio', 'haahays haha', 'aray koo', 'closed', 'okaay', '2025-07-27 00:14:14');

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
  `c_billstatus` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_customer`
--

INSERT INTO `tbl_customer` (`c_id`, `c_fname`, `c_lname`, `c_purok`, `c_barangay`, `c_contact`, `c_email`, `c_date`, `c_napname`, `c_napport`, `c_macaddress`, `c_status`, `c_plan`, `c_equipment`, `c_account_no`, `c_coordinates`, `c_balance`, `c_startdate`, `c_nextdue`, `c_lastdue`, `c_nextbill`, `c_billstatus`) VALUES
(29, 'Lyle', 'Barr', 'Lance Heath', 'Deleniti dolore est', '25690765564', 'moluxonu@mailinator.com', '2025-05-24', 'Lp1 Np8', 5, 'Aconsecteturerror', 'Active', 'Plan 2500', 'Customer-Owned', 69037768, 'Reprehenderit atque', 0, '2025-07-23', '2025-08-23', '', '2025-08-23', 'Active'),
(30, 'David', 'Burns', 'Maris Ferguson', 'Aut nostrud veniam', '77445677712', 'demarexe@mailinator.com', '2025-05-24', 'Lp1 Np5', 7, 'AbNamomnisquisin123', 'Active', 'Plan 999', 'ISP-Provided Modem/Router', 85849371, '14.23245,562.67523', 0, '2025-07-23', '2025-08-23', '', '2025-08-23', 'Active'),
(31, 'Eve', 'Rosa', 'Aileen Colon', 'In et ut dolorem max', '802', 'darywyje@mailinator.com', '2025-05-24', 'Lp1 Np6', 4, 'PorroNamullamcosi', 'Active', 'Plan 1299', 'ISP-Provided Modem/Router', 52111545, 'Quasi a recusandae', 0, '', '', '', '', ''),
(32, 'John William', 'Mayormita', 'Purok Tambis', 'Banhigan', '09394578940', 'demarexe@mailinator.com', '2025-07-20', 'Lp1 Np2', 3, 'testing', 'Active', 'Plan 1499', 'ISP-Provided Modem/Router', 59993635, 'Reprehenderitatque', 0, '', '', '', '', ''),
(33, 'ryan', 'cansancio', 'tambis', 'ward', '0900909099', 'ryancansancio7@gmail.com', '2025-07-26', 'Lp1 Np6', 5, 'ward', 'Active', 'Plan 2500', 'ISP-Provided Modem/Router', 83775848, 'awawawaw', 0, '', '', '', '', '');

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
  `s_status` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_customer_ticket`
--

INSERT INTO `tbl_customer_ticket` (`s_ref`, `c_id`, `c_fname`, `c_lname`, `s_subject`, `s_message`, `s_status`) VALUES
('ref#-23-05-2025-511113', 9, 'Latifah', 'Sims', 'redlight', 'okaay kaayo', 'Approved'),
('ref#-23-05-2025-565607', 9, 'Latifah', 'Sims', 'stoplight', 'bugnaw', 'Approved'),
('ref#-23-05-2025-627429', 11, 'Lunea', 'Mendez', 'redlight', 'no net', 'Approved'),
('ref#-23-05-2025-758042', 9, 'Latifah', 'Sims', 'stop', 'okay nako bahala nani', 'Approved'),
('ref#-23-05-2025-884487', 9, 'Latifah', 'Sims', 'okaay', 'goods', 'Rejected'),
('ref#-23-05-2025-331671', 11, 'Lunea', 'Mendez', 'okaay kaayo', 'natuk an ko', 'Rejected'),
('ref#-23-05-2025-716828', 9, 'Latifah', 'Sims', 'okaay rako', 'bantay btw', 'Rejected'),
('ref#-23-05-2025-951343', 9, 'Latifah', 'Sims', 'okaay kaayo', 'bugnaw', 'Approved'),
('ref#-23-05-2025-803167', 9, 'Latifah', 'Sims', 'qpal', 'okaay', 'Approved'),
('ref#-27-06-2025-928737', 1, 'awawawa', 'wawawawaw', 'test', 'testing purposes', 'Approved'),
('ref#-27-06-2025-477366', 1, 'awawawa', 'wawawawaw', 'test', 'for testing', 'Approved'),
('ref#-27-06-2025-980002', 1, 'awawawa', 'wawawawaw', 'test test', 'for testing purposes', 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_deployed`
--

CREATE TABLE `tbl_deployed` (
  `d_id` int(50) NOT NULL,
  `d_assets_name` varchar(100) NOT NULL,
  `d_quantity` int(50) NOT NULL,
  `d_technician_name` varchar(100) NOT NULL,
  `d_date` varchar(100) NOT NULL,
  `d_technician_id` int(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_deployed`
--

INSERT INTO `tbl_deployed` (`d_id`, `d_assets_name`, `d_quantity`, `d_technician_name`, `d_date`, `d_technician_id`) VALUES
(1, 'Modem', 6, 'Tatyana Hayden', '2025-04-21', 37);

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
('2025-07-27 00:14:18', 'Technician haahays haha', 'Ticket ref#-27-07-2025-567344 closed by technician haahays haha (Type: regular)');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_reject_ticket`
--

CREATE TABLE `tbl_reject_ticket` (
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
-- Dumping data for table `tbl_reject_ticket`
--

INSERT INTO `tbl_reject_ticket` (`s_ref`, `c_id`, `c_fname`, `c_lname`, `s_subject`, `s_message`, `s_status`, `s_remarks`) VALUES
('ref#-23-05-2025-884487', 9, 'Latifah', 'Sims', 'okaay', 'goods', 'Rejected', ''),
('ref#-23-05-2025-331671', 11, 'Lunea', 'Mendez', 'okaay kaayo', 'natuk an ko', 'Rejected', ''),
('ref#-23-05-2025-716828', 9, 'Latifah', 'Sims', 'okaay rako', 'bantay btw', 'Rejected', 'tarunga na imong pag report qpal kaba');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_returned`
--

CREATE TABLE `tbl_returned` (
  `r_id` int(50) NOT NULL,
  `r_assets_name` varchar(200) NOT NULL,
  `r_quantity` int(50) NOT NULL,
  `r_technician_name` varchar(200) NOT NULL,
  `r_technician_id` int(50) NOT NULL,
  `r_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_returned`
--

INSERT INTO `tbl_returned` (`r_id`, `r_assets_name`, `r_quantity`, `r_technician_name`, `r_technician_id`, `r_date`) VALUES
(2, 'Example', 5, 'Tatyana Hayden', 37, '2025-03-31'),
(3, 'Modems', 5, 'Tatyana Hayden', 37, '2025-03-31'),
(4, 'Example', 3, 'Tatyana Hayden', 37, '2025-03-31'),
(5, 'Modems', 5, 'Tatyana Hayden', 37, '2025-03-31'),
(6, 'Modems', 8, 'Tatyana Hayden', 37, '2025-03-31'),
(7, 'Modems', 2, 'Tatyana Hayden', 37, '2025-03-31'),
(17, 'Modems', 4, 'Tatyana Hayden', 37, '2025-03-31'),
(18, 'Bukog', 1, 'Tatyana Hayden', 37, '2025-03-31'),
(21, 'Fiber Optic Cable', 15, 'Tatyana Hayden', 37, '2025-04-05'),
(22, 'Fiber Optic Cable', 30, 'Tatyana Hayden', 37, '2025-04-05'),
(23, 'Fiber Optic Cable', 20, 'Tatyana Hayden', 37, '2025-04-10'),
(24, 'Fiber Optic Cable', 1, 'Yumi Chan', 45, '2025-04-26'),
(25, 'Fiber Optic Cable', 1, 'Yumi Chan', 45, '2025-04-25'),
(26, 'Bukog', 2, 'Yumi Chan', 45, '2025-04-24'),
(27, 'Fiber Optic Cable', 1, 'Tatyana Hayden', 37, '2025-04-24'),
(28, 'Fiber Optic Cable', 5, 'Yumi Chan', 45, '2025-04-24'),
(29, 'Wire', 5, 'Kolehe Kai', 41, '2025-05-16'),
(30, 'Wire', 3, 'Kolehe Kai', 41, '2025-05-16'),
(31, 'Wire', 3, 'Kolehe Kai', 41, '2025-05-16'),
(32, 'Modems', 5, 'Joelle Graves', 54, '2025-05-16'),
(33, 'Wire', 1, 'Kolehe Kai', 41, '2025-05-16'),
(34, 'Wire', 1, 'Kolehe Kai', 41, '2025-05-16'),
(35, 'Fiber Optic Cable', 3, 'Yumi Chan', 45, '2025-05-16'),
(37, 'Wire', 1, 'Kolehe Kai', 41, '2025-05-16'),
(38, 'Ladder', 4, 'Kolehe Kai', 41, '2025-05-16'),
(39, 'Ladder', 1, 'Kolehe Kai', 41, '2025-05-16'),
(43, 'Example', 2, 'Kolehe Kai', 41, '2025-05-16'),
(44, 'Fiber Optic Cable', 2, 'Yumi Chan', 45, '2025-05-16'),
(45, 'Fiber Optic Cable', 1, 'Yumi Chan', 45, '2025-05-16'),
(46, 'Example', 1, 'Tatyana Hayden', 37, '2025-05-19'),
(47, 'knife', 4, 'Tatyana Hayden', 37, '2025-05-19'),
(48, 'Tester', 3, 'Kolehe Kai', 41, '2025-05-19');

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
  `id` int(11) NOT NULL,
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
(1, 'wawawawaw', 'awawawa', 'ref#-27-06-2025-477366', 'test', 'for testing', 'Open', 32, '');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_techborrowed`
--

CREATE TABLE `tbl_techborrowed` (
  `b_assets_name` varchar(200) NOT NULL,
  `b_quantity` int(50) NOT NULL,
  `b_technician_name` varchar(200) NOT NULL,
  `b_date` varchar(100) NOT NULL,
  `b_technician_id` int(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_techborrowed`
--

INSERT INTO `tbl_techborrowed` (`b_assets_name`, `b_quantity`, `b_technician_name`, `b_date`, `b_technician_id`) VALUES
('Example', 6, 'Kolehe Kai', '2025-05-16', 41),
('Example', 6, 'Kolehe Kai', '2025-05-16', 41),
('Modems', 1, 'Joelle Graves', '2025-05-17', 54),
('knife', 1, 'Tatyana Hayden', '2025-05-19', 37),
('Ladder', 10, 'Tatyana Hayden', '2025-05-23', 37);

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
(18, 'Scarlett', 'Williams', 'bukeke@mailinator.com', 'kogicosa', 'Pa$$w0rd!', 'admin', 'pending'),
(19, 'Rose', 'Donovan', 'lywawusype@mailinator.com', 'fuqum', '$2y$10$IVgruo96Y8NbSfhn/CYqKOn6Ma..swAN1NphEeL/DAOb0tqkYhnPe', 'Admin', 'Pending'),
(20, 'Rize', 'Chan', 'Rize@gmail.com', 'Rizee', '$2y$10$.Pr5z4b2D4EWEq5SbYozlepjWFxEAhGeBpThqWi4O793b0rkGp80m', 'admin', 'Active'),
(21, 'Astark', 'Mayormita', 'larraverallo@gmail.com', 'Astark', '$2y$10$tacqlNHSmJWBh3M4dgWJNe3PJmYfkIeUllONrdTARDaYg8NSadOZS', 'admin', 'Active'),
(22, 'John', 'Wilyam', 'jonwilyammayormita@gmail.com', 'Stark', '$2y$10$z.cDRaq6kxiXAB6CAXHM4OUch5jFsrGbRYlwdQ6SONixDKcSrb6C6', 'admin', 'active'),
(23, 'Ryan', 'Cansancio', 'jhonsepayla@yahoo.com', 'Ryan', '$2y$10$PxP0Kuq6wRU4J0QXLxuwFerGro3.cQoL6nc9shd0KIlUSKeix0if6', 'admin', 'Active'),
(25, 'John Wilyam', 'Wilyam', 'xugecev@mailinator.com', 'WilyamSama', '$2y$10$izLTdpzNFvJ7mfjs014Jj.7IzygMHQ6wCABcpbDFIn3PaP5/Ihs1O', 'admin', 'pending'),
(26, 'Xyla', 'Salinas', 'qiko@mailinator.com', 'qovalony', '$2y$10$1w/hBAF/J5QdrUtj4mtMhuBXuoPDIri6WAm4lYW.MXclYSDsOrit6', 'admin', 'pending'),
(27, 'Fiona', 'Rogers', 'fiona@gmail.com', 'Fiona Chan', '$2y$10$mJbipq.7nSIizFJAXf04POmIqSQdEQcDGat7lBXb9rbFdm35aHoTu', 'staff', 'active'),
(28, 'Illana', 'Alston', 'sijirugy@mailinator.com', 'meminubof', '$2y$10$RRcEmlzVVhhi6Uk.4Hh24OLfN0KkdCiJ4q5bnIcUKav7z4n8E85dq', 'admin', 'pending'),
(29, 'Brianna', 'Macias', 'dixypof@mailinator.com', 'Test', '$2y$10$b6e7YnYWZmwukVMoYV7X.eNkszNxKp3dHnr7dV4MYZ7kf57BClCli', 'staff', 'active'),
(30, 'Ursula', 'Walls', 'qofowoxoto@mailinator.com', 'Meowa', '$2y$10$VH3kXpwA6guV8aV4wg30Ne.grXF14qCOK9jImO7BjQzFhER3wGQye', 'admin', 'active'),
(31, 'Meredith', 'Dunlap', 'wojyx@mailinator.com', 'gypewa', '$2y$10$E2is5g3ncyrtNHdWXU8pH.aIig1PaeCxUCNozYLHvcYDPvG0iZxzG', 'admin', 'pending'),
(32, 'awawawa', 'wawawawawa', 'waw@gmail.com', 'oicnasnac', '$2y$10$EUZJMcTTpel2tHpMjIgT6.kDty.VTXcPU2rWbBWuARUFCdZCMao/W', 'admin', 'active'),
(33, 'awawawawa', 'wawawawaawaa', 'waw@gmail.com', 'oicnasnac12', '$2y$10$lNctiWTaf5CP1FYhlhgl3ehntsiEOMfsBnjnbjlq2BKHb9WOAYPa.', 'staff', 'active'),
(34, 'awawawawa', 'wawawawaawaa', 'waw@gmail.com', 'oicnasnac1234', '$2y$10$xVlsAy4RAGbUNwPIxTy6BuO2kCO3eoZ8aDJWw10h64CQ.RuPh6maC', 'staff', 'active'),
(35, 'awawawa', 'wawawawawa', 'waw@gmail.com', 'oicnasnac123', '$2y$10$HrYeqM5sk0e8gTZaRnJDau0JSPQp1NAe6UC4r4NllomMSaFbPvFJG', 'admin', 'active'),
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
(81, 'awa', 'awaa', 'ryancansancio7@gmail.com', 'alaxan123', '$2y$10$R6i5pZHuNreKjhlLh8GPOeMRyITpc6zP7gtngyTHR34vadTH9S.KS', 'staff', 'active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_assets`
--
ALTER TABLE `tbl_assets`
  ADD PRIMARY KEY (`a_id`);

--
-- Indexes for table `tbl_borrowed`
--
ALTER TABLE `tbl_borrowed`
  ADD PRIMARY KEY (`b_id`);

--
-- Indexes for table `tbl_customer`
--
ALTER TABLE `tbl_customer`
  ADD PRIMARY KEY (`c_id`);

--
-- Indexes for table `tbl_deployed`
--
ALTER TABLE `tbl_deployed`
  ADD PRIMARY KEY (`d_id`);

--
-- Indexes for table `tbl_returned`
--
ALTER TABLE `tbl_returned`
  ADD PRIMARY KEY (`r_id`);

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
  MODIFY `a_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `tbl_borrowed`
--
ALTER TABLE `tbl_borrowed`
  MODIFY `b_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `tbl_customer`
--
ALTER TABLE `tbl_customer`
  MODIFY `c_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `tbl_deployed`
--
ALTER TABLE `tbl_deployed`
  MODIFY `d_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_returned`
--
ALTER TABLE `tbl_returned`
  MODIFY `r_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `tbl_supp_tickets`
--
ALTER TABLE `tbl_supp_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `tbl_ticket`
--
ALTER TABLE `tbl_ticket`
  MODIFY `t_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `tbl_user`
--
ALTER TABLE `tbl_user`
  MODIFY `u_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
