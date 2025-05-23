-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 23, 2025 at 08:16 AM
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
  `t_details` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `s_status` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_close_supp`
--

INSERT INTO `tbl_close_supp` (`s_ref`, `c_id`, `te_technician`, `c_fname`, `c_lname`, `s_subject`, `s_message`, `s_status`) VALUES
('ref#-18-05-2025-189091', 9, 'haahays haha', 'Latifah', 'Sims', 'redlight', 'awaw', 'Closed'),
('ref#-07-04-2025-484929', 9, 'haahays haha', 'Latifah', 'Sims', 'Critical', 'awaw', 'Closed'),
('ref#-19-05-2025-710976', 9, 'haahays haha', 'Latifah', 'Sims', 'redlight', 'ygwedjfdjwe', 'Closed');

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
  `c_equipment` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_customer`
--

INSERT INTO `tbl_customer` (`c_id`, `c_fname`, `c_lname`, `c_purok`, `c_barangay`, `c_contact`, `c_email`, `c_date`, `c_napname`, `c_napport`, `c_macaddress`, `c_status`, `c_plan`, `c_equipment`) VALUES
(1, 'awawawa', 'wawawawaw', 'awawawawaw', '12121212', '0', '2000-02-21', '0000-00-00', '12', 0, 'awawaw', '', '', ''),
(2, 'awawawa', 'wawaw', 'awawaw', '1212121212', '0', '2000-02-21', '0000-00-00', '12', 0, 'awawawawa', '', '', ''),
(4, 'awawa', 'wawa', 'wawaw', '121212112', '0', '2000-02-21', '0000-00-00', '121', 0, 'awawaw', '', '', ''),
(5, 'awawa', 'wawawaw', 'awawawa', '0', '0', '2000-02-21', '0000-00-00', '12', 0, 'awawaw', '', '', ''),
(6, 'awawa', 'wawawa', 'awaww', '0', '0', '2000-02-21', '0000-00-00', '0', 0, 'awaw', '', '', ''),
(7, 'awawa', 'wawa', 'wawaw', '0', '0', '2000-02-21', '0000-00-00', '0', 0, 'awaw', '', '', ''),
(8, 'awawaw', 'awawa', 'wawa', '0', '0', '2000-02-21', '0000-00-00', '0', 0, 'awaw', '', '', ''),
(9, 'Latifah', 'Sims', 'Perspiciatis ea dol', '0', '0', '1970-01-21', '0000-00-00', '0', 0, 'Aute eum maxime aper', '', '', ''),
(10, 'Mohammad', 'Mcdaniel', 'Vel ut a et deserunt', '939990939', '0', '2024-08-31', '0000-00-00', '0', 0, 'Sapiente suscipit no', '', '', ''),
(11, 'Lunea', 'Mendez', 'Dolores accusamus mo', '93442324', '0', '2025-03-20', '0000-00-00', '2147483647', 0, 'Ut dolorem quia est', '', '', ''),
(12, 'Leila', 'Swanson', 'Pakigne Minglanilla', '939990939', '0', '2025-04-28', '0000-00-00', '123490', 0, 'Active', '', '', ''),
(13, 'Cara', 'Blackwell', 'Possimus ab dolores', '2147483647', '0', '2025-05-13', '0000-00-00', '12', 0, 'ARCHIVED:Active', '', '', ''),
(15, 'Donovan', 'Montgomery', 'Suscipit ut et ut ex', '10292993', '0', '2025-05-13', '0000-00-00', '1213232', 0, 'Active', '', '', ''),
(16, 'Haley', 'Waller', 'Blair Harvey', 'Ullam provident ali', '2147483647', 'xomati@mailinator.com', '1977-05-07', 'Wilma', 1203333, '102030i9mdk', 'Active', '1 Gbps Fiber', 'Customer-Owned'),
(17, 'Justin', 'Peterson', 'Aladdin Klein', 'Fugiat laudantium h', '463', 'dokykutuko@mailinator.com', '1989-08-18', 'Marsden', 1234, '102038798i9mdk', 'Active', '1 Gbps Fiber', 'Customer-Owned'),
(18, 'Andrew', 'Hurley', 'Purok Catleya', 'Pakigne', '2147483647', 'kajokupy@mailinator.com', '2025-05-17', 'One', 2, 'BC-BD-84-A5-CE', 'Active', '25 Mbps DSL', 'Customer-Owned'),
(19, 'Duncan', 'William', 'Purok Tambis', 'Ward II', '09394578940', 'fadipobo@mailinator.com', '2025-05-17', 'Lp1 Np2', 2, 'A5-ER-BX-R4-DE', 'Active', '100 Mbps', 'ISP-Provided Modem/Router'),
(20, 'Karleigh', 'Landry', 'Purok 11', 'Banhigan', '09567894567', 'pava@mailinator.com', '2025-05-17', 'Lp1 Np1', 1, 'AD-GH-DB-E3-CC', 'Active', '1 Gbps', 'Customer-Owned'),
(21, 'Zachary', 'Beasley', 'Purok Wildflower', 'Banhigan', '09453458693', 'fyjuxyjo@mailinator.com', '2025-05-17', 'Lp1 Np6', 6, 'AS-DK-FK-B3-DW', 'Active', '50 Mbps', 'Customer-Owned'),
(22, 'cheska', 'malisorn', 'mangga1', 'san isidro', '646286437', 'cheska@gmsil.com', '2025-05-19', 'Lp1 Np1', 1, 'hekjhke', 'Active', '100 Mbps', 'Customer-Owned'),
(23, 'Ann', 'Daniel', 'Colette Shepard', 'Consequatur neque a', '231', 'cytineci@mailinator.com', '2021-10-26', 'Lp1 Np5', 2, 'QuisJEVE', 'Active', '100 Mbps', 'Customer-Owned');

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
('2025-05-23 13:57:34', 'Staff Aiah Love', 'Staff Aiah has successfully logged in');

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
  `id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_supp_tickets`
--

INSERT INTO `tbl_supp_tickets` (`c_id`, `c_lname`, `c_fname`, `s_ref`, `s_subject`, `s_message`, `s_status`, `id`) VALUES
(9, 'Sims', 'Latifah', 'ref#-22-05-2025-483623', 'no wifi', 'ARCHIVED:tungod sa uwan', 'Open', 20),
(9, 'Sims', 'Latifah', 'ref#-22-05-2025-261353', 'no connection', 'kusogg kaayo ang uwan', 'Open', 21),
(9, 'Sims', 'Latifah', 'ref#-22-05-2025-104028', 'no net', 'kussogg kaayo', 'Open', 22),
(9, 'Sims', 'Latifah', 'ref#-22-05-2025-521587', 'redlight', 'stop', 'Open', 23),
(23, 'Daniel', 'Ann', 'ref#-23-05-2025-114488', 'test', 'test lang ni para sa modal', 'Open', 24);

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
  `t_ref` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_ticket`
--

INSERT INTO `tbl_ticket` (`t_id`, `t_aname`, `t_subject`, `t_status`, `t_details`, `t_ref`) VALUES
(19, 'Haley Waller', 'redlight', 'Open', 'awawaw', 'ref#-22-05-2025-107976');

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
(71, 'awawaww', 'awaww', 'wwawaw@gmail.com', 'sprite', '$2y$10$6KdLZORtkdIXT3U4MNuBwONFhlBS/S82HJZf/aueW/GEzHJI.1riC', 'admin', 'active');

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
  MODIFY `c_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `tbl_ticket`
--
ALTER TABLE `tbl_ticket`
  MODIFY `t_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `tbl_user`
--
ALTER TABLE `tbl_user`
  MODIFY `u_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
