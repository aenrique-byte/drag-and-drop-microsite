<?php
require_once "../bootstrap.php";
require_method(["POST"]);

$data = body_json();
$email = strtolower(trim($data['email'] ?? ""));
$notifyChapters = isset($data['notify_chapters']) ? (bool) $data['notify_chapters'] : true;
$notifyBlog = isset($data['notify_blog']) ? (bool) $data['notify_blog'] : true;
$notifyGallery = isset($data['notify_gallery']) ? (bool) $data['notify_gallery'] : true;

if (!validateEmail($email)) {
    json_error("Please enter a valid email address.", 400);
}

$pdo = db();

try {
    $stmt = $pdo->prepare("SELECT id, unsubscribed_at FROM email_subscribers WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $subscriber = $stmt->fetch();

    if (!$subscriber || !empty($subscriber['unsubscribed_at'])) {
        jsonResponse([
            "success" => false,
            "message" => "Subscription not found."
        ], 404);
    }

    $subscriberId = (int) $subscriber['id'];

    $prefs = $pdo->prepare("INSERT INTO email_preferences (subscriber_id, notify_chapters, notify_blog, notify_gallery) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE notify_chapters = VALUES(notify_chapters), notify_blog = VALUES(notify_blog), notify_gallery = VALUES(notify_gallery)");
    $prefs->execute([
        $subscriberId,
        $notifyChapters ? 1 : 0,
        $notifyBlog ? 1 : 0,
        $notifyGallery ? 1 : 0,
    ]);

    $logDetails = json_encode([
        "notify_chapters" => $notifyChapters,
        "notify_blog" => $notifyBlog,
        "notify_gallery" => $notifyGallery,
    ], JSON_UNESCAPED_SLASHES);

    $log = $pdo->prepare("INSERT INTO email_subscription_log (subscriber_id, action, details, ip_address, user_agent) VALUES (?, 'preference_updated', ?, ?, ?)");
    $log->execute([
        $subscriberId,
        $logDetails,
        getClientIP(),
        getUserAgent(),
    ]);

    jsonResponse([
        "success" => true,
        "message" => "Preferences updated successfully."
    ]);
} catch (Throwable $e) {
    error_log("Newsletter preference update failed: " . $e->getMessage());
    jsonResponse([
        "success" => false,
        "message" => "Unable to update preferences right now."
    ], 500);
}
