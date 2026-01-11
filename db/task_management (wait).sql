-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 08, 2025 at 05:51 PM
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
  `a_specs` varchar(200) NOT NULL,
  `a_cycle` varchar(200) NOT NULL,
  `a_condition` varchar(200) NOT NULL,
  `a_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_assets`
--

INSERT INTO `tbl_assets` (`a_id`, `a_ref_no`, `a_name`, `a_status`, `a_current_status`, `a_quantity`, `a_serial_no`, `a_specs`, `a_cycle`, `a_condition`, `a_date`) VALUES
(82, 'WIRE-001', 'Wire', 'Deployment', 'Available', 1, '', 'tulo ka meter', 'Reusable', 'Brand New', '2025-12-08'),
(83, 'WIRE-002', 'Wire', 'Deployment', 'Available', 1, '', 'tulo ka meter', 'Reusable', 'Brand New', '2025-12-08'),
(84, 'WIRE-003', 'Wire', 'Deployment', 'Available', 1, '', 'tulo ka meter', 'Reusable', 'Brand New', '2025-12-08'),
(85, 'WIRE-004', 'Wire', 'Deployment', 'Available', 1, '', 'tulo ka meter', 'Reusable', 'Brand New', '2025-12-08'),
(86, 'WIRE-005', 'Wire', 'Deployment', 'Available', 1, '', 'tulo ka meter', 'Reusable', 'Brand New', '2025-12-08'),
(87, 'MODEM-001', 'Modem', 'Deployment', 'Archived', 1, '', 'example', 'Reusable', 'Brand New', '2025-12-08'),
(88, 'MODEM-002', 'Modem', 'Deployment', 'Available', 1, '', 'example', 'Reusable', 'Brand New', '2025-12-08'),
(89, 'MODEM-003', 'Modem', 'Deployment', 'Available', 1, '', 'example', 'Reusable', 'Brand New', '2025-12-08'),
(90, 'MODEM-004', 'Modem', 'Deployment', 'Available', 1, '', 'example', 'Reusable', 'Brand New', '2025-12-08'),
(91, 'MODEM-005', 'Modem', 'Deployment', 'Available', 1, '', 'example', 'Reusable', 'Brand New', '2025-12-08'),
(92, 'UTPCABLE-001', 'UTP Cable', 'Deployment', 'Available', 1, '', 'cut six, straight thru', 'Non-reusable', 'Slightly Used', '2025-12-08'),
(93, 'UTPCABLE-002', 'UTP Cable', 'Deployment', 'Available', 1, '', 'cut six, straight thru', 'Non-reusable', 'Slightly Used', '2025-12-08'),
(94, 'UTPCABLE-003', 'UTP Cable', 'Deployment', 'Available', 1, '', 'cut six, straight thru', 'Non-reusable', 'Slightly Used', '2025-12-08'),
(95, 'UTPCABLE-004', 'UTP Cable', 'Deployment', 'Available', 1, '', 'cut six, straight thru', 'Non-reusable', 'Slightly Used', '2025-12-08'),
(96, 'UTPCABLE-005', 'UTP Cable', 'Deployment', 'Available', 1, '', 'cut six, straight thru', 'Non-reusable', 'Slightly Used', '2025-12-08'),
(97, 'UTPCABLE-006', 'UTP Cable', 'Deployment', 'Available', 1, '', 'cut six, straight thru', 'Non-reusable', 'Slightly Used', '2025-12-08'),
(98, 'UTPCABLE-007', 'UTP Cable', 'Deployment', 'Available', 1, '', 'cut six, straight thru', 'Non-reusable', 'Slightly Used', '2025-12-08'),
(99, 'UTPCABLE-008', 'UTP Cable', 'Deployment', 'Available', 1, '', 'cut six, straight thru', 'Non-reusable', 'Slightly Used', '2025-12-08'),
(100, 'UTPCABLE-009', 'UTP Cable', 'Deployment', 'Available', 1, '', 'cut six, straight thru', 'Non-reusable', 'Slightly Used', '2025-12-08'),
(101, 'UTPCABLE-010', 'UTP Cable', 'Deployment', 'Available', 1, '', 'cut six, straight thru', 'Non-reusable', 'Slightly Used', '2025-12-08'),
(102, 'ROUTER-001', 'Router', 'Borrowing', 'Borrowed', 1, 'ADXGTVF123G', 'for customer', 'Reusable', 'Brand New', '2025-12-08');

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
(5, 'LADDER-003', 'Ladder', 'tech admin', 77, '0', '2025-09-01', '2025-10-28', 'Returned'),
(9, 'SUPERMODEM-001', 'Super Modem', 'Tatyana Hayden', 37, '0', '2025-09-03', '0000-00-00', 'Deployed'),
(10, 'TESTER-001', 'Tester', 'Tatyana Hayden', 37, '', '2025-09-03', '2025-09-03', 'Returned'),
(11, 'LADDER-001', 'Ladder', 'Tatyana Hayden', 37, '0', '2025-09-04', '2025-10-27', 'Returned'),
(14, 'OPTICALLIGHTMETERS-001', 'Optical Light Meters', 'jorge bugwak', 84, '', '2025-10-26', '0000-00-00', 'Deployed'),
(15, 'POWERADAPTERS-001', 'Power Adapter', 'Tatyana Hayden', 37, 'FAB12X3456Y', '2025-10-26', '0000-00-00', 'Returned'),
(16, 'OPTICALLIGHTMETERS-002', 'Optical Light Meters', 'Tatyana Hayden', 37, '', '2025-10-28', '0000-00-00', 'Deployed'),
(17, 'ROUTER-001', 'Router', 'Tatyana Hayden', 37, 'ADXGTVF123G', '2025-12-08', '0000-00-00', 'Borrowed');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_close_regular`
--

CREATE TABLE `tbl_close_regular` (
  `r_id` int(50) NOT NULL,
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

INSERT INTO `tbl_close_regular` (`r_id`, `t_ref`, `t_aname`, `te_technician`, `t_subject`, `t_status`, `t_details`, `te_date`) VALUES
(1, 'ref#-22-05-2025-107976', 'Haley Waller', 'haahays haha', 'stoplight', 'closed', 'awawaw', '2025-07-26 22:52:31'),
(2, 'ref#-26-07-2025-281372', 'David Burns', 'haahays haha', 'awaw', 'closed', 'awaw', '2025-07-27 00:06:41'),
(3, 'ref#-27-07-2025-567344', 'ryan cansancio', 'haahays haha', 'aray koo', 'closed', 'okaay', '2025-07-27 00:14:14'),
(4, 'ref#-10-13-2025-067198', 'ryan cansancio', 'tech admin', 'redlight', 'Closed', 'awawwa', '2025-10-13 13:42:47'),
(5, 'ref#-10-26-2025-754303', 'ryan cansancio', 'tech admin', 'redlight', 'Closed', 'no connection', '2025-10-26 19:13:44');

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
('ref#-04-10-2025-209485', 33, 'techsss', 'ryan', 'cansancio', 'Moon', 'Stars', 'closed', '2025-10-04 10:01:41'),
('ref#-04-10-2025-245305', 33, 'techsss', 'ryan', 'cansancio', 'Moon', 'Moon', 'closed', '2025-10-04 10:15:26'),
('ref#-05-09-2025-457302', 29, 'techsss', 'Lyle', 'Barr', 'Faster', 'hjfyujyjy', 'closed', '2025-10-06 11:34:29'),
('ref#-06-10-2025-366867', 33, 'techsss', 'ryan', 'cansancio', 'akoako', 'akoako', 'closed', '2025-10-06 12:08:38'),
('ref#-04-09-2025-511310', 29, 'tech admin', '', '', 'kapoy', 'fdvdfsgdrg', 'Closed', '2025-10-13 13:21:45'),
('ref#-13-10-2025-182410', 33, 'tech admin', '', '', 'Moon', 'Stars', 'Closed', '2025-10-13 17:57:13'),
('ref#-15-10-2025-967398', 33, 'tech admin', '', '', 'awawa', 'awawaw', 'Closed', '2025-10-15 14:52:21'),
('ref#-15-10-2025-967398', 33, 'tech admin', '', '', 'awawa', 'awawaw', 'Closed', '2025-10-15 15:12:18'),
('ref#-15-10-2025-507101', 33, 'tech admin', '', '', 'awawawawaw', 'awawawaw', 'Closed', '2025-10-15 15:12:25'),
('ref#-15-10-2025-507101', 33, 'tech admin', '', '', 'awawawawaw', 'awawawaw', 'Closed', '2025-10-26 14:42:55'),
('ref#-26-10-2025-476475', 33, 'tech admin', '', '', 'unstable connection', 'Our internet connection is not consistently dropping completely, but it becomes extremely slow and unresponsive multiple times per hour, especially in the evenings', 'Closed', '2025-10-26 19:15:13'),
('ref#-26-10-2025-301131', 33, 'tech admin', '', '', 'Moon', 'wawaw', 'Closed', '2025-10-26 19:15:33');

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
(30, 'David', 'Burnsss', 'Maris Ferguson', 'Aut nostrud veniam', '77445677712', 'demarexe@mailinator.com', '2025-05-24', 'Lp1 Np5', 7, 'AbNamomnisquisin123', 'Active', 'Plan 999', 'ISP-Provided Modem/Router', 85849371, '14.23245,562.67523', -1000, '2025-08-03', '2025-12-05', '2025-11-04', '2025-12-05', 'Active', '0', 0),
(31, 'Eve', 'Rosa', 'Aileen Colon', 'In et ut dolorem max', '802', 'darywyje@mailinator.com', '2025-05-24', 'Lp1 Np6', 4, 'PorroNamullamcosi', 'Active', 'Plan 1299', 'ISP-Provided Modem/Router', 52111545, 'Quasi a recusandae', 0, '2025-08-03', '2025-12-05', '2025-11-04', '2025-12-05', 'Active', '0', 0),
(32, 'John William', 'Mayormita', 'Purok Tambis', 'Banhigan', '09394578940', 'demarexe@mailinator.com', '2025-07-20', 'Lp1 Np2', 3, 'testing', 'Active', 'Plan 1499', 'ISP-Provided Modem/Router', 59993635, 'Reprehenderitatque', 1, '2025-08-06', '2025-12-08', '2025-11-07', '2025-12-08', 'Active', '0', 0),
(33, 'ryan', 'cansancio', 'tambis', 'ward', '0900909099', 'ryancansancio7@gmail.com', '2025-07-26', 'Lp1 Np6', 5, 'ward', 'Active', 'Plan 2500', 'ISP-Provided Modem/Router', 83775848, 'awawawaw', 500, '2025-08-03', '2025-12-05', '2025-11-04', '2025-12-05', 'Active', '0', 0),
(34, 'Mikha', 'Lim', 'Purok Wildflower', 'Pakigne', '09560390918', 'williammayormita69@gmail.com', '2025-08-14', 'Lp1 Np1', 4, 'Amzxksdn', 'Active', 'Plan 799', 'Customer-Owned', 31010041, '142.342.234.234', 1001, '2025-08-15', '2025-12-17', '2025-11-16', '2025-12-09', 'Active', '8', 0),
(35, 'Pham', 'Hanni', 'Curvada', 'Banhigan', '092345678918', 'williammayormita69@gmail.com', '2025-08-14', 'Lp1 Np1', 6, 'ahsshAJAJSDB', 'Active', 'Plan 1799', 'ISP-Provided Modem/Router', 48926186, '142.342.234.234', 201, '2025-08-15', '2025-12-17', '2025-11-16', '2025-12-09', 'Active', '8', 0),
(36, 'cy', 'xai', 'Purok Wildflower', 'Pakigne', '09999999999', 'xaicy@gmail.com', '2025-09-05', 'Lp1 Np1', 1, 'mingla', 'Active', 'Plan 1499', 'ISP-Provided Modem/Router', 66686351, '142.342.234.234', 0, '', '', '', '', '', '', 0),
(37, 'ryanss', 'cansancioss', 'tambis', 'ward', '09090909', 'ryancansancio7@gmail.com', '2025-10-16', 'Lp1 Np5', 5, 'awaa', 'Active', 'Plan 1999', 'ISP-Provided Modem/Router', 89718865, 'awaawaaw', 0, '2025-10-16', '2025-12-17', '2025-11-16', '2025-12-15', 'Active', '2', 0),
(38, 'ryansss', 'cansancio', 'tambiss', 'ward', '090909', 'ryancansancio7@gmail.com', '2025-10-16', 'Lp1 Np5', 4, 'macccc', 'Active', 'Plan 1999', 'ISP-Provided Modem/Router', 51720197, 'aawawaw', 1, '2025-10-28', '2025-11-28', '', '2025-11-21', 'Active', '7', 0),
(39, 'Amal', 'Hawkins', 'Eleanor Kinney', 'Optio debitis elit', '529', 'fypowubacu@mailinator.com', '2025-10-26', 'Lp1 Np6', 7, '5:AF:92:1A:7B:21', 'Active', 'Plan 799', 'ISP-Provided Modem/Router', 26491069, '48.8584, 2.2945', 0, '2025-10-28', '2025-11-28', '', '2025-11-23', 'Active', '5', 0),
(40, 'Carolyn', 'Burt', 'Alexandra Oliver', 'Nostrum id repudiand', '882', 'qucor@mailinator.com', '2025-10-26', 'Lp1 Np6', 2, '54:AF:92:1A:7B:21', 'Active', 'Plan 1799', 'ISP-Provided Modem/Router', 86338536, 'Voluptatem Providen', 0, '2025-10-28', '2025-11-28', '', '2025-11-21', 'Active', '7', 0),
(41, 'Bae', 'Suzy', 'Medge Lloyd', 'Magnam obcaecati quo', '0909090909', 'williammayormita69@gmail.com', '2025-10-26', 'Lp1 Np4', 8, '54:AF:92:1A:7B:21', 'Active', 'Plan 999', 'ISP-Provided Modem/Router', 98675902, '14.12345,121.67890', 0, '2025-10-26', '2025-11-26', '', '2025-11-19', 'Active', '7', 0),
(42, 'Brent', 'Hatfield', 'Virginia Hamilton', 'Pakigne', '0909090909', 'williammayormita69@gmail.com', '2025-10-27', 'Lp1 Np4', 2, '54:AF:92:1A:7B:21', 'Active', 'Plan 999', 'ISP-Provided Modem/Router', 10485694, '123.234.234', 0, '2025-10-28', '2025-11-28', '', '2025-11-21', 'Active', '7', 0),
(43, 'Michelle', 'Pearson', 'Donovan Roman', 'Ward II', '0909090909', 'wyqyx@mailinator.com', '2025-10-27', 'Lp1 Np7', 6, 'WESFX425DST', 'Active', 'Plan 2500', 'Customer-Owned', 15599828, '123.234.234', 0, '2025-10-28', '2025-11-28', '', '2025-11-21', 'Active', '7', 0);

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
('ref#-13-10-2025-182410', 33, 'ryan', 'cansancio', 'Moon', 'Stars', 'Approved', ''),
('ref#-15-10-2025-967398', 33, 'ryan', 'cansancio', 'awawa', 'awawaw', 'Approved', ''),
('ref#-15-10-2025-507101', 33, 'ryan', 'cansancio', 'awawawawaw', 'awawawaw', 'Approved', ''),
('ref#-26-10-2025-301131', 33, 'ryan', 'cansancio', 'Moon', 'wawaw', 'Approved', ''),
('ref#-26-10-2025-476475', 33, 'ryan', 'cansancio', 'unstable connection', 'Our internet connection is not consistently dropping completely, but it becomes extremely slow and unresponsive multiple times per hour, especially in the evenings', 'Approved', ''),
('ref#-21-11-2025-977917', 31, 'Eve', 'Rosa', 'awawaw', 'awaw', 'Declined', 'awawaw');

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
('2025-10-08 13:43:04', 'ryan cansancio', 'has successfully logged in'),
('2025-10-08 13:51:17', 'ryan cansancio', 'has successfully logged in'),
('2025-10-08 14:42:42', 'ryan cansancio', 'has successfully logged in'),
('2025-10-08 14:57:49', 'ryan cansancio', 'has successfully logged in'),
('2025-10-08 15:08:19', 'ryan cansancio', 'has successfully logged in'),
('2025-10-08 15:16:57', 'ryan cansancio', 'has successfully logged in'),
('2025-10-08 16:14:59', 'ryan cansancio', 'has successfully logged in'),
('2025-10-08 16:33:25', 'ryan cansancio', 'has successfully logged in'),
('2025-10-08 16:35:59', 'awaww awawaww', 'has successfully logged in'),
('2025-10-08 16:35:59', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-08 16:39:12', 'awaww awawaww', 'has successfully logged in'),
('2025-10-08 16:43:17', 'awaww awawaww', 'has successfully logged in'),
('2025-10-08 16:50:46', 'awaww awawaww', 'has successfully logged in'),
('2025-10-08 17:28:44', 'tech admin', 'has successfully logged in'),
('2025-10-08 17:31:44', 'ryan cansancio', 'has successfully logged in'),
('2025-10-08 17:32:15', 'ryan cansancio', 'has successfully logged in'),
('2025-10-08 17:43:49', 'awaww awawaww', 'has successfully logged in'),
('2025-10-08 17:45:35', 'ryan cansancio', 'has successfully logged in'),
('2025-10-08 17:50:04', 'awaww awawaww', 'has successfully logged in'),
('2025-10-08 17:51:04', 'awaww awawaww', 'has successfully logged in'),
('2025-10-08 18:00:17', 'tech admin', 'has successfully logged in'),
('2025-10-08 18:01:52', 'ryan cansancio', 'has successfully logged in'),
('2025-10-08 18:06:12', 'xiexie Ryan', 'has successfully logged in'),
('2025-10-08 18:18:36', 'Staff xiexie Ryan', 'Staff xiexie Ryan archived ticket ref#-09-14-2025-536663'),
('2025-10-08 18:19:45', 'tech admin', 'has successfully logged in'),
('2025-10-08 18:23:35', 'ryan cansancio', 'has successfully logged in'),
('2025-10-08 18:25:02', 'awaww awawaww', 'has successfully logged in'),
('2025-10-08 18:35:09', 'tech admin', 'has successfully logged in'),
('2025-10-13 13:04:37', 'tech admin', 'has successfully logged in'),
('2025-10-13 13:06:09', 'tech admin', 'has successfully logged in'),
('2025-10-13 13:21:49', 'Technician tech admin', 'Ticket ref#-04-09-2025-511310 closed by technician tech admin (Type: support) - Email notification sent'),
('2025-10-13 13:22:25', 'awaww awawaww', 'has successfully logged in'),
('2025-10-13 13:22:26', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-13 13:22:41', 'Staff awaww awawaww', 'Created ticket #ref#-10-13-2025-067198 for customer ryan cansancio'),
('2025-10-13 13:23:07', 'Staff awaww awawaww', 'Assigned ticket ref#-10-13-2025-067198 to technician techsss by awaww awawaww (Type: regular)'),
('2025-10-13 13:23:17', 'tech admin', 'has successfully logged in'),
('2025-10-13 13:42:51', 'Technician tech admin', 'Ticket ref#-10-13-2025-067198 closed by technician tech admin (Type: regular)'),
('2025-10-13 13:43:17', 'awaww awawaww', 'has successfully logged in'),
('2025-10-13 13:43:17', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-13 14:48:43', 'ryan cansancio', 'has successfully logged in'),
('2025-10-13 15:23:43', 'awaww awawaww', 'has successfully logged in'),
('2025-10-13 15:40:02', 'ryan cansancio', 'has successfully logged in'),
('2025-10-13 15:41:09', 'awaww awawaww', 'has successfully logged in'),
('2025-10-13 17:55:18', 'ryan cansancio', 'has successfully logged in'),
('2025-10-13 17:55:49', 'customer ryan cansancio', 'created ticket ref#-13-10-2025-182410'),
('2025-10-13 17:56:30', 'awaww awawaww', 'has successfully logged in'),
('2025-10-13 17:56:35', 'Staff awaww awawaww', 'Staff awaww awawaww approved customer ticket ref#-13-10-2025-182410 for customer ryan cansancio'),
('2025-10-13 17:56:46', 'awaww awawaww', 'has successfully logged in'),
('2025-10-13 17:56:57', 'Staff awaww awawaww', 'Assigned ticket ref#-13-10-2025-182410 to technician techsss by awaww awawaww (Type: support)'),
('2025-10-13 17:57:08', 'tech admin', 'has successfully logged in'),
('2025-10-13 17:57:16', 'Technician tech admin', 'Ticket ref#-13-10-2025-182410 closed by technician tech admin (Type: support)'),
('2025-10-13 17:58:03', 'ryan cansancio', 'has successfully logged in'),
('2025-10-13 18:05:16', 'awaww awawaww', 'has successfully logged in'),
('2025-10-13 18:05:17', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-13 18:05:39', 'tech admin', 'has successfully logged in'),
('2025-10-13 18:16:09', 'awaww awawaww', 'has successfully logged in'),
('2025-10-13 18:16:09', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-13 18:17:43', 'tech admin', 'has successfully logged in'),
('2025-10-13 18:23:57', 'tech admin', 'has successfully logged in'),
('2025-10-13 18:35:43', 'awaww awawaww', 'has successfully logged in'),
('2025-10-13 18:35:43', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-13 18:35:59', 'Staff awaww awawaww', 'Created ticket #ref#-10-13-2025-055302 for customer ryan cansancio'),
('2025-10-13 18:36:19', 'Staff awaww awawaww', 'Assigned ticket ref#-10-13-2025-055302 to technician techsss by awaww awawaww (Type: regular)'),
('2025-10-13 18:36:45', 'tech admin', 'has successfully logged in'),
('2025-10-13 18:36:58', 'Technician tech admin', 'Ticket ref#-10-13-2025-055302 closed by technician tech admin (Type: regular)'),
('2025-10-15 13:23:26', 'ryan cansancio', 'has successfully logged in'),
('2025-10-15 13:33:35', 'ryan cansancio', 'has successfully logged in'),
('2025-10-15 13:33:46', 'customer ryan cansancio', 'archived ticket ref#-02-10-2025-538165'),
('2025-10-15 14:05:38', 'customer ryan cansancio', 'archived ticket ref#-02-10-2025-771863'),
('2025-10-15 14:06:38', 'customer ryan cansancio', 'archived ticket ref#-02-10-2025-376857'),
('2025-10-15 14:09:19', 'customer ryan cansancio', 'archived ticket ref#-04-10-2025-209485'),
('2025-10-15 14:09:34', 'customer ryan cansancio', 'archived ticket ref#-04-10-2025-245305'),
('2025-10-15 14:09:50', 'customer ryan cansancio', 'archived ticket ref#-06-10-2025-366867'),
('2025-10-15 14:10:12', 'customer ryan cansancio', 'archived ticket ref#-13-10-2025-182410'),
('2025-10-15 14:10:42', 'customer ryan cansancio', 'unarchived ticket ref#-02-10-2025-538165'),
('2025-10-15 14:10:56', 'customer ryan cansancio', 'unarchived ticket ref#-02-10-2025-771863'),
('2025-10-15 14:11:20', 'customer ryan cansancio', 'unarchived ticket ref#-02-10-2025-376857'),
('2025-10-15 14:11:32', 'customer ryan cansancio', 'archived ticket ref#-02-10-2025-538165'),
('2025-10-15 14:11:43', 'customer ryan cansancio', 'unarchived ticket ref#-02-10-2025-538165'),
('2025-10-15 14:11:56', 'customer ryan cansancio', 'archived ticket ref#-02-10-2025-538165'),
('2025-10-15 14:12:21', 'customer ryan cansancio', 'created ticket ref#-15-10-2025-967398'),
('2025-10-15 14:14:30', 'awaww awawaww', 'has successfully logged in'),
('2025-10-15 14:14:31', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-15 14:49:58', 'ryan cansancio', 'has successfully logged in'),
('2025-10-15 14:50:16', 'awaww awawaww', 'has successfully logged in'),
('2025-10-15 14:50:22', 'Staff awaww awawaww', 'Staff awaww awawaww approved customer ticket ref#-15-10-2025-967398 for customer ryan cansancio'),
('2025-10-15 14:50:47', 'ryan cansancio', 'has successfully logged in'),
('2025-10-15 14:51:30', 'customer ryan cansancio', 'archived ticket ref#-02-10-2025-771863'),
('2025-10-15 14:51:37', 'customer ryan cansancio', 'archived ticket ref#-02-10-2025-376857'),
('2025-10-15 14:51:58', 'awaww awawaww', 'has successfully logged in'),
('2025-10-15 14:52:09', 'Staff awaww awawaww', 'Assigned ticket ref#-15-10-2025-967398 to technician techsss by awaww awawaww (Type: support)'),
('2025-10-15 14:52:16', 'tech admin', 'has successfully logged in'),
('2025-10-15 14:52:25', 'Technician tech admin', 'Ticket ref#-15-10-2025-967398 closed by technician tech admin (Type: support)'),
('2025-10-15 14:52:37', 'ryan cansancio', 'has successfully logged in'),
('2025-10-15 14:52:55', 'customer ryan cansancio', 'archived ticket ref#-15-10-2025-967398'),
('2025-10-15 14:53:04', 'customer ryan cansancio', 'unarchived ticket ref#-15-10-2025-967398'),
('2025-10-15 15:11:23', 'customer ryan cansancio', 'created ticket ref#-15-10-2025-507101'),
('2025-10-15 15:11:39', 'awaww awawaww', 'has successfully logged in'),
('2025-10-15 15:11:40', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-15 15:11:45', 'Staff awaww awawaww', 'Staff awaww awawaww approved customer ticket ref#-15-10-2025-507101 for customer ryan cansancio'),
('2025-10-15 15:11:57', 'Staff awaww awawaww', 'Assigned ticket ref#-15-10-2025-507101 to technician techsss by awaww awawaww (Type: support)'),
('2025-10-15 15:12:05', 'Staff awaww awawaww', 'Assigned ticket ref#-15-10-2025-967398 to technician techsss by awaww awawaww (Type: support)'),
('2025-10-15 15:12:12', 'tech admin', 'has successfully logged in'),
('2025-10-15 15:12:22', 'Technician tech admin', 'Ticket ref#-15-10-2025-967398 closed by technician tech admin (Type: support)'),
('2025-10-15 15:12:29', 'Technician tech admin', 'Ticket ref#-15-10-2025-507101 closed by technician tech admin (Type: support)'),
('2025-10-15 15:12:41', 'ryan cansancio', 'has successfully logged in'),
('2025-10-15 15:12:51', 'customer ryan cansancio', 'archived ticket ref#-15-10-2025-507101'),
('2025-10-15 15:13:06', 'customer ryan cansancio', 'unarchived ticket ref#-15-10-2025-507101'),
('2025-10-15 16:07:33', 'customer ryan cansancio', 'archived ticket ref#-15-10-2025-967398'),
('2025-10-15 16:07:41', 'customer ryan cansancio', 'unarchived ticket ref#-15-10-2025-967398'),
('2025-10-15 16:09:30', 'awaww awawaww', 'has successfully logged in'),
('2025-10-15 16:09:30', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-15 17:21:52', 'awaww awawaww', 'has successfully logged in'),
('2025-10-15 23:04:00', 'awaww awawaww', 'has successfully logged in'),
('2025-10-15 23:04:00', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-15 23:38:53', 'awaww awawaww', 'has successfully logged in'),
('2025-10-16 23:25:20', 'awaww awawaww', 'has successfully logged in'),
('2025-10-16 23:25:21', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-17 00:46:06', 'awaww awawaww', 'has successfully logged in'),
('2025-10-17 01:47:07', 'tech admin', 'has successfully logged in'),
('2025-10-17 02:06:20', 'ryan cansancio', 'has successfully logged in'),
('2025-10-17 02:27:47', 'awaww awawaww', 'has successfully logged in'),
('2025-10-17 02:29:01', 'tech admin', 'has successfully logged in'),
('2025-10-17 02:29:25', 'ryan cansancio', 'has successfully logged in'),
('2025-10-18 14:47:30', 'awaww awawaww', 'has successfully logged in'),
('2025-10-18 14:47:30', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-18 15:05:02', 'awaww awawaww', 'has successfully logged in'),
('2025-10-18 15:10:29', 'awaww awawaww', 'has successfully logged in'),
('2025-10-18 15:11:14', 'tech admin', 'has successfully logged in'),
('2025-10-18 15:23:27', 'ryan cansancio', 'has successfully logged in'),
('2025-10-18 15:30:47', 'awaww awawaww', 'has successfully logged in'),
('2025-10-18 15:30:47', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-18 15:47:23', 'awaww awawaww', 'has successfully logged in'),
('2025-10-18 15:47:23', 'Staff awaww awawaww', 'Staff awaww has successfully logged in'),
('2025-10-18 16:08:17', 'awaww awawaww', 'has successfully logged in'),
('2025-10-26 14:24:11', '', 'Admin awawawawaaw awawawwaw deleted closed regular ticket Ref# ref#-08-15-2025-358045'),
('2025-10-26 14:24:14', '', 'Admin awawawawaaw awawawwaw deleted closed support ticket Ref# ref#-02-10-2025-376857'),
('2025-10-26 14:24:17', '', 'Admin awawawawaaw awawawwaw deleted closed support ticket Ref# ref#-02-10-2025-538165'),
('2025-10-26 14:28:28', '', 'Admin awawawawaaw awawawwaw deleted closed regular ticket Ref# ref#-09-14-2025-242664'),
('2025-10-26 14:28:32', '', 'Admin awawawawaaw awawawwaw deleted closed support ticket Ref# ref#-02-10-2025-771863'),
('2025-10-26 14:28:38', '', 'Admin awawawawaaw awawawwaw deleted closed support ticket Ref# ref#-04-09-2025-312296'),
('2025-10-26 14:33:18', 'awawaw awawaww', 'has successfully logged in'),
('2025-10-26 14:33:18', 'Staff awawaw awawaww', 'Staff awawaw has successfully logged in'),
('2025-10-26 14:34:18', 'Staff awawaw awawaww', 'Staff awawaw awawaww edited ticket ref#-09-14-2025-708102 ticket details'),
('2025-10-26 14:34:23', 'Staff awawaw awawaww', 'Staff awawaw awawaww archived ticket ref#-09-14-2025-708102'),
('2025-10-26 14:34:27', 'Staff awawaw awawaww', 'Staff awawaw awawaww unarchived ticket ref#-09-12-2025-898305'),
('2025-10-26 14:34:52', 'Staff awawaw awawaww', 'Staff awawaw awawaww deleted ticket ref#-09-14-2025-803480'),
('2025-10-26 14:35:51', 'Staff awawaw awawaww', 'Staff awawaw awawaww closed ticket ref#-09-12-2025-898305 (was assigned to haha12)'),
('2025-10-26 14:36:16', 'Staff awawaw awawaww', 'Created ticket #ref#-10-26-2025-735797 for customer ryan cansancio'),
('2025-10-26 14:41:17', 'Staff awawaw awawaww', 'Assigned ticket ref#-15-10-2025-507101 to technician techsss by awawaw awawaww (Type: support)'),
('2025-10-26 14:41:23', 'Staff awawaw awawaww', 'Toggled status for technician techsss to Unavailable by awawaw awawaww'),
('2025-10-26 14:41:25', 'Staff awawaw awawaww', 'Toggled status for technician Hanni to Unavailable by awawaw awawaww'),
('2025-10-26 14:41:28', 'Staff awawaw awawaww', 'Toggled status for technician Test ni ha to Unavailable by awawaw awawaww'),
('2025-10-26 14:41:30', 'Staff awawaw awawaww', 'Toggled status for technician Kolohe to Unavailable by awawaw awawaww'),
('2025-10-26 14:41:31', 'Staff awawaw awawaww', 'Toggled status for technician Zia to Unavailable by awawaw awawaww'),
('2025-10-26 14:41:32', 'Staff awawaw awawaww', 'Toggled status for technician Yumi Chan to Unavailable by awawaw awawaww'),
('2025-10-26 14:41:33', 'Staff awawaw awawaww', 'Toggled status for technician TestT to Unavailable by awawaw awawaww'),
('2025-10-26 14:41:35', 'Staff awawaw awawaww', 'Toggled status for technician Yumi Chan to Available by awawaw awawaww'),
('2025-10-26 14:41:36', 'Staff awawaw awawaww', 'Toggled status for technician Kolohe to Available by awawaw awawaww'),
('2025-10-26 14:41:36', 'Staff awawaw awawaww', 'Toggled status for technician Test ni ha to Available by awawaw awawaww'),
('2025-10-26 14:41:36', 'Staff awawaw awawaww', 'Toggled status for technician Zia to Available by awawaw awawaww'),
('2025-10-26 14:41:38', 'Staff awawaw awawaww', 'Toggled status for technician tech12 to Unavailable by awawaw awawaww'),
('2025-10-26 14:41:39', 'Staff awawaw awawaww', 'Toggled status for technician techs to Unavailable by awawaw awawaww'),
('2025-10-26 14:41:40', 'Staff awawaw awawaww', 'Toggled status for technician tech12 to Available by awawaw awawaww'),
('2025-10-26 14:41:40', 'Staff awawaw awawaww', 'Toggled status for technician TestT to Available by awawaw awawaww'),
('2025-10-26 14:41:41', 'Staff awawaw awawaww', 'Toggled status for technician techs to Available by awawaw awawaww'),
('2025-10-26 14:41:42', 'Staff awawaw awawaww', 'Toggled status for technician Hanni to Available by awawaw awawaww'),
('2025-10-26 14:41:44', 'Staff awawaw awawaww', 'Toggled status for technician Xai Cy to Unavailable by awawaw awawaww'),
('2025-10-26 14:41:46', 'Staff awawaw awawaww', 'Toggled status for technician Xai Cy to Available by awawaw awawaww'),
('2025-10-26 14:41:48', 'Staff awawaw awawaww', 'Toggled status for technician techsss to Available by awawaw awawaww'),
('2025-10-26 14:42:47', 'tech admin', 'has successfully logged in'),
('2025-10-26 14:42:59', 'Technician tech admin', 'Ticket ref#-15-10-2025-507101 closed by technician tech admin (Type: support)'),
('2025-10-26 14:43:48', 'ryan cansancio', 'has successfully logged in'),
('2025-10-26 14:44:28', 'customer ryan cansancio', 'archived ticket ref#-15-10-2025-967398'),
('2025-10-26 14:44:36', 'customer ryan cansancio', 'unarchived ticket ref#-02-10-2025-538165'),
('2025-10-26 14:44:46', 'customer ryan cansancio', 'deleted ticket ref#-02-10-2025-771863'),
('2025-10-26 14:45:07', 'customer ryan cansancio', 'created ticket ref#-26-10-2025-301131'),
('2025-10-26 14:53:36', 'awaww awawaww', 'has successfully logged in'),
('2025-10-26 14:55:22', 'awaww awawaww', 'has successfully logged in'),
('2025-10-26 14:57:53', 'awaww awawaww', 'has successfully logged in'),
('2025-10-26 15:26:34', 'Staff awaww awawaww', 'Created ticket #ref#-10-26-2025-754303 for customer ryan cansancio'),
('2025-10-26 15:37:34', 'Staff awaww awawaww', 'Created ticket #ref#-10-26-2025-219630 for customer ryan cansancio'),
('2025-10-26 15:40:32', '', 'Admin awawawawaaw awawawwaw deleted closed regular ticket Ref# ref#-09-12-2025-898305'),
('2025-10-26 15:40:42', '', 'Admin awawawawaaw awawawwaw deleted closed regular ticket Ref# ref#-09-14-2025-851882'),
('2025-10-26 15:40:45', '', 'Admin awawawawaaw awawawwaw deleted closed regular ticket Ref# ref#-09-15-2025-193019'),
('2025-10-26 15:41:12', '', 'Admin awawawawaaw awawawwaw deleted closed regular ticket Ref# ref#-09-16-2025-982641'),
('2025-10-26 15:48:32', '', 'Admin awawawawaaw awawawwaw deleted closed regular ticket Ref# ref#-09-29-2025-000348'),
('2025-10-26 15:49:16', '', 'Admin awawawawaaw awawawwaw deleted closed regular ticket Ref# ref#-10-04-2025-219683'),
('2025-10-26 15:49:21', '', 'Admin awawawawaaw awawawwaw deleted closed regular ticket Ref# ref#-10-13-2025-055302'),
('2025-10-26 16:53:45', 'awaww awawaww', 'has successfully logged in'),
('2025-10-26 17:36:40', 'awaww awawaww', 'has successfully logged in'),
('2025-10-26 17:43:37', 'awaww awawaww', 'has successfully logged in'),
('2025-10-26 17:44:05', 'Staff awaww awawaww', 'Created ticket #ref#-10-26-2025-798332 for customer ryan cansancio'),
('2025-10-26 17:44:42', 'Staff awaww awawaww', 'Staff awaww awawaww archived ticket ref#-10-26-2025-798332'),
('2025-10-26 17:45:12', 'Staff awaww awawaww', 'Staff awaww awawaww unarchived ticket ref#-10-26-2025-798332'),
('2025-10-26 17:45:21', 'Staff awaww awawaww', 'Staff awaww awawaww deleted ticket ref#-09-14-2025-708102'),
('2025-10-26 17:48:51', 'Staff awaww awawaww', 'Staff awaww awawaww closed ticket ref#-10-26-2025-798332'),
('2025-10-26 17:52:31', 'ryan awawaww', 'has successfully logged in'),
('2025-10-26 17:53:43', 'ryan awawaww', 'has successfully logged in'),
('2025-10-26 17:55:28', 'ryan awawaww', 'has successfully logged in'),
('2025-10-26 18:02:56', 'Staff haha12', 'Staff haha12 added 1 asset(s) named \'Power Adapters\''),
('2025-10-26 18:07:52', 'Staff haha12', 'Staff haha12 borrowed 1 asset(s) to technician Tatyana Hayden (ID: 37)'),
('2025-10-26 18:09:11', 'Staff haha12', 'Staff haha12 added 5 asset(s) named \'Optical Light Meters\''),
('2025-10-26 18:10:48', 'Staff haha12', 'Staff haha12 added 2 asset(s) named \'Optical Light Meters\''),
('2025-10-26 18:15:25', 'Staff haha12', 'Staff haha12 deployed 1 asset(s) to technician jorge bugwak (ID: 84)'),
('2025-10-26 18:16:58', 'Staff haha12', 'Staff haha12 returned 1 asset(s)'),
('2025-10-26 18:19:59', 'ryan awawaww', 'has successfully logged in'),
('2025-10-26 18:22:24', 'Staff ryan awawaww', 'Staff ryan awawaww approved customer ticket ref#-26-10-2025-301131 for customer ryan cansancio'),
('2025-10-26 18:22:43', 'ryan cansancio', 'has successfully logged in'),
('2025-10-26 18:25:57', 'customer ryan cansancio', 'created ticket ref#-26-10-2025-476475'),
('2025-10-26 18:26:33', 'ryan awawaww', 'has successfully logged in'),
('2025-10-26 18:26:57', 'Staff ryan awawaww', 'Staff ryan awawaww approved customer ticket ref#-26-10-2025-476475 for customer ryan cansancio'),
('2025-10-26 18:27:10', 'ryan cansancio', 'has successfully logged in'),
('2025-10-26 18:28:44', 'ryan awawaww', 'has successfully logged in'),
('2025-10-26 18:30:41', 'Staff ryan awawaww', 'Created ticket #ref#-10-26-2025-574648 for customer ryan cansancio'),
('2025-10-26 18:31:57', 'ryan awawaww', 'has successfully logged in'),
('2025-10-26 18:45:26', 'ryan awawaww', 'has successfully logged in'),
('2025-10-26 18:45:26', 'Staff ryan awawaww', 'Staff ryan has successfully logged in'),
('2025-10-26 18:54:33', 'Staff ryan awawaww', 'Toggled status for technician Test ni ha to Unavailable by ryan awawaww'),
('2025-10-26 18:54:34', 'Staff ryan awawaww', 'Toggled status for technician Test ni ha to Available by ryan awawaww'),
('2025-10-26 18:57:15', 'Staff ryan awawaww', 'Toggled status for technician Test ni ha to Unavailable by ryan awawaww'),
('2025-10-26 18:57:24', 'Staff ryan awawaww', 'Toggled status for technician Test ni ha to Available by ryan awawaww'),
('2025-10-26 18:57:34', 'Staff ryan awawaww', 'Toggled status for technician Test ni ha to Unavailable by ryan awawaww'),
('2025-10-26 18:57:42', 'Staff ryan awawaww', 'Toggled status for technician Test ni ha to Available by ryan awawaww'),
('2025-10-26 18:57:52', 'Staff ryan awawaww', 'Toggled status for technician Kolohe to Unavailable by ryan awawaww'),
('2025-10-26 18:57:59', 'Staff ryan awawaww', 'Toggled status for technician Test ni ha to Unavailable by ryan awawaww'),
('2025-10-26 18:58:02', 'Staff ryan awawaww', 'Toggled status for technician Zia to Unavailable by ryan awawaww'),
('2025-10-26 18:58:12', 'Staff ryan awawaww', 'Toggled status for technician Test ni ha to Available by ryan awawaww'),
('2025-10-26 18:58:13', 'Staff ryan awawaww', 'Toggled status for technician Kolohe to Available by ryan awawaww'),
('2025-10-26 18:58:15', 'Staff ryan awawaww', 'Toggled status for technician Zia to Available by ryan awawaww'),
('2025-10-26 19:08:03', 'tech admin', 'has successfully logged in'),
('2025-10-26 19:09:13', 'ryan awawaww', 'has successfully logged in'),
('2025-10-26 19:09:13', 'Staff ryan awawaww', 'Staff ryan has successfully logged in'),
('2025-10-26 19:09:59', 'Staff ryan awawaww', 'Assigned ticket ref#-26-10-2025-476475 to technician techsss by ryan awawaww (Type: support)'),
('2025-10-26 19:10:08', 'Staff ryan awawaww', 'Assigned ticket ref#-26-10-2025-301131 to technician techsss by ryan awawaww (Type: support)'),
('2025-10-26 19:10:19', 'Staff ryan awawaww', 'Assigned ticket ref#-10-26-2025-574648 to technician techsss by ryan awawaww (Type: regular)'),
('2025-10-26 19:10:37', 'Staff ryan awawaww', 'Assigned ticket ref#-10-26-2025-754303 to technician techsss by ryan awawaww (Type: regular)'),
('2025-10-26 19:10:55', 'tech admin', 'has successfully logged in'),
('2025-10-26 19:13:49', 'Technician tech admin', 'Ticket ref#-10-26-2025-754303 closed by technician tech admin (Type: regular)'),
('2025-10-26 19:15:24', 'Technician tech admin', 'Ticket ref#-26-10-2025-476475 closed by technician tech admin (Type: support)'),
('2025-10-26 19:15:38', 'Technician tech admin', 'Ticket ref#-26-10-2025-301131 closed by technician tech admin (Type: support)'),
('2025-10-26 19:17:40', 'Bae Suzy', 'has successfully logged in'),
('2025-10-27 23:44:19', 'Sky Brii', 'has successfully logged in'),
('2025-10-27 23:44:20', 'Staff Sky Brii', 'Staff Sky has successfully logged in'),
('2025-10-28 00:28:19', 'Sky Brii', 'has successfully logged in'),
('2025-10-28 01:26:12', 'Sky Brii', 'has successfully logged in'),
('2025-10-28 01:26:12', 'Staff Sky Brii', 'Staff Sky has successfully logged in'),
('2025-10-28 02:25:50', 'Sky Brii', 'has successfully logged in'),
('2025-10-28 02:25:51', 'Staff Sky Brii', 'Staff Sky has successfully logged in'),
('2025-10-28 02:26:53', 'Sky Brii', 'has successfully logged in'),
('2025-10-28 02:26:53', 'Staff Sky Brii', 'Staff Sky has successfully logged in'),
('2025-10-28 02:44:15', 'Staff Erza', 'Staff Erza deployed 1 asset(s) to technician Tatyana Hayden (ID: 37)'),
('2025-10-28 02:44:58', 'Staff Erza', 'Staff Erza returned 1 asset(s)'),
('2025-10-28 02:46:25', 'Sky Brii', 'has successfully logged in'),
('2025-10-28 02:47:29', 'Staff Erza', 'Staff Erza returned 1 asset(s)'),
('2025-10-28 02:49:11', 'Sky Brii', 'has successfully logged in'),
('2025-10-28 02:55:07', 'Sky Brii', 'has successfully logged in'),
('2025-10-28 11:58:02', 'Sky Brii', 'has successfully logged in'),
('2025-10-28 12:00:42', 'Sky Brii', 'has successfully logged in'),
('2025-10-28 12:00:42', 'Staff Sky Brii', 'Staff Sky has successfully logged in'),
('2025-10-28 13:38:13', 'tech admin', 'has successfully logged in'),
('2025-10-28 14:08:00', 'Sky Brii', 'has successfully logged in'),
('2025-10-28 14:09:33', 'tech admin', 'has successfully logged in'),
('2025-10-28 14:18:12', 'Mikha Lim', 'has successfully logged in'),
('2025-10-28 14:32:20', 'Sky Brii', 'has successfully logged in'),
('2025-10-28 14:32:23', 'Sky Brii', 'has successfully logged in'),
('2025-10-28 14:35:33', 'tech admin', 'has successfully logged in'),
('2025-10-28 14:36:01', 'Sky Brii', 'has successfully logged in'),
('2025-10-28 14:51:10', 'Sky Brii', 'has successfully logged in'),
('2025-10-28 15:07:21', 'Bae Suzy', 'has successfully logged in'),
('2025-10-28 15:16:06', 'Sky Brii', 'has successfully logged in'),
('2025-10-28 15:16:06', 'Staff Sky Brii', 'Staff Sky has successfully logged in'),
('2025-10-28 15:22:12', 'Sky Brii', 'has successfully logged in'),
('2025-10-28 15:22:38', 'Staff Sky Brii', 'Created ticket #ref#-10-28-2025-329085 for customer ryan cansancio'),
('2025-10-28 15:23:07', 'Staff Sky Brii', 'Assigned ticket ref#-10-28-2025-329085 to technician techsss by Sky Brii (Type: regular)'),
('2025-10-28 15:23:25', 'tech admin', 'has successfully logged in'),
('2025-10-28 15:23:51', 'Technician tech admin', 'Ticket ref#-10-28-2025-329085 closed by technician tech admin (Type: regular)'),
('2025-10-28 15:25:11', 'Sky Brii', 'has successfully logged in'),
('2025-10-28 15:37:48', 'Bae Suzy', 'has successfully logged in'),
('2025-10-28 15:38:32', 'Sky Brii', 'has successfully logged in'),
('2025-10-28 15:38:58', 'Staff Sky Brii', 'Staff Sky Brii edited ticket ref#-09-12-2025-898305 subject and ticket details'),
('2025-10-28 16:52:39', 'Sky Brii', 'has successfully logged in'),
('2025-10-28 16:53:51', 'Staff Sky Brii', 'Staff Sky Brii archived ticket ref#-09-14-2025-919152'),
('2025-10-28 16:53:57', 'Staff Sky Brii', 'Staff Sky Brii deleted ticket ref#-09-14-2025-919152'),
('2025-10-28 16:54:03', 'Staff Sky Brii', 'Staff Sky Brii archived ticket ref#-09-14-2025-822541'),
('2025-10-28 16:54:08', 'Staff Sky Brii', 'Staff Sky Brii archived ticket ref#-09-14-2025-877994'),
('2025-10-28 16:54:39', 'Staff Sky Brii', 'Staff Sky Brii deleted ticket ref#-09-14-2025-822541'),
('2025-10-28 16:54:42', 'Staff Sky Brii', 'Staff Sky Brii deleted ticket ref#-09-14-2025-877994'),
('2025-10-28 16:55:36', 'Staff Sky Brii', 'Staff Sky Brii edited ticket ref#-09-14-2025-245086 subject and ticket details'),
('2025-10-28 16:55:53', 'Staff Sky Brii', 'Staff Sky Brii archived ticket ref#-09-14-2025-159306'),
('2025-10-28 16:55:56', 'Staff Sky Brii', 'Staff Sky Brii archived ticket ref#-09-14-2025-091312'),
('2025-10-28 16:56:03', 'Staff Sky Brii', 'Staff Sky Brii deleted ticket ref#-09-14-2025-461129'),
('2025-10-28 16:56:07', 'Staff Sky Brii', 'Staff Sky Brii deleted ticket ref#-09-14-2025-914887'),
('2025-10-28 16:56:10', 'Staff Sky Brii', 'Staff Sky Brii deleted ticket ref#-09-14-2025-923719'),
('2025-10-28 16:56:14', 'Staff Sky Brii', 'Staff Sky Brii deleted ticket ref#-09-14-2025-536663'),
('2025-10-28 16:56:17', 'Staff Sky Brii', 'Staff Sky Brii deleted ticket ref#-09-14-2025-159306'),
('2025-10-28 16:56:20', 'Staff Sky Brii', 'Staff Sky Brii deleted ticket ref#-09-14-2025-091312'),
('2025-10-28 17:02:29', 'Staff Sky Brii', 'Staff Sky Brii archived ticket ref#-09-14-2025-051578'),
('2025-10-28 17:02:33', 'Staff Sky Brii', 'Staff Sky Brii archived ticket ref#-09-14-2025-270586'),
('2025-10-28 17:02:39', 'Staff Sky Brii', 'Staff Sky Brii archived ticket ref#-09-14-2025-851882'),
('2025-10-28 17:02:43', 'Staff Sky Brii', 'Staff Sky Brii deleted ticket ref#-09-14-2025-051578'),
('2025-10-28 17:02:45', 'Staff Sky Brii', 'Staff Sky Brii deleted ticket ref#-09-14-2025-270586'),
('2025-10-28 17:02:47', 'Staff Sky Brii', 'Staff Sky Brii deleted ticket ref#-09-14-2025-851882'),
('2025-10-28 17:03:26', 'Staff Sky Brii', 'Staff Sky Brii edited ticket ref#-09-14-2025-759183 subject and ticket details'),
('2025-10-28 17:04:38', 'Staff Sky Brii', 'Staff Sky Brii edited ticket ref#-09-14-2025-242664 subject and ticket details'),
('2025-10-28 17:08:17', 'Staff Sky Brii', 'Staff Sky Brii edited ticket ref#-09-15-2025-254542 subject and ticket details'),
('2025-10-28 17:10:55', 'Staff Sky Brii', 'Created ticket #ref#-10-28-2025-871395 for customer Mikha Lim'),
('2025-10-28 17:13:09', 'Staff Sky Brii', 'Created ticket #ref#-10-28-2025-724489 for customer Pham Hanni'),
('2025-10-28 17:13:33', 'Staff Sky Brii', 'Staff Sky Brii archived ticket ref#-10-28-2025-329085'),
('2025-10-28 17:14:00', 'Staff Sky Brii', 'Staff Sky Brii edited ticket ref#-10-13-2025-067198 ticket details'),
('2025-10-28 17:14:04', 'Staff Sky Brii', 'Staff Sky Brii archived ticket ref#-10-13-2025-055302'),
('2025-10-28 17:14:07', 'Staff Sky Brii', 'Staff Sky Brii archived ticket ref#-10-26-2025-735797'),
('2025-10-28 17:14:10', 'Staff Sky Brii', 'Staff Sky Brii archived ticket ref#-10-26-2025-219630'),
('2025-10-28 17:14:17', 'Staff Sky Brii', 'Staff Sky Brii deleted ticket ref#-10-13-2025-055302'),
('2025-10-28 17:14:19', 'Staff Sky Brii', 'Staff Sky Brii deleted ticket ref#-10-26-2025-735797'),
('2025-10-28 17:14:20', 'Staff Sky Brii', 'Staff Sky Brii deleted ticket ref#-10-26-2025-219630'),
('2025-10-28 17:14:22', 'Staff Sky Brii', 'Staff Sky Brii deleted ticket ref#-10-28-2025-329085'),
('2025-10-28 17:14:27', 'Staff Sky Brii', 'Staff Sky Brii archived ticket ref#-09-15-2025-107534'),
('2025-10-28 17:14:30', 'Staff Sky Brii', 'Staff Sky Brii archived ticket ref#-09-15-2025-193019'),
('2025-10-28 17:14:41', 'Staff Sky Brii', 'Staff Sky Brii archived ticket ref#-09-16-2025-982641'),
('2025-10-28 17:15:33', 'Staff Sky Brii', 'Staff Sky Brii deleted ticket ref#-09-15-2025-107534'),
('2025-10-28 17:15:35', 'Staff Sky Brii', 'Staff Sky Brii deleted ticket ref#-09-15-2025-193019'),
('2025-10-28 17:15:36', 'Staff Sky Brii', 'Staff Sky Brii deleted ticket ref#-09-16-2025-982641'),
('2025-11-12 22:29:36', 'Staff Huh Jen', 'Staff Huh has successfully logged in'),
('2025-11-12 23:24:26', 'Mikha Lim', 'has successfully logged in'),
('2025-11-21 19:59:19', 'Staff Huh Jen', 'Staff Huh has successfully logged in'),
('2025-11-21 20:11:01', 'Eve Rosa', 'has successfully logged in'),
('2025-11-21 20:11:13', 'customer Eve Rosa', 'created ticket ref#-21-11-2025-977917'),
('2025-11-21 20:11:26', 'Staff Huh Jen', 'Staff Huh Jen rejected customer ticket ref#-21-11-2025-977917 for customer Eve Rosa with remarks: awawaw'),
('2025-11-21 20:12:30', 'Eve Rosa', 'has successfully logged in'),
('2025-11-21 20:19:12', 'Eve Rosa', 'has successfully logged in'),
('2025-11-21 20:26:10', 'Eve Rosa', 'has successfully logged in'),
('2025-11-21 21:33:55', 'Staff Huh Jen', 'Staff Huh has successfully logged in'),
('2025-11-21 21:52:36', 'Mikha Lim', 'has successfully logged in'),
('2025-11-21 22:04:36', 'Staff Huh Jen', 'Staff Huh has successfully logged in'),
('2025-12-08 22:29:49', 'Staff Huh Jen', 'Staff Huh has successfully logged in'),
('2025-12-08 23:54:52', 'Staff Erzaa', 'Staff Erzaa added 5 asset(s) named \'Wire\''),
('2025-12-09 00:29:52', 'Staff Erzaa', 'Staff Erzaa added 5 asset(s) named \'Modem\''),
('2025-12-09 00:37:49', 'Staff Erzaa', 'Staff Erzaa added 10 asset(s) named \'UTP Cable\''),
('2025-12-09 00:39:48', 'Staff Erzaa', 'Staff Erzaa added 1 asset(s) named \'Router\''),
('2025-12-09 00:42:02', 'Staff Erzaa', 'Staff Erzaa borrowed 1 asset(s) to technician Tatyana Hayden (ID: 37)');

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
(29, 'Barr', 'Lyle', 'ref#-04-09-2025-319876', 'kapoy', 'hahahah', 'Open', 34, ''),
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
(33, 'cansancio', 'ryan', 'ref#-02-10-2025-538165', 'awww', 'wawawaw', 'Open', 49, ''),
(33, 'cansancio', 'ryan', 'ref#-02-10-2025-376857', 'awaw', 'awawaw', 'Archived', 51, ''),
(33, 'cansancio', 'ryan', 'ref#-04-10-2025-209485', 'Moon', 'Stars', 'Archived', 52, ''),
(33, 'cansancio', 'ryan', 'ref#-04-10-2025-245305', 'Moon', 'Moon', 'Archived', 53, ''),
(33, 'cansancio', 'ryan', 'ref#-06-10-2025-366867', 'akoako', 'akoako', 'Archived', 54, ''),
(33, 'cansancio', 'ryan', 'ref#-13-10-2025-182410', 'Moon', 'Stars', 'Archived', 55, ''),
(33, 'cansancio', 'ryan', 'ref#-15-10-2025-967398', 'awawa', 'awawaw', 'Archived', 56, ''),
(33, 'cansancio', 'ryan', 'ref#-15-10-2025-507101', 'awawawawaw', 'awawawaw', 'Closed', 57, ''),
(33, 'cansancio', 'ryan', 'ref#-26-10-2025-301131', 'Moon', 'wawaw', 'Closed', 58, ''),
(33, 'cansancio', 'ryan', 'ref#-26-10-2025-476475', 'unstable connection', 'Our internet connection is not consistently dropping completely, but it becomes extremely slow and unresponsive multiple times per hour, especially in the evenings', 'Closed', 59, '');

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
(36, 'ryan cansancio', 'Redlight', 'Closed', 'no connection', 'ref#-09-12-2025-898305', ''),
(43, 'ryan cansancio', 'Slow Connection', 'Open', 'High Reading', 'ref#-09-14-2025-245086', 'tech12'),
(52, 'Mikha Lim', 'Speed Issue', 'Open', 'I am paying for a 200 Mbps plan, but my speeds are consistently below 10 Mbps, especially in the evenings.', 'ref#-09-14-2025-759183', ''),
(53, 'ryan cansancio', 'Redlight', 'Closed', 'The customer has no internet', 'ref#-09-14-2025-242664', 'techsss'),
(54, 'ryan cansancio', 'No Connection', 'Open', 'Customer cannot connect the phone, laptop, TV to the Internet', 'ref#-09-15-2025-254542', 'jorge'),
(58, 'ryan cansancio', 'awawa', 'Closed', 'wawaw', 'ref#-09-29-2025-000348', 'techsss'),
(59, 'ryan cansancio', 'Moon', 'Closed', 'Stars', 'ref#-10-04-2025-219683', 'techsss'),
(60, 'ryan cansancio', 'redlight', 'Closed', 'Router is flickering red light', 'ref#-10-13-2025-067198', 'techsss'),
(63, 'ryan cansancio', 'redlight', 'Closed', 'no connection', 'ref#-10-26-2025-754303', 'techsss'),
(65, 'ryan cansancio', 'redlight', 'Closed', 'no connection', 'ref#-10-26-2025-798332', ''),
(66, 'ryan cansancio', 'Modem Wont Power On', 'Open', 'My internet is completely down. I have no Wi-Fi and the modem/router has no lights on it at all', 'ref#-10-26-2025-574648', 'techsss'),
(68, 'Mikha Lim', 'Main Line Fiber Cut', 'Open', 'Lacated at Uling, City of Naga, Cebu', 'ref#-10-28-2025-871395', ''),
(69, 'Pham Hanni', 'Weak Wi-Fi in Kitchen Area', 'Open', 'Slow internet Connection in this location', 'ref#-10-28-2025-724489', '');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_ticket_conversations`
--

CREATE TABLE `tbl_ticket_conversations` (
  `id` int(100) NOT NULL,
  `ticket_ref` varchar(200) NOT NULL,
  `sender_type` varchar(200) NOT NULL,
  `sender_id` int(100) NOT NULL,
  `message` varchar(200) NOT NULL,
  `timestamp` varchar(200) NOT NULL,
  `is_read` tinyint(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_ticket_conversations`
--

INSERT INTO `tbl_ticket_conversations` (`id`, `ticket_ref`, `sender_type`, `sender_id`, `message`, `timestamp`, `is_read`) VALUES
(1, 'ref#-21-11-2025-977917', 'staff', 104, 'Your ticket has been declined. Remarks: awawaw', '2025-11-21 20:11:26', 1),
(2, 'ref#-21-11-2025-977917', 'customer', 31, 'AWAW', '2025-11-21 20:21:19', 1),
(3, 'ref#-21-11-2025-977917', 'staff', 104, 'I love you lab :> uwuwu', '2025-11-21 21:39:08', 0);

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
(10, '2025-08-15', 1, '2025-08-15', 'Plan 1499', 1500, 'John William Mayormita'),
(11, '2025-10-26', 501, '2025-10-26', 'Plan 999', 1500, 'Bae Suzy'),
(12, '2025-10-26', 0, '2025-10-26', 'Plan 999', 498, 'Bae Suzy'),
(13, '2025-10-28', 1, '2025-10-28', 'Plan 1999', 2000, 'ryansss cansancio'),
(14, '2025-10-28', 500, '2025-10-28', 'Plan 2500', 3000, 'Michelle Pearson'),
(15, '2025-10-28', 0, '2025-10-28', 'Plan 2500', 2000, 'Michelle Pearson');

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
(18, 'Scarlett', 'Williams', 'bukeke@mailinator.com', 'kogicosa', 'Pa$$w0rd!', 'admin', 'archived'),
(20, 'Rize', 'Chan', 'Rize@gmail.com', 'Rizee', '$2y$10$.Pr5z4b2D4EWEq5SbYozlepjWFxEAhGeBpThqWi4O793b0rkGp80m', 'admin', 'active'),
(21, 'Astark', 'Mayormita', 'larraverallo@gmail.com', 'Astark', '$2y$10$tacqlNHSmJWBh3M4dgWJNe3PJmYfkIeUllONrdTARDaYg8NSadOZS', 'admin', 'active'),
(22, 'John', 'Wilyam', 'jonwilyammayormita@gmail.com', 'Stark', '$2y$10$z.cDRaq6kxiXAB6CAXHM4OUch5jFsrGbRYlwdQ6SONixDKcSrb6C6', 'admin', 'active'),
(23, 'Ryan', 'Cansancio', 'jhonsepayla@yahoo.com', 'Ryan', '$2y$10$PxP0Kuq6wRU4J0QXLxuwFerGro3.cQoL6nc9shd0KIlUSKeix0if6', 'admin', 'active'),
(25, 'John Wilyam', 'Wilyam', 'xugecev@mailinator.com', 'WilyamSama', '$2y$10$izLTdpzNFvJ7mfjs014Jj.7IzygMHQ6wCABcpbDFIn3PaP5/Ihs1O', 'admin', 'active'),
(26, 'Xyla', 'Salinas', 'qiko@mailinator.com', 'qovalony', '$2y$10$1w/hBAF/J5QdrUtj4mtMhuBXuoPDIri6WAm4lYW.MXclYSDsOrit6', 'admin', 'active'),
(27, 'Fiona', 'Rogers', 'fiona@gmail.com', 'Fiona Chan', '$2y$10$mJbipq.7nSIizFJAXf04POmIqSQdEQcDGat7lBXb9rbFdm35aHoTu', 'staff', 'active'),
(28, 'Illana', 'Alston', 'sijirugy@mailinator.com', 'meminubof', '$2y$10$RRcEmlzVVhhi6Uk.4Hh24OLfN0KkdCiJ4q5bnIcUKav7z4n8E85dq', 'admin', 'active'),
(29, 'Brianna', 'Macias', 'dixypof@mailinator.com', 'Test', '$2y$10$b6e7YnYWZmwukVMoYV7X.eNkszNxKp3dHnr7dV4MYZ7kf57BClCli', 'staff', 'active'),
(30, 'ryan', 'cansancio', 'ryancansancio7@mail.com', 'Ryan12', '$2y$10$VH3kXpwA6guV8aV4wg30Ne.grXF14qCOK9jImO7BjQzFhER3wGQye', 'admin', 'active'),
(31, 'Meredith', 'Dunlap', 'wojyx@mailinator.com', 'gypewa', '$2y$10$E2is5g3ncyrtNHdWXU8pH.aIig1PaeCxUCNozYLHvcYDPvG0iZxzG', 'admin', 'active'),
(32, 'Chris', 'Van', 'cris@gmail.com', 'oicnasnac', '$2y$10$EUZJMcTTpel2tHpMjIgT6.kDty.VTXcPU2rWbBWuARUFCdZCMao/W', 'admin', 'active'),
(33, 'David', 'Burnss', 'Burns@gmail.com', 'oicnasnac12', '$2y$10$lNctiWTaf5CP1FYhlhgl3ehntsiEOMfsBnjnbjlq2BKHb9WOAYPa.', 'staff', 'active'),
(34, 'Arje', 'Cansancio', 'Arje@gmail.com', 'oicnasnac1234', '$2y$10$xVlsAy4RAGbUNwPIxTy6BuO2kCO3eoZ8aDJWw10h64CQ.RuPh6maC', 'staff', 'active'),
(36, 'Nattyy De Coco', 'Tillman', 'rage@mailinator.com', 'Natty', '$2y$10$JJd2ZIQG6nEBu8dZ7SRULufGWNQb9fl/OSeXzFi63/T6UguImRE/G', 'staff', 'active'),
(37, 'Tatyana', 'Hayden', 'jimeruveby@mailinator.com', 'Test ni ha', '$2y$10$GiKnS6yTtPo6qudbPLqITeo4fFbcBUjN2PucdtFhfPJnjYrQbyJP6', 'technician', 'active'),
(38, 'Steph', 'Silva', 'mayormita@gmail.com', 'Steph13', '$2y$10$sWiqat3sALnS5uxgTFEDpuCC0Rh6Jguk1HA0zNVfvbqbJgpN7eWj2', 'admin', 'active'),
(39, 'Ryan', 'Hernane', 'ryancansancio7@gmail.com', 'hernane12', '$2y$10$cl0fXBKDBaMex2SjBnDErOPk/xK38M.HTOw0VsYrr9lxmCH2xo8ku', 'staff', 'active'),
(40, 'Mikha', 'Lim', 'mikhamylove@gmail.com', 'Mikha Lim', '$2y$10$wgKdbJ5BKYDxrU0GcdOTiuHchprGX8eKjWkB.cTtONksqZKRshZ4S', 'staff', 'active'),
(41, 'Kolehe', 'Kai', 'kolohekai@gmail.com', 'Kolohe', '$2y$10$p0uaBuH3RfB6BxHsbgIguevieEhZ9CplkIFXB50PetTP.0eFfmfkm', 'technician', 'active'),
(42, 'Zia', 'Jackson', 'zia@gmail.com', 'Zia', '$2y$10$ll3uoOyEKUDsdZeOOdPpAezyfFEVxkM0amJp3ILjtqWy/1fNWlRr.', 'technician', 'active'),
(43, 'Suzy', 'Bae', 'devizipot@mailinator.com', 'Suzyy', '$2y$10$AZueEfn.JBOkOzacbym8/OIANPAxZHMJpBmlX2FLgWPghFNTFZlU.', 'staff', 'active'),
(44, 'Admin', 'Test', 'admin@gmail.com', 'Admin', '$2y$10$LBq.HvsUTSzqerTOqSIfa.TGAsjEa3VokSXfB4NpNHhnQNRAC.dPO', 'admin', 'active'),
(45, 'Yumi', 'Chan', 'sohynyq@gmail.com', 'Yumi Chan', '$2y$10$GInrYkcHcu9PIrbehLTksO0MmcG4lAAZXC7Tgfiy0iaDXBJQ5mo0S', 'technician', 'active'),
(51, 'Aiah', 'Love', 'aiahlove@gmail.com', 'Aiahkins', '$2y$10$zthsh5racOm7RvvGDHj2i.xQV4jHp/.yS9gemChTZtsHfABSb/WsO', 'staff', 'pending'),
(53, 'Zeph', 'Patel', 'huhezeru@mailinator.com', 'ADMINN', '$2y$10$rCAUF7yizAqKOlXGNmlf5OuO.wX.X5ZV2I4FBrBHMR3J3.qRo6V2u', 'admin', 'pending'),
(54, 'Joelle', 'Graves', 'vefud@mailinator.com', 'TestT', '$2y$10$YcCrLftfHHg/JVNrXCJsO.x0pJ3vXRtukqGjj8OS00jDuA2JQez3i', 'technician', 'pending'),
(55, 'Germaine', 'Gray', 'williammayormita69@gmail.com', 'Jawil', '$2y$10$8ZbZIaaUs001K4sgyso0VeizbeCokz.6bTY2hIzonOsr3.lLhZYWy', 'staff', 'active'),
(64, 'Samantha', 'Holloway', 'ruvixir@mailinator.com', 'Kysecu', '$2y$10$wUkZRXsNrZoYjgFYOX.HCecyTc2xzvPuP2VqrB7cprW7M7ZVmUUz.', 'admin', 'active'),
(65, 'Ivan', 'Sailas', 'ryancansanco7@gmail.com', 'tech12', '$2y$10$qUzZa9kDhvkiXKNyO/7P3.5ZSm7ZMsxzVBd6wk2h1ro8fcOhv6oju', 'technician', 'active'),
(66, 'haha', 'hahah', 'ryancansanco7@gmail.com', 'techs', '$2y$10$DX/P7vKhwur7pt0n/2Y8f.qWbIwhm6/bYR5tcE5afBbYq3Vvvjb22', 'technician', 'archived'),
(67, 'cha', 'hae', 'ryancansanco7@gmail.com', 'cha12', '$2y$10$yS2zmOWYPzWjIedtnrBuveQkMns4h.nOiks1ENdaqJHlilyh9rIzm', 'staff', 'archived'),
(68, 'Stephen', 'Silva', 'Stephensilva@gmail.com', 'Stephen', '$2y$10$iLzCtJL1.gX/0CPTFGj9ae5SkSMlYH.BQ9xcow73jaPLPEoBr81RC', 'admin', 'archived'),
(69, 'Ryan', 'Cansancio', 'haysss@gmail.com', 'ryan12345', '$2y$10$FJwstGNN0uVUz45URai4iu3U4uk2uMEvoSAeo1VFvDhtT8Wy463mm', 'admin', 'archived'),
(72, 'Ray', 'Quan', 'rayquan@gmail.com', 'Ray Quan', '$2y$10$RLxHYJDYEbnbKUG3SSh3/uSKKSpr7hJNK3w.7s6Az4Unw9dFtn0IW', 'staff', 'archived'),
(73, 'Pham', 'Hanny', 'hanny@gmail.com', 'Hanni', '$2y$10$XSj9STnj0nWrnUF1V5tBEuN7Wa1IjLyw523qyVGdUbaH2FSbCpzzK', 'technician', 'archived'),
(74, 'ryan', 'cansancio', 'ryan@gmail.com', 'joyuri', '$2y$10$AMaBJBi.DQbVYtHdHbvSledLRhCRT7YQUYiwGCXN/6j1JLahWjVy6', 'staff', 'archived'),
(76, 'xiexie', 'Ryan', 'cansancio@gmail.com', 'ryanryan', '$2y$10$Twa624BuQNGvxsp3ZU.Z..ABQJpv2XACHo6nJy2cI5YoO9QaqLaei', 'staff', 'archived'),
(77, 'tech', 'admin', 'admin@gmail.com', 'techsss', '$2y$10$8ROUBP0Hkx6rcidFGZdGM.ZvzYMVZe0Z6lQNFtmfdGShBIhyF6p0G', 'technician', 'archived'),
(79, 'ryan', 'cansancio', 'ryancansancio7@gmail.com', 'xixie123', '$2y$10$SsTJdeVIhmM6ue2E5Eb1sOGcGw2ElHoiN3.IcPzqeSHvfzopKE20u', 'staff', 'archived'),
(80, 'awaw', 'awawa', 'ryancansancio7@gmail.com', 'xixiee123', '$2y$10$96.iOjVSOgKNr79pfKRhseBiYTDlKtoLcNTO6Sd5fOrWJ7shEwz.y', 'staff', 'archived'),
(81, 'awa', 'awaa', 'ryancansancio7@gmail.com', 'alaxan123', '$2y$10$R6i5pZHuNreKjhlLh8GPOeMRyITpc6zP7gtngyTHR34vadTH9S.KS', 'staff', 'archived'),
(82, 'Cyrel', 'Bini', 'agboncyrelann@gmail.com', 'Xai Cy', '$2y$10$ItjsvGzwVDzghGU77.UONeNRn..yBsK3NVM/fm2nq5xXX5R2u1L2m', 'technician', 'archived'),
(83, 'brother', 'cansancio', 'ryancansancio7@gmail.com', 'brother', '$2y$10$chkUHWMGYc4NzuXwomUe4eNmxIs6k9TdvLjSMzBwG3oeGJXeSWd1S', 'technician', 'archived'),
(84, 'jorge', 'bugwak', 'ryancansancio7@gmail.com', 'jorge', '$2y$10$ZlIrmry5Pg1sgzxmoWS2IuOXtjDLFz2HlcaGt5LNW8fA2BVre/dQG', 'technician', 'archived'),
(85, 'georgia', 'jorge', 'ryancansancio7@gmail.com', 'georgia', '$2y$10$2RiTTGEzTMXCb5KX6Dquo.STvfHpFGk0FabxaSX6As2B.97pRqRz.', 'technician', 'pending'),
(86, 'latina', 'georgiana', 'ryancansancio7@gmail.com', 'ansakit', '$2y$10$F3UzAY9I0ZCqNPwS1CxPs.W7LhNdINcGVigTQIh8mtvZ54CwZs3s2', 'technician', 'pending'),
(88, 'Moon', 'Stars', 'ryancansancio7@gmail.com', 'samson', '$2y$10$JsHuYRgU10Du0vmNQaoqbuHKadF.ZH/MbZzPM6l/rZk2v4OLRsFKq', 'staff', 'pending'),
(92, 'Grace', 'Palma', 'Palma@gmail.com', 'pending', '$2y$10$h2tzliinmbWxVjCGPxFvOO/KV03K876dSgaZKQpguZw5iWZEcVSpK', 'staff', 'pending'),
(102, 'john', 'will', 'williammayormita69@gmail.com', 'daddy', '$2y$10$63.iYZCmqgqFnhTTojLwiueM1INXJp45Y3i6guQocLQh6GVetxaKu', 'staff', 'pending'),
(103, 'Sky', 'Brii', 'jonwilyammayormita@gmail.com', 'Erza', '$2y$10$NCn4zb/zO/BWhjLvEKJyJ.zE/vtpusf/g/dZLaTxN321dkt3aUO7e', 'staff', 'active'),
(104, 'Huh', 'Jen', 'huhyunjin@gmail.com', 'Erzaa', '$2y$10$4MS.7vZAwabIRJjDsRYNeO/ePlaM/7omDEzIWiWFYncU/15p4ZaUy', 'staff', 'active'),
(105, 'Wilyam', 'Scarlet', 'wilyam@gmail.com', 'Wilyam', '$2y$10$gJ.01yfPtic/bxxz0G9JM.CzJ3PprzZRnD9bfcUrO5MSuVBIKP.ZO', 'admin', 'active'),
(106, 'Hanni', 'Cutie', 'hannicutie@gmail.com', 'Hanney', '$2y$10$hK7HfvOUK7sSYvu5WX6gN.tKM1hv.nyxrWNIT4J2t0AHSkWi2UdCe', 'technician', 'active'),
(107, 'Zhu', 'Cutie', 'zhucutie@gmail.com', 'Zuzu', '$2y$10$i8CxEDlM4HYOc7piNXrkPOYukMNlSizCvIyrjaZT/eWk74wZJGMma', 'admin', 'active');

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
-- Indexes for table `tbl_close_regular`
--
ALTER TABLE `tbl_close_regular`
  ADD PRIMARY KEY (`r_id`);

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
-- Indexes for table `tbl_ticket_conversations`
--
ALTER TABLE `tbl_ticket_conversations`
  ADD PRIMARY KEY (`id`);

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
  MODIFY `a_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `tbl_asset_status`
--
ALTER TABLE `tbl_asset_status`
  MODIFY `a_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `tbl_close_regular`
--
ALTER TABLE `tbl_close_regular`
  MODIFY `r_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbl_customer`
--
ALTER TABLE `tbl_customer`
  MODIFY `c_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `tbl_supp_tickets`
--
ALTER TABLE `tbl_supp_tickets`
  MODIFY `id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `tbl_ticket`
--
ALTER TABLE `tbl_ticket`
  MODIFY `t_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `tbl_ticket_conversations`
--
ALTER TABLE `tbl_ticket_conversations`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_transactions`
--
ALTER TABLE `tbl_transactions`
  MODIFY `t_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `tbl_user`
--
ALTER TABLE `tbl_user`
  MODIFY `u_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=108;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
