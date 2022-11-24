CREATE TABLE `jobDocument` (
  `jobDocumentId` int(11) NOT NULL AUTO_INCREMENT,
  `documentGroupId` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `fileName` varchar(255) DEFAULT NULL,
  `description` text,
  `userCreate` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `userUpdate` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `fileOnDisk` varchar(255) DEFAULT NULL,
  `jobId` int(11) DEFAULT NULL,
  PRIMARY KEY (`jobDocumentId`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4;


