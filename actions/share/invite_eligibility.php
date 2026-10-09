<?php
/**
 * Emails already on the board or workspace (for share-by-email validation).
 * GET /actions/share/invite_eligibility.php?board_id=
 */
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$userId = (int) $_SESSION['user_id'];
$boardId = (int) ($_GET['board_id'] ?? 0);

if ($boardId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid board ID'], 400);
}

if (!userCanShareBoard($conn, $userId, $boardId)) {
    jsonResponse(['success' => false, 'message' => 'Forbidden'], 403);
}

$existing = getShareInviteExistingEmails($conn, $boardId);

jsonResponse([
    'success' => true,
    'board_emails' => $existing['board'],
    'workspace_emails' => $existing['workspace'],
]);
