-- Migration: Add publication status to galleries
-- Run this on your database before deploying the code changes.

ALTER TABLE `galleries`
  ADD COLUMN `status` ENUM('draft','published','archived') NOT NULL DEFAULT 'published' AFTER `description`,
  ADD KEY `idx_galleries_status` (`status`);
