-- etc/create-table-workOrderFile.sql 
-- for v2020-4

CREATE TABLE `workOrderFile` (
	`workOrderFileId` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`workOrderId` INT(10) UNSIGNED NOT NULL,
	`origFileName` VARCHAR(255) NULL DEFAULT NULL,
	`fileName` VARCHAR(255) NULL DEFAULT NULL,
	`personId` INT(10) UNSIGNED NULL DEFAULT NULL,
	`inserted` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`workOrderFileId`) USING BTREE,
	INDEX `ix_wofile_woid` (`workOrderId`) USING BTREE
);