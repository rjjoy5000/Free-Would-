<?php
/**
 * Anthropic Provider - Claude
 */

function chatCompletion($messages, $options, $apiKey, $stream = false) {
    $model = $options['model'] ?? 'claude-3-sonnet-20240229';
    $maxTokens = $options['max_tokens'] ?? 2048;
    $temperature = $options['temperature'] ?? 0.7;

    $anthropicMessages = [];
    foreach ($messages as $msg) {
        if ($msg['role'] === 'system') continue;
        $anthropicMessages[] = [
            'role' => $msg['role'],
            'content' => $msg['content']
        ];
    }

    $payload = [
        'model' => $model,
        'max_tokens' => (int)$maxTokens,
        'temperature' => (float)$temperature,
        'messages' => $anthropicMessages
    ];

    if (!empty($options['system'])) {
        $payload['system'] = $options['system'];
    }

    $endpoint = $options['endpoint'] ?? 'https://api.anthropic.com/v1';

    $ch = curl_init($endpoint . '/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01'
        ],
        CURLOPT_RETURNTRANSFER => !$stream,
        CURLOPT_TIMEOUT => 120
    ]);

    if ($stream) {
        $fullContent = '';
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$fullContent) {
            $lines = explode("\n", $chunk);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!str_starts_with($line, 'data: ')) continue;
                $json = json_decode(substr($line, 6), true);
                if ($json['type'] === 'content_block_delta') {
                    $text = $json['delta']['text'] ?? '';
                    $fullContent .= $text;
                    echo "data: " . json_encode(['content' => $text]) . "\n\n";
                    ob_flush();
                    flush();
                }
            }
            return strlen($chunk);
        });

        curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }
        return ['success' => true, 'content' => $fullContent, 'tokens_used' => 0];
    }

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => 'cURL Error: ' . $error];
    }

    if ($httpCode >= 400) {
        $data = json_decode($body, true);
        return ['success' => false, 'error' => $data['error']['message'] ?? 'API Error (HTTP ' . $httpCode . ')'];
    }

    $data = json_decode($body, true);
    $content = '';
    foreach ($data['content'] ?? [] as $block) {
        if ($block['type'] === 'text') {
            $content .= $block['text'];
        }
    }

    $tokensUsed = ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0);

    return [
        'success' => true,
        'content' => $content,
        'tokens_used' => $tokensUsed
    ];
}
