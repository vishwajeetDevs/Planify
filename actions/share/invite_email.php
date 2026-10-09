<?php
/**
 * Send board share invitations by email (instant join links).
 * POST /actions/share/invite_email.php
 */
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../src/MailHelper.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method'], 400);
}

validateCSRFToken();

$userId = (int) $_SESSION['user_id'];
$input = [];
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
} else {
    $input = $_POST;
}

$boardId = (int) ($input['board_id'] ?? 0);
$emailsRaw = $input['emails'] ?? '';
$roleOnJoin = $input['role_on_join'] ?? 'member';
$expiresIn = $input['expires_in'] ?? '1day';

if ($boardId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid board ID'], 400);
}

if (!in_array($roleOnJoin, ['admin', 'member'], true)) {
    jsonResponse(['success' => false, 'message' => 'Invalid role'], 400);
}

if (!userCanShareBoard($conn, $userId, $boardId)) {
    jsonResponse(['success' => false, 'message' => 'Only a Super Admin or Admin can share this board'], 403);
}

$parsed = parseShareInviteEmails($emailsRaw);
if (!$parsed['ok']) {
    jsonResponse(['success' => false, 'message' => $parsed['message']], 400);
}
$recipientEmails = $parsed['emails'];

$filtered = filterShareInviteRecipients($conn, $boardId, $recipientEmails);
if (!$filtered['ok']) {
    jsonResponse(['success' => false, 'message' => $filtered['message'], 'skipped' => $filtered['skipped'] ?? []], 400);
}
$recipientEmails = $filtered['emails'];
$skippedExisting = $filtered['skipped'] ?? [];

$expiry = computeShareLinkExpiresAt($expiresIn);
if (!$expiry['ok']) {
    jsonResponse(['success' => false, 'message' => $expiry['message']], 400);
}
$expiresAt = $expiry['expires_at'];

$stmt = $conn->prepare('SELECT b.name AS board_name, u.name AS inviter_name FROM boards b INNER JOIN users u ON u.id = ? WHERE b.id = ?');
$stmt->bind_param('ii', $userId, $boardId);
$stmt->execute();
$meta = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$meta) {
    jsonResponse(['success' => false, 'message' => 'Board not found'], 404);
}

$roleLabel = $roleOnJoin === 'admin' ? 'Admin' : 'Member';
$expiresLabel = $expiresAt
    ? date('F j, Y g:i A', strtotime($expiresAt))
    : 'Never';

$baseUrl = rtrim(BASE_URL, '/');
$sent = [];
$failed = [];

// Look up which recipients already have an account so the email can show
// the right call to action (Join board vs. Create account + Join board).
$existingAccounts = [];
if ($recipientEmails !== []) {
    $placeholders = implode(',', array_fill(0, count($recipientEmails), '?'));
    $types = str_repeat('s', count($recipientEmails));
    $lookup = $conn->prepare("SELECT LOWER(TRIM(email)) AS email, name FROM users WHERE LOWER(TRIM(email)) IN ($placeholders)");
    $lookup->bind_param($types, ...$recipientEmails);
    $lookup->execute();
    $lookupResult = $lookup->get_result();
    while ($row = $lookupResult->fetch_assoc()) {
        $existingAccounts[$row['email']] = (string) ($row['name'] ?? '');
    }
    $lookup->close();
}

// Phase 1 (fast): create one single-use join link per recipient and record the
// activity. This is all database work and finishes in milliseconds.
$invites = [];

try {
    $conn->begin_transaction();

    $insert = $conn->prepare("
        INSERT INTO share_links (
            board_id, owner_id, token_hash, role_on_join, access_type,
            max_uses, expires_at, restrict_domain, single_use, notes
        ) VALUES (?, ?, ?, ?, 'join_on_click', 1, ?, '', 1, ?)
    ");

    foreach ($recipientEmails as $recipientEmail) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $notes = 'Email invite: ' . $recipientEmail;
        $insert->bind_param('iissss', $boardId, $userId, $tokenHash, $roleOnJoin, $expiresAt, $notes);

        if (!$insert->execute()) {
            $failed[] = $recipientEmail;
            continue;
        }

        $sharePath = 'share.php?token=' . $token;
        $hasAccount = array_key_exists($recipientEmail, $existingAccounts);

        $invites[] = [
            'email' => $recipientEmail,
            'share_url' => $baseUrl . '/' . $sharePath,
            'create_account_url' => $hasAccount
                ? ''
                : $baseUrl . '/register.php?redirect=' . urlencode($sharePath) . '&email=' . urlencode($recipientEmail),
            'name' => $hasAccount ? $existingAccounts[$recipientEmail] : '',
        ];
    }

    $insert->close();

    if ($invites !== []) {
        $recipientList = implode(', ', array_column($invites, 'email'));
        $description = count($invites) === 1
            ? "Invited {$recipientList} to the board by email as {$roleLabel}"
            : 'Invited ' . count($invites) . " people to the board by email as {$roleLabel}: {$recipientList}";
        logActivity($conn, $boardId, $userId, 'share_link_created', $description);
    }

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    secureErrorResponse($e, 'Share email invite');
}

if ($invites === []) {
    jsonResponse(['success' => false, 'message' => 'Could not create invitation links. Please try again.', 'failed' => $failed], 500);
}

// Phase 2: answer the browser right away, then deliver the emails over a single
// SMTP connection after the response has been flushed.
$queued = array_column($invites, 'email');
$message = count($queued) === 1
    ? 'Invitation sent to ' . $queued[0]
    : 'Invitations sent to ' . count($queued) . ' people';

jsonResponseAndContinue([
    'success' => true,
    'message' => $message,
    'sent' => $queued,
    'failed' => $failed,
    'skipped' => $skippedExisting,
]);

$delivery = MailHelper::sendBoardShareInviteEmails(
    $invites,
    $meta['board_name'],
    $meta['inviter_name'],
    $roleLabel,
    $expiresLabel
);

if (!empty($delivery['failed'])) {
    error_log('Share invite delivery failed for board ' . $boardId . ': ' . implode(', ', $delivery['failed']));
}

exit;
