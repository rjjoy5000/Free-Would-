<?php
/**
 * Free Would - Admin Handler
 * All admin management functions
 */

require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';
$input = getInput();

switch ($action) {
    case 'get-stats': handleGetStats(); break;
    case 'get-users': handleGetUsers($input); break;
    case 'update-user': handleUpdateUser($input); break;
    case 'delete-user': handleDeleteUser($input); break;
    case 'ban-user': handleBanUser($input, true); break;
    case 'unban-user': handleBanUser($input, false); break;
    case 'add-credits': handleAddCredits($input); break;
    case 'change-plan': handleChangePlan($input); break;
    case 'get-api-keys': handleGetApiKeys(); break;
    case 'save-api-key': handleSaveApiKey($input); break;
    case 'delete-api-key': handleDeleteApiKey($input); break;
    case 'test-api': handleTestApi($input); break;
    case 'get-analytics': handleGetAnalytics($input); break;
    case 'get-plans': handleGetPlans(); break;
    case 'save-plan': handleSavePlan($input); break;
    case 'delete-plan': handleDeletePlan($input); break;
    case 'get-settings': handleGetSettings(); break;
    case 'save-settings': handleSaveSettings($input); break;
    case 'export-users': handleExportUsers(); break;
    default: sendJSON(['error' => 'Invalid action'], 400);
}

function handleGetStats(): void
{
    global $pdo;
    checkAdmin();

    $stats = [];

    $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['total_revenue'] = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'credit' AND status = 'completed'")->fetchColumn();
    $stats['api_calls_today'] = $pdo->query("SELECT COUNT(*) FROM api_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $stats['active_subscriptions'] = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'active'")->fetchColumn();
    $stats['total_images'] = $pdo->query("SELECT COUNT(*) FROM image_generations")->fetchColumn();
    $stats['total_videos'] = $pdo->query("SELECT COUNT(*) FROM video_generations")->fetchColumn();
    $stats['total_chats'] = $pdo->query("SELECT COUNT(DISTINCT session_id) FROM chat_histories")->fetchColumn();
    $stats['active_users_today'] = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM api_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();

    sendJSON(['success' => true, 'stats' => $stats]);
}

function handleGetUsers(array $input): void
{
    global $pdo;
    checkAdmin();

    $page = max(1, (int)($input['page'] ?? 1));
    $limit = min(100, max(1, (int)($input['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $search = $input['search'] ?? '';
    $role = $input['role'] ?? '';
    $status = $input['status'] ?? '';

    $where = "WHERE 1=1";
    $params = [];

    if ($search) {
        $where .= " AND (name LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($role) { $where .= " AND role = ?"; $params[] = $role; }
    if ($status) { $where .= " AND status = ?"; $params[] = $status; }

    $stmt = $pdo->prepare("SELECT id, name, email, role, credits, avatar, plan, status, email_verified, last_login, created_at FROM users $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    $countParams = array_slice($params, 0, -2);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
    $stmt->execute($countParams);
    $total = $stmt->fetchColumn();

    sendJSON(['success' => true, 'users' => $users, 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => (int)$total, 'pages' => ceil($total / $limit)]]);
}

function handleUpdateUser(array $input): void
{
    global $pdo;
    checkAdmin();

    $id = (int)($input['user_id'] ?? 0);
    if (!$id) sendJSON(['error' => 'User ID is required'], 400);

    $fields = [];
    $params = [];
    $allowed = ['name', 'email', 'role', 'credits', 'plan', 'status'];

    foreach ($allowed as $field) {
        if (isset($input[$field])) {
            $fields[] = "$field = ?";
            $params[] = $input[$field];
        }
    }

    if (empty($fields)) sendJSON(['error' => 'No fields to update'], 400);

    $params[] = $id;
    $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?");
    $stmt->execute($params);

    sendJSON(['success' => true, 'message' => 'User updated']);
}

function handleDeleteUser(array $input): void
{
    global $pdo;
    checkAdmin();

    $id = (int)($input['user_id'] ?? 0);
    if (!$id) sendJSON(['error' => 'User ID is required'], 400);

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) sendJSON(['error' => 'User not found'], 404);

    sendJSON(['success' => true, 'message' => 'User deleted']);
}

function handleBanUser(array $input, bool $ban): void
{
    global $pdo;
    checkAdmin();

    $id = (int)($input['user_id'] ?? 0);
    if (!$id) sendJSON(['error' => 'User ID is required'], 400);

    $status = $ban ? 'banned' : 'active';
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);

    sendJSON(['success' => true, 'message' => $ban ? 'User banned' : 'User unbanned']);
}

function handleAddCredits(array $input): void
{
    global $pdo;
    checkAdmin();

    $id = (int)($input['user_id'] ?? 0);
    $amount = (int)($input['amount'] ?? 0);

    if (!$id || $amount <= 0) sendJSON(['error' => 'Valid user ID and amount required'], 400);

    $stmt = $pdo->prepare("UPDATE users SET credits = credits + ? WHERE id = ?");
    $stmt->execute([$amount, $id]);

    // Log transaction
    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, description, status) VALUES (?, ?, 'credit', ?, 'completed')");
    $stmt->execute([$id, $amount, "Admin added $amount credits"]);

    sendJSON(['success' => true, 'message' => "$amount credits added"]);
}

function handleChangePlan(array $input): void
{
    global $pdo;
    checkAdmin();

    $userId = (int)($input['user_id'] ?? 0);
    $planId = (int)($input['plan_id'] ?? 0);

    if (!$userId || !$planId) sendJSON(['error' => 'User ID and Plan ID required'], 400);

    $stmt = $pdo->prepare("SELECT name FROM plans WHERE id = ?");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();
    if (!$plan) sendJSON(['error' => 'Plan not found'], 404);

    $stmt = $pdo->prepare("UPDATE users SET plan = ? WHERE id = ?");
    $stmt->execute([$plan['name'], $userId]);

    // Create/update subscription
    $stmt = $pdo->prepare("INSERT INTO subscriptions (user_id, plan_id, start_date, status) VALUES (?, ?, NOW(), 'active') ON DUPLICATE KEY UPDATE plan_id = VALUES(plan_id), start_date = NOW(), status = 'active'");
    $stmt->execute([$userId, $planId]);

    sendJSON(['success' => true, 'message' => 'Plan changed to ' . $plan['name']]);
}

function handleGetApiKeys(): void
{
    global $pdo;
    checkAdmin();

    $stmt = $pdo->query("SELECT id, provider_name, type, model, endpoint, priority, rate_limit, is_active, created_at, updated_at FROM api_keys ORDER BY provider_name, type");
    $keys = $stmt->fetchAll();

    // Mask keys for display
    foreach ($keys as &$key) {
        $key['key_preview'] = '****...****';
    }

    sendJSON(['success' => true, 'api_keys' => $keys]);
}

function handleSaveApiKey(array $input): void
{
    global $pdo;
    checkAdmin();

    $id = (int)($input['id'] ?? 0);
    $providerName = sanitize($input['provider_name'] ?? '');
    $type = $input['type'] ?? '';
    $apiKey = $input['api_key'] ?? '';
    $apiSecret = $input['api_secret'] ?? '';
    $model = $input['model'] ?? '';
    $endpoint = $input['endpoint'] ?? '';
    $priority = (int)($input['priority'] ?? 1);
    $rateLimit = (int)($input['rate_limit'] ?? 100);
    $isActive = (int)($input['is_active'] ?? 1);

    if (empty($providerName) || empty($type)) sendJSON(['error' => 'Provider name and type are required'], 400);

    if ($id) {
        // Update
        $fields = "provider_name = ?, type = ?, model = ?, endpoint = ?, priority = ?, rate_limit = ?, is_active = ?";
        $params = [$providerName, $type, $model, $endpoint, $priority, $rateLimit, $isActive];

        if ($apiKey) {
            $fields .= ", api_key = ?";
            $params[] = encryptKey($apiKey);
        }
        if ($apiSecret) {
            $fields .= ", api_secret = ?";
            $params[] = encryptKey($apiSecret);
        }

        $params[] = $id;
        $stmt = $pdo->prepare("UPDATE api_keys SET $fields WHERE id = ?");
        $stmt->execute($params);
        sendJSON(['success' => true, 'message' => 'API key updated']);
    } else {
        // Insert
        if (empty($apiKey)) sendJSON(['error' => 'API key is required for new entries'], 400);
        $stmt = $pdo->prepare("INSERT INTO api_keys (provider_name, type, api_key, api_secret, model, endpoint, priority, rate_limit, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$providerName, $type, encryptKey($apiKey), $apiSecret ? encryptKey($apiSecret) : null, $model, $endpoint, $priority, $rateLimit, $isActive]);
        sendJSON(['success' => true, 'message' => 'API key added', 'id' => (int)$pdo->lastInsertId()]);
    }
}

function handleDeleteApiKey(array $input): void
{
    global $pdo;
    checkAdmin();
    $id = (int)($input['id'] ?? 0);
    if (!$id) sendJSON(['error' => 'API key ID is required'], 400);

    $stmt = $pdo->prepare("DELETE FROM api_keys WHERE id = ?");
    $stmt->execute([$id]);
    sendJSON(['success' => true, 'message' => 'API key deleted']);
}

function handleTestApi(array $input): void
{
    global $pdo;
    checkAdmin();

    $id = (int)($input['id'] ?? 0);
    if (!$id) sendJSON(['error' => 'API key ID is required'], 400);

    $stmt = $pdo->prepare("SELECT * FROM api_keys WHERE id = ?");
    $stmt->execute([$id]);
    $key = $stmt->fetch();
    if (!$key) sendJSON(['error' => 'API key not found'], 404);

    $apiKey = decryptKey($key['api_key']);
    $endpoint = $key['endpoint'] ?? '';

    // Simple connectivity test
    $ch = curl_init($endpoint ?: 'https://api.openai.com/v1/models');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        sendJSON(['success' => false, 'message' => 'Connection failed: ' . $error]);
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        sendJSON(['success' => true, 'message' => 'API connection successful', 'status_code' => $httpCode]);
    }

    sendJSON(['success' => false, 'message' => 'API returned status: ' . $httpCode, 'status_code' => $httpCode]);
}

function handleGetAnalytics(array $input): void
{
    global $pdo;
    checkAdmin();

    $days = (int)($input['days'] ?? 30);

    $analytics = [];

    // API calls per day
    $stmt = $pdo->prepare("SELECT DATE(created_at) as date, COUNT(*) as calls, SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed FROM api_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY DATE(created_at) ORDER BY date");
    $stmt->execute([$days]);
    $analytics['daily_calls'] = $stmt->fetchAll();

    // Calls by provider
    $stmt = $pdo->prepare("SELECT provider, COUNT(*) as calls FROM api_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY provider");
    $stmt->execute([$days]);
    $analytics['by_provider'] = $stmt->fetchAll();

    // Calls by type
    $stmt = $pdo->prepare("SELECT type, COUNT(*) as calls FROM api_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY type");
    $stmt->execute([$days]);
    $analytics['by_type'] = $stmt->fetchAll();

    // Average response time
    $stmt = $pdo->prepare("SELECT AVG(response_time) as avg_ms FROM api_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$days]);
    $analytics['avg_response_time'] = (int)$stmt->fetchColumn();

    sendJSON(['success' => true, 'analytics' => $analytics, 'period_days' => $days]);
}

function handleGetPlans(): void
{
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM plans ORDER BY price ASC");
    sendJSON(['success' => true, 'plans' => $stmt->fetchAll()]);
}

function handleSavePlan(array $input): void
{
    global $pdo;
    checkAdmin();

    $id = (int)($input['id'] ?? 0);
    $name = sanitize($input['name'] ?? '');
    $price = (float)($input['price'] ?? 0);
    $credits = (int)($input['credits'] ?? 100);
    $duration = $input['duration'] ?? 'monthly';
    $features = json_encode($input['features'] ?? []);
    $imageLimit = (int)($input['image_limit'] ?? 10);
    $videoLimit = (int)($input['video_limit'] ?? 2);
    $chatLimit = (int)($input['chat_limit'] ?? 100);
    $isPopular = (int)($input['is_popular'] ?? 0);
    $status = $input['status'] ?? 'active';

    if (empty($name)) sendJSON(['error' => 'Plan name is required'], 400);

    if ($id) {
        $stmt = $pdo->prepare("UPDATE plans SET name = ?, price = ?, credits = ?, duration = ?, features = ?, image_limit = ?, video_limit = ?, chat_limit = ?, is_popular = ?, status = ? WHERE id = ?");
        $stmt->execute([$name, $price, $credits, $duration, $features, $imageLimit, $videoLimit, $chatLimit, $isPopular, $status, $id]);
        sendJSON(['success' => true, 'message' => 'Plan updated']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO plans (name, price, credits, duration, features, image_limit, video_limit, chat_limit, is_popular, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $price, $credits, $duration, $features, $imageLimit, $videoLimit, $chatLimit, $isPopular, $status]);
        sendJSON(['success' => true, 'message' => 'Plan created', 'id' => (int)$pdo->lastInsertId()]);
    }
}

function handleDeletePlan(array $input): void
{
    global $pdo;
    checkAdmin();
    $id = (int)($input['id'] ?? 0);
    if (!$id) sendJSON(['error' => 'Plan ID is required'], 400);

    $stmt = $pdo->prepare("DELETE FROM plans WHERE id = ?");
    $stmt->execute([$id]);
    sendJSON(['success' => true, 'message' => 'Plan deleted']);
}

function handleGetSettings(): void
{
    global $pdo;
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    sendJSON(['success' => true, 'settings' => $settings]);
}

function handleSaveSettings(array $input): void
{
    global $pdo;
    checkAdmin();

    foreach ($input as $key => $value) {
        if ($key === 'action') continue;
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$key, $value]);
    }

    sendJSON(['success' => true, 'message' => 'Settings saved']);
}

function handleExportUsers(): void
{
    global $pdo;
    checkAdmin();

    $stmt = $pdo->query("SELECT id, name, email, role, credits, plan, status, last_login, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="freewould_users_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'Email', 'Role', 'Credits', 'Plan', 'Status', 'Last Login', 'Created']);
    foreach ($users as $user) {
        fputcsv($output, $user);
    }
    fclose($output);
    exit;
}
