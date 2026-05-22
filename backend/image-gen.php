<?php
/**
 * Free Would - Image Generation Handler
 * Actions: generate, history, delete, download
 */

require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';
$input = getInput();

switch ($action) {
    case 'generate':
        handleImageGenerate($input);
        break;
    case 'history':
        handleImageHistory($input);
        break;
    case 'delete':
        handleImageDelete($input);
        break;
    case 'download':
        handleImageDownload($input);
        break;
    default:
        sendJSON(['error' => 'Invalid action'], 400);
}

function handleImageGenerate(array $input): void
{
    global $pdo;
    $user = authenticateUser();
    $prompt = trim($input['prompt'] ?? '');
    $provider = $input['provider'] ?? 'openai';

    if (empty($prompt)) {
        sendJSON(['error' => 'Prompt is required'], 400);
    }

    if ($user['credits'] < 1) {
        sendJSON(['error' => 'Insufficient credits. You need at least 1 credit.'], 402);
    }

    $stmt = $pdo->prepare("SELECT * FROM api_keys WHERE provider_name = ? AND type = 'image' AND is_active = 1 ORDER BY priority ASC LIMIT 1");
    $stmt->execute([$provider]);
    $apiKeyRow = $stmt->fetch();

    if (!$apiKeyRow) {
        sendJSON(['error' => "No active image API key for provider: $provider"], 404);
    }

    $apiKey = decryptKey($apiKeyRow['api_key']);
    $model = $input['model'] ?? $apiKeyRow['model'];
    $endpoint = $apiKeyRow['endpoint'];

    $options = [
        'model' => $model,
        'size' => $input['size'] ?? '1024x1024',
        'style' => $input['style'] ?? null,
        'quality' => $input['quality'] ?? 'standard',
        'negative_prompt' => $input['negative_prompt'] ?? null,
        'endpoint' => $endpoint,
    ];

    $providerFile = __DIR__ . '/providers/' . basename($provider) . '.php';
    if (!file_exists($providerFile)) {
        $providerFile = __DIR__ . '/providers/custom.php';
    }
    require_once $providerFile;

    $startTime = microtime(true);

    try {
        if (!function_exists('generateImage')) {
            sendJSON(['error' => 'Provider does not support image generation'], 400);
        }

        $result = generateImage($prompt, $options, $apiKey);
        $responseTime = (int)((microtime(true) - $startTime) * 1000);

        $stmt = $pdo->prepare("INSERT INTO image_generations (user_id, prompt, negative_prompt, image_url, provider, model, size, style, quality, credits_used) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([
            $user['id'], $prompt, $options['negative_prompt'],
            $result['image_url'] ?? '', $provider, $model,
            $options['size'], $options['style'], $options['quality']
        ]);

        deductCredits($user['id'], 1);
        logApiCall($user['id'], $provider, 'image', 'success', $responseTime);

        sendJSON([
            'success' => true,
            'image_url' => $result['image_url'] ?? '',
            'provider' => $provider,
            'model' => $model,
            'credits_remaining' => $user['credits'] - 1,
            'generation_id' => (int)$pdo->lastInsertId()
        ]);
    } catch (Exception $e) {
        $responseTime = (int)((microtime(true) - $startTime) * 1000);
        logApiCall($user['id'], $provider, 'image', 'failed', $responseTime);
        sendJSON(['error' => 'Image generation failed', 'message' => $e->getMessage()], 502);
    }
}

function handleImageHistory(array $input): void
{
    global $pdo;
    $user = authenticateUser();
    $page = max(1, (int)($input['page'] ?? 1));
    $limit = min(50, max(1, (int)($input['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $stmt = $pdo->prepare("SELECT id, prompt, negative_prompt, image_url, provider, model, size, style, quality, credits_used, created_at FROM image_generations WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute([$user['id'], $limit, $offset]);
    $images = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM image_generations WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $total = $stmt->fetch()['total'];

    sendJSON([
        'success' => true,
        'images' => $images,
        'pagination' => [
            'page' => $page, 'limit' => $limit,
            'total' => (int)$total, 'pages' => ceil($total / $limit)
        ]
    ]);
}

function handleImageDelete(array $input): void
{
    global $pdo;
    $user = authenticateUser();
    $id = (int)($input['id'] ?? 0);

    if (!$id) sendJSON(['error' => 'Image ID is required'], 400);

    $stmt = $pdo->prepare("DELETE FROM image_generations WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);

    if ($stmt->rowCount() === 0) sendJSON(['error' => 'Image not found or unauthorized'], 404);
    sendJSON(['success' => true, 'message' => 'Image deleted']);
}

function handleImageDownload(array $input): void
{
    $user = authenticateUser();
    $url = $input['url'] ?? '';

    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        sendJSON(['error' => 'Valid image URL is required'], 400);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $imageData = curl_exec($ch);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$imageData) sendJSON(['error' => 'Failed to download image'], 502);

    header('Content-Type: ' . ($contentType ?: 'image/png'));
    header('Content-Disposition: attachment; filename="freewould_image_' . time() . '.png"');
    header('Content-Length: ' . strlen($imageData));
    echo $imageData;
    exit;
}
