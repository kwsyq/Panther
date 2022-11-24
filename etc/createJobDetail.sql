CREATE TABLE `jobDetail` (
  `jobDetailId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `jobId` int(10) unsigned DEFAULT NULL,
  `detailRevisionId` int(10) unsigned DEFAULT NULL,
  `personId` int(10) unsigned DEFAULT NULL,
  `hidden` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`jobDetailId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


