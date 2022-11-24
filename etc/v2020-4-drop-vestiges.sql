-- /etc/v2020-4-drop-vestiges.sql
-- getting rid of miscellaneous tables & columns that are no longer used.

DROP TABLE taskCategory;

ALTER TABLE invoice
DROP COLUMN extra;

-- Get rid of old rows in invoiceStatus that were hardcoded to specific individuals.
-- This will leave us only rows with parentId=0, after which we can get rid of parentId.
DELETE FROM invoiceStatus
WHERE uniquename='eorlookoverronskinner'
OR uniquename='eorlookoverdfleming'
OR uniquename='eorlookovertfile';

ALTER TABLE invoiceStatus
DROP COLUMN parentId,
DROP COLUMN misc;

ALTER TABLE invoiceStatusTime
DROP COLUMN extra;

-- Change the trigger invstatustime_after_insert; this gets rid of 'extra' here
-- 
-- OLD TRIGGER from v2020-3
-- TRIGGER invstatustime_after_insert AFTER INSERT ON invoiceStatusTime
-- FOR EACH ROW 
--     BEGIN
--         UPDATE invoice SET 
--             invoiceStatusId = NEW.invoiceStatusId,
--             extra = NEW.extra,  -- this is what we will drop.
--             invoiceStatusTimeId = NEW.invoiceStatusTimeId
--         WHERE invoiceId = NEW.invoiceId;
--     END;
    
DROP TRIGGER IF EXISTS invstatustime_after_insert;

CREATE TRIGGER invstatustime_after_insert AFTER INSERT ON invoiceStatusTime
    FOR EACH ROW 
        UPDATE invoice 
        SET
            invoiceStatusId = NEW.invoiceStatusId,
            invoiceStatusTimeId = NEW.invoiceStatusTimeId
        WHERE invoiceId = NEW.invoiceId;
        
-- END Change the trigger invstatustime_after_insert

DROP TABLE jobLocation;

DROP TABLE jobLocationType;

ALTER TABLE workOrder
DROP COLUMN extra,
DROP COLUMN tempAmount;

ALTER TABLE workOrderStatusTime
DROP COLUMN extra;

-- Change the trigger wostatustime_after_insert; this gets rid of 'extra' here
-- 
-- OLD TRIGGER from v2020-3
-- TRIGGER wostatustime_after_insert AFTER INSERT ON workOrderStatusTime
-- FOR EACH ROW
--     BEGIN
--         UPDATE workOrder SET
--             workOrderStatusId = NEW.workOrderStatusId,
--             extra = NEW.extra,
--             workOrderStatusTimeId = NEW.workOrderStatusTimeId
--         WHERE workOrderId = NEW.workOrderId;
--     END

DROP TRIGGER IF EXISTS wostatustime_after_insert;

CREATE TRIGGER wostatustime_after_insert AFTER INSERT ON workOrderStatusTime
    FOR EACH ROW 
        UPDATE workOrder 
        SET
            workOrderStatusId = NEW.workOrderStatusId,
            workOrderStatusTimeId = NEW.workOrderStatusTimeId
        WHERE workOrderId = NEW.workOrderId;

-- END Change the trigger wostatustime_after_insert

ALTER TABLE task
DROP COLUMN viewMode;

ALTER TABLE workOrderTask
DROP COLUMN workOrderTaskCategoryId, 
DROP COLUMN viewMode;

DROP TABLE crap;

-- End of file --