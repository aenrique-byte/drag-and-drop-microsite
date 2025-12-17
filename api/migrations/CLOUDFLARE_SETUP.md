# Cloudflare & Trusted Proxy Configuration

## Overview

The rate limiting system uses CIDR-aware IP detection to safely trust proxy headers only when the request comes from a known trusted source (like Cloudflare).

## How It Works

1. **CIDR Matching**: The system checks if `REMOTE_ADDR` matches any trusted proxy CIDR range
2. **Header Trust**: Only if the request comes from a trusted proxy, we trust:
   - `CF-Connecting-IP` (Cloudflare's true client IP header)
   - `X-Forwarded-For` (left-most hop = original client)
3. **Fallback**: If no trusted proxy, use `REMOTE_ADDR` directly

## Pre-configured Cloudflare Ranges

The system comes pre-configured with Cloudflare's official IP ranges in `bootstrap.php`:

```php
$trustedProxies = [
    // Cloudflare IPv4 ranges
    '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
    '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
    '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
    '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    // Cloudflare IPv6 ranges
    '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
    '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
];
```

## If You're NOT Behind Cloudflare

If you're **not** using Cloudflare (or any proxy), update `bootstrap.php`:

```php
function getClientIP() {
    // Empty array = no trusted proxies
    $trustedProxies = [];
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // ... rest of code will use REMOTE_ADDR directly
}
```

## If You're Behind a Different Proxy

Add your proxy's IP ranges to the `$trustedProxies` array:

```php
$trustedProxies = [
    '203.0.113.10',           // Single IP
    '192.168.1.0/24',         // CIDR range
    '10.0.0.0/8',             // Large CIDR range
];
```

## Updating Cloudflare Ranges

Cloudflare occasionally updates their IP ranges. To get the latest:

1. Visit: https://www.cloudflare.com/ips/
2. Copy the IPv4 and IPv6 ranges
3. Update the `$trustedProxies` array in `api/bootstrap.php`

## Security Benefits

✅ **Prevents IP Spoofing**: Only trusts headers when request comes from known proxy
✅ **CIDR-Aware**: Supports both single IPs and CIDR ranges
✅ **IPv6 Support**: Works with both IPv4 and IPv6
✅ **Cloudflare-Optimized**: Pre-loaded with CF ranges for zero configuration
✅ **Fallback Safety**: Always falls back to REMOTE_ADDR if validation fails

## Testing

To verify your IP detection is working:

```php
// Add to any endpoint temporarily
error_log('Detected IP: ' . getClientIP());
error_log('REMOTE_ADDR: ' . ($_SERVER['REMOTE_ADDR'] ?? 'none'));
error_log('CF-Connecting-IP: ' . ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? 'none'));
error_log('X-Forwarded-For: ' . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'none'));
```

## Rate Limiting Impact

Rate limits are keyed by IP address:
- **Login**: `login:<ip>:<username>` (prevents brute force per IP+user combo)
- **Comments**: `comment_create:<ip>` (prevents spam from single IP)

Correct IP detection ensures:
- Legitimate users behind the same proxy aren't blocked together
- Attackers can't bypass limits by spoofing headers
- Rate limits work as intended across different network topologies
