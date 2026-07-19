<?php
session_start();
include 'db.php';
header('Content-Type: application/json');

// Must be logged in
if (!isset($_SESSION['parent_logged_in']) || !$_SESSION['parent_logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$data    = json_decode(file_get_contents('php://input'), true);
$token   = trim($data['token'] ?? '');
$userId  = (int) $_SESSION['parent_id'];

if ($token === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No token provided']);
    exit;
}

// Insert or update the token for this user
$stmt = $conn->prepare("
    INSERT INTO fcm_tokens (user_id, token, created_at)
    VALUES (?, ?, NOW())
    ON DUPLICATE KEY UPDATE token = ?, created_at = NOW()
");
$stmt->bind_param('iss', $userId, $token, $token);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}