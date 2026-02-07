-- Add action column to event log table
-- Run this SQL to add action tracking to existing database

ALTER TABLE mdl_local_mzi_event_log 
ADD COLUMN action VARCHAR(20) DEFAULT NULL COMMENT 'Action taken by backend (created, updated, deleted)' AFTER status;
