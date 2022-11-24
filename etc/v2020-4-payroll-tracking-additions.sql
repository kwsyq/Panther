-- /etc/v2020-4-payroll-tracking-additions.sql
-- New columns and one new table for v2020-4
-- See http://sssengwiki.com/Payroll+signoff+by+employee

ALTER TABLE customerPersonPayPeriodInfo
ADD COLUMN adminSignedPayrollTime TIMESTAMP NULL DEFAULT NULL,
ADD COLUMN lastSignoffTime TIMESTAMP NULL DEFAULT NULL,
ADD COLUMN reopenTime TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE pto
ADD COLUMN lastModificationTime TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD COLUMN lastModificationPersonId INT(10)  NULL DEFAULT NULL,
ADD COLUMN adminAcceptTime TIMESTAMP NULL DEFAULT NULL,
ADD COLUMN adminPersonId INT(10)  NULL DEFAULT NULL;

CREATE TABLE ptoLateModification(
ptoLateModificationId INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
ptoId	INT(10) UNSIGNED NOT NULL,
inserted TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
oldMinutes INT(11) NOT NULL, -- Presumably should really be unsigned, but following workOrderTaskTime.minutes
newMinutes INT(11) NOT NULL, -- Presumably should really be unsigned, but following workOrderTaskTime.minutes
notificationSent TINYINT(3) NOT NULL, -- effectively Boolean
PRIMARY KEY (ptoLateModificationId) USING BTREE
);

ALTER TABLE workOrderTaskTime
ADD COLUMN lastModificationTime TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD COLUMN lastModificationPersonId INT(10)  NULL DEFAULT NULL,
ADD COLUMN adminAcceptTime TIMESTAMP NULL DEFAULT NULL,
ADD COLUMN adminPersonId INT(10)  NULL DEFAULT NULL;

CREATE TABLE workOrderTaskTimeLateModification(
workOrderTaskTimeLateModificationId INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
workOrderTaskTimeId	INT(10) UNSIGNED NOT NULL,
inserted TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
oldMinutes INT(11) NOT NULL, -- Presumably should really be unsigned, but following workOrderTaskTime.minutes
newMinutes INT(11) NOT NULL, -- Presumably should really be unsigned, but following workOrderTaskTime.minutes
notificationSent TINYINT(3) NOT NULL, -- effectively Boolean
PRIMARY KEY (workOrderTaskTimeLateModificationId) USING BTREE
);

UPDATE customerPersonPayPeriodInfo 
SET lastSignoffTime = initialSignoffTime;

-- End of file --