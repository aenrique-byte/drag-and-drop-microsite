# Database Migrations

## How to Run Migrations

Execute these SQL files in order on your database.

### 001 - Rate Limiting Table

Run this first to enable rate limiting:

```bash
mysql -u your_username -p your_database < 001_create_rate_limit_table.sql
```

Or via phpMyAdmin: Import the SQL file.

## Rate Limiting Configuration

### Current Limits

- **Login**: 10 attempts per 15 minutes per IP+username (fail-closed)
- **Chapter Comments**: 5 comments per minute per IP (fail-open)
- **Gallery Comments**: 20 comments per minute per IP (fail-open)

### Headers Returned

All rate-limited endpoints return these headers:

```
X-RateLimit-Limit: 10
X-RateLimit-Remaining: 8
X-RateLimit-Reset: 1234567890
```

On 429 (Too Many Requests):

```
Retry-After: 45
```

### Maintenance

Run the cleanup script daily via cron:

```cron
0 2 * * * /usr/bin/php /path/to/api/cron/cleanup-rate-limits.php
```

This removes rate limit entries older than 24 hours to prevent table bloat.

### Trusted Proxies

If you're behind Cloudflare or another proxy, update `bootstrap.php`:

```php
function getClientIP() {
    $trustedProxies = ['your.cloudflare.ip']; // Add your proxy IPs here
    // ...
}
```

Otherwise, X-Forwarded-For headers are ignored for security.
