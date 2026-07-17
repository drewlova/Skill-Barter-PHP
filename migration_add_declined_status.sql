-- Run this only if you already imported skillswap_schema.sql before this update.
-- It adds the 'Declined' status so users can decline a pending exchange proposal.
-- (Skip this if you're importing skillswap_schema.sql fresh — it's already included.)

USE skillswap;

ALTER TABLE exchange_sessions
  MODIFY COLUMN status ENUM('Pending','Active','Declined','Completed') NOT NULL DEFAULT 'Pending';
