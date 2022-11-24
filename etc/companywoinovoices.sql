-- phpMyAdmin SQL Dump
-- version 4.9.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Feb 04, 2021 at 02:15 PM
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
-- Structure for view `companywoinovoices`
--

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `companywoinovoices`  AS
select `c`.`companyId` AS `companyId`,`wo`.`workOrderId` AS `workOrderId`,`i`.`invoiceStatusId` AS `invoiceStatusId`,count(0) AS `nrInvoices` from ((((`team` `t` left join `companyPerson` `cp` on(`t`.`companyPersonId` = `cp`.`companyPersonId`)) left join `company` `c` on(`cp`.`companyId` = `c`.`companyId`)) left join `workOrder` `wo` on(`t`.`id` = `wo`.`workOrderId`)) left join `invoice` `i` on(`wo`.`workOrderId` = `i`.`workOrderId`)) where `t`.`inTable` = 1 and `i`.`invoiceStatusId` = 64 group by 1,3 ;

--
-- VIEW  `companywoinovoices`
-- Data: None
--

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
