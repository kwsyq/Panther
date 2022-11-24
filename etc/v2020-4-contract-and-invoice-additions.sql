-- /etc/v2020-4-contract-and-invoice-additions.sql

ALTER TABLE contract
ADD COLUMN data2 MEDIUMTEXT NULL DEFAULT NULL COLLATE 'latin1_swedish_ci' AFTER data;

ALTER TABLE invoice
ADD COLUMN data2 MEDIUMTEXT NULL DEFAULT NULL COLLATE 'latin1_swedish_ci' AFTER data,
ADD COLUMN invoiceNotes TEXT(65535) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci' AFTER textOverride;

-- Changing the approach to workOrderTask tally & to the flow of certain information from workOrder to invoice prep to written invoice.
ALTER TABLE workOrder
MODIFY tempNote VARCHAR(4096) NULL DEFAULT NULL;

-- End of file --