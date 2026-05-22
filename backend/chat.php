<?php
/**
 * Free Would - Chat Handler
 * Actions: send, history, sessions, rename, delete, export
 */

require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';
$input = getInput();

switch ($action) {
    case 'send': handleChatSend($input); break;
    case 'history': handleChatHistory($input); break;
    case 'sessions': handleChatSessions(); break;
    case 'rename': handleChatRename($input); break;
    case 'delete': handleChatDelete($input); break;
    case 'export': handleChatExport($input); break;
    default: sendJSON(['error' => 'Invalid action'], 400);
}

function handleChatSend(array $input): void
{
    global $pdo;
    $user = authenticateUser();

    $message = trim($input['message'] ?? '');
    $sessionId = $input['session_id'] ?? uniqid('chat_');
    $provider = $input['provider'] ?? 'openai';
    $stream = $input['stream'] ?? false;

    if (empty($message)) sendJSON(['error' => 'Message is required'], 400);
    if ($user['credits'] < 1) sendJSON(['error' => 'Insufficient credits'], 402);

    // Load API key
    $stmt = $pdo->prepare("SELECT * FROM api_keys WHERE provider_name = ? AND type = 'chat' AND is_active = 1 ORDER BY priority ASC LIMIT 1");
    $stmt->execute([$provider]);
    $apiKeyRow = $stmt->fetch();
    if (!$apiKeyRow) sendJSON(['error' => "No active chat API key for provider: $provider"], 404);

    $apiKey = decryptKey($apiKeyRow['api_key']);
    $model = $input['model'] ?? $apiKeyRow['model'];
    $endpoint = $apiKeyRow['endpoint'];

    // Build messages array from history
    $messages = [];
    $systemPrompt = $input['system_prompt'] ?? 'You are a helpful AI assistant.';

    if ($systemPrompt) {
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];
    }

    // Load recent history
    $stmt = $pdo->prepare("SELECT role, message FROM chat_histories WHERE user_id = ? AND session_id = ? ORDER BY created_at ASC LIMIT 50");
    $stmt->execute([$user['id'], $sessionId]);
    $history = $stmt->fetchAll();
    foreach ($history as $h) {
        $messages[] = ['role' => $h['role'], 'content' => $h['message']];
    }

    // Add current message
    $messages[] = ['role' => 'user', 'content' => $message];

    // Save user message
    $stmt = $pdo->prepare("INSERT INTO chat_histories (user_id, session_id, session_name, role, message, provider) VALUES (?, ?, ?, 'user', ?, ?)");
    $sessionName = $input['session_name'] ?? 'New Chat';
    $stmt->execute([$user['id'], $sessionId, $sessionName, $message, $provider]);

    // Load provider
    $providerFile = __DIR__ . '/providers/' . basename($provider) . '.php';
    if (!file_exists($providerFile)) $providerFile = __DIR__ . '/providers/custom.php';
    require_once $providerFile;

    $options = [
        'model' => $model,
        'temperature' => $input['temperature'] ?? 0.7,
        'max_tokens' => $input['max_tokens'] ?? 2048,
        'stream' => $stream,
        'endpoint' => $endpoint,
    ];

    $startTime = microtime(true);

    try {
        if (!function_exists('chatCompletion')) sendJSON(['error' => 'Provider does not support chat'], 400);

        if ($stream) {
            // SSE Streaming
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            $fullResponse = '';
            $result = chatCompletion($messages, $options, $apiKey);

            if (isset($result['stream_callback'])) {
                // Provider handles streaming directly
                call_user_func($result['stream_callback'], function ($chunk) use (&$fullResponse, $pdo, $user, $sessionId, $provider, $model) {
                    $fullResponse .= $chunk;
                    echo "data: " . json_encode(['content' => $chunk]) . "\n\n";
                    if (ob_get_level()) ob_flush();
                    flush();
                });
            } else {
                $fullResponse = $result['content'] ?? '';
                // Simulate streaming with chunks
                $chunks = str_split($fullResponse, 10);
                foreach ($chunks as $chunk) {
                    echo "data: " . json_encode(['content' => $chunk]) . "\n\n";
                    if (ob_get_level()) ob_flush();
                    flush();
                    usleep(20000);
                }
            }

            echo "data: " . json_encode(['done' => true]) . "\n\n";
            flush();

            // Save assistant message
            $stmt = $pdo->prepare("INSERT INTO chat_histories (user_id, session_id, role, message, model, provider, tokens_used) VALUES (?, ?, 'assistant', ?, ?, ?, ?)");
            $tokensUsed = (int)ceil(str_word_count($fullResponse) * 1.3);
            $stmt->execute([$user['id'], $sessionId, $fullResponse, $model, $provider, $tokensUsed]);

            $creditsUsed = max(1, (int)ceil($tokensUsed / 100));
            deductCredits($user['id'], $creditsUsed);
        } else {
            // Non-streaming
            $result = chatCompletion($messages, $options, $apiKey);
            $responseTime = (int)((microtime(true) - $startTime) * 1000);

            $assistantMessage = $result['content'] ?? '';
            $tokensUsed = (int)($result['tokens_used'] ?? ceil(str_word_count($assistantMessage) * 1.3));

            // Save assistant message
            $stmt = $pdo->prepare("INSERT INTO chat_histories (user_id, session_id, role, message, model, provider, tokens_used) VALUES (?, ?, 'assistant', ?, ?, ?, ?)");
            $stmt->execute([$user['id'], $sessionId, $assistantMessage, $model, $provider, $tokensUsed]);

            $creditsUsed = max(1, (int)ceil($tokensUsed / 100));
            deductCredits($user['id'], $creditsUsed);
            logApiCall($user['id'], $provider, 'chat', 'success', $responseTime);

            sendJSON([
                'success' => true,
                'message' => $assistantMessage,
                'session_id' => $sessionId,
                'model' => $model,
                'provider' => $provider,
                'tokens_used' => $tokensUsed,
                'credits_used' => $creditsUsed,
                'credits_remaining' => $user['credits'] - $creditsUsed
            ]);
        }
    } catch (Exception $e) {
        $responseTime = (int)((microtime(true) - $startTime) * 1000);
        logApiCall($user['id'], $provider, 'chat', 'failed', $responseTime);
        sendJSON(['error' => 'Chat request failed', 'message' => $e->getMessage()], 502);
    }
}

function handleChatHistory(array $input): void
{
    global $pdo;
    $user = authenticateUser();
    $sessionId = $input['session_id'] ?? '';
    if (empty($sessionId)) sendJSON(['error' => 'Session ID is required'], 400);

    $stmt = $pdo->prepare("SELECT id, role, message, model, provider, tokens_used, created_at FROM chat_histories WHERE user_id = ? AND session_id = ? ORDER BY created_at ASC");
    $stmt->execute([$user['id'], $sessionId]);
    $messages = $stmt->fetchAll();

    sendJSON(['success' => true, 'messages' => $messages, 'session_id' => $sessionId]);
}

function handleChatSessions(): void
{
    global $pdo;
    $user = authenticateUser();

    $stmt = $pdo->prepare("SELECT session_id, session_name, MAX(created_at) as last_message, COUNT(*) as message_count FROM chat_histories WHERE user_id = ? GROUP BY session_id, session_name ORDER BY last_message DESC");
    $stmt->execute([$user['id']]);
    $sessions = $stmt->fetchAll();

    sendJSON(['success' => true, 'sessions' => $sessions]);
}

function handleChatRename(array $input): void
{
    global $pdo;
    $user = authenticateUser();
    $sessionId = $input['session_id'] ?? '';
    $newName = trim($input['name'] ?? '');

    if (empty($sessionId) || empty($newName)) sendJSON(['error' => 'Session ID and name are required'], 400);

    $stmt = $pdo->prepare("UPDATE chat_histories SET session_name = ? WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$newName, $user['id'], $sessionId]);

    sendJSON(['success' => true, 'message' => 'Session renamed']);
}

function handleChatDelete(array $input): void
{
    global $pdo;
    $user = authenticateUser();
    $sessionId = $input['session_id'] ?? '';
    if (empty($sessionId)) sendJSON(['error' => 'Session ID is required'], 400);

    $stmt = $pdo->prepare("DELETE FROM chat_histories WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$user['id'], $sessionId]);

    sendJSON(['success' => true, 'message' => 'Chat session deleted']);
}

function handleChatExport(array $input): void
{
    global $pdo;
    $user = authenticateUser();
    $sessionId = $input['session_id'] ?? '';
    $format = $input['format'] ?? 'json';

    if (empty($sessionId)) sendJSON(['error' => 'Session ID is required'], 400);

    $stmt = $pdo->prepare("SELECT role, message, created_at FROM chat_histories WHERE user_id = ? AND session_id = ? ORDER BY created_at ASC");
    $stmt->execute([$user['id'], $sessionId]);
    $messages = $stmt->fetchAll();

    if ($format === 'text') {
        $text = "Free Would - Chat Export\nSession: $sessionId\n" . str_repeat('=', 50) . "\n\n";
        foreach ($messages as $m) {
            $role = ucfirst($m['role']);
            $text .= "[$role] ({$m['created_at']})\n{$m['message']}\n\n";
        }
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="chat_export_' . $sessionId . '.txt"');
        echo $text;
        exit;
    }

    sendJSON(['success' => true, 'messages' => $messages, 'session_id' => $sessionId]);
}
