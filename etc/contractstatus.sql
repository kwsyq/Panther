-- phpMyAdmin SQL Dump
-- version 4.9.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Dec 21, 2021 at 01:49 PM
-- Server version: 10.4.10-MariaDB
-- PHP Version: 7.3.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sssdevnew`
--

-- --------------------------------------------------------

--
-- Table structure for table `contractStatus`
--

DROP TABLE IF EXISTS `contractStatus`
CREATE TABLE IF NOT EXISTS `contractStatus`(
  `contractStatusId` int(10) UNSIGNED NOT NULL,
  `uniqueName` varchar(32) NOT NULL,
  `statusName` varchar(32) DEFAULT NULL,
  `displayOrder` tinyint(4) NOT NULL DEFAULT 0,
  `color` varchar(8) NOT NULL DEFAULT 'ffffff',
  `notes` varchar(255) DEFAULT NULL,
  `sent` tinyint(3) NOT NULL DEFAULT 0,
  PRIMARY KEY (`contractStatusId`),
  UNIQUE KEY `uniqueName` (`uniqueName`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `contractStatus`
--

INSERT INTO `contractStatus`(`contractStatusId`, `uniqueName`, `statusName`, `displayOrder`, `color`, `notes`, `sent`) VALUES
(0, 'draft_status', 'Draft', 1, 'ff9900', 'Initial status', 0),
(1, 'review_status', 'Review', 2, 'ffff00', 'Review ', 0),
(2, 'committed_status', 'Committed', 3, '4a4ad4', NULL, 1),
(3, 'delivered_status', 'Delivered', 4, 'ffffff', NULL, 0),
(4, 'signed_status', 'Signed', 5, 'ffffff', NULL, 1),
(5, 'void_status', 'Void', 6, '8080e0', 'Void', 1),
(6, 'void_delete', 'Voided', 7, '8080e4', 'Voided', 1);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
