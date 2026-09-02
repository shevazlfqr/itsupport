-- SLA Tracking Migration
-- Run this script to add SLA deadline column to tickets table

ALTER TABLE tickets
    ADD COLUMN sla_deadline DATETIME NULL AFTER updated_at;

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

-- Index untuk query/urutan berdasarkan deadline SLA (menyamakan dengan schema.sql).
-- Jalankan hanya jika index belum ada:
--   SHOW INDEX FROM tickets WHERE Key_name = 'tickets_sla_index';
ALTER TABLE tickets
    ADD INDEX tickets_sla_index (sla_deadline);
