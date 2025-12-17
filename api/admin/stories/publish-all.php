<?php
require_once '../../bootstrap.php';

// Require admin session
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo 'Not authenticated';
    exit;
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$method = $_SERVER['REQUEST_METHOD'];
$isJson = isset($_REQUEST['format']) && $_REQUEST['format'] === 'json';

try {
    global $pdo;

    if ($method === 'POST') {
        $storyId = isset($_POST['story_id']) ? (int)$_POST['story_id'] : 0;
        $dryRun  = isset($_POST['dry_run']) && $_POST['dry_run'] === '1';

        if ($storyId <= 0) {
            if ($isJson) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'story_id is required']);
                exit;
            }
            $error = 'story_id is required';
        } else {
            // Ensure story exists
            $stmt = $pdo->prepare("SELECT id, title FROM stories WHERE id = ?");
            $stmt->execute([$storyId]);
            $story = $stmt->fetch();

            if (!$story) {
                if ($isJson) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Story not found']);
                    exit;
                }
                $error = 'Story not found';
            } else {
                if ($dryRun) {
                    // Count non-published chapters
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM chapters WHERE story_id = ? AND status <> 'published'");
                    $stmt->execute([$storyId]);
                    $wouldAffect = (int)$stmt->fetchColumn();

                    if ($isJson) {
                        echo json_encode([
                            'success' => true,
                            'dry_run' => true,
                            'story_id' => $storyId,
                            'story_title' => $story['title'],
                            'would_affect' => $wouldAffect
                        ]);
                        exit;
                    }
                    $message = "Dry Run: Would publish {$wouldAffect} chapter(s) for \"".h($story['title'])."\".";
                } else {
                    // Publish all non-published chapters; touch publish_at if null
                    $stmt = $pdo->prepare("
                        UPDATE chapters
                        SET status = 'published',
                            updated_at = NOW(),
                            publish_at = IF(publish_at IS NULL, NOW(), publish_at)
                        WHERE story_id = ? AND status <> 'published'
                    ");
                    $stmt->execute([$storyId]);
                    $affected = $stmt->rowCount();

                    // Touch story updated_at
                    $stmt = $pdo->prepare("UPDATE stories SET updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$storyId]);

                    if ($isJson) {
                        echo json_encode([
                            'success' => true,
                            'story_id' => $storyId,
                            'story_title' => $story['title'],
                            'affected' => $affected
                        ]);
                        exit;
                    }
                    $message = "Published {$affected} chapter(s) for \"".h($story['title'])."\".";
                }
            }
        }
    }

    // GET (or POST fallback to HTML render)
    // Fetch stories with non-published counts
    $stmt = $pdo->query("
        SELECT s.id, s.title,
               COALESCE(SUM(CASE WHEN c.id IS NOT NULL AND c.status <> 'published' THEN 1 ELSE 0 END), 0) AS non_published_count,
               COALESCE(COUNT(c.id), 0) AS total_chapters
        FROM stories s
        LEFT JOIN chapters c ON c.story_id = s.id
        GROUP BY s.id, s.title
        ORDER BY s.updated_at DESC
    ");
    $stories = $stmt->fetchAll();

    if ($isJson) {
        echo json_encode([
            'success' => true,
            'stories' => $stories
        ]);
        exit;
    }

} catch (Exception $e) {
    if ($isJson) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error']);
        exit;
    }
    $error = 'Server error: ' . h($e->getMessage());
}

// Render simple Admin HTML
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Admin · Publish All Chapters</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; margin: 20px; color: #111; }
    .wrap { max-width: 1000px; margin: 0 auto; }
    h1 { font-size: 22px; margin-bottom: 16px; }
    table { width: 100%; border-collapse: collapse; background: #fff; }
    th, td { padding: 8px 10px; border-bottom: 1px solid #ddd; text-align: left; }
    th { background: #f7f7f7; }
    .row-actions { display: flex; gap: 8px; align-items: center; }
    .btn { display: inline-block; padding: 6px 10px; border-radius: 6px; border: 1px solid #ccc; background: #fafafa; cursor: pointer; }
    .btn-primary { background: #2563eb; border-color: #1d4ed8; color: #fff; }
    .btn-warn { background: #e11d48; border-color: #be123c; color: #fff; }
    .msg { padding: 10px; background: #ecfeff; border: 1px solid #a5f3fc; color: #064e3b; border-radius: 6px; margin-bottom: 16px; }
    .err { padding: 10px; background: #fee2e2; border: 1px solid #fecaca; color: #7f1d1d; border-radius: 6px; margin-bottom: 16px; }
    .muted { color: #555; }
    @media (max-width: 640px) { .row-actions { flex-direction: column; align-items: flex-start; } }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Publish All Chapters (per Story)</h1>
    <p class="muted">Use this tool to publish all non-published chapters (drafts) for a selected story.</p>

    <?php if (!empty($message)): ?>
      <div class="msg"><?= $message ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
      <div class="err"><?= $error ?></div>
    <?php endif; ?>

    <table>
      <thead>
        <tr>
          <th>Story</th>
          <th>Total Chapters</th>
          <th>Non‑Published</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($stories)): ?>
          <?php foreach ($stories as $s): ?>
            <tr>
              <td><?= h($s['title']) ?> <span class="muted">(#<?= (int)$s['id'] ?>)</span></td>
              <td><?= (int)$s['total_chapters'] ?></td>
              <td><?= (int)$s['non_published_count'] ?></td>
              <td>
                <div class="row-actions">
                  <form method="post" action="" onsubmit="return true;">
                    <input type="hidden" name="story_id" value="<?= (int)$s['id'] ?>" />
                    <input type="hidden" name="dry_run" value="1" />
                    <button class="btn" type="submit">Dry Run</button>
                  </form>
                  <form method="post" action="" onsubmit="return confirm('Publish all non-published chapters for this story?');">
                    <input type="hidden" name="story_id" value="<?= (int)$s['id'] ?>" />
                    <button class="btn btn-primary" type="submit">Publish All</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="4">No stories found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <p class="muted" style="margin-top:12px;">JSON mode: append <code>?format=json</code> to the URL for machine-readable responses.</p>
  </div>
</body>
</html>
