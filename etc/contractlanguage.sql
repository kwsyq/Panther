-- phpMyAdmin SQL Dump
-- version 4.9.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Feb 25, 2022 at 12:41 PM
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
-- Table structure for table `contractLanguage`
--

DROP TABLE IF EXISTS `contractLanguage`;
CREATE TABLE IF NOT EXISTS `contractLanguage` (
  `contractLanguageId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `fileName` varchar(128) NOT NULL,
  `displayName` varchar(255) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `inserted` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` varchar(50) DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `status` tinyint(3) NOT NULL DEFAULT 0,
  PRIMARY KEY (`contractLanguageId`),
  UNIQUE KEY `fileName` (`fileName`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `contractLanguage`
--

INSERT INTO `contractLanguage` (`contractLanguageId`, `fileName`, `displayName`, `notes`, `inserted`, `deleted_at`, `deleted_by`, `type`, `status`) VALUES
(1, '140404_Standard-Contract_Language_(DOR).pdf', 'Standard Contract Language Rev. 4/14/2014 DOR', '', '2017-10-02 21:51:19', NULL, NULL, 'DOR', 1),
(2, '140404_Standard_Contract_Language_(LL).pdf', 'Standard Contract Language Rev. 4/4/2014 LL', '', '2017-10-02 21:51:19', NULL, NULL, 'LL', 1),
(3, '140404_Standard_Contract_Language_(NL).pdf', 'Standard Contract Language Rev. 4/4/2014 NL', '', '2017-10-02 21:51:19', NULL, NULL, 'NL', 1),
(4, '160513 Standard Contract Language (NL).pdf', 'Standard Contract Language Rev. 5/13/2016 NL', '', '2017-10-02 21:51:19', NULL, NULL, 'NL', 0),
(7, '180418_Contract_Language_(Type_IV).pdf', 'Contract Language Type IV 4/18/2018', NULL, '2018-04-23 17:20:12', NULL, NULL, 'TYPE_IV', 1),
(8, '210101 Standard Contract Language (DOR).pdf', '210101 Standard Contract Language (DOR).pdf', NULL, '2021-05-07 23:46:28', NULL, NULL, 'DOR', 0);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
