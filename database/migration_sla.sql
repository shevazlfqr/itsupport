-- Historical SLA migration for databases created before schema.sql included
-- the SLA column and index. schema.sql is the baseline for new installations.
-- This file is safe to run when the column or index already exists.

USE itsupport;

SET @add_sla_column = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'tickets'
          AND column_name = 'sla_deadline'
    ),
    'SELECT 1',
    'ALTER TABLE tickets ADD COLUMN sla_deadline DATETIME NULL AFTER updated_at'
);
PREPARE add_sla_column_stmt FROM @add_sla_column;
EXECUTE add_sla_column_stmt;
DEALLOCATE PREPARE add_sla_column_stmt;

-- SLA Deadlines by Priority (from ticket created_at):
-- urgent: 4 hours
-- high:   8 hours
-- medium: 24 hours
-- low:    72 hours

-- Update existing tickets with calculated SLA deadlines
UPDATE tickets SET sla_deadline = CASE
    WHEN priority = 'urgent' THEN DATE_ADD(created_at, INTERVAL 4  HOUR)
    WHEN priority = 'high'   THEN DATE_ADD(created_at, INTERVAL 8  HOUR)
    WHEN priority = 'medium' THEN DATE_ADD(created_at, INTERVAL 24 HOUR)
    WHEN priority = 'low'    THEN DATE_ADD(created_at, INTERVAL 72 HOUR)
    ELSE DATE_ADD(created_at, INTERVAL 24 HOUR)
END
WHERE sla_deadline IS NULL;

-- Index untuk query dan urutan berdasarkan deadline SLA.
SET @add_sla_index = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'tickets'
          AND index_name = 'tickets_sla_index'
    ),
    'SELECT 1',
    'ALTER TABLE tickets ADD INDEX tickets_sla_index (sla_deadline)'
);
PREPARE add_sla_index_stmt FROM @add_sla_index;
EXECUTE add_sla_index_stmt;
DEALLOCATE PREPARE add_sla_index_stmt;
