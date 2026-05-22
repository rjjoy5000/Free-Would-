<?php
/**
 * Free Would - Video Generation Handler
 * Actions: generate, status, history, delete
 */

require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';
$input = getInput();

switch ($action) {
    case 'generate': handleVideoGenerate($input); break;
    case 'status': handleVideoStatus($input); break;
    case 'history': handleVideoHistory($input); break;
    case 'delete': handleVideoDelete($input); break;
    default: sendJSON(['error' => 'Invalid action'], 400);
}

function handleVideoGenerate(array $input): void
{
    global $pdo;
    $user = authenticateUser();
    $prompt = trim($input['prompt'] ?? '');
    $provider = $input['provider'] ?? 'runway';

    if (empty($prompt)) sendJSON(['error' => 'Prompt is required'], 400);
    if ($user['credits'] < 10) sendJSON(['error' => 'Insufficient credits. You need at least 10 credits.'], 402);

    $stmt = $pdo->prepare("SELECT * FROM api_keys WHERE provider_name = ? AND type = 'video' AND is_active = 1 ORDER BY priority ASC LIMIT 1");
    $stmt->execute([$provider]);
    $apiKeyRow = $stmt->fetch();
    if (!$apiKeyRow) sendJSON(['error' => "No active video API key for provider: $provider"], 404);

    $apiKey = decryptKey($apiKeyRow['api_key']);
    $model = $input['model'] ?? $apiKeyRow['model'];
    $endpoint = $apiKeyRow['endpoint'];

    $options = [
        'model' => $model,
        'duration' => $input['duration'] ?? 5,
        'resolution' => $input['resolution'] ?? '720p',
        'fps' => $input['fps'] ?? 24,
        'style' => $input['style'] ?? null,
        'endpoint' => $endpoint,
    ];

    $providerFile = __DIR__ . '/providers/' . basename($provider) . '.php';
    if (!file_exists($providerFile)) $providerFile = __DIR__ . '/providers/custom.php';
    require_once $providerFile;

    $startTime = microtime(true);

    try {
        if (!function_exists('generateVideo')) sendJSON(['error' => 'Provider does not support video generation'], 400);

        $result = generateVideo($prompt, $options, $apiKey);
        $responseTime = (int)((microtime(true) - $startTime) * 1000);

        $stmt = $pdo->prepare("INSERT INTO video_generations (user_id, prompt, video_url, provider, duration, resolution, fps, style, status, credits_used) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 10)");
        $stmt->execute([
            $user['id'], $prompt, $result['video_url'] ?? null,
            $provider, $options['duration'], $options['resolution'],
            $options['fps'], $options['style'], $result['status'] ?? 'processing'
        ]);

        $videoId = (int)$pdo->lastInsertId();
        deductCredits($user['id'], 10);
        logApiCall($user['id'], $provider, 'video', 'success', $responseTime);

        sendJSON([
            'success' => true,
            'video_id' => $videoId,
            'video_url' => $result['video_url'] ?? null,
            'status' => $result['status'] ?? 'processing',
            'task_id' => $result['task_id'] ?? null,
            'provider' => $provider,
            'credits_remaining' => $user['credits'] - 10
        ]);
    } catch (Exception $e) {
        $responseTime = (int)((microtime(true) - $startTime) * 1000);
        logApiCall($user['id'], $provider, 'video', 'failed', $responseTime);
        sendJSON(['error' => 'Video generation failed', 'message' => $e->getMessage()], 502);
    }
}

function handleVideoStatus(array $input): void
{
    global $pdo;
    $user = authenticateUser();
    $videoId = (int)($input['video_id'] ?? 0);
    if (!$videoId) sendJSON(['error' => 'Video ID is required'], 400);

    $stmt = $pdo->prepare("SELECT * FROM video_generations WHERE id = ? AND user_id = ?");
    $stmt->execute([$videoId, $user['id']]);
    $video = $stmt->fetch();
    if (!$video) sendJSON(['error' => 'Video not found'], 404);

    if ($video['status'] === 'processing' && !empty($video['provider'])) {
        // Could poll provider for status update
    }

    sendJSON(['success' => true, 'video' => $video]);
}

function handleVideoHistory(array $input): void
{
    global $pdo;
    $user = authenticateUser();
    $page = max(1, (int)($input['page'] ?? 1));
    $limit = min(50, max(1, (int)($input['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $stmt = $pdo->prepare("SELECT id, prompt, video_url, provider, duration, resolution, fps, style, status, credits_used, created_at FROM video_generations WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute([$user['id'], $limit, $offset]);
    $videos = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM video_generations WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $total = $stmt->fetch()['total'];

    sendJSON([
        'success' => true, 'videos' => $videos,
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => (int)$total, 'pages' => ceil($total / $limit)]
    ]);
}

function handleVideoDelete(array $input): void
{
    global $pdo;
    $user = authenticateUser();
    $id = (int)($input['id'] ?? 0);
    if (!$id) sendJSON(['error' => 'Video ID is required'], 400);

    $stmt = $pdo->prepare("DELETE FROM video_generations WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
    if ($stmt->rowCount() === 0) sendJSON(['error' => 'Video not found or unauthorized'], 404);
    sendJSON(['success' => true, 'message' => 'Video deleted']);
}
