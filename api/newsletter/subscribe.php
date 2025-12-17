<?php
require_once "../bootstrap.php";
require_once __DIR__ . "/../shoutouts/smtp-mailer.php";
require_once __DIR__ . "/../email/helpers.php";
require_method(["POST"]);

$newsletterEnabled = defined("NEWSLETTER_ENABLED") ? filter_var(NEWSLETTER_ENABLED, FILTER_VALIDATE_BOOLEAN) : true;
if (!$newsletterEnabled) {
    jsonResponse([
        "success" => false,
        "message" => "Newsletter signups are temporarily unavailable."
    ]);
}

/**
 * Send confirmation email to subscriber
 */
function sendConfirmationEmail($email, $confirmationToken) {
    $config = get_email_config();
    $fromEmail = $config['from_email'] ?? 'noreply@ocwanderer.com';
    $fromName = $config['from_name'] ?? 'O.C. Wanderer';

    $confirmUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
                  . "://" . $_SERVER['HTTP_HOST']
                  . "/api/newsletter/confirm.php?token=" . urlencode($confirmationToken);

    $subject = "Please confirm your newsletter subscription";

    $htmlBody = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f8fafc; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 30px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; }
        .content { padding: 40px 30px; }
        .content p { margin: 0 0 16px; color: #475569; }
        .button { display: inline-block; padding: 14px 32px; background: #10b981; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; margin: 20px 0; }
        .button:hover { background: #059669; }
        .footer { padding: 20px 30px; background: #f1f5f9; text-align: center; font-size: 12px; color: #64748b; }
        .footer a { color: #10b981; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📬 Welcome to the Newsletter!</h1>
        </div>
        <div class="content">
            <p>Hi there,</p>
            <p>Thanks for signing up! Just one more step to start receiving updates about new chapters, blog posts, and galleries.</p>
            <p style="text-align: center;">
                <a href="' . htmlspecialchars($confirmUrl, ENT_QUOTES, 'UTF-8') . '" class="button">Confirm Your Subscription</a>
            </p>
            <p style="font-size: 14px; color: #64748b;">
                Or copy and paste this link into your browser:<br>
                <a href="' . htmlspecialchars($confirmUrl, ENT_QUOTES, 'UTF-8') . '" style="color: #10b981; word-break: break-all;">' . htmlspecialchars($confirmUrl, ENT_QUOTES, 'UTF-8') . '</a>
            </p>
            <p>Once confirmed, you\'ll get email notifications based on your preferences. You can unsubscribe or update your preferences at any time.</p>
            <p>Looking forward to sharing my latest work with you!</p>
            <p>— O.C. Wanderer</p>
        </div>
        <div class="footer">
            <p>You\'re receiving this because you (or someone) signed up for the newsletter at ocwanderer.com.</p>
            <p>If you didn\'t request this, you can safely ignore this email.</p>
        </div>
    </div>
</body>
</html>';

    try {
        $result = smtpSendHtml($email, $subject, $htmlBody, $fromEmail, $fromName, $fromEmail);

        // Log email attempt
        $logFile = __DIR__ . '/email-log.txt';
        $logEntry = date('c') . " | type=confirmation | to={$email} | subject=\"{$subject}\" | result=" . json_encode($result) . "\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND);

        return $result['success'] ?? false;
    } catch (Throwable $e) {
        error_log("Newsletter confirmation email failed: " . $e->getMessage());
        return false;
    }
}

$data = body_json();
$email = strtolower(trim($data['email'] ?? ""));
$notifyChapters = isset($data['notify_chapters']) ? (bool) $data['notify_chapters'] : true;
$notifyBlog = isset($data['notify_blog']) ? (bool) $data['notify_blog'] : true;
$notifyGallery = isset($data['notify_gallery']) ? (bool) $data['notify_gallery'] : true;
$source = substr(trim($data['source'] ?? ""), 0, 50);

if (!validateEmail($email)) {
    json_error("Please enter a valid email address.", 400);
}

$pdo = db();
$ip = getClientIP();
$userAgent = getUserAgent();
$rateLimitSeconds = defined("NEWSLETTER_RATE_LIMIT_SECONDS") ? (int) NEWSLETTER_RATE_LIMIT_SECONDS : 60;

try {
    $stmt = $pdo->prepare("SELECT created_at FROM email_subscription_log WHERE ip_address = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$ip]);
    $lastAttempt = $stmt->fetchColumn();
    if ($lastAttempt) {
        $diff = time() - strtotime($lastAttempt);
        if ($diff < $rateLimitSeconds) {
            error_log("Newsletter rate limit exceeded for IP " . $ip);
            jsonResponse([
                "success" => true,
                "message" => "Thanks! We'll send confirmation soon."
            ]);
        }
    }
} catch (Throwable $e) {
    error_log("Newsletter rate limit check failed: " . $e->getMessage());
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, is_confirmed, unsubscribed_at, source FROM email_subscribers WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    $confirmationToken = bin2hex(random_bytes(32));
    $unsubscribeToken = bin2hex(random_bytes(32));

    if ($existing) {
        $subscriberId = (int) $existing['id'];
        $updatedSource = $source ?: ($existing['source'] ?? null);

        $update = $pdo->prepare("UPDATE email_subscribers SET confirmation_token = ?, unsubscribe_token = ?, is_confirmed = 0, confirmed_at = NULL, unsubscribed_at = NULL, source = ? WHERE id = ?");
        $update->execute([$confirmationToken, $unsubscribeToken, $updatedSource, $subscriberId]);

        $prefs = $pdo->prepare("INSERT INTO email_preferences (subscriber_id, notify_chapters, notify_blog, notify_gallery) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE notify_chapters = VALUES(notify_chapters), notify_blog = VALUES(notify_blog), notify_gallery = VALUES(notify_gallery)");
        $prefs->execute([
            $subscriberId,
            $notifyChapters ? 1 : 0,
            $notifyBlog ? 1 : 0,
            $notifyGallery ? 1 : 0,
        ]);

        $logDetails = json_encode([
            "source" => $updatedSource,
            "reason" => "duplicate_or_resubscribe",
            "notify_chapters" => $notifyChapters,
            "notify_blog" => $notifyBlog,
            "notify_gallery" => $notifyGallery,
        ], JSON_UNESCAPED_SLASHES);

        $log = $pdo->prepare("INSERT INTO email_subscription_log (subscriber_id, action, details, ip_address, user_agent) VALUES (?, 'subscribed', ?, ?, ?)");
        $log->execute([$subscriberId, $logDetails, $ip, $userAgent]);

        $pdo->commit();

        // Send confirmation email (for re-subscribe)
        sendConfirmationEmail($email, $confirmationToken);

        jsonResponse([
            "success" => true,
            "message" => "You're already subscribed! Check your email.",
            "subscriber_id" => $subscriberId,
        ]);
    }

    $insert = $pdo->prepare("INSERT INTO email_subscribers (email, is_confirmed, confirmation_token, source, unsubscribe_token, created_at) VALUES (?, 0, ?, ?, ?, NOW())");
    $insert->execute([$email, $confirmationToken, $source, $unsubscribeToken]);
    $subscriberId = (int) $pdo->lastInsertId();

    $prefs = $pdo->prepare("INSERT INTO email_preferences (subscriber_id, notify_chapters, notify_blog, notify_gallery) VALUES (?, ?, ?, ?)");
    $prefs->execute([
        $subscriberId,
        $notifyChapters ? 1 : 0,
        $notifyBlog ? 1 : 0,
        $notifyGallery ? 1 : 0,
    ]);

    $logDetails = json_encode([
        "source" => $source,
        "notify_chapters" => $notifyChapters,
        "notify_blog" => $notifyBlog,
        "notify_gallery" => $notifyGallery,
    ], JSON_UNESCAPED_SLASHES);

    $log = $pdo->prepare("INSERT INTO email_subscription_log (subscriber_id, action, details, ip_address, user_agent) VALUES (?, 'subscribed', ?, ?, ?)");
    $log->execute([$subscriberId, $logDetails, $ip, $userAgent]);

    $pdo->commit();

    // Send confirmation email (don't fail if email fails)
    sendConfirmationEmail($email, $confirmationToken);

    jsonResponse([
        "success" => true,
        "message" => "Please check your email to confirm.",
        "subscriber_id" => $subscriberId,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Newsletter subscribe failed: " . $e->getMessage());
    jsonResponse([
        "success" => true,
        "message" => "Thanks! We'll send confirmation soon."
    ]);
}
