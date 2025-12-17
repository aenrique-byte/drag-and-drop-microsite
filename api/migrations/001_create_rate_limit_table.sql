-- Rate limiting table for atomic fixed-window rate limiting
-- Run this migration once to create the table

CREATE TABLE IF NOT EXISTS rate_limit_agg (
  key_name VARCHAR(191) NOT NULL,
  window_start INT UNSIGNED NOT NULL,
  count INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (key_name, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: Create index for cleanup queries (if running periodic cleanup)
CREATE INDEX idx_window_start ON rate_limit_agg(window_start);
