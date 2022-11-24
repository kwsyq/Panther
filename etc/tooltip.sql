SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


DROP TABLE IF EXISTS `tooltip`;
CREATE TABLE IF NOT EXISTS `tooltip` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pageName` varchar(100) NOT NULL,
  `fieldName` varchar(100) NOT NULL,
  `fieldLabel` varchar(100) NOT NULL,
  `tooltip` text NOT NULL,
  `help` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=21 DEFAULT CHARSET=latin1;

INSERT INTO `tooltip` (`id`, `pageName`, `fieldName`, `fieldLabel`, `tooltip`, `help`) VALUES
(1, 'company', 'companyName', 'Company Name', 'This is the name of your company. Max length 128 characters', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.'),
(2, 'company', 'companyNickname', 'Company NickName', 'Company Nickname for testing', ''),
(3, 'company', 'companyURL', 'URL', 'URL must to be valid', ''),
(4, 'company', 'companyLicense', 'License', 'The company License', ''),
(6, 'company', 'phoneNumber', 'Update Phone Number', 'Tooltip for Phone Number', ''),
(7, 'company', 'addphoneNumber', 'Add Phone Number', 'Phone Number must be 10 digits long. You can Update it with a new value or to delete it, update it with a blank value', ''),
(8, 'company', 'updateEmailAddress', 'Update Email Address', '', ''),
(9, 'company', 'addEmailAddress', 'Add Email Address', '', ''),
(11, 'company', 'locationTypeId', 'Location Type', '', '');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
