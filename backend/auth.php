<?php
/**
 * Free Would - Authentication Handler
 * Handles: login, register, logout, forgot-password, reset-password, get-user, check-email
 */

require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';
$input = getInput();

switch ($action) {
    case 'login':
        handleLogin($input);
        break;
    case 'register':
        handleRegister($input);
        break;
    case 'logout':
        handleLogout();
        break;
    case 'forgot-password':
        handleForgotPassword($input);
        break;
    case 'reset-password':
        handleResetPassword($input);
        break;
    case 'get-user':
        handleGetUser();
        break;
    case 'check-email':
        handleCheckEmail($input);
        break;
    default:
        sendJSON(['error' => 'Invalid action'], 400);
}

/**
 * Login Handler
 */
function handleLogin(array $input): void
{
    global $pdo;

    if (!checkRateLimit('login')) {
        sendJSON(['error' => 'Too many attempts. Please try again in 15 minutes.'], 429);
    }

    $email = sanitize($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($email) || empty($password)) {
        sendJSON(['error' => 'Email and password are required'], 400);
    }

    if (!isValidEmail($email)) {
        sendJSON(['error' => 'Invalid email format'], 400);
    }

    $stmt = $pdo->prepare("SELECT id, name, email, password, role, credits, avatar, plan, status, email_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        sendJSON(['error' => 'Invalid email or password'], 401);
    }

    if ($user['status'] === 'banned') {
        sendJSON(['error' => 'Your account has been banned. Contact support.'], 403);
    }

    // Update last_login
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);

    // Generate token
    $token = generateToken($user['id']);

    unset($user['password']);

    sendJSON([
        'success' => true,
        'message' => 'Login successful',
        'token' => $token,
        'user' => $user
    ]);
}

/**
 * Register Handler
 */
function handleRegister(array $input): void
{
    global $pdo;

    if (!checkRateLimit('register')) {
        sendJSON(['error' => 'Too many attempts. Please try again in 15 minutes.'], 429);
    }

    // Check if registration is allowed
    if (getSetting('allow_registration') === '0') {
        sendJSON(['error' => 'Registration is currently disabled'], 403);
    }

    $name = sanitize($input['name'] ?? '');
    $email = sanitize($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';

    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        sendJSON(['error' => 'Name, email and password are required'], 400);
    }

    if (strlen($name) < 2 || strlen($name) > 100) {
        sendJSON(['error' => 'Name must be between 2 and 100 characters'], 400);
    }

    if (!isValidEmail($email)) {
        sendJSON(['error' => 'Invalid email format'], 400);
    }

    if (strlen($password) < 6) {
        sendJSON(['error' => 'Password must be at least 6 characters'], 400);
    }

    if ($password !== $confirmPassword) {
        sendJSON(['error' => 'Passwords do not match'], 400);
    }

    // Check duplicate email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendJSON(['error' => 'Email already registered'], 409);
    }

    // Hash password & insert
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    $defaultCredits = (int)(getSetting('default_credits') ?: 100);

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, credits, plan) VALUES (?, ?, ?, ?, 'free')");
    $stmt->execute([$name, $email, $hashedPassword, $defaultCredits]);

    $userId = (int)$pdo->lastInsertId();
    $token = generateToken($userId);

    // Fetch created user
    $stmt = $pdo->prepare("SELECT id, name, email, role, credits, avatar, plan, status, email_verified, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    sendJSON([
        'success' => true,
        'message' => 'Registration successful',
        'token' => $token,
        'user' => $user
    ], 201);
}

/**
 * Logout Handler
 */
function handleLogout(): void
{
    session_destroy();
    sendJSON(['success' => true, 'message' => 'Logged out successfully']);
}

/**
 * Forgot Password Handler
 */
function handleForgotPassword(array $input): void
{
    global $pdo;

    if (!checkRateLimit('forgot')) {
        sendJSON(['error' => 'Too many attempts. Please try again in 15 minutes.'], 429);
    }

    $email = sanitize($input['email'] ?? '');

    if (empty($email) || !isValidEmail($email)) {
        sendJSON(['error' => 'Valid email is required'], 400);
    }

    // Always return success to prevent email enumeration
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
        $stmt->execute([$token, $expires, $user['id']]);

        // In production, send email with reset link
        // mail($email, 'Password Reset', "Reset link: " . SITE_URL . "/reset-password.html?token=$token");
    }

    sendJSON([
        'success' => true,
        'message' => 'If the email exists, a reset link has been sent'
    ]);
}

/**
 * Reset Password Handler
 */
function handleResetPassword(array $input): void
{
    global $pdo;

    $token = $input['token'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';

    if (empty($token) || empty($newPassword)) {
        sendJSON(['error' => 'Token and new password are required'], 400);
    }

    if (strlen($newPassword) < 6) {
        sendJSON(['error' => 'Password must be at least 6 characters'], 400);
    }

    if ($newPassword !== $confirmPassword) {
        sendJSON(['error' => 'Passwords do not match'], 400);
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        sendJSON(['error' => 'Invalid or expired reset token'], 400);
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);

    $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
    $stmt->execute([$hashedPassword, $user['id']]);

    sendJSON([
        'success' => true,
        'message' => 'Password reset successful. You can now login.'
    ]);
}

/**
 * Get Current User
 */
function handleGetUser(): void
{
    $user = authenticateUser();
    sendJSON(['success' => true, 'user' => $user]);
}

/**
 * Check Email Availability
 */
function handleCheckEmail(array $input): void
{
    global $pdo;

    $email = sanitize($input['email'] ?? '');

    if (empty($email) || !isValidEmail($email)) {
        sendJSON(['error' => 'Valid email is required'], 400);
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $exists = $stmt->fetch() !== false;

    sendJSON([
        'success' => true,
        'available' => !$exists
    ]);
}
