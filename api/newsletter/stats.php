<?php
require_once "../bootstrap.php";
requireAuth();
require_method(["GET"]);

$pdo = db();

try {
    $summaryStmt = $pdo->query("SELECT COUNT(*) AS total FROM email_subscribers");
    $totalSubscribers = (int) $summaryStmt->fetchColumn();

    $confirmedStmt = $pdo->query("SELECT COUNT(*) FROM email_subscribers WHERE is_confirmed = 1 AND unsubscribed_at IS NULL");
    $confirmedSubscribers = (int) $confirmedStmt->fetchColumn();

    $unconfirmedStmt = $pdo->query("SELECT COUNT(*) FROM email_subscribers WHERE is_confirmed = 0 AND unsubscribed_at IS NULL");
    $unconfirmedSubscribers = (int) $unconfirmedStmt->fetchColumn();

    $unsubscribedStmt = $pdo->query("SELECT COUNT(*) FROM email_subscribers WHERE unsubscribed_at IS NOT NULL");
    $unsubscribed = (int) $unsubscribedStmt->fetchColumn();
    $confirmationRate = $totalSubscribers > 0 ? round(($confirmedSubscribers / $totalSubscribers) * 100, 2) : 0;

    $sourceStmt = $pdo->query("SELECT source, COUNT(*) as count FROM email_subscribers WHERE (source IS NOT NULL AND source != '') AND unsubscribed_at IS NULL GROUP BY source ORDER BY count DESC");
    $bySource = [];
    foreach ($sourceStmt->fetchAll() as $row) {
        $bySource[$row['source']] = (int) $row['count'];
    }

    $prefsStmt = $pdo->query("SELECT SUM(p.notify_chapters) as chapters, SUM(p.notify_blog) as blog, SUM(p.notify_gallery) as gallery FROM email_preferences p JOIN email_subscribers s ON s.id = p.subscriber_id WHERE s.unsubscribed_at IS NULL");
    $prefs = $prefsStmt->fetch();
    $byPreference = [
        "notify_chapters" => isset($prefs['chapters']) ? (int) $prefs['chapters'] : 0,
        "notify_blog" => isset($prefs['blog']) ? (int) $prefs['blog'] : 0,
        "notify_gallery" => isset($prefs['gallery']) ? (int) $prefs['gallery'] : 0,
    ];

    $recentStmt = $pdo->query("SELECT s.id, s.email, s.created_at, s.source, s.is_confirmed, p.notify_chapters, p.notify_blog, p.notify_gallery FROM email_subscribers s LEFT JOIN email_preferences p ON p.subscriber_id = s.id WHERE s.unsubscribed_at IS NULL ORDER BY s.created_at DESC LIMIT 20");
    $recentSignups = $recentStmt->fetchAll();

    jsonResponse([
        "success" => true,
        "total_subscribers" => $totalSubscribers,
        "confirmed_subscribers" => $confirmedSubscribers,
        "unconfirmed_subscribers" => $unconfirmedSubscribers,
        "unsubscribed" => $unsubscribed,
        "confirmation_rate" => $confirmationRate,
        "by_source" => $bySource,
        "by_preference" => $byPreference,
        "recent_signups" => $recentSignups,
    ]);
} catch (Throwable $e) {
    error_log("Newsletter stats failed: " . $e->getMessage());
    json_error("Unable to load newsletter stats", 500);
}
