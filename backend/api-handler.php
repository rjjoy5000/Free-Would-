<?php
/**
 * Free Would - Central API Handler
 * Routes API calls to correct provider, manages keys, logging, and retries
 */

require_once __DIR__ . '/config.php';

$input = getInput();
$user = authenticateUser();

$type = $input['type'] ?? '';       // image, video, chat
$provider = $input['provider'] ?? ''; // openai, stability, anthropic, etc.
$action = $input['action'] ?? 'generate';

if (empty($type) || empty($provider)) {
    sendJSON(['error' => 'Type and provider are required'], 400);
}

// Load active API key for provider
$stmt = $pdo->prepare("SELECT * FROM api_keys WHERE provider_name = ? AND type = ? AND is_active = 1 ORDER BY priority ASC LIMIT 1");
$stmt->execute([$provider, $type]);
$apiKeyRow = $stmt->fetch();

if (!$apiKeyRow) {
    sendJSON(['error' => "No active API key found for provider: $provider"], 404);
}

$apiKey = decryptKey($apiKeyRow['api_key']);
$apiSecret = $apiKeyRow['api_secret'] ? decryptKey($apiKeyRow['api_secret']) : null;
$model = $apiKeyRow['model'];
$endpoint = $apiKeyRow['endpoint'];

// Provider file mapping
$providerFiles = [
    'openai' => 'providers/openai.php',
    'stability' => 'providers/stability.php',
    'anthropic' => 'providers/anthropic.php',
    'gemini' => 'providers/gemini.php',
    'groq' => 'providers/groq.php',
    'replicate' => 'providers/replicate.php',
    'runway' => 'providers/runway.php',
    'custom' => 'providers/custom.php',
];

if (!isset($providerFiles[$provider])) {
    sendJSON(['error' => 'Unsupported provider'], 400);
}

$providerFile = __DIR__ . '/' . $providerFiles[$provider];
if (!file_exists($providerFile)) {
    sendJSON(['error' => 'Provider file not found'], 500);
}

require_once $providerFile;

// Build options
$options = [
    'model' => $input['model'] ?? $model,
    'size' => $input['size'] ?? '1024x1024',
    'style' => $input['style'] ?? null,
    'quality' => $input['quality'] ?? 'standard',
    'duration' => $input['duration'] ?? 5,
    'resolution' => $input['resolution'] ?? '720p',
    'fps' => $input['fps'] ?? 24,
    'temperature' => $input['temperature'] ?? 0.7,
    'max_tokens' => $input['max_tokens'] ?? 2048,
    'stream' => $input['stream'] ?? false,
    'system_prompt' => $input['system_prompt'] ?? null,
    'endpoint' => $endpoint,
    'api_secret' => $apiSecret,
    'negative_prompt' => $input['negative_prompt'] ?? null,
];

$maxRetries = 2;
$lastError = null;

for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
    $startTime = microtime(true);

    try {
        $result = null;

        switch ($type) {
            case 'image':
                if (!function_exists('generateImage')) {
                    sendJSON(['error' => 'Provider does not support image generation'], 400);
                }
                $result = generateImage($input['prompt'] ?? '', $options, $apiKey);
                break;

            case 'video':
                if (!function_exists('generateVideo')) {
                    sendJSON(['error' => 'Provider does not support video generation'], 400);
                }
                $result = generateVideo($input['prompt'] ?? '', $options, $apiKey);
                break;

            case 'chat':
                if (!function_exists('chatCompletion')) {
                    sendJSON(['error' => 'Provider does not support chat'], 400);
                }
                $messages = $input['messages'] ?? [];
                $result = chatCompletion($messages, $options, $apiKey);
                break;

            default:
                sendJSON(['error' => 'Invalid type'], 400);
        }

        $responseTime = (int)((microtime(true) - $startTime) * 1000);
        logApiCall($user['id'], $provider, $type, 'success', $responseTime);

        sendJSON([
            'success' => true,
            'provider' => $provider,
            'type' => $type,
            'data' => $result
        ]);
        return;

    } catch (Exception $e) {
        $lastError = $e->getMessage();
        $responseTime = (int)((microtime(true) - $startTime) * 1000);
        logApiCall($user['id'], $provider, $type, 'failed', $responseTime);

        if ($attempt < $maxRetries) {
            usleep(500000); // 500ms delay before retry
            continue;
        }
    }
}

sendJSON([
    'error' => 'API request failed after retries',
    'message' => $lastError,
    'provider' => $provider
], 502);
