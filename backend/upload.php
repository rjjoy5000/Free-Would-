<?php
/**
 * Free Would - File Upload Handler
 * Handles avatar uploads
 */

require_once __DIR__ . '/config.php';

$user = authenticateUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['error' => 'POST method required'], 405);
}

if (!isset($_FILES['file'])) {
    sendJSON(['error' => 'No file uploaded'], 400);
}

$file = $_FILES['file'];

// Validate
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$maxSize = 5 * 1024 * 1024; // 5MB

if (!in_array($file['type'], $allowedTypes)) {
    sendJSON(['error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP'], 400);
}

if ($file['size'] > $maxSize) {
    sendJSON(['error' => 'File too large. Maximum size: 5MB'], 400);
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    sendJSON(['error' => 'Upload error: ' . $file['error']], 500);
}

// Create upload directory if not exists
$uploadDir = __DIR__ . '/../assets/images/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'avatar_' . $user['id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$filepath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    sendJSON(['error' => 'Failed to save file'], 500);
}

// Update user avatar
$fileUrl = SITE_URL . '/assets/images/uploads/' . $filename;
$stmt = $GLOBALS['pdo']->prepare("UPDATE users SET avatar = ? WHERE id = ?");
$stmt->execute([$fileUrl, $user['id']]);

sendJSON([
    'success' => true,
    'message' => 'File uploaded successfully',
    'url' => $fileUrl,
    'filename' => $filename
]);
