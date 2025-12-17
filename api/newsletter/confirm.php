<?php
require_once "../bootstrap.php";
require_method(["GET"]);

$token = trim($_GET['token'] ?? "");

function respondConfirmationMessage($success, $message, $statusCode = 200) {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $wantsHtml = strpos($accept, 'text/html') !== false;

    if ($wantsHtml) {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>';
        echo '<html lang="en">';
        echo '<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Newsletter Confirmation</title>';
        echo '<style>body{font-family:Arial,sans-serif;background:#f8fafc;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;padding:16px;}';
        echo '.card{background:#fff;max-width:480px;width:100%;padding:24px;border-radius:12px;box-shadow:0 10px 30px rgba(15,23,42,0.12);}';
        echo '.title{font-size:20px;margin-bottom:8px;color:#0f172a;}';
        echo '.message{color:#334155;line-height:1.6;margin-bottom:16px;}';
        echo '.status{display:inline-flex;align-items:center;gap:8px;font-weight:600;margin-bottom:12px;}';
        echo '.success{color:#16a34a;}';
        echo '.error{color:#b91c1c;}';
        echo '</style></head><body>';
        echo '<div class="card">';
        echo '<div class="status ' . ($success ? 'success' : 'error') . '">' . ($success ? '✅' : '⚠️') . ' Newsletter confirmation</div>';
        echo '<div class="title">' . ($success ? 'Subscription confirmed' : 'Unable to confirm') . '</div>';
        echo '<div class="message">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>';
        echo '</div></body></html>';
        exit();
    }

    jsonResponse([
        "success" => $success,
        "message" => $message,
    ], $statusCode);
}

if (empty($token)) {
    respondConfirmationMessage(false, "Confirmation token is required.", 400);
}

$pdo = db();

try {
    $stmt = $pdo->prepare("SELECT id, is_confirmed, unsubscribed_at FROM email_subscribers WHERE confirmation_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $subscriber = $stmt->fetch();

    if (!$subscriber) {
        respondConfirmationMessage(false, "Invalid or expired confirmation link.", 404);
    }

    if (!empty($subscriber['unsubscribed_at'])) {
        respondConfirmationMessage(false, "This subscription has been unsubscribed.", 410);
    }

    $subscriberId = (int) $subscriber['id'];

    if ((bool) $subscriber['is_confirmed']) {
        respondConfirmationMessage(true, "Email already confirmed.");
    }

    $update = $pdo->prepare("UPDATE email_subscribers SET is_confirmed = 1, confirmed_at = NOW(), unsubscribed_at = NULL WHERE id = ?");
    $update->execute([$subscriberId]);

    $log = $pdo->prepare("INSERT INTO email_subscription_log (subscriber_id, action, details, ip_address, user_agent) VALUES (?, 'confirmed', ?, ?, ?)");
    $log->execute([
        $subscriberId,
        json_encode(["confirmation_token" => $token], JSON_UNESCAPED_SLASHES),
        getClientIP(),
        getUserAgent(),
    ]);

    respondConfirmationMessage(true, "Email confirmed! You're now subscribed.");
} catch (Throwable $e) {
    error_log("Newsletter confirm failed: " . $e->getMessage());
    respondConfirmationMessage(false, "Unable to confirm subscription at this time.", 500);
}
