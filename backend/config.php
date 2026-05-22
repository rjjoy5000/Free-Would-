<?php
/**
 * Free Would - Main Configuration
 * PHP 8.x Backend Configuration & Helper Functions
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'freewould_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application Settings
define('JWT_SECRET', 'fr33w0uld_jwt_s3cret_k3y_2024_ch4ng3_th1s');
define('JWT_EXPIRY', 604800); // 7 days in seconds
define('SITE_URL', 'http://localhost/Free%20Would');
define('SITE_NAME', 'Free Would');
define('ENCRYPTION_KEY', 'Fr33W0uld_AES256_Encrypt_Key_32b!');
define('ENCRYPTION_METHOD', 'AES-256-CBC');

// Session Start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('Content-Type: application/json; charset=UTF-8');

// Handle Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database Connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    sendJSON(['error' => 'Database connection failed', 'message' => $e->getMessage()], 500);
}

/**
 * Send JSON Response
 */
function sendJSON(mixed $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Generate JWT Token (HS256)
 */
function generateToken(int $userId): string
{
    $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    $payload = base64_encode(json_encode([
        'user_id' => $userId,
        'iat' => time(),
        'exp' => time() + JWT_EXPIRY
    ]));
    $signature = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    return "$header.$payload.$signature";
}

/**
 * Verify JWT Token
 */
function verifyToken(string $token): ?object
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;

    [$header, $payload, $signature] = $parts;
    $expectedSig = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));

    if (!hash_equals($expectedSig, $signature)) return null;

    $data = json_decode(base64_decode($payload));
    if (!$data || $data->exp < time()) return null;

    return $data;
}

/**
 * Authenticate User from Bearer Token
 */
function authenticateUser(): array
{
    global $pdo;

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        sendJSON(['error' => 'Authorization token required'], 401);
    }

    $tokenData = verifyToken($matches[1]);
    if (!$tokenData) {
        sendJSON(['error' => 'Invalid or expired token'], 401);
    }

    $stmt = $pdo->prepare("SELECT id, name, email, role, credits, avatar, plan, status, email_verified, created_at FROM users WHERE id = ?");
    $stmt->execute([$tokenData->user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        sendJSON(['error' => 'User not found'], 404);
    }

    if ($user['status'] === 'banned') {
        sendJSON(['error' => 'Account has been banned'], 403);
    }

    return $user;
}

/**
 * Check if Current User is Admin
 */
function checkAdmin(): array
{
    $user = authenticateUser();
    if ($user['role'] !== 'admin') {
        sendJSON(['error' => 'Admin access required'], 403);
    }
    return $user;
}

/**
 * Deduct Credits from User
 */
function deductCredits(int $userId, int $amount): bool
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || $user['credits'] < $amount) {
        return false;
    }

    $stmt = $pdo->prepare("UPDATE users SET credits = credits - ? WHERE id = ?");
    return $stmt->execute([$amount, $userId]);
}

/**
 * Encrypt API Key (AES-256-CBC)
 */
function encryptKey(string $key): string
{
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($key, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
    return base64_encode($iv . '::' . $encrypted);
}

/**
 * Decrypt API Key
 */
function decryptKey(string $encryptedKey): string
{
    $data = base64_decode($encryptedKey);
    [$iv, $encrypted] = explode('::', $data, 2);
    return openssl_decrypt($encrypted, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
}

/**
 * Get Site Setting Value
 */
function getSetting(string $key): ?string
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : null;
}

/**
 * Log API Call
 */
function logApiCall(?int $userId, string $provider, string $type, string $status, int $responseTime): void
{
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO api_logs (user_id, provider, type, status, response_time) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $provider, $type, $status, $responseTime]);
}

/**
 * Get Input Data (JSON or POST)
 */
function getInput(): array
{
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    return is_array($data) ? $data : $_POST;
}

/**
 * Sanitize String Input
 */
function sanitize(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate Email
 */
function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Rate Limiting Check (5 attempts per 15 minutes)
 */
function checkRateLimit(string $action): bool
{
    $key = 'rate_' . $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $now = time();

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }

    // Clean old entries
    $_SESSION[$key] = array_filter($_SESSION[$key], fn($t) => $now - $t < 900);

    if (count($_SESSION[$key]) >= 5) {
        return false;
    }

    $_SESSION[$key][] = $now;
    return true;
}
