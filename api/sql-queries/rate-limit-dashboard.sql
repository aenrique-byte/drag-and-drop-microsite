-- =====================================================
-- RATE LIMITING DASHBOARD QUERIES
-- =====================================================
-- Useful SQL queries for monitoring and debugging rate limits
-- =====================================================

-- Top noisy keys (last hour)
-- Shows which keys are hitting rate limits most frequently
SELECT
    key_name,
    SUM(count) AS hits,
    COUNT(DISTINCT window_start) AS windows_hit
FROM rate_limit_agg
WHERE window_start >= UNIX_TIMESTAMP() - 3600
GROUP BY key_name
ORDER BY hits DESC
LIMIT 50;

-- Per-action volume (last 15 min)
-- Shows aggregate traffic by action type
SELECT
    SUBSTRING_INDEX(key_name, ':', 1) AS action,
    SUM(count) AS hits,
    COUNT(DISTINCT SUBSTRING_INDEX(key_name, ':', -1)) AS unique_ips
FROM rate_limit_agg
WHERE window_start >= UNIX_TIMESTAMP() - 900
GROUP BY action
ORDER BY hits DESC;

-- Active rate limit windows
-- Shows currently active rate limit buckets
SELECT
    key_name,
    FROM_UNIXTIME(window_start) AS window_start_time,
    count,
    CASE
        WHEN key_name LIKE 'login:%' THEN 10
        WHEN key_name LIKE 'comment_create:%' THEN 5
        WHEN key_name LIKE 'gallery_comment_create:%' THEN 20
        WHEN key_name LIKE 'all:%' THEN 120
        ELSE 0
    END AS limit,
    ROUND(count / CASE
        WHEN key_name LIKE 'login:%' THEN 10
        WHEN key_name LIKE 'comment_create:%' THEN 5
        WHEN key_name LIKE 'gallery_comment_create:%' THEN 20
        WHEN key_name LIKE 'all:%' THEN 120
        ELSE 1
    END * 100, 1) AS usage_pct
FROM rate_limit_agg
WHERE window_start >= UNIX_TIMESTAMP() - 3600
ORDER BY usage_pct DESC, count DESC
LIMIT 100;

-- Suspected abuse (near or over limits)
-- Shows IPs that are frequently hitting limits
SELECT
    SUBSTRING_INDEX(key_name, ':', -1) AS ip_address,
    SUBSTRING_INDEX(key_name, ':', 1) AS action,
    COUNT(*) AS windows_hit,
    SUM(count) AS total_attempts,
    MAX(count) AS max_in_window
FROM rate_limit_agg
WHERE window_start >= UNIX_TIMESTAMP() - 86400
GROUP BY ip_address, action
HAVING max_in_window >= 5 -- Adjust threshold as needed
ORDER BY total_attempts DESC
LIMIT 50;

-- Rate limit table size and health
-- Monitor table growth for cleanup needs
SELECT
    COUNT(*) AS total_rows,
    COUNT(DISTINCT key_name) AS unique_keys,
    MIN(FROM_UNIXTIME(window_start)) AS oldest_window,
    MAX(FROM_UNIXTIME(window_start)) AS newest_window,
    ROUND(SUM(count)) AS total_requests_tracked,
    ROUND((SELECT COUNT(*) FROM rate_limit_agg WHERE window_start < UNIX_TIMESTAMP() - 86400) / COUNT(*) * 100, 1) AS pct_stale
FROM rate_limit_agg;

-- Login attempts analysis (security monitoring)
-- Focus on login activity for brute force detection
SELECT
    SUBSTRING_INDEX(SUBSTRING_INDEX(key_name, ':', -1), ':', 1) AS username_or_ip,
    COUNT(DISTINCT window_start) AS attempt_windows,
    SUM(count) AS total_attempts,
    MAX(count) AS max_attempts_in_window,
    MIN(FROM_UNIXTIME(window_start)) AS first_attempt,
    MAX(FROM_UNIXTIME(window_start)) AS last_attempt
FROM rate_limit_agg
WHERE key_name LIKE 'login:%'
    AND window_start >= UNIX_TIMESTAMP() - 86400
GROUP BY username_or_ip
HAVING total_attempts >= 10
ORDER BY total_attempts DESC
LIMIT 50;

-- Cleanup preview (what would be deleted)
-- Run before cleanup to see impact
SELECT
    COUNT(*) AS rows_to_delete,
    COUNT(DISTINCT key_name) AS keys_affected,
    MIN(FROM_UNIXTIME(window_start)) AS oldest,
    MAX(FROM_UNIXTIME(window_start)) AS newest
FROM rate_limit_agg
WHERE window_start < UNIX_TIMESTAMP() - 86400;
