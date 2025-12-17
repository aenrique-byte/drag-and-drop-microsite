<?php
declare(strict_types=1);
// Load bootstrap from parent directory with absolute path
$bootstrapPath = dirname(__DIR__) . '/bootstrap.php';
require_once $bootstrapPath;
require_once __DIR__ . '/smtp-mailer.php';
require_once __DIR__ . '/../email/helpers.php';

// GET /api/shoutouts/bookings.php - Get all bookings or filtered by storyId
// POST /api/shoutouts/bookings.php - Create a new booking (public)
// PUT /api/shoutouts/bookings.php - Update booking status (admin only)
// DELETE /api/shoutouts/bookings.php?id=xxx - Delete booking (admin only)

$method = $_SERVER['REQUEST_METHOD'];

// Helper function to format date nicely (e.g., "December 16th, 2025")
function formatDateNice($dateStr) {
    $timestamp = strtotime($dateStr);
    $day = date('j', $timestamp);
    $suffix = 'th';
    if ($day == 1 || $day == 21 || $day == 31) $suffix = 'st';
    elseif ($day == 2 || $day == 22) $suffix = 'nd';
    elseif ($day == 3 || $day == 23) $suffix = 'rd';
    
    return date('F ', $timestamp) . $day . $suffix . date(', Y', $timestamp);
}

// Helper: send admin notification when a new booking is created
function sendNewBookingNotification($booking) {
    $config = get_email_config();
    $adminEmail = $config['admin_email'] ?? '';
    
    if (!$adminEmail) {
        return ['success' => false, 'log' => 'admin_email not configured'];
    }

    $fromEmail = $config['from_email'] ?? 'noreply@example.com';
    $fromName = $config['from_name'] ?? 'Shoutout Manager';

    $dateStr = $booking['dateStr'] ?? '';
    $niceDate = $dateStr ? formatDateNice($dateStr) : $dateStr;

    $subject = 'New Shoutout Request for ' . $niceDate;

    $htmlBody = '
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background: #f8fafc; padding: 20px;">
  <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 24px; box-shadow: 0 4px 10px rgba(15,23,42,0.08);">
    <h1 style="margin: 0 0 12px 0; font-size: 20px; color: #0f172a;">New Shoutout Request</h1>
    <p style="margin: 0 0 16px 0; color: #475569;">Someone just submitted a new shoutout request.</p>

    <table cellpadding="6" cellspacing="0" style="width: 100%; font-size: 14px; color: #0f172a;">
      <tr>
        <td style="font-weight: 600; width: 140px; color: #64748b;">Date</td>
        <td>' . htmlspecialchars($niceDate) . '</td>
      </tr>
      <tr>
        <td style="font-weight: 600; color: #64748b;">Author</td>
        <td>' . htmlspecialchars($booking['authorName'] ?? '') . '</td>
      </tr>
      <tr>
        <td style="font-weight: 600; color: #64748b;">Email</td>
        <td><a href="mailto:' . htmlspecialchars($booking['email'] ?? '') . '" style="color: #2563eb; text-decoration: none;">' . htmlspecialchars($booking['email'] ?? '') . '</a></td>
      </tr>
      <tr>
        <td style="font-weight: 600; color: #64748b;">Story Link</td>
        <td><a href="' . htmlspecialchars($booking['storyLink'] ?? '') . '" style="color: #2563eb;">' . htmlspecialchars($booking['storyLink'] ?? '') . '</a></td>
      </tr>
    </table>

    <div style="margin-top: 20px;">
      <div style="font-weight: 600; font-size: 12px; text-transform: uppercase; color: #64748b; margin-bottom: 6px;">Submitted Code</div>
      <div style="background: #0f172a; color: #e2e8f0; padding: 12px; border-radius: 8px; font-family: SFMono-Regular,Menlo,Monaco,Consolas,\'Liberation Mono\',\'Courier New\',monospace; font-size: 12px; white-space: pre-wrap; max-height: 200px; overflow-y: auto;">
        ' . htmlspecialchars($booking['shoutoutCode'] ?? '') . '
      </div>
    </div>

    <p style="margin-top: 20px; font-size: 12px; color: #94a3b8;">To approve or reject this request, log into your admin panel.</p>
  </div>
</body>
</html>';

    $result = smtpSendHtml($adminEmail, $subject, $htmlBody, $fromEmail, $fromName, $fromEmail);

    // Log notification attempt
    $logFile = __DIR__ . '/email-log.txt';
    $logEntry = date('c') . " | type=new_booking | to={$adminEmail} | subject=\"{$subject}\" | result=" . json_encode($result) . "\n";
    @file_put_contents($logFile, $logEntry, FILE_APPEND);

    return $result;
}

// Helper function to send approval email
function sendApprovalEmail($pdo, $booking, $story, $shoutouts) {
    $config = get_email_config();
    $to = $booking['email'];
    $adminEmail = $config['admin_email'] ?? '';
    
    // Format the date nicely
    $niceDate = formatDateNice($booking['date_str']);
    
    // Get color scheme based on story color
    $colorSchemes = [
        'amber' => ['primary' => '#f59e0b', 'light' => '#fef3c7', 'dark' => '#92400e'],
        'blue' => ['primary' => '#2563eb', 'light' => '#dbeafe', 'dark' => '#1e40af'],
        'rose' => ['primary' => '#e11d48', 'light' => '#ffe4e6', 'dark' => '#9f1239'],
        'emerald' => ['primary' => '#059669', 'light' => '#d1fae5', 'dark' => '#065f46'],
        'violet' => ['primary' => '#7c3aed', 'light' => '#ede9fe', 'dark' => '#5b21b6'],
        'cyan' => ['primary' => '#0891b2', 'light' => '#cffafe', 'dark' => '#155e75'],
    ];
    
    $color = $colorSchemes[$story['color']] ?? $colorSchemes['amber'];
    
    // Build shoutout codes HTML
    $shoutoutCodesHtml = '';
    foreach ($shoutouts as $shoutout) {
        $shoutoutCodesHtml .= '
        <div style="margin-bottom: 20px; padding: 15px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
            <div style="font-weight: bold; color: ' . $color['dark'] . '; margin-bottom: 10px; font-size: 14px; text-transform: uppercase;">' . htmlspecialchars($shoutout['label']) . '</div>
            <div style="background: #1e293b; color: #94a3b8; padding: 12px; border-radius: 6px; font-family: monospace; font-size: 12px; white-space: pre-wrap; word-break: break-all; max-height: 150px; overflow-y: auto;">' . htmlspecialchars($shoutout['code']) . '</div>
        </div>';
    }
    
    if (empty($shoutoutCodesHtml)) {
        $shoutoutCodesHtml = '<p style="color: #64748b; font-style: italic;">No shoutout codes configured yet.</p>';
    }
    
    $subject = "Shoutout APPROVED for " . $niceDate . " - " . $story['title'];
    
    $htmlBody = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f1f5f9;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <!-- Header -->
        <div style="background: linear-gradient(135deg, ' . $color['primary'] . ' 0%, ' . $color['dark'] . ' 100%); border-radius: 16px 16px 0 0; padding: 30px; text-align: center;">
            <div style="font-size: 48px; margin-bottom: 10px;">🎉</div>
            <h1 style="color: white; margin: 0; font-size: 28px; font-weight: bold;">Shoutout Approved!</h1>
            <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 16px;">Your request for <strong>' . htmlspecialchars($niceDate) . '</strong> has been approved</p>
        </div>
        
        <!-- Main Content -->
        <div style="background: white; padding: 30px; border-radius: 0 0 16px 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <!-- Story Card -->
            <div style="display: flex; margin-bottom: 30px; padding: 20px; background: ' . $color['light'] . '; border-radius: 12px; border: 2px solid ' . $color['primary'] . ';">
                <div style="flex-shrink: 0; margin-right: 20px;">
                    <img src="' . htmlspecialchars($story['cover_image']) . '" alt="' . htmlspecialchars($story['title']) . '" style="width: 80px; height: 120px; object-fit: cover; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">
                </div>
                <div style="flex: 1;">
                    <h2 style="margin: 0 0 10px 0; color: ' . $color['dark'] . '; font-size: 20px;">' . htmlspecialchars($story['title']) . '</h2>
                    <p style="margin: 0 0 15px 0; color: #64748b; font-size: 14px;">Your shoutout swap partner</p>
                    <a href="' . htmlspecialchars($story['link']) . '" style="display: inline-block; background: ' . $color['primary'] . '; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 14px;">📖 Read on Royal Road</a>
                </div>
            </div>
            
            <!-- Greeting -->
            <p style="color: #334155; font-size: 16px; line-height: 1.6;">
                Hey <strong>' . htmlspecialchars($booking['author_name']) . '</strong>! 👋
            </p>
            <p style="color: #334155; font-size: 16px; line-height: 1.6;">
                Great news! Your shoutout request has been approved. Here\'s what you need to know:
            </p>
            
            <!-- Details Box -->
            <div style="background: #f8fafc; border-radius: 12px; padding: 20px; margin: 20px 0; border-left: 4px solid ' . $color['primary'] . ';">
                <div style="margin-bottom: 12px;">
                    <span style="color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: bold;">Scheduled Date</span>
                    <div style="color: #1e293b; font-size: 18px; font-weight: bold;">' . htmlspecialchars($niceDate) . '</div>
                </div>
                <div>
                    <span style="color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: bold;">Your Story</span>
                    <div style="color: #1e293b; font-size: 14px;"><a href="' . htmlspecialchars($booking['story_link']) . '" style="color: ' . $color['primary'] . ';">' . htmlspecialchars($booking['story_link']) . '</a></div>
                </div>
            </div>
            
            <!-- Shoutout Codes Section -->
            <div style="margin-top: 30px;">
                <h3 style="color: ' . $color['dark'] . '; font-size: 18px; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid ' . $color['light'] . ';">
                    📋 My Shoutout Code(s) for You
                </h3>
                <p style="color: #64748b; font-size: 14px; margin-bottom: 20px;">
                    Copy and paste the code below into your chapter\'s author note on the scheduled date:
                </p>
                ' . $shoutoutCodesHtml . '
            </div>
            
            <!-- Reminder -->
            <div style="background: #fef3c7; border-radius: 12px; padding: 20px; margin-top: 30px; border: 1px solid #fcd34d;">
                <div style="font-weight: bold; color: #92400e; margin-bottom: 8px;">⏰ Reminder</div>
                <p style="color: #78350f; font-size: 14px; margin: 0; line-height: 1.5;">
                    Please add the shoutout code to your chapter on <strong>' . htmlspecialchars($niceDate) . '</strong>. 
                    I\'ll be adding your shoutout to my chapter on the same day!
                </p>
            </div>
            
            <!-- Footer -->
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; text-align: center;">
                <p style="color: #94a3b8; font-size: 12px; margin: 0;">
                    Happy writing! 📝<br>
                    <em>Sent via Shoutout Manager</em>
                </p>
            </div>
        </div>
    </div>
</body>
</html>';

    $fromEmail = $config['from_email'] ?? 'noreply@example.com';
    $fromName = $config['from_name'] ?? 'Shoutout Manager';

    $debug = [
        'to' => $to,
        'subject' => $subject,
        'fromEmail' => $fromEmail,
        'fromName' => $fromName,
        'adminEmail' => $adminEmail,
    ];

    // Send to user via SMTP
    $smtpUserResult = smtpSendHtml($to, $subject, $htmlBody, $fromEmail, $fromName, $fromEmail);
    $userSent = $smtpUserResult['success'] ?? false;
    $debug['smtp_user'] = $smtpUserResult;

    // Admin copy
    $adminSent = false;
    if ($adminEmail) {
        $adminSubject = "[COPY] " . $subject;
        $smtpAdminResult = smtpSendHtml($adminEmail, $adminSubject, $htmlBody, $fromEmail, $fromName, $fromEmail);
        $adminSent = $smtpAdminResult['success'] ?? false;
        $debug['smtp_admin'] = $smtpAdminResult;
    }

    // Log email attempt
    $logFile = __DIR__ . '/email-log.txt';
    $logEntry = date('c') . " | to={$to} | admin={$adminEmail} | subject=\"{$subject}\" | userSent=" . ($userSent ? '1' : '0') . " | adminSent=" . ($adminSent ? '1' : '0') . " | debug=" . json_encode($debug) . "\n";
    @file_put_contents($logFile, $logEntry, FILE_APPEND);

    return [
        'userSent' => $userSent,
        'adminSent' => $adminSent,
        'debug' => $debug
    ];
}

// GET - Get all bookings or filtered by storyId
if ($method === 'GET') {
    try {
        $pdo = db();
        $storyId = $_GET['storyId'] ?? null;
        
        if ($storyId) {
            $stmt = $pdo->prepare("SELECT * FROM shoutout_bookings WHERE story_id = ? ORDER BY created_at DESC");
            $stmt->execute([$storyId]);
        } else {
            $stmt = $pdo->query("SELECT * FROM shoutout_bookings ORDER BY created_at DESC");
        }
        
        $bookings = $stmt->fetchAll();
        
        // Convert to frontend format (camelCase)
        $result = array_map(function($booking) {
            return [
                'id' => $booking['id'],
                'dateStr' => $booking['date_str'],
                'storyId' => $booking['story_id'],
                'authorName' => $booking['author_name'],
                'storyLink' => $booking['story_link'],
                'shoutoutCode' => $booking['shoutout_code'],
                'email' => $booking['email'],
                'status' => $booking['status'],
                'createdAt' => strtotime($booking['created_at']) * 1000 // Milliseconds for JS
            ];
        }, $bookings);
        
        json_response($result);
        
    } catch (Throwable $e) {
        json_error('Failed to fetch bookings.', 500, ['detail' => $e->getMessage()]);
    }
}

// POST - Create a new booking (public endpoint)
if ($method === 'POST') {
    try {
        $pdo = db();
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['dateStr']) || !isset($input['storyId']) || !isset($input['authorName']) || 
            !isset($input['storyLink']) || !isset($input['shoutoutCode']) || !isset($input['email'])) {
            json_error('Missing required fields', 400);
        }
        
        // Generate random ID
        $id = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 7);
        
        $stmt = $pdo->prepare("
            INSERT INTO shoutout_bookings 
            (id, date_str, story_id, author_name, story_link, shoutout_code, email, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $stmt->execute([
            $id,
            $input['dateStr'],
            $input['storyId'],
            $input['authorName'],
            $input['storyLink'],
            $input['shoutoutCode'],
            $input['email']
        ]);
        
        // Return the created booking
        $booking = [
            'id' => $id,
            'dateStr' => $input['dateStr'],
            'storyId' => $input['storyId'],
            'authorName' => $input['authorName'],
            'storyLink' => $input['storyLink'],
            'shoutoutCode' => $input['shoutoutCode'],
            'email' => $input['email'],
            'status' => 'pending',
            'createdAt' => time() * 1000
        ];

        // Send admin notification (best effort)
        try {
            sendNewBookingNotification($booking);
        } catch (Exception $e) {
            $logFile = __DIR__ . '/email-log.txt';
            $logEntry = date('c') . " | type=new_booking_error | message=" . json_encode($e->getMessage()) . "\n";
            @file_put_contents($logFile, $logEntry, FILE_APPEND);
        }
        
        json_response($booking);
        
    } catch (Throwable $e) {
        json_error('Failed to create booking.', 500, ['detail' => $e->getMessage()]);
    }
}

// PUT - Update booking status (admin only)
if ($method === 'PUT') {
    require_auth();
    
    try {
        $pdo = db();
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id']) || !isset($input['status'])) {
            json_error('Missing required fields', 400);
        }
        
        $validStatuses = ['pending', 'approved', 'rejected'];
        if (!in_array($input['status'], $validStatuses)) {
            json_error('Invalid status', 400);
        }
        
        // Get the booking details first
        $stmt = $pdo->prepare("SELECT * FROM shoutout_bookings WHERE id = ?");
        $stmt->execute([$input['id']]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            json_error('Booking not found', 404);
        }
        
        // Update the status
        $stmt = $pdo->prepare("UPDATE shoutout_bookings SET status = ? WHERE id = ?");
        $stmt->execute([$input['status'], $input['id']]);
        
        // If approved, send email
        $emailResult = null;
        
        if ($input['status'] === 'approved') {
            // Get story details
            $stmt = $pdo->prepare("SELECT * FROM shoutout_stories WHERE id = ?");
            $stmt->execute([$booking['story_id']]);
            $story = $stmt->fetch();
            
            if ($story) {
                // Get shoutout codes for this story (or global ones)
                $stmt = $pdo->prepare("
                    SELECT * FROM shoutout_admin_shoutouts 
                    WHERE story_id = ? OR story_id IS NULL OR story_id = ''
                ");
                $stmt->execute([$booking['story_id']]);
                $shoutouts = $stmt->fetchAll();
                
                // Send the approval email
                try {
                    $emailResult = sendApprovalEmail($pdo, $booking, $story, $shoutouts);
                } catch (Exception $e) {
                    // Log but don't fail the request
                    error_log("Approval email failed: " . $e->getMessage());
                }
            }
        }
        
        json_response([
            'message' => 'Booking status updated successfully',
            'emailSent' => $emailResult
        ]);
        
    } catch (Throwable $e) {
        json_error('Failed to update booking.', 500, ['detail' => $e->getMessage()]);
    }
}

// DELETE - Delete booking (admin only)
if ($method === 'DELETE') {
    require_auth();
    
    try {
        $pdo = db();
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            json_error('Booking ID required', 400);
        }
        
        $stmt = $pdo->prepare("DELETE FROM shoutout_bookings WHERE id = ?");
        $stmt->execute([$id]);
        
        json_response(['message' => 'Booking deleted successfully']);
        
    } catch (Throwable $e) {
        json_error('Failed to delete booking.', 500, ['detail' => $e->getMessage()]);
    }
}

json_error('Method not allowed', 405);
