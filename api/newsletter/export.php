<?php
require_once "../bootstrap.php";
requireAuth();
require_method(["GET"]);

$pdo = db();

try {
    $stmt = $pdo->query("SELECT s.email, s.confirmed_at, s.created_at, p.notify_chapters, p.notify_blog, p.notify_gallery FROM email_subscribers s LEFT JOIN email_preferences p ON p.subscriber_id = s.id WHERE s.is_confirmed = 1 AND s.unsubscribed_at IS NULL ORDER BY s.created_at DESC");
    $subscribers = $stmt->fetchAll();

    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"newsletter-subscribers.csv\"");

    $output = fopen("php://output", "w");
    fputcsv($output, ["email", "created_at", "confirmed_at", "notify_chapters", "notify_blog", "notify_gallery"]);

    foreach ($subscribers as $row) {
        fputcsv($output, [
            $row['email'],
            $row['created_at'],
            $row['confirmed_at'],
            ($row['notify_chapters'] ?? 0) ? 1 : 0,
            ($row['notify_blog'] ?? 0) ? 1 : 0,
            ($row['notify_gallery'] ?? 0) ? 1 : 0,
        ]);
    }

    fclose($output);
    exit();
} catch (Throwable $e) {
    error_log("Newsletter export failed: " . $e->getMessage());
    json_error("Unable to export subscribers", 500);
}
